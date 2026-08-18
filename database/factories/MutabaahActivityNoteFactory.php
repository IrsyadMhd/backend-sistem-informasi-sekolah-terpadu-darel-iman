<?php

namespace Database\Factories;

use App\Models\MutabaahActivityNote;
use App\Models\MutabaahDailyHeader;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MutabaahActivityNoteFactory extends Factory
{
    protected $model = MutabaahActivityNote::class;

    public function definition(): array
    {
        return ['daily_header_id' => MutabaahDailyHeader::factory(), 'user_id' => User::query()->value('id'), 'note_type' => fake()->randomElement(['supervisor', 'parent', 'follow_up']), 'note' => fake()->paragraph()];
    }
}
