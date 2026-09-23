<?php

namespace App\Models;

use App\Enums\ConversationTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'name', 'last_message_id', 'created_by'])]
class Conversation extends Model
{
    /** @use HasFactory<\Database\Factories\ConversationFactory> */
    use HasFactory;

    protected $casts =
    [
        'type' => ConversationTypeEnum::class,
    ];

    /** Returns the user who created this conversation. */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Returns this conversation's most recent message. */
    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    /** Returns all active (not left) users who are members of this conversation (via the conversation_member pivot). */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_member', 'conversation_id', 'user_id')
            ->using(ConversationMember::class)
            ->withPivot(['id', 'last_read_id', 'role', 'joined_at', 'left_at'])
            ->withTimestamps()
            ->wherePivotNull('left_at');
    }

    /** Returns this conversation's conversation_member pivot rows. */
    public function conversationMembers(): HasMany
    {
        return $this->hasMany(ConversationMember::class, 'conversation_id');
    }

    /** Returns all messages posted in this conversation. */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }
}
