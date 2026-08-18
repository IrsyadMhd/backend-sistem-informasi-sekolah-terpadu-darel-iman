<?php

namespace Database\Factories;

use App\Models\MutabaahAgendaItem;
use App\Models\MutabaahTemplate;
use App\Models\MutabaahTemplateItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class MutabaahTemplateItemFactory extends Factory
{
    protected $model = MutabaahTemplateItem::class;

    public function definition(): array
    {
        return ['template_id' => MutabaahTemplate::factory(), 'agenda_item_id' => MutabaahAgendaItem::factory(), 'sort_order' => fake()->numberBetween(1, 30), 'weight' => fake()->randomFloat(2, 1, 10), 'target_value' => null, 'is_required' => true, 'requires_parent_signature' => false, 'instruction' => fake()->optional()->sentence(), 'is_active' => true];
    }
}
