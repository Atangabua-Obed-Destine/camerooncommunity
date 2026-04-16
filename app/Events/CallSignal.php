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
        return [
            new Channel('tenant.' . $this->tenantId . '.room.' . $this->roomId),
        ];
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
