<?php

namespace Database\Factories;

use App\Models\LikeMessage;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LikeMessage>
 */
class LikeMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'user_id' => User::factory(),
            'liked' => fake()->boolean(75),
        ];
    }

    public function liked(): static
    {
        return $this->state(fn () => ['liked' => true]);
    }

    public function disliked(): static
    {
        return $this->state(fn () => ['liked' => false]);
    }
}
