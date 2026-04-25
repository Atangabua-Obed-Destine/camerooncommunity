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

    // ── Avatar upload state ──
    public $newAvatar = null;
    public bool $avatarUploading = false;

    protected $listeners = [
        'show-room-info' => 'showInfo',
    ];

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

        $membership->delete();
        $room->decrement('members_count');

        $this->dispatch('room-updated');
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

        return YardRoomMember::where('room_id', $this->roomId)
            ->with('user:id,name,username,avatar,current_region,last_active_at')
            ->orderByDesc('last_read_at')
            ->limit(50)
            ->get();
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
