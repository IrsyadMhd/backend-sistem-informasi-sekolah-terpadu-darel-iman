<?php

namespace Tests\Feature;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentUnitScopeAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_unit_user_only_reads_and_manages_students_in_its_unit(): void
    {
        [$unitA, $unitB] = $this->educationUnits();
        $user = $this->unitAdministrationUser($unitA);
        $studentA = $this->student($unitA, 'Siswa Unit A');
        $studentB = $this->student($unitB, 'Siswa Unit B');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/students')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $studentA->id);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/students/{$studentB->id}")
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/students/{$studentB->id}", $this->studentPayload('Siswa Unit B Diubah', $unitB->id))
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/students/{$studentB->id}")
            ->assertNotFound();
    }

    public function test_unit_user_cannot_create_student_in_another_unit_and_defaults_to_its_own_unit(): void
    {
        [$unitA, $unitB] = $this->educationUnits();
        $user = $this->unitAdministrationUser($unitA);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/students', $this->studentPayload('Siswa Lintas Unit', $unitB->id))
            ->assertForbidden();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/students', $this->studentPayload('Siswa Unit Default'))
            ->assertCreated()
            ->assertJsonPath('data.unit_id', $unitA->id);

        $this->assertDatabaseHas('students', [
            'id' => $response->json('data.id'),
            'unit_id' => $unitA->id,
        ]);
    }

    public function test_student_class_assignment_uses_active_kelas_and_rejects_another_unit(): void
    {
        [$unitA, $unitB] = $this->educationUnits();
        $user = $this->unitAdministrationUser($unitA);
        $kelasA = $this->kelas($unitA, 'Kelas Unit A');
        $kelasB = $this->kelas($unitB, 'Kelas Unit B');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/students', $this->studentPayload('Siswa Berkelas', null, $kelasA->id))
            ->assertCreated()
            ->assertJsonPath('data.kelas_id', $kelasA->id);

        $this->assertDatabaseHas('students', [
            'id' => $response->json('data.id'),
            'kelas_id' => $kelasA->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/students', $this->studentPayload('Siswa Kelas Lintas Unit', null, $kelasB->id))
            ->assertForbidden();
    }

    public function test_role_without_student_permission_cannot_access_student_api(): void
    {
        $teacher = User::create([
            'name' => 'Guru Tanpa Akses Siswa',
            'email' => 'guru.tanpa.akses.siswa@school-erp.local',
            'password' => Hash::make('GuruAkses!2026'),
            'is_active' => true,
        ]);
        $teacher->assignRole('Guru');

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/students')
            ->assertForbidden();
    }

    public function test_division_monitoring_role_can_read_its_scope_but_cannot_mutate_students(): void
    {
        [$unitA] = $this->educationUnits();
        $division = User::create([
            'name' => 'Divisi Pendidikan Monitoring',
            'email' => 'divisi.monitoring@school-erp.local',
            'password' => Hash::make('DivisiAkses!2026'),
            'is_active' => true,
        ]);
        $division->assignRole('Divisi Pendidikan');
        Employee::create([
            'niy' => 'NIY-DIVISI-MONITORING',
            'nama_lengkap' => 'Divisi Pendidikan Monitoring',
            'unit_id' => $unitA->id,
            'user_id' => $division->id,
            'status' => 'Aktif',
        ]);
        $student = $this->student($unitA, 'Siswa Monitoring Divisi');

        $this->actingAs($division, 'sanctum')
            ->getJson('/api/students')
            ->assertOk()
            ->assertJsonPath('data.0.id', $student->id);

        $this->actingAs($division, 'sanctum')
            ->postJson('/api/students', $this->studentPayload('Siswa Baru oleh Divisi', $unitA->id))
            ->assertForbidden();
        $this->actingAs($division, 'sanctum')
            ->putJson("/api/students/{$student->id}", $this->studentPayload('Siswa Diubah Divisi', $unitA->id))
            ->assertForbidden();
        $this->actingAs($division, 'sanctum')
            ->deleteJson("/api/students/{$student->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'full_name' => 'Siswa Monitoring Divisi',
            'deleted_at' => null,
        ]);
    }

    public function test_student_dashboard_uses_scoped_database_values(): void
    {
        [$unitA, $unitB] = $this->educationUnits();
        $user = $this->unitAdministrationUser($unitA);
        $this->student($unitA, 'Siswa Mutasi Unit A', [
            'metadata' => ['mutasi_type' => 'masuk'],
        ]);
        $this->student($unitB, 'Siswa Mutasi Unit B', [
            'metadata' => ['mutasi_type' => 'masuk'],
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/students/dashboard')
            ->assertOk()
            ->assertJsonPath('akses.semua_unit', false)
            ->assertJsonPath('akses.unit_id', $unitA->id)
            ->assertJsonPath('statistik.total_siswa', 1)
            ->assertJsonPath('laporan_siswa.mutasi_masuk', 1);
    }

    private function educationUnits(): array
    {
        return [
            EducationUnit::create([
                'code' => 'UNIT-A',
                'name' => 'Unit A',
                'level' => 'SD',
                'is_active' => true,
            ]),
            EducationUnit::create([
                'code' => 'UNIT-B',
                'name' => 'Unit B',
                'level' => 'SMP',
                'is_active' => true,
            ]),
        ];
    }

    private function unitAdministrationUser(EducationUnit $unit): User
    {
        $user = User::create([
            'name' => 'Tata Usaha Unit',
            'email' => "tu.{$unit->code}@school-erp.local",
            'password' => Hash::make('TataUsahaAkses!2026'),
            'is_active' => true,
        ]);
        $user->assignRole('Tata Usaha');
        // Test ini mengisolasi data scope CRUD. Permission delete diberikan
        // eksplisit karena baseline TU tidak otomatis memegang seluruh aksi arsip.
        $user->givePermissionTo('student.delete');

        Employee::create([
            'niy' => "NIY-{$unit->code}",
            'nama_lengkap' => 'Pegawai Tata Usaha',
            'unit_id' => $unit->id,
            'user_id' => $user->id,
            'status' => 'Aktif',
        ]);

        return $user;
    }

    private function student(EducationUnit $unit, string $name, array $overrides = []): Student
    {
        return Student::create(array_merge([
            'unit_id' => $unit->id,
            'nis' => 'NIS-'.str()->upper(str()->random(10)),
            'full_name' => $name,
            'gender' => 'male',
            'is_active' => true,
            'metadata' => [],
        ], $overrides));
    }

    private function studentPayload(string $name, ?string $unitId = null, ?string $kelasId = null): array
    {
        return array_filter([
            'unit_id' => $unitId,
            'nis' => 'NIS-'.str()->upper(str()->random(10)),
            'full_name' => $name,
            'gender' => 'male',
            'is_active' => true,
            'kelas_id' => $kelasId,
        ], fn ($value) => $value !== null);
    }

    private function kelas(EducationUnit $unit, string $name): Kelas
    {
        $year = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]
        );
        $semester = Semester::firstOrCreate(
            ['academic_year_id' => $year->id, 'sequence' => 1],
            ['name' => 'Ganjil', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]
        );

        return Kelas::create([
            'unit_pendidikan_id' => $unit->id,
            'tahun_ajaran_id' => $year->id,
            'semester_id' => $semester->id,
            'jenjang' => $unit->level,
            'tingkat' => '1',
            'kode_kelas' => 'KLS-'.str()->upper(str()->random(8)),
            'nama_kelas' => $name,
            'status' => 'Aktif',
        ]);
    }
}
