<?php

namespace Tests\Feature;

use App\Models\Attachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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

    public function test_signed_url_downloads_file_with_original_name(): void
    {
        $attachment = $this->storedAttachment();
        $url = URL::temporarySignedRoute('attachments.download', now()->addMinutes(30), ['attachment' => $attachment->id]);

        $this->get($url)
            ->assertOk()
            ->assertDownload('report.pdf');
    }

    public function test_unsigned_url_is_rejected(): void
    {
        $attachment = $this->storedAttachment();

        $this->getJson("/api/attachments/{$attachment->id}/download")->assertForbidden();
    }

    public function test_expired_url_is_rejected(): void
    {
        $attachment = $this->storedAttachment();
        $url = URL::temporarySignedRoute('attachments.download', now()->addMinutes(30), ['attachment' => $attachment->id]);

        $this->travel(31)->minutes();

        $this->getJson($url)->assertForbidden();
    }
}
