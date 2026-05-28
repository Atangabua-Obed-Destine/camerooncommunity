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
        $room = YardRoom::find($this->roomId);
        if (!$room || in_array($room->room_type->value, ['national', 'regional'])) {
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
        if (!$this->addMemberOpen || strlen($this->memberSearch) < 1) {
            return collect();
        }

        $existingMemberIds = YardRoomMember::where('room_id', $this->roomId)
            ->pluck('user_id')
            ->toArray();

        return User::where('tenant_id', auth()->user()->tenant_id)
            ->whereNotIn('id', $existingMemberIds)
            ->where(function ($q) {
                $q->where('name', 'like', '%' . $this->memberSearch . '%')
                  ->orWhere('username', 'like', '%' . $this->memberSearch . '%');
            })
            ->select('id', 'name', 'username', 'avatar', 'current_region')
            ->limit(20)
            ->get();
    }

    public function addSelectedMembers()
    {
        if (empty($this->selectedUsers) || !$this->roomId) {
            return;
        }

        $room = YardRoom::find($this->roomId);
        if (!$room || in_array($room->room_type->value, ['national', 'regional'])) {
            return;
        }

        $existingIds = YardRoomMember::where('room_id', $this->roomId)
            ->pluck('user_id')
            ->toArray();

        $added = 0;
        foreach ($this->selectedUsers as $user) {
            if (!in_array($user['id'], $existingIds)) {
                YardRoomMember::create([
                    'room_id' => $this->roomId,
                    'user_id' => $user['id'],
                    'role' => 'member',
                    'joined_at' => now(),
                    'last_read_at' => now(),
                ]);
                $added++;
            }
        }

        if ($added > 0) {
            $room->increment('members_count', $added);

            try {
                broadcast(new \App\Events\RoomUpdated($room->fresh(), 'member_added', [
                    'added_count' => $added,
                ]));
            } catch (\Throwable $e) {
                \Log::warning('Room member-added broadcast failed: ' . $e->getMessage());
            }
        }

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

        $room = YardRoom::find($this->roomId);
        if (!$room) {
            return;
        }

        // Only the admin (creator) of a non-DM, non-system room can change the avatar
        if (!$this->canEditRoom($room)) {
            $this->newAvatar = null;
            return;
        }

        $this->avatarUploading = true;

        try {
            // Delete old avatar if it exists and is in storage
            if ($room->avatar && Storage::disk('public')->exists($room->avatar)) {
                Storage::disk('public')->delete($room->avatar);
            }

            $path = $this->newAvatar->store('yard/rooms/' . $room->id, 'public');

            $room->update(['avatar' => $path]);
        } finally {
            $this->newAvatar = null;
            $this->avatarUploading = false;
        }

        $this->dispatch('room-updated');
        $this->dispatch('room-avatar-updated', roomId: $room->id);

        try {
            broadcast(new \App\Events\RoomUpdated($room->fresh(), 'avatar_changed'));
        } catch (\Throwable $e) {
            \Log::warning('Avatar broadcast failed: ' . $e->getMessage());
        }
    }

    /**
     * Admin removes the group avatar.
     */
    public function removeAvatar(): void
    {
        $room = YardRoom::find($this->roomId);
        if (!$room || !$this->canEditRoom($room)) {
            return;
        }

        if ($room->avatar && Storage::disk('public')->exists($room->avatar)) {
            Storage::disk('public')->delete($room->avatar);
        }

        $room->update(['avatar' => null]);

        $this->dispatch('room-updated');
        $this->dispatch('room-avatar-updated', roomId: $room->id);

        try {
            broadcast(new \App\Events\RoomUpdated($room->fresh(), 'avatar_changed'));
        } catch (\Throwable $e) {
            \Log::warning('Avatar broadcast failed: ' . $e->getMessage());
        }
    }

    /**
     * Determine whether the current user can edit this room's profile.
     */
    protected function canEditRoom(YardRoom $room): bool
    {
        if ($room->is_system_room) {
            return false;
        }
        if (in_array($room->room_type->value, ['national', 'regional', 'city', 'direct_message'], true)) {
            return false;
        }
        return $room->created_by === auth()->id();
    }

    /**
     * Admin removes a member from the group.
     */
    public function removeMember(int $userId): void
    {
        $room = YardRoom::find($this->roomId);
        if (!$room) {
            return;
        }

        // Only room creator (admin) can remove members
        if ($room->created_by !== auth()->id()) {
            return;
        }

        // Cannot remove yourself
        if ($userId === auth()->id()) {
            return;
        }

        $membership = YardRoomMember::where('room_id', $this->roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$membership) {
            return;
        }

        // Record the removal in history before deleting so admins can
        // review past members later.
        \App\Models\YardRoomMemberHistory::create([
            'tenant_id'  => $room->tenant_id,
            'room_id'    => $room->id,
            'user_id'    => $userId,
            'removed_by' => auth()->id(),
            'reason'     => 'removed',
            'exited_at'  => now(),
        ]);

        $membership->delete();
        $room->decrement('members_count');

        $this->dispatch('room-updated');

        try {
            broadcast(new \App\Events\RoomUpdated($room->fresh(), 'member_removed', [
                'user_id' => $userId,
            ]));
        } catch (\Throwable $e) {
            \Log::warning('Member-removed broadcast failed: ' . $e->getMessage());
        }
    }

    public function getRoomProperty()
    {
        if (!$this->roomId || !$this->visible) {
            return null;
        }
        return YardRoom::withCount('members')->find($this->roomId);
    }

    public function getMembersProperty()
    {
        if (!$this->roomId || !$this->visible) {
            return collect();
        }

        $filter = trim($this->memberFilter);

        return YardRoomMember::where('room_id', $this->roomId)
            ->with('user:id,name,username,avatar,cover_photo,bio,email,current_region,last_active_at')
            ->when($filter !== '', function ($q) use ($filter) {
                $q->whereHas('user', function ($u) use ($filter) {
                    $u->where('name', 'like', "%{$filter}%")
                      ->orWhere('username', 'like', "%{$filter}%");
                });
            })
            ->orderByDesc('last_read_at')
            ->limit(50)
            ->get();
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
        if (! $this->roomId || ! $this->visible) {
            return collect();
        }

        $room = YardRoom::find($this->roomId);
        if (! $room || $room->created_by !== auth()->id()) {
            return collect();
        }

        // Exclude users who have since rejoined — only show those who are
        // not currently active members of the room.
        $currentMemberIds = YardRoomMember::where('room_id', $this->roomId)
            ->pluck('user_id');

        return \App\Models\YardRoomMemberHistory::where('room_id', $this->roomId)
            ->whereNotIn('user_id', $currentMemberIds)
            ->with(['user:id,name,username,avatar', 'remover:id,name,username'])
            ->orderByDesc('exited_at')
            ->limit(100)
            ->get()
            // Collapse to most recent exit per user (in case of multiple cycles).
            ->unique('user_id')
            ->values();
    }

    public function getPinnedProperty()
    {
        if (!$this->roomId || !$this->visible) {
            return collect();
        }

        return YardMessage::where('room_id', $this->roomId)
            ->where('is_pinned', true)
            ->with('user:id,name,username')
            ->orderByDesc('pinned_at')
            ->limit(10)
            ->get();
    }

    public function getMediaProperty()
    {
        if (!$this->roomId || !$this->visible) {
            return collect();
        }

        return YardMessage::where('room_id', $this->roomId)
            ->whereIn('message_type', ['image', 'file', 'audio'])
            ->whereNotNull('media_path')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    public function getStarredProperty()
    {
        if (!$this->roomId || !$this->visible) {
            return collect();
        }

        $userId = auth()->id();

        return YardMessage::where('room_id', $this->roomId)
            ->whereIn('yard_messages.id', function ($q) use ($userId) {
                $q->select('message_id')
                    ->from('yard_message_stars')
                    ->where('user_id', $userId);
            })
            ->with('user:id,name,username')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    /**
     * Get pending join requests for this room (admin only).
     */
    public function getPendingRequestsProperty()
    {
        if (!$this->roomId || !$this->visible) {
            return collect();
        }

        $room = YardRoom::find($this->roomId);
        if (!$room || !$room->is_private || $room->created_by !== auth()->id()) {
            return collect();
        }

        return YardJoinRequest::where('room_id', $this->roomId)
            ->where('status', 'pending')
            ->with('user:id,name,username,avatar,current_region')
            ->orderBy('created_at')
            ->get();
    }

    public function approveRequest(int $requestId): void
    {
        $joinRequest = YardJoinRequest::with('room')->findOrFail($requestId);
        $room = $joinRequest->room;

        // Only room admin can approve
        if ($room->created_by !== auth()->id()) {
            return;
        }

        // Already a member
        if (YardRoomMember::where('room_id', $room->id)->where('user_id', $joinRequest->user_id)->exists()) {
            $joinRequest->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
            return;
        }

        YardRoomMember::create([
            'room_id' => $room->id,
            'user_id' => $joinRequest->user_id,
            'role' => RoomMemberRole::Member,
            'joined_at' => now(),
        ]);

        $room->increment('members_count');

        $joinRequest->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        // WhatsApp-style: replace the admin-only "requested to join" pill with
        // a public "X joined" system message visible to everyone in the room.
        // The announcement itself is now posted automatically by the
        // YardRoomMember model event; here we only need to clear the
        // previous "join_request" pill so it doesn't linger.
        try {
            \App\Models\YardMessage::where('room_id', $room->id)
                ->where('message_type', \App\Enums\MessageType::System->value)
                ->whereJsonContains('media_metadata->kind', 'join_request')
                ->whereJsonContains('media_metadata->request_id', $joinRequest->id)
                ->delete();
        } catch (\Throwable $e) {
            \Log::warning('Join-request pill cleanup failed: ' . $e->getMessage());
        }

        try {
            broadcast(new \App\Events\RoomUpdated($room->fresh(), 'request_approved', [
                'user_id' => $joinRequest->user_id,
            ]));
        } catch (\Throwable $e) {
            \Log::warning('Request-approved broadcast failed: ' . $e->getMessage());
        }
    }

    public function rejectRequest(int $requestId): void
    {
        $joinRequest = YardJoinRequest::with('room')->findOrFail($requestId);

        if ($joinRequest->room->created_by !== auth()->id()) {
            return;
        }

        $joinRequest->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        // Remove the pending admin-only "requested to join" pill so the chat stays clean.
        try {
            \App\Models\YardMessage::where('room_id', $joinRequest->room_id)
                ->where('message_type', \App\Enums\MessageType::System->value)
                ->whereJsonContains('media_metadata->kind', 'join_request')
                ->whereJsonContains('media_metadata->request_id', $joinRequest->id)
                ->delete();
        } catch (\Throwable $e) {
            \Log::warning('Join-rejected cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Member voluntarily leaves the room.
     * Group creator cannot leave their own room (must delete it instead).
     */
    public function leaveRoom(): void
    {
        $room = YardRoom::find($this->roomId);
        if (! $room) {
            return;
        }

        // Disallow leaving system rooms (national/regional/city) and own group
        if (in_array($room->room_type->value, ['national', 'regional', 'city'], true)) {
            $this->dispatch('toast', type: 'error', message: __('You cannot leave system rooms.'));
            return;
        }

        if ($room->created_by === auth()->id()) {
            $this->dispatch('toast', type: 'error', message: __('Group admins cannot leave their own group. Delete it instead.'));
            return;
        }

        $membership = YardRoomMember::where('room_id', $this->roomId)
            ->where('user_id', auth()->id())
            ->first();

        if (! $membership) {
            return;
        }

        // Record the leave in history before deleting so admins can
        // review past members later.
        \App\Models\YardRoomMemberHistory::create([
            'tenant_id'  => $room->tenant_id,
            'room_id'    => $room->id,
            'user_id'    => auth()->id(),
            'removed_by' => null,
            'reason'     => 'left',
            'exited_at'  => now(),
        ]);

        $membership->delete();
        $room->decrement('members_count');

        $this->visible = false;
        $this->roomId = null;

        $this->dispatch('toast', type: 'success', message: __('You left the group.'));
        $this->dispatch('room-left', roomId: $room->id);
        $this->dispatch('refreshRoomList');

        try {
            broadcast(new \App\Events\RoomUpdated($room->fresh(), 'left', [
                'user_id' => auth()->id(),
            ]));
        } catch (\Throwable $e) {
            \Log::warning('Leave broadcast failed: ' . $e->getMessage());
        }
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
        $room = YardRoom::find($this->roomId);
        if (! $room) {
            return;
        }

        $reasonEnum = \App\Enums\ReportReason::tryFrom($reason) ?? \App\Enums\ReportReason::Inappropriate;

        // Avoid duplicate open reports from the same user against this room.
        $existing = \App\Models\Report::query()
            ->where('reporter_id', auth()->id())
            ->where('reportable_type', YardRoom::class)
            ->where('reportable_id', $room->id)
            ->where('status', \App\Enums\ReportStatus::Pending)
            ->exists();

        if ($existing) {
            $this->dispatch('toast', type: 'info', message: __('You already reported this group. Our team is reviewing it.'));
            return;
        }

        \App\Models\Report::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'reporter_id' => auth()->id(),
            'reportable_type' => YardRoom::class,
            'reportable_id' => $room->id,
            'reason' => $reasonEnum,
            'details' => mb_substr(trim(strip_tags($details)), 0, 1000),
            'status' => \App\Enums\ReportStatus::Pending,
        ]);

        $this->dispatch('toast', type: 'success', message: __('Thanks — our team will review this group.'));
    }

    /**
     * Block the DM partner. Replaces any existing connection with a blocked record
     * owned by the current user. Reuses ConnectionService for canonical pair handling.
     */
    public function blockUser(int $userId): void
    {
        if ($userId === auth()->id()) {
            return;
        }

        // Only allow blocking from inside a DM with this user.
        $room = YardRoom::find($this->roomId);
        if (! $room || $room->room_type->value !== 'direct_message') {
            return;
        }

        $partnerInRoom = YardRoomMember::where('room_id', $this->roomId)
            ->where('user_id', $userId)
            ->where('user_id', '!=', auth()->id())
            ->exists();
        if (! $partnerInRoom) {
            return;
        }

        try {
            app(\App\Services\ConnectionService::class)->block(auth()->user(), $userId);
        } catch (\Throwable $e) {
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
        $room = YardRoom::find($this->roomId);
        if (!$room) return;
        $partner = $room->members()->where('user_id', '!=', auth()->id())->with('user')->first()?->user;
        if (!$partner) return;

        $nickname = trim($this->nicknameDraft);
        $ownerId  = auth()->id();

        if ($nickname === '') {
            \App\Models\UserContactName::where('owner_user_id', $ownerId)
                ->where('contact_user_id', $partner->id)
                ->delete();
            $this->dispatch('toast', type: 'success', message: __('Custom name removed'));
        } else {
            if (mb_strlen($nickname) > 60) {
                $this->dispatch('toast', type: 'error', message: __('Name is too long (60 max)'));
                return;
            }
            \App\Models\UserContactName::updateOrCreate(
                ['owner_user_id' => $ownerId, 'contact_user_id' => $partner->id],
                ['tenant_id' => auth()->user()->tenant_id, 'nickname' => $nickname],
            );
            $this->dispatch('toast', type: 'success', message: __('Saved as :name', ['name' => $nickname]));
        }

        \Cache::driver('array')->forget("ucn.{$ownerId}.{$partner->id}");
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
