<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use App\Models\Attachment;
use Illuminate\Auth\Access\Response;

class AttachmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Conversation $conversation): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Attachment $attachment): bool
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
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Attachment $attachment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Attachment $attachment): bool
    {
        return false;
    }
}
