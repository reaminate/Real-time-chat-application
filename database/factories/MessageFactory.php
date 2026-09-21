<?php

namespace Database\Factories;

use App\Enums\MessageTypeEnum;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Image and file messages need at least one attachment, which the
     * AttachmentSeeder adds afterwards.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => User::factory(),
            'reply_to' => null,
            'type' => MessageTypeEnum::TEXT,
            'body' => fake()->sentences(3, true),
            'edited_at' => fake()->optional(0.15)->dateTimeBetween('-1 week'),
        ];
    }

    /**
     * An image message, optionally with a caption.
     */
    public function image(): static
    {
        return $this->state(fn () => [
            'type' => MessageTypeEnum::IMAGE,
            'body' => fake()->optional(0.3)->sentence(),
            'edited_at' => null,
        ]);
    }

    /**
     * A file message, optionally with a caption.
     */
    public function file(): static
    {
        return $this->state(fn () => [
            'type' => MessageTypeEnum::FILE,
            'body' => fake()->optional(0.3)->sentence(),
            'edited_at' => null,
        ]);
    }

    /**
     * Reply to a message, which keeps it in the same conversation.
     */
    public function replyingTo(Message $message): static
    {
        return $this->state(fn () => [
            'conversation_id' => $message->conversation_id,
            'reply_to' => $message->id,
        ]);
    }
}
