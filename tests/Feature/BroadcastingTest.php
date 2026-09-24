<?php

namespace Tests\Feature;

use App\Events\GroupCreated;
use App\Events\MessageSent;
use App\Events\UserAdded;
use App\Events\UserDeleted;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    use RefreshDatabase;

    /** Sorted names of the channels an event broadcasts on. */
    private function channelNames(array $channels): array
    {
        return collect($channels)->map(fn ($c) => $c->name)->sort()->values()->all();
    }

    private function groupWith(User $owner, User ...$members): Conversation
    {
        $conversation = Conversation::factory()->create(['created_by' => $owner->id]);
        ConversationMember::factory()->owner()->create(['user_id' => $owner->id, 'conversation_id' => $conversation->id]);
        foreach ($members as $member) {
            ConversationMember::factory()->create(['user_id' => $member->id, 'conversation_id' => $conversation->id]);
        }

        return $conversation;
    }

    public function test_message_sent_broadcasts_on_conversation_channel(): void
    {
        Event::fake([MessageSent::class]);
        [$a, $b] = User::factory(2)->create();
        $conversation = $this->groupWith($a, $b);
        Sanctum::actingAs($a);

        $this->postJson('/api/message', ['conversation_id' => $conversation->id, 'type' => 'text', 'body' => 'hi'])
            ->assertCreated();

        Event::assertDispatched(MessageSent::class, fn (MessageSent $e) => $this->channelNames($e->broadcastOn()) === ['private-conversation.'.$conversation->id]
            && $e->message->body === 'hi');
    }

    public function test_group_created_broadcasts_on_user_channels(): void
    {
        Event::fake([GroupCreated::class]);
        [$a, $b, $c] = User::factory(3)->create();
        Sanctum::actingAs($a);

        $this->postJson('/api/conversation', ['users' => [$a->id, $b->id, $c->id], 'name' => 'Team'])
            ->assertCreated();

        $event = Event::dispatched(GroupCreated::class)->first()[0];
        $this->assertSame(
            collect([$a, $b, $c])->map(fn ($u) => 'private-user.'.$u->id)->sort()->values()->all(),
            $this->channelNames($event->broadcastOn()),
        );
    }

    public function test_user_added_broadcasts_on_conversation_and_user_channels(): void
    {
        Event::fake([UserAdded::class]);
        [$a, $b, $new] = User::factory(3)->create();
        $conversation = $this->groupWith($a, $b);
        Sanctum::actingAs($a);

        $this->postJson("/api/conversation/{$conversation->id}/users", ['add_users' => [$new->id]])
            ->assertOk();

        Event::assertDispatched(UserAdded::class, fn (UserAdded $e) => $this->channelNames($e->broadcastOn())
            === ['private-conversation.'.$conversation->id, 'private-user.'.$new->id]);
    }

    public function test_user_deleted_broadcasts_on_conversation_and_user_channels(): void
    {
        Event::fake([UserDeleted::class]);
        [$a, $b, $c] = User::factory(3)->create();
        $conversation = $this->groupWith($a, $b, $c);
        Sanctum::actingAs($a);

        $this->deleteJson("/api/conversation/{$conversation->id}/users", ['delete_users' => [$c->id]])
            ->assertOk();

        Event::assertDispatched(UserDeleted::class, fn (UserDeleted $e) => $this->channelNames($e->broadcastOn())
            === ['private-conversation.'.$conversation->id, 'private-user.'.$c->id]);
    }

    public function test_restore_broadcasts_user_added_with_user_ids(): void
    {
        Event::fake([UserAdded::class]);
        [$a, $b, $c] = User::factory(3)->create();
        $conversation = $this->groupWith($a, $b);
        ConversationMember::factory()->create(['user_id' => $c->id, 'conversation_id' => $conversation->id, 'left_at' => now()]);
        Sanctum::actingAs($a);

        $this->getJson("/api/conversation/{$conversation->id}/restore?".http_build_query(['users' => [$c->id]]))
            ->assertOk();

        Event::assertDispatched(UserAdded::class, fn (UserAdded $e) => $this->channelNames($e->broadcastOn())
            === ['private-conversation.'.$conversation->id, 'private-user.'.$c->id]);
    }

    public function test_channel_authorization(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'key',
            'broadcasting.connections.reverb.secret' => 'secret',
            'broadcasting.connections.reverb.app_id' => 'id',
        ]);
        // channels.php registered on the null driver at boot; re-register on reverb
        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');
        [$a, $b, $outsider] = User::factory(3)->create();
        $conversation = $this->groupWith($a, $b);

        $auth = fn (User $u, string $channel) => $this->actingAs($u, 'sanctum')
            ->postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => $channel]);

        $auth($b, 'private-conversation.'.$conversation->id)->assertOk();
        $auth($outsider, 'private-conversation.'.$conversation->id)->assertForbidden();
        $auth($a, 'private-user.'.$a->id)->assertOk();
        $auth($a, 'private-user.'.$b->id)->assertForbidden();
    }
}
