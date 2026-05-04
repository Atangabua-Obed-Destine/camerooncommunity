<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a connection state changes between two users (declined,
 * cancelled, disconnected, blocked, unblocked). Broadcasts to the
 * "other" user's per-user channel so their UI can refresh badges,
 * banners, and lists in real time — no manual reload required.
 *
 * The `accepted` and `requested` flows have their own dedicated
 * celebratory events (ConnectionAccepted, ConnectionRequested).
 */
class ConnectionStateChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  User    $actor   The user who performed the action.
     * @param  User    $target  The user being notified (channel owner).
     * @param  string  $kind    declined | cancelled | disconnected | blocked | unblocked
     */
    public function __construct(
        public User $actor,
        public User $target,
        public string $kind,
    ) {}

    public function broadcastAs(): string
    {
        return 'connection.state';
    }

    public function broadcastOn(): array
    {
        $tenantId = $this->target->tenant_id;
        return [new Channel("tenant.{$tenantId}.user.{$this->target->id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'kind' => $this->kind,
            'from' => [
                'id'       => $this->actor->id,
                'name'     => $this->actor->name,
                'username' => $this->actor->username,
                'avatar'   => $this->actor->avatar,
            ],
            'sent_at' => now()->toIso8601String(),
        ];
    }
}
