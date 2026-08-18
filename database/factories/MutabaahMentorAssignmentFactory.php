<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\MutabaahMentorAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

class MutabaahMentorAssignmentFactory extends Factory
{
    protected $model = MutabaahMentorAssignment::class;

    public function definition(): array
    {
        return ['employee_id' => Employee::query()->inRandomOrder()->value('id'), 'mentor_type' => fake()->randomElement(['Pembimbing', 'Guru', 'Musyrif', 'Musyrifah']), 'group_name' => 'Kelompok '.fake()->randomLetter(), 'start_date' => now(), 'is_active' => true];
    }
}
