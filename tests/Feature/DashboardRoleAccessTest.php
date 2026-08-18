<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Student;
use App\Models\TahfizhDailyLog;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function createUserWithRole(string $role, array $overrides = []): User
    {
        $user = User::create(array_merge([
            'name' => 'User '.$role,
            'email' => strtolower(str_replace(' ', '.', $role)).'.'.uniqid().'@school-erp.local',
            'password' => Hash::make('Dashboard!2026'),
            'is_active' => true,
        ], $overrides));
        $user->assignRole($role);

        return $user;
    }

    private function actingAsUser(User $user)
    {
        return $this->actingAs($user, 'sanctum');
    }

    public function test_dashboard_endpoints_require_authentication(): void
    {
        $endpoints = [
            '/api/dashboard',
            '/api/dashboard/super-admin',
            '/api/dashboard/kepala-sekolah',
            '/api/dashboard/divisi-pendidikan',
            '/api/dashboard/waka-kurikulum',
            '/api/dashboard/waka-kesiswaan',
            '/api/dashboard/tata-usaha',
            '/api/dashboard/wali-kelas',
            '/api/dashboard/guru-tahfizh',
            '/api/dashboard/guru-bk',
            '/api/dashboard/operator',
            '/api/teacher/dashboard',
            '/api/portal/alumni/dashboard',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)->assertUnauthorized();
        }
    }

    public static function roleDashboardProvider(): array
    {
        return [
            'super admin' => ['Super Admin', '/api/dashboard/super-admin'],
            'kepala sekolah' => ['Kepala Sekolah', '/api/dashboard/kepala-sekolah'],
            'divisi pendidikan' => ['Divisi Pendidikan', '/api/dashboard/divisi-pendidikan'],
            'waka kurikulum' => ['Waka Kurikulum', '/api/dashboard/waka-kurikulum'],
            'waka kesiswaan' => ['Waka Kesiswaan', '/api/dashboard/waka-kesiswaan'],
            'tata usaha' => ['Tata Usaha', '/api/dashboard/tata-usaha'],
            'operator' => ['Operator', '/api/dashboard/operator'],
            'wali kelas' => ['Wali Kelas', '/api/dashboard/wali-kelas'],
            'guru tahfizh' => ['Guru Tahfizh', '/api/dashboard/guru-tahfizh'],
            'guru bk' => ['Guru BK', '/api/dashboard/guru-bk'],
            'guru' => ['Guru', '/api/teacher/dashboard'],
            'alumni' => ['Alumni', '/api/portal/alumni/dashboard'],
        ];
    }

    public function test_each_role_can_access_its_own_dashboard(): void
    {
        foreach (self::roleDashboardProvider() as [$role, $endpoint]) {
            $user = $this->createUserWithRole($role);

            if ($role === 'Alumni') {
                Student::create([
                    'id' => 'stu-alumni-'.uniqid(),
                    'full_name' => 'Alumni',
                    'nisn' => '0000000009',
                    'nis' => 'S-ALUMNI-'.uniqid(),
                    'gender' => 'male',
                    'user_id' => $user->id,
                    'is_active' => false,
                    'metadata' => ['is_alumni' => true, 'status_siswa' => 'alumni'],
                ]);
            }

            $this->actingAsUser($user)
                ->getJson($endpoint)
                ->assertOk();
        }
    }

    public function test_roles_cannot_access_other_role_dashboards(): void
    {
        $teacher = $this->createUserWithRole('Guru');
        $forbidden = [
            '/api/dashboard/super-admin',
            '/api/dashboard/kepala-sekolah',
            '/api/dashboard/divisi-pendidikan',
            '/api/dashboard/waka-kurikulum',
            '/api/dashboard/waka-kesiswaan',
            '/api/dashboard/operator',
            '/api/portal/alumni/dashboard',
        ];

        foreach ($forbidden as $endpoint) {
            $this->actingAsUser($teacher)
                ->getJson($endpoint)
                ->assertForbidden();
        }

        $siswa = $this->createUserWithRole('Siswa');
        $this->actingAsUser($siswa)->getJson('/api/teacher/dashboard')->assertForbidden();
        $this->actingAsUser($siswa)->getJson('/api/portal/alumni/dashboard')->assertForbidden();
    }

    public function test_foundation_dashboard_requires_foundation_permission(): void
    {
        $guru = $this->createUserWithRole('Guru');
        $this->actingAsUser($guru)->getJson('/api/foundation/dashboard')->assertForbidden();

        $yayasan = $this->createUserWithRole('Yayasan');
        $this->actingAsUser($yayasan)->getJson('/api/foundation/dashboard')->assertOk();
    }

    public function test_pemantauan_ringkasan_requires_pemantauan_permission(): void
    {
        $guru = $this->createUserWithRole('Guru');
        $this->actingAsUser($guru)->getJson('/api/dashboard-pemantauan/ringkasan')->assertForbidden();

        $yayasan = $this->createUserWithRole('Yayasan');
        $this->actingAsUser($yayasan)->getJson('/api/dashboard-pemantauan/ringkasan')->assertOk();

        $admin = $this->createUserWithRole('Admin');
        $this->actingAsUser($admin)->getJson('/api/dashboard-pemantauan/ringkasan')->assertOk();
    }

    public function test_guru_tahfizh_without_assignments_returns_zero_not_all_students(): void
    {
        Student::create([
            'id' => 'stu-1-'.uniqid(),
            'full_name' => 'Siswa A',
            'nisn' => '0000000001',
            'nis' => 'S-A-'.uniqid(),
            'gender' => 'male',
            'unit_id' => null,
            'is_active' => true,
            'status' => 'aktif',
        ]);

        $guru = $this->createUserWithRole('Guru Tahfizh');

        $response = $this->actingAsUser($guru)
            ->getJson('/api/dashboard/guru-tahfizh')
            ->assertOk();

        $this->assertSame(0, $response->json('data.kpis.total_siswa_binaan.total'));
    }

    public function test_wali_kelas_class_id_outside_scope_is_ignored(): void
    {
        $unit = \App\Models\EducationUnit::create([
            'code' => 'UNIT-'.uniqid(),
            'name' => 'Unit Wali Kelas',
            'level' => 'SD',
            'is_active' => true,
        ]);
        $year = \App\Models\AcademicYear::create([
            'name' => '2025/2026',
            'start_date' => now(),
            'end_date' => now()->addYear(),
            'is_active' => true,
        ]);
        $semester = \App\Models\Semester::create([
            'name' => 'Ganjil',
            'academic_year_id' => $year->id,
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'id' => 'emp-walas-'.uniqid(),
            'niy' => 'NIY-WALAS-'.uniqid(),
            'nama_lengkap' => 'Wali Kelas A',
            'unit_id' => $unit->id,
            'status' => 'Aktif',
        ]);
        $teacher = Teacher::create([
            'user_id' => null,
            'employee_id' => $employee->id,
            'employee_number' => 'TCH-WALAS-'.uniqid(),
            'full_name' => 'Wali Kelas A',
        ]);

        $classA = Kelas::create([
            'unit_pendidikan_id' => $unit->id,
            'tahun_ajaran_id' => $year->id,
            'semester_id' => $semester->id,
            'jenjang' => 'SD',
            'tingkat' => '1',
            'kode_kelas' => 'ROMBEL-A-'.uniqid(),
            'nama_kelas' => 'Rombel A',
            'wali_kelas_id' => $employee->id,
            'status' => 'Aktif',
        ]);
        $classB = Kelas::create([
            'unit_pendidikan_id' => $unit->id,
            'tahun_ajaran_id' => $year->id,
            'semester_id' => $semester->id,
            'jenjang' => 'SD',
            'tingkat' => '1',
            'kode_kelas' => 'ROMBEL-B-'.uniqid(),
            'nama_kelas' => 'Rombel B',
            'wali_kelas_id' => null,
            'status' => 'Aktif',
        ]);

        Student::create([
            'id' => 'stu-b-'.uniqid(),
            'full_name' => 'Siswa Rombel B',
            'nisn' => '0000000002',
            'nis' => 'S-B-'.uniqid(),
            'gender' => 'male',
            'kelas_id' => $classB->id,
            'unit_id' => $unit->id,
            'is_active' => true,
            'status' => 'aktif',
        ]);

        $walas = $this->createUserWithRole('Wali Kelas');
        $employee->update(['user_id' => $walas->id]);

        // Minta class B (di luar scope wali kelas): harus tetap diarahkan ke rombel sendiri.
        $response = $this->actingAsUser($walas)
            ->getJson('/api/dashboard/wali-kelas?class_id='.$classB->id)
            ->assertOk();

        $this->assertSame($classA->id, $response->json('data.context.rombel.id'));
        $this->assertSame(0, $response->json('data.kpis.total_siswa_rombel.total'));
    }

    public function test_alumni_dashboard_only_returns_own_student_data(): void
    {
        $alumniUser = $this->createUserWithRole('Alumni');
        $otherAlumni = $this->createUserWithRole('Alumni');

        Student::create([
            'id' => 'stu-mine-'.uniqid(),
            'full_name' => 'Alumni Saya',
            'nisn' => '0000000003',
            'nis' => 'S-MINE-'.uniqid(),
            'gender' => 'male',
            'user_id' => $alumniUser->id,
            'is_active' => false,
            'metadata' => ['is_alumni' => true, 'status_siswa' => 'alumni'],
        ]);
        Student::create([
            'id' => 'stu-other-'.uniqid(),
            'full_name' => 'Alumni Lain',
            'nisn' => '0000000004',
            'nis' => 'S-OTHER-'.uniqid(),
            'gender' => 'female',
            'user_id' => $otherAlumni->id,
            'is_active' => false,
            'metadata' => ['is_alumni' => true, 'status_siswa' => 'alumni'],
        ]);

        $response = $this->actingAsUser($alumniUser)
            ->getJson('/api/portal/alumni/dashboard')
            ->assertOk();

        $profile = $response->json('data.profile') ?? $response->json('data.user') ?? [];
        $this->assertSame('Alumni Saya', $profile['full_name'] ?? $profile['name'] ?? null);
    }
}
