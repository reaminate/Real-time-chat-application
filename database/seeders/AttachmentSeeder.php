<?php

namespace Database\Seeders;

use App\Enums\MessageTypeEnum;
use App\Models\Attachment;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SplFileInfo;

class AttachmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
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

        $avatars = collect(File::files(database_path('seeders/avatars')))->shuffle();

        User::whereDoesntHave('avatar')
            ->inRandomOrder()
            ->limit($avatars->count())
            ->get()
            ->each(fn (User $user, int $i) => $this->createAvatarFor($user, $avatars[$i]));
    }

    /**
     * copies a real image onto the public disk so the seeded avatar can actually be served
     */
    private function createAvatarFor(User $user, SplFileInfo $image): void
    {
        $fileName = Str::uuid().'.'.$image->getExtension();
        $path = 'avatars/'.$fileName;

        Storage::disk('public')->put($path, File::get($image->getPathname()));

        Attachment::factory()->avatar()->create([
            'attachable_id' => $user->id,
            'original_name' => $image->getFilename(),
            'file_name' => $fileName,
            'mime_type' => File::mimeType($image->getPathname()),
            'size' => $image->getSize(),
            'path' => $path,
        ]);
    }
}
