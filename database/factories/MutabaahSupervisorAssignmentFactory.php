<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

class MutabaahSupervisorAssignmentFactory extends Factory
{
    protected $model = MutabaahSupervisorAssignment::class;

    public function definition(): array
    {
        return ['employee_id' => Employee::query()->value('id'), 'supervisor_type' => fake()->randomElement(['pembimbing', 'wali_kelas', 'guru_pai', 'guru_tahfizh', 'musyrif', 'musyrifah']), 'education_unit_id' => EducationUnit::query()->value('id'), 'academic_year_id' => AcademicYear::query()->value('id'), 'semester_id' => Semester::query()->value('id'), 'mentoring_group' => 'Kelompok '.strtoupper(fake()->randomLetter()), 'start_date' => now(), 'end_date' => now()->addMonths(6), 'is_primary' => true, 'can_input' => true, 'can_edit' => true, 'can_finalize' => true, 'can_view_report' => true, 'status' => 'active'];
    }
}
