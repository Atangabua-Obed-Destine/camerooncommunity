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

        // Also address each other participant directly.
        //
        // The room channel alone was not enough: a caller who landed in the room
        // by way of something that never announced it, or whose socket dropped
        // and came back mid-ring, is not subscribed to it — and then the 'joined'
        // event from the person picking up never arrived, leaving the caller on
        // "Calling…" while the other side was already in the call. Everyone in a
        // call is always on their own .calls channel, since that is how the call
        // reached them in the first place.
        $call = \App\Models\YardCall::where('uuid', $this->callUuid)->first();

        if ($call) {
            $participantIds = $call->participants()
                ->where('user_id', '!=', $this->userId)
                ->pluck('user_id');

            foreach ($participantIds as $uid) {
                $channels[] = new Channel('tenant.' . $this->tenantId . '.user.' . $uid . '.calls');
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
