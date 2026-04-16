<?php

namespace App\Events;

use App\Models\YardMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public YardMessage $message)
    {
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
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
            'uuid' => $this->message->uuid,
            'room_id' => $this->message->room_id,
            'user_id' => $this->message->user_id,
            'user_name' => $this->message->user?->name,
            'user_avatar' => $this->message->user?->avatar,
            'message_type' => $this->message->message_type->value,
            'content' => $this->message->content,
            'parent_message_id' => $this->message->parent_message_id,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
