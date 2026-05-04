<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlatformSettingUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $key, public mixed $value) {}

    public function broadcastOn(): array
    {
        return [new Channel('platform-settings')];
    }

    public function broadcastAs(): string
    {
        return 'setting.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
        ];
    }
}
