<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  array<int, int>  $userIds
     */
    public function __construct(public Conversation $conversation, public array $userIds)
    {
        //
    }

    /**
     * Broadcast to the conversation's existing members and to each added
     * user's personal channel so they learn about the conversation.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->conversation->id),
            ...array_map(fn (int $userId) => new PrivateChannel('user.'.$userId), $this->userIds),
        ];
    }
}
