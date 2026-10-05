<?php

namespace Tests\Feature;

use App\Enums\AttachmentCollectionEnum;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\LikeMessage;
use App\Models\Message;
use App\Models\User;
use App\Notifications\UserReactedToYourMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MessageTest extends TestCase
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

    private function messageFrom(User $sender, Conversation $conversation): Message
    {
        return Message::factory()->create(['sender_id' => $sender->id, 'conversation_id' => $conversation->id]);
    }

    public static function protectedRoutes(): array
    {
        return [
            'index' => ['getJson', '/api/message'],
            'store' => ['postJson', '/api/message'],
            'show' => ['getJson', '/api/message/1'],
            'update' => ['patchJson', '/api/message/1'],
            'destroy' => ['deleteJson', '/api/message/1'],
            'restore' => ['getJson', '/api/message/1/restore'],
            'force delete' => ['deleteJson', '/api/message/1/force_delete'],
            'like' => ['postJson', '/api/message/1/like'],
            'dislike' => ['postJson', '/api/message/1/dislike'],
            'remove reaction' => ['deleteJson', '/api/message/1/remove_reaction'],
        ];
    }

    #[DataProvider('protectedRoutes')]
    public function test_returns_401_without_token(string $method, string $uri): void
    {
        $this->{$method}($uri)->assertUnauthorized();
    }

    // index

    public function test_index_lists_only_own_messages(): void
    {
        [$me, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($me, $other);
        $this->messageFrom($me, $conversation);
        $this->messageFrom($me, $conversation);
        $this->messageFrom($other, $conversation);
        Sanctum::actingAs($me);

        $this->getJson('/api/message')->assertOk()->assertJsonCount(2, 'data');
    }

    // store

    public function test_store_text_message_persists_and_updates_last_message(): void
    {
        [$me, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($me, $other);
        Sanctum::actingAs($me);

        $this->postJson('/api/message', ['conversation_id' => $conversation->id, 'type' => 'text', 'body' => 'hello'])
            ->assertCreated();

        $message = Message::sole();
        $this->assertSame('hello', $message->body);
        $this->assertSame($me->id, $message->sender_id);
        $this->assertSame($message->id, $conversation->fresh()->last_message_id);
    }

    public function test_store_image_message_saves_attachment(): void
    {
        [$me, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($me, $other);
        Storage::fake('local');
        Sanctum::actingAs($me);

        $this->post('/api/message', [
            'conversation_id' => $conversation->id,
            'type' => 'image',
            'attachment' => UploadedFile::fake()->create('pic.png', 10, 'image/png'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $attachment = Message::sole()->attachments()->sole();
        $this->assertSame(AttachmentCollectionEnum::ATTACHMENT, $attachment->collection);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_store_reply_links_to_original_message(): void
    {
        [$me, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($me, $other);
        $original = $this->messageFrom($other, $conversation);
        Sanctum::actingAs($me);

        $this->postJson('/api/message', [
            'conversation_id' => $conversation->id,
            'reply_to' => $original->id,
            'type' => 'text',
            'body' => 'reply',
        ])->assertCreated();

        $this->assertSame($original->id, Message::where('body', 'reply')->sole()->reply_to);
    }

    public function test_store_rejects_reply_to_message_from_another_conversation(): void
    {
        [$me, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($me, $other);
        $foreign = $this->messageFrom($other, $this->groupWith($other));
        Sanctum::actingAs($me);

        $this->postJson('/api/message', [
            'conversation_id' => $conversation->id,
            'reply_to' => $foreign->id,
            'type' => 'text',
            'body' => 'reply',
        ])->assertUnprocessable()->assertJsonValidationErrors('reply_to');
    }

    public function test_store_rejects_empty_payload_with_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/message', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['conversation_id', 'type']);
    }

    public function test_store_requires_body_for_text_message(): void
    {
        $me = User::factory()->create();
        $conversation = $this->groupWith($me, User::factory()->create());
        Sanctum::actingAs($me);

        $this->postJson('/api/message', ['conversation_id' => $conversation->id, 'type' => 'text'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_store_requires_attachment_for_file_message(): void
    {
        $me = User::factory()->create();
        $conversation = $this->groupWith($me, User::factory()->create());
        Sanctum::actingAs($me);

        $this->postJson('/api/message', ['conversation_id' => $conversation->id, 'type' => 'file'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachment');
    }

    public static function invalidAttachments(): array
    {
        return [
            'pdf sent as image' => ['image', 'doc.pdf', 10, 'application/pdf'],
            'png sent as file' => ['file', 'pic.png', 10, 'image/png'],
            'executable' => ['file', 'virus.exe', 10, 'application/x-msdownload'],
            'oversized image' => ['image', 'big.png', 10241, 'image/png'],
            'attachment on text message' => ['text', 'pic.png', 10, 'image/png'],
        ];
    }

    #[DataProvider('invalidAttachments')]
    public function test_store_rejects_invalid_attachment_with_422(string $type, string $name, int $kilobytes, string $mime): void
    {
        $me = User::factory()->create();
        $conversation = $this->groupWith($me, User::factory()->create());
        Storage::fake('local');
        Sanctum::actingAs($me);

        $this->post('/api/message', [
            'conversation_id' => $conversation->id,
            'type' => $type,
            'body' => 'hi',
            'attachment' => UploadedFile::fake()->create($name, $kilobytes, $mime),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachment');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_store_saves_real_mime_type(): void
    {
        $me = User::factory()->create();
        $conversation = $this->groupWith($me, User::factory()->create());
        Storage::fake('local');
        Sanctum::actingAs($me);

        $this->post('/api/message', [
            'conversation_id' => $conversation->id,
            'type' => 'file',
            'attachment' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame('application/pdf', Attachment::sole()->mime_type);
    }

    public function test_store_forbids_non_member_with_403(): void
    {
        $conversation = $this->groupWith(User::factory()->create());
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/message', ['conversation_id' => $conversation->id, 'type' => 'text', 'body' => 'hi'])
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    // show

    public function test_show_returns_message_with_sender(): void
    {
        [$me, $other] = User::factory(2)->create();
        $message = $this->messageFrom($other, $this->groupWith($me, $other));
        Sanctum::actingAs($me);

        $this->getJson("/api/message/{$message->id}")
            ->assertOk()
            ->assertJsonPath('body', $message->body)
            ->assertJsonPath('sender.email', $other->email);
    }

    public function test_show_marks_message_read_for_viewer(): void
    {
        [$me, $other] = User::factory(2)->create();
        $message = $this->messageFrom($other, $this->groupWith($me, $other));
        Sanctum::actingAs($me);

        $this->getJson("/api/message/{$message->id}")->assertOk();

        $this->assertSame($message->id, ConversationMember::where('user_id', $me->id)->sole()->last_read_id);
        $this->assertNull(ConversationMember::where('user_id', $other->id)->sole()->last_read_id);
    }

    public function test_show_returns_404_for_unknown_message(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/message/999')->assertNotFound();
    }

    // update

    public function test_update_edits_own_text_message(): void
    {
        $me = User::factory()->create();
        $conversation = $this->groupWith($me, User::factory()->create());
        $message = $this->messageFrom($me, $conversation);
        Sanctum::actingAs($me);

        $this->patchJson("/api/message/{$message->id}", ['conversation_id' => $conversation->id, 'body' => 'edited'])
            ->assertOk();

        $this->assertSame('edited', $message->fresh()->body);
    }

    public function test_update_replaces_attachment_on_file_message(): void
    {
        $me = User::factory()->create();
        $conversation = $this->groupWith($me, User::factory()->create());
        $message = Message::factory()->file()->create(['sender_id' => $me->id, 'conversation_id' => $conversation->id]);
        Attachment::factory()->document()->create(['attachable_id' => $message->id]);
        Storage::fake('local');
        Sanctum::actingAs($me);

        $this->patch("/api/message/{$message->id}", [
            'conversation_id' => $conversation->id,
            'attachment' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'),
            'make_sticker' => false,
        ], ['Accept' => 'application/json'])->assertOk();

        $attachment = $message->attachments()->sole();
        $this->assertSame('new.pdf', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_update_deletes_replaced_file_from_disk(): void
    {
        $me = User::factory()->create();
        $conversation = $this->groupWith($me, User::factory()->create());
        $message = Message::factory()->file()->create(['sender_id' => $me->id, 'conversation_id' => $conversation->id]);
        Storage::fake('local');
        Storage::disk('local')->put('attachments/old.pdf', 'old contents');
        Attachment::factory()->document()->create(['attachable_id' => $message->id, 'path' => 'attachments/old.pdf']);
        Sanctum::actingAs($me);

        $this->patch("/api/message/{$message->id}", [
            'conversation_id' => $conversation->id,
            'attachment' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        Storage::disk('local')->assertMissing('attachments/old.pdf');
        $attachment = $message->attachments()->sole();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame(basename($attachment->path), $attachment->file_name);
    }

    public function test_update_rejects_attachment_not_matching_message_type(): void
    {
        $me = User::factory()->create();
        $conversation = $this->groupWith($me, User::factory()->create());
        $message = Message::factory()->file()->create(['sender_id' => $me->id, 'conversation_id' => $conversation->id]);
        $original = Attachment::factory()->document()->create(['attachable_id' => $message->id]);
        Storage::fake('local');
        Sanctum::actingAs($me);

        $this->patch("/api/message/{$message->id}", [
            'conversation_id' => $conversation->id,
            'attachment' => UploadedFile::fake()->create('pic.png', 10, 'image/png'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachment');

        $this->assertSame($original->path, $message->attachments()->sole()->path);
    }

    public function test_update_rejects_oversized_attachment(): void
    {
        $me = User::factory()->create();
        $conversation = $this->groupWith($me, User::factory()->create());
        $message = Message::factory()->file()->create(['sender_id' => $me->id, 'conversation_id' => $conversation->id]);
        Attachment::factory()->document()->create(['attachable_id' => $message->id]);
        Storage::fake('local');
        Sanctum::actingAs($me);

        $this->patch("/api/message/{$message->id}", [
            'conversation_id' => $conversation->id,
            'attachment' => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachment');
    }

    public function test_update_rejects_missing_conversation_id_with_422(): void
    {
        $me = User::factory()->create();
        $message = $this->messageFrom($me, $this->groupWith($me));
        Sanctum::actingAs($me);

        $this->patchJson("/api/message/{$message->id}", ['body' => 'edited'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('conversation_id');
    }

    public function test_update_forbids_editing_someone_elses_message_with_403(): void
    {
        [$owner, $other] = User::factory(2)->create();
        $conversation = $this->groupWith($owner, $other);
        $message = $this->messageFrom($other, $conversation);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/message/{$message->id}", ['conversation_id' => $conversation->id, 'body' => 'edited'])
            ->assertForbidden();

        $this->assertNotSame('edited', $message->fresh()->body);
    }

    // destroy

    public function test_destroy_soft_deletes_own_message(): void
    {
        $me = User::factory()->create();
        $message = $this->messageFrom($me, $this->groupWith($me));
        Sanctum::actingAs($me);

        $this->deleteJson("/api/message/{$message->id}")->assertNoContent();

        $this->assertSoftDeleted($message);
    }

    public function test_destroy_lets_admin_delete_members_message(): void
    {
        [$owner, $admin, $member] = User::factory(3)->create();
        $conversation = $this->groupWith($owner, $member);
        ConversationMember::factory()->admin()->create(['user_id' => $admin->id, 'conversation_id' => $conversation->id]);
        $message = $this->messageFrom($member, $conversation);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/message/{$message->id}")->assertNoContent();

        $this->assertSoftDeleted($message);
    }

    public function test_destroy_forbids_member_deleting_others_message_with_403(): void
    {
        [$owner, $member] = User::factory(2)->create();
        $conversation = $this->groupWith($owner, $member);
        $message = $this->messageFrom($owner, $conversation);
        Sanctum::actingAs($member);

        $this->deleteJson("/api/message/{$message->id}")->assertForbidden();

        $this->assertNotSoftDeleted($message);
    }

    // restore

    public function test_restore_brings_back_soft_deleted_message(): void
    {
        [$owner, $admin, $member] = User::factory(3)->create();
        $conversation = $this->groupWith($owner, $member);
        ConversationMember::factory()->admin()->create(['user_id' => $admin->id, 'conversation_id' => $conversation->id]);
        $message = $this->messageFrom($member, $conversation);
        $message->delete();
        Sanctum::actingAs($admin);

        $this->getJson("/api/message/{$message->id}/restore")
            ->assertCreated()
            ->assertJsonPath('body', $message->body);

        $this->assertNotSoftDeleted($message);
    }

    public function test_restore_forbids_plain_member_with_403(): void
    {
        [$owner, $member] = User::factory(2)->create();
        $conversation = $this->groupWith($owner, $member);
        $message = $this->messageFrom($member, $conversation);
        $message->delete();
        Sanctum::actingAs($member);

        $this->getJson("/api/message/{$message->id}/restore")->assertForbidden();

        $this->assertSoftDeleted($message);
    }

    // force delete

    public function test_force_delete_permanently_removes_message(): void
    {
        [$owner, $admin, $member] = User::factory(3)->create();
        $conversation = $this->groupWith($owner, $member);
        ConversationMember::factory()->admin()->create(['user_id' => $admin->id, 'conversation_id' => $conversation->id]);
        $message = $this->messageFrom($member, $conversation);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/message/{$message->id}/force_delete")->assertNoContent();

        $this->assertModelMissing($message);
    }

    public function test_force_delete_forbids_plain_member_with_403(): void
    {
        [$owner, $member] = User::factory(2)->create();
        $conversation = $this->groupWith($owner, $member);
        $message = $this->messageFrom($member, $conversation);
        Sanctum::actingAs($member);

        $this->deleteJson("/api/message/{$message->id}/force_delete")->assertForbidden();

        $this->assertModelExists($message);
    }

    // like / dislike

    public function test_like_adds_user_to_liked_users(): void
    {
        [$me, $other] = User::factory(2)->create();
        $message = $this->messageFrom($other, $this->groupWith($me, $other));
        Sanctum::actingAs($me);

        $this->postJson("/api/message/{$message->id}/like")
            ->assertOk()
            ->assertJsonPath('liked_users.0.id', $me->id)
            ->assertJsonCount(0, 'disliked_users');

        $this->assertDatabaseHas('like_message', ['message_id' => $message->id, 'user_id' => $me->id, 'liked' => true]);
    }

    public function test_dislike_adds_user_to_disliked_users(): void
    {
        [$me, $other] = User::factory(2)->create();
        $message = $this->messageFrom($other, $this->groupWith($me, $other));
        Sanctum::actingAs($me);

        $this->postJson("/api/message/{$message->id}/dislike")
            ->assertOk()
            ->assertJsonPath('disliked_users.0.id', $me->id)
            ->assertJsonCount(0, 'liked_users');

        $this->assertDatabaseHas('like_message', ['message_id' => $message->id, 'user_id' => $me->id, 'liked' => false]);
    }

    public function test_dislike_switches_an_existing_like(): void
    {
        [$me, $other] = User::factory(2)->create();
        $message = $this->messageFrom($other, $this->groupWith($me, $other));
        LikeMessage::factory()->liked()->create(['message_id' => $message->id, 'user_id' => $me->id]);
        Sanctum::actingAs($me);

        $this->postJson("/api/message/{$message->id}/dislike")
            ->assertOk()
            ->assertJsonCount(0, 'liked_users')
            ->assertJsonPath('disliked_users.0.id', $me->id);

        $this->assertDatabaseCount('like_message', 1);
        $this->assertDatabaseHas('like_message', ['message_id' => $message->id, 'user_id' => $me->id, 'liked' => false]);
    }

    public function test_liking_twice_keeps_a_single_reaction(): void
    {
        [$me, $other] = User::factory(2)->create();
        $message = $this->messageFrom($other, $this->groupWith($me, $other));
        Sanctum::actingAs($me);

        $this->postJson("/api/message/{$message->id}/like")->assertOk();
        $this->postJson("/api/message/{$message->id}/like")->assertOk()->assertJsonCount(1, 'liked_users');

        $this->assertDatabaseCount('like_message', 1);
    }

    public function test_like_keeps_other_users_reactions(): void
    {
        [$me, $other, $third] = User::factory(3)->create();
        $message = $this->messageFrom($other, $this->groupWith($me, $other, $third));
        LikeMessage::factory()->liked()->create(['message_id' => $message->id, 'user_id' => $third->id]);
        Sanctum::actingAs($me);

        $this->postJson("/api/message/{$message->id}/like")->assertOk()->assertJsonCount(2, 'liked_users');
    }

    public function test_like_forbids_non_member_with_403(): void
    {
        $owner = User::factory()->create();
        $message = $this->messageFrom($owner, $this->groupWith($owner));
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/message/{$message->id}/like")->assertForbidden();
        $this->postJson("/api/message/{$message->id}/dislike")->assertForbidden();

        $this->assertDatabaseCount('like_message', 0);
    }

    public function test_like_forbids_member_who_left_with_403(): void
    {
        [$owner, $leaver] = User::factory(2)->create();
        $conversation = $this->groupWith($owner);
        ConversationMember::factory()->left()->create(['user_id' => $leaver->id, 'conversation_id' => $conversation->id]);
        $message = $this->messageFrom($owner, $conversation);
        Sanctum::actingAs($leaver);

        $this->postJson("/api/message/{$message->id}/like")->assertForbidden();
    }

    public function test_like_returns_404_for_unknown_message(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/message/999/like')->assertNotFound();
    }

    // remove reaction

    public static function reactionStates(): array
    {
        return [
            'like' => [true],
            'dislike' => [false],
        ];
    }

    #[DataProvider('reactionStates')]
    public function test_remove_reaction_deletes_own_reaction(bool $liked): void
    {
        [$me, $other] = User::factory(2)->create();
        $message = $this->messageFrom($other, $this->groupWith($me, $other));
        LikeMessage::factory()->create(['message_id' => $message->id, 'user_id' => $me->id, 'liked' => $liked]);
        Sanctum::actingAs($me);

        $this->deleteJson("/api/message/{$message->id}/remove_reaction")
            ->assertOk()
            ->assertJsonCount(0, 'liked_users')
            ->assertJsonCount(0, 'disliked_users');

        $this->assertDatabaseCount('like_message', 0);
    }

    public function test_remove_reaction_forbids_user_without_reaction_with_403(): void
    {
        [$me, $other] = User::factory(2)->create();
        $message = $this->messageFrom($other, $this->groupWith($other, $me));
        Sanctum::actingAs($me);

        $this->deleteJson("/api/message/{$message->id}/remove_reaction")->assertForbidden();
    }

    public function test_remove_reaction_forbids_member_removing_someone_elses_with_403(): void
    {
        [$owner, $me, $other] = User::factory(3)->create();
        $message = $this->messageFrom($owner, $this->groupWith($owner, $me, $other));
        LikeMessage::factory()->create(['message_id' => $message->id, 'user_id' => $me->id]);
        LikeMessage::factory()->create(['message_id' => $message->id, 'user_id' => $other->id]);
        Sanctum::actingAs($me);

        $this->deleteJson("/api/message/{$message->id}/remove_reaction", ['user_id' => $other->id])->assertForbidden();

        $this->assertDatabaseHas('like_message', ['message_id' => $message->id, 'user_id' => $other->id]);
    }

    public function test_remove_reaction_lets_admin_remove_someone_elses(): void
    {
        [$owner, $admin, $member] = User::factory(3)->create();
        $conversation = $this->groupWith($owner, $member);
        ConversationMember::factory()->admin()->create(['user_id' => $admin->id, 'conversation_id' => $conversation->id]);
        $message = $this->messageFrom($owner, $conversation);
        LikeMessage::factory()->create(['message_id' => $message->id, 'user_id' => $member->id]);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/message/{$message->id}/remove_reaction", ['user_id' => $member->id])->assertOk();

        $this->assertDatabaseCount('like_message', 0);
    }

    public function test_remove_reaction_rejects_unknown_user_id_with_422(): void
    {
        $me = User::factory()->create();
        $message = $this->messageFrom($me, $this->groupWith($me));
        Sanctum::actingAs($me);

        $this->deleteJson("/api/message/{$message->id}/remove_reaction", ['user_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    // reaction notifications

    public function test_reacting_notifies_message_sender(): void
    {
        Notification::fake();
        [$me, $other] = User::factory(2)->create();
        $message = $this->messageFrom($other, $this->groupWith($me, $other));
        Sanctum::actingAs($me);

        $this->postJson("/api/message/{$message->id}/dislike")->assertOk();

        Notification::assertSentTo($other, UserReactedToYourMessage::class, fn ($n) => $n->user->is($me)
            && $n->liked === false
            && $n->toArray($other)['reactor_id'] === $me->id);
    }

    public function test_reacting_to_own_message_sends_no_notification(): void
    {
        Notification::fake();
        $me = User::factory()->create();
        $message = $this->messageFrom($me, $this->groupWith($me));
        Sanctum::actingAs($me);

        $this->postJson("/api/message/{$message->id}/like")->assertOk();

        Notification::assertNothingSent();
    }

    public function test_repeating_the_same_reaction_notifies_each_time(): void
    {
        Notification::fake();
        [$me, $other] = User::factory(2)->create();
        $message = $this->messageFrom($other, $this->groupWith($me, $other));
        Sanctum::actingAs($me);

        $this->postJson("/api/message/{$message->id}/like")->assertOk();
        $this->postJson("/api/message/{$message->id}/like")->assertOk();

        Notification::assertSentToTimes($other, UserReactedToYourMessage::class, 2);
    }

    // reaction rate limit

    private function exhaustReactionLimit(Message $message): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->postJson("/api/message/{$message->id}/like")->assertOk();
        }
    }

    public function test_reactions_are_throttled_after_30_per_minute(): void
    {
        Notification::fake();
        $me = User::factory()->create();
        $message = $this->messageFrom($me, $this->groupWith($me));
        Sanctum::actingAs($me);

        $this->exhaustReactionLimit($message);

        $this->postJson("/api/message/{$message->id}/like")
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'too many reactions in 1 minute')
            ->assertHeader('Retry-After')
            ->assertHeader('X-RateLimit-Remaining', 0);
    }

    public function test_reaction_limit_is_shared_across_reaction_routes(): void
    {
        Notification::fake();
        $me = User::factory()->create();
        $message = $this->messageFrom($me, $this->groupWith($me));
        Sanctum::actingAs($me);

        $this->exhaustReactionLimit($message);

        $this->postJson("/api/message/{$message->id}/dislike")->assertTooManyRequests();
        $this->deleteJson("/api/message/{$message->id}/remove_reaction")->assertTooManyRequests();
    }

    public function test_reaction_limit_is_tracked_per_user(): void
    {
        Notification::fake();
        [$me, $other] = User::factory(2)->create();
        $message = $this->messageFrom($me, $this->groupWith($me, $other));
        Sanctum::actingAs($me);
        $this->exhaustReactionLimit($message);

        Sanctum::actingAs($other);

        $this->postJson("/api/message/{$message->id}/like")->assertOk();
    }

    public function test_reaction_limit_resets_after_a_minute(): void
    {
        Notification::fake();
        $me = User::factory()->create();
        $message = $this->messageFrom($me, $this->groupWith($me));
        Sanctum::actingAs($me);
        $this->exhaustReactionLimit($message);

        $this->travel(61)->seconds();

        $this->postJson("/api/message/{$message->id}/like")->assertOk();
    }

    public function test_reaction_limit_does_not_affect_other_message_routes(): void
    {
        Notification::fake();
        $me = User::factory()->create();
        $message = $this->messageFrom($me, $this->groupWith($me));
        Sanctum::actingAs($me);
        $this->exhaustReactionLimit($message);

        $this->getJson("/api/message/{$message->id}")->assertOk();
    }
}
