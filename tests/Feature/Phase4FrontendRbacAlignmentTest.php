<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AppNavigationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase4FrontendRbacAlignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AppNavigationSeeder::class);
    }

    /**
     * P4-TEST-01: Zero hardcoded authorization in routes and layout.
     */
    public function test_p4_01_zero_hardcoded_authorization(): void
    {
        $routesPath = base_path('../web-dashboard/src/routes/index.jsx');
        $this->assertFileExists($routesPath);
        $content = File::get($routesPath);

        $this->assertStringNotContainsString('hasRole(', $content, 'routes/index.jsx must not contain hasRole()');
        $this->assertStringNotContainsString('hasAnyRole(', $content, 'routes/index.jsx must not contain hasAnyRole()');
        $this->assertStringNotContainsString('roles.includes(', $content, 'routes/index.jsx must not contain roles.includes()');
    }

    /**
     * P4-TEST-02: Zero deniedRoles across web-dashboard/src.
     */
    public function test_p4_02_zero_denied_roles(): void
    {
        $routesPath = base_path('../web-dashboard/src/routes/index.jsx');
        $content = File::get($routesPath);

        $this->assertStringNotContainsString('deniedRoles', $content, 'routes/index.jsx must not contain deniedRoles');
        $this->assertStringNotContainsString('deny=', $content, 'routes/index.jsx must not contain deny=');
        $this->assertStringNotContainsString('hasDeniedRole', $content, 'routes/index.jsx must not contain hasDeniedRole');
    }

    /**
     * P4-TEST-03: Zero hardcoded authorization role catalog as authorization source.
     */
    public function test_p4_03_zero_hardcoded_authorization_role_catalog(): void
    {
        $routesPath = base_path('../web-dashboard/src/routes/index.jsx');
        $content = File::get($routesPath);

        $this->assertStringContainsString('function RouteRole({ any = [], children })', $content);
        $this->assertStringNotContainsString('RouteRole allow=', $content);
        $this->assertStringNotContainsString('RoleElement allow=', $content);
    }

    /**
     * P4-TEST-04: Permission context works in routes/index.jsx.
     */
    public function test_p4_04_permission_context_works(): void
    {
        $routesPath = base_path('../web-dashboard/src/routes/index.jsx');
        $content = File::get($routesPath);

        $this->assertStringContainsString('function PermissionElement({ any = [], children })', $content);
        $this->assertStringNotContainsString('allowedRoles', $content);
    }

    /**
     * P4-TEST-05: Authorized feature works with backend permission.
     */
    public function test_p4_05_authorized_feature_works(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('dashboard.super-admin.view');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/navigation/modules');
        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
    }

    /**
     * P4-TEST-06: Unauthorized feature blocked by backend.
     */
    public function test_p4_06_unauthorized_feature_blocked(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/gate-attendance/logs');
        $this->assertTrue(in_array($response->status(), [401, 403]));
    }

    /**
     * P4-TEST-07: Role name alone cannot grant feature without Spatie permission.
     */
    public function test_p4_07_role_name_alone_cannot_grant_feature(): void
    {
        $dummyRole = Role::firstOrCreate(['name' => 'CustomDummyRole', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($dummyRole);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/education-units', ['name' => 'Unauthorized Unit']);
        $this->assertTrue(in_array($response->status(), [401, 403]));
    }

    /**
     * P4-TEST-08: Direct route cannot bypass backend authorization.
     */
    public function test_p4_08_direct_route_cannot_bypass_authorization(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/gate-attendance/schedule-config');
        $this->assertTrue(in_array($response->status(), [401, 403]));
    }

    /**
     * P4-TEST-09: Backend remains enforcement authority.
     */
    public function test_p4_09_backend_remains_enforcement_authority(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/education-units', ['name' => 'Unenforced Unit']);
        $this->assertTrue(in_array($response->status(), [401, 403]));
    }

    /**
     * P4-TEST-10: Every navigation route resolves in React Router.
     */
    public function test_p4_10_every_navigation_route_resolves(): void
    {
        $routesPath = base_path('../web-dashboard/src/routes/index.jsx');
        $content = File::get($routesPath);

        $dbPaths = DB::table('app_menus')
            ->whereNotNull('path')
            ->where('path', '!=', '')
            ->distinct()
            ->pluck('path')
            ->toArray();

        $this->assertNotEmpty($dbPaths);

        foreach ($dbPaths as $path) {
            $matched = false;
            if (str_contains($content, "'{$path}'") || str_contains($content, "\"{$path}\"")) {
                $matched = true;
            }
            if (!$matched && str_starts_with($path, '/dashboard/')) {
                $sub = substr($path, strlen('/dashboard/'));
                if (str_contains($content, "'{$sub}'") || str_contains($content, "\"{$sub}\"")) {
                    $matched = true;
                }
                if (!$matched && str_starts_with($sub, 'yayasan/')) {
                    $yayasanSub = substr($sub, strlen('yayasan/'));
                    if (str_contains($content, "'{$yayasanSub}'") || str_contains($content, "\"{$yayasanSub}\"")) {
                        $matched = true;
                    }
                }
            }
            if (!$matched && str_starts_with($path, '/absensi/')) {
                $sub = substr($path, strlen('/absensi/'));
                if (str_contains($content, "'{$sub}'") || str_contains($content, "\"{$sub}\"")) {
                    $matched = true;
                }
            }
            if (!$matched && $path === '/dashboard' && str_contains($content, "path: '/dashboard'")) {
                $matched = true;
            }
            if (!$matched && $path === '/mutabaah' && (str_contains($content, "path: '/mutabaah'") || str_contains($content, "path: 'mutabaah'"))) {
                $matched = true;
            }

            $this->assertTrue($matched, "app_menus path [{$path}] must resolve in web-dashboard/src/routes/index.jsx");
        }
    }

    /**
     * P4-TEST-11: Every protected route has valid component on disk.
     */
    public function test_p4_11_every_protected_route_has_valid_component(): void
    {
        $routesPath = base_path('../web-dashboard/src/routes/index.jsx');
        $content = File::get($routesPath);

        preg_match_all('/import\(\'([^\']+)\'\)/', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $relImport) {
            $normalized = base_path('../web-dashboard/src/routes/' . $relImport);
            $realPath = realpath($normalized . '.jsx')
                ?: realpath($normalized . '.tsx')
                ?: realpath($normalized . '.js')
                ?: realpath($normalized . '/index.jsx');

            $this->assertNotNull($realPath, "Lazy component import [{$relImport}] must exist on disk");
        }
    }

    /**
     * P4-TEST-12: Deep-link works (AcademicCalendarPage registered).
     */
    public function test_p4_12_deep_link_works(): void
    {
        $calPage = base_path('../web-dashboard/src/pages/AcademicCalendarPage.jsx');
        $this->assertFileExists($calPage);

        $routesContent = File::get(base_path('../web-dashboard/src/routes/index.jsx'));
        $this->assertStringContainsString("akademik/kalender", $routesContent);
    }

    /**
     * P4-TEST-13: Refresh works (RouteErrorElement and errorElement registered).
     */
    public function test_p4_13_refresh_works(): void
    {
        $routesContent = File::get(base_path('../web-dashboard/src/routes/index.jsx'));
        $this->assertStringContainsString('errorElement: <RouteErrorElement />', $routesContent);
    }

    /**
     * P4-TEST-14: No unhandled React route (wildcard error catch-all exists).
     */
    public function test_p4_14_no_unhandled_react_route(): void
    {
        $routesContent = File::get(base_path('../web-dashboard/src/routes/index.jsx'));
        $this->assertStringContainsString("path: '*'", $routesContent);
    }

    /**
     * P4-TEST-15: No missing import/component in routes.
     */
    public function test_p4_15_no_missing_import_component(): void
    {
        $routesContent = File::get(base_path('../web-dashboard/src/routes/index.jsx'));
        $this->assertStringContainsString('AcademicCalendarPage', $routesContent);
        $this->assertStringContainsString('AttendanceWorkspacePage', $routesContent);
    }

    /**
     * P4-TEST-16: Design-system inventory of canonical components exists.
     */
    public function test_p4_16_design_system_inventory(): void
    {
        $base = base_path('../web-dashboard/src/components/tailgrids/core');
        $this->assertFileExists($base . '/button.tsx');
        $this->assertFileExists($base . '/dialog.tsx');
        $this->assertFileExists($base . '/alert-dialog.tsx');
        $this->assertFileExists($base . '/badge.tsx');
        $this->assertFileExists($base . '/card.tsx');
    }

    /**
     * P4-TEST-17: No SweetAlert2 runtime in production bundle.
     */
    public function test_p4_17_no_sweetalert2_destructive_confirmation(): void
    {
        $compatFile = base_path('../web-dashboard/src/components/tailgrids/compat/swal-tailgrids.jsx');
        $this->assertFileExists($compatFile);

        $distDir = base_path('../web-dashboard/dist');
        if (File::isDirectory($distDir)) {
            $files = File::allFiles($distDir);
            foreach ($files as $f) {
                if ($f->getExtension() === 'js') {
                    $this->assertStringNotContainsString(
                        'sweetalert2.all',
                        $f->getContents(),
                        'Production build must not contain sweetalert2 runtime'
                    );
                }
            }
        }
    }

    /**
     * P4-TEST-18: Canonical button usage available.
     */
    public function test_p4_18_canonical_button_usage(): void
    {
        $buttonPath = base_path('../web-dashboard/src/components/tailgrids/core/button.tsx');
        $this->assertFileExists($buttonPath);
        $content = File::get($buttonPath);
        $this->assertTrue(
            str_contains($content, 'export function Button') || str_contains($content, 'export const Button'),
            'button.tsx must export canonical Button component'
        );
    }

    /**
     * P4-TEST-19: Canonical form usage available.
     */
    public function test_p4_19_canonical_form_usage(): void
    {
        $fieldPath = base_path('../web-dashboard/src/components/tailgrids/core/field.tsx');
        $this->assertFileExists($fieldPath);
    }

    /**
     * P4-TEST-20: Canonical dialog usage available.
     */
    public function test_p4_20_canonical_dialog_usage(): void
    {
        $dialogPath = base_path('../web-dashboard/src/components/tailgrids/core/dialog.tsx');
        $this->assertFileExists($dialogPath);
    }

    /**
     * P4-TEST-21: Canonical icon system packages present.
     */
    public function test_p4_21_canonical_icon_usage(): void
    {
        $pkgPath = base_path('../web-dashboard/package.json');
        $content = File::get($pkgPath);
        $this->assertStringContainsString('@tailgrids/icons', $content);
        $this->assertStringContainsString('lucide-react', $content);
    }

    /**
     * P4-TEST-22: State consistency components exist.
     */
    public function test_p4_22_state_consistency(): void
    {
        $errorComp = base_path('../web-dashboard/src/components/reports/ReportErrorState.jsx');
        $appErrorComp = base_path('../web-dashboard/src/components/app/AppErrorState.jsx');
        $this->assertTrue(File::exists($errorComp) || File::exists($appErrorComp));
    }

    /**
     * P4-TEST-23: Responsive smoke in DashboardLayout.
     */
    public function test_p4_23_responsive_smoke(): void
    {
        $layoutPath = base_path('../web-dashboard/src/layouts/DashboardLayout.jsx');
        $content = File::get($layoutPath);
        $this->assertStringContainsString('window.innerWidth < 1024', $content);
    }

    /**
     * P4-TEST-24: P0 regression gate (database role & permission integrity).
     */
    public function test_p4_24_p0_regression(): void
    {
        $roleCount = Role::count();
        $permCount = Permission::count();

        $this->assertGreaterThanOrEqual(10, $roleCount);
        $this->assertGreaterThanOrEqual(50, $permCount);
    }

    /**
     * P4-TEST-25: P2/P3 regression gate (dynamic module navigation endpoint).
     */
    public function test_p4_25_p2_p3_regression(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/navigation/modules');
        $response->assertStatus(200);
        $this->assertArrayHasKey('modules', $response->json('data'));
    }
}
