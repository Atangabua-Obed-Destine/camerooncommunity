<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallSignal implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $tenantId,
        public int $roomId,
        public string $callUuid,
        public int $fromUserId,
        public int $toUserId,
        public string $signalType,
        public array $signalData,
    ) {}

    public function broadcastAs(): string
    {
        return 'CallSignal';
    }

    public function broadcastOn(): array
    {
        $channels = [
            new Channel('tenant.' . $this->tenantId . '.room.' . $this->roomId),
        ];

        // Signalling is point to point, so send it to the recipient's own call
        // channel as well. The room channel is the historical path, but a peer
        // is only on it while the room is open and announced — and an offer or
        // an ICE candidate that misses its target leaves a call that looks
        // connected with no audio flowing. Everyone in a call is on their own
        // .calls channel, which is how the call reached them to begin with.
        if ($this->toUserId > 0) {
            $channels[] = new Channel('tenant.' . $this->tenantId . '.user.' . $this->toUserId . '.calls');
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'call_uuid' => $this->callUuid,
            'from_user_id' => $this->fromUserId,
            'to_user_id' => $this->toUserId,
            'signal_type' => $this->signalType,
            'signal_data' => $this->signalData,
        ];
    }
}
