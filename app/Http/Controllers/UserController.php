<?php

namespace App\Http\Controllers;

use App\Events\UserDeletedForever;
use App\Events\UserUpdatedInfo;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UserSearchRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(UserSearchRequest $request)
    {
        if ($request->user()->cannot('viewAny', User::class)) {
            abort(403);
        }
        $string_of_letters = $request->validated('search');
        $string_of_letters = "%$string_of_letters%";
        $users = User::whereLike('name', $string_of_letters)->whereNot('name', $request->user()->__get('name'))->orderByDesc('name')->with('avatar')->cursorPaginate(20);

        return UserResource::collection($users);
    }

    /**
     * Store a newly created resource in storage.
     * intentionally left blank, not needed
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, User $user)
    {
        if ($request->user()->cannot('view', $user)) {
            abort(403);
        }

        $authId = $request->user()->__get('id');

        $user->load(['conversations' => function ($query) use ($authId) {
            $query->whereHas('users', fn ($query) => $query->where('users.id', $authId));
        }])->cursorPaginate(20);

        return response()->json(UserResource::make($user), 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user, UserService $service)
    {
        //policy check in the request form
        $validated = $request->validated();
        $user = $service->update($validated, $request, $user)->load('avatar');
        broadcast(new UserUpdatedInfo($user));

        return response()->json(UserResource::make($user), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user, Request $request)
    {
        if ($request->user()->cannot('delete', $user)) {
            abort(403);
        }
        $event = new UserDeletedForever($user);
        $user->delete();
        broadcast($event)->toOthers();

        return response()->noContent();
    }

    /**
     * returns the currently active users
     *
     * @return JsonResponse
     */
    public function currentlyActive(Request $request)
    {
        $user = $request->user();
        $users = Cache::remember('active_users', 60, function () {
            return User::where('last_seen_at', null)->get();
        });

        return response()->json([
            'message' => 'currently_active_users',
            'users' => $users,
        ], 200);
    }
}
