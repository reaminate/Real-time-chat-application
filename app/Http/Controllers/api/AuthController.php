<?php

namespace App\Http\Controllers\api;

use App\Enums\AttachmentCollectionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Attachment;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    public function register(RegisterUserRequest $request, AuthService $service)
    {
        $validated = $request->validated();
        $data = $service->register($validated, $request);

        return response([
            'message' => 'login successful',
            'user' => UserResource::make($data['user']),
            'access_token' => $data['token'],
            'token_type' => 'bearer',
        ], 201);
    }
    /**
     * logs in the user
     * @param LoginUserRequest $request
     * @return \Illuminate\Http\Response
     */
    public function login(LoginUserRequest $request, AuthService $service) 
    {
        $validated = $request->validated();
        $data = $service->login($validated, $request);
        return response([
            'message' => 'login successful',
            'user' => UserResource::make($data['user']),
            'access_token' => $data['token'],
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
        $request->user()->currentAccessToken()->delete();
        $request->user()->update(['last_seen_at'=>now()]);
        return response()->json([
            'message' => 'logout successful',
        ]);
    }  

    public function meSelf()
    {
        $user = Auth::user();
        $user->load(['conversations', 'createdConversations']);
        return UserResource::make($user);
    }
}
