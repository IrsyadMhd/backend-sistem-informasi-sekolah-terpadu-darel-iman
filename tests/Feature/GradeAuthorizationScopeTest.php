<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GradeAuthorizationScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_grade_api_requires_authentication(): void
    {
        $this->getJson('/api/grades')->assertUnauthorized();
    }

    public function test_teacher_only_sees_and_writes_the_assigned_subject_and_class(): void
    {
        $this->seed(RolePermissionSeeder::class);

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
        $unit = EducationUnit::create([
            'code' => 'UNIT-GRADE',
            'name' => 'Unit Grade',
            'level' => 'SMP',
            'is_active' => true,
        ]);
        $kelas = Kelas::create([
            'unit_pendidikan_id' => $unit->id,
            'tahun_ajaran_id' => $year->id,
            'semester_id' => $semester->id,
            'jenjang' => 'SMP',
            'tingkat' => '7',
            'kode_kelas' => 'VII-A',
            'nama_kelas' => 'VII A',
            'status' => 'Aktif',
        ]);
        $teacher = User::create([
            'name' => 'Guru Grade',
            'email' => 'guru.grade@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $teacher->assignRole('Guru');
        $employee = Employee::create([
            'niy' => 'NIY-GRADE',
            'nama_lengkap' => 'Guru Grade',
            'unit_id' => $unit->id,
            'user_id' => $teacher->id,
            'status' => 'Aktif',
        ]);
        $student = Student::create([
            'full_name' => 'Siswa Grade',
            'nisn' => '1000000001',
            'nis' => 'GRADE-001',
            'gender' => 'male',
            'unit_id' => $unit->id,
            'kelas_id' => $kelas->id,
            'is_active' => true,
        ]);
        $assignedSubject = Subject::create([
            'code' => 'MAT-GRADE',
            'name' => 'Matematika',
            'unit_pendidikan_id' => $unit->id,
            'status' => true,
        ]);
        $foreignSubject = Subject::create([
            'code' => 'IPA-GRADE',
            'name' => 'IPA',
            'unit_pendidikan_id' => $unit->id,
            'status' => true,
        ]);
        ClassSchedule::create([
            'kelas_id' => $kelas->id,
            'employee_id' => $employee->id,
            'subject_id' => $assignedSubject->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'day_of_week' => 1,
            'time_start' => '08:00',
            'time_end' => '09:00',
            'is_active' => true,
        ]);
        $visibleGrade = $this->createGrade($student, $assignedSubject, $kelas, $year, $semester);
        $hiddenGrade = $this->createGrade($student, $foreignSubject, $kelas, $year, $semester);

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/grades')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $visibleGrade->id);

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/grades/'.$hiddenGrade->id)
            ->assertNotFound();

        $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/grades', [
                'student_id' => $student->id,
                'subject_id' => $foreignSubject->id,
                'academic_year_id' => $year->id,
                'semester_id' => $semester->id,
                'kelas_id' => $kelas->id,
                'score_assignment' => 90,
            ])
            ->assertForbidden();
    }

    private function createGrade(
        Student $student,
        Subject $subject,
        Kelas $kelas,
        AcademicYear $year,
        Semester $semester
    ): StudentGrade {
        return StudentGrade::create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester->id,
            'kelas_id' => $kelas->id,
            'score_assignment' => 80,
            'final_score' => 80,
            'grade_letter' => 'B',
            'is_passed' => true,
        ]);
    }
}
