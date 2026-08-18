<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use App\Services\AccessScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessScopeKelasRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_alias_without_employee_keeps_global_unit_scope(): void
    {
        [$unitA, $unitB] = $this->educationUnits();
        $user = $this->userWithRole('Super Alias', 'super_admin', [
            'unit.view',
            'unit.view_all',
        ]);

        $ids = app(AccessScopeService::class)
            ->accessibleEducationUnits($user)
            ->pluck('education_units.id')
            ->all();

        $this->assertEqualsCanonicalizing([$unitA->id, $unitB->id], $ids);
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/education-units?per_page=100')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('statistics.total_unit', 2);
    }

    public function test_division_scope_uses_metadata_allowlist_before_employee_unit_and_fails_closed_without_assignment(): void
    {
        [$unitA, $unitB, $unitC] = $this->educationUnits(3);
        $user = $this->userWithRole('Divisi Terbatas', 'Divisi Pendidikan', [
            'foundation.unit.view',
            'employee.view',
            'kesiswaan.kelas_rombel',
        ], [
            'allowed_unit_ids' => [$unitA->id, $unitC->id],
        ]);
        Employee::create([
            'niy' => 'DIVISI-001',
            'nama_lengkap' => 'Divisi Terbatas',
            'unit_id' => $unitB->id,
            'user_id' => $user->id,
            'status' => 'Aktif',
        ]);

        $scope = app(AccessScopeService::class);
        $this->assertEqualsCanonicalizing(
            [$unitA->id, $unitC->id],
            $scope->accessibleEducationUnits($user)->pluck('education_units.id')->all()
        );

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/education-units?per_page=100')
            ->assertOk()
            ->assertJsonPath('total', 2);

        $user->update(['metadata' => ['accessibile_unit_ids' => [$unitB->id]]]);
        $this->assertSame(
            [$unitB->id],
            $scope->accessibleEducationUnits($user->fresh())->pluck('education_units.id')->all()
        );

        $orphan = $this->userWithRole('Divisi Tanpa Assignment', 'Divisi Pendidikan', [
            'foundation.unit.view',
        ]);
        $this->assertSame([], $scope->accessibleEducationUnits($orphan)->pluck('education_units.id')->all());
    }

    public function test_teacher_class_list_detail_statistics_options_and_import_use_exact_assignment_scope(): void
    {
        [$unitA, $unitB] = $this->educationUnits();
        [$year, $semester] = $this->academicPeriod();
        $teacher = $this->userWithRole('Guru Satu Rombel', 'Guru', [
            'academic.schedule.view',
            'academic.schedule.create',
            'kesiswaan.kelas_rombel',
        ]);
        $teacherEmployee = Employee::create([
            'niy' => 'GURU-A-001',
            'nama_lengkap' => 'Guru Satu Rombel',
            'unit_id' => $unitA->id,
            'user_id' => $teacher->id,
            'status' => 'Aktif',
        ]);
        $foreignEmployee = Employee::create([
            'niy' => 'GURU-B-001',
            'nama_lengkap' => 'Guru Unit B',
            'unit_id' => $unitB->id,
            'status' => 'Aktif',
        ]);
        $kelasAssigned = $this->kelas($unitA, $year, $semester, 'A-1', 25);
        $kelasSameUnit = $this->kelas($unitA, $year, $semester, 'A-2', 30);
        $kelasForeign = $this->kelas($unitB, $year, $semester, 'B-1', 35);
        $subject = Subject::create([
            'code' => 'MTK-A',
            'name' => 'Matematika A',
            'unit_pendidikan_id' => $unitA->id,
            'status' => true,
        ]);
        ClassSchedule::create([
            'kelas_id' => $kelasAssigned->id,
            'employee_id' => $teacherEmployee->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'day_of_week' => 1,
            'time_start' => '08:00',
            'time_end' => '09:00',
            'is_active' => true,
        ]);

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/kelas?per_page=100')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $kelasAssigned->id)
            ->assertJsonPath('statistik.total_kelas', 1)
            ->assertJsonPath('statistik.total_kapasitas', 25);

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/kelas/stats')
            ->assertOk()
            ->assertJsonPath('data.total_kelas', 1)
            ->assertJsonPath('data.total_kapasitas', 25);

        $this->actingAs($teacher, 'sanctum')->getJson("/api/kelas/{$kelasAssigned->id}")->assertOk();
        $this->actingAs($teacher, 'sanctum')->getJson("/api/kelas/{$kelasSameUnit->id}")->assertNotFound();
        $this->actingAs($teacher, 'sanctum')->getJson("/api/kelas/{$kelasForeign->id}")->assertNotFound();

        $options = $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/kelas/options')
            ->assertOk();
        $this->assertSame([$unitA->id], collect($options->json('data.units'))->pluck('id')->all());
        $this->assertNotContains(
            $foreignEmployee->id,
            collect($options->json('data.employees'))->pluck('id')->all()
        );

        $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/kelas/import', [
                'data' => [[
                    'kode_kelas' => $kelasSameUnit->kode_kelas,
                    'nama_kelas' => 'Kelas A-2 Diretas',
                    'unit_pendidikan_id' => $unitA->id,
                ]],
            ])
            ->assertForbidden();
        $this->assertDatabaseMissing('tbl_kelas', ['id' => $kelasSameUnit->id, 'nama_kelas' => 'Kelas A-2 Diretas']);

        $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/kelas/import', [
                'data' => [[
                    'kode_kelas' => 'B-NEW',
                    'nama_kelas' => 'Kelas Unit B Baru',
                    'unit_pendidikan_id' => $unitB->id,
                ]],
            ])
            ->assertForbidden();
        $this->assertDatabaseMissing('tbl_kelas', ['kode_kelas' => 'B-NEW']);
    }

    public function test_unit_role_cannot_show_update_or_delete_foreign_education_unit(): void
    {
        [$unitA, $unitB] = $this->educationUnits();
        $principal = $this->userWithRole('Kepala Unit A', 'Kepala Sekolah', [
            'unit.view',
            'unit.update',
            'unit.delete',
        ]);
        Employee::create([
            'niy' => 'KEPSEK-A-001',
            'nama_lengkap' => 'Kepala Unit A',
            'unit_id' => $unitA->id,
            'user_id' => $principal->id,
            'status' => 'Aktif',
        ]);

        $this->actingAs($principal, 'sanctum')
            ->getJson("/api/education-units/{$unitA->id}")
            ->assertOk()
            ->assertJsonPath('id', $unitA->id);
        $this->actingAs($principal, 'sanctum')
            ->getJson("/api/education-units/{$unitB->id}")
            ->assertNotFound();

        $this->actingAs($principal, 'sanctum')
            ->putJson("/api/education-units/{$unitB->id}", [
                'code' => $unitB->code,
                'name' => 'Unit B Diretas',
                'level' => $unitB->level,
                'is_active' => true,
            ])
            ->assertNotFound();
        $this->assertDatabaseMissing('education_units', ['id' => $unitB->id, 'name' => 'Unit B Diretas']);

        $this->actingAs($principal, 'sanctum')
            ->deleteJson("/api/education-units/{$unitB->id}")
            ->assertNotFound();
        $this->assertDatabaseHas('education_units', ['id' => $unitB->id, 'deleted_at' => null]);
    }

    private function userWithRole(
        string $name,
        string $roleName,
        array $permissions,
        array $metadata = []
    ): User {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $role = Role::findOrCreate($roleName, 'web');
        $role->syncPermissions($permissions);

        $user = User::create([
            'name' => $name,
            'email' => str()->slug($name).'-'.str()->lower(str()->random(6)).'@school.test',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
            'metadata' => $metadata,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function educationUnits(int $count = 2): array
    {
        return collect(range(1, $count))->map(function (int $index) {
            $letter = chr(64 + $index);

            return EducationUnit::create([
                'code' => "UNIT-{$letter}",
                'name' => "Unit {$letter}",
                'level' => $index === 1 ? 'SD' : 'SMP',
                'is_active' => true,
            ]);
        })->all();
    }

    private function academicPeriod(): array
    {
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);
        $semester = Semester::create([
            'academic_year_id' => $year->id,
            'name' => 'Ganjil',
            'sequence' => 1,
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        return [$year, $semester];
    }

    private function kelas(
        EducationUnit $unit,
        AcademicYear $year,
        Semester $semester,
        string $code,
        int $capacity
    ): Kelas {
        return Kelas::create([
            'unit_pendidikan_id' => $unit->id,
            'tahun_ajaran_id' => $year->id,
            'semester_id' => $semester->id,
            'jenjang' => $unit->level,
            'tingkat' => $unit->level === 'SD' ? '1' : '7',
            'kode_kelas' => $code,
            'nama_kelas' => "Kelas {$code}",
            'kapasitas' => $capacity,
            'status' => 'Aktif',
        ]);
    }
}
