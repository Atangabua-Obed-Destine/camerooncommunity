<?php

namespace App\Http\Presenters;

use App\Enums\MessageType;
use App\Models\User;
use App\Models\UserContactName;
use App\Models\YardJoinRequest;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Models\YardRoomMemberHistory;
use App\Services\Yard\RoomInfoService;
use App\Support\StorageUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A chat's info panel as JSON (livewire/yard/room-info), shaped for one
 * viewer. A sibling of YardPresenter, kept apart so the panel's many lists
 * don't crowd the message shape. Every URL is absolute.
 */
class RoomInfoPresenter
{
    public function __construct(private RoomInfoService $info) {}

    public function panel(YardRoom $room, User $viewer, YardRoomMember $me): array
    {
        $isDm = $this->info->isDm($room);
        $partner = $this->info->partner($viewer, $room);
        $media = $this->info->media($viewer, $room);

        return [
            'room' => [
                'id' => $room->id,
                'type' => $room->room_type->value,
                'is_dm' => $isDm,
                'is_private' => (bool) $room->is_private,
                'name' => $room->name,
                'description' => $room->description,
                'avatar_url' => StorageUrl::for($room->avatar),
                'emoji' => YardPresenter::ROOM_EMOJI[$room->room_type->value] ?? '💬',
                'avatar_seed' => 'room:'.$room->id,
                'members_count' => YardRoomMember::where('room_id', $room->id)->count(),
            ],
            'partner' => $isDm && $partner ? $this->partner($viewer, $partner) : null,
            'me' => [
                'is_admin' => $this->info->isAdmin($viewer, $room),
                'can_edit_avatar' => $this->info->canEditAvatar($viewer, $room),
                'can_add_members' => $this->info->canAddMembers($viewer, $room),
                'can_leave' => $this->info->canLeave($viewer, $room),
                'is_favorited' => (bool) $me->is_favorited,
                'is_archived' => $me->archived_at !== null,
            ],
            'media' => $media->map(fn (YardMessage $m) => [
                'id' => $m->id,
                'type' => $m->message_type->value,
                'url' => StorageUrl::for($m->media_path),
                'name' => $m->media_original_name,
            ])->values(),
            'starred' => $this->quotes($this->info->starred($viewer, $room)),
            'pinned' => $this->quotes($this->info->pinned($viewer, $room)),
            'requests' => $this->info->pendingRequests($viewer, $room)->map(fn (YardJoinRequest $r) => [
                'id' => $r->id,
                'user' => $this->person($r->user),
                'region' => $r->user->current_region,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->values(),
            'members' => $isDm ? [] : $this->members($this->info->members($room), $room, $viewer),
            'past_members' => $isDm ? [] : $this->info->pastMembers($viewer, $room)->map(fn (YardRoomMemberHistory $e) => [
                'user' => $this->person($e->user),
                'reason' => $e->reason,
                'removed_by' => $e->remover ? ($e->remover->username ?? $e->remover->name) : null,
                'exited_at' => $e->exited_at?->toIso8601String(),
            ])->values(),
        ];
    }

    /** The DM partner's card. No email: the web showed it to anyone in a DM, marketplace buyers included. */
    public function partner(User $viewer, User $p): array
    {
        $nickname = UserContactName::nicknamesFor($viewer->id, [$p->id])[$p->id] ?? null;

        return [
            'id' => $p->id,
            'name' => $nickname ?? ($p->username ?? $p->name),
            'handle' => $p->username ?? $p->name,
            'username' => $p->username,
            'nickname' => $nickname,
            'bio' => $p->bio,
            'avatar_url' => $p->avatarUrl(),
            'cover_url' => $p->coverUrl(),
            'avatar_seed' => 'user:'.$p->id,
            'is_blocked_by_me' => $this->info->isBlockedByMe($viewer, $p),
        ];
    }

    /** @param  Collection<int, YardRoomMember>  $members */
    public function members(Collection $members, YardRoom $room, User $viewer): array
    {
        $online = now()->subMinutes(5);

        return $members->map(fn (YardRoomMember $m) => $this->person($m->user) + [
            // Bio, else region, else role: the web's second line.
            'meta' => $m->user->bio ? Str::limit($m->user->bio, 40) : ($m->user->current_region ?: ($m->role?->value ?? '')),
            'is_admin' => $m->user_id === $room->created_by,
            'is_online' => $m->user->last_active_at && $m->user->last_active_at->gt($online),
            'is_me' => $m->user_id === $viewer->id,
        ])->values()->all();
    }

    /** @param  Collection<int, User>  $users */
    public function candidates(Collection $users): array
    {
        return $users->map(fn (User $u) => $this->person($u) + ['region' => $u->current_region])->values()->all();
    }

    public function person(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->username ?? $u->name,
            'username' => $u->username,
            'avatar_url' => StorageUrl::for($u->avatar),
            'avatar_seed' => 'user:'.$u->id,
        ];
    }

    /** A starred or pinned message as the panel quotes it. */
    private function quotes(Collection $messages): array
    {
        return $messages->map(fn (YardMessage $m) => [
            'id' => $m->id,
            'type' => $m->message_type->value,
            'sender' => $m->user ? ($m->user->username ?? $m->user->name) : null,
            'content' => $m->message_type === MessageType::Text || $m->content ? Str::limit((string) $m->content, 120) : null,
            'name' => $m->media_original_name,
            'created_at' => $m->created_at?->toIso8601String(),
        ])->values()->all();
    }
}
