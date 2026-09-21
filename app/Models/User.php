<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AttachmentCollectionEnum;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'last_seen_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'date',
            'password' => 'hashed',
        ];
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_member', 'user_id', 'conversation_id')
            ->using(ConversationMember::class)
            ->withPivot(['id', 'last_read_id', 'role', 'joined_at', 'left_at'])
            ->withTimestamps();
    }
    /**
     * return the conversations user is part of
     * 
     * @return HasMany<ConversationMember, User>
     */
    public function conversationMembers(): HasMany
    {
        return $this->hasMany(ConversationMember::class, 'user_id');
    }
    /**
     * return the conversations this user created
     * 
     * @return HasMany<Conversation, User>
     */
    public function conversation(): HasMany
    {
        return $this->hasMany(Conversation::class, 'created_by');
    }
    /**
     * returns the messages of this user
     * 
     * @return HasMany<Message, User>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }
    /**
     * 
     * return the avatar of this user
     * @return MorphOne<Attachment, User>
     */
    public function avatar(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable')
            ->where('collection', AttachmentCollectionEnum::AVATAR->value);
    }
}
