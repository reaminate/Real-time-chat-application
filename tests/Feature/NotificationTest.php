<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\User;
use App\Notifications\PingUser;
use App\Notifications\UserReactedToYourMessage;
use App\Notifications\UserRepliedToMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function groupWith(User $owner, User ...$members): Conversation
    {
        $conversation = Conversation::factory()->group()->create(['created_by' => $owner->id]);
        ConversationMember::factory()->owner()->create(['user_id' => $owner->id, 'conversation_id' => $conversation->id]);
        foreach ($members as $member) {
            ConversationMember::factory()->create(['user_id' => $member->id, 'conversation_id' => $conversation->id]);
        }

        return $conversation;
    }

    public function test_reply_and_tag_and_react_notify_user_and_fetching_marks_them_read(): void
    {
        [$me, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($me, $other);
        $original = Message::factory()->create(['sender_id' => $other->id, 'conversation_id' => $conversation->id]);
        Sanctum::actingAs($me);
        //replying
        $this->postJson('/api/message', [
            'conversation_id' => $conversation->id,
            'reply_to' => $original->id,
            'type' => 'text',
            'body' => 'reply',
        ])->assertCreated();
        //tagging
        $this->postJson("/api/notify/{$other->friend_id}/in/{$conversation->id}")->assertNoContent();
        //
        $this->post("/api/message/{$original->id}/like")->assertOk();
        Sanctum::actingAs($other);
        $this->assertCount(3, $other->unreadNotifications);
        $this->assertEqualsCanonicalizing(
            [UserRepliedToMessage::class, PingUser::class, UserReactedToYourMessage::class],
            $other->unreadNotifications->pluck('type')->all(),
        );

        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(3);

        $this->assertCount(0, $other->fresh()->unreadNotifications);
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(0);
    }
}
