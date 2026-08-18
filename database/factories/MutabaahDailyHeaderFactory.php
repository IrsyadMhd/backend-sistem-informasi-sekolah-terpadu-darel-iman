<?php

namespace Database\Factories;

use App\Models\MutabaahDailyHeader;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\MutabaahTemplate;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class MutabaahDailyHeaderFactory extends Factory
{
    protected $model = MutabaahDailyHeader::class;

    public function definition(): array
    {
        $supervisor = MutabaahSupervisorAssignment::query()->first();

        return ['student_id' => Student::query()->value('id'), 'template_id' => $supervisor?->template_id ?? MutabaahTemplate::query()->value('id'), 'supervisor_assignment_id' => $supervisor?->id, 'education_unit_id' => $supervisor?->education_unit_id, 'academic_year_id' => $supervisor?->academic_year_id, 'semester_id' => $supervisor?->semester_id, 'activity_date' => fake()->date(), 'status' => 'draft', 'total_items' => 0, 'good_count' => 0, 'less_count' => 0, 'not_done_count' => 0, 'na_count' => 0];
    }
}
