<?php

namespace Database\Factories;

use App\Models\MutabaahCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class MutabaahCategoryFactory extends Factory
{
    protected $model = MutabaahCategory::class;

    public function definition(): array
    {
        return ['code' => 'KAT-'.fake()->unique()->numerify('#####'), 'name' => fake()->unique()->words(2, true), 'icon' => 'ListChecks', 'color' => fake()->hexColor(), 'sort_order' => fake()->numberBetween(1, 20), 'is_active' => true, 'description' => fake()->sentence()];
    }
}
