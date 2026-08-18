<?php

namespace Database\Factories;

use App\Enums\Mutabaah\InputType;
use App\Models\MutabaahAgendaItem;
use App\Models\MutabaahCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class MutabaahAgendaItemFactory extends Factory
{
    protected $model = MutabaahAgendaItem::class;

    public function definition(): array
    {
        return ['category_id' => MutabaahCategory::factory(), 'code' => 'AGD-'.fake()->unique()->numerify('#####'), 'name' => fake()->unique()->sentence(3), 'input_type' => fake()->randomElement(InputType::cases()), 'weight' => fake()->randomFloat(2, 1, 10), 'sort_order' => fake()->numberBetween(1, 50), 'icon' => 'CircleCheck', 'color' => fake()->hexColor(), 'description' => fake()->sentence(), 'is_active' => true];
    }
}
