<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

// compares raw ids: User's route key is friend_id, so model binding would look up the wrong column
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('conversation.{conversation}', function ($user, Conversation $conversation) {
    return $conversation->users()->where('users.id', $user->__get('id'))->exists();
});
