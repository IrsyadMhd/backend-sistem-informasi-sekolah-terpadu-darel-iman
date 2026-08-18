<?php

namespace Database\Factories;

use App\Models\MutabaahDailyHeader;
use App\Models\MutabaahParentSignature;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MutabaahParentSignatureFactory extends Factory
{
    protected $model = MutabaahParentSignature::class;

    public function definition(): array
    {
        return ['daily_header_id' => MutabaahDailyHeader::factory(), 'parent_user_id' => User::query()->value('id'), 'signature_status' => fake()->randomElement(['approved', 'clarification_requested', 'unable_to_verify']), 'comment' => fake()->optional()->sentence(), 'signed_at' => now(), 'device_info' => ['platform' => 'testing'], 'ip_address' => fake()->ipv4()];
    }
}
