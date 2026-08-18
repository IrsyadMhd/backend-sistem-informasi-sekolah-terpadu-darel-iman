<?php

namespace Database\Factories;

use App\Models\JenisUnitPendidikan;
use App\Models\MutabaahAgenda;
use App\Models\MutabaahCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class MutabaahAgendaFactory extends Factory
{
    protected $model = MutabaahAgenda::class;

    public function definition(): array
    {
        $category = MutabaahCategory::factory()->create();

        return ['jenis_unit_id' => JenisUnitPendidikan::query()->inRandomOrder()->value('uuid'), 'category_id' => $category->id, 'category' => $category->name, 'code' => 'AGD-'.fake()->unique()->numerify('####'), 'name' => fake()->sentence(3), 'input_type' => 'baik_kurang', 'weight' => 5, 'sort_order' => fake()->numberBetween(1, 50), 'is_active' => true];
    }
}
