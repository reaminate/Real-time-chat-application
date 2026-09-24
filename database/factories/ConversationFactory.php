<?php

namespace Database\Factories;

use App\Enums\ConversationTypeEnum;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * 
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ConversationTypeEnum::GROUP,
            'name' => ucwords(fake()->words(3, true)),
            'last_message_id' => null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * A one-to-one conversation, which has no name.
     */
    public function direct(): static
    {
        return $this->state(fn () => [
            'type' => ConversationTypeEnum::DIRECT,
            'name' => null,
        ]);
    }

    /**
     * A named group conversation.
     */
    public function group(): static
    {
        return $this->state(fn () => [
            'type' => ConversationTypeEnum::GROUP,
            'name' => ucwords(fake()->words(3, true)),
        ]);
    }
}
