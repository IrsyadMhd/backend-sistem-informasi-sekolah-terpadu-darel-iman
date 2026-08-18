<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccessControlHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_master_quran_mutations_require_authentication(): void
    {
        $this->postJson('/api/equran/surah', [])->assertUnauthorized();
    }

    public function test_teacher_cannot_manage_master_quran_or_qr_credentials(): void
    {
        $teacher = User::create([
            'name' => 'Guru Akses Terbatas',
            'email' => 'guru.akses@school-erp.local',
            'password' => Hash::make('GuruAkses!2026'),
            'is_active' => true,
        ]);
        $teacher->assignRole('Guru');

        $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/equran/surah', [])
            ->assertForbidden();

        $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/auth/qr/employee/unknown')
            ->assertForbidden();
    }

    public function test_non_foundation_user_cannot_read_foundation_dashboard(): void
    {
        $teacher = User::create([
            'name' => 'Guru Dashboard',
            'email' => 'guru.dashboard@school-erp.local',
            'password' => Hash::make('GuruDashboard!2026'),
            'is_active' => true,
        ]);
        $teacher->assignRole('Guru');

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/foundation/dashboard')
            ->assertForbidden();
    }

    public function test_foundation_reports_do_not_return_static_preview_data(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin Laporan',
            'email' => 'superadmin.laporan@school-erp.local',
            'password' => Hash::make('SuperAdminLaporan!2026'),
            'is_active' => true,
        ]);
        $superAdmin->assignRole('Super Admin');

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/foundation/reports')
            ->assertOk()
            ->assertJsonPath('data.total_records', 0)
            ->assertJsonPath('data.preview', []);
    }

    public function test_bootstrap_password_for_another_account_cannot_be_used(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin Teruji',
            'email' => 'superadmin.teruji@school-erp.local',
            'password' => Hash::make('Admin@2026!'),
            'is_active' => true,
        ]);
        $superAdmin->assignRole('Super Admin');

        $this->postJson('/api/auth/login/admin', [
            'username' => 'superadmin.teruji@school-erp.local',
            'password' => 'Password123!',
        ])->assertUnauthorized();
    }
}
