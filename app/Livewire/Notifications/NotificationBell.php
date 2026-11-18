<?php

namespace App\Livewire\Notifications;

use App\Services\NotificationFeed;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Real-time notification bell shown in the global header.
 *
 * Aggregates four live signals into a single dropdown:
 *   1. Unread DM messages (one row per conversation, latest first).
 *   2. Unread @mentions across any room.
 *   3. Pending join requests for groups the current user owns (admin).
 *   4. Pending incoming connection requests.
 *
 * Realtime: refreshes itself when the existing client-side dispatchers
 * fire ('connection-incoming', 'connection-updated', 'bell-refresh',
 * 'refreshRoomList', etc.) — no extra Echo wiring needed in this PHP
 * class because the blade view re-broadcasts user channel events as
 * Livewire 'bell-refresh' calls.
 */
class NotificationBell extends Component
{
    /** Active filter tab: 'all' | 'mentions' | 'groups' | 'connections'. */
    public string $tab = 'all';

    /**
     * The rows and counts (App\Services\NotificationFeed, shared with the
     * app's GET /api/v1/notifications), computed once per render.
     */
    #[Computed]
    public function built(): array
    {
        $user = auth()->user();

        return $user
            ? app(NotificationFeed::class)->build($user)
            : ['items' => collect(), 'counts' => ['all' => 0, 'mentions' => 0, 'groups' => 0, 'connections' => 0]];
    }

    /** Unified, time-sorted notification feed for the open tab. */
    #[Computed]
    public function feed(): Collection
    {
        return NotificationFeed::filter($this->built['items'], $this->tab);
    }

    /** Counts per tab — used to render colored badges on the segmented control. */
    #[Computed]
    public function counts(): array
    {
        return $this->built['counts'];
    }

    /** Total badge count shown on the bell icon. */
    #[Computed]
    public function total(): int
    {
        return (int) $this->counts['all'];
    }

    /**
     * Stored notifications are the only rows with a read state of their own: the
     * rest stop counting when the underlying thing is handled. Clicking one has
     * to mark it read, or it counts forever.
     */
    public function openStored(string $id)
    {
        $data = auth()->user() ? app(NotificationFeed::class)->openStored(auth()->user(), $id) : null;

        if ($data === null) {
            return null;
        }

        $this->bust();

        return redirect()->to($data['url'] ?? route('yard'));
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, NotificationFeed::TABS, true) ? $tab : 'all';
    }

    /** Mark every chat room as read for this user (does not touch action items). */
    public function markAllChatsRead(): void
    {
        if (! auth()->user()) return;

        // Every chat, and the stored notifications (order events) with them.
        app(NotificationFeed::class)->markAllChatsRead(auth()->user());

        $this->bust();
        $this->dispatch('refreshRoomList');
        $this->dispatch('toast', type: 'success', message: __('All notifications marked as read'));
    }

    /** Refresh hooks fired by existing client dispatchers. */
    #[On('connection-incoming')]
    #[On('connection-updated')]
    #[On('connection-badge-refresh')]
    #[On('refreshRoomList')]
    #[On('bell-refresh')]
    public function bust(): void
    {
        unset($this->built, $this->feed, $this->counts, $this->total);
    }

    public function render()
    {
        return view('livewire.notifications.notification-bell');
    }
}
