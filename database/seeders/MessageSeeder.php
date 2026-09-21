<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds 15-25 messages per conversation (120+ in total) sent by current
     * members over the last week: mostly text, some image/file messages
     * (their attachments come from AttachmentSeeder), about 30% replies to
     * an earlier message in the same conversation and about 5% soft-deleted.
     * Afterwards each conversation's last_message_id and each member's
     * last_read_id are set, so unread counts differ between members.
     */
    public function run(): void
    {
        foreach (Conversation::all() as $conversation) {
            $senderIds = ConversationMember::where('conversation_id', $conversation->id)
                ->whereNull('left_at')
                ->pluck('user_id');

            $sentAt = now()->subDays(7);
            $messages = collect();

            foreach (range(1, random_int(15, 25)) as $ignored) {
                $sentAt = $sentAt->copy()->addMinutes(random_int(5, 300));
                $roll = random_int(1, 100);

                $factory = match (true) {
                    $roll <= 8 => Message::factory()->image(),
                    $roll <= 16 => Message::factory()->file(),
                    default => Message::factory(),
                };

                if ($messages->isNotEmpty() && random_int(1, 100) <= 30) {
                    $factory = $factory->replyingTo($messages->random());
                }

                $messages->push($factory->create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $senderIds->random(),
                    'created_at' => $sentAt,
                    'updated_at' => $sentAt,
                    'deleted_at' => random_int(1, 100) <= 5 ? $sentAt : null,
                ]));
            }

            $visible = $messages->reject(fn (Message $message) => $message->trashed());
            $lastMessage = $visible->last();

            $conversation->forceFill([
                'last_message_id' => $lastMessage->id,
                'updated_at' => $lastMessage->created_at,
            ])->save();

            ConversationMember::where('conversation_id', $conversation->id)
                ->whereNull('left_at')
                ->pluck('id')
                ->each(fn (int $memberId) => ConversationMember::whereKey($memberId)->update([
                    'last_read_id' => match (random_int(0, 2)) {
                        0 => null,
                        1 => $lastMessage->id,
                        default => $visible->random()->id,
                    },
                ]));
        }
    }
}
