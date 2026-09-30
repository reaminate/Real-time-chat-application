<?php

namespace Tests\Feature;

use App\Events\GroupCreated;
use App\Events\MessageDeleted;
use App\Events\MessageDeletedForever;
use App\Events\MessageDelivered;
use App\Events\MessageRead;
use App\Events\MessageRestored;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Events\UserAdded;
use App\Events\UserDeleted;
use App\Events\UserOffline;
use App\Events\UserOnline;
use App\Events\UserStoppedTyping;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
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

        $this->postJson("/api/conversation/{$conversation->id}/restore?".http_build_query(['users' => [$c->id]]))
            ->assertOk();

        Event::assertDispatched(UserAdded::class, fn (UserAdded $e) => $this->channelNames($e->broadcastOn())
            === ['private-conversation.'.$conversation->id, 'private-user.'.$c->id]);
    }

    public function test_message_updated_broadcasts_on_conversation_channel(): void
    {
        Event::fake([MessageUpdated::class]);
        [$a, $b] = User::factory(2)->create();
        $conversation = $this->groupWith($a, $b);
        $message = Message::factory()->create(['sender_id' => $a->id, 'conversation_id' => $conversation->id]);
        Sanctum::actingAs($a);

        $this->patchJson("/api/message/{$message->id}", ['conversation_id' => $conversation->id, 'body' => 'edited'])
            ->assertOk();

        Event::assertDispatched(MessageUpdated::class, fn (MessageUpdated $e) => $this->channelNames($e->broadcastOn()) === ['private-conversation.'.$conversation->id]
            && $e->message->body === 'edited');
    }

    public function test_message_deleted_broadcasts_on_conversation_channel(): void
    {
        Event::fake([MessageDeleted::class]);
        [$a, $b] = User::factory(2)->create();
        $conversation = $this->groupWith($a, $b);
        $message = Message::factory()->create(['sender_id' => $a->id, 'conversation_id' => $conversation->id]);
        Sanctum::actingAs($a);

        $this->deleteJson("/api/message/{$message->id}")->assertNoContent();

        Event::assertDispatched(MessageDeleted::class, fn (MessageDeleted $e) => $this->channelNames($e->broadcastOn()) === ['private-conversation.'.$conversation->id]
            && $e->message->is($message));
    }

    public function test_message_restored_broadcasts_on_conversation_channel(): void
    {
        Event::fake([MessageRestored::class]);
        [$owner, $admin, $member] = User::factory(3)->create();
        $conversation = $this->groupWith($owner, $member);
        ConversationMember::factory()->admin()->create(['user_id' => $admin->id, 'conversation_id' => $conversation->id]);
        $message = Message::factory()->create(['sender_id' => $member->id, 'conversation_id' => $conversation->id]);
        $message->delete();
        Sanctum::actingAs($admin);

        $this->getJson("/api/message/{$message->id}/restore")->assertCreated();

        Event::assertDispatched(MessageRestored::class, fn (MessageRestored $e) => $e instanceof ShouldBroadcast
            && $this->channelNames($e->broadcastOn()) === ['private-conversation.'.$conversation->id]
            && $e->message->is($message));
    }

    public function test_message_deleted_forever_broadcasts_on_conversation_channel(): void
    {
        Event::fake([MessageDeletedForever::class]);
        [$owner, $admin, $member] = User::factory(3)->create();
        $conversation = $this->groupWith($owner, $member);
        ConversationMember::factory()->admin()->create(['user_id' => $admin->id, 'conversation_id' => $conversation->id]);
        $message = Message::factory()->create(['sender_id' => $member->id, 'conversation_id' => $conversation->id]);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/message/{$message->id}/force_delete")->assertNoContent();

        Event::assertDispatched(MessageDeletedForever::class, fn (MessageDeletedForever $e) => $e instanceof ShouldBroadcast
            && $this->channelNames($e->broadcastOn()) === ['private-conversation.'.$conversation->id]
            && $e->message->id === $message->id);
    }

    public function test_message_read_broadcasts_on_conversation_channel(): void
    {
        Event::fake([MessageRead::class]);
        [$a, $b] = User::factory(2)->create();
        $conversation = $this->groupWith($a, $b);
        $message = Message::factory()->create(['sender_id' => $b->id, 'conversation_id' => $conversation->id]);
        Sanctum::actingAs($a);

        $this->getJson("/api/message/{$message->id}")->assertOk();

        Event::assertDispatched(MessageRead::class, fn (MessageRead $e) => $this->channelNames($e->broadcastOn()) === ['private-conversation.'.$conversation->id]
            && $e->user->is($a)
            && $e->messageId === $message->id);
    }

    public function test_message_delivered_broadcasts_unread_message_ids_on_conversation_channel(): void
    {
        Event::fake([MessageDelivered::class]);
        [$a, $b] = User::factory(2)->create();
        $conversation = $this->groupWith($a, $b);
        // own messages are never "delivered" to yourself
        Message::factory()->create(['sender_id' => $a->id, 'conversation_id' => $conversation->id]);
        $unread = Message::factory(2)->create(['sender_id' => $b->id, 'conversation_id' => $conversation->id]);
        $conversation->update(['last_message_id' => $unread->last()->id]);
        Sanctum::actingAs($a);

        $this->getJson("/api/conversation/{$conversation->id}/messages")->assertOk();

        Event::assertDispatched(MessageDelivered::class, fn (MessageDelivered $e) => $this->channelNames($e->broadcastOn()) === ['private-conversation.'.$conversation->id]
            && $e->user->is($a)
            && $e->messageIds->sort()->values()->all() === $unread->modelKeys());
    }

    public function test_user_stopped_typing_broadcasts_on_conversation_channel(): void
    {
        Event::fake([UserStoppedTyping::class]);
        [$a, $b] = User::factory(2)->create();
        $conversation = $this->groupWith($a, $b);
        Sanctum::actingAs($a);

        $this->postJson("/api/conversation/{$conversation->id}/stopped-typing")->assertSuccessful();

        Event::assertDispatched(UserStoppedTyping::class, fn (UserStoppedTyping $e) => $this->channelNames($e->broadcastOn()) === ['private-conversation.'.$conversation->id]
            && $e->userId === $a->id
            && $e->broadcastAs() === 'user.stopped.typing');
    }

    public function test_login_broadcasts_user_online_on_members_conversation_channels(): void
    {
        Event::fake([UserOnline::class]);
        [$a, $b, $c] = User::factory(3)->create(['password' => 'Secret123']);
        $first = $this->groupWith($a, $b);
        $second = $this->groupWith($c, $a);
        $this->groupWith($b, $c);

        $this->postJson('/api/login?'.http_build_query(['email' => $a->email, 'password' => 'Secret123']))
            ->assertOk();

        Event::assertDispatched(UserOnline::class, fn (UserOnline $e) => $this->channelNames($e->broadcastOn())
            === collect([$first, $second])->map(fn ($c) => 'private-conversation.'.$c->id)->sort()->values()->all()
            && $e->broadcastAs() === 'user.online'
            && $e->broadcastWith()['user_id'] === $a->id);
    }

    public function test_logout_broadcasts_user_offline_with_last_seen_at(): void
    {
        Event::fake([UserOffline::class]);
        [$a, $b] = User::factory(2)->create();
        $conversation = $this->groupWith($a, $b);
        $token = $a->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/logout')->assertOk();

        Event::assertDispatched(UserOffline::class, fn (UserOffline $e) => $this->channelNames($e->broadcastOn()) === ['private-conversation.'.$conversation->id]
            && $e->broadcastAs() === 'user.offline'
            && $e->broadcastWith()['last_seen_at'] !== null);
    }

    public function test_channel_authorization(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'key',
            'broadcasting.connections.reverb.secret' => 'secret',
            'broadcasting.connections.reverb.app_id' => 'id',
        ]);
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
