<?php

namespace Database\Factories;

use App\Enums\MessageType;
use App\Models\Tenant;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<YardMessage> */
class YardMessageFactory extends Factory
{
    protected $model = YardMessage::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::first()?->id ?? Tenant::factory(),
            'uuid' => Str::uuid()->toString(),
            'room_id' => YardRoom::factory(),
            'user_id' => User::factory(),
            'message_type' => MessageType::Text,
            'content' => fake()->sentence(),
        ];
    }

    public function flagged(): static
    {
        return $this->state(fn () => [
            'is_flagged' => true,
            'ai_moderation_score' => 0.85,
        ]);
    }
}
