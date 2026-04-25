<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a user sends a connection request to another user.
 * Broadcasts to the recipient's per-user channel so their UI lights up
 * in real time (toast + bell + optional browser notification + chime).
 */
class ConnectionRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $from,
        public User $to,
        public ?string $notificationId = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'connection.requested';
    }

    public function broadcastOn(): array
    {
        $tenantId = $this->to->tenant_id;
        return [new Channel("tenant.{$tenantId}.user.{$this->to->id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'notification_id' => $this->notificationId,
            'from' => [
                'id'       => $this->from->id,
                'name'     => $this->from->name,
                'username' => $this->from->username,
                'avatar'   => $this->from->avatar,
                'region'   => $this->from->current_region ?? null,
            ],
            'sent_at' => now()->toIso8601String(),
        ];
    }
}
