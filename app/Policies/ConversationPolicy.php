<?php

namespace App\Policies;

use App\Enums\ConversationRoleEnum;
use App\Models\User;
use App\Models\Conversation;
use Illuminate\Auth\Access\Response;

class ConversationPolicy
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
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->conversationMembers()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();
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
    public function update(User $user, Conversation $conversation): bool
    {
        return $this->isAdmin($user, $conversation);  
    }
    /**
     * manages adding and deleting users
     * @param User $user
     * @param Conversation $conversation
     * @return bool
     */
    public function manageUsers(User $user, Conversation $conversation): bool 
    {
        return $this->isAdmin($user, $conversation);
    }
    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Conversation $conversation): bool
    {
        return $this->isOwner($user, $conversation);  
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Conversation $conversation): bool
    {
        return $this->isOwner($user, $conversation); 
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Conversation $conversation): bool
    {
        return $this->isOwner($user, $conversation); 
    }
    /**
     * checks if the its the owner of the convo
     * @param User $user
     * @param Conversation $conversation
     * @return bool
     */
    private function isOwner(User $user, Conversation $conversation): bool
    {
        return $conversation->conversationMembers()
            ->where('user_id', $user->id)
            ->where('role', ConversationRoleEnum::OWNER->value)
            ->whereNull('left_at')
            ->exists();
    }
    /**
     * checks if the user is admin of this convo
     * @param User $user
     * @param Conversation $conversation
     * @return void
     */
    private function isAdmin(User $user, Conversation $conversation): bool
    {
        return $conversation->conversationMembers()
            ->where('user_id', $user->id)
            ->where('role', '!=' ,ConversationRoleEnum::MEMBER->value)
            ->whereNull('left_at')
            ->exists();
    }

}
