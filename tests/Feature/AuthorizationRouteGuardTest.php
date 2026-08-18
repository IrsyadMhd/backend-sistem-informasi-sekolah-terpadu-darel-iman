<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationRouteGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_compatibility_aliases_receive_gate_bypass(): void
    {
        foreach (['Super Admin', 'super_admin', 'super-admin', 'Superadmin'] as $index => $roleName) {
            $role = Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $user = User::factory()->create(['email' => "super-alias-{$index}@school.test"]);
            $user->assignRole($role);

            $this->assertTrue(
                Gate::forUser($user)->allows('session-one.arbitrary-ability'),
                "{$roleName} must receive the canonical Super Admin bypass."
            );
        }

        $ordinaryRole = Role::query()->firstOrCreate(['name' => 'Super Admin Assistant', 'guard_name' => 'web']);
        $ordinaryUser = User::factory()->create();
        $ordinaryUser->assignRole($ordinaryRole);

        $this->assertFalse(Gate::forUser($ordinaryUser)->allows('session-one.arbitrary-ability'));
    }

    public function test_reseeding_preserves_extra_non_super_permission(): void
    {
        $extra = Permission::query()->firstOrCreate([
            'name' => 'integration.extra.permission',
            'guard_name' => 'web',
        ]);
        $guru = Role::query()->where('name', 'Guru')->firstOrFail();
        $guru->givePermissionTo($extra);

        $this->seed(RolePermissionSeeder::class);

        $this->assertTrue($guru->refresh()->hasPermissionTo($extra));
        $this->assertTrue($guru->hasPermissionTo('teacher.material.create'));
    }

    public function test_view_only_permission_cannot_call_write_routes(): void
    {
        $viewerRole = Role::query()->create(['name' => 'Session One Viewer', 'guard_name' => 'web']);
        $viewerRole->givePermissionTo([
            'dashboard.pemantauan.lihat',
            'student.view',
            'unit.view',
            'master.view',
            'academic.schedule.view',
            'academic.grade.view',
        ]);
        $viewer = User::factory()->create();
        $viewer->assignRole($viewerRole);

        foreach ([
            '/api/dashboard-pemantauan/pengumuman-sekolah',
            '/api/students',
            '/api/education-units',
            '/api/kelas',
            '/api/jabatan',
            '/api/divisions',
            '/api/schedules',
            '/api/grades',
        ] as $endpoint) {
            $this->actingAs($viewer, 'sanctum')
                ->postJson($endpoint, [])
                ->assertForbidden();
        }
    }

    public function test_protected_write_route_returns_401_without_token_and_403_without_permission(): void
    {
        $this->postJson('/api/students', [])->assertUnauthorized();

        $userWithoutPermission = User::factory()->create();
        $this->actingAs($userWithoutPermission, 'sanctum')
            ->postJson('/api/students', [])
            ->assertForbidden();
    }

    public function test_teacher_action_requires_its_granular_permission(): void
    {
        $guruRole = Role::query()->where('name', 'Guru')->firstOrFail();
        $this->assertTrue($guruRole->hasPermissionTo('teacher.material.create'));
        $guruRole->revokePermissionTo('teacher.material.create');

        $guru = User::factory()->create();
        $guru->assignRole($guruRole);

        $this->actingAs($guru, 'sanctum')
            ->postJson('/api/teacher/materials', [])
            ->assertForbidden();
    }

    public function test_foundation_canonical_and_separator_aliases_remain_read_only(): void
    {
        foreach (['Pengurus Yayasan', 'pengurus_yayasan', 'pengurus-yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan'] as $index => $roleName) {
            $role = Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $user = User::factory()->create(['email' => "foundation-readonly-{$index}@school.test"]);
            $user->assignRole($role);

            $this->actingAs($user, 'sanctum')
                ->postJson('/api/student-card-settings', [])
                ->assertForbidden();
        }
    }

    public function test_parent_and_student_action_guards_accept_legacy_aliases(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/portal/assignments/missing-assignment/submit', [])
            ->assertNotFound();

        $parentAlias = Role::query()->firstOrCreate(['name' => 'orang-tua', 'guard_name' => 'web']);
        $parent = User::factory()->create();
        $parent->assignRole($parentAlias);

        $this->actingAs($parent, 'sanctum')
            ->postJson('/api/portal/permissions', [])
            ->assertUnprocessable();
    }

    public function test_grade_permission_mapping_keeps_monitoring_roles_read_only(): void
    {
        foreach (['Kepala Sekolah', 'Divisi Pendidikan'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();

            $this->assertTrue($role->hasPermissionTo('academic.grade.view'));
            $this->assertFalse($role->hasPermissionTo('academic.grade.create'));
            $this->assertFalse($role->hasPermissionTo('academic.grade.update'));
        }

        $waka = Role::query()->where('name', 'Waka Kurikulum')->firstOrFail();
        foreach (['academic.grade.view', 'academic.grade.create', 'academic.grade.update', 'academic.grade.finalize', 'academic.grade.publish'] as $permission) {
            $this->assertTrue($waka->hasPermissionTo($permission));
        }

        $guru = Role::query()->where('name', 'Guru')->firstOrFail();
        foreach (['teacher.grade.view', 'teacher.grade.create', 'teacher.grade.update'] as $permission) {
            $this->assertTrue($guru->hasPermissionTo($permission));
        }
    }

    public function test_principal_monitoring_role_does_not_receive_management_permissions(): void
    {
        $principal = Role::query()->where('name', 'Kepala Sekolah')->firstOrFail();

        $this->assertTrue($principal->hasPermissionTo('dashboard.pemantauan.lihat'));
        $this->assertTrue($principal->hasPermissionTo('academic.grade.view'));
        foreach (['dashboard.pemantauan.kelola', 'divisi.laporan_bulanan', 'master.create', 'academic.manage'] as $permission) {
            $this->assertFalse($principal->hasPermissionTo($permission));
        }
    }
}
