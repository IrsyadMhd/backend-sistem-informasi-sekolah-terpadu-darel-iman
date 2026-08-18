<?php

namespace Tests\Feature;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardAuthorizationScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_principal_cannot_select_another_unit_from_query_string(): void
    {
        [$unitA, $unitB] = $this->createUnits();
        $principal = $this->createUser('Kepala Sekolah', 'principal');

        Employee::create([
            'niy' => 'NIY-PRINCIPAL',
            'nama_lengkap' => 'Kepala Unit A',
            'unit_id' => $unitA->id,
            'user_id' => $principal->id,
            'status' => 'Aktif',
        ]);
        $this->createStudent($unitA, 'A');
        $this->createStudent($unitB, 'B');

        $this->actingAs($principal, 'sanctum')
            ->getJson('/api/dashboard/kepala-sekolah')
            ->assertOk()
            ->assertJsonPath('data.context.unit.id', $unitA->id)
            ->assertJsonPath('data.kpis.total_siswa.total', 1);

        $this->actingAs($principal, 'sanctum')
            ->getJson('/api/dashboard/kepala-sekolah?unit_id='.$unitB->id)
            ->assertForbidden();
    }

    public function test_division_dashboard_is_limited_to_assigned_units(): void
    {
        [$unitA, $unitB] = $this->createUnits();
        $division = $this->createUser('Divisi Pendidikan', 'division');

        Employee::create([
            'niy' => 'NIY-DIVISION',
            'nama_lengkap' => 'Divisi Unit A',
            'unit_id' => $unitA->id,
            'user_id' => $division->id,
            'status' => 'Aktif',
        ]);
        $this->createStudent($unitA, 'A');
        $this->createStudent($unitB, 'B');

        $this->actingAs($division, 'sanctum')
            ->getJson('/api/dashboard/divisi-pendidikan')
            ->assertOk()
            ->assertJsonPath('data.kpis.unit_monitored.total', 1)
            ->assertJsonPath('data.kpis.total_siswa.total', 1);

        $this->actingAs($division, 'sanctum')
            ->getJson('/api/dashboard/divisi-pendidikan?unit_id='.$unitB->id)
            ->assertForbidden();
    }

    public function test_administrative_dashboard_without_unit_linkage_fails_closed(): void
    {
        [$unitA] = $this->createUnits();
        $this->createStudent($unitA, 'A');
        $administration = $this->createUser('Tata Usaha', 'administration');

        $this->actingAs($administration, 'sanctum')
            ->getJson('/api/dashboard/tata-usaha')
            ->assertOk()
            ->assertJsonPath('data.kpis.total_siswa.total', 0)
            ->assertJsonPath('data.kpis.total_pegawai.total', 0);
    }

    /** @return array{EducationUnit, EducationUnit} */
    private function createUnits(): array
    {
        return [
            EducationUnit::create(['code' => 'UNIT-A', 'name' => 'Unit A', 'level' => 'SD', 'is_active' => true]),
            EducationUnit::create(['code' => 'UNIT-B', 'name' => 'Unit B', 'level' => 'SMP', 'is_active' => true]),
        ];
    }

    private function createUser(string $role, string $slug): User
    {
        $user = User::create([
            'name' => $role,
            'email' => $slug.'@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function createStudent(EducationUnit $unit, string $suffix): Student
    {
        return Student::create([
            'full_name' => 'Siswa '.$suffix,
            'nisn' => '000000000'.$suffix,
            'nis' => 'SISWA-'.$suffix,
            'gender' => 'male',
            'unit_id' => $unit->id,
            'is_active' => true,
            'status' => 'aktif',
        ]);
    }
}
