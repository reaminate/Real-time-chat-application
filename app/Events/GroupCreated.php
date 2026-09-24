<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Conversation $conversation)
    {
        //
    }

    /**
     * Broadcast to each member's personal channel
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return $this->conversation->users()->pluck('users.id')
            ->map(fn (int $userId) => new PrivateChannel('user.'.$userId))
            ->all();
    }
}
