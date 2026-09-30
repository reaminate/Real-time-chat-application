<?php

namespace Tests\Feature;

use App\Enums\ConversationRoleEnum;
use App\Enums\ConversationTypeEnum;
use App\Events\UserTyping;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConversationTest extends TestCase
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

    private function memberRole(Conversation $conversation, User $user): ConversationRoleEnum
    {
        return ConversationMember::where('conversation_id', $conversation->id)->where('user_id', $user->id)->sole()->role;
    }

    public static function protectedRoutes(): array
    {
        return [
            'index' => ['getJson', '/api/conversation'],
            'store' => ['postJson', '/api/conversation'],
            'update' => ['patchJson', '/api/conversation/1'],
            'destroy' => ['deleteJson', '/api/conversation/1'],
            'messages' => ['getJson', '/api/conversation/1/messages'],
            'add users' => ['postJson', '/api/conversation/1/users'],
            'delete users' => ['deleteJson', '/api/conversation/1/users'],
            'typing' => ['postJson', '/api/conversation/1/typing'],
            'restore' => ['postJson', '/api/conversation/1/restore'],
            'force delete' => ['deleteJson', '/api/conversation/1/force_delete'],
            'pin' => ['putJson', '/api/pin/1/add'],
            'remove pin' => ['putJson', '/api/pin/1/remove'],
            'pinned' => ['getJson', '/api/pin/1/pinned'],
        ];
    }

    #[DataProvider('protectedRoutes')]
    public function test_returns_401_without_token(string $method, string $uri): void
    {
        $this->{$method}($uri)->assertUnauthorized();
    }

    // index

    public function test_index_lists_only_conversations_user_is_active_in(): void
    {
        [$me, $other] = User::factory(2)->create();
        $this->groupWith($me, $other);
        $left = $this->groupWith($other);
        ConversationMember::factory()->left()->create(['user_id' => $me->id, 'conversation_id' => $left->id]);
        $this->groupWith($other);
        Sanctum::actingAs($me);

        $this->getJson('/api/conversation')->assertOk()->assertJsonCount(1, 'data');
    }

    // store

    public function test_store_with_one_other_user_creates_direct_conversation(): void
    {
        [$me, $other] = User::factory(2)->create();
        Sanctum::actingAs($me);

        $this->postJson('/api/conversation', ['users' => [$other->id], 'name' => 'ignored'])->assertCreated();

        $conversation = Conversation::sole();
        $this->assertSame(ConversationTypeEnum::DIRECT, $conversation->type);
        $this->assertNull($conversation->name);
        $this->assertSame(ConversationRoleEnum::OWNER, $this->memberRole($conversation, $me));
        $this->assertSame(ConversationRoleEnum::MEMBER, $this->memberRole($conversation, $other));
    }

    public function test_store_with_several_users_creates_named_group(): void
    {
        [$me, $b, $c] = User::factory(3)->create();
        Sanctum::actingAs($me);

        $this->postJson('/api/conversation', ['users' => [$b->id, $c->id], 'name' => 'Team'])->assertCreated();

        $conversation = Conversation::sole();
        $this->assertSame(ConversationTypeEnum::GROUP, $conversation->type);
        $this->assertSame('Team', $conversation->name);
        $this->assertSame($me->id, $conversation->created_by);
        $this->assertCount(3, $conversation->users);
    }

    public function test_store_rejects_second_direct_conversation_with_same_user(): void
    {
        [$me, $other] = User::factory(2)->create();
        Sanctum::actingAs($me);
        $this->postJson('/api/conversation', ['users' => [$other->id]])->assertCreated();

        $this->postJson('/api/conversation', ['users' => [$other->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['users' => 'A direct conversation with this user already exists.']);
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_store_rejects_conversation_with_only_self(): void
    {
        $me = User::factory()->create();
        Sanctum::actingAs($me);

        $this->postJson('/api/conversation', ['users' => [$me->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['users' => 'A conversation needs at least one other user.']);
    }

    public function test_store_requires_name_for_group(): void
    {
        [$me, $b, $c] = User::factory(3)->create();
        Sanctum::actingAs($me);

        $this->postJson('/api/conversation', ['users' => [$b->id, $c->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_store_rejects_unknown_user_ids(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/conversation', ['users' => [999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('users.0');
    }

    // show (messages)

    public function test_messages_returns_conversation_messages(): void
    {
        [$me, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($me, $other);
        $message = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $other->id]);
        $conversation->update(['last_message_id' => $message->id]);
        Sanctum::actingAs($me);

        $this->getJson("/api/conversation/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', $message->body);

    }

    public function test_messages_forbids_non_member_with_403(): void
    {
        $conversation = $this->groupWith(User::factory()->create());
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/conversation/{$conversation->id}/messages")->assertForbidden();
    }

    public function test_messages_returns_404_for_unknown_conversation(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/conversation/999/messages')->assertNotFound();
    }

    // update

    public function test_update_renames_conversation(): void
    {
        $me = User::factory()->create();
        $conversation = $this->groupWith($me, User::factory()->create());
        Sanctum::actingAs($me);

        $this->patchJson("/api/conversation/{$conversation->id}", ['name' => 'Renamed'])->assertOk();

        $this->assertSame('Renamed', $conversation->fresh()->name);
    }

    public function test_update_rejects_name_longer_than_10_characters(): void
    {
        $me = User::factory()->create();
        $conversation = $this->groupWith($me, User::factory()->create());
        Sanctum::actingAs($me);

        $this->patchJson("/api/conversation/{$conversation->id}", ['name' => 'Much Too Long'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_update_promotes_members_to_admin(): void
    {
        [$me, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($me, $other);
        Sanctum::actingAs($me);

        $this->patchJson("/api/conversation/{$conversation->id}", ['make_users_admin' => [$other->id]])->assertOk();

        $this->assertSame(ConversationRoleEnum::ADMIN, $this->memberRole($conversation, $other));
    }

    public function test_update_transfers_ownership_only_in_this_conversation(): void
    {
        [$me, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($me, $other);
        $unrelated = $this->groupWith(User::factory()->create(), $other);
        Sanctum::actingAs($me);

        $this->patchJson("/api/conversation/{$conversation->id}", ['created_by' => $other->id])->assertOk();

        $this->assertSame(ConversationRoleEnum::OWNER, $this->memberRole($conversation, $other));
        $this->assertSame(ConversationRoleEnum::MEMBER, $this->memberRole($conversation, $me));
        $this->assertSame(ConversationRoleEnum::MEMBER, $this->memberRole($unrelated, $other));
        $this->assertSame($other->id, $conversation->fresh()->created_by);
    }

    public function test_update_forbids_plain_member_with_403(): void
    {
        [$owner, $member] = User::factory(2)->create();
        $conversation = $this->groupWith($owner, $member);
        Sanctum::actingAs($member);

        $this->patchJson("/api/conversation/{$conversation->id}", ['name' => 'Hacked'])->assertForbidden();

        $this->assertNotSame('Hacked', $conversation->fresh()->name);
    }

    // add users

    public function test_add_users_adds_members_and_promotes_direct_to_group(): void
    {
        [$me, $other, $new] = User::factory(3)->create();
        $conversation = Conversation::factory()->direct()->create(['created_by' => $me->id]);
        ConversationMember::factory()->owner()->create(['user_id' => $me->id, 'conversation_id' => $conversation->id]);
        ConversationMember::factory()->create(['user_id' => $other->id, 'conversation_id' => $conversation->id]);
        Sanctum::actingAs($me);

        $this->postJson("/api/conversation/{$conversation->id}/users", ['add_users' => [$new->id]])->assertOk();

        $conversation->refresh();
        $this->assertSame(ConversationTypeEnum::GROUP, $conversation->type);
        $this->assertSame('New Group', $conversation->name);
        $this->assertSame(ConversationRoleEnum::MEMBER, $this->memberRole($conversation, $new));
    }

    public function test_add_users_rejects_existing_member_with_422(): void
    {
        [$me, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($me, $other);
        Sanctum::actingAs($me);

        $this->postJson("/api/conversation/{$conversation->id}/users", ['add_users' => [$other->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('add_users.0');
    }

    public function test_add_users_forbids_plain_member_with_403(): void
    {
        [$owner, $member, $new] = User::factory(3)->create();
        $conversation = $this->groupWith($owner, $member);
        Sanctum::actingAs($member);

        $this->postJson("/api/conversation/{$conversation->id}/users", ['add_users' => [$new->id]])->assertForbidden();

        $this->assertFalse($conversation->users()->whereKey($new->id)->exists());
    }

    // delete users

    public function test_delete_users_marks_members_as_left(): void
    {
        [$me, $b, $c] = User::factory(3)->create();
        $conversation = $this->groupWith($me, $b, $c);
        Sanctum::actingAs($me);

        $this->deleteJson("/api/conversation/{$conversation->id}/users", ['delete_users' => [$c->id]])->assertOk();

        $this->assertNotNull(ConversationMember::where('user_id', $c->id)->sole()->left_at);
        $this->assertCount(2, $conversation->users);
    }

    public function test_delete_users_demotes_group_to_direct_when_two_remain(): void
    {
        [$me, $b, $c] = User::factory(3)->create();
        $conversation = $this->groupWith($me, $b, $c);
        Sanctum::actingAs($me);

        $this->deleteJson("/api/conversation/{$conversation->id}/users", ['delete_users' => [$c->id]])->assertOk();

        $conversation->refresh();
        $this->assertSame(ConversationTypeEnum::DIRECT, $conversation->type);
        $this->assertNull($conversation->name);
    }

    public function test_delete_users_deletes_conversation_when_one_remains_and_returns_204(): void
    {
        [$me, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($me, $other);
        Sanctum::actingAs($me);

        $this->deleteJson("/api/conversation/{$conversation->id}/users", ['delete_users' => [$other->id]])
            ->assertNoContent();

        $this->assertModelMissing($conversation);
    }

    public function test_delete_users_rejects_non_member_with_422(): void
    {
        [$me, $other, $outsider] = User::factory(3)->create();
        $conversation = $this->groupWith($me, $other);
        Sanctum::actingAs($me);

        $this->deleteJson("/api/conversation/{$conversation->id}/users", ['delete_users' => [$outsider->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delete_users.0');
    }

    public function test_delete_users_forbids_plain_member_with_403(): void
    {
        [$owner, $member, $other] = User::factory(3)->create();
        $conversation = $this->groupWith($owner, $member, $other);
        Sanctum::actingAs($member);

        $this->deleteJson("/api/conversation/{$conversation->id}/users", ['delete_users' => [$other->id]])
            ->assertForbidden();
    }

    // typing

    public function test_typing_broadcasts_once_within_throttle_window(): void
    {
        [$me, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($me, $other);
        Event::fake([UserTyping::class]);
        Sanctum::actingAs($me);

        $this->postJson("/api/conversation/{$conversation->id}/typing")->assertNoContent();
        $this->postJson("/api/conversation/{$conversation->id}/typing")->assertNoContent();

        Event::assertDispatchedTimes(UserTyping::class, 1);
        Event::assertDispatched(UserTyping::class, fn (UserTyping $e) => $e->conversationId === $conversation->id
            && $e->userId === $me->id
            && $e->name === $me->name);
    }

    public function test_typing_forbids_non_member_with_403(): void
    {
        $conversation = $this->groupWith(User::factory()->create());
        Event::fake([UserTyping::class]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/conversation/{$conversation->id}/typing")->assertForbidden();

        Event::assertNotDispatched(UserTyping::class);
    }

    // pin

    public function test_pin_marks_message_as_pinned_and_allows_several_pins(): void
    {
        [$me, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($me, $other);
        [$first, $second] = Message::factory(2)->create(['conversation_id' => $conversation->id, 'sender_id' => $other->id]);
        Sanctum::actingAs($me);

        $this->putJson("/api/pin/{$conversation->id}/add", ['pin_message' => $first->id])->assertOk();
        $this->putJson("/api/pin/{$conversation->id}/add", ['pin_message' => $second->id])->assertOk();

        $this->assertTrue($first->fresh()->is_pinned);
        $this->assertTrue($second->fresh()->is_pinned);
    }

    public function test_pin_rejects_message_from_another_conversation_with_422(): void
    {
        [$me, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($me, $other);
        $foreign = Message::factory()->create();
        Sanctum::actingAs($me);

        $this->putJson("/api/pin/{$conversation->id}/add", [
            'pin_message' => $foreign->id,
            'conversation_id' => $foreign->conversation_id,
        ])->assertUnprocessable()->assertJsonValidationErrors('pin_message');

        $this->assertFalse($foreign->fresh()->is_pinned);
    }

    public function test_pin_rejects_soft_deleted_message_with_422(): void
    {
        [$me, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($me, $other);
        $message = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $other->id]);
        $message->delete();
        Sanctum::actingAs($me);

        $this->putJson("/api/pin/{$conversation->id}/add", ['pin_message' => $message->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pin_message');
    }

    public function test_pin_forbids_plain_member_with_403(): void
    {
        [$owner, $member] = User::factory(2)->create();
        $conversation = $this->groupWith($owner, $member);
        $message = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $owner->id]);
        Sanctum::actingAs($member);

        $this->putJson("/api/pin/{$conversation->id}/add", ['pin_message' => $message->id])->assertForbidden();

        $this->assertFalse($message->fresh()->is_pinned);
    }

    public function test_remove_pin_unpins_message(): void
    {
        [$me, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($me, $other);
        $message = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $other->id, 'is_pinned' => true]);
        Sanctum::actingAs($me);

        $this->putJson("/api/pin/{$conversation->id}/remove", ['remove_pin_message' => $message->id])->assertOk();

        $this->assertFalse($message->fresh()->is_pinned);
    }

    public function test_remove_pin_rejects_message_that_is_not_pinned_with_422(): void
    {
        [$me, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($me, $other);
        $message = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $other->id]);
        Sanctum::actingAs($me);

        $this->putJson("/api/pin/{$conversation->id}/remove", ['remove_pin_message' => $message->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('remove_pin_message');
    }

    public function test_remove_pin_forbids_plain_member_with_403(): void
    {
        [$owner, $member] = User::factory(2)->create();
        $conversation = $this->groupWith($owner, $member);
        $message = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $owner->id, 'is_pinned' => true]);
        Sanctum::actingAs($member);

        $this->putJson("/api/pin/{$conversation->id}/remove", ['remove_pin_message' => $message->id])->assertForbidden();

        $this->assertTrue($message->fresh()->is_pinned);
    }

    public function test_pinned_returns_only_pinned_messages_to_member(): void
    {
        [$owner, $member] = User::factory(2)->create();
        $conversation = $this->groupWith($owner, $member);
        $pinned = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $owner->id, 'is_pinned' => true]);
        Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $owner->id]);
        Sanctum::actingAs($member);

        $this->getJson("/api/pin/{$conversation->id}/pinned")
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', $pinned->body);
    }

    public function test_pinned_forbids_non_member_with_403(): void
    {
        $conversation = $this->groupWith(User::factory()->create());
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/pin/{$conversation->id}/pinned")->assertForbidden();
    }

    // destroy

    public function test_destroy_deletes_conversation_with_204(): void
    {
        $me = User::factory()->create();
        $conversation = $this->groupWith($me, User::factory()->create());
        Sanctum::actingAs($me);

        $this->deleteJson("/api/conversation/{$conversation->id}")->assertNoContent();

        $this->assertModelMissing($conversation);
    }

    public function test_destroy_forbids_admin_who_is_not_owner_with_403(): void
    {
        [$owner, $admin] = User::factory(2)->create();
        $conversation = $this->groupWith($owner);
        ConversationMember::factory()->admin()->create(['user_id' => $admin->id, 'conversation_id' => $conversation->id]);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/conversation/{$conversation->id}")->assertForbidden();

        $this->assertModelExists($conversation);
    }

    // restore

    public function test_restore_readds_left_users(): void
    {
        [$me, $other, $leaver] = User::factory(3)->create();
        $conversation = $this->groupWith($me, $other);
        ConversationMember::factory()->left()->create(['user_id' => $leaver->id, 'conversation_id' => $conversation->id]);
        Sanctum::actingAs($me);

        $this->postJson("/api/conversation/{$conversation->id}/restore?".http_build_query(['users' => [$leaver->id]]))
            ->assertOk()
            ->assertJsonPath('message', 'users have been restored')
            ->assertJsonPath('users.0.email', $leaver->email);

        $this->assertTrue($conversation->users()->whereKey($leaver->id)->exists());
    }

    public function test_restore_rejects_user_who_has_not_left_with_422(): void
    {
        [$me, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($me, $other);
        Sanctum::actingAs($me);

        $this->postJson("/api/conversation/{$conversation->id}/restore?".http_build_query(['users' => [$other->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('users.0');
    }

    public function test_restore_forbids_non_owner_with_403(): void
    {
        [$owner, $member, $leaver] = User::factory(3)->create();
        $conversation = $this->groupWith($owner, $member);
        ConversationMember::factory()->left()->create(['user_id' => $leaver->id, 'conversation_id' => $conversation->id]);
        Sanctum::actingAs($member);

        $this->postJson("/api/conversation/{$conversation->id}/restore?".http_build_query(['users' => [$leaver->id]]))
            ->assertForbidden();
    }

    // force delete

    public function test_force_delete_removes_left_users_membership_rows(): void
    {
        [$me, $other, $leaver] = User::factory(3)->create();
        $conversation = $this->groupWith($me, $other);
        ConversationMember::factory()->left()->create(['user_id' => $leaver->id, 'conversation_id' => $conversation->id]);
        Sanctum::actingAs($me);

        $this->deleteJson("/api/conversation/{$conversation->id}/force_delete", ['users' => [$leaver->id]])
            ->assertNoContent();

        $this->assertDatabaseMissing('conversation_member', ['user_id' => $leaver->id]);
        $this->assertDatabaseCount('conversation_member', 2);
    }

    public function test_force_delete_forbids_non_owner_with_403(): void
    {
        [$owner, $member, $leaver] = User::factory(3)->create();
        $conversation = $this->groupWith($owner, $member);
        ConversationMember::factory()->left()->create(['user_id' => $leaver->id, 'conversation_id' => $conversation->id]);
        Sanctum::actingAs($member);

        $this->deleteJson("/api/conversation/{$conversation->id}/force_delete", ['users' => [$leaver->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('conversation_member', ['user_id' => $leaver->id]);
    }
}
