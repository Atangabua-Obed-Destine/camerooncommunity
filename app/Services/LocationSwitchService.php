<?php

namespace App\Services;

use App\Enums\NotificationPref;
use App\Enums\RoomType;
use App\Models\User;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Handles "active location" switching — when a user travels (physically or
 * via VPN) to a new country/region, this service:
 *
 *   1. Updates `users.active_country` / `users.active_region`.
 *   2. Auto-archives default (system) rooms tied to the old location.
 *   3. Auto-joins the new country's national room (and ensures the new
 *      region's regional room exists).
 *   4. Silently un-archives matching rooms when the user comes back.
 *
 * Rules:
 *   - Country switch  → archives ALL default rooms (national + regional)
 *                       belonging to OTHER countries.
 *   - Region switch (same country) → archives ONLY default *regional* rooms
 *                       in the same country whose region differs.
 *                       The country room stays active.
 *   - User-created rooms (is_system_room = false) — DMs, private groups,
 *     friend chats — are never auto-archived.
 */
class LocationSwitchService
{
    /**
     * Apply a confirmed location switch.
     *
     * @return array{archived:int,restored:int,joined_room_id:?int}
     */
    public function switchTo(User $user, string $newCountry, ?string $newRegion = null): array
    {
        $newCountry = trim($newCountry);
        $newRegion = $newRegion ? trim($newRegion) : null;

        $oldCountry = $user->active_country ?: $user->current_country;
        $oldRegion = $user->active_region ?: $user->current_region;

        return DB::transaction(function () use ($user, $newCountry, $newRegion, $oldCountry, $oldRegion) {
            $user->forceFill([
                'active_country' => $newCountry,
                'active_region' => $newRegion,
            ])->saveQuietly();

            $countryChanged = $oldCountry !== $newCountry;

            // 1. Archive what no longer applies
            $archived = $this->archiveAwayRooms($user, $newCountry, $newRegion);

            // 2. Restore anything matching the new location (in case they
            //    travelled here before and rooms are still archived).
            $restored = $this->restoreMatchingRooms($user, $newCountry, $newRegion);

            // 3. Auto-join the new country's national room (per product spec:
            //    auto-join country room, region room is opt-in via "Suggested").
            $joinedRoomId = null;
            if ($countryChanged) {
                $joinedRoomId = $this->ensureNationalMembership($user, $newCountry);
                $this->ensureRegionalRoomExists($user, $newCountry, $newRegion);
            }

            return [
                'archived' => $archived,
                'restored' => $restored,
                'joined_room_id' => $joinedRoomId,
            ];
        });
    }

    /**
     * Silent return — called by LocationTracker when the detected location
     * matches a previous active location. Just unarchives matching rooms,
     * no prompt, no notifications.
     */
    public function silentRestoreOnReturn(User $user): int
    {
        if (! $user->active_country) {
            return 0;
        }

        return $this->restoreMatchingRooms(
            $user,
            $user->active_country,
            $user->active_region,
        );
    }

    /**
     * Archive default (system) rooms that don't match the new location,
     * per the product rules at the top of this file.
     */
    protected function archiveAwayRooms(User $user, string $newCountry, ?string $newRegion): int
    {
        // Pull active default-room memberships
        $memberships = YardRoomMember::with('room')
            ->where('user_id', $user->id)
            ->whereNull('auto_archived_at')
            ->whereHas('room', fn ($q) => $q->where('is_system_room', true))
            ->get();

        $toArchive = $memberships->filter(function (YardRoomMember $m) use ($newCountry, $newRegion) {
            $room = $m->room;
            if (! $room) {
                return false;
            }

            // Country room: archive if its country differs from new active country
            if ($room->room_type === RoomType::National) {
                return $room->country !== $newCountry;
            }

            // Regional room: archive if either country or region differs
            if ($room->room_type === RoomType::Regional) {
                if ($room->country !== $newCountry) {
                    return true;
                }
                // Same country — archive only when region differs and a new region is known
                if ($newRegion && $room->region && $room->region !== $newRegion) {
                    return true;
                }

                return false;
            }

            // Other system room types (city, etc.) — match on country
            return $room->country !== $newCountry;
        });

        $now = now();

        foreach ($toArchive as $m) {
            $m->forceFill([
                'auto_archived_at' => $now,
                'notification_pref_before_archive' => $m->notification_pref?->value
                    ?? $m->getRawOriginal('notification_pref')
                    ?? NotificationPref::All->value,
                'notification_pref' => NotificationPref::None->value,
            ])->save();
        }

        return $toArchive->count();
    }

