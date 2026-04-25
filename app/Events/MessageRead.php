<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to the SENDER of one or more messages that they were read.
 *
 * Channel: tenant.{tid}.user.{senderId}.receipts
 * Event:   MessageRead
 *
 * Payload includes a list of message IDs (batched per markAsRead call).
 */
class MessageRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int>  $messageIds
     */
    public function __construct(
        public int $tenantId,
        public int $senderId,
        public int $roomId,
        public int $byUserId,
        public array $messageIds,
        public string $readAt,
        public array $allReadMap = [] // [messageId => bool] — true if all recipients have now read
    ) {}

    public function broadcastAs(): string
    {
        return 'MessageRead';
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
            'room_id' => $this->roomId,
            'by_user_id' => $this->byUserId,
            'message_ids' => $this->messageIds,
            'read_at' => $this->readAt,
            'all_read' => $this->allReadMap,
        ];
    }
}
