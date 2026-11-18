<?php

namespace App\Livewire\Yard;

use App\Enums\RoomMemberRole;
use App\Models\User;
use App\Models\YardJoinRequest;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class RoomInfo extends Component
{
    use WithFileUploads;

    public ?int $roomId = null;
    public bool $visible = false;

    // ── Add-member state ──
    public bool $addMemberOpen = false;
    public string $memberSearch = '';
    public array $selectedUsers = [];

    // ── Members list filter (in-group search) ──
    public string $memberFilter = '';

    // ── Avatar upload state ──
    public $newAvatar = null;
    public bool $avatarUploading = false;

    // ── DM nickname ("Save as a contact") state ──
    public bool $editingNickname = false;
    public string $nicknameDraft = '';

    private function info(): \App\Services\Yard\RoomInfoService
    {
        return app(\App\Services\Yard\RoomInfoService::class);
    }

    /** The room, only when the viewer is in it (the panel's roomId is client-set). */
    private function memberRoom(): ?YardRoom
    {
        $room = $this->roomId ? YardRoom::find($this->roomId) : null;

        return $room && $this->info()->isMember(auth()->user(), $room) ? $room : null;
    }

    protected $listeners = [
        'show-room-info' => 'showInfo',
        'room-updated'   => 'refreshRoomState',
    ];

    /**
     * Re-pull room + members + pinned + media when an Echo broadcast or
     * sibling component dispatches `room-updated`. Keeps the side panel
     * roster, header, and pinned list current without a manual refresh.
     */
    public function refreshRoomState(): void
    {
        unset(
            $this->room,
            $this->members,
            $this->pinned,
            $this->media,
            $this->starred,
            $this->pendingRequests,
        );
    }

    /**
     * Called when the info panel is actually opened.
     */
    public function showInfo(int $roomId)
    {
        $this->roomId = $roomId;
        $this->visible = true;
        $this->closeAddMember();
    }

    // ── Add-member methods ──

    public function openAddMember()
    {
        $room = $this->memberRoom();
        if (! $room || ! $this->info()->canAddMembers(auth()->user(), $room)) {
            return;
        }

        $this->addMemberOpen = true;
        $this->memberSearch = '';
        $this->selectedUsers = [];
    }

    public function closeAddMember()
    {
        $this->addMemberOpen = false;
        $this->memberSearch = '';
        $this->selectedUsers = [];
    }

    public function toggleUserSelection(int $userId, string $name)
    {
        $key = array_search($userId, array_column($this->selectedUsers, 'id'));
        if ($key !== false) {
            array_splice($this->selectedUsers, $key, 1);
        } else {
            $this->selectedUsers[] = ['id' => $userId, 'name' => $name];
        }
    }

    public function getSearchResultsProperty()
    {
        $room = $this->addMemberOpen ? $this->memberRoom() : null;
        if (! $room || strlen($this->memberSearch) < 1) {
            return collect();
        }

        return $this->info()->candidates(auth()->user(), $room, $this->memberSearch);
    }

    public function addSelectedMembers()
    {
        $room = $this->memberRoom();
        if (empty($this->selectedUsers) || ! $room) {
            return;
        }

        // The service checks this member may add here, and skips anyone
        // already in, blocked, or outside the tenant. It announces the change.
        $this->info()->addMembers(auth()->user(), $room, array_column($this->selectedUsers, 'id'));
        $this->closeAddMember();
    }

    /**
     * Admin updates the group avatar (auto-saves on file selection).
     */
    public function updatedNewAvatar(): void
    {
        $this->validate([
            'newAvatar' => 'image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $room = $this->memberRoom();
        $this->avatarUploading = true;
        try {
            $ok = $room && $this->info()->setAvatar(auth()->user(), $room, $this->newAvatar);
        } finally {
            $this->newAvatar = null;
            $this->avatarUploading = false;
        }

        if ($ok) {
            $this->dispatch('room-updated');
            $this->dispatch('room-avatar-updated', roomId: $room->id);
        }
    }

    /**
     * Admin removes the group avatar.
     */
    public function removeAvatar(): void
    {
        $room = $this->memberRoom();
        if ($room && $this->info()->removeAvatar(auth()->user(), $room)) {
            $this->dispatch('room-updated');
            $this->dispatch('room-avatar-updated', roomId: $room->id);
        }
    }

    /**
     * Determine whether the current user can edit this room's profile.
     */
    protected function canEditRoom(YardRoom $room): bool
    {
        return $this->info()->canEditAvatar(auth()->user(), $room);
    }

    /**
     * Admin removes a member from the group.
     */
    public function removeMember(int $userId): void
    {
        $room = $this->memberRoom();
        if ($room && $this->info()->removeMember(auth()->user(), $room, $userId)) {
            $this->dispatch('room-updated');
        }
    }

    public function getRoomProperty()
    {
        if (!$this->roomId || !$this->visible || ! $this->memberRoom()) {
            return null;
        }
        return YardRoom::withCount('members')->find($this->roomId);
    }

    public function getMembersProperty()
    {
        $room = $this->visible ? $this->memberRoom() : null;

        return $room ? $this->info()->members($room, $this->memberFilter) : collect();
    }

    public function updatedMemberFilter(): void
    {
        unset($this->members);
    }

    /**
     * Past members (left or removed) for the current room — only loaded
     * for the room creator (admin), matching WhatsApp's "Past participants".
     */
    public function getPastMembersProperty()
    {
        $room = $this->visible ? $this->memberRoom() : null;

        return $room ? $this->info()->pastMembers(auth()->user(), $room) : collect();
    }

    public function getPinnedProperty()
    {
        $room = $this->visible ? $this->memberRoom() : null;

        // Through visible(): no pins from before the viewer joined (the chat's own rule).
        return $room ? $this->info()->pinned(auth()->user(), $room) : collect();
    }

    public function getMediaProperty()
    {
        $room = $this->visible ? $this->memberRoom() : null;

        return $room ? $this->info()->media(auth()->user(), $room) : collect();
    }

    public function getStarredProperty()
    {
        $room = $this->visible ? $this->memberRoom() : null;

        return $room ? $this->info()->starred(auth()->user(), $room) : collect();
    }

    /**
     * Get pending join requests for this room (admin only).
     */
    public function getPendingRequestsProperty()
    {
        $room = $this->visible ? $this->memberRoom() : null;

        return $room ? $this->info()->pendingRequests(auth()->user(), $room) : collect();
    }

    public function approveRequest(int $requestId): void
    {
        $room = $this->memberRoom();
        if ($room) {
            $this->info()->approveRequest(auth()->user(), $room, $requestId);
        }
    }

    public function rejectRequest(int $requestId): void
    {
        $room = $this->memberRoom();
        if ($room) {
            $this->info()->rejectRequest(auth()->user(), $room, $requestId);
        }
    }

    /**
     * Member voluntarily leaves the room.
     * Group creator cannot leave their own room (must delete it instead).
     */
    public function leaveRoom(): void
    {
        $room = $this->roomId ? YardRoom::find($this->roomId) : null;
        if (! $room) {
            return;
        }

        $why = $this->info()->leave(auth()->user(), $room);
        if ($why === 'system_room') {
            $this->dispatch('toast', type: 'error', message: __('You cannot leave system rooms.'));
            return;
        }
        if ($why === 'admin') {
            $this->dispatch('toast', type: 'error', message: __('Group admins cannot leave their own group. Delete it instead.'));
            return;
        }
        if ($why !== null) {
            return;
        }

        $this->visible = false;
        $this->roomId = null;

        $this->dispatch('toast', type: 'success', message: __('You left the group.'));
        $this->dispatch('room-left', roomId: $room->id);
        $this->dispatch('refreshRoomList');
    }

    /**
     * Pin / unpin (favorite) the current chat for this user.
     * Mirrors RoomList::toggleFavorite so the action is reachable from
     * inside the contact-info pane as well as the chat list.
     */
    public function togglePinChat(): void
    {
        $member = YardRoomMember::where('room_id', $this->roomId)
            ->where('user_id', auth()->id())
            ->first();

        if (! $member) {
            return;
        }

        $nowPinned = ! $member->is_favorited;
        $member->update(['is_favorited' => $nowPinned]);

        $this->dispatch('refreshRoomList');
        $this->dispatch('toast',
            type: 'success',
            message: $nowPinned ? __('Chat pinned') : __('Chat unpinned'),
        );
    }

    /**
     * Manually archive / unarchive the current chat for this user.
     * Mirrors RoomList::toggleArchive — uses the manual `archived_at`
     * column (never the location-driven `auto_archived_at`).
     */
    public function toggleArchiveChat(): void
    {
        $member = YardRoomMember::where('room_id', $this->roomId)
            ->where('user_id', auth()->id())
            ->first();

        if (! $member) {
            return;
        }

        $isArchived = (bool) $member->archived_at;
        $member->update(['archived_at' => $isArchived ? null : now()]);

        $this->dispatch('refreshRoomList');

        // Keep the topbar archive badge in sync.
        $newCount = YardRoomMember::where('user_id', auth()->id())
            ->whereNotNull('archived_at')
            ->whereNull('auto_archived_at')
            ->count();
        $this->dispatch('archived-count-changed', count: $newCount);

        $this->dispatch('toast',
            type: 'success',
            message: $isArchived ? __('Chat unarchived') : __('Chat archived'),
        );

        // Note: keep the Contact info pane open and the chat surface intact.
        // Previously we closed the pane / deselected the room here, but that
        // left the right pane empty until the user refreshed.
    }

    /**
     * User reports the current room to admins.
     * `reason` should be a value from \App\Enums\ReportReason.
     */
    public function reportRoom(string $reason = 'inappropriate', string $details = ''): void
    {
        $room = $this->memberRoom();
        if (! $room) {
            return;
        }

        if (! $this->info()->report(auth()->user(), $room, $reason, $details)) {
            $this->dispatch('toast', type: 'info', message: __('You already reported this group. Our team is reviewing it.'));
            return;
        }

        $this->dispatch('toast', type: 'success', message: __('Thanks, our team will review this group.'));
    }

    /**
     * Block the DM partner. Replaces any existing connection with a blocked record
     * owned by the current user. Reuses ConnectionService for canonical pair handling.
     */
    public function blockUser(int $userId): void
    {
        $room = $this->memberRoom();
        $partner = $room ? $this->info()->partner(auth()->user(), $room) : null;
        if (! $partner || $partner->id !== $userId) {
            return;
        }

        if (! $this->info()->block(auth()->user(), $room)) {
            $this->dispatch('toast', type: 'error', message: __('Could not block this user.'));
            return;
        }

        $this->dispatch('toast', type: 'success', message: __('User blocked. They can no longer message you.'));
        // Refresh chat-room so the blocked banner / disabled input appears immediately.
        $this->dispatch('room-updated');
        // Also invalidate dmConnectionState in ChatRoom (separate listener) so the
        // text input is hidden right away — not only after a manual reload.
        $this->dispatch('connection-updated', userId: $userId, state: 'blocked-by-me');
    }

    /**
     * Unblock the DM partner (only the user who placed the block can lift it).
     */
    public function unblockUser(int $userId): void
    {
        if ($userId === auth()->id()) {
            return;
        }

        $ok = app(\App\Services\ConnectionService::class)->unblock(auth()->user(), $userId);

        if ($ok) {
            $this->dispatch('toast', type: 'success', message: __('User unblocked.'));
            $this->dispatch('room-updated');
            $this->dispatch('connection-updated', userId: $userId, state: 'none');
        } else {
            $this->dispatch('toast', type: 'info', message: __('Nothing to unblock.'));
        }
    }

    /**
     * Open the inline nickname editor inside the DM info panel.
     */
    public function startEditingNickname(): void
    {
        $room = YardRoom::find($this->roomId);
        if (!$room) return;
        $partner = $room->members()->where('user_id', '!=', auth()->id())->with('user')->first()?->user;
        if (!$partner) return;

        $this->nicknameDraft  = (string) (auth()->user()->nicknameFor($partner->id) ?? '');
        $this->editingNickname = true;
    }

    public function cancelEditingNickname(): void
    {
        $this->editingNickname = false;
        $this->nicknameDraft = '';
    }

    /**
     * Save (or, when blank, clear) the per-viewer nickname for the DM partner.
     */
    public function saveContactNickname(): void
    {
        $room = $this->memberRoom();
        if (! $room || ! $this->info()->partner(auth()->user(), $room)) {
            return;
        }

        $nickname = trim($this->nicknameDraft);
        if (mb_strlen($nickname) > 60) {
            $this->dispatch('toast', type: 'error', message: __('Name is too long (60 max)'));
            return;
        }

        $saved = $this->info()->saveNickname(auth()->user(), $room, $nickname);
        $this->dispatch('toast', type: 'success', message: $saved === null
            ? __('Custom name removed')
            : __('Saved as :name', ['name' => $saved]));
        $this->editingNickname = false;

        // Refresh the chat list so the room row updates immediately
        $this->dispatch('refreshRoomList');
        $this->dispatch('room-updated');
    }

    public function render()
    {
        if (!$this->visible || !$this->roomId) {
            return view('livewire.yard.room-info', [
                'room' => null,
                'members' => collect(),
                'pinned' => collect(),
                'media' => collect(),
                'starred' => collect(),
                'pendingRequests' => collect(),
            ]);
        }

        return view('livewire.yard.room-info', [
            'room' => $this->room,
            'members' => $this->members,
            'pinned' => $this->pinned,
            'media' => $this->media,
            'starred' => $this->starred,
            'pendingRequests' => $this->pendingRequests,
        ]);
    }
}
