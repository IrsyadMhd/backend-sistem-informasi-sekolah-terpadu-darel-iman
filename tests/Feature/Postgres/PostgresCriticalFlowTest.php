<?php

namespace Tests\Feature\Postgres;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PostgreSQL Certification — Critical Flow API Smoke
 *
 * Menguji alur login + endpoint kritis terhadap PostgreSQL runtime.
 * Ekspektasi: tidak ada schema-related 500 (42703, 42P01, 23502, 23503, 22P02).
 */
class PostgresCriticalFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL certification suite hanya berjalan di driver pgsql.');
        }

        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    protected function loginAsSuperAdmin(): array
    {
        $response = $this->postJson('/api/v2/auth/login/admin', [
            'username' => 'superadmin@school-erp.local',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('portal', $data);

        return ['token' => $data['token'], 'user' => $data['user']];
    }

    protected function activePeriod(): array
    {
        $academicYear = \App\Models\AcademicYear::where('is_active', true)->first()
            ?? \App\Models\AcademicYear::latest()->first();
        $semester = \App\Models\Semester::where('is_active', true)->first()
            ?? \App\Models\Semester::latest()->first();

        return [
            'academic_year_id' => $academicYear?->id,
            'semester_id' => $semester?->id,
        ];
    }

    protected function authGet(string $token, string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($token)->getJson($uri);
    }

    public function test_login_superadmin_tidak_error_schema(): void
    {
        $login = $this->loginAsSuperAdmin();
        $this->assertNotEmpty($login['token']);
    }

    public function test_dashboard_super_admin_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/dashboard/super-admin');
        $response->assertStatus(200);
    }

    public function test_education_units_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/education-units');
        $response->assertStatus(200);
    }

    public function test_employees_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/employees');
        $response->assertStatus(200);
    }

    public function test_employees_dashboard_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/employees/dashboard');
        $response->assertStatus(200);
    }

    public function test_students_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/students');
        $response->assertStatus(200);
    }

    public function test_students_dashboard_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/students/dashboard');
        $response->assertStatus(200);
    }

    public function test_attendance_stats_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/attendance/stats');
        $response->assertStatus(200);
    }

    public function test_attendance_index_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/attendance');
        $response->assertStatus(200);
    }

    public function test_lms_penugasan_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/lms/penugasan');
        $response->assertStatus(200);
    }

    public function test_lms_ujian_stats_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/lms/ujian/stats');
        $response->assertStatus(200);
    }

    public function test_tahfizh_report_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/tahfizh/report');
        $response->assertStatus(200);
    }

    public function test_notifications_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/notifications');
        $response->assertStatus(200);
    }

    public function test_notifications_unread_count_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/notifications/unread-count');
        $response->assertStatus(200);
    }

    public function test_profile_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/profile');
        $response->assertStatus(200);
    }

    public function test_kelas_options_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/kelas/options');
        $response->assertStatus(200);
    }

    public function test_kelas_index_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/kelas');
        $response->assertStatus(200);
    }

    public function test_mutabaah_analytics_dashboard_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/mutabaah/analytics/dashboard');
        $response->assertStatus(200);
    }

    public function test_mutabaah_enterprise_options_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/mutabaah/options');
        $response->assertStatus(200);
    }

    public function test_grades_rekap_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $period = $this->activePeriod();
        $response = $this->authGet($token, '/api/grades/rekap?semester_id='.$period['semester_id']);
        $response->assertStatus(200);
    }

    public function test_foundation_dashboard_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/foundation/dashboard');
        $response->assertStatus(200);
    }

    public function test_foundation_units_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/foundation/units');
        $response->assertStatus(200);
    }

    public function test_foundation_employees_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/foundation/employees');
        $response->assertStatus(200);
    }

    public function test_foundation_students_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/foundation/students');
        $response->assertStatus(200);
    }

    public function test_worship_attendance_templates_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/worship-attendance/templates');
        $response->assertStatus(200);
    }

    public function test_worship_attendance_sessions_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/worship-attendance/sessions');
        $response->assertStatus(200);
    }

    public function test_chat_employee_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/employee/chat/conversations');
        $response->assertStatus(200);
    }

    public function test_lms_rapor_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/lms/rapor');
        $response->assertStatus(200);
    }

    public function test_lms_presensi_stats_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/lms/presensi/stats');
        $response->assertStatus(200);
    }

    public function test_employee_chats_scope_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/chat/employee/contacts');
        $response->assertStatus(200);
    }

    public function test_attendance_workflow_dashboard_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/attendance/teacher/dashboard');
        $response->assertStatus(200);
    }

    public function test_lesson_attendance_sessions_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/lesson-attendance/sessions');
        $response->assertStatus(200);
    }

    public function test_gate_attendance_stats_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/gate-attendance/stats');
        $response->assertStatus(200);
    }

    public function test_attendance_export_rekap_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/attendance/report');
        $response->assertStatus(200);
    }

    public function test_employee_attendance_tidak_error_schema(): void
    {
        $token = $this->loginAsSuperAdmin()['token'];
        $response = $this->authGet($token, '/api/attendance/student/me');
        $response->assertStatus(200);
    }
}
