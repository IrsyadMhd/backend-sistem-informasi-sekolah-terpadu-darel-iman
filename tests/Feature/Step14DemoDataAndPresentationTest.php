<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\QrCredential;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DefaultRoleUserSeeder;
use Database\Seeders\QrCredentialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Step14DemoDataAndPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_all_14_demo_accounts_login_and_resolve_portals_correctly(): void
    {
        $matrix = [
            ['identifier' => 'superadmin@school-erp.local', 'password' => 'Password123!', 'expected_portal' => 'admin'],
            ['identifier' => 'admin@school-erp.local', 'password' => 'Admin@2026!', 'expected_portal' => 'admin'],
            ['identifier' => 'yayasan@school-erp.local', 'password' => 'Yayasan@2026!', 'expected_portal' => 'yayasan'],
            ['identifier' => 'divisi.pendidikan@school-erp.local', 'password' => 'Divisi@2026!', 'expected_portal' => 'divisi'],
            ['identifier' => 'kepsek@school-erp.local', 'password' => 'Kepsek@2026!', 'expected_portal' => 'kepsek'],
            ['identifier' => 'tu@school-erp.local', 'password' => 'TU@2026!', 'expected_portal' => 'tu'],
            ['identifier' => 'guru@school-erp.local', 'password' => 'Guru@2026!', 'expected_portal' => 'teacher'],
            ['identifier' => 'role.wali.kelas@school-erp.local', 'password' => 'Password123!', 'expected_portal' => 'wali_kelas'],
            ['identifier' => 'guru.tahfizh@school-erp.local', 'password' => 'Tahfizh@2026!', 'expected_portal' => 'guru_tahfizh'],
            ['identifier' => 'musyrif@school-erp.local', 'password' => 'Musyrif@2026!', 'expected_portal' => 'musyrif'],
            ['identifier' => 'orangtua@school-erp.local', 'password' => 'OrangTua@2026!', 'expected_portal' => 'parent'],
            ['identifier' => 'siswa@school-erp.local', 'password' => 'Siswa@2026!', 'expected_portal' => 'student'],
            ['identifier' => 'alumni@school-erp.local', 'password' => 'Password123!', 'expected_portal' => 'student'],
        ];

        foreach ($matrix as $account) {
            $response = $this->postJson('/api/auth/login', [
                'identifier' => $account['identifier'],
                'password' => $account['password'],
            ]);

            $response->assertOk()
                ->assertJsonStructure(['token', 'user', 'portal'])
                ->assertJsonPath('default_portal', $account['expected_portal']);
        }
    }

    public function test_parent_demo_account_has_at_least_two_linked_children(): void
    {
        $parentUser = User::where('email', 'orangtua@school-erp.local')->first();
        $this->assertNotNull($parentUser);

        $parent = ParentModel::where('user_id', $parentUser->id)->first();
        $this->assertNotNull($parent);

        $children = $parent->students()->where('is_active', true)->get();
        $this->assertGreaterThanOrEqual(2, $children->count());

        $nisList = $children->pluck('nis')->toArray();
        $this->assertContains('TEST-NIS-023', $nisList);
        $this->assertContains('TEST-NIS-025', $nisList);
    }

    public function test_employee_demo_qr_login_authenticates_demo_guru(): void
    {
        $response = $this->postJson('/api/v2/auth/login/employee-qr', [
            'qr_token' => 'empqr-demo-guru-0017',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user', 'portal'])
            ->assertJsonPath('user.email', 'guru@school-erp.local');
    }

    public function test_student_demo_qr_resolves_student_for_gate_attendance(): void
    {
        $student = Student::where('nis', 'TEST-NIS-023')->first();
        $this->assertNotNull($student);

        $qr = QrCredential::where('student_id', $student->id)->active()->first();
        $this->assertNotNull($qr);

        $response = $this->postJson('/api/v1/auth/qr-credentials/student/'.$student->id);
        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.student_id', $student->id);
    }

    public function test_seeder_is_idempotent_on_second_run(): void
    {
        $userCountBefore = User::count();
        $employeeCountBefore = Employee::count();
        $studentCountBefore = Student::count();

        // Second seed run
        $this->seed(DefaultRoleUserSeeder::class);
        $this->seed(QrCredentialSeeder::class);

        $this->assertEquals($userCountBefore, User::count());
        $this->assertEquals($employeeCountBefore, Employee::count());
        $this->assertEquals($studentCountBefore, Student::count());
    }

    public function test_connected_demo_story_graph_is_non_empty(): void
    {
        $this->assertGreaterThan(0, EducationUnit::count());
        $this->assertGreaterThan(0, AcademicYear::count());
        $this->assertGreaterThan(0, Semester::count());
        $this->assertGreaterThan(0, Subject::count());
        $this->assertGreaterThan(0, Kelas::count());
        $this->assertGreaterThan(0, Teacher::count());
        $this->assertGreaterThan(0, Student::count());
        $this->assertGreaterThan(0, ParentModel::count());
    }
}
