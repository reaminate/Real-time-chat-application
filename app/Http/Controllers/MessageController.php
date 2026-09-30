<?php

namespace App\Http\Controllers;

use App\Enums\MessageTypeEnum;
use App\Events\MessageDeleted;
use App\Events\MessageDeletedForever;
use App\Events\MessageRead;
use App\Events\MessageRestored;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Services\MessageService;
use Illuminate\Broadcasting\PendingBroadcast;
use Illuminate\Http\Request;
use Illuminate\Broadcasting\BroadcastEvent;
class MessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if($request->user()->cannot('viewAny', Message::class)){
            abort(403);
        }
        $user = $request->user();
        $messages = Message::query()
        ->where('sender_id', $user->__get('id'))
        ->when($request->has('attachments'), function($query){
            $query->with('attachments');
        })->with('replies')->cursorPaginate(10);

        return MessageResource::collection($messages);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMessageRequest $request, MessageService $service)
    {
        if($request->user()->cannot('create', Message::class)){
            abort(403);
        }
        $validated = $request->validated();

        $message = $service->store($validated, $request);
        broadcast(new MessageSent($message))->toOthers();
        return response()->json(MessageResource::make($message), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Message $message, Request $request)
    {
        if($request->user()->cannot('view', $message)){
            abort(403);
        }
        $message->load(['user.avatar', 'attachments'])
        ->when($request->has('conversation_information'), fn($query) => $query->load('conversation'))
        ->when($request->has('reply_to'), fn($query) => $query->load('replyTo'))
        ->when($request->has('replies'), fn($query) => $query->load('replies'));
        if($message->__get('type') != MessageTypeEnum::TEXT->value){
            $message->load('attachments');
        }
        $user = $request->user();
        $user->conversationMembers()->where('conversation_id', $message->conversation_id)
        ->update([
            'last_read_id' => $message->__get('id'),
        ]);
        broadcast(new MessageRead($message->conversation_id, $user, $message->id))->toOthers();
        return response()->json(MessageResource::make($message), 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMessageRequest $request, Message $message, MessageService $service)
    {
        if($request->user()->cannot('update', $message)){
            abort(403);
        }
        $validated = $request->validated();
        $message = $service->update($validated, $request, $message);
        broadcast(new MessageUpdated($message))->toOthers();
        return response()->json(MessageResource::make($message), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Message $message, Request $request)
    {
        if($request->user()->cannot('delete', $message)){
            abort(403);
        }
        $message_id = $message->__get('id');
        $message->delete();
        if($message->trashed()){
            broadcast(new MessageDeleted(Message::withTrashed()->find($message_id)))->toOthers();
        }
        return response()->noContent();
    }

    /**
     * fully destroy the model
     */
    public function forceDelete(Message $message, Request $request)
    {
        if($request->user()->cannot('forceDelete', $message)){
            abort(403);
        }
        $message->forceDelete();
        broadcast(new MessageDeletedForever($message))->toOthers();
        return response()->noContent();
    }
    /**
     * resotre a message
     */
    public function restore(Message $message, Request $request)
    {
        if($request->user()->cannot('restore', $message)){
            abort(403);
        }
        $message->restore();
        broadcast(new MessageRestored($message));
        return response()->json(MessageResource::make($message), 201);
    }
    
    
}
