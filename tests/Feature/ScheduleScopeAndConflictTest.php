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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduleScopeAndConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_schedule_manager_cannot_read_or_update_another_units_schedule(): void
    {
        [$manager, $scheduleA, $scheduleB] = $this->context();

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/schedules')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $scheduleA->id);

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/schedules/{$scheduleB->id}")
            ->assertNotFound();

        $this->actingAs($manager, 'sanctum')
            ->putJson("/api/schedules/{$scheduleB->id}", ['time_start' => '10:00'])
            ->assertNotFound();
    }

    public function test_schedule_rejects_overlapping_teacher_or_class_and_mismatched_context(): void
    {
        [$manager, $scheduleA] = $this->context();
        $payload = $scheduleA->only(['kelas_id', 'employee_id', 'subject_id', 'academic_year_id', 'semester_id', 'day_of_week']);

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/schedules', $payload + ['time_start' => '08:30', 'time_end' => '09:30'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('time_start');

        $otherYear = AcademicYear::create(['name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'is_active' => false]);
        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/schedules', array_merge($payload, ['academic_year_id' => $otherYear->id, 'time_start' => '10:00', 'time_end' => '11:00']))
            ->assertStatus(422);
    }

    private function context(): array
    {
        $permissions = ['academic.schedule.view', 'academic.schedule.create', 'academic.schedule.update', 'academic.schedule.delete'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $role = Role::findOrCreate('Pengelola Jadwal', 'web');
        $role->syncPermissions($permissions);
        $unitA = EducationUnit::create(['code' => 'UNIT-A', 'name' => 'Unit A', 'level' => 'SD', 'is_active' => true]);
        $unitB = EducationUnit::create(['code' => 'UNIT-B', 'name' => 'Unit B', 'level' => 'SMP', 'is_active' => true]);
        $year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
        $semester = Semester::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'sequence' => 1, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $manager = User::create(['name' => 'Pengelola Unit A', 'email' => 'schedule-a@school.test', 'password' => Hash::make('Password123!'), 'is_active' => true]);
        $manager->assignRole($role);
        $employeeA = Employee::create(['niy' => 'NIY-A', 'nama_lengkap' => 'Guru Unit A', 'unit_id' => $unitA->id, 'user_id' => $manager->id, 'status' => 'Aktif']);
        $employeeB = Employee::create(['niy' => 'NIY-B', 'nama_lengkap' => 'Guru Unit B', 'unit_id' => $unitB->id, 'status' => 'Aktif']);
        $subjectA = Subject::create(['code' => 'MTK-A', 'name' => 'Matematika A', 'unit_pendidikan_id' => $unitA->id, 'status' => true]);
        $subjectB = Subject::create(['code' => 'MTK-B', 'name' => 'Matematika B', 'unit_pendidikan_id' => $unitB->id, 'status' => true]);
        $kelasA = Kelas::create(['unit_pendidikan_id' => $unitA->id, 'tahun_ajaran_id' => $year->id, 'semester_id' => $semester->id, 'jenjang' => 'SD', 'tingkat' => '1', 'kode_kelas' => 'A-1', 'nama_kelas' => 'Kelas A', 'status' => 'Aktif']);
        $kelasB = Kelas::create(['unit_pendidikan_id' => $unitB->id, 'tahun_ajaran_id' => $year->id, 'semester_id' => $semester->id, 'jenjang' => 'SMP', 'tingkat' => '7', 'kode_kelas' => 'B-7', 'nama_kelas' => 'Kelas B', 'status' => 'Aktif']);
        $scheduleA = ClassSchedule::create(['kelas_id' => $kelasA->id, 'employee_id' => $employeeA->id, 'subject_id' => $subjectA->id, 'academic_year_id' => $year->id, 'semester_id' => $semester->id, 'day_of_week' => 1, 'time_start' => '08:00', 'time_end' => '09:00', 'is_active' => true]);
        $scheduleB = ClassSchedule::create(['kelas_id' => $kelasB->id, 'employee_id' => $employeeB->id, 'subject_id' => $subjectB->id, 'academic_year_id' => $year->id, 'semester_id' => $semester->id, 'day_of_week' => 1, 'time_start' => '08:00', 'time_end' => '09:00', 'is_active' => true]);

        return [$manager, $scheduleA, $scheduleB];
    }
}
