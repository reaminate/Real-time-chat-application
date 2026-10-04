<?php

namespace App\Services;

use App\Enums\AttachmentCollectionEnum;
use App\Enums\MessageTypeEnum;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\User;
use App\Notifications\UserRepliedToMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MessageService
{
    /**
     * stores a message, attaching a file when the message type isn't text
     * alos notifies the person if someone replied to their message
     * also updates the conversations last message
     */
    public function store(array $validated, StoreMessageRequest $request): Message
    {
        // checking if user is a member of that convo
        if (! ConversationMember::where('conversation_id', $validated['conversation_id'])
            ->where('user_id', $request->user()->__get('id'))
            ->exists()) {
            abort(403);
        }
        unset($validated['attachment']);

        $validated['sender_id'] = $request->user()->__get('id');
        $lock = Cache::lock('sending_message'.$validated['sender_id'], 5);
        throw_if(! $lock->get(), ValidationException::class);
        $message = Message::create($validated);
        if (! $message->exists) {
            abort(500);
        }
        // update last message with this in that conversation after storage is successful
        Conversation::where('id', $validated['conversation_id'])
            ->update(['last_message_id' => $message->__get('id')]);
        // notify the person that sent that message if the current user replies to it
        if (isset($validated['reply_to'])) {
            $original = $message->replyTo()->with('user')->first();
            $recipient = $original?->user;
            if ($recipient && $recipient->__get('id') !== $request->user()->__get('id')) {
                $recipient->notify(new UserRepliedToMessage($message, $request->user()));
            }
        }
        $message->conversation()->update(['last_message_id' => $message->__get('id')]);
        if ($validated['type'] != MessageTypeEnum::TEXT->value) {
            $this->storeAttachmentFor($request, $message);
            $message->load('attachments');
        }
        $lock->release();

        return $message;
    }

    /**
     * updates a message's body, or replaces its attachment when the message isn't text
     */
    public function update(array $validated, UpdateMessageRequest $request, Message $message): Message
    {
        if ($message->__get('type') == MessageTypeEnum::TEXT) {
            unset($validated['attachment']);
            $message->update($validated);
        } else {
            unset($validated['body']);
            $this->storeAttachmentFor($request, $message, update: true);
            $message->load('attachments');
        }

        return $message;
    }

    public function show(Message $message, Request $request, User $user): Message
    {
        $relations = ['user', 'user.avatar', 'attachments'];
        if ($request->has('conversation_information')) {
            $relations[] = 'conversation';
        }
        if ($request->has('reply_to')) {
            $relations[] = 'replyTo';
        }
        if ($request->has('replies')) {
            $relations[] = 'replies';
        }
        $message->load($relations);

        $user->conversationMembers()->where('conversation_id', $message->conversation_id)
            ->update([
                'last_read_id' => $message->__get('id'),
            ]);

        return $message;
    }

    /**
     * stores the attachment file on the message in attachments or updates it
     */
    protected function storeAttachmentFor(StoreMessageRequest|UpdateMessageRequest $request, Message $message, bool $update = false): ?Attachment
    {
        // an incase
        if (! $request->hasFile('attachment')) {
            return null;
        }

        $file = $request->file('attachment');
        $path = $file->store('attachments', 'local');
        $data = [
            'collection' => AttachmentCollectionEnum::ATTACHMENT->value,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => basename($path),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'path' => $path,
        ];

        if ($update) {
            $oldPaths = $message->attachments()->pluck('path')->all();
            $message->attachments()->delete();
            Storage::disk('local')->delete($oldPaths);

            return $message->attachments()->create($data);
        }

        return $message->attachments()->create($data);
    }
}
