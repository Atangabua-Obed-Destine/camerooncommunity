<?php

namespace App\Livewire\Yard;

use App\Models\User;
use App\Models\UserConnection;
use App\Models\YardRoomMember;
use App\Services\ConnectionService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Connections extends Component
{
    public bool $show = false;
    public string $tab = 'mine'; // mine | requests | suggestions | search
    public string $search = '';

    #[On('open-connections')]
    public function open(string $tab = 'mine'): void
    {
        $this->show = true;
        $this->tab = $tab;
        $this->search = '';
    }

    /**
     * Triggered from the global notifier when a new connection request
     * arrives for this user — refresh computed lists so the badge/tab
     * shows the new entry without a page reload.
     */
    #[On('connection-incoming')]
    public function refreshIncoming(): void
    {
        unset($this->incomingRequests, $this->myConnections, $this->sentRequests);
    }

    public function close(): void
    {
        $this->show = false;
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->search = '';
    }

    /*
    |---------------------------------------------------------------
    | Computed lists
    |---------------------------------------------------------------
    */

    /** Accepted connections (mutual) for the current user. */
    #[Computed]
    public function myConnections()
    {
        $userId = auth()->id();

        $connections = UserConnection::where('status', UserConnection::STATUS_ACCEPTED)
            ->where(function ($q) use ($userId) {
                $q->where('user_a_id', $userId)->orWhere('user_b_id', $userId);
            })
            ->orderByDesc('accepted_at')
            ->get();

        $partnerIds = $connections->map(fn ($c) => $c->otherUserId($userId))->all();
        $users = User::whereIn('id', $partnerIds)
            ->get(['id', 'name', 'username', 'avatar', 'current_country'])
            ->keyBy('id');

        return $connections->map(fn ($c) => $users->get($c->otherUserId($userId)))->filter()->values();
    }

    /** Incoming pending requests for the current user. */
    #[Computed]
    public function incomingRequests()
    {
        $userId = auth()->id();

        $rows = UserConnection::where('status', UserConnection::STATUS_PENDING)
            ->where('requested_by', '!=', $userId)
            ->where(function ($q) use ($userId) {
                $q->where('user_a_id', $userId)->orWhere('user_b_id', $userId);
            })
            ->latest()
            ->get();

        $userIds = $rows->pluck('requested_by')->all();
        $users = User::whereIn('id', $userIds)
            ->get(['id', 'name', 'username', 'avatar', 'current_country'])
            ->keyBy('id');

        return $rows->map(fn ($r) => $users->get($r->requested_by))->filter()->values();
    }

    /** Outgoing pending requests sent by the current user. */
    #[Computed]
    public function sentRequests()
    {
        $userId = auth()->id();

        $rows = UserConnection::where('status', UserConnection::STATUS_PENDING)
            ->where('requested_by', $userId)
            ->latest()
            ->get();

        $userIds = $rows->map(fn ($r) => $r->otherUserId($userId))->all();
        $users = User::whereIn('id', $userIds)
            ->get(['id', 'name', 'username', 'avatar', 'current_country'])
            ->keyBy('id');

        return $rows->map(fn ($r) => $users->get($r->otherUserId($userId)))->filter()->values();
    }

    /**
     * People the user shares a National/Regional/City room with — minus
     * existing connections, blocked, and self.
     */
    #[Computed]
    public function suggestions()
    {
        $userId = auth()->id();

        $myRoomIds = YardRoomMember::where('user_id', $userId)->pluck('room_id');
        if ($myRoomIds->isEmpty()) {
            return collect();
        }

        $excludedIds = collect(UserConnection::where(function ($q) use ($userId) {
                $q->where('user_a_id', $userId)->orWhere('user_b_id', $userId);
            })
            ->get(['user_a_id', 'user_b_id'])
            ->map(fn ($c) => $c->user_a_id === $userId ? $c->user_b_id : $c->user_a_id)
            ->all())
            ->push($userId)
            ->unique()
            ->all();

        return User::query()
            ->whereIn('id', function ($q) use ($myRoomIds) {
                $q->select('user_id')
                    ->from('yard_room_members')
                    ->whereIn('room_id', $myRoomIds);
            })
            ->whereNotIn('id', $excludedIds)
            ->limit(30)
            ->get(['id', 'name', 'username', 'avatar', 'current_country']);
    }

    /** Global search results (excluding self + blocked). */
    #[Computed]
    public function searchResults()
    {
        $term = trim($this->search);
        if (strlen($term) < 2) {
            return collect();
        }
        $userId = auth()->id();

        $blockedIds = collect(UserConnection::where('status', UserConnection::STATUS_BLOCKED)
            ->where(function ($q) use ($userId) {
                $q->where('user_a_id', $userId)->orWhere('user_b_id', $userId);
            })
            ->get(['user_a_id', 'user_b_id'])
            ->map(fn ($c) => $c->user_a_id === $userId ? $c->user_b_id : $c->user_a_id)
            ->all())
            ->push($userId)
            ->unique()
            ->all();

        return User::query()
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%");
            })
            ->whereNotIn('id', $blockedIds)
            ->limit(30)
            ->get(['id', 'name', 'username', 'avatar', 'current_country']);
    }

    /**
     * Per-user state ("connected" | "outgoing" | "incoming" | "blocked-by-me" | "blocked-by-them" | "none").
     * Used by the blade to render the right action button.
     */
    public function stateFor(int $otherId): string
    {
        $c = UserConnection::between(auth()->id(), $otherId);
        if (! $c) return 'none';
        if ($c->status === UserConnection::STATUS_ACCEPTED) return 'connected';
        if ($c->status === UserConnection::STATUS_PENDING) {
            return $c->requested_by === auth()->id() ? 'outgoing' : 'incoming';
        }
        if ($c->status === UserConnection::STATUS_BLOCKED) {
            return $c->requested_by === auth()->id() ? 'blocked-by-me' : 'blocked-by-them';
        }
        return 'none';
    }

    /*
    |---------------------------------------------------------------
    | Actions
    |---------------------------------------------------------------
    */

    #[On('connect-user')]
    public function sendRequest(int $userId): void
    {
        $other = User::find($userId);
        if (! $other) return;
        try {
            app(ConnectionService::class)->request(auth()->user(), $other);
            $this->dispatch('toast', type: 'success', message: app()->getLocale() === 'fr'
                ? 'Demande de connexion envoyée.'
                : 'Connection request sent.');
            $this->dispatch('connection-updated', userId: $userId, state: 'outgoing');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
            $this->dispatch('connection-failed', userId: $userId, message: $e->getMessage());
        }
    }

    #[On('accept-user')]
    public function acceptRequest(int $userId): void
    {
        if (app(ConnectionService::class)->accept(auth()->user(), $userId)) {
            $this->dispatch('toast', type: 'success', message: app()->getLocale() === 'fr'
                ? 'Connexion acceptée.'
                : 'Connection accepted.');
            $this->dispatch('connection-updated', userId: $userId, state: 'connected');
        }
    }

    public function declineRequest(int $userId): void
    {
        app(ConnectionService::class)->decline(auth()->user(), $userId);
    }

    public function cancelRequest(int $userId): void
    {
        app(ConnectionService::class)->cancel(auth()->user(), $userId);
    }

    public function disconnect(int $userId): void
    {
        app(ConnectionService::class)->disconnect(auth()->user(), $userId);
    }

    public function block(int $userId): void
    {
        app(ConnectionService::class)->block(auth()->user(), $userId);
        $this->dispatch('toast', type: 'success', message: app()->getLocale() === 'fr'
            ? 'Utilisateur bloqué.'
            : 'User blocked.');
    }

    public function unblock(int $userId): void
    {
        app(ConnectionService::class)->unblock(auth()->user(), $userId);
    }

    /**
     * Open (or create) the DM with this user — only works once connected.
     */
    public function openDm(int $userId): void
    {
        if (! auth()->user()->isConnectedWith($userId)) {
            $this->dispatch('toast', type: 'warning', message: app()->getLocale() === 'fr'
                ? 'Vous devez d\'abord vous connecter avec cet utilisateur.'
                : 'You must connect with this user first.');
            return;
        }

        $this->show = false;
        $this->dispatch('open-dm', userId: $userId);
    }

    public function render()
    {
        return view('livewire.yard.connections');
    }
}
