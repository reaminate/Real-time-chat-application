<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Requests\UpdateConversationRequest;
use App\Models\User;
use Illuminate\Http\Request;

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
    public function store(StoreConversationRequest $request)
    {
        if($request->user()->cannot('create', Conversation::class)){
            abort(403);
        }
        $validated = $request->validated();
        $validated['created_by'] = $request->user()->__get('id');
        Conversation::create($validated);
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
        if ($request->has('messages'))   $conversation->load('messages');

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
        $conversation->update($validated);
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
