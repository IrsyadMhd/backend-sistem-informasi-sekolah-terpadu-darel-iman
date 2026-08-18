<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\MutabaahTemplate;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

class MutabaahTemplateFactory extends Factory
{
    protected $model = MutabaahTemplate::class;

    public function definition(): array
    {
        return ['code' => 'TPL-'.fake()->unique()->numerify('#####'), 'name' => 'Template '.fake()->words(2, true), 'education_level' => fake()->randomElement(['TK/RA', 'SD/MI', 'SMP/MTs', 'SMA/MA']), 'academic_year_id' => AcademicYear::query()->value('id'), 'semester_id' => Semester::query()->value('id'), 'status' => 'active', 'start_date' => now()->startOfYear(), 'end_date' => now()->endOfYear()];
    }
}
