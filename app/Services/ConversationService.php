<?php

namespace App\Services;

use App\Enums\ConversationRoleEnum;
use App\Enums\ConversationTypeEnum;
use App\Events\GroupCreated;
use App\Http\Requests\AddUsersRequest;
use App\Http\Requests\DeleteUsersRequest;
use App\Http\Requests\ForceDeleteOrRestoreUserInConversationRequest;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Requests\UpdateConversationRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    /**
     * stores a conversation
     * if a direct convo exists between these two and type is direct, it will not allow
     *
     * @param  mixed  $validated  users always includes the creator (see StoreConversationRequest)
     *
     * @throws ValidationException
     */
    public function store($validated, StoreConversationRequest $request): Conversation
    {
        $creator = $request->user();
        $validated['created_by'] = $creator->id;
        $users = $validated['users'];
        $validated['type'] = (count($users) === 2) ? ConversationTypeEnum::DIRECT->value : ConversationTypeEnum::GROUP->value;
        if ($validated['type'] === ConversationTypeEnum::DIRECT->value) {
            $otherUserId = collect($users)->first(fn (int $id) => $id !== $creator->id);
            if ($this->checkIfTwoInDirect($creator, $otherUserId)) {
                throw ValidationException::withMessages([
                    'users' => 'A direct conversation with this user already exists.',
                ]);
            }
            $validated['name'] = null;
        }
        unset($validated['users']);
        $conversation = DB::transaction(function () use ($validated, $users, $creator) {
            $conversation = Conversation::create($validated);
            $conversation->users()->attach(
                collect($users)->mapWithKeys(fn (int $id) => [$id => [
                    'role' => $id === $creator->id
                        ? ConversationRoleEnum::OWNER->value
                        : ConversationRoleEnum::MEMBER->value,
                    'joined_at' => now(),
                ]])->all()
            );
            return $conversation;
        });

        broadcast(new GroupCreated($conversation));

        return $conversation;
    }

    /**
     * helper function to check if two users have an existing direct convo
     */
    private function checkIfTwoInDirect(User $currentUser, int $otherUserId): bool
    {
        return $currentUser->conversations()
            ->where('type', ConversationTypeEnum::DIRECT->value)
            ->whereHas('users', function ($query) use ($otherUserId) {
                $query->where('users.id', $otherUserId);
            })
            ->exists();
    }
    /**
     * updates a conversation, optionally transferring ownership and/or promoting members to admin
     */
    public function update(array $validated, UpdateConversationRequest $request, Conversation $conversation): Conversation
    {
        if (isset($validated['created_by'])) {
            DB::transaction(function () use ($request, $validated, $conversation) {
                $request->user()->conversationMembers()->where('conversation_id', $conversation->__get('id'))->update(['role' => ConversationRoleEnum::MEMBER->value]);
                $creator = User::findOrFail($validated['created_by']);
                $creator->conversationMembers()->update(['role' => ConversationRoleEnum::OWNER->value]);
            });
            unset($validated['created_by']);
        }
        if (isset($validated['make_users_admin'])) {
            $users = $validated['make_users_admin'];
            User::where('conversation_id', $conversation->__get('id'))->findMany($users)->each(function ($user) {
                $user->conversationMembers()->update(['role' => ConversationRoleEnum::ADMIN->value]);
            });
        }
        $conversation->update($validated);

        return $conversation;
    }

    /**
     * adds users to a conversation, promoting it to a group if it was direct
     */
    public function addUsers(AddUsersRequest $request, Conversation $conversation): Conversation
    {
        $validated = $request->validated();
        if ($conversation->type === ConversationTypeEnum::DIRECT) {
            $conversation->update([
                'type' => ConversationTypeEnum::GROUP->value,
                'name' => 'New Group',
            ]);
        }
        foreach ($validated['add_users'] as $userId) {
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
     */
    public function deleteUsers(DeleteUsersRequest $request, Conversation $conversation): Conversation
    {
        $validated = $request->validated();
        $users = $validated['delete_users'];

        DB::transaction(function () use ($conversation, $users, &$validated) {
            $conversation->conversationMembers()
                ->whereIn('user_id', $users)
                ->whereNull('left_at')
                ->update(['left_at' => now()]);

            $remaining = $conversation->users()->lockForUpdate()->count();
            if ($remaining <= 1) {
                $conversation->delete();

                return;
            }
            if ($remaining === 2) {
                $validated['type'] = ConversationTypeEnum::DIRECT->value;
                $validated['name'] = null;
            }
        });

        if (! $conversation->exists) {
            return $conversation;
        }
        if (isset($validated['type'])) {
            $conversation->update([
                'type' => $validated['type'],
                'name' => $validated['name'],
            ]);
        }

        return $conversation;
    }

    /**
     * restores the users to the conversation and returns them
     *
     * @return Collection<int, User>
     */
    public function restore(ForceDeleteOrRestoreUserInConversationRequest $request, Conversation $conversation): Collection
    {
        $users = $request->validated('users');

        $conversation->conversationMembers()
            ->whereIn('user_id', $users)
            ->whereNotNull('left_at')
            ->update(['left_at' => null]);

        return User::whereIn('id', $users)->get();
    }
}
