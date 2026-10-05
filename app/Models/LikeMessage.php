<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
#[Fillable('user_id', 'message_id', 'liked')]
class LikeMessage extends Pivot
{
    /** @use HasFactory<\Database\Factories\LikeMessageFactory> */
    use HasFactory;
    protected $table = 'like_message';
    public $timestamps = false;
    protected $casts =
    [
        'liked' => 'boolean',
    ];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }
}
