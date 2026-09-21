<?php

namespace Database\Seeders;

use App\Enums\MessageTypeEnum;
use App\Models\Attachment;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;

class AttachmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Gives every image/file message an attachment (soft-deleted messages
     * keep theirs) and 6 users an avatar. Only the database rows are
     * created; no files are written to storage.
     */
    public function run(): void
    {
        Message::withTrashed()
            ->whereIn('type', [MessageTypeEnum::IMAGE->value, MessageTypeEnum::FILE->value])
            ->doesntHave('attachments')
            ->get()
            ->each(function (Message $message) {
                $attachment = $message->type === MessageTypeEnum::IMAGE
                    ? Attachment::factory()->image()
                    : Attachment::factory()->document();

                $attachment->create(['attachable_id' => $message->id]);
            });

        User::whereDoesntHave('avatar')
            ->inRandomOrder()
            ->limit(6)
            ->get()
            ->each(fn (User $user) => Attachment::factory()->avatar()->create(['attachable_id' => $user->id]));
    }
}
