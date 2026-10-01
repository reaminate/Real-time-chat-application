<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// for user model deletion
class UserDeletedForever implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Conversation ids captured at construction, because deleting the user
     * nulls their membership rows before the channels are resolved.
     *
     * @var array<int, int>
     */
    public array $conversationIds;

    /**
     * Create a new event instance.
     */
    public function __construct(public User $user)
    {
        $this->conversationIds = $user->conversations()->pluck('conversations.id')->all();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return array_map(fn (int $id) => new PrivateChannel('conversation.'.$id), $this->conversationIds);
    }
}
