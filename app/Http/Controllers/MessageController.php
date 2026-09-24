<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Services\MessageService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if($request->user()->cannot('viewAll', Message::class)){
            abort(403);
        }
        $messages = Message::query()
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
        return response('', 201);
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
        ->when($request->has('replies'), fn($query) => $query->load('replies'))
        ->when($request->has('attachments'), fn($query) => $query->load('attachments'))->first();

        return MessageResource::make($message);
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
        $service->update($validated, $request, $message);
        return response('', 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Message $message, Request $request)
    {
        if($request->user()->cannot('delete', $message)){
            abort(403);
        }
        $message->delete();
        return response()->noContent();
    }

    /**
     * fully destroy the model
     */
    public function forceDelete(Message $message, Request $request)
    {
        if($request->user()->cannot('forceDelete')){
            abort(403);
        }
        $message->forceDelete();
        return response()->noContent();
    }
    /**
     * resotre a message
     */
    public function restore(Message $message, Request $request)
    {
        if($request->user()->cannot('restore')){
            abort(403);
        }
        $message->restore();
        return MessageResource::make($message);
    }
    
    
}
