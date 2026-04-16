<?php

namespace App\Events;

use App\Models\YardCallParticipant;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $tenantId,
        public int $roomId,
        public string $callUuid,
        public string $status,
        public int $userId,
        public string $userName,
        public string $action,
    ) {}

    public function broadcastAs(): string
    {
        return 'CallUpdated';
    }

    public function broadcastOn(): array
    {
        $channels = [
            new Channel('tenant.' . $this->tenantId . '.room.' . $this->roomId),
        ];

        // For ended/declined actions, also broadcast to personal channels
        // so participants receive the update even if not subscribed to the room
        if (in_array($this->action, ['ended', 'declined'])) {
            $call = \App\Models\YardCall::where('uuid', $this->callUuid)->first();
            if ($call) {
                $participantIds = $call->participants()
                    ->where('user_id', '!=', $this->userId)
                    ->pluck('user_id');

                foreach ($participantIds as $uid) {
                    $channels[] = new Channel('tenant.' . $this->tenantId . '.user.' . $uid . '.calls');
                }
            }
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'call_uuid' => $this->callUuid,
            'status' => $this->status,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'action' => $this->action,
        ];
    }
}
