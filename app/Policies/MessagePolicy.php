<?php

namespace App\Policies;

use App\Enums\ConversationRoleEnum;
use App\Models\User;
use App\Models\Message;
use Illuminate\Auth\Access\Response;

class MessagePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Message $message): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Message $message): bool
    {
        return $this->isOwner($user, $message);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Message $message): bool
    {
        return $this->isOwner($user, $message) || $this->isAdmin($user, $message);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Message $message): bool
    {
        return $this->isAdmin($user, $message);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Message $message): bool
    {
        return $this->isAdmin($user, $message);
    }
    /**
     * returns true if youre the owner of the message
     * @param User $user
     * @param Message $message
     * @return bool
     */
    private function isOwner(User $user, Message $message): bool 
    {
        return $message->__get('sender_id') === $user->__get('id');
    }
    /**
     * returns true if the user is an admin of the convo that message is being sent to
     * @param User $user
     * @param Message $message
     * @return bool
     */
    private function isAdmin(User $user, Message $message): bool
    {
        return $message->conversation->conversationMembers()
        ->where('user_id', $user->__get('id'))
        ->where('role', ConversationRoleEnum::ADMIN)
        ->exists();
    }
    
    
}
