<?php

namespace App\Services;

use App\Enums\RoomType;
use App\Models\User;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Support\Str;

/**
 * Finding and opening the one-to-one room between two people.
 *
 * Extracted from YardController::createDm so the profile page can open a
 * conversation without going through HTTP. The connection gate is the caller's
 * responsibility — see ConnectionService and User::isConnectedWith() — because
 * each caller reports a refusal differently (a 403 for the endpoint, a toast in
 * a Livewire component).
 */
class DirectMessageService
{
    /** The existing DM between these two, or null. */
    public function existing(User $user, int $otherId): ?YardRoom
    {
        if ($otherId === $user->id) {
            return null;
        }

        return YardRoom::where('room_type', RoomType::DirectMessage)
            ->whereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->whereHas('members', fn ($q) => $q->where('user_id', $otherId))
            ->first();
    }

    /** The DM between these two, created if this is their first conversation. */
    public function findOrCreate(User $user, int $otherId): YardRoom
    {
        if ($existing = $this->existing($user, $otherId)) {
            return $existing;
        }

        $target = User::findOrFail($otherId);

        $room = YardRoom::create([
            'tenant_id'      => $user->tenant_id,
            'name'           => ($user->username ?? $user->name) . ' & ' . ($target->username ?? $target->name),
            'slug'           => 'dm-' . Str::uuid()->toString(),
            'country'        => $user->current_country ?? 'Cameroon',
            'room_type'      => RoomType::DirectMessage,
            'created_by'     => $user->id,
            'is_system_room' => false,
            'members_count'  => 2,
        ]);

        foreach ([$user->id, $otherId] as $memberId) {
            YardRoomMember::create([
                'tenant_id' => $user->tenant_id,
                'room_id'   => $room->id,
                'user_id'   => $memberId,
                'role'      => 'member',
            ]);
        }

        return $room;
    }
}
