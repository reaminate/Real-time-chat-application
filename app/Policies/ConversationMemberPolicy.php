<?php

namespace App\Policies;

use App\Enums\ConversationRoleEnum;
use App\Models\Conversation;
use App\Models\User;
use App\Models\ConversationMember;
use Illuminate\Auth\Access\Response;

class ConversationMemberPolicy
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
    public function view(User $user, ConversationMember $conversationMember): bool
    {
        if ($conversationMember->user_id === $user->id) {
            return true;
        }

        return $user->conversationMembers()
            ->where('conversation_id', $conversationMember->conversation_id)
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
    public function update(User $user, ConversationMember $conversationMember): bool
    {
        return $this->getAdminOrUser($user, $conversationMember);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ConversationMember $conversationMember): bool
    {
        return $this->getAdminOrUser($user, $conversationMember);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ConversationMember $conversationMember): bool
    {
        return $this->isConversationAdmin($user, $conversationMember);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ConversationMember $conversationMember): bool
    {
        return $this->isConversationAdmin($user, $conversationMember);
    }

    /**
     * Return true if the member row belongs to the user, or the user is an
     * admin/owner of that *same* conversation.
     *
     * @param User $user
     * @param ConversationMember $conversationMember
     * @return bool
     */
    private function getAdminOrUser(User $user, ConversationMember $conversationMember): bool
    {
        if ($conversationMember->user_id === $user->id) {
            return true;
        }

        return $this->isConversationAdmin($user, $conversationMember);
    }

    /**
     * Return true only if the user is an admin/owner of the conversation
     * that this member row belongs to (no self-exception).
     *
     * @param User $user
     * @param ConversationMember $conversationMember
     * @return bool
     */
    private function isConversationAdmin(User $user, ConversationMember $conversationMember): bool
    {
        return $user->conversationMembers()
            ->where('conversation_id', $conversationMember->conversation_id)
            ->where('role', '!=', ConversationRoleEnum::MEMBER)
            ->exists();
    }
}
