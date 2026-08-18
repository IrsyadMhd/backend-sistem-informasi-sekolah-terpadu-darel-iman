<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Auth\AuthService;
use Database\Seeders\DefaultRoleUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CanonicalRoleLoginTest extends TestCase
{
    use RefreshDatabase;

    private const ROLES = [
        'Super Admin', 'Ketua Yayasan', 'Pengurus Yayasan', 'Sekretaris Yayasan',
        'Bendahara Yayasan', 'Kepala Bidang Pendidikan', 'Divisi Kurikulum',
        'Divisi Kesiswaan', 'Divisi Bahasa', 'Divisi Program Khusus', 'Kepala Sekolah',
        'Wakil Kepala Sekolah', 'Wakil Kurikulum', 'Wakil Kesiswaan', 'Tata Usaha',
        'Operator', 'Guru', 'Guru Tahfizh', 'Guru BK', 'Wali Kelas', 'Musyrif',
        'Orang Tua', 'Siswa', 'Alumni',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DefaultRoleUserSeeder::class);
    }

    public function test_all_canonical_roles_and_minimum_permissions_come_from_spatie_tables(): void
    {
        $minimumPermissions = [
            'dashboard.view', 'dashboard.manage', 'master.view', 'master.create',
            'master.update', 'master.delete', 'academic.view', 'academic.manage',
            'attendance.view', 'attendance.manage', 'lms.view', 'lms.manage',
            'cbt.manage', 'grades.manage', 'report.view', 'report.export', 'portal.view',
            'approval.manage', 'notification.manage', 'chat.manage', 'setting.manage',
            'user.manage', 'permission.manage', 'role.manage', 'audit.view', 'activity.view',
        ];

        $this->assertEqualsCanonicalizing(self::ROLES, Role::query()->whereIn('name', self::ROLES)->pluck('name')->all());
        $this->assertEqualsCanonicalizing($minimumPermissions, Permission::query()->whereIn('name', $minimumPermissions)->pluck('name')->all());

        foreach (self::ROLES as $roleName) {
            $this->assertGreaterThan(0, Role::query()->where('name', $roleName)->firstOrFail()->permissions()->count(), $roleName);
        }
    }

    public function test_every_canonical_role_has_a_hashed_relational_account_that_can_login(): void
    {
        $passwordOverrides = [
            'Super Admin' => 'Password123!',
            'Kepala Sekolah' => 'Kepsek@2026!',
            'Tata Usaha' => 'TU@2026!',
            'Guru' => 'Guru@2026!',
            'Guru Tahfizh' => 'Tahfizh@2026!',
            'Musyrif' => 'Musyrif@2026!',
            'Orang Tua' => 'OrangTua@2026!',
            'Siswa' => 'Siswa@2026!',
        ];
        $auth = app(AuthService::class);

        foreach (self::ROLES as $roleName) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();
            $user = $role->users()->where('metadata->bootstrap_role', $roleName)->firstOrFail();
            $password = $passwordOverrides[$roleName] ?? 'Password123!';

            $this->assertTrue(Hash::check($password, $user->password), $roleName.' password must be hashed');
            $this->assertNotSame($password, $user->password);
            $this->assertNotNull($user->metadata['data_scope'] ?? null, $roleName.' must have a data scope');

            $result = match ($roleName) {
                'Super Admin' => $auth->loginAdminSystem($user->email, $password, 'phpunit'),
                'Orang Tua' => $auth->loginParentStudent('parent', $user->email, $password, 'phpunit'),
                'Siswa', 'Alumni' => $auth->loginParentStudent('student', $user->email, $password, 'phpunit'),
                default => $auth->loginEmployeeGuru($user->email, $password, 'phpunit'),
            };

            $this->assertSame($user->id, $result['user']->id, $roleName);
        }
    }
}
