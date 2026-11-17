<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\MasterKurikulum;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageTwoScopeAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    public function test_employee_positions_endpoint_returns_template_positions_for_new_unit_without_employees(): void
    {
        $newUnit = EducationUnit::factory()->create(['name' => "Ma'had Abu Ja'far"]);

        Position::create([
            'code' => 'TPL-GURU',
            'name' => 'Guru Mata Pelajaran',
            'satuan_kerja' => 'Unit Pendidikan',
            'unit_sekolah_id' => null,
            'level_jabatan' => 5,
            'scope_akses' => 'unit_sendiri',
            'is_active' => true,
        ]);

        Position::create([
            'code' => 'YAY-INTI',
            'name' => 'Pengurus Yayasan',
            'satuan_kerja' => 'Pengurus',
            'unit_sekolah_id' => null,
            'level_jabatan' => 2,
            'scope_akses' => 'semua_unit',
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['email' => 'sa-pos@school.test', 'is_active' => true]);
        $admin->assignRole('Super Admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/employees/positions?unit_id={$newUnit->id}")
            ->assertOk();

        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('TPL-GURU', $codes);
        $this->assertNotContains('YAY-INTI', $codes);
    }

    public function test_kurikulum_stats_are_unit_scoped_for_unit_users(): void
    {
        $unitA = EducationUnit::factory()->create();
        $unitB = EducationUnit::factory()->create();
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        MasterKurikulum::create([
            'kode_kurikulum' => 'KUR-UNITA-1',
            'nama_kurikulum' => 'Kurikulum Unit A',
            'jenis_kurikulum' => 'Merdeka',
            'jenjang' => 'SD',
            'unit_pendidikan_id' => $unitA->id,
            'tahun_ajaran_id' => $year->id,
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'status' => true,
        ]);

        MasterKurikulum::create([
            'kode_kurikulum' => 'KUR-UNITB-1',
            'nama_kurikulum' => 'Kurikulum Unit B',
            'jenis_kurikulum' => 'Merdeka',
            'jenjang' => 'SMP',
            'unit_pendidikan_id' => $unitB->id,
            'tahun_ajaran_id' => $year->id,
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'status' => true,
        ]);

        $tuA = User::factory()->create(['email' => 'tu-kurikulum@school.test', 'is_active' => true]);
        $tuA->assignRole('Tata Usaha');
        Employee::factory()->create(['user_id' => $tuA->id, 'unit_id' => $unitA->id]);

        $indexRes = $this->actingAs($tuA, 'sanctum')
            ->getJson('/api/master/kurikulum')
            ->assertOk();

        $this->assertSame(1, $indexRes->json('statistik.total'));
        $this->assertSame(1, $indexRes->json('meta.total'));

        $statsRes = $this->actingAs($tuA, 'sanctum')
            ->getJson('/api/master/kurikulum/stats')
            ->assertOk();

        $this->assertSame(1, $statsRes->json('data.total'));
    }
}
