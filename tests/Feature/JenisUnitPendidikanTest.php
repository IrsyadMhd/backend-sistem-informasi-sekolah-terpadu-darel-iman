<?php

namespace Tests\Feature;

use App\Models\JenisUnitPendidikan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JenisUnitPendidikanTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('Super Admin');
    }

    public function test_dapat_mengambil_daftar_jenis_unit_pendidikan(): void
    {
        JenisUnitPendidikan::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/master/jenis-unit');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'meta',
                'statistik',
            ]);
    }

    public function test_dapat_menambah_jenis_unit_pendidikan_baru(): void
    {
        $payload = [
            'kode_jenis' => 'SDIT',
            'nama_jenis' => 'Sekolah Dasar Islam Terpadu',
            'singkatan' => 'SDIT',
            'jenjang' => 'SD',
            'urutan' => 1,
            'warna_badge' => '#10B981',
            'icon' => 'School',
            'status' => true,
            'keterangan' => 'Unit SDIT Terpadu',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/master/jenis-unit', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.kode_jenis', 'SDIT');

        $this->assertDatabaseHas('master_jenis_unit_pendidikan', [
            'kode_jenis' => 'SDIT',
            'nama_jenis' => 'Sekolah Dasar Islam Terpadu',
        ]);
    }

    public function test_validasi_kode_dan_nama_harus_unik(): void
    {
        JenisUnitPendidikan::factory()->create([
            'kode_jenis' => 'SDIT',
            'nama_jenis' => 'Sekolah Dasar Islam Terpadu',
        ]);

        $payload = [
            'kode_jenis' => 'SDIT',
            'nama_jenis' => 'Sekolah Dasar Islam Terpadu',
            'jenjang' => 'SD',
            'urutan' => 1,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/master/jenis-unit', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['kode_jenis', 'nama_jenis']);
    }

    public function test_dapat_mengubah_jenis_unit_pendidikan(): void
    {
        $jenisUnit = JenisUnitPendidikan::factory()->create([
            'kode_jenis' => 'TKIT',
            'nama_jenis' => 'TK Islam Terpadu',
        ]);

        $payload = [
            'kode_jenis' => 'TKIT',
            'nama_jenis' => 'Taman Kanak-kanak Islam Terpadu Updated',
            'singkatan' => 'TKIT',
            'jenjang' => 'TK',
            'urutan' => 2,
            'warna_badge' => '#3B82F6',
            'icon' => 'Children',
            'status' => true,
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/master/jenis-unit/{$jenisUnit->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.nama_jenis', 'Taman Kanak-kanak Islam Terpadu Updated');
    }

    public function test_dapat_menghapus_jenis_unit_pendidikan_soft_delete(): void
    {
        $jenisUnit = JenisUnitPendidikan::factory()->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/master/jenis-unit/{$jenisUnit->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('master_jenis_unit_pendidikan', [
            'id' => $jenisUnit->id,
        ]);
    }

    public function test_normalisasi_input_kode_dan_nama_jenis_unit(): void
    {
        $payload = [
            'kode_jenis' => ' sdit-norm ',
            'nama_jenis' => '  Sekolah   Dasar   Islam   Terpadu   Norm  ',
            'singkatan' => ' SDIT ',
            'jenjang' => 'SD',
            'urutan' => 1,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/master/jenis-unit', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.kode_jenis', 'SDIT-NORM')
            ->assertJsonPath('data.nama_jenis', 'Sekolah Dasar Islam Terpadu Norm');
    }

    public function test_dapat_mengimpor_dan_mengespor_jenis_unit(): void
    {
        $importPayload = [
            'data' => [
                ['kode_jenis' => 'IMP-01', 'nama_jenis' => 'Jenis Import 1', 'jenjang' => 'SD', 'urutan' => 10],
                ['kode_jenis' => 'IMP-02', 'nama_jenis' => 'Jenis Import 2', 'jenjang' => 'SMP', 'urutan' => 11],
            ],
        ];

        $importRes = $this->actingAs($this->user)
            ->postJson('/api/master/jenis-unit/import', $importPayload);

        $importRes->assertStatus(200)
            ->assertJsonPath('data.berhasil', 2);

        $exportRes = $this->actingAs($this->user)
            ->getJson('/api/master/jenis-unit/export?search=IMP-01');

        $exportRes->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }
}
