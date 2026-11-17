<?php

namespace Tests\Feature;

use App\Models\AppMenu;
use App\Models\AppModule;
use App\Models\EducationUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessScopeService;
use Database\Seeders\AppNavigationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DynamicRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AppNavigationSeeder::class);
    }

    public function test_navigation_options_returns_dynamic_configuration_registries(): void
    {
        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole('Super Admin');
        Sanctum::actingAs($superAdmin);

        $response = $this->getJson('/api/hak-akses/navigation-options')
            ->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertNotEmpty($data['modules']);
        $this->assertNotEmpty($data['scope_options']);
        $this->assertNotEmpty($data['portal_group_options']);
        $this->assertNotEmpty($data['route_options']);
    }

    public function test_custom_role_crud_and_dynamic_me_navigation_work_without_hardcoding(): void
    {
        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole('Super Admin');
        Sanctum::actingAs($superAdmin);

        // Ensure at least two active web menus exist
        $menus = AppMenu::query()->active()->forPlatform('web')->take(2)->get();
        $this->assertCount(2, $menus);
        $pickedMenuIds = $menus->pluck('id')->all();
        $pickedPaths = $menus->pluck('path')->all();

        $createRes = $this->postJson('/api/hak-akses/roles', [
            'name' => 'Koordinator Ekskul Dinamis',
            'level' => 5,
            'description' => 'Role uji dinamis tanpa hardcode di kode.',
            'scope_type' => 'cross_unit',
            'portal_group' => 'staff',
            'default_route' => '/dashboard/pemantauan',
            'is_read_only' => false,
            'can_switch_unit' => true,
            'is_above_tu' => true,
            'can_manage_global_access' => false,
            'use_custom_menu_access' => true,
            'permissions' => ['academic.view', 'unit.view'],
            'allowed_menu_ids' => $pickedMenuIds,
        ])->assertCreated()->assertJsonPath('success', true);

        $roleId = $createRes->json('data.id');
        $this->assertNotNull($roleId);
        $this->assertEquals('cross_unit', $createRes->json('data.scope_type'));
        $this->assertEquals('/dashboard/pemantauan', $createRes->json('data.default_route'));
        $this->assertTrue($createRes->json('data.can_switch_unit'));
        $this->assertTrue($createRes->json('data.use_custom_menu_access'));
        $this->assertEqualsCanonicalizing($pickedMenuIds, $createRes->json('data.allowed_menu_ids'));

        // Assign this brand-new role to a fresh user
        $customUser = User::factory()->create(['is_active' => true]);
        $customUser->assignRole('Koordinator Ekskul Dinamis');
        Sanctum::actingAs($customUser);

        // /api/auth/me exposes dynamic access_policy & exact accessible_paths
        $meRes = $this->getJson('/api/auth/me')->assertOk();
        $meUser = $meRes->json('data') ?? $meRes->json();
        $this->assertEquals('cross_unit', $meUser['access_policy']['scope_type']);
        $this->assertEquals('staff', $meUser['access_policy']['portal_group']);
        $this->assertEquals('/dashboard/pemantauan', $meUser['default_redirect']);
        $this->assertTrue($meUser['access_policy']['can_switch_unit']);
        $this->assertTrue($meUser['access_policy']['is_above_tu']);
        $this->assertFalse($meUser['access_policy']['is_read_only']);
        $this->assertEqualsCanonicalizing($pickedPaths, $meUser['accessible_paths']);

        // /api/v1/navigation/modules strictly returns ONLY the custom checked menus
        $navRes = $this->getJson('/api/v1/navigation/modules?platform=web')->assertOk();
        $returnedMenuIds = collect($navRes->json('data.modules'))
            ->flatMap(fn ($m) => collect($m['items'])->pluck('id'))
            ->all();
        $this->assertEqualsCanonicalizing($pickedMenuIds, $returnedMenuIds);

        // Super Admin deletes role cleanly once unassigned
        $customUser->syncRoles([]);
        Sanctum::actingAs($superAdmin);
        $this->deleteJson("/api/hak-akses/roles/{$roleId}")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_dynamic_read_only_flag_on_custom_role_enforces_and_lifts_write_block(): void
    {
        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole('Super Admin');
        Sanctum::actingAs($superAdmin);

        $unit = EducationUnit::factory()->create();

        $res = $this->postJson('/api/hak-akses/roles', [
            'name' => 'Auditor Mutu ReadOnly',
            'level' => 4,
            'scope_type' => 'education_unit',
            'portal_group' => 'staff',
            'default_route' => '/dashboard/pemantauan',
            'is_read_only' => true,
            'permissions' => ['student.view', 'student.create'],
        ])->assertCreated();

        $roleId = $res->json('data.id');

        $auditor = User::factory()->create([
            'is_active' => true,
            'metadata' => ['education_unit_id' => $unit->id],
        ]);
        $auditor->assignRole('Auditor Mutu ReadOnly');

        // Read-only role blocks write requests with 403
        Sanctum::actingAs($auditor);
        $this->postJson('/api/students', ['nama_lengkap' => 'Test Blocked'])
            ->assertForbidden();

        // Update role via API to turn OFF is_read_only
        Sanctum::actingAs($superAdmin);
        $this->putJson("/api/hak-akses/roles/{$roleId}", [
            'name' => 'Auditor Mutu ReadOnly',
            'is_read_only' => false,
            'permissions' => ['student.view', 'student.create'],
        ])->assertOk()->assertJsonPath('data.is_read_only', false);

        // Re-check EnsureFoundationReadOnly middleware no longer returns 403 read-only error (will hit 422 validation instead!)
        Sanctum::actingAs($auditor->fresh());
        $writeAttempt = $this->postJson('/api/students', []);
        $this->assertNotEquals(403, $writeAttempt->getStatusCode());
    }

    public function test_custom_global_role_triggers_scope_and_privilege_escalation_guards(): void
    {
        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->assignRole('Super Admin');
        Sanctum::actingAs($superAdmin);

        // Super Admin creates a custom global role (NOT in hardcoded GLOBAL_ROLE_NAMES!)
        $this->postJson('/api/hak-akses/roles', [
            'name' => 'Inspektorat Yayasan Kustom',
            'level' => 3,
            'scope_type' => 'global',
            'portal_group' => 'staff',
            'default_route' => '/dashboard/yayasan',
            'permissions' => ['unit.view'],
        ])->assertCreated();

        $inspector = User::factory()->create(['is_active' => true]);
        $inspector->assignRole('Inspektorat Yayasan Kustom');

        $scope = app(AccessScopeService::class);
        $this->assertTrue($scope->hasGlobalScope($inspector));
        $this->assertTrue($scope->isAboveTu($inspector));

        // Unit-level Kepala Sekolah cannot escalate/assign this dynamic global role
        $kepsek = User::factory()->create(['is_active' => true]);
        $kepsek->assignRole('Kepala Sekolah');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $scope->assertRoleAssignmentAllowed($kepsek, 'Inspektorat Yayasan Kustom', ['unit.view']);
    }

    public function test_custom_role_without_menu_override_evaluates_spatie_permissions_only(): void
    {
        $role = Role::create([
            'name' => 'Analis Kurikulum Dinamis',
            'guard_name' => 'web',
            'level' => 5,
            'scope_type' => 'education_unit',
            'portal_group' => 'staff',
            'default_route' => '/dashboard/master/kurikulum',
            'use_custom_menu_access' => false,
        ]);
        $role->syncPermissions(['academic.manage', 'academic.schedule.view']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        Sanctum::actingAs($user);

        $navRes = $this->getJson('/api/v1/navigation/modules?platform=web')->assertOk();
        $returnedMenuIds = collect($navRes->json('data.modules'))
            ->flatMap(fn ($m) => collect($m['items'])->pluck('id'))
            ->all();

        $this->assertContains('akademik-kurikulum', $returnedMenuIds);
        $this->assertContains('akademik-jadwal', $returnedMenuIds);
        $this->assertNotContains('master-unit', $returnedMenuIds);
    }
}

