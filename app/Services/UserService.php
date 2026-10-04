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
        return DB::transaction(function() use($validated, $request, $user){
            $user = User::whereKey($user->__get('id'))->lockForUpdate()->firstOrFail();
            if(isset($validated['avatar'])){
                $this->storeAvatarFor($request, $user);
                unset($validated['avatar']);
            }
            unset($validated['password']);
            if(isset($validated['new_password'])){
                $validated['password'] = $validated['new_password'];
                unset($validated['new_password']);
            }
            $user->update($validated);

            return $user;
        });
    }

    /**
     * stores the avatar of the user in attachments
     * @param UpdateUserRequest $request
     * @param User $user
     * @return void
     */
    protected function storeAvatarFor(UpdateUserRequest $request, User $user): void
    {
        $oldPaths = $user->avatar()->pluck('path')->all();
        $file = $request->file('avatar');
        $path = $file->store('avatars', 'public');

        try {
            $user->avatar()->updateOrCreate([], [
                'collection' => AttachmentCollectionEnum::AVATAR,
                'original_name' => $file->getClientOriginalName(),
                'file_name' => basename($path),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'path' => $path,
            ]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        DB::afterCommit(fn () => Storage::disk('public')->delete($oldPaths));
    }
}
