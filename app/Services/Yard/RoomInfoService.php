<?php

namespace App\Services\Yard;

use App\Enums\MessageType;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\RoomMemberRole;
use App\Enums\RoomType;
use App\Events\RoomUpdated;
use App\Models\Report;
use App\Models\User;
use App\Models\UserConnection;
use App\Models\UserContactName;
use App\Models\YardJoinRequest;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Models\YardRoomMemberHistory;
use App\Services\ConnectionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A chat's info panel (livewire/yard/room-info): who is in it, what was
 * shared, and the member's and admin's actions on it. Called by the web's
 * RoomInfo component and by /api/v1/yard, so both clients obey one set of
 * rules.
 *
 * The web component trusted its public $roomId: a crafted call could read
 * any room's members and media, or add people (yourself included) to any
 * group or DM. Every method here starts from the member's own membership.
 */
class RoomInfoService
{
    /** Rooms that belong to everyone in a place: nobody leaves or edits them. */
    private const SYSTEM_TYPES = ['national', 'regional', 'city'];

    public function __construct(
        private YardMessageService $messages,
        private ConnectionService $connections,
    ) {}

    public function isMember(User $user, YardRoom $room): bool
    {
        return $this->messages->membership($user, $room) !== null;
    }

    public function isDm(YardRoom $room): bool
    {
        return $room->room_type === RoomType::DirectMessage;
    }

    public function isAdmin(User $user, YardRoom $room): bool
    {
        return $room->created_by === $user->id;
    }

    /** The group photo: the creator of a private group only. */
    public function canEditAvatar(User $user, YardRoom $room): bool
    {
        return ! $room->is_system_room
            && $room->room_type === RoomType::PrivateGroup
            && $this->isAdmin($user, $room);
    }

    /** "Add member": any member of a private group or a city room, as the web offers it. */
    public function canAddMembers(User $user, YardRoom $room): bool
    {
        return in_array($room->room_type->value, ['private_group', 'city'], true) && $this->isMember($user, $room);
    }

    /** "Exit group": not a community room, and not the group's own admin. */
    public function canLeave(User $user, YardRoom $room): bool
    {
        return ! $this->isDm($room)
            && ! in_array($room->room_type->value, self::SYSTEM_TYPES, true)
            && ! $this->isAdmin($user, $room);
    }

    public function partner(User $user, YardRoom $room): ?User
    {
        if (! $this->isDm($room)) {
            return null;
        }
        $id = YardRoomMember::where('room_id', $room->id)->where('user_id', '!=', $user->id)->value('user_id');

        return $id ? User::find($id) : null;
    }

    public function isBlockedByMe(User $user, ?User $other): bool
    {
        if (! $other) {
            return false;
        }
        $c = UserConnection::between($user->id, $other->id);

        return $c && $c->status === UserConnection::STATUS_BLOCKED && $c->requested_by === $user->id;
    }

    // ── What the panel lists ─────────────────────────────────────────────

