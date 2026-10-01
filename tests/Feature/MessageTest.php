<?php

namespace Tests\Feature;

use App\Enums\AttachmentCollectionEnum;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
}
