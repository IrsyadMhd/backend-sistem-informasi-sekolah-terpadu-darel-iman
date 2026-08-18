<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\QrCredential;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Step13RUiImplementationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_ui_refactor_does_not_break_api_contracts(): void
    {
        $guruUser = User::where('email', 'guru@school-erp.local')->first();
        $this->assertNotNull($guruUser);

        $response = $this->actingAs($guruUser, 'sanctum')
            ->getJson('/api/teacher/step04/schedules');

        $response->assertOk();
    }

    public function test_print_and_qr_login_regression_pass(): void
    {
        $employee = Employee::where('niy', 'TEST-NIY-17')->first();
        $this->assertNotNull($employee);

        $qr = QrCredential::where('employee_id', $employee->id)->active()->first();
        $this->assertNotNull($qr);

        $response = $this->postJson('/api/v2/auth/login/employee-qr', [
            'qr_token' => 'empqr-demo-guru-0017',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', 'guru@school-erp.local');
    }

    public function test_student_qr_gate_attendance_regression_pass(): void
    {
        $student = Student::where('nis', 'TEST-NIS-023')->first();
        $this->assertNotNull($student);

        $superAdmin = User::where('email', 'superadmin@school-erp.local')->first();
        $this->assertNotNull($superAdmin);

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->postJson('/api/auth/qr/student/'.$student->id);
        $response->assertOk()
            ->assertJsonPath('status', 'success');
    }
}
