<?php

namespace App\Events;

use App\Models\YardMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public YardMessage $message)
    {
    }

    public function broadcastAs(): string
    {
        return 'MessageDeleted';
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('tenant.' . $this->message->tenant_id . '.room.' . $this->message->room_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'room_id' => $this->message->room_id,
        ];
    }
}
