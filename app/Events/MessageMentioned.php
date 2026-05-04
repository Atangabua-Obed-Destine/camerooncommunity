<?php

namespace App\Events;

use App\Models\User;
use App\Models\YardMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once per mentioned user when a message contains @mentions.
 * Broadcasts on the mentioned user's personal tenant channel so they
 * see a "you were mentioned" toast even if they have the room muted.
 */
class MessageMentioned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public YardMessage $message,
        public User $mentionedUser,
        public User $sender,
    ) {}

    public function broadcastAs(): string
    {
        return 'message.mentioned';
    }

    public function broadcastOn(): array
    {
        $tenantId = $this->message->tenant_id;
        return [new Channel("tenant.{$tenantId}.user.{$this->mentionedUser->id}")];
    }

    public function broadcastWith(): array
    {
        $room = $this->message->room;

        return [
            'message_id' => $this->message->id,
            'room_id'    => $this->message->room_id,
            'room_name'  => $room?->name ?? '',
            'preview'    => mb_substr((string) $this->message->content, 0, 140),
            'from'       => [
                'id'       => $this->sender->id,
                'name'     => $this->sender->name,
                'username' => $this->sender->username,
                'avatar'   => $this->sender->avatar,
            ],
            'sent_at'    => now()->toIso8601String(),
        ];
    }
}
