<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\DefaultRoleUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SuperAdminAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DefaultRoleUserSeeder::class);
    }

    public function test_super_admin_has_gate_before_bypass(): void
    {
        $superadmin = User::where('email', 'superadmin@school-erp.local')->firstOrFail();

        $this->assertTrue(Gate::forUser($superadmin)->allows('any.arbitrary.permission.name'));
    }

    public function test_super_admin_receives_all_permissions_from_seeder(): void
    {
        $superadmin = User::where('email', 'superadmin@school-erp.local')->firstOrFail();
        $totalPermissions = Permission::where('guard_name', 'web')->count();

        $this->assertSame($totalPermissions, $superadmin->getAllPermissions()->count());
    }

    public function test_super_admin_can_access_foundation_dashboard(): void
    {
        $superadmin = User::where('email', 'superadmin@school-erp.local')->firstOrFail();

        $response = $this->actingAs($superadmin, 'sanctum')
            ->getJson('/api/foundation/dashboard');

        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_auth_profile(): void
    {
        $superadmin = User::where('email', 'superadmin@school-erp.local')->firstOrFail();

        $response = $this->actingAs($superadmin, 'sanctum')
            ->getJson('/api/auth/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'superadmin@school-erp.local');
    }

    public function test_super_admin_can_perform_operational_mutations(): void
    {
        $superadmin = User::where('email', 'superadmin@school-erp.local')->firstOrFail();

        $response = $this->actingAs($superadmin, 'sanctum')
            ->postJson('/api/students', [
                'nis' => 'SA_STUDENT_001',
                'full_name' => 'Siswa Tes Super Admin',
                'gender' => 'male',
            ]);

        $response->assertStatus(201);
    }

    public function test_pengurus_yayasan_remains_read_only_on_operational_mutations(): void
    {
        $yayasanUser = User::where('email', 'pengurus.yayasan@school-erp.local')->firstOrFail();

        $response = $this->actingAs($yayasanUser, 'sanctum')
            ->postJson('/api/students', [
                'nis' => 'YAYASAN_MUTATION_TEST',
                'full_name' => 'Siswa Tes Yayasan',
                'gender' => 'male',
            ]);

        $response->assertStatus(403);
    }

    public function test_re_seeding_preserves_and_syncs_all_permissions_to_super_admin(): void
    {
        Permission::create(['name' => 'new.test.feature.permission', 'guard_name' => 'web']);

        $this->seed(RolePermissionSeeder::class);

        $superadmin = User::where('email', 'superadmin@school-erp.local')->first();

        $this->assertTrue($superadmin->hasPermissionTo('new.test.feature.permission'));
    }

    public function test_permission_cache_reset_command(): void
    {
        $exitCode = Artisan::call('permission:cache-reset');
        $this->assertSame(0, $exitCode);
    }

    public function test_auth_profile_returns_complete_roles_and_permissions(): void
    {
        $superadmin = User::where('email', 'superadmin@school-erp.local')->first();

        $response = $this->actingAs($superadmin, 'sanctum')
            ->getJson('/api/auth/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.roles.0', 'Super Admin');

        $permissions = $response->json('data.permissions');
        $this->assertNotEmpty($permissions);
    }
}
