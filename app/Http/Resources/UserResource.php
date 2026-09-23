<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->__get('name'),
            'email' => $this->__get('email'),
            'friend_id' => $this->__get('friend_id'),
            'last_seen_at' => $this->when($this->resource->tokens()->doesntExist(), $this->__get('last_seen_at')),
            'conversations' => ConversationResource::collection($this->whenLoaded('conversations')),
            'created_conversations' => ConversationResource::collection($this->whenLoaded('createdConversations')),
        ];
    }
}
