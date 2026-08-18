<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JabatanTest extends TestCase
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

    public function test_dapat_menambah_jabatan_baru_dengan_deskripsi(): void
    {
        $payload = [
            'kode_jabatan' => 'JBT-999',
            'nama_jabatan' => 'Bendahara Yayasan',
            'satuan_kerja' => 'Pengurus',
            'scope_akses' => 'semua_unit',
            'level_jabatan' => 2,
            'urutan' => 1,
            'warna' => '#3B82F6',
            'ikon' => 'UserCheck',
            'deskripsi' => 'Bertanggung jawab atas pengelolaan keuangan yayasan',
            'status' => 'Aktif',
            'tampil_struktur' => true,
            'boleh_login' => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/jabatan', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.nama_jabatan', 'Bendahara Yayasan')
            ->assertJsonPath('data.deskripsi', 'Bertanggung jawab atas pengelolaan keuangan yayasan');

        $this->assertDatabaseHas('positions', [
            'code' => 'JBT-999',
            'name' => 'Bendahara Yayasan',
            'description' => 'Bertanggung jawab atas pengelolaan keuangan yayasan',
            'satuan_kerja' => 'Pengurus',
            'scope_akses' => 'semua_unit',
        ]);
    }

    public function test_dapat_menambah_jabatan_baru_dengan_satuan_kerja_dan_scope(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/jabatan', [
            'nama_jabatan' => 'Koordinator Laboratorium',
            'satuan_kerja' => 'Unit Pendidikan',
            'scope_akses' => 'unit_sendiri',
            'level_jabatan' => 10,
            'status' => 'Aktif',
            'tampil_struktur' => true,
            'boleh_login' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.satuan_kerja', 'Unit Pendidikan')
            ->assertJsonPath('data.scope_akses', 'unit_sendiri');
    }

    public function test_menolak_satuan_kerja_dan_scope_tidak_valid(): void
    {
        $this->actingAs($this->user)->postJson('/api/jabatan', [
            'nama_jabatan' => 'Jabatan Tidak Valid',
            'satuan_kerja' => 'Tidak Dikenal',
            'scope_akses' => 'bebas',
            'level_jabatan' => 10,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['satuan_kerja', 'scope_akses']);
    }

    public function test_kepala_sekolah_tidak_dapat_mengubah_atau_menghapus_jabatan_pengurus_yayasan(): void
    {
        $positionYayasan = \App\Models\Position::create([
            'code' => 'JBT-YYS',
            'name' => 'Pengurus Yayasan',
            'satuan_kerja' => 'Pengurus',
            'scope_akses' => 'semua_unit',
            'level_jabatan' => 1,
            'is_active' => true,
        ]);

        $kepsekUser = User::factory()->create();
        $kepsekUser->assignRole('Kepala Sekolah');

        // Test update restricted
        $updateResponse = $this->actingAs($kepsekUser)
            ->putJson("/api/jabatan/{$positionYayasan->id}", [
                'nama_jabatan' => 'Pengurus Yayasan Modified',
            ]);

        $updateResponse->assertStatus(403)
            ->assertJsonPath('status', 'error');

        // Test delete restricted
        $deleteResponse = $this->actingAs($kepsekUser)
            ->deleteJson("/api/jabatan/{$positionYayasan->id}");

        $deleteResponse->assertStatus(403)
            ->assertJsonPath('status', 'error');
    }
}
