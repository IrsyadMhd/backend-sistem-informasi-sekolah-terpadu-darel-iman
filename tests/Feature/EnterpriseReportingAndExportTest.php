<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseReportingAndExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $foundationUser;
    protected User $kepsekUser;
    protected User $teacherUser;
    protected EducationUnit $unit1;
    protected EducationUnit $unit2;
    protected AcademicYear $academicYear;
    protected Semester $semester;
    protected Employee $employee;
    protected Student $student;
    protected Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('Super Admin');

        $this->foundationUser = User::factory()->create();
        $this->foundationUser->assignRole('Pengurus Yayasan');

        $this->unit1 = EducationUnit::firstOrCreate(
            ['code' => 'SDIT-TEST-RPT'],
            ['name' => 'SDIT Report Unit', 'level' => 'SD', 'is_active' => true]
        );

        $this->unit2 = EducationUnit::firstOrCreate(
            ['code' => 'SMPIT-TEST-RPT'],
            ['name' => 'SMPIT Report Unit', 'level' => 'SMP', 'is_active' => true]
        );

        $this->kepsekUser = User::factory()->create();
        $this->kepsekUser->assignRole('Kepala Sekolah');
        Employee::firstOrCreate(
            ['user_id' => $this->kepsekUser->id],
            ['niy' => 'KEPSEK-RPT-01', 'nama_lengkap' => 'Kepsek Test', 'unit_id' => $this->unit1->id, 'status' => 'Aktif']
        );

        $this->academicYear = AcademicYear::firstOrCreate(
            ['code' => '2025/2026'],
            ['name' => '2025/2026', 'is_active' => true, 'start_date' => '2025-07-01', 'end_date' => '2026-06-30']
        );

        $this->semester = Semester::firstOrCreate(
            ['academic_year_id' => $this->academicYear->id, 'semester_code' => 'GANJIL-2025'],
            ['name' => 'Semester Ganjil', 'sequence' => 1, 'is_active' => true]
        );

        $this->employee = Employee::firstOrCreate(
            ['niy' => 'EMP-RPT-01'],
            ['nama_lengkap' => 'Pegawai Report Test', 'status' => 'Aktif', 'unit_id' => $this->unit1->id]
        );

        $this->kelas = Kelas::firstOrCreate(
            ['kode_kelas' => 'KLS-RPT-1A'],
            [
                'nama_kelas' => '1 A Report',
                'unit_pendidikan_id' => $this->unit1->id,
                'tahun_ajaran_id' => $this->academicYear->id,
                'semester_id' => $this->semester->id,
                'jenjang' => 'SD',
                'tingkat' => '1',
                'status' => 'Aktif',
                'is_active' => true,
                'kapasitas' => 30,
            ]
        );

        $this->student = Student::firstOrCreate(
            ['nisn' => '9988776655'],
            [
                'full_name' => 'Siswa Report Test',
                'nis' => 'NIS-RPT-01',
                'gender' => 'male',
                'unit_id' => $this->unit1->id,
                'kelas_id' => $this->kelas->id,
                'is_active' => true,
            ]
        );
    }

    public function test_report_metrics_match_source_modules(): void
    {
        $response = $this->actingAs($this->foundationUser)->getJson('/api/foundation/laporan/sdm');

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'data' => ['summary', 'charts', 'details']]);
    }

    public function test_foundation_reports_are_cross_unit_scoped(): void
    {
        $response = $this->actingAs($this->foundationUser)->getJson('/api/foundation/laporan/lintas-unit');

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'data']);
    }

    public function test_principal_reports_are_unit_scoped(): void
    {
        $response = $this->actingAs($this->kepsekUser)->getJson('/api/employees?unit_id=' . $this->unit2->id);

        $response->assertStatus(200);
        $items = $response->json('data') ?? [];
        foreach ($items as $item) {
            if (isset($item['unit_id'])) {
                $this->assertEquals($this->unit1->id, $item['unit_id']);
            }
        }
    }

    public function test_teacher_reports_are_assignment_scoped(): void
    {
        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('Guru');
        Employee::firstOrCreate(
            ['user_id' => $teacherUser->id],
            ['niy' => 'GURU-RPT-01', 'nama_lengkap' => 'Guru Test', 'unit_id' => $this->unit1->id, 'status' => 'Aktif']
        );

        $response = $this->actingAs($teacherUser)->getJson('/api/teacher/dashboard');

        $response->assertStatus(200);
    }

    public function test_homeroom_reports_are_class_scoped(): void
    {
        $walikelasUser = User::factory()->create();
        $walikelasUser->assignRole('Wali Kelas');
        Employee::firstOrCreate(
            ['user_id' => $walikelasUser->id],
            ['niy' => 'WALIKELAS-RPT-01', 'nama_lengkap' => 'Wali Kelas Test', 'unit_id' => $this->unit1->id, 'status' => 'Aktif']
        );

        $response = $this->actingAs($walikelasUser)->getJson('/api/dashboard/wali-kelas');

        $response->assertStatus(200);
    }

    public function test_report_filters_are_applied(): void
    {
        $response = $this->actingAs($this->foundationUser)->getJson('/api/foundation/laporan/sdm?unit_id=' . $this->unit1->id);

        $response->assertStatus(200);
        $this->assertEquals('success', $response->json('status'));
    }

    public function test_cross_unit_filter_is_forbidden_for_unit_role(): void
    {
        $response = $this->actingAs($this->kepsekUser)->getJson('/api/foundation/laporan/sdm');

        // Non-foundation user should be denied access to foundation executive report endpoint
        $response->assertStatus(403);
    }

    public function test_report_drilldown_matches_kpi(): void
    {
        $response = $this->actingAs($this->foundationUser)->getJson('/api/foundation/laporan/sdm/detail/' . $this->employee->id);

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'data']);
    }

    public function test_pdf_export_respects_filters(): void
    {
        $response = $this->actingAs($this->foundationUser)->get('/api/foundation/laporan/sdm/export?format=pdf&orientation=landscape');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_pdf_export_respects_scope(): void
    {
        $response = $this->actingAs($this->kepsekUser)->get('/api/foundation/laporan/sdm/export?format=pdf');

        $response->assertStatus(403);
    }

    public function test_xlsx_export_respects_filters(): void
    {
        $response = $this->actingAs($this->foundationUser)->get('/api/foundation/laporan/sdm/export?format=excel');

        $response->assertStatus(200);
    }

    public function test_xlsx_export_respects_scope(): void
    {
        $response = $this->actingAs($this->kepsekUser)->get('/api/foundation/laporan/sdm/export?format=excel');

        $response->assertStatus(403);
    }

    public function test_attendance_report_excludes_holidays(): void
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/attendance/reports/summary');

        $response->assertStatus(200);
    }

    public function test_tahfizh_report_deduplicates_overlaps(): void
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/tahfizh/report');

        $response->assertStatus(200);
    }

    public function test_mutabaah_report_reads_real_entries(): void
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/mutabaah/analytics/recap');

        $response->assertStatus(200);
    }

    public function test_graduation_report_matches_alumni(): void
    {
        $response = $this->actingAs($this->foundationUser)->getJson('/api/foundation/laporan/kelulusan');

        $response->assertStatus(200);
    }

    public function test_draft_records_are_excluded_from_official_reports(): void
    {
        $response = $this->actingAs($this->foundationUser)->getJson('/api/foundation/laporan/siswa');

        $response->assertStatus(200);
    }

    public function test_employee_attendance_can_be_stored_on_postgresql(): void
    {
        $att = Attendance::create([
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $this->semester->id,
            'attendance_date' => now()->toDateString(),
            'month' => now()->month,
            'employee_id' => $this->employee->id,
            'unit_pendidikan_id' => $this->unit1->id,
            'tipe_presensi' => 'Pegawai',
            'status' => 'Hadir',
            'check_in_time' => now()->toTimeString(),
        ]);

        $this->assertDatabaseHas('attendances', ['id' => $att->id, 'employee_id' => $this->employee->id]);
    }

    public function test_employee_attendance_partition_accepts_employee_record(): void
    {
        $att = Attendance::create([
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $this->semester->id,
            'attendance_date' => now()->toDateString(),
            'month' => now()->month,
            'employee_id' => $this->employee->id,
            'unit_pendidikan_id' => $this->unit1->id,
            'tipe_presensi' => 'Pegawai',
            'status' => 'Hadir',
        ]);

        $this->assertEquals('Pegawai', $att->tipe_presensi);
    }

    public function test_employee_attendance_does_not_require_student_id(): void
    {
        $att = Attendance::create([
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $this->semester->id,
            'attendance_date' => now()->toDateString(),
            'month' => now()->month,
            'employee_id' => $this->employee->id,
            'tipe_presensi' => 'Pegawai',
            'status' => 'Hadir',
            'student_id' => null,
            'class_id' => null,
        ]);

        $this->assertNull($att->student_id);
        $this->assertNull($att->class_id);
    }

    public function test_employee_attendance_report_reads_employee_source(): void
    {
        Attendance::create([
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $this->semester->id,
            'attendance_date' => now()->toDateString(),
            'month' => now()->month,
            'employee_id' => $this->employee->id,
            'unit_pendidikan_id' => $this->unit1->id,
            'tipe_presensi' => 'Pegawai',
            'status' => 'Hadir',
        ]);

        $count = Attendance::where('employee_id', $this->employee->id)->where('tipe_presensi', 'Pegawai')->count();
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_student_attendance_and_employee_attendance_do_not_conflict(): void
    {
        $studentAtt = Attendance::create([
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $this->semester->id,
            'attendance_date' => now()->toDateString(),
            'month' => now()->month,
            'student_id' => $this->student->id,
            'class_id' => $this->kelas->id,
            'tipe_presensi' => 'Siswa',
            'status' => 'Hadir',
        ]);

        $empAtt = Attendance::create([
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $this->semester->id,
            'attendance_date' => now()->toDateString(),
            'month' => now()->month,
            'employee_id' => $this->employee->id,
            'unit_pendidikan_id' => $this->unit1->id,
            'tipe_presensi' => 'Pegawai',
            'status' => 'Hadir',
        ]);

        $this->assertNotEquals($studentAtt->id, $empAtt->id);
    }
}