    /** Up to 50 members, the most recently active readers first; `filter` searches name and username. */
    public function members(YardRoom $room, ?string $filter = null): Collection
    {
        $filter = trim((string) $filter);

        return YardRoomMember::where('room_id', $room->id)
            ->with('user:id,name,username,avatar,cover_photo,bio,current_region,last_active_at,anonymised_at')
            ->when($filter !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('name', 'like', "%{$filter}%")
                ->orWhere('username', 'like', "%{$filter}%")))
            ->orderByDesc('last_read_at')
            ->limit(50)
            ->get()
            ->filter(fn ($m) => $m->user !== null)
            ->values();
    }

    /** Past participants (left or removed), the admin only, latest exit per person. */
    public function pastMembers(User $user, YardRoom $room): Collection
    {
        if (! $this->isAdmin($user, $room)) {
            return collect();
        }
        $current = YardRoomMember::where('room_id', $room->id)->pluck('user_id');

        return YardRoomMemberHistory::where('room_id', $room->id)
            ->whereNotIn('user_id', $current)
            ->with(['user:id,name,username,avatar', 'remover:id,name,username'])
            ->orderByDesc('exited_at')
            ->limit(100)
            ->get()
            ->unique('user_id')
            ->filter(fn ($e) => $e->user !== null)
            ->values();
    }

    /**
     * Pinned messages the member may see. The web's panel skipped the
     * joined-at cut-off its chat applies, so a newcomer could read pins from
     * before they arrived; going through visible() keeps the two in step.
     */
    public function pinned(User $user, YardRoom $room, int $limit = 10): Collection
    {
        return $this->messages->visible($user, $room)
            ->where('is_pinned', true)
            ->where('is_deleted', false)
            ->with('user:id,name,username')
            ->orderByDesc('pinned_at')
            ->limit($limit)
            ->get();
    }

    /** Photos, voice notes and documents, newest first. */
    public function media(User $user, YardRoom $room): Collection
    {
        return $this->messages->visible($user, $room)
            ->whereIn('message_type', [MessageType::Image->value, MessageType::File->value, MessageType::Audio->value])
            ->whereNotNull('media_path')
            ->where('is_deleted', false)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    /** This member's starred messages in this room. */
    public function starred(User $user, YardRoom $room): Collection
    {
        return $this->messages->visible($user, $room)
            ->whereIn('yard_messages.id', fn ($q) => $q->select('message_id')->from('yard_message_stars')->where('user_id', $user->id))
            ->where('is_deleted', false)
            ->with('user:id,name,username')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    /** Requests to join a private group, for its admin. */
    public function pendingRequests(User $user, YardRoom $room): Collection
    {
        if (! $room->is_private || ! $this->isAdmin($user, $room)) {
            return collect();
        }

        return YardJoinRequest::where('room_id', $room->id)
            ->where('status', 'pending')
            ->with('user:id,name,username,avatar,current_region')
            ->orderBy('created_at')
            ->get()
            ->filter(fn ($r) => $r->user !== null)
            ->values();
    }

    /**
     * People who could be added: in the tenant, findable, not already in,
     * and nobody either side has blocked (the web's search offered them).
     */
    public function candidates(User $user, YardRoom $room, string $search): Collection
    {
        $search = trim($search);
        if ($search === '' || ! $this->canAddMembers($user, $room)) {
            return collect();
        }
        $existing = YardRoomMember::where('room_id', $room->id)->pluck('user_id');

        return User::where('tenant_id', $user->tenant_id)
            ->discoverable()
            ->whereNotIn('id', $existing)
            ->whereNotIn('id', $user->blockedOrBlockingUserIds() ?: [0])
            ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"))
            ->select('id', 'name', 'username', 'avatar', 'current_region')
            ->limit(20)
            ->get();
    }

    // ── Changes ──────────────────────────────────────────────────────────

    /** @param  int[]  $userIds  @return int how many were added */
    public function addMembers(User $user, YardRoom $room, array $userIds): int
    {
        if (! $this->canAddMembers($user, $room)) {
            return 0;
        }
        $wanted = collect($userIds)->map(fn ($id) => (int) $id)->filter()->unique()->take(50);
        $existing = YardRoomMember::where('room_id', $room->id)->pluck('user_id')->all();
        $blocked = $user->blockedOrBlockingUserIds();
        $allowed = User::where('tenant_id', $user->tenant_id)->discoverable()
            ->whereIn('id', $wanted->diff($existing)->diff($blocked)->all())
            ->pluck('id');

        foreach ($allowed as $id) {
            YardRoomMember::create([
                'room_id' => $room->id,
                'user_id' => $id,
                'role' => RoomMemberRole::Member,
                'joined_at' => now(),
                'last_read_at' => now(),
            ]);
        }

        $added = $allowed->count();
        if ($added > 0) {
            $room->increment('members_count', $added);
            $this->announce($room, 'member_added', ['added_count' => $added]);
        }

        return $added;
    }

    public function setAvatar(User $user, YardRoom $room, UploadedFile $file): bool
    {
        if (! $this->canEditAvatar($user, $room)) {
            return false;
        }
        $this->deleteAvatarFile($room);
        $room->update(['avatar' => $file->store('yard/rooms/'.$room->id, 'public')]);
        $this->announce($room, 'avatar_changed');

        return true;
    }

    public function removeAvatar(User $user, YardRoom $room): bool
    {
        if (! $this->canEditAvatar($user, $room)) {
            return false;
        }
        $this->deleteAvatarFile($room);
        $room->update(['avatar' => null]);
        $this->announce($room, 'avatar_changed');

        return true;
    }

    /** The admin takes someone out of their group. */
    public function removeMember(User $admin, YardRoom $room, int $userId): bool
    {
        if (! $this->isAdmin($admin, $room) || $userId === $admin->id || $this->isDm($room)) {
            return false;
        }
        $membership = YardRoomMember::where('room_id', $room->id)->where('user_id', $userId)->first();
        if (! $membership) {
            return false;
        }

        YardRoomMemberHistory::create([
            'tenant_id' => $room->tenant_id,
            'room_id' => $room->id,
            'user_id' => $userId,
            'removed_by' => $admin->id,
            'reason' => 'removed',
            'exited_at' => now(),
        ]);
        $membership->delete();
        $room->decrement('members_count');
        $this->announce($room, 'member_removed', ['user_id' => $userId]);

        return true;
    }

    public function approveRequest(User $admin, YardRoom $room, int $requestId): bool
    {
        $request = YardJoinRequest::where('id', $requestId)->where('room_id', $room->id)->first();
        if (! $request || ! $this->isAdmin($admin, $room) || $request->status !== 'pending') {
            return false;
        }

        $request->update(['status' => 'approved', 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
        if (YardRoomMember::where('room_id', $room->id)->where('user_id', $request->user_id)->exists()) {
            return true;
        }

        // The "X joined" line is posted by YardRoomMember's own model event.
        YardRoomMember::create([
            'room_id' => $room->id,
            'user_id' => $request->user_id,
            'role' => RoomMemberRole::Member,
            'joined_at' => now(),
        ]);
        $room->increment('members_count');
        $this->clearRequestPill($room, $request->id);
        $this->announce($room, 'request_approved', ['user_id' => $request->user_id]);

        return true;
    }

    public function rejectRequest(User $admin, YardRoom $room, int $requestId): bool
    {
        $request = YardJoinRequest::where('id', $requestId)->where('room_id', $room->id)->first();
        if (! $request || ! $this->isAdmin($admin, $room) || $request->status !== 'pending') {
            return false;
        }

        $request->update(['status' => 'rejected', 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
        $this->clearRequestPill($room, $request->id);
        $this->announce($room, 'request_rejected', ['user_id' => $request->user_id]);

        return true;
    }

    /** @return string|null why not ('system_room', 'admin', 'not_member'), null when left */
    public function leave(User $user, YardRoom $room): ?string
    {
        if ($this->isDm($room) || in_array($room->room_type->value, self::SYSTEM_TYPES, true)) {
            return 'system_room';
        }
        if ($this->isAdmin($user, $room)) {
            return 'admin';
        }
        $membership = YardRoomMember::where('room_id', $room->id)->where('user_id', $user->id)->first();
        if (! $membership) {
            return 'not_member';
        }

        YardRoomMemberHistory::create([
            'tenant_id' => $room->tenant_id,
            'room_id' => $room->id,
            'user_id' => $user->id,
            'removed_by' => null,
            'reason' => 'left',
            'exited_at' => now(),
        ]);
        $membership->delete();
        $room->decrement('members_count');
        $this->announce($room, 'left', ['user_id' => $user->id]);

        return null;
    }

    /** @return bool false when this member already has a report open on it */
    public function report(User $user, YardRoom $room, string $reason, string $details = ''): bool
    {
        $open = Report::where('reporter_id', $user->id)
            ->where('reportable_type', YardRoom::class)
            ->where('reportable_id', $room->id)
            ->where('status', ReportStatus::Pending)
            ->exists();
        if ($open) {
            return false;
        }

        Report::create([
            'uuid' => (string) Str::uuid(),
            'reporter_id' => $user->id,
            'reportable_type' => YardRoom::class,
            'reportable_id' => $room->id,
            'reason' => ReportReason::tryFrom($reason) ?? ReportReason::Inappropriate,
            'details' => mb_substr(trim(strip_tags($details)), 0, 1000),
            'status' => ReportStatus::Pending,
        ]);

        return true;
    }

    /** Block the other person in a DM. */
    public function block(User $user, YardRoom $room): bool
    {
        $partner = $this->partner($user, $room);
        if (! $partner || ! $this->isMember($user, $room)) {
            return false;
        }
        try {
            $this->connections->block($user, $partner->id);
        } catch (\Throwable $e) {
            Log::warning('Block from room info failed: '.$e->getMessage());

            return false;
        }

        return true;
    }

    public function unblock(User $user, YardRoom $room): bool
    {
        $partner = $this->partner($user, $room);

        return $partner && $this->isMember($user, $room) && $this->connections->unblock($user, $partner->id);
    }

    /**
     * "Save as a contact": the member's own name for the other person in a
     * DM. Blank clears it. Returns the saved name, or null when cleared.
     */
    public function saveNickname(User $user, YardRoom $room, string $nickname): ?string
    {
        $partner = $this->partner($user, $room);
        if (! $partner) {
            return null;
        }
        $nickname = trim($nickname);
        if ($nickname === '') {
            UserContactName::where('owner_user_id', $user->id)->where('contact_user_id', $partner->id)->delete();
        } else {
            UserContactName::updateOrCreate(
                ['owner_user_id' => $user->id, 'contact_user_id' => $partner->id],
                ['tenant_id' => $user->tenant_id, 'nickname' => mb_substr($nickname, 0, 60)],
            );
        }
        Cache::driver('array')->forget("ucn.{$user->id}.{$partner->id}");

        return $nickname === '' ? null : mb_substr($nickname, 0, 60);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function deleteAvatarFile(YardRoom $room): void
    {
        if ($room->avatar && Storage::disk('public')->exists($room->avatar)) {
            Storage::disk('public')->delete($room->avatar);
        }
    }

    /** The admin-only "X asked to join" line goes once the request is answered. */
    private function clearRequestPill(YardRoom $room, int $requestId): void
    {
        try {
            YardMessage::where('room_id', $room->id)
                ->where('message_type', MessageType::System->value)
                ->whereJsonContains('media_metadata->kind', 'join_request')
                ->whereJsonContains('media_metadata->request_id', $requestId)
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('Join-request pill cleanup failed: '.$e->getMessage());
        }
    }

    /** room.updated: the room list, open chats and info panels refresh. */
    private function announce(YardRoom $room, string $kind, array $payload = []): void
    {
        try {
            broadcast(new RoomUpdated($room->fresh(), $kind, $payload));
        } catch (\Throwable $e) {
            Log::warning("Room {$kind} broadcast failed: ".$e->getMessage());
        }
    }
}
