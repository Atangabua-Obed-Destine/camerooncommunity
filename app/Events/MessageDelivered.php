<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to the SENDER of a message that one of their messages was delivered
 * to a recipient's client (i.e. the recipient's browser received the broadcast).
 *
 * Channel: tenant.{tid}.user.{senderId}.receipts
 * Event:   MessageDelivered
 */
class MessageDelivered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $tenantId,
        public int $senderId,
        public int $messageId,
        public int $roomId,
        public int $byUserId,
        public string $deliveredAt,
        public bool $allDelivered
    ) {}

    public function broadcastAs(): string
    {
        return 'MessageDelivered';
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('tenant.' . $this->tenantId . '.user.' . $this->senderId . '.receipts'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'room_id' => $this->roomId,
            'by_user_id' => $this->byUserId,
            'delivered_at' => $this->deliveredAt,
            'all_delivered' => $this->allDelivered,
        ];
    }
}
