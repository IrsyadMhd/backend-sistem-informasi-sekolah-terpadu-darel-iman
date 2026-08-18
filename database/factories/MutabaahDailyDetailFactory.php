<?php

namespace Database\Factories;

use App\Models\MutabaahDailyDetail;
use App\Models\MutabaahDailyHeader;
use App\Models\MutabaahTemplateItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MutabaahDailyDetailFactory extends Factory
{
    protected $model = MutabaahDailyDetail::class;

    public function definition(): array
    {
        $item = MutabaahTemplateItem::query()->first();

        return ['daily_header_id' => MutabaahDailyHeader::factory(), 'template_item_id' => $item?->id, 'agenda_item_id' => $item?->agenda_item_id, 'status_value' => fake()->randomElement(['good', 'less', 'not_done', 'na']), 'numeric_value' => null, 'text_value' => null, 'notes' => fake()->optional()->sentence(), 'input_by' => User::query()->value('id'), 'input_at' => now()];
    }
}
