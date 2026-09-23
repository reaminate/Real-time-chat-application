<?php

namespace App\Services;

use App\Enums\AttachmentCollectionEnum;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * registering a user
     * @param array $validated
     * @param RegisterUserRequest $request
     * @return array<string|User>
     */
    public function register(array $validated, RegisterUserRequest $request): array
    {
        $key = Str::lower($validated['email']).'|'.$request->ip();
        if(RateLimiter::tooManyAttempts($key, 5)){
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'error' => 'too many register attempts',
                'try_again' => $seconds,
            ]);
        }
        RateLimiter::hit($key);

        $user = User::create($request->safe()->except('avatar'));
        $this->storeAvatarFor($request, $user);
        $user->load('avatar');

        $token = $user->createToken('usertoken')->plainTextToken;
        $data = [];
        $data['token'] = $token;
        $data['user'] = $user;
        return $data;
    }
     /**
     * stores the avatar of the user in attachments
     * @param RegisterUserRequest $request
     * @param User $user
     * @return Attachment|null
     */
    protected function storeAvatarFor(RegisterUserRequest $request, User $user): ?Attachment
    {
        if (! $request->hasFile('avatar')) {
            return null;
        }

        $file = $request->file('avatar');
        $path = $file->store('avatars', 'local');

        return $user->avatar()->create([
            'collection' => AttachmentCollectionEnum::AVATAR,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => basename($path),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'path' => $path,
        ]);
    }
    /**
     * loggin in a user
     * @param array $validated
     * @param LoginUserRequest $request
     * @return array
     */
    public function login(array $validated, LoginUserRequest $request):array{
        $key = Str::lower($validated['email']).'|'.$request->ip();
        if(RateLimiter::tooManyAttempts($key, 5)){
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'error' => 'too many login attempts',
                'try_again' => $seconds,
            ]);
        }
        RateLimiter::hit($key);

        $user = User::where('email', $validated['email'])->firstOrFail();
        if(!Hash::check($validated['password'], $user->password)){
            throw ValidationException::withMessages([
                'error' => 'wrong_password',
            ]);
        }
        $token = $user->createToken('auth_token')->plainTextToken;
        $data = [];
        $data['token'] = $token;
        $data['user'] = $user;
        return $data;
    }
}
