<?php

namespace Database\Factories;

use App\Enums\ConversationRoleEnum;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationMember>
 */
class ConversationMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * last_read_id is left null: it points at a message, which only exists
     * once the MessageSeeder has run.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'conversation_id' => Conversation::factory(),
            'last_read_id' => null,
            'role' => ConversationRoleEnum::MEMBER,
            'joined_at' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'left_at' => null,
            // // Pivot models don't touch timestamps on their own.
            // 'created_at' => now(),
            // 'updated_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => ConversationRoleEnum::OWNER]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => ConversationRoleEnum::ADMIN]);
    }

    /**
     * A member who has since left the conversation.
     */
    public function left(): static
    {
        return $this->state(fn () => ['left_at' => now()->toDateString()]);
    }
}
