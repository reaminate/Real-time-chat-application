<?php

namespace App\Http\Controllers;

use App\Enums\ConversationTypeEnum;
use App\Events\UserAdded;
use App\Events\UserDeleted;
use App\Http\Requests\AddUsersRequest;
use App\Http\Requests\DeleteUsersRequest;
use App\Http\Requests\ForceDeleteOrRestoreUserInConversationRequest;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Requests\UpdateConversationRequest;
use App\Http\Requests\UserSearchRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\UserResource;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ConversationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->cannot('viewAny', Conversation::class)) {
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
        if ($request->user()->cannot('create', Conversation::class)) {
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
        if ($request->user()->cannot('view', $conversation)) {
            abort(403);
        }
        if ($request->has('created_by')) {
            $conversation->load('createdBy');
        }
        if ($request->has('members')) {
            $conversation->load('users');
        }
        //load messages regardless
        $conversation->load(['messages' => fn ($q) => $q->latest()->with('user')]);

        $conversation->conversationMembers()->where('user_id', $request->user()->__get('id'))->update([
            'last_read_id' => $conversation->last_message_id,
        ]);

        return ConversationResource::make($conversation);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateConversationRequest $request, Conversation $conversation, ConversationService $service)
    {
        if ($request->user()->cannot('update', $conversation)) {
            abort(403);
        }
        $validated = $request->validated();
        try {
            $service->update($validated, $request, $conversation);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Record not found.'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Database update failed.'], 500);
        }

        return response('', 200);
    }

    public function addUsers(AddUsersRequest $request, Conversation $conversation, ConversationService $service)
    {
        if ($request->user()->cannot('manageUsers', $conversation)) {
            abort(403);
        }
        $service->addUsers($request, $conversation);
        broadcast(new UserAdded($conversation, $request->validated('add_users')))->toOthers();

        return response('', 200);
    }

    public function deleteUsers(DeleteUsersRequest $request, Conversation $conversation, ConversationService $service)
    {
        if ($request->user()->cannot('manageUsers', $conversation)) {
            abort(403);
        }
        try {
            $service->deleteUsers($request, $conversation);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Database update failed.'], 500);
        }
        broadcast(new UserDeleted($conversation->id, $request->validated('delete_users')))->toOthers();

        if (! $conversation->exists) {
            return response()->noContent();
        }

        return response('', 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Conversation $conversation, Request $request)
    {
        if ($request->user()->cannot('delete', $conversation)) {
            abort(403);
        }
        $conversation->delete();

        return response()->noContent();
    }

    /**
     * readds back users that were previously deleted
     *
     * @return JsonResponse
     */
    public function restore(Conversation $conversation, ForceDeleteOrRestoreUserInConversationRequest $request, ConversationService $service)
    {
        if ($request->user()->cannot('restore', $conversation)) {
            abort(403);
        }
        $users = $service->restore($request, $conversation);
        broadcast(new UserAdded($conversation, $users->modelKeys()))->toOthers();

        return response()->json([
            'message' => 'users have been restored',
            'users' => UserResource::collection($users),
        ], 200);
    }

    /**
     * permenantly deletes users from this convo
     *
     * @return Response
     */
    public function forceDelete(Conversation $conversation, ForceDeleteOrRestoreUserInConversationRequest $request)
    {
        if ($request->user()->cannot('forceDelete', $conversation)) {
            abort(403);
        }
        $users = $request->validated('users');
        $conversation->allUsers()->detach($users);

        return response()->noContent();
    }
    
}
