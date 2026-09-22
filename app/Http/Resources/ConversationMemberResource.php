<?php

namespace App\Http\Resources;

use App\Enums\ConversationRoleEnum;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->__get('user_id'),
            'user_information' => UserResource::make($this->whenLoaded('user')),
            'conversation_id' => $this->__get('conversation_id'),
            'conversation_information' => ConversationResource::make($this->whenLoaded('conversation')),
            'last_read_id' => $this->__get('last_read_id'),
            'last_read' => MessageResource::make($this->whenLoaded('lastRead')),
            'role' => $this->__get('role'),
            'joined_at' => $this->__get('joined_at'),
            'left_at' => $this->when(
                $request->user() && $this->isConversationAdmin($request->user()),
                $this->__get('left_at')
            ),
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
