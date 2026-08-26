<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthSessionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);

        $ay = AcademicYear::create([
            'name' => 'Tahun Ajaran Test '.Str::random(4),
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'is_active' => true,
        ]);

        Semester::create([
            'academic_year_id' => $ay->id,
            'name' => 'Semester Ganjil',
            'sequence' => 1,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'is_active' => true,
        ]);
    }

    public function test_login_returns_token_and_user_profile(): void
    {
        $superadmin = User::create([
            'name' => 'Superadmin Test',
            'email' => 'superadmin_persistence@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $superadmin->assignRole('Super Admin');

        $response = $this->postJson('/api/v2/auth/login/admin', [
            'username' => 'superadmin_persistence@school-erp.local',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user', 'portal'])
            ->assertJson(['portal' => 'admin']);

        $token = $response->json('token');
        $this->assertNotEmpty($token);
    }

    public function test_profile_endpoint_restores_user_role_permission_and_unit_scope(): void
    {
        $user = User::create([
            'name' => 'Guru Persistence Test',
            'email' => 'guru_persistence@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $user->assignRole('Guru');

        $employee = Employee::create([
            'user_id' => $user->id,
            'niy' => 'NIY-PERSISTENCE-001',
            'nama_lengkap' => 'Guru Persistence Test',
            'jenis_kelamin' => 'L',
            'is_active' => true,
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'roles',
                'permissions',
                'default_portal',
                'scope' => ['employee_id'],
            ])
            ->assertJsonPath('name', 'Guru Persistence Test')
            ->assertJsonPath('scope.employee_id', $employee->id);
    }

    public function test_invalid_or_missing_token_returns_401(): void
    {
        $response = $this->getJson('/api/profile');
        $response->assertStatus(401);

        $invalidResponse = $this->withHeader('Authorization', 'Bearer invalid-token-string')
            ->getJson('/api/profile');
        $invalidResponse->assertStatus(401);
    }

    public function test_logout_invalidates_token(): void
    {
        $user = User::create([
            'name' => 'Logout User Test',
            'email' => 'logout_test@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $user->assignRole('Guru');

        $token = $user->createToken('test-device')->plainTextToken;

        $profileResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/profile');
        $profileResponse->assertStatus(200);

        $logoutResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout');
        $logoutResponse->assertStatus(200)
            ->assertJson(['message' => 'Logout berhasil.']);

        $expiredResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/profile');
        $expiredResponse->assertStatus(401);
    }

    public function test_session_lifetime_config_is_24_hours(): void
    {
        $lifetime = config('session.lifetime');
        $this->assertEquals(1440, $lifetime, 'Session lifetime must be configured to 1440 minutes (24 hours).');
    }
}
