<?php

namespace Tests\Feature;

use App\Models\AppMenu;
use App\Models\AppModule;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AppNavigationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AppNavigationSeeder::class);
    }

    public function test_unauthenticated_request_is_unauthorized(): void
    {
        $this->getJson('/api/v1/navigation/modules')
            ->assertUnauthorized();
    }

    public function test_super_admin_receives_all_navigation_modules(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/v1/navigation/modules')
            ->assertOk()
            ->assertJsonPath('success', true);

        $moduleIds = collect($response->json('data.modules'))->pluck('id')->all();
        $this->assertContains('dashboard', $moduleIds);
        $this->assertContains('master-data', $moduleIds);
        $this->assertContains('akademik', $moduleIds);
        $this->assertContains('lms', $moduleIds);
        $this->assertContains('presensi', $moduleIds);
        $this->assertContains('mutabaah', $moduleIds);
        $this->assertContains('laporan', $moduleIds);
        $this->assertContains('pengaturan', $moduleIds);
    }

    public function test_teacher_receives_only_permitted_modules(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('Guru');

        $response = $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/v1/navigation/modules')
            ->assertOk()
            ->assertJsonPath('success', true);

        $moduleIds = collect($response->json('data.modules'))->pluck('id')->all();
        $this->assertContains('dashboard', $moduleIds);
        $this->assertContains('lms', $moduleIds);
        // Teacher without master/system permissions should NOT see system settings
        $this->assertNotContains('pengaturan', $moduleIds);
    }

    public function test_custom_role_sees_only_authorized_menu(): void
    {
        $customRole = Role::create(['name' => 'Staf Kesiswaan Custom', 'guard_name' => 'web']);
        $customRole->givePermissionTo(['kesiswaan.data_lengkap_siswa', 'student.view']);

        $user = User::factory()->create();
        $user->assignRole('Staf Kesiswaan Custom');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/navigation/modules')
            ->assertOk()
            ->assertJsonPath('success', true);

        $modules = $response->json('data.modules');
        $this->assertCount(1, $modules);
        $this->assertEquals('master-data', $modules[0]['id']);

        $menuIds = collect($modules[0]['items'])->pluck('id')->all();
        $this->assertContains('master-siswa', $menuIds);
        $this->assertNotContains('master-unit', $menuIds);
        $this->assertNotContains('master-users', $menuIds);
    }

    public function test_permission_revocation_immediately_hides_menu(): void
    {
        $customRole = Role::create(['name' => 'Staf Dinamis', 'guard_name' => 'web']);
        $customRole->givePermissionTo(['kesiswaan.data_lengkap_siswa']);

        $user = User::factory()->create();
        $user->assignRole('Staf Dinamis');

        $res1 = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/navigation/modules')
            ->assertOk();
        $this->assertCount(1, $res1->json('data.modules'));

        // Revoke permission dynamically
        $customRole->syncPermissions([]);

        $res2 = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/navigation/modules')
            ->assertOk();
        $this->assertCount(0, $res2->json('data.modules'));
    }

    public function test_inactive_menu_is_not_returned(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');

        // Deactivate a menu
        AppMenu::where('id', 'master-mata-pelajaran')->update(['is_active' => false]);

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/v1/navigation/modules')
            ->assertOk();

        $masterModule = collect($response->json('data.modules'))->firstWhere('id', 'master-data');
        $menuIds = collect($masterModule['items'])->pluck('id')->all();

        $this->assertNotContains('master-mata-pelajaran', $menuIds);
    }

    public function test_platform_filtering_works_properly(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');

        // Web platform request
        $webRes = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/v1/navigation/modules?platform=web')
            ->assertOk();
        $webModuleIds = collect($webRes->json('data.modules'))->pluck('id')->all();
        $this->assertContains('pengaturan', $webModuleIds);

        // Mobile platform request (master-data and pengaturan are web-only)
        $mobileRes = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/v1/navigation/modules?platform=mobile')
            ->assertOk();
        $mobileModuleIds = collect($mobileRes->json('data.modules'))->pluck('id')->all();
        $this->assertNotContains('pengaturan', $mobileModuleIds);
        $this->assertNotContains('master-data', $mobileModuleIds);
    }

    public function test_user_without_permissions_receives_empty_modules(): void
    {
        $user = User::factory()->create(); // No roles or permissions

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/navigation/modules')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(0, $response->json('data.modules'));
    }

    public function test_dashboard_tahfizh_menu_access_equivalence(): void
    {
        // Siswa & Ortu: absent
        $student = User::factory()->create();
        $student->assignRole('Siswa');
        $studentRes = $this->actingAs($student, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $studentMenus = collect($studentRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertNotContains('dash-tahfizh', $studentMenus);

        $parent = User::factory()->create();
        $parent->assignRole('Orang Tua');
        $parentRes = $this->actingAs($parent, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $parentMenus = collect($parentRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertNotContains('dash-tahfizh', $parentMenus);

        // Guru Tahfizh & Super Admin: present
        $guruTahfizh = User::factory()->create();
        $guruTahfizh->assignRole('Guru Tahfizh');
        $gtRes = $this->actingAs($guruTahfizh, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $gtMenus = collect($gtRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains('dash-tahfizh', $gtMenus);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');
        $saRes = $this->actingAs($superAdmin, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $saMenus = collect($saRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains('dash-tahfizh', $saMenus);

        // Kepsek & Admin: present per legacy matrix
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $adminRes = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $adminMenus = collect($adminRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains('dash-tahfizh', $adminMenus);

        $kepsek = User::factory()->create();
        $kepsek->assignRole('Kepala Sekolah');
        $kepsekRes = $this->actingAs($kepsek, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $kepsekMenus = collect($kepsekRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains('dash-tahfizh', $kepsekMenus);
    }

    public function test_master_unit_access_equivalence(): void
    {
        // Guru & TU: absent
        $guru = User::factory()->create();
        $guru->assignRole('Guru');
        $guruRes = $this->actingAs($guru, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $guruMenus = collect($guruRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertNotContains('master-unit', $guruMenus);

        $tu = User::factory()->create();
        $tu->assignRole('Tata Usaha');
        $tuRes = $this->actingAs($tu, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $tuMenus = collect($tuRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertNotContains('master-unit', $tuMenus);

        // Admin & Super Admin: present
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $adminRes = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $adminMenus = collect($adminRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains('master-unit', $adminMenus);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');
        $saRes = $this->actingAs($superAdmin, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $saMenus = collect($saRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains('master-unit', $saMenus);
    }

    public function test_master_pegawai_access_equivalence(): void
    {
        // Guru & TU: absent
        $guru = User::factory()->create();
        $guru->assignRole('Guru');
        $guruRes = $this->actingAs($guru, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $guruMenus = collect($guruRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertNotContains('master-pegawai', $guruMenus);

        $tu = User::factory()->create();
        $tu->assignRole('Tata Usaha');
        $tuRes = $this->actingAs($tu, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $tuMenus = collect($tuRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertNotContains('master-pegawai', $tuMenus);

        // Admin, Super Admin, Kepsek: present
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $adminRes = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $adminMenus = collect($adminRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains('master-pegawai', $adminMenus);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');
        $saRes = $this->actingAs($superAdmin, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $saMenus = collect($saRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains('master-pegawai', $saMenus);

        $kepsek = User::factory()->create();
        $kepsek->assignRole('Kepala Sekolah');
        $kepsekRes = $this->actingAs($kepsek, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $kepsekMenus = collect($kepsekRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains('master-pegawai', $kepsekMenus);
    }

    public function test_master_tahun_ajaran_and_mata_pelajaran_equivalence(): void
    {
        // Guru & TU: absent
        $guru = User::factory()->create();
        $guru->assignRole('Guru');
        $guruRes = $this->actingAs($guru, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $guruMenus = collect($guruRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertNotContains('master-tahun-ajaran', $guruMenus);
        $this->assertNotContains('master-mata-pelajaran', $guruMenus);

        $tu = User::factory()->create();
        $tu->assignRole('Tata Usaha');
        $tuRes = $this->actingAs($tu, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $tuMenus = collect($tuRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertNotContains('master-tahun-ajaran', $tuMenus);
        $this->assertNotContains('master-mata-pelajaran', $tuMenus);

        // Admin & Super Admin: present
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $adminRes = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $adminMenus = collect($adminRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains('master-tahun-ajaran', $adminMenus);
        $this->assertContains('master-mata-pelajaran', $adminMenus);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');
        $saRes = $this->actingAs($superAdmin, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $saMenus = collect($saRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains('master-tahun-ajaran', $saMenus);
        $this->assertContains('master-mata-pelajaran', $saMenus);
    }

    public function test_jadwal_pelajaran_menu_access_equivalence(): void
    {
        // Guru: present
        $guru = User::factory()->create();
        $guru->assignRole('Guru');
        $guruRes = $this->actingAs($guru, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $guruMenus = collect($guruRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains('akademik-jadwal', $guruMenus);

        // Admin: present
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $adminRes = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $adminMenus = collect($adminRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains('akademik-jadwal', $adminMenus);

        // Kepsek: present
        $kepsek = User::factory()->create();
        $kepsek->assignRole('Kepala Sekolah');
        $kepsekRes = $this->actingAs($kepsek, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $kepsekMenus = collect($kepsekRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains('akademik-jadwal', $kepsekMenus);

        // Super Admin: present
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');
        $saRes = $this->actingAs($superAdmin, 'sanctum')->getJson('/api/v1/navigation/modules?platform=web');
        $saMenus = collect($saRes->json('data.modules'))->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains('akademik-jadwal', $saMenus);
    }
}
