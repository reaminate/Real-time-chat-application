<?php

namespace App\Models;

use App\Enums\ConversationRoleEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
#[Fillable('user_id', 'conversation_id', 'last_read_id', 'role', 'joined_at', 'left_at')]
class ConversationMember extends Pivot
{
    /** @use HasFactory<\Database\Factories\ConversationMemberFactory> */
    use HasFactory;

    protected $casts =
    [
        'role' => ConversationRoleEnum::class,
        'joined_at' => 'date',
        'left_at' => 'date',
    ];
    protected $table = 'conversation_member';
    public $incrementing = true;
    public $timestamps = true;
    public function user(): BelongsTo 
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function conversation(): BelongsTo 
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
    public function lastRead(): BelongsTo 
    {
        return $this->belongsTo(Message::class, 'last_read_id');
    }
}
