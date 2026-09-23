<?php

namespace App\Http\Controllers;

use App\Enums\AttachmentCollectionEnum;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if($request->user()->cannot('viewAny', User::class)){
            abort(403);
        }
        $users = User::withoutRelations()->orderBy('name', 'desc')->cursorPaginate(20);
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
        if($request->user()->cannot('view', $user)){
            abort(403);
        }

        $authId = $request->user()->__get('id');

        $user->load(['conversations' => function($query) use($authId){
            $query->whereHas('users', fn($query) => $query->where('users.id', $authId))->cursorPaginate(20);
        }]);

        return UserResource::make($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        if($request->user()->cannot('update', $user)){
            abort(403);
        }
        $validated = $request->validated();
        if(isset($validated['avatar'])){
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
            unset($validated['avatar']);
        }
        $user->update($validated);
        return response('', 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user, Request $request)
    {
        if($request->user()->cannot('delete', $user)){
            abort(403);
        }
        $user->delete();
        return response()->noContent();
    }
}
