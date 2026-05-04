<?php

namespace App\Events;

use App\Models\YardRoom;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a room's metadata changes — membership additions/removals,
 * avatar, name, about text, or admin moderation actions. Receivers
 * refresh the room header, member list, and room list entry without
 * needing a manual reload.
 *
 * Broadcasts on the room's tenant channel; everyone currently subscribed
 * (i.e. anyone with the chat open or with that room in their list) will
 * receive it.
 */
class RoomUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  YardRoom  $room
     * @param  string    $kind  member_added | member_removed | avatar_changed | name_changed | left | request_approved | request_rejected
     * @param  array     $payload  Optional small extra data (e.g. ['user_id' => 7]).
     */
    public function __construct(
        public YardRoom $room,
        public string $kind,
        public array $payload = [],
    ) {}

    public function broadcastAs(): string
    {
        return 'room.updated';
    }

    public function broadcastOn(): array
    {
        $tenantId = $this->room->tenant_id;
        return [new Channel("tenant.{$tenantId}.room.{$this->room->id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->room->id,
            'kind'    => $this->kind,
            'payload' => $this->payload,
            'sent_at' => now()->toIso8601String(),
        ];
    }
}
