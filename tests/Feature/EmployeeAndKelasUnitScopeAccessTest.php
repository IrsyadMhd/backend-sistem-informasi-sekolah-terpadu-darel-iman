<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeAndKelasUnitScopeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_user_cannot_list_employees_or_classes_from_another_unit(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Role::firstOrCreate(['name' => 'Kepala Sekolah', 'guard_name' => 'web']);
        $unitA = EducationUnit::create(['code' => 'UNIT-A', 'name' => 'Unit A', 'level' => 'SD', 'is_active' => true]);
        $unitB = EducationUnit::create(['code' => 'UNIT-B', 'name' => 'Unit B', 'level' => 'SMP', 'is_active' => true]);
        $academicYear = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
        $semester = Semester::create(['academic_year_id' => $academicYear->id, 'name' => 'Ganjil', 'sequence' => 1, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $user = User::create([
            'name' => 'User Unit A',
            'email' => 'unit-a@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $user->assignRole('Kepala Sekolah');
        Employee::create(['niy' => 'NIY-A', 'nama_lengkap' => 'User Unit A', 'unit_id' => $unitA->id, 'user_id' => $user->id, 'status' => 'Aktif']);
        Employee::create(['niy' => 'NIY-B', 'nama_lengkap' => 'Pegawai Unit B', 'unit_id' => $unitB->id, 'status' => 'Aktif']);
        Kelas::create(['unit_pendidikan_id' => $unitA->id, 'tahun_ajaran_id' => $academicYear->id, 'semester_id' => $semester->id, 'jenjang' => 'SD', 'tingkat' => '1', 'kode_kelas' => 'A-1', 'nama_kelas' => 'Kelas Unit A', 'status' => 'Aktif']);
        Kelas::create(['unit_pendidikan_id' => $unitB->id, 'tahun_ajaran_id' => $academicYear->id, 'semester_id' => $semester->id, 'jenjang' => 'SMP', 'tingkat' => '7', 'kode_kelas' => 'B-7', 'nama_kelas' => 'Kelas Unit B', 'status' => 'Aktif']);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/employees?unit_id={$unitB->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/kelas?unit_id={$unitB->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }
}
