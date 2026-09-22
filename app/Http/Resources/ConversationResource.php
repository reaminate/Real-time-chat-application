<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->__get('type'),
            'name' => $this->__get('name')??'direct_conversation',
            'last_message' => MessageResource::make($this->whenLoaded('lastMessage')),
            'created_by' => UserResource::make($this->whenLoaded('createdBy')),
            'members' => UserResource::collection($this->whenLoaded('users')),
            'messages' => MessageResource::collection($this->whenLoaded('messages'))
        ];
    }
}
