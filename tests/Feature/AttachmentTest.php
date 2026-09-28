<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function storedAttachment(): Attachment
    {
        Storage::fake('local');
        Storage::disk('local')->put('attachments/stored.pdf', 'file contents');

        return Attachment::factory()->document()->create([
            'path' => 'attachments/stored.pdf',
            'original_name' => 'report.pdf',
        ]);
    }

    private function memberOf(Attachment $attachment): User
    {
        $user = User::factory()->create();
        ConversationMember::factory()->create([
            'user_id' => $user->id,
            'conversation_id' => Message::findOrFail($attachment->attachable_id)->conversation_id,
        ]);

        return $user;
    }

    public function test_member_downloads_message_attachment(): void
    {
        $attachment = $this->storedAttachment();
        Sanctum::actingAs($this->memberOf($attachment));

        $this->get("/api/attachments/{$attachment->id}/download")
            ->assertOk()
            ->assertStreamedContent('file contents');
    }

    public function test_non_member_is_forbidden_with_403(): void
    {
        $attachment = $this->storedAttachment();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/attachments/{$attachment->id}/download")->assertForbidden();
    }

    public function test_any_user_downloads_avatar_from_public_disk(): void
    {
        Storage::fake('public');
        $avatar = Attachment::factory()->avatar()->create();
        Storage::disk('public')->put($avatar->path, 'avatar contents');
        Sanctum::actingAs(User::factory()->create());

        $this->get("/api/attachments/{$avatar->id}/download")
            ->assertOk()
            ->assertStreamedContent('avatar contents');
    }

    public function test_missing_file_returns_404(): void
    {
        $attachment = $this->storedAttachment();
        Storage::disk('local')->delete($attachment->path);
        Sanctum::actingAs($this->memberOf($attachment));

        $this->getJson("/api/attachments/{$attachment->id}/download")->assertNotFound();
    }

    public function test_returns_401_without_token(): void
    {
        $attachment = $this->storedAttachment();

        $this->getJson("/api/attachments/{$attachment->id}/download")->assertUnauthorized();
    }
}
