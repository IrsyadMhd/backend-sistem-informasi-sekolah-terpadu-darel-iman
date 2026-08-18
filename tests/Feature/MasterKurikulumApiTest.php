<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\MasterKurikulum;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterKurikulumApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected EducationUnit $unit;

    protected AcademicYear $tahun;

    protected Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('Super Admin');

        $this->unit = EducationUnit::create([
            'name' => 'SMP IT Antigravity',
            'code' => 'SMP-AG',
            'level' => 'SMP',
            'is_active' => true,
        ]);

        $this->tahun = AcademicYear::create([
            'name' => '2025/2026',
            'code' => '2025-2026',
            'is_active' => true,
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $this->tahun->id,
            'name' => 'Ganjil',
            'semester_number' => 1,
            'is_active' => true,
        ]);
    }

    public function test_dapat_mengambil_daftar_master_kurikulum(): void
    {
        MasterKurikulum::create([
            'kode_kurikulum' => 'KUR-API-01',
            'nama_kurikulum' => 'Kurikulum Merdeka SMP',
            'jenis_kurikulum' => 'Merdeka',
            'unit_pendidikan_id' => $this->unit->id,
            'jenjang' => 'SMP',
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'tanggal_mulai' => '2025-07-15',
            'status' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/master/kurikulum');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'meta',
                'statistik',
            ]);
    }

    public function test_dapat_menambah_master_kurikulum_via_api(): void
    {
        $payload = [
            'kode_kurikulum' => 'KUR-API-STORE',
            'nama_kurikulum' => 'Kurikulum Baru via REST API',
            'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $this->unit->id,
            'jenjang' => 'SMP',
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'tanggal_mulai' => '2025-07-15',
            'status' => true,
            'deskripsi' => 'Testing API Store Endpoint',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/master/kurikulum', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.kode_kurikulum', 'KUR-API-STORE');

        $this->assertDatabaseHas('master_kurikulum', [
            'kode_kurikulum' => 'KUR-API-STORE',
        ]);
    }

    public function test_dapat_melihat_detail_kurikulum(): void
    {
        $kurikulum = MasterKurikulum::create([
            'kode_kurikulum' => 'KUR-API-SHOW',
            'nama_kurikulum' => 'Kurikulum Show Detail',
            'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $this->unit->id,
            'jenjang' => 'SMP',
            'tahun_ajaran_id' => $this->tahun->id,
            'tanggal_mulai' => '2025-07-15',
            'status' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/master/kurikulum/{$kurikulum->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $kurikulum->id)
            ->assertJsonPath('data.nama_kurikulum', 'Kurikulum Show Detail');
    }

    public function test_dapat_mengubah_kurikulum_via_api(): void
    {
        $kurikulum = MasterKurikulum::create([
            'kode_kurikulum' => 'KUR-API-PUT',
            'nama_kurikulum' => 'Kurikulum Sebelum Edit',
            'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $this->unit->id,
            'jenjang' => 'SMP',
            'tahun_ajaran_id' => $this->tahun->id,
            'tanggal_mulai' => '2025-07-15',
            'status' => true,
        ]);

        $payload = [
            'nama_kurikulum' => 'Kurikulum Setelah Edit API',
            'status' => false,
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/master/kurikulum/{$kurikulum->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.nama_kurikulum', 'Kurikulum Setelah Edit API');
    }

    public function test_dapat_menghapus_dan_memulihkan_kurikulum_via_api(): void
    {
        $kurikulum = MasterKurikulum::create([
            'kode_kurikulum' => 'KUR-API-DEL',
            'nama_kurikulum' => 'Kurikulum Untuk Delete',
            'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $this->unit->id,
            'jenjang' => 'SMP',
            'tahun_ajaran_id' => $this->tahun->id,
            'tanggal_mulai' => '2025-07-15',
            'status' => true,
        ]);

        // Soft Delete
        $delResponse = $this->actingAs($this->user)
            ->deleteJson("/api/master/kurikulum/{$kurikulum->id}");

        $delResponse->assertStatus(200);
        $this->assertSoftDeleted('master_kurikulum', ['id' => $kurikulum->id]);

        // Restore
        $restoreResponse = $this->actingAs($this->user)
            ->postJson("/api/master/kurikulum/{$kurikulum->id}/restore");

        $restoreResponse->assertStatus(200);
        $this->assertDatabaseHas('master_kurikulum', [
            'id' => $kurikulum->id,
            'deleted_at' => null,
        ]);
    }
}