    /**
     * Restore (unarchive) any auto-archived memberships whose room matches
     * the new active location. Restores their original notification pref.
     */
    protected function restoreMatchingRooms(User $user, string $country, ?string $region): int
    {
        $candidates = YardRoomMember::with('room')
            ->where('user_id', $user->id)
            ->whereNotNull('auto_archived_at')
            ->whereHas('room', fn ($q) => $q->where('is_system_room', true)->where('country', $country))
            ->get();

        $matching = $candidates->filter(function (YardRoomMember $m) use ($region) {
            $room = $m->room;
            if (! $room) {
                return false;
            }
            if ($room->room_type === RoomType::National) {
                return true;
            }
            if ($room->room_type === RoomType::Regional) {
                return ! $region || ! $room->region || $room->region === $region;
            }

            return true;
        });

        foreach ($matching as $m) {
            $original = $m->notification_pref_before_archive ?: NotificationPref::All->value;
            $m->forceFill([
                'auto_archived_at' => null,
                'notification_pref' => $original,
                'notification_pref_before_archive' => null,
            ])->save();
        }

        return $matching->count();
    }

    /**
     * Make sure the user is a member of the new country's national room.
     * If a membership row exists but is archived, unarchive it instead of
     * creating a duplicate.
     */
    protected function ensureNationalMembership(User $user, string $country): int
    {
        $room = YardRoom::firstOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'room_type' => RoomType::National,
                'country' => $country,
            ],
            [
                'name' => RoomNamingService::national($country),
                'slug' => Str::slug(RoomNamingService::shortCountry($country) . '-kamer'),
                'description' => RoomNamingService::nationalDescription($country),
                'is_active' => true,
                'is_system_room' => true,
                'members_count' => 0,
            ],
        );

        $member = YardRoomMember::firstOrNew([
            'tenant_id' => $user->tenant_id,
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        $isNew = ! $member->exists;

        if ($isNew) {
            $member->role = 'member';
            $member->joined_at = now();
        }

        // If they were previously here and archived, lift the archive
        if ($member->auto_archived_at) {
            $member->auto_archived_at = null;
            $member->notification_pref = $member->notification_pref_before_archive ?: NotificationPref::All->value;
            $member->notification_pref_before_archive = null;
        }

        $member->save();

        if ($isNew) {
            $room->increment('members_count');
        }

        return $room->id;
    }

    /**
     * Lazily create the regional room so it shows up under "Suggested" for
     * one-tap join. We do NOT auto-join regional — per product spec.
     */
    protected function ensureRegionalRoomExists(User $user, string $country, ?string $region): void
    {
        if (! $region) {
            return;
        }

        YardRoom::firstOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'room_type' => RoomType::Regional,
                'country' => $country,
                'region' => $region,
            ],
            [
                'name' => RoomNamingService::regional($region),
                'slug' => Str::slug("{$region}-kamer-" . RoomNamingService::shortCountry($country)),
                'description' => RoomNamingService::regionalDescription($region, $country),
                'is_active' => true,
                'is_system_room' => true,
                'members_count' => 0,
            ],
        );
    }

    /**
     * Get the user's currently auto-archived memberships, eager-loaded with
     * their room. Used by the chat list to render the "Archived (away)"
     * section.
     *
     * @return Collection<int, YardRoomMember>
     */
    public function archivedMemberships(User $user): Collection
    {
        return YardRoomMember::with('room')
            ->where('user_id', $user->id)
            ->whereNotNull('auto_archived_at')
            ->get();
    }
}
