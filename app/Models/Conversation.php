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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_member', 'conversation_id', 'user_id')
            ->using(ConversationMember::class)
            ->withPivot(['id', 'last_read_id', 'role', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function conversationMembers(): HasMany
    {
        return $this->hasMany(ConversationMember::class, 'conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }
}
