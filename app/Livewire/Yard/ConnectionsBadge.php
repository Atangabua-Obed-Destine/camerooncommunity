<?php

namespace App\Livewire\Yard;

use App\Models\UserConnection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tiny badge that surfaces the count of pending incoming connection
 * requests for the current user. Self-refreshes on real-time events
 * dispatched by the ConnectionNotifier and ConnectionService.
 */
class ConnectionsBadge extends Component
{
    /** 'dot' (overlay on icon button) or 'pill' (inline list item). */
    public string $variant = 'dot';

    public function mount(string $variant = 'dot'): void
    {
        $this->variant = in_array($variant, ['dot', 'pill'], true) ? $variant : 'dot';
    }

    #[Computed]
    public function pendingCount(): int
    {
        $userId = auth()->id();
        if (! $userId) return 0;

        return UserConnection::where('status', UserConnection::STATUS_PENDING)
            ->where('requested_by', '!=', $userId)
            ->where(function ($q) use ($userId) {
                $q->where('user_a_id', $userId)->orWhere('user_b_id', $userId);
            })
            ->count();
    }

    /** Refresh when a new request arrives in real time. */
    #[On('connection-incoming')]
    #[On('connection-updated')]
    #[On('connection-badge-refresh')]
    public function refresh(): void
    {
        unset($this->pendingCount);
    }

    public function render()
    {
        return view('livewire.yard.connections-badge');
    }
}
