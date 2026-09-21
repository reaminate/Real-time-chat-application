<?php

namespace Database\Factories;

use App\Enums\AttachmentCollectionEnum;
use App\Enums\AttachmentFileTypeEnum;
use App\Enums\AttachmentImageTypeEnum;
use App\Models\Attachment;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mimeType = fake()->randomElement([
            ...array_column(AttachmentImageTypeEnum::cases(), 'value'),
            ...array_column(AttachmentFileTypeEnum::cases(), 'value'),
        ]);

        return $this->fileAttributes($mimeType) + [
            'attachable_type' => (new Message)->getMorphClass(),
            'attachable_id' => Message::factory(),
            'collection' => AttachmentCollectionEnum::ATTACHMENT,
        ];
    }

    /**
     * An image attachment belonging to a message.
     */
    public function image(): static
    {
        return $this->state(fn () => $this->fileAttributes(
            fake()->randomElement(array_column(AttachmentImageTypeEnum::cases(), 'value')),
        ));
    }

    /**
     * A PDF/DOCX attachment belonging to a message.
     */
    public function document(): static
    {
        return $this->state(fn () => $this->fileAttributes(
            fake()->randomElement(array_column(AttachmentFileTypeEnum::cases(), 'value')),
        ));
    }

    /**
     * An avatar image belonging to a user (images only, max 2 MB).
     */
    public function avatar(): static
    {
        return $this->state(fn () => [
            ...$this->fileAttributes(
                fake()->randomElement(array_column(AttachmentImageTypeEnum::cases(), 'value')),
                'avatars',
            ),
            'size' => fake()->numberBetween(1_000, 2_000_000),
            'attachable_type' => (new User)->getMorphClass(),
            'attachable_id' => User::factory(),
            'collection' => AttachmentCollectionEnum::AVATAR,
        ]);
    }

    /**
     * Build the file-related attributes for the given mime type.
     *
     * @return array<string, mixed>
     */
    private function fileAttributes(string $mimeType, string $directory = 'attachments'): array
    {
        $extension = match ($mimeType) {
            AttachmentImageTypeEnum::PNG->value => 'png',
            AttachmentImageTypeEnum::JPEG->value => 'jpg',
            AttachmentFileTypeEnum::PDF->value => 'pdf',
            AttachmentFileTypeEnum::DOCX->value => 'docx',
        };

        $fileName = Str::uuid().'.'.$extension;

        return [
            'original_name' => fake()->word().'.'.$extension,
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'size' => fake()->numberBetween(1_000, 5_000_000),
            'path' => $directory.'/'.$fileName,
        ];
    }
}
