<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('conversation.{conversation}', function($user, Conversation $conversation){
    return $conversation->users()->where('users.id', $user->__get('id'))->exists();
});
