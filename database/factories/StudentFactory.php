<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Student> */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'nis' => $this->faker->unique()->numerify('NIS#####'),
            'full_name' => $this->faker->name(),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'is_active' => true,
            'metadata' => [],
        ];
    }
}
