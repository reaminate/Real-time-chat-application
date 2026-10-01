<?php

namespace App\Enums;

enum MessageTypeEnum: string
{
    case TEXT = 'text';
    case IMAGE = 'image';
    case FILE = 'file';

    /**
     *
     * @return array<string>
     */
    public function allowedMimeTypes(): array
    {
        return array_column(match ($this) {
            self::IMAGE => AttachmentImageTypeEnum::cases(),
            self::FILE => AttachmentFileTypeEnum::cases(),
            self::TEXT => [], //left blank cause this is called only for attachments.
        }, 'value');
    }
}
