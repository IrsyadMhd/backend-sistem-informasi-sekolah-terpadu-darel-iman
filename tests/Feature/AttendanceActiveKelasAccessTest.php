<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceActiveKelasAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_homeroom_student_scope_includes_students_assigned_through_active_kelas_id(): void
    {
        $unit = EducationUnit::create(['code' => 'UNIT-WALI', 'name' => 'Unit Wali', 'level' => 'SD', 'is_active' => true]);
        $year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
        $semester = Semester::create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'sequence' => 1, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Wali Kelas', 'web'));
        $employee = Employee::create(['niy' => 'NIY-WALI', 'nama_lengkap' => 'Wali Kelas', 'unit_id' => $unit->id, 'user_id' => $user->id, 'status' => 'Aktif']);
        $kelas = Kelas::create(['unit_pendidikan_id' => $unit->id, 'tahun_ajaran_id' => $year->id, 'semester_id' => $semester->id, 'wali_kelas_id' => $employee->id, 'jenjang' => 'SD', 'tingkat' => '1', 'kode_kelas' => 'I-A', 'nama_kelas' => 'Kelas I A', 'status' => 'Aktif']);
        $student = Student::create(['kelas_id' => $kelas->id, 'unit_id' => $unit->id, 'nis' => 'S-WALI-001', 'full_name' => 'Siswa Kelas Aktif', 'gender' => 'male', 'is_active' => true]);

        $studentIds = app(AttendanceAccessService::class)->homeroomStudentIds($user);

        $this->assertTrue($studentIds->contains($student->id));
    }
}
