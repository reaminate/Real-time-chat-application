<?php

namespace App\Services;

use App\Enums\AttachmentCollectionEnum;
use App\Enums\MessageTypeEnum;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Models\Attachment;
use App\Models\Message;

class MessageService
{
    /**
     * stores a message, attaching a file when the message type isn't text
     * @param array $validated
     * @param StoreMessageRequest $request
     * @return Message
     */
    public function store(array $validated, StoreMessageRequest $request): Message
    {
        unset($validated['attachment']);
        $validated['sender_id'] = $request->user()->__get('id');
        $message = Message::create($validated);
        $message->conversation()->update(['last_message_id'=> $message->__get('id')]);
        if($validated['type'] != MessageTypeEnum::TEXT->value){
            $this->storeAttachmentFor($request, $message);
        }
        return $message;
    }

    /**
     * updates a message's body, or replaces its attachment when the message isn't text
     * @param array $validated
     * @param UpdateMessageRequest $request
     * @param Message $message
     * @return Message
     */
    public function update(array $validated, UpdateMessageRequest $request, Message $message): Message
    {
        if($message->__get('type') == MessageTypeEnum::TEXT){
            unset($validated['attachment']);
            $message->update($validated);
        } else {
            unset($validated['body']);
            $this->storeAttachmentFor($request, $message, update: true);
        }
        return $message;
    }

    /**
     * stores the attachment file on the message in attachments
     * @param StoreMessageRequest|UpdateMessageRequest $request
     * @param Message $message
     * @param bool $update
     * @return Attachment|null
     */
    protected function storeAttachmentFor(StoreMessageRequest|UpdateMessageRequest $request, Message $message, bool $update = false): ?Attachment
    {
        if(!$request->hasFile('attachment')){
            return null;
        }

        $file = $request->file('attachment');
        $path = $file->store('attachments', 'local');
        $data = [
            'collection' => AttachmentCollectionEnum::ATTACHMENT->value,
            'original_name' => $file->getClientOriginalName(),
            'file_name' => basename($file),
            'mime_type' => $file->extension(),
            'size' => $file->getSize(),
            'path' => $path,
        ];

        if($update){
            $message->attachments()->update($data);
            return $message->attachments()->first();
        }
        return $message->attachments()->create($data);
    }
}
