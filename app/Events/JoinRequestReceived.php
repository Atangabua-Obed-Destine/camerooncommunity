<?php

namespace App\Events;

use App\Models\YardJoinRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JoinRequestReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public YardJoinRequest $joinRequest)
    {
    }

    public function broadcastAs(): string
    {
        return 'JoinRequestReceived';
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('tenant.' . $this->joinRequest->room->tenant_id . '.room.' . $this->joinRequest->room_id),
        ];
    }

    public function broadcastWith(): array
    {
        $user = $this->joinRequest->user;

        return [
            'id' => $this->joinRequest->id,
            'room_id' => $this->joinRequest->room_id,
            'user_id' => $user->id,
            'user_name' => $user->username ?? $user->name,
            'user_avatar' => $user->avatar,
            'status' => $this->joinRequest->status,
            'created_at' => $this->joinRequest->created_at->toIso8601String(),
        ];
    }
}
