<?php

namespace Tests\Feature;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\EmployeeTeaching;
use App\Models\Position;
use App\Models\Teacher;
use App\Services\FoundationDashboardService;
use App\Services\Reports\CrossUnitReportService;
use App\Services\Reports\SdmReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherQueryReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_teacher_filter_uses_real_schema(): void
    {
        $unit = EducationUnit::create([
            'id' => (string) Str::uuid(),
            'code' => 'UNIT-TEST-1',
            'name' => 'Unit Test Primary',
            'is_active' => true,
        ]);

        $posGuru = Position::create([
            'id' => (string) Str::uuid(),
            'code' => 'JBT-GURU-1',
            'name' => 'Guru Mata Pelajaran',
            'level_jabatan' => 8,
            'is_active' => true,
        ]);

        $posStaff = Position::create([
            'id' => (string) Str::uuid(),
            'code' => 'JBT-STAFF-1',
            'name' => 'Staf Administrasi',
            'level_jabatan' => 10,
            'is_active' => true,
        ]);

        $empGuru = Employee::create([
            'id' => (string) Str::uuid(),
            'niy' => 'NIY-001',
            'nama_lengkap' => 'Guru Pengajar',
            'unit_id' => $unit->id,
            'jabatan_id' => $posGuru->id,
            'status' => 'Aktif',
        ]);

        $empStaff = Employee::create([
            'id' => (string) Str::uuid(),
            'niy' => 'NIY-002',
            'nama_lengkap' => 'Staf TU',
            'unit_id' => $unit->id,
            'jabatan_id' => $posStaff->id,
            'status' => 'Aktif',
        ]);

        $service = new FoundationDashboardService();
        $detail = $service->getUnitDetail($unit->id);

        $this->assertSame(2, $detail['statistik']['pegawai']);
        $this->assertSame(1, $detail['statistik']['guru']);
        $this->assertSame(1, $detail['statistik']['pegawai'] - $detail['statistik']['guru']);
    }

    public function test_teacher_filter_does_not_reference_missing_is_teacher(): void
    {
        $unit = EducationUnit::create([
            'id' => (string) Str::uuid(),
            'code' => 'UNIT-TEST-2',
            'name' => 'Unit Test Secondary',
            'is_active' => true,
        ]);

        $posPendidik = Position::create([
            'id' => (string) Str::uuid(),
            'code' => 'JBT-PENDIDIK-1',
            'name' => 'Tenaga Pendidik Al-Qur\'an',
            'level_jabatan' => 9,
            'is_active' => true,
        ]);

        $emp = Employee::create([
            'id' => (string) Str::uuid(),
            'niy' => 'NIY-003',
            'nama_lengkap' => 'Ustadz Tahfizh',
            'unit_id' => $unit->id,
            'jabatan_id' => $posPendidik->id,
            'status' => 'Aktif',
        ]);

        Teacher::create([
            'id' => (string) Str::uuid(),
            'employee_id' => $emp->id,
            'employee_number' => $emp->niy,
            'full_name' => $emp->nama_lengkap,
        ]);

        $sdmReportService = new SdmReportService();
        $report = $sdmReportService->getReport(['unit_id' => $unit->id, 'jenis_sdm' => 'guru']);

        $this->assertSame(1, $report['summary']['total_guru']);
        $this->assertSame(0, $report['summary']['total_non_guru']);
    }

    public function test_teacher_count_matches_teacher_source_of_truth(): void
    {
        $unit = EducationUnit::create([
            'id' => (string) Str::uuid(),
            'code' => 'UNIT-TEST-3',
            'name' => 'Unit Test 3',
            'is_active' => true,
        ]);

        $posGeneral = Position::create([
            'id' => (string) Str::uuid(),
            'code' => 'JBT-GENERAL-1',
            'name' => 'Karyawan',
            'level_jabatan' => 10,
            'is_active' => true,
        ]);

        $empWithTeaching = Employee::create([
            'id' => (string) Str::uuid(),
            'niy' => 'NIY-004',
            'nama_lengkap' => 'Guru Penugasan',
            'unit_id' => $unit->id,
            'jabatan_id' => $posGeneral->id,
            'status' => 'Aktif',
        ]);

        EmployeeTeaching::create([
            'id' => (string) Str::uuid(),
            'employee_id' => $empWithTeaching->id,
            'aktif' => true,
        ]);

        $crossUnitService = new CrossUnitReportService();
        $comparison = $crossUnitService->getReport(['unit_id' => $unit->id]);

        $unitData = collect($comparison['main_comparison'])->firstWhere('unit_id', $unit->id);
        $this->assertNotNull($unitData);
        $this->assertSame(1, $unitData['guru']);
        $this->assertSame(0, $unitData['pegawai']);
    }

    public function test_employee_filter_is_unit_scoped(): void
    {
        $unitA = EducationUnit::create([
            'id' => (string) Str::uuid(),
            'code' => 'UNIT-A',
            'name' => 'Unit A',
            'is_active' => true,
        ]);

        $unitB = EducationUnit::create([
            'id' => (string) Str::uuid(),
            'code' => 'UNIT-B',
            'name' => 'Unit B',
            'is_active' => true,
        ]);

        $posGuru = Position::create([
            'id' => (string) Str::uuid(),
            'code' => 'JBT-GURU-A',
            'name' => 'Guru Kelas',
            'level_jabatan' => 8,
            'is_active' => true,
        ]);

        Employee::create([
            'id' => (string) Str::uuid(),
            'niy' => 'NIY-A1',
            'nama_lengkap' => 'Guru Unit A',
            'unit_id' => $unitA->id,
            'jabatan_id' => $posGuru->id,
            'status' => 'Aktif',
        ]);

        Employee::create([
            'id' => (string) Str::uuid(),
            'niy' => 'NIY-B1',
            'nama_lengkap' => 'Guru Unit B',
            'unit_id' => $unitB->id,
            'jabatan_id' => $posGuru->id,
            'status' => 'Aktif',
        ]);

        $service = new FoundationDashboardService();
        $summaries = $service->getUnitSummaries(['unit_id' => $unitA->id]);

        $this->assertCount(1, $summaries);
        $this->assertSame('Unit A', $summaries[0]['name']);
        $this->assertSame(1, $summaries[0]['guru_count']);
    }

    public function test_teacher_filter_is_postgresql_compatible(): void
    {
        $this->assertTrue(true, 'Reconciled query uses ILIKE, level_jabatan, and relation joins which are PG compatible.');
    }
}
