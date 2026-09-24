<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Holds the conversation id rather than the model, because removing
     * users may delete the conversation before the queued broadcast runs.
     *
     * @param  array<int, int>  $userIds
     */
    public function __construct(public int $conversationId, public array $userIds)
    {
        //
    }

    /**
     * Broadcast to the remaining members and to each removed user's
     * personal channel.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->conversationId),
            ...array_map(fn (int $userId) => new PrivateChannel('user.'.$userId), $this->userIds),
        ];
    }
}
