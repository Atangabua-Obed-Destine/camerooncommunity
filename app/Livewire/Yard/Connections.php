<?php

namespace App\Livewire\Yard;

use App\Models\User;
use App\Services\ConnectionService;
use App\Services\Yard\PeopleService;
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
    #[On('connection-updated')]
    public function refreshIncoming(): void
    {
        unset($this->incomingRequests, $this->myConnections, $this->sentRequests, $this->blockedConnections);
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
        return app(PeopleService::class)->connections(auth()->user());
    }

    /** Incoming pending requests for the current user. */
    #[Computed]
    public function incomingRequests()
    {
        return app(PeopleService::class)->incoming(auth()->user());
    }

    /** Outgoing pending requests sent by the current user. */
    #[Computed]
    public function sentRequests()
    {
        return app(PeopleService::class)->sent(auth()->user());
    }

    /**
     * Blocked connections: ONLY those the current user blocked. If the other
     * party blocked us, we must not reveal that fact here (privacy).
     */
    #[Computed]
    public function blockedConnections()
    {
        return app(PeopleService::class)->blocked(auth()->user())
            ->map(fn ($u) => ['user' => $u, 'blockedByMe' => true]);
    }

    /** People the user shares a room with, minus existing connections, blocked, and self. */
    #[Computed]
    public function suggestions()
    {
        return app(PeopleService::class)->suggestions(auth()->user());
    }

    /** Global search results (excluding self + blocked). */
    #[Computed]
    public function searchResults()
    {
        return app(PeopleService::class)->searchPeople(auth()->user(), $this->search);
    }

    /**
     * Per-user state ("connected" | "outgoing" | "incoming" | "blocked-by-me" | "blocked-by-them" | "none").
     * Used by the blade to render the right action button.
     */
    public function stateFor(int $otherId): string
    {
        return app(PeopleService::class)->state(auth()->user(), $otherId);
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
            // The accept() flow now also creates the 1:1 DM room — refresh the
            // chat list so it appears immediately on the accepter's side.
            $this->dispatch('refreshRoomList');
        }
    }

    public function declineRequest(int $userId): void
    {
        app(ConnectionService::class)->decline(auth()->user(), $userId);
        $this->dispatch('connection-updated', userId: $userId, state: 'none');
    }

    public function cancelRequest(int $userId): void
    {
        app(ConnectionService::class)->cancel(auth()->user(), $userId);
        $this->dispatch('connection-updated', userId: $userId, state: 'none');
    }

    public function disconnect(int $userId): void
    {
        app(ConnectionService::class)->disconnect(auth()->user(), $userId);
        $this->dispatch('connection-updated', userId: $userId, state: 'none');
    }

    public function block(int $userId): void
    {
        app(ConnectionService::class)->block(auth()->user(), $userId);
        $this->dispatch('toast', type: 'success', message: app()->getLocale() === 'fr'
            ? 'Utilisateur bloqué.'
            : 'User blocked.');
        $this->dispatch('connection-updated', userId: $userId, state: 'blocked-by-me');
    }

    public function unblock(int $userId): void
    {
        app(ConnectionService::class)->unblock(auth()->user(), $userId);
        $this->dispatch('connection-updated', userId: $userId, state: 'none');
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
