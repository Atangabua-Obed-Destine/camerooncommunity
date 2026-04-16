<?php

namespace App\Events;

use App\Models\YardCall;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public YardCall $call,
        public string $callerName,
    ) {}

    public function broadcastAs(): string
    {
        return 'CallStarted';
    }

    public function broadcastOn(): array
    {
        $channels = [
            new Channel('tenant.' . $this->call->tenant_id . '.room.' . $this->call->room_id),
        ];

        // Also broadcast to each non-initiator participant's personal channel
        // so they receive the call even if they aren't viewing this room
        $participantIds = $this->call->participants()
            ->where('user_id', '!=', $this->call->initiated_by)
            ->pluck('user_id');

        foreach ($participantIds as $uid) {
            $channels[] = new Channel('tenant.' . $this->call->tenant_id . '.user.' . $uid . '.calls');
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'call_uuid' => $this->call->uuid,
            'call_id' => $this->call->id,
            'room_id' => $this->call->room_id,
            'call_type' => $this->call->call_type,
            'initiated_by' => $this->call->initiated_by,
            'caller_name' => $this->callerName,
        ];
    }
}
