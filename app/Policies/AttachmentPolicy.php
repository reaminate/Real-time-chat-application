<?php

namespace App\Policies;

use App\Enums\ConversationRoleEnum;
use App\Models\Message;
use App\Models\User;
use App\Models\Attachment;
use Illuminate\Auth\Access\Response;

class AttachmentPolicy
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
    public function view(User $user): bool
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
    public function update(User $user, Attachment $attachment): bool
    {
        return $this->ownedBy($user, $attachment);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        return $this->ownedBy($user, $attachment);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Attachment $attachment): bool
    {
        return $this->isConversationAdmin($user, $attachment);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Attachment $attachment): bool
    {
        return $this->isConversationAdmin($user, $attachment);
    }
    /**
     * returns true when the user is the one who made the attachment
     * @param User $user
     * @param Attachment $attachment
     * @return bool
     */
    private function ownedBy(User $user, Attachment $attachment): bool
    {
        return match (true) {
            $attachment->attachable instanceof User => $attachment->attachable_id === $user->id,
            $attachment->attachable instanceof Message => $attachment->attachable->sender_id === $user->id,
            default => false,
        };
    }

    /**
     * Returns true only when the attachment belongs to a message and the
     * user is an admin/owner of that message's conversation.
     * @param User $user
     * @param Attachment $attachment
     * @return bool
     */
    private function isConversationAdmin(User $user, Attachment $attachment): bool
    {
        if (! $attachment->attachable instanceof Message) {
            return false;
        }

        return $user->conversationMembers()
            ->where('conversation_id', $attachment->attachable->conversation_id)
            ->where('role', '!=', ConversationRoleEnum::MEMBER)
            ->exists();
    }
}
