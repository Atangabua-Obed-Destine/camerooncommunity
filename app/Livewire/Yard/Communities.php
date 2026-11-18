<?php

namespace App\Livewire\Yard;

use App\Services\Yard\CommunityService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Communities extends Component
{
    public bool $show = false;
    public string $tab = 'mine'; // mine | discover
    public string $search = '';

    // Create group inline
    public bool $creating = false;
    public string $newName = '';
    public string $newDescription = '';
    public bool $newIsPrivate = true;

    #[On('open-communities')]
    public function open(): void
    {
        $this->show = true;
        $this->tab = 'mine';
        $this->search = '';
        $this->creating = false;
    }

    public function close(): void
    {
        $this->show = false;
        $this->creating = false;
        $this->resetCreate();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->search = '';
    }

    #[Computed]
    public function myCommunities()
    {
        return app(CommunityService::class)->mine(auth()->user(), $this->search);
    }

    #[Computed]
    public function discoverCommunities()
    {
        return app(CommunityService::class)->discover(auth()->user(), $this->search);
    }

    /**
     * Get pending join request IDs for current user (used in discover view).
     */
    #[Computed]
    public function pendingRequestRoomIds(): array
    {
        return app(CommunityService::class)->pendingRequestRoomIds(auth()->user());
    }

    /** Only a room Discover offers this member (CommunityService checks). */
    public function joinCommunity(int $roomId): void
    {
        $result = app(CommunityService::class)->join(auth()->user(), $roomId);
        unset($this->discoverCommunities, $this->pendingRequestRoomIds, $this->myCommunities);

        if ($result === 'joined') {
            $this->dispatch('room-updated');
        }
    }

    public function requestToJoin(int $roomId): void
    {
        $this->joinCommunity($roomId);
    }

    public function cancelRequest(int $roomId): void
    {
        app(CommunityService::class)->cancelRequest(auth()->user(), $roomId);
        unset($this->pendingRequestRoomIds);
    }

    public function leaveCommunity(int $roomId): void
    {
        $result = app(CommunityService::class)->leave(auth()->user(), $roomId);

        // Default location-based rooms (National / Regional) cannot be left.
        // Membership is managed automatically by the location switch system.
        if ($result === CommunityService::LEAVE_DEFAULT) {
            $this->dispatch('toast', type: 'info', message: app()->getLocale() === 'fr'
                ? 'Vous ne pouvez pas quitter votre groupe national ou régional par défaut.'
                : "You can't leave your default country or regional group.");
            return;
        }

        if ($result === true) {
            unset($this->myCommunities, $this->discoverCommunities);
            $this->dispatch('room-updated');
        }
    }

    public function openCommunity(int $roomId): void
    {
        $this->show = false;
        $this->dispatch('room-selected', roomId: $roomId);
    }

    public function startCreate(): void
    {
        $this->creating = true;
        $this->newName = '';
        $this->newDescription = '';
        $this->newIsPrivate = true;
    }

    public function resetCreate(): void
    {
        $this->creating = false;
        $this->newName = '';
        $this->newDescription = '';
        $this->newIsPrivate = true;
    }

    public function createCommunity(): void
    {
        $this->validate([
            'newName' => 'required|string|min:2|max:100',
            'newDescription' => 'nullable|string|max:500',
        ]);

        app(CommunityService::class)->create(auth()->user(), $this->newName, $this->newDescription, $this->newIsPrivate);

        $this->resetCreate();
        unset($this->myCommunities);
        $this->dispatch('room-updated');
    }

    public function render()
    {
        return view('livewire.yard.communities');
    }
}
