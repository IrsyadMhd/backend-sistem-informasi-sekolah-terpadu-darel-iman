<?php

namespace Tests\Feature;

use App\Models\AppMenu;
use App\Models\AppModule;
use App\Models\MobileAppConfig;
use App\Models\User;
use Database\Seeders\AppNavigationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AppNavigationSeeder::class);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('Super Admin');

        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole('Siswa');
    }

    public function test_unauthorized_user_cannot_access_navigation_admin_endpoints(): void
    {
        $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/v1/admin/navigation/modules')
            ->assertForbidden();
    }

    public function test_authorized_admin_can_retrieve_modules_and_menus(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/navigation/modules')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_authorized_admin_can_retrieve_icon_registries(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/navigation/icon-registries')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'lucide_icons',
                    'material_icons',
                    'mobile_screens',
                    'canonical_roles',
                ]
            ]);
    }

    public function test_authorized_admin_can_create_and_delete_menu(): void
    {
        $module = AppModule::first();

        $createResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/admin/navigation/menus', [
                'module_id' => $module->id,
                'name' => 'Test Web Menu',
                'name_mobile' => 'Test Mobile Menu',
                'route' => '/test-web-route',
                'icon' => 'BookOpen',
                'icon_mobile' => 'book-open-page-variant',
                'order' => 1,
                'is_active' => true,
                'platform' => 'both',
                'target_roles' => ['student', 'teacher'],
                'metadata' => [
                    'mobile_route' => 'Materi',
                    'mobile_color' => '#059669',
                    'mobile_bg' => '#ECFDF5',
                ],
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.name', 'Test Web Menu')
            ->assertJsonPath('data.name_mobile', 'Test Mobile Menu')
            ->assertJsonPath('data.icon_mobile', 'book-open-page-variant');

        $menuId = $createResponse->json('data.id');

        $deleteResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson("/api/v1/admin/navigation/menus/{$menuId}");

        $deleteResponse->assertOk();
        $this->assertNull(AppMenu::find($menuId));
    }

    public function test_sync_mobile_menus_updates_mobile_app_config(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/admin/navigation/sync-mobile-config')
            ->assertOk()
            ->assertJsonPath('success', true);

        $androidConfig = MobileAppConfig::android()->fresh();
        $this->assertNotNull($androidConfig);
        $this->assertArrayHasKey('role_menus', $androidConfig->config);
        $this->assertArrayHasKey('student', $androidConfig->config['role_menus']);
        $this->assertArrayHasKey('divisi', $androidConfig->config['role_menus']);
    }

    public function test_web_menu_respects_target_roles_and_invalidates_navigation_cache(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('Guru');

        $tu = User::factory()->create();
        $tu->assignRole('Tata Usaha');

        // Prime navigation module cache before creating menu
        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/v1/navigation/modules?platform=web')
            ->assertOk();

        $createRes = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/admin/navigation/menus', [
                'module_id' => 'akademik',
                'name' => 'Menu Khusus TU',
                'path' => '/dashboard/khusus-tu',
                'icon' => 'Briefcase',
                'platform' => 'web',
                'target_roles' => ['tu'],
                'required_permissions' => [],
                'is_active' => true,
            ])
            ->assertStatus(201);

        $menuId = $createRes->json('data.id');

        // Teacher must NOT see TU-only menu, TU MUST see it
        $teacherMenus = collect(
            $this->actingAs($teacher, 'sanctum')
                ->getJson('/api/v1/navigation/modules?platform=web')
                ->assertOk()
                ->json('data.modules')
        )->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertNotContains($menuId, $teacherMenus);

        $tuMenus = collect(
            $this->actingAs($tu, 'sanctum')
                ->getJson('/api/v1/navigation/modules?platform=web')
                ->assertOk()
                ->json('data.modules')
        )->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains($menuId, $tuMenus);

        // Update target_roles to include teacher -> cache must bust immediately
        $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/v1/admin/navigation/menus/{$menuId}", [
                'target_roles' => ['tu', 'teacher'],
            ])
            ->assertOk();

        $teacherMenusAfter = collect(
            $this->actingAs($teacher, 'sanctum')
                ->getJson('/api/v1/navigation/modules?platform=web')
                ->assertOk()
                ->json('data.modules')
        )->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains($menuId, $teacherMenusAfter);
    }

    public function test_module_crud_and_mobile_sync_with_empty_target_roles(): void
    {
        $modRes = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/admin/navigation/modules', [
                'name' => 'Modul Ekstra Mobile',
                'icon' => 'Smartphone',
                'platform' => 'mobile',
                'is_active' => true,
            ])
            ->assertStatus(201);

        $moduleId = $modRes->json('data.id');

        $menuRes = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/admin/navigation/menus', [
                'module_id' => $moduleId,
                'name' => 'Layanan Universal',
                'name_mobile' => 'Universal',
                'path' => 'Bantuan',
                'icon' => 'HelpCircle',
                'icon_mobile' => 'help-circle-outline',
                'platform' => 'mobile',
                'target_roles' => [],
                'is_active' => true,
            ])
            ->assertStatus(201);

        $menuId = $menuRes->json('data.id');

        $androidConfig = MobileAppConfig::android()->fresh();
        foreach (['student', 'parent', 'teacher', 'principal', 'tu', 'foundation', 'divisi', 'musyrif', 'alumni'] as $roleKey) {
            $ids = collect($androidConfig->config['role_menus'][$roleKey] ?? [])->pluck('id')->all();
            $this->assertContains($menuId, $ids, "Expected {$menuId} in role_menus[{$roleKey}]");
        }

        // Deactivating parent module must exclude its menus from mobile sync
        $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/v1/admin/navigation/modules/{$moduleId}", [
                'is_active' => false,
            ])
            ->assertOk();

        $androidConfigAfter = MobileAppConfig::android()->fresh();
        $studentIdsAfter = collect($androidConfigAfter->config['role_menus']['student'] ?? [])->pluck('id')->all();
        $this->assertNotContains($menuId, $studentIdsAfter);

        $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson("/api/v1/admin/navigation/modules/{$moduleId}")
            ->assertOk();
    }
}
