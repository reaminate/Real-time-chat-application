<?php

namespace App\Http\Controllers\api;

use App\Enums\AttachmentCollectionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Response;

class AuthController extends Controller
{
    /**
     * register the user and store in db
     * @param RegisterUserRequest $request
     * @return \Illuminate\Http\Response
     */
    public function register(RegisterUserRequest $request)
    {

        $user = User::create($request->safe()->except('avatar'));
        $this->storeAvatarFor($request, $user);
        $user->load('avatar');

        $token = $user->createToken('usertoken')->plainTextToken;

        return response([
            'message' => 'login successful',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'bearer',
        ], 201);
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
        $path = $file->store('avatars', 'public');

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
     * logs in the user
     * @param LoginUserRequest $request
     * @return \Illuminate\Http\Response
     */
    public function login(LoginUserRequest $request) 
    {
        $validated = $request->validated();
        $key = Str::lower($validated['email']).'|'.$request->ip();
        if(RateLimiter::tooManyAttempts($key, 5)){
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'error' => 'too many login attempts',
                'try_again' => $seconds,
            ]);
        }
        $user = User::findOrFail('email', $validated['email']);
        if(!Hash::check($validated['password'], $user->password)){
            throw ValidationException::withMessages([
                'error' => 'wrong_password',
            ]);
        }
        $token = $user->createToken('auth_token')->plainTextToken;

        return response([
            'message' => 'login successful',
            'user' => $user,
            'access_token' => $token,
            'type' => 'bearer',
        ], 200);
    }
    /**
     * logs out the user
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete;
        $request->user()->update(['last_seen_at'=>now()]);
        return response()->json([
            'message' => 'logout successful',
        ]);
    }  
}
