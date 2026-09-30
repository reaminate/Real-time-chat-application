<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserOffline implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public User $user)
    {
        //
    }

    /**
     * Broadcast on every conversation the user is currently a member of.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return $this->user->conversations()
            ->pluck('conversations.id')
            ->map(fn ($id) => new PrivateChannel('conversation.'.$id))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'user.offline';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'name' => $this->user->name,
            'friend_id' => $this->user->friend_id,
            'last_seen_at' => $this->user->last_seen_at?->toIso8601String(),
        ];
    }
}
