<?php

namespace Tests\Feature;

use App\Models\EducationUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EducationUnitTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_dapat_mengambil_daftar_unit_pendidikan(): void
    {
        EducationUnit::query()->create([
            'code' => 'SDIT-01',
            'name' => 'SDIT Dar el-Iman 1',
            'level' => 'SDIT',
            'is_active' => true,
            'metadata' => [
                'city' => 'Padang',
                'province' => 'Sumatera Barat',
                'principal_name' => 'Ust. Ahmad',
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/education-units');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'level', 'description', 'is_active', 'metadata'],
                ],
                'current_page',
                'total',
            ]);
    }

    public function test_dapat_menambah_unit_pendidikan_baru(): void
    {
        $payload = [
            'code' => '',
            'name' => 'SMPIT Dar el-Iman',
            'level' => 'SMPIT',
            'description' => 'Unit Sekolah Menengah Pertama',
            'is_active' => true,
            'metadata' => [
                'npsn' => '12345678',
                'city' => 'Padang',
                'province' => 'Sumatera Barat',
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/education-units', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'SMPIT Dar el-Iman');

        $this->assertDatabaseHas('education_units', [
            'name' => 'SMPIT Dar el-Iman',
        ]);
    }

    public function test_dapat_menambah_unit_pendidikan_kedua_tanpa_kode(): void
    {
        EducationUnit::query()->create([
            'code' => 'SMPIT-ABC',
            'name' => 'SMPIT 1',
        ]);

        $payload = [
            'code' => '',
            'name' => 'SMPIT 2',
            'level' => 'SMPIT',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/education-units', $payload);

        $response->assertStatus(201);
    }

    public function test_dapat_mengubah_unit_pendidikan_dengan_kode_yang_sama(): void
    {
        $unit = EducationUnit::query()->create([
            'code' => 'TKIT-01',
            'name' => 'TKIT 1',
            'level' => 'TKIT',
            'is_active' => true,
        ]);

        $payload = [
            'code' => 'TKIT-01',
            'name' => 'TKIT 1 Dar el-Iman (Diperbarui)',
            'level' => 'TKIT',
            'description' => 'Deskripsi baru',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/education-units/{$unit->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'TKIT 1 Dar el-Iman (Diperbarui)');

        $this->assertDatabaseHas('education_units', [
            'id' => $unit->id,
            'name' => 'TKIT 1 Dar el-Iman (Diperbarui)',
        ]);
    }

    public function test_dapat_menghapus_unit_pendidikan(): void
    {
        $unit = EducationUnit::query()->create([
            'code' => 'SMAIT-01',
            'name' => 'SMAIT Dar el-Iman',
            'level' => 'SMAIT',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/education-units/{$unit->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Data unit pendidikan berhasil dihapus.');

        $this->assertSoftDeleted('education_units', [
            'id' => $unit->id,
        ]);
    }
}
