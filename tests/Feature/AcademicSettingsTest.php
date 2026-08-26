<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\MasterKurikulum;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicSettingsTest extends TestCase
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

    public function test_tahun_ajaran_date_boundary_dan_normalisasi_input(): void
    {
        // 1. Menolak periode invalid (end_date < start_date)
        $invalidRes = $this->actingAs($this->user)->postJson('/api/master/tahun-ajaran', [
            'name' => '2027/2028',
            'start_date' => '2027-07-01',
            'end_date' => '2026-06-30',
        ]);
        $invalidRes->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);

        // 2. Simpan valid dengan normalisasi whitespace
        $validRes = $this->actingAs($this->user)->postJson('/api/master/tahun-ajaran', [
            'name' => '  2028 / 2029  ',
            'start_date' => '2028-07-01',
            'end_date' => '2029-06-30',
            'is_active' => true,
        ]);
        $validRes->assertCreated()
            ->assertJsonPath('data.name', '2028 / 2029');
    }

    public function test_tahun_ajaran_export_dan_import(): void
    {
        // Export
        $exportRes = $this->actingAs($this->user)->getJson('/api/master/tahun-ajaran/export');
        $exportRes->assertOk()->assertJsonPath('status', 'success');

        // Import
        $importRes = $this->actingAs($this->user)->postJson('/api/master/tahun-ajaran/import', [
            'data' => [
                [
                    'name' => '2030/2031',
                    'start_date' => '2030-07-01',
                    'end_date' => '2031-06-30',
                ],
            ],
        ]);
        $importRes->assertOk()->assertJsonPath('status', 'success');
    }

    public function test_kurikulum_memvalidasi_relasi_semester_tahun_ajaran(): void
    {
        $unit = EducationUnit::create([
            'code' => 'SDIT-TEST',
            'name' => 'SD IT Test',
            'level' => 'SD',
        ]);

        $ay1 = AcademicYear::create([
            'name' => '2025/2026',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);

        $ay2 = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => false,
        ]);

        $semesterAy1 = Semester::create([
            'academic_year_id' => $ay1->id,
            'name' => 'Semester Ganjil 2025/2026',
            'semester_type' => 'odd',
            'start_date' => '2025-07-01',
            'end_date' => '2025-12-31',
            'is_active' => true,
        ]);

        // Kirim semester dari ay1 tetapi tahun_ajaran_id ay2 (invalid mismatch)
        $mismatchRes = $this->actingAs($this->user)->postJson('/api/master/kurikulum', [
            'kode_kurikulum' => 'KUR-MISMATCH',
            'nama_kurikulum' => '  Kurikulum   SIT   Test  ',
            'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $unit->id,
            'jenjang' => 'SD',
            'tahun_ajaran_id' => $ay2->id,
            'semester_id' => $semesterAy1->id,
            'tanggal_mulai' => '2026-07-01',
        ]);

        $mismatchRes->assertUnprocessable()
            ->assertJsonValidationErrors(['semester_id']);
    }
}
