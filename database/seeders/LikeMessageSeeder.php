<?php

namespace Database\Seeders;

use App\Models\ConversationMember;
use App\Models\LikeMessage;
use App\Models\Message;
use Illuminate\Database\Seeder;

class LikeMessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Roughly 40% of visible messages get reactions from a few current
     * members of their conversation. Each user reacts at most once per message.
     */
    public function run(): void
    {
        $membersByConversation = ConversationMember::whereNull('left_at')
            ->get(['conversation_id', 'user_id'])
            ->groupBy('conversation_id')
            ->map(fn ($members) => $members->pluck('user_id'));

        foreach (Message::all() as $message) {
            if (random_int(1, 100) > 40) {
                continue;
            }

            $members = $membersByConversation->get($message->conversation_id, collect());
            if ($members->isEmpty()) {
                continue;
            }

            $members->random(random_int(1, $members->count()))
                ->each(fn (int $userId) => LikeMessage::factory()->create([
                    'message_id' => $message->id,
                    'user_id' => $userId,
                ]));
        }
    }
}
