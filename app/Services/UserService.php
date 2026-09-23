<?php

namespace App\Services;

use App\Enums\AttachmentCollectionEnum;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;

class UserService
{
    /**
     * updates a user, replacing their avatar when one is provided
     * @param array $validated
     * @param UpdateUserRequest $request
     * @param User $user
     * @return User
     */
    public function update(array $validated, UpdateUserRequest $request, User $user): User
    {
        if(isset($validated['avatar'])){
            $this->storeAvatarFor($request, $user);
            unset($validated['avatar']);
        }
        $user->update($validated);
        return $user;
    }

    /**
     * stores the avatar of the user in attachments
     * @param UpdateUserRequest $request
     * @param User $user
     * @return void
     */
    protected function storeAvatarFor(UpdateUserRequest $request, User $user): void
    {
        $file = $request->file('avatar');
        $path = $file->store('avatars', 'local');

        $user->avatar()->update([
            'collection' => AttachmentCollectionEnum::AVATAR,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => basename($path),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'path' => $path,
        ]);
    }
}
