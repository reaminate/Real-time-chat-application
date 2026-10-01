<?php

namespace App\Services;

use App\Enums\AttachmentCollectionEnum;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserService
{
    /**
     * updates a user, replacing their avatar when one is provided
     * @param array $validated
     * @param UpdateUserRequest $request
     * @param User $user
     */
    public function update(array $validated, UpdateUserRequest $request, User $user): User
    {
        DB::transaction(function() use($validated, $request, $user){
            $user->lockForUpdate();
            if(isset($validated['avatar'])){
                $this->storeAvatarFor($request, $user);
                unset($validated['avatar']);
            }
            if(isset($validated['new_password'])){
                $validated['password'] = $validated['new_password'];
                unset($validated['new_password']);
            }
            $user->update($validated);
        });
        
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
        $oldPath = $user->avatar()->pluck('path')->all();

        $file = $request->file('avatar');
        $path = $file->store('avatars', 'public');
        $user->avatar()->updateOrCreate([], [
            'collection' => AttachmentCollectionEnum::AVATAR,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => basename($path),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'path' => $path,
        ]);
        Storage::disk('public')->delete($oldPath);
    }
}
