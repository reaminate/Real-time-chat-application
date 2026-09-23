<?php

namespace App\Services;

use App\Enums\ConversationRoleEnum;
use App\Enums\ConversationTypeEnum;
use App\Http\Requests\StoreConversationRequest;
use App\Models\Conversation;
use App\Models\User;

class ConversationService
{
    /**
     * stores a conversation
     * @param mixed $validated
     * @param StoreConversationRequest $request
     * @return Conversation
     */
    public function store($validated, StoreConversationRequest $request): Conversation
    {
        $creator = User::findOrFail($request->user()->__get('id'));
        $validated['created_by'] = $creator->__get('id');
        $users = $validated['users'];
        $validated['type'] = (count($users) === 1) ? ConversationTypeEnum::DIRECT->value : ConversationTypeEnum::GROUP->value;
        unset($validated['users']);
        $conversation = Conversation::create($validated);
        foreach($users as $userId){
            $conversation->conversationMembers()->create([
                'user_id' => $userId,
                'role' => $userId === $creator->id
                    ? ConversationRoleEnum::OWNER->value
                    : ConversationRoleEnum::MEMBER->value,
                'joined_at' => now(),
            ]);
        }
        return $conversation;
    }
}
