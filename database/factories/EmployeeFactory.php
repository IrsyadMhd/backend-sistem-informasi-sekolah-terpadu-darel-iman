<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'niy' => $this->faker->unique()->numerify('NIY-#####'),
            'nama_lengkap' => $this->faker->name(),
            'jenis_kelamin' => $this->faker->randomElement(['L', 'P']),
            'status_pegawai' => 'Tetap',
            'status' => 'Aktif',
            'metadata' => [],
        ];
    }
}
