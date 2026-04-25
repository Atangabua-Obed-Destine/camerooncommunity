<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when the recipient accepts a pending connection request.
 * Broadcasts to the original requester so they see "🎉 X accepted!".
 */
class ConnectionAccepted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $accepter,
        public User $requester,
        public ?string $notificationId = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'connection.accepted';
    }

    public function broadcastOn(): array
    {
        $tenantId = $this->requester->tenant_id;
        return [new Channel("tenant.{$tenantId}.user.{$this->requester->id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'notification_id' => $this->notificationId,
            'from' => [
                'id'       => $this->accepter->id,
                'name'     => $this->accepter->name,
                'username' => $this->accepter->username,
                'avatar'   => $this->accepter->avatar,
                'region'   => $this->accepter->current_region ?? null,
            ],
            'sent_at' => now()->toIso8601String(),
        ];
    }
}
