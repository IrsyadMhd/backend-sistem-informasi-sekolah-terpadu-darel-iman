<?php

namespace Tests\Feature;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonnelAccessAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_and_pengurus_yayasan_can_manage_global_access(): void
    {
        $unitA = EducationUnit::factory()->create();
        $unitB = EducationUnit::factory()->create();
        $employeeA = Employee::factory()->create(['unit_id' => $unitA->id]);
        $employeeB = Employee::factory()->create(['unit_id' => $unitB->id]);

        foreach (['Admin', 'Pengurus Yayasan'] as $index => $roleName) {
            $user = User::factory()->create(['email' => "global-manager-{$index}@school.test"]);
            $user->assignRole($roleName);

            $this->actingAs($user, 'sanctum')
                ->postJson('/api/hak-akses/roles', [
                    'name' => "Global Test Role {$index}",
                    'permissions' => [],
                ])
                ->assertCreated();

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/hak-akses/pegawai')
                ->assertOk()
                ->assertJsonPath('meta.total', 2);

            $this->actingAs($user, 'sanctum')
                ->putJson("/api/employees/{$employeeB->id}", [
                    'nama_lengkap' => "Diperbarui {$index}",
                    'jenis_kelamin' => 'L',
                ])
                ->assertOk();
        }

        $this->assertDatabaseHas('employees', [
            'id' => $employeeB->id,
            'nama_lengkap' => 'Diperbarui 1',
        ]);
    }

    public function test_unit_manager_can_assign_only_local_non_global_access(): void
    {
        $unitA = EducationUnit::factory()->create();
        $unitB = EducationUnit::factory()->create();
        $position = Position::create([
            'code' => 'LOCAL-001',
            'name' => 'Guru Unit A',
            'satuan_kerja' => 'Unit Pendidikan',
            'unit_sekolah_id' => $unitA->id,
            'level_jabatan' => 8,
            'scope_akses' => 'unit_sendiri',
            'is_active' => true,
        ]);
        $employeeA = Employee::factory()->create(['unit_id' => $unitA->id]);
        $employeeB = Employee::factory()->create(['unit_id' => $unitB->id]);
        $manager = User::factory()->create();
        $manager->assignRole('Divisi Pendidikan');
        Employee::factory()->create(['user_id' => $manager->id, 'unit_id' => $unitA->id]);

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/hak-akses/pegawai/{$employeeA->id}/assign-role", [
                'role_name' => 'Guru',
                'permissions' => ['employee.update'],
            ])
            ->assertOk();

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/hak-akses/pegawai/{$employeeB->id}/assign-role", [
                'role_name' => 'Guru',
            ])
            ->assertForbidden();

        $this->actingAs($manager, 'sanctum')
            ->postJson("/api/hak-akses/pegawai/{$employeeA->id}/assign-role", [
                'role_name' => 'Admin',
            ])
            ->assertForbidden();

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/hak-akses/roles', [
                'name' => 'Role Tidak Boleh Dibuat Unit',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('employees', [
            'id' => $employeeA->id,
            'role_id' => Role::where('name', 'Guru')->value('id'),
        ]);
        $this->assertNotNull($position->id);
    }

    public function test_headmaster_can_edit_local_position_but_not_global_position(): void
    {
        $unit = EducationUnit::factory()->create();
        $localPosition = Position::create([
            'code' => 'LOCAL-002',
            'name' => 'Staf Unit A',
            'satuan_kerja' => 'Unit Pendidikan',
            'unit_sekolah_id' => $unit->id,
            'level_jabatan' => 10,
            'scope_akses' => 'unit_sendiri',
            'is_active' => true,
        ]);
        $globalPosition = Position::create([
            'code' => 'GLOBAL-001',
            'name' => 'Pengurus Yayasan',
            'satuan_kerja' => 'Pengurus',
            'level_jabatan' => 1,
            'scope_akses' => 'semua_unit',
            'is_active' => true,
        ]);
        $manager = User::factory()->create();
        $manager->assignRole('Kepala Sekolah');
        Employee::factory()->create(['user_id' => $manager->id, 'unit_id' => $unit->id]);

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/jabatan/{$localPosition->id}", [
                'nama_jabatan' => 'Staf Unit A Diperbarui',
            ])
            ->assertOk();

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/jabatan/{$globalPosition->id}", [
                'nama_jabatan' => 'Tidak Boleh Diubah',
            ])
            ->assertForbidden()
            ->assertJsonPath('status', 'error');
    }
}
