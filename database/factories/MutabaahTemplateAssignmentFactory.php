<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\MutabaahTemplate;
use App\Models\MutabaahTemplateAssignment;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

class MutabaahTemplateAssignmentFactory extends Factory
{
    protected $model = MutabaahTemplateAssignment::class;

    public function definition(): array
    {
        return ['template_id' => MutabaahTemplate::factory(), 'education_level' => fake()->randomElement(['SD/MI', 'SMP/MTs', 'SMA/MA']), 'academic_year_id' => AcademicYear::query()->value('id'), 'semester_id' => Semester::query()->value('id'), 'priority' => 0, 'status' => 'active', 'start_date' => now(), 'end_date' => now()->addYear()];
    }
}
