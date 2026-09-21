<?php

namespace App\Models;

use App\Enums\AttachmentCollectionEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['attachable_type', 'attachable_id', 'collection', 'original_name', 'file_name', 'mime_type', 'size', 'path'])]
class Attachment extends Model
{
    /** @use HasFactory<\Database\Factories\AttachmentFactory> */
    use HasFactory;

    protected $casts =
    [
        'collection' => AttachmentCollectionEnum::class,
        'size' => 'integer',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

}
