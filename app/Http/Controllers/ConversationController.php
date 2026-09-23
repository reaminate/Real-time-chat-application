<?php

namespace App\Http\Controllers;

use App\Enums\ConversationRoleEnum;
use App\Enums\ConversationTypeEnum;
use App\Http\Requests\AddUsersRequest;
use App\Http\Requests\DeleteUsersRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Requests\UpdateConversationRequest;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if($user->cannot('viewAny', Conversation::class)){
            abort(403);
        }
        $conversations = $user->conversations()->with('lastMessage')->get();
        return ConversationResource::collection($conversations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreConversationRequest $request, ConversationService $service)
    {
        if($request->user()->cannot('create', Conversation::class)){
            abort(403);
        }
        $validated = $request->validated();
        $service->store($validated, $request);
        return response('', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Conversation $conversation)
    {
        if($request->user()->cannot('view', $conversation)){
            abort(403);
        }
        if ($request->has('created_by')) $conversation->load('createdBy');
        if ($request->has('members'))    $conversation->load('users');
        if ($request->has('messages'))   $conversation->load('messages')->cursorPaginate(20);

        return ConversationResource::make($conversation);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateConversationRequest $request, Conversation $conversation)
    {
        if($request->user()->cannot('update', $conversation)){
            abort(403);
        }
        $validated = $request->validated();
        if(isset($validated['created_by'])){
            DB::transaction(function() use($request, $validated){
                try{
                    $request->user()->conversationMembers()->update(['role' => ConversationRoleEnum::MEMBER->value]);
                    $creator = User::findOrFail($validated['created_by']);
                    $creator->conversationMembers()->update(['role' => ConversationRoleEnum::OWNER->value]);
                }catch(ModelNotFoundException $e){
                    return response()->json(['error' => 'Record not found.'], 404);
                }catch(QueryException $e){
                    return response()->json(['error' => 'Database update failed.'], 500);
                }
            });  
            unset($validated['created_by']);
        }
        if(isset($validated['make_users_admin'])){
            $users = $validated['make_users_admin'];
            User::findMany($users)->each(function($query){
                $query->conversationMembers()->update(['role' => ConversationRoleEnum::ADMIN->value]);
            });
        }
        $conversation->update($validated);
        return response('', 200);
    }

    public function addUsers(AddUsersRequest $request, Conversation $conversation)
    {
        if($request->user()->cannot('manageUsers', $conversation)){
            abort(403);
        }
        $validated = $request->validated();
        if($conversation->type === ConversationTypeEnum::DIRECT){
            $conversation->update([
                'type' => ConversationTypeEnum::GROUP->value,
                'name' => 'New Group'
            ]);
        }
        foreach($validated['add_users'] as $userId){
            $conversation->conversationMembers()->updateOrCreate(
                ['user_id' => $userId],
                [
                    'role' => ConversationRoleEnum::MEMBER->value,
                    'joined_at' => now(),
                    'left_at' => null,
                ]
            );
        }
        return response('', 200);
    }
    public function deleteUsers(DeleteUsersRequest $request, Conversation $conversation)
    {
        if($request->user()->cannot('manageUsers', $conversation)){
            abort(403);
        }
        $validated = $request->validated();
        $users = $validated['delete_users'];
        try{
            DB::transaction(function() use($conversation, $users, &$validated) {
                $conversation->conversationMembers()
                    ->whereIn('user_id', $users)
                    ->whereNull('left_at')
                    ->update(['left_at' => now()]);

                $remaining = $conversation->users()->lockForUpdate()->count();
                if($remaining <= 1){
                    $conversation->delete();
                    return;
                }
                if($remaining === 2){
                    $validated['type'] = ConversationTypeEnum::DIRECT->value;
                    $validated['name'] = null;
                }
            });
        }catch(QueryException $e){
            return response()->json(['error' => 'Database update failed.'], 500);
        }

        if(!$conversation->exists){
            return response()->noContent();
        }
        if(isset($validated['type'])){
            $conversation->update([
                'type' => $validated['type'],
                'name' => $validated['name'],
            ]);
        }
        return response('', 200);
    }
    
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Conversation $conversation, Request $request)
    {
        if($request->user()->cannot('delete', $conversation)){
            abort(403);
        }
        $conversation->delete();
        return response()->noContent();
    }
}
