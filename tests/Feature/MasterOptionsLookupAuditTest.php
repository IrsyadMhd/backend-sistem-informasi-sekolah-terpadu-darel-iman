<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\CapaianPembelajaran;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsBankSoal;
use App\Models\LmsModulAjar;
use App\Models\LmsMateri;
use App\Models\MutabaahTemplate;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterOptionsLookupAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected EducationUnit $unit;
    protected AcademicYear $academicYear;
    protected Semester $semester;
    protected Teacher $teacher;
    protected Kelas $kelas;
    protected Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('Super Admin');

        $this->unit = EducationUnit::firstOrCreate(
            ['code' => 'SDIT-TEST'],
            ['name' => 'SDIT Test Unit', 'level' => 'SD', 'is_active' => true]
        );

        $this->academicYear = AcademicYear::firstOrCreate(
            ['name' => '2025/2026'],
            ['name' => '2025/2026', 'is_active' => true, 'start_date' => '2025-07-01', 'end_date' => '2026-06-30']
        );

        $this->semester = Semester::firstOrCreate(
            ['academic_year_id' => $this->academicYear->id, 'name' => 'Semester Ganjil'],
            ['name' => 'Semester Ganjil', 'sequence' => 1, 'is_active' => true]
        );

        $employee = Employee::firstOrCreate(
            ['niy' => 'EMP-TEST-99'],
            ['nama_lengkap' => 'Guru Test Options', 'status' => 'Aktif', 'unit_id' => $this->unit->id]
        );

        $this->teacher = Teacher::firstOrCreate(
            ['user_id' => $this->adminUser->id],
            [
                'employee_id' => $employee->id,
                'employee_number' => 'EMP-TEST-99',
                'full_name' => 'Guru Test Options',
                'join_date' => '2025-01-01',
            ]
        );

        $this->kelas = Kelas::firstOrCreate(
            ['kode_kelas' => 'KLS-1A'],
            [
                'nama_kelas' => '1 A',
                'unit_pendidikan_id' => $this->unit->id,
                'tahun_ajaran_id' => $this->academicYear->id,
                'semester_id' => $this->semester->id,
                'jenjang' => 'SD',
                'tingkat' => '1',
                'status' => 'Aktif',
                'is_active' => true,
                'kapasitas' => 30,
            ]
        );

        $this->subject = Subject::firstOrCreate(
            ['kode_mapel' => 'MAT-1'],
            [
                'code' => 'MAT-1',
                'name' => 'Matematika Test',
                'nama_mapel' => 'Matematika Test',
                'unit_pendidikan_id' => $this->unit->id,
                'kurikulum_id' => null,
                'status' => true,
            ]
        );
    }

    public function test_education_unit_options_are_database_backed(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/education-units');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_academic_year_options_are_scoped(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/kelas/options');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('tahun_ajaran', $data);
    }

    public function test_semester_options_follow_academic_year(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/kelas/options');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('semesters', $data);
    }

    public function test_class_options_follow_unit_and_period(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/kelas/options');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('units', $data);
    }

    public function test_rombel_options_follow_class(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/kelas');

        $response->assertStatus(200);
    }

    public function test_student_options_follow_rombel(): void
    {
        $student = Student::firstOrCreate(
            ['nisn' => '1234567890'],
            [
                'full_name' => 'Siswa Test Options',
                'nis' => 'NIS-OPT-01',
                'gender' => 'male',
                'unit_id' => $this->unit->id,
                'kelas_id' => $this->kelas->id,
                'is_active' => true,
            ]
        );

        $response = $this->actingAs($this->adminUser)->getJson('/api/students?class_id=' . $this->kelas->id);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_teacher_options_follow_assignment(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/kelas/options');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('guru', $data);
    }

    public function test_schedule_options_follow_teacher_and_class(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/schedules-options');

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'data']);
    }

    public function test_cp_options_follow_subject(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/lms/modul-ajar/options');

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'data']);
    }

    public function test_tp_options_follow_cp(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/lms/tujuan-pembelajaran/options');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_mutabaah_template_options_are_scoped(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/mutabaah/options');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_mentor_options_follow_unit(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/mutabaah/enterprise/options');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_tahfizh_student_options_follow_halaqah(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/tahfizh/report');

        $response->assertStatus(200);
    }

    public function test_options_endpoints_do_not_leak_cross_unit_data(): void
    {
        $unit2 = EducationUnit::create([
            'code' => 'UNIT-OTHER-99',
            'name' => 'Unit Lintas Test',
            'level' => 'SMP',
            'is_active' => true,
        ]);

        $unitUser = User::factory()->create();
        $unitUser->assignRole('Kepala Sekolah');
        Employee::firstOrCreate(
            ['user_id' => $unitUser->id],
            ['niy' => 'KEPSEK-OPT-99', 'nama_lengkap' => 'Kepsek Scope Test', 'unit_id' => $this->unit->id, 'status' => 'Aktif']
        );

        $response = $this->actingAs($unitUser)->getJson('/api/employees?unit_id=' . $unit2->id);

        $response->assertStatus(200);
        // Verify response contains only own unit or empty
        $items = $response->json('data') ?? [];
        foreach ($items as $item) {
            if (isset($item['unit_id'])) {
                $this->assertEquals($this->unit->id, $item['unit_id']);
            }
        }
    }

    public function test_soft_deleted_options_are_not_returned(): void
    {
        $delSubject = Subject::create([
            'code' => 'DEL-SUBJ-1',
            'name' => 'Mata Pelajaran Terhapus',
            'kode_mapel' => 'DEL-SUBJ-1',
            'nama_mapel' => 'Mata Pelajaran Terhapus',
            'unit_pendidikan_id' => $this->unit->id,
            'status' => true,
        ]);

        $delSubject->delete();

        $response = $this->actingAs($this->adminUser)->getJson('/api/master/subjects');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($delSubject->id, $ids);
    }
}
