<?php

namespace Database\Seeders;

use App\Enums\ConversationTypeEnum;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\User;
use Illuminate\Database\Seeder;

class ConversationMemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     */
    public function run(): void
    {
        $users = User::all();
        $testUser = $users->firstWhere('email', 'test@example.com');
        $conversations = Conversation::orderBy('id')->get();
        $lastGroupId = $conversations->where('type', ConversationTypeEnum::GROUP)->last()?->id;

        // "lowerId-higherId" for every pair that already has a direct conversation.
        $directPairs = [];

        foreach ($conversations as $conversation) {
            $creator = $users->find($conversation->created_by);
            $joinedAt = $conversation->created_at->toDateString();
            $base = ['conversation_id' => $conversation->id, 'joined_at' => $joinedAt];

            ConversationMember::factory()->owner()->create($base + ['user_id' => $creator->id]);

            if ($conversation->type === ConversationTypeEnum::DIRECT) {
                $partner = $users
                    ->reject(fn (User $user) => $user->is($creator)
                        || isset($directPairs[$this->pairKey($creator, $user)]))
                    ->random();

                $directPairs[$this->pairKey($creator, $partner)] = true;
                ConversationMember::factory()->create($base + ['user_id' => $partner->id]);

                continue;
            }

            if (! $creator->is($testUser)) {
                ConversationMember::factory()->create($base + ['user_id' => $testUser->id]);
            }

            $extras = $users
                ->reject(fn (User $user) => $user->is($creator) || $user->is($testUser))
                ->random(3)
                ->values();

            foreach ($extras as $index => $user) {
                $member = ConversationMember::factory();

                if ($index === 0) {
                    $member = $member->admin();
                }

                if ($index === 2 && $conversation->id === $lastGroupId) {
                    $member = $member->left();
                }

                $member->create($base + ['user_id' => $user->id]);
            }
        }
    }

    private function pairKey(User $a, User $b): string
    {
        return min($a->id, $b->id).'-'.max($a->id, $b->id);
    }
}
