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
use App\Models\StudentParent;
use App\Models\User;
use App\Services\StudentQrCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Step12UserManagementAndCardTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private EducationUnit $unit;
    private AcademicYear $ay;
    private Semester $sem;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Orang Tua', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Siswa', 'guard_name' => 'web']);

        $this->ay = AcademicYear::create([
            'name' => '2026/2027',
            'code' => '2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->sem = Semester::create([
            'academic_year_id' => $this->ay->id,
            'name' => 'Ganjil',
            'sequence' => 1,
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $this->unit = EducationUnit::create([
            'name' => 'SDIT Step 12',
            'code' => 'SDIT-12',
            'level' => 'SD',
            'is_active' => true,
        ]);

        $this->superAdmin = User::create([
            'name' => 'SuperAdmin Step12',
            'email' => 'superadmin12@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('Super Admin');

        $this->admin = User::create([
            'name' => 'Admin Step12',
            'email' => 'admin12@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('Admin');
    }

    public function test_employee_qr_login_with_valid_token_authenticates_user(): void
    {
        $guruUser = User::create([
            'name' => 'Guru QR Step12',
            'email' => 'guru12@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $guruUser->assignRole('Guru');

        $employee = Employee::create([
            'niy' => 'NIY-STEP12-01',
            'nama_lengkap' => 'Guru QR Step12',
            'status' => 'Aktif',
            'unit_id' => $this->unit->id,
            'user_id' => $guruUser->id,
            'is_active' => true,
        ]);

        $rawToken = Str::uuid()->toString();
        $tokenHash = hash('sha256', $rawToken);

        QrCredential::create([
            'user_id' => $guruUser->id,
            'employee_id' => $employee->id,
            'card_type' => 'employee_card',
            'token_hash' => $tokenHash,
            'status' => 'active',
            'issued_at' => now(),
        ]);

        $response = $this->postJson('/api/v2/auth/login/employee-qr', [
            'qr_token' => $rawToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user', 'portal'])
            ->assertJsonPath('user.id', $guruUser->id);
    }

    public function test_employee_qr_login_fails_when_token_is_revoked(): void
    {
        $guruUser = User::create([
            'name' => 'Guru Revoked',
            'email' => 'revoked@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $guruUser->assignRole('Guru');

        $employee = Employee::create([
            'niy' => 'NIY-REVOKED-01',
            'nama_lengkap' => 'Guru Revoked',
            'status' => 'Aktif',
            'unit_id' => $this->unit->id,
            'user_id' => $guruUser->id,
            'is_active' => true,
        ]);

        $rawToken = Str::uuid()->toString();
        $tokenHash = hash('sha256', $rawToken);

        $qr = QrCredential::create([
            'user_id' => $guruUser->id,
            'employee_id' => $employee->id,
            'card_type' => 'employee_card',
            'token_hash' => $tokenHash,
            'status' => 'active',
            'issued_at' => now(),
        ]);

        // Revoke token
        $qr->update(['status' => 'revoked', 'revoked_at' => now()]);

        $response = $this->postJson('/api/v2/auth/login/employee-qr', [
            'qr_token' => $rawToken,
        ]);

        $response->assertStatus(401);
    }

    public function test_employee_qr_login_fails_when_token_is_rotated(): void
    {
        $guruUser = User::create([
            'name' => 'Guru Rotated',
            'email' => 'rotated@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $guruUser->assignRole('Guru');

        $employee = Employee::create([
            'niy' => 'NIY-ROTATED-01',
            'nama_lengkap' => 'Guru Rotated',
            'status' => 'Aktif',
            'unit_id' => $this->unit->id,
            'user_id' => $guruUser->id,
            'is_active' => true,
        ]);

        // First QR Token
        $oldToken = Str::uuid()->toString();
        QrCredential::create([
            'user_id' => $guruUser->id,
            'employee_id' => $employee->id,
            'card_type' => 'employee_card',
            'token_hash' => hash('sha256', $oldToken),
            'status' => 'revoked',
            'issued_at' => now()->subDay(),
        ]);

        // New Rotated Token
        $newToken = Str::uuid()->toString();
        QrCredential::create([
            'user_id' => $guruUser->id,
            'employee_id' => $employee->id,
            'card_type' => 'employee_card',
            'token_hash' => hash('sha256', $newToken),
            'status' => 'active',
            'issued_at' => now(),
        ]);

        // Old token must fail
        $this->postJson('/api/v2/auth/login/employee-qr', ['qr_token' => $oldToken])
            ->assertStatus(401);

        // New token must succeed
        $this->postJson('/api/v2/auth/login/employee-qr', ['qr_token' => $newToken])
            ->assertStatus(200)
            ->assertJsonPath('user.id', $guruUser->id);
    }

    public function test_employee_qr_login_fails_when_account_is_inactive(): void
    {
        $inactiveUser = User::create([
            'name' => 'Guru Nonaktif',
            'email' => 'inactive@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => false,
        ]);
        $inactiveUser->assignRole('Guru');

        $employee = Employee::create([
            'niy' => 'NIY-INACTIVE-01',
            'nama_lengkap' => 'Guru Nonaktif',
            'status' => 'Nonaktif',
            'unit_id' => $this->unit->id,
            'user_id' => $inactiveUser->id,
            'is_active' => false,
        ]);

        $rawToken = Str::uuid()->toString();
        QrCredential::create([
            'user_id' => $inactiveUser->id,
            'employee_id' => $employee->id,
            'card_type' => 'employee_card',
            'token_hash' => hash('sha256', $rawToken),
            'status' => 'active',
            'issued_at' => now(),
        ]);

        $response = $this->postJson('/api/v2/auth/login/employee-qr', [
            'qr_token' => $rawToken,
        ]);

        $response->assertStatus(401);
    }

    public function test_student_qr_cannot_be_used_for_employee_qr_login(): void
    {
        $studentUser = User::create([
            'name' => 'Siswa QR Test',
            'email' => 'siswaqr@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $studentUser->assignRole('Siswa');

        $student = Student::create([
            'user_id' => $studentUser->id,
            'full_name' => 'Siswa QR Test',
            'nis' => 'NIS-QR-01',
            'gender' => 'male',
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);

        $issued = app(StudentQrCredentialService::class)->issue($student);
        $studentRawToken = $issued['raw_token'];

        $response = $this->postJson('/api/v2/auth/login/employee-qr', [
            'qr_token' => $studentRawToken,
        ]);

        $response->assertStatus(401);
    }

    public function test_student_qr_resolves_student_for_gate_attendance(): void
    {
        $studentUser = User::create([
            'name' => 'Siswa Gate Test',
            'email' => 'gate@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $studentUser->assignRole('Siswa');

        $student = Student::create([
            'user_id' => $studentUser->id,
            'full_name' => 'Siswa Gate Test',
            'nis' => 'NIS-GATE-01',
            'gender' => 'male',
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);

        $issued = app(StudentQrCredentialService::class)->issue($student);
        $studentRawToken = $issued['raw_token'];

        $resolved = app(StudentQrCredentialService::class)->resolve($studentRawToken);

        $this->assertNotNull($resolved);
        $this->assertEquals($student->id, $resolved->id);
    }
}
