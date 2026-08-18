<?php

namespace Tests\Feature;

use App\Enums\Mutabaah\RecordStatus;
use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\MutabaahTemplate;
use App\Models\ParentModel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * SESI 10 CLOSURE — Gate agenda mutabaah di POST /api/portal/mutabaah.
 *
 * Aturan gate (saveMutabaahStudent):
 *  - assignment aktif (status = active);
 *  - tanggal mulai <= activity_date dan (tanggal selesai null atau >= activity_date);
 *  - unit assignment == unit siswa;
 *  - assignment memiliki template;
 *  - header harian bersifat upsert (firstOrCreate) per (student, activity_date, template).
 */
class MutabaahPortalGateTest extends TestCase
{
    use RefreshDatabase;

    private function chain(): array
    {
        Role::firstOrCreate(['name' => 'Orang Tua', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Orang Tua');
        $parent = ParentModel::create(['user_id' => $user->id, 'full_name' => 'Wali Gate']);

        $unit = EducationUnit::create(['name' => 'Unit A', 'code' => 'UNA']);
        $ay = AcademicYear::create(['name' => '2026/2027', 'is_active' => true]);
        $sem = Semester::create(['academic_year_id' => $ay->id, 'name' => 'Ganjil', 'sequence' => 1, 'is_active' => true]);

        $template = MutabaahTemplate::create([
            'code' => 'TM-GATE',
            'name' => 'Template Gate',
            'education_unit_id' => $unit->id,
            'status' => RecordStatus::Active,
        ]);

        $employee = Employee::create(['niy' => 'NIY-GATE-1', 'nama_lengkap' => 'Guru Gate', 'jenis_kelamin' => 'L']);

        $student = Student::create([
            'full_name' => 'Siswa Gate',
            'nis' => 'GATE001',
            'gender' => 'male',
            'parent_id' => $parent->id,
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $assignment = MutabaahSupervisorAssignment::create([
            'employee_id' => $employee->id,
            'supervisor_type' => 'wali_kelas',
            'education_unit_id' => $unit->id,
            'template_id' => $template->id,
            'academic_year_id' => $ay->id,
            'semester_id' => $sem->id,
            'start_date' => now()->subDays(1)->toDateString(),
            'end_date' => now()->addDays(1)->toDateString(),
            'status' => RecordStatus::Active,
        ]);

        return [$user, $student, $unit, $assignment, $template];
    }

    public function test_student_cannot_submit_without_active_assignment(): void
    {
        [$user, $student, $unit, $assignment] = $this->chain();
        $assignment->delete();

        $this->actingAs($user)->postJson('/api/portal/mutabaah?child_id='.$student->id)
            ->assertStatus(422);
    }

    public function test_student_cannot_submit_outside_assignment_period(): void
    {
        [$user, $student, $unit, $assignment] = $this->chain();
        // Rentang berakhir kemarin → hari ini berada di luar periode.
        $assignment->update(['end_date' => now()->subDay()->toDateString()]);

        $this->actingAs($user)->postJson('/api/portal/mutabaah?child_id='.$student->id)
            ->assertStatus(422);
    }

    public function test_student_cannot_submit_for_another_unit(): void
    {
        [$user, $student, $unit, $assignment] = $this->chain();
        // Assignment berada di unit lain, bukan unit siswa.
        $otherUnit = EducationUnit::create(['name' => 'Unit Lain', 'code' => 'UNB']);
        $assignment->update(['education_unit_id' => $otherUnit->id]);

        $this->actingAs($user)->postJson('/api/portal/mutabaah?child_id='.$student->id)
            ->assertStatus(422);
    }

    public function test_linked_student_can_submit_with_valid_assignment(): void
    {
        [$user, $student, $unit, $assignment] = $this->chain();

        $this->actingAs($user)->postJson('/api/portal/mutabaah?child_id='.$student->id)
            ->assertOk()
            ->assertJsonPath('data.student_id', $student->id)
            ->assertJsonPath('data.supervisor_assignment_id', $assignment->id)
            ->assertJsonPath('data.template_id', $assignment->template_id);
    }

    public function test_duplicate_daily_entry_is_upserted_to_same_header(): void
    {
        [$user, $student, $unit, $assignment] = $this->chain();

        $first = $this->actingAs($user)->postJson('/api/portal/mutabaah?child_id='.$student->id)
            ->assertOk();
        $firstId = $first->json('data.id');

        // Entri kedua pada hari + template yang sama → header sama (upsert).
        $second = $this->actingAs($user)->postJson('/api/portal/mutabaah?child_id='.$student->id)
            ->assertOk()
            ->assertJsonPath('data.id', $firstId);

        $this->assertSame($firstId, $second->json('data.id'));
        $this->assertDatabaseCount('mutabaah_daily_headers', 1);
    }
}
