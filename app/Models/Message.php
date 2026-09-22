<?php

namespace App\Models;

use App\Enums\AttachmentCollectionEnum;
use App\Enums\MessageTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['conversation_id', 'sender_id', 'reply_to', 'type', 'body', 'edited_at'])]
class Message extends Model
{
    /** @use HasFactory<\Database\Factories\MessageFactory> */
    use HasFactory, SoftDeletes;

    protected $casts =
    [
        'type' => MessageTypeEnum::class,
        'edited_at' => 'datetime',
    ];

    /** Returns the conversation this message belongs to. */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /** Returns the user who sent this message. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** Returns the message this one is a reply to. */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to');
    }

    /** Returns the messages that reply to this message. */
    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'reply_to');
    }

    /** Returns the file attachments on this message. */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')
            ->where('collection', AttachmentCollectionEnum::ATTACHMENT->value);
    }
}
