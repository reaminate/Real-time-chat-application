<?php

namespace App\Http\Resources;

use App\Enums\ConversationRoleEnum;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'conversation_id' => $this->__get('conversation_id'),
            'conversation_more_information' => $this->when($this->isConversationAdmin($request->user()), 
                ConversationResource::make($this->whenLoaded('conversation'))),
            'sender' => UserResource::make($this->whenLoaded('user')),
            'reply_to' => MessageResource::make($this->whenLoaded('replyTo')),
            'replies' => MessageResource::collection($this->whenLoaded('replies')),
            'type' => $this->__get('type'),
            'body' => $this->__get('body') ?? AttachmentResource::collection($this->whenLoaded('attachments')),
            'edited_at' => $this->when($this->__get('edited_at')!=null, $this->__get('edited_at')),
        ];
    }
    /**
     * Return true only if the user is an admin/owner of the conversation
     * that this member row belongs to (no self-exception).
     *
     * @param User $user
     * @return bool
     */
    private function isConversationAdmin(User $user): bool
    {
        return $user->conversationMembers()
            ->where('conversation_id', $this->__get('conversation_id'))
            ->where('role', '!=', ConversationRoleEnum::MEMBER)
            ->exists();
    }
}
