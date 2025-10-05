<?php

namespace Database\Factories;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chat_id' => Chat::factory(),
            'user_id' => User::factory(),
            'content' => fake()->sentence(),
            'is_read' => fake()->boolean(30),
            'read_at' => fake()->optional(0.3)->dateTime(),
        ];
    }
}