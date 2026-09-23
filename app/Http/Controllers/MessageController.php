<?php

namespace App\Http\Controllers;

use App\Enums\AttachmentCollectionEnum;
use App\Enums\MessageTypeEnum;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
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
    public function store(StoreMessageRequest $request)
    {
        if($request->user()->cannot('create', Message::class)){
            abort(403);
        }
        $validated = $request->validated();
        $file = $validated['attachment'];
        unset($validated['attachment']);
        $validated['sender_id'] = $request->user()->__get('id');
        $message = Message::create($validated);
        if($validated['type'] != MessageTypeEnum::TEXT->value){
            $path = $request->file('attachment')->store('attachments', 'local');
            $message->attachments()->create([
                'collection' => AttachmentCollectionEnum::ATTACHMENT->value,
                'original_name' => $file->getClientOriginalName(),
                'file_name' => basename($file),
                'mime_type' => $file->extension(),
                'size' => $file->getSize(),
                'path' => $path,
            ]);
        }
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
    public function update(UpdateMessageRequest $request, Message $message)
    {
        if($request->user()->cannot('update')){
            abort(403);
        }
        $validated = $request->validated();
        if($message->__get('type') == MessageTypeEnum::TEXT){
            unset($validated['attachment']);
            $message->update($validated);
        } else {
            $file = $validated['attachment'];
            unset($validated['body']);
            $path = $request->file('attachment')->store('attachments', 'local');
            $message->attachments()->update([
                'collection' => AttachmentCollectionEnum::ATTACHMENT->value,
                'original_name' => $file->getClientOriginalName(),
                'file_name' => basename($file),
                'mime_type' => $file->extension(),
                'size' => $file->getSize(),
                'path' => $path,
            ]);
        }
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
