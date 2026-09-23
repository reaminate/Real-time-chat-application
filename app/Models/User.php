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
use Illuminate\Support\Str;
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
    /** Returns all conversations this user is currently (not left) a member of. */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_member', 'user_id', 'conversation_id')
            ->using(ConversationMember::class)
            ->withPivot(['id', 'last_read_id', 'role', 'joined_at', 'left_at'])
            ->withTimestamps()
            ->wherePivotNull('left_at');
    }

    /** Returns this user's conversation_member pivot rows (their membership record in each conversation). updating that record*/
    public function conversationMembers(): HasMany
    {
        return $this->hasMany(ConversationMember::class, 'user_id');
    }

    /** Returns the conversations this user created. to cehck convos this user created*/
    public function createdConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'created_by');
    }

    /** Returns the messages sent by this user. */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /** Returns this user's single avatar attachment. */
    public function avatar(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable')
            ->where('collection', AttachmentCollectionEnum::AVATAR->value);
    }
    protected static function booted():void
    {
        static::saving(function($model){
            $model->friend_id = Str::slug($model->email);
        });
    }
    public function getRouteKeyName(): string
    {
        return 'friend_id';
    }
}
