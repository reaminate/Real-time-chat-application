<?php

namespace App\Services;

use App\Enums\ConversationRoleEnum;
use App\Enums\ConversationTypeEnum;
use App\Http\Requests\AddUsersRequest;
use App\Http\Requests\DeleteUsersRequest;
use App\Http\Requests\ForceDeleteOrRestoreRequest;
use App\Http\Requests\ForceDeleteOrRestoreUserInConversationRequest;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Requests\UpdateConversationRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

    /**
     * updates a conversation, optionally transferring ownership and/or promoting members to admin
     * @param array $validated
     * @param UpdateConversationRequest $request
     * @param Conversation $conversation
     * @return Conversation
     */
    public function update(array $validated, UpdateConversationRequest $request, Conversation $conversation): Conversation
    {
        if(isset($validated['created_by'])){
            DB::transaction(function() use($request, $validated){
                $request->user()->conversationMembers()->update(['role' => ConversationRoleEnum::MEMBER->value]);
                $creator = User::findOrFail($validated['created_by']);
                $creator->conversationMembers()->update(['role' => ConversationRoleEnum::OWNER->value]);
            });
            unset($validated['created_by']);
        }
        if(isset($validated['make_users_admin'])){
            $users = $validated['make_users_admin'];
            User::findMany($users)->each(function($user){
                $user->conversationMembers()->update(['role' => ConversationRoleEnum::ADMIN->value]);
            });
        }
        $conversation->update($validated);
        return $conversation;
    }

    /**
     * adds users to a conversation, promoting it to a group if it was direct
     * @param AddUsersRequest $request
     * @param Conversation $conversation
     * @return Conversation
     */
    public function addUsers(AddUsersRequest $request, Conversation $conversation): Conversation
    {
        $validated = $request->validated();
        if($conversation->type === ConversationTypeEnum::DIRECT){
            $conversation->update([
                'type' => ConversationTypeEnum::GROUP->value,
                'name' => 'New Group'
            ]);
        }
        foreach($validated['add_users'] as $userId){
            $conversation->conversationMembers()->updateOrCreate(
                ['user_id' => $userId],
                [
                    'role' => ConversationRoleEnum::MEMBER->value,
                    'joined_at' => now(),
                    'left_at' => null,
                ]
            );
        }
        return $conversation;
    }

    /**
     * removes users from a conversation, deleting it or demoting it to direct as appropriate
     * @param DeleteUsersRequest $request
     * @param Conversation $conversation
     * @return Conversation
     */
    public function deleteUsers(DeleteUsersRequest $request, Conversation $conversation): Conversation
    {
        $validated = $request->validated();
        $users = $validated['delete_users'];

        DB::transaction(function() use($conversation, $users, &$validated) {
            $conversation->conversationMembers()
                ->whereIn('user_id', $users)
                ->whereNull('left_at')
                ->update(['left_at' => now()]);

            $remaining = $conversation->users()->lockForUpdate()->count();
            if($remaining <= 1){
                $conversation->delete();
                return;
            }
            if($remaining === 2){
                $validated['type'] = ConversationTypeEnum::DIRECT->value;
                $validated['name'] = null;
            }
        });

        if(!$conversation->exists){
            return $conversation;
        }
        if(isset($validated['type'])){
            $conversation->update([
                'type' => $validated['type'],
                'name' => $validated['name'],
            ]);
        }
        return $conversation;
    }
    /**
     * restores the models and return the restored model names
     * @param ForceDeleteOrRestoreUserInConversationRequest $request
     * @param Conversation $conversation
     * @return array
     */
    public function restore(ForceDeleteOrRestoreUserInConversationRequest $request, Conversation $conversation): array 
    {
        $validated = $request->validated();
        $users = $validated['delete_users'];
        $restored_users = [];
        DB::transaction(function() use($conversation, $users, &$validated){
            $conversation->conversationMembers()
                ->whereIn('user_id', $users)
                ->whereNotNull('left_at')
                ->update(['left_at' => null]);
        });
        $restored_users = User::findMany($users)->only(['name'])->toArray();
        return $restored_users;
    }
    public function forceDelete(): ReturnType {}
    
    
}
