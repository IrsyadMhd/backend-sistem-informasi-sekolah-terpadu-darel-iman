<?php

namespace Tests\Feature;

use App\Models\EducationUnit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestingRoleAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestingRoleAccountsAndAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Siapkan role dan permission di test database
        $this->seed(RolePermissionSeeder::class);

        // Siapkan unit pendidikan untuk pengujian unit-scoping
        EducationUnit::create([
            'code' => 'TKIT-01',
            'name' => 'TKIT 1 Dar el-Iman',
            'level' => 'TK',
            'is_active' => true,
        ]);
        EducationUnit::create([
            'code' => 'PONPES-PA',
            'name' => 'PONPES Putra Dar el-Iman',
            'level' => 'SMA',
            'is_active' => true,
        ]);

        // Buat akun superadmin dasar (seperti kondisi database awal)
        User::factory()->create([
            'email' => 'superadmin@dareliman.sch.id',
            'name' => 'Super Admin',
            'is_active' => true,
        ])->assignRole('Super Admin');
    }

    public function test_seeder_runs_idempotently_without_duplication(): void
    {
        $seeder = new TestingRoleAccountsSeeder();

        // Eksekusi pertama
        $seeder->run();
        $userCountFirstRun = User::count();

        // Eksekusi kedua (harus idempoten)
        $seeder->run();
        $userCountSecondRun = User::count();

        $this->assertEquals(
            $userCountFirstRun,
            $userCountSecondRun,
            'Seeder harus idempoten dan tidak menduplikasi akun user.'
        );

        // Verifikasi akun superadmin produksi tidak terduplikasi
        $superAdminCount = User::where('email', 'superadmin@dareliman.sch.id')->count();
        $this->assertEquals(1, $superAdminCount);
    }

    public function test_valid_login_returns_token_and_user_profile(): void
    {
        $this->seed(TestingRoleAccountsSeeder::class);

        // Test login Kepala Sekolah
        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'kepsek.test@dareliman.sch.id',
            'password' => 'KepsekTest@2026!',
            'device_name' => 'sims-mobile-test',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'roles',
                    'permissions',
                    'scope' => ['unit_id'],
                ],
            ]);

        $roles = $response->json('user.roles');
        $this->assertContains('Kepala Sekolah', $roles);
    }

    public function test_invalid_password_is_rejected(): void
    {
        $this->seed(TestingRoleAccountsSeeder::class);

        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'kepsek.test@dareliman.sch.id',
            'password' => 'PasswordSalah123!',
            'device_name' => 'sims-mobile-test',
        ]);

        $response->assertStatus(401);
    }

    public function test_profile_endpoint_works_with_bearer_token(): void
    {
        $this->seed(TestingRoleAccountsSeeder::class);

        $user = User::where('email', 'tu.test@dareliman.sch.id')->first();
        $this->assertNotNull($user);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'tu.test@dareliman.sch.id')
            ->assertJsonPath('data.unit_code', 'TKIT-01');

        $roles = $response->json('data.roles');
        $this->assertContains('Tata Usaha', $roles);
    }

    public function test_permission_enforcement_blocks_unauthorized_endpoints(): void
    {
        $this->seed(TestingRoleAccountsSeeder::class);

        // Akun Guru tidak memiliki izin 'sistem.master_data'
        $guruUser = User::where('email', 'guru.test@dareliman.sch.id')->first();
        $this->assertNotNull($guruUser);

        // Coba akses endpoint master unit pendidikan yang membutuhkan permission 'master.create' atau 'sistem.master_data'
        $response = $this->actingAs($guruUser, 'sanctum')
            ->postJson('/api/master/jenis-unit', [
                'code' => 'JNS-TEST',
                'name' => 'Jenis Unit Test',
            ]);

        $response->assertStatus(403);
    }

    public function test_unit_scope_is_properly_assigned(): void
    {
        $this->seed(TestingRoleAccountsSeeder::class);

        // Musyrif berlokasi di PONPES-PA
        $musyrif = User::where('email', 'musyrif.test@dareliman.sch.id')->first();
        $this->assertNotNull($musyrif);
        $this->assertEquals('PONPES-PA', $musyrif->metadata['unit_code'] ?? null);

        // Guru berlokasi di TKIT-01
        $guru = User::where('email', 'guru.test@dareliman.sch.id')->first();
        $this->assertNotNull($guru);
        $this->assertEquals('TKIT-01', $guru->metadata['unit_code'] ?? null);

        // Bendahara Yayasan berakses global/lintas unit
        $bendahara = User::where('email', 'bendahara.test@dareliman.sch.id')->first();
        $this->assertNotNull($bendahara);
        $this->assertEquals('cross_unit_read', $bendahara->metadata['data_scope'] ?? null);
    }

    public function test_cleanup_removes_test_accounts_without_affecting_superadmin(): void
    {
        $this->seed(TestingRoleAccountsSeeder::class);

        $testUsersBefore = User::where('metadata->' . TestingRoleAccountsSeeder::TEST_METADATA_FLAG, true)->count();
        $this->assertGreaterThan(0, $testUsersBefore);

        // Jalankan cleanup
        $cleaned = TestingRoleAccountsSeeder::cleanup();
        $this->assertGreaterThan(0, $cleaned);

        // Verifikasi seluruh akun test terhapus
        $testUsersAfter = User::where('metadata->' . TestingRoleAccountsSeeder::TEST_METADATA_FLAG, true)->count();
        $this->assertEquals(0, $testUsersAfter);

        // Verifikasi superadmin produksi tetap ada
        $superAdmin = User::where('email', 'superadmin@dareliman.sch.id')->first();
        $this->assertNotNull($superAdmin);
    }
}
