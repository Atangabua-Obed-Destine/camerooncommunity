<?php

use App\Models\User;
use App\Models\YardRoomMember;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Who may listen
|--------------------------------------------------------------------------
|
| Every room and per-member channel is private. They were public, and the
| names are guessable (tenant.1.room.9), so anyone could open a socket and
| read any room, DMs and private groups included, or watch someone's
| receipts, mentions and call signals. The browser now has to ask
| /broadcasting/auth first, which runs these.
|
| platform-settings and admin-health.{id} stay public: they carry no member
| data.
*/

// A room: its members, as ChatRoom::mount requires to read it at all.
// Archived ("away") members still count: the room list shows their last
// message.
Broadcast::channel('tenant.{tenantId}.room.{roomId}', function (User $user, int $tenantId, int $roomId) {
    return (int) $user->tenant_id === $tenantId
        && YardRoomMember::where('room_id', $roomId)->where('user_id', $user->id)->exists();
});

// One member's own channels: connections and mentions, read receipts, calls.
$own = fn (User $user, int $tenantId, int $userId) => (int) $user->id === $userId && (int) $user->tenant_id === $tenantId;

Broadcast::channel('tenant.{tenantId}.user.{userId}', $own);
Broadcast::channel('tenant.{tenantId}.user.{userId}.receipts', $own);
Broadcast::channel('tenant.{tenantId}.user.{userId}.calls', $own);
