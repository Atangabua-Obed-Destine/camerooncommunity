<?php

namespace App\Services\Yard;

use App\Enums\RoomType;
use App\Models\User;
use App\Models\UserConnection;
use App\Models\UserContactName;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Support\AvatarPalette;
use App\Support\StorageUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Who a member can find and how they stand with each other: the lists in
 * the Connections sheet, the new-chat search, and the profile preview card.
 *
 * One place for the web (YardController, Livewire Connections) and the app
 * (Api\V1\Yard\PeopleController), so the two never disagree about a state.
 *
 * A connection state is always the viewer's side of it:
 * self · none · connected · outgoing · incoming · blocked-by-me · blocked-by-them.
 */
class PeopleService
{
    /** The columns every person row needs. */
    private const ROW = ['id', 'name', 'username', 'avatar', 'current_country', 'current_region'];

    public function state(User $viewer, int $otherId): string
    {
        if ($otherId === $viewer->id) {
            return 'self';
        }

        return self::stateOf(UserConnection::between($viewer->id, $otherId), $viewer->id);
    }

    /**
     * States for many people at once (one query).
     *
     * @param  array<int, int>  $ids
     * @return array<int, string> keyed by user id; people with no row are 'none'
     */
    public function states(User $viewer, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }

        $rows = UserConnection::where(function ($q) use ($viewer, $ids) {
            $q->where(fn ($qq) => $qq->where('user_a_id', $viewer->id)->whereIn('user_b_id', $ids))
                ->orWhere(fn ($qq) => $qq->where('user_b_id', $viewer->id)->whereIn('user_a_id', $ids));
        })->get(['user_a_id', 'user_b_id', 'status', 'requested_by']);

        $out = array_fill_keys($ids, 'none');
        foreach ($rows as $c) {
            $other = $c->user_a_id === $viewer->id ? $c->user_b_id : $c->user_a_id;
            $out[$other] = self::stateOf($c, $viewer->id);
        }
        if (isset($out[$viewer->id])) {
            $out[$viewer->id] = 'self';
        }

        return $out;
    }

    public static function stateOf(?UserConnection $c, int $viewerId): string
    {
        if (! $c) {
            return 'none';
        }

        return match ($c->status) {
            UserConnection::STATUS_ACCEPTED => 'connected',
            UserConnection::STATUS_PENDING => $c->requested_by === $viewerId ? 'outgoing' : 'incoming',
            UserConnection::STATUS_BLOCKED => $c->requested_by === $viewerId ? 'blocked-by-me' : 'blocked-by-them',
            default => 'none',
        };
    }

    // ── Lists (the Connections sheet) ────────────────────────────────────

    /** Accepted connections, most recently accepted first. */
    public function connections(User $user): Collection
    {
        $rows = UserConnection::where('status', UserConnection::STATUS_ACCEPTED)
            ->where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id))
            ->orderByDesc('accepted_at')
            ->get();

        return $this->usersInOrder($rows->map(fn ($c) => $c->otherUserId($user->id))->all());
    }

    /** Requests waiting on this member, newest first. */
    public function incoming(User $user): Collection
    {
        $rows = UserConnection::where('status', UserConnection::STATUS_PENDING)
            ->where('requested_by', '!=', $user->id)
            ->where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id))
            ->latest()
            ->get();

        return $this->usersInOrder($rows->pluck('requested_by')->all());
    }

    /** Requests this member sent that are still unanswered, newest first. */
    public function sent(User $user): Collection
    {
        $rows = UserConnection::where('status', UserConnection::STATUS_PENDING)
            ->where('requested_by', $user->id)
            ->latest()
            ->get();

        return $this->usersInOrder($rows->map(fn ($c) => $c->otherUserId($user->id))->all());
    }

    /**
     * People this member blocked. Never those who blocked them: that would
     * tell them, and the unblock isn't theirs to make.
     */
    public function blocked(User $user): Collection
    {
        $rows = UserConnection::where('status', UserConnection::STATUS_BLOCKED)
            ->where('requested_by', $user->id)
            ->where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id))
            ->latest()
            ->get();

        return $this->usersInOrder($rows->map(fn ($c) => $c->otherUserId($user->id))->all());
    }

    /** People sharing a room with this member, not yet related in any way. */
    public function suggestions(User $user, int $limit = 30): Collection
    {
        $myRoomIds = YardRoomMember::where('user_id', $user->id)->pluck('room_id');
        if ($myRoomIds->isEmpty()) {
            return collect();
        }

        $excluded = $this->relatedIds($user);
        $excluded[] = $user->id;

        return User::query()
            ->discoverable()
            ->whereIn('id', fn ($q) => $q->select('user_id')->from('yard_room_members')->whereIn('room_id', $myRoomIds))
            ->whereNotIn('id', $excluded)
            ->limit($limit)
            ->get(self::ROW);
    }

    /**
     * The Connections sheet's search: two characters or more, by name or
     * username, never self, never anyone either side has blocked.
     */
    public function searchPeople(User $user, string $term, int $limit = 30): Collection
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        $blocked = UserConnection::where('status', UserConnection::STATUS_BLOCKED)
            ->where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id))
            ->get(['user_a_id', 'user_b_id'])
            ->map(fn ($c) => $c->user_a_id === $user->id ? $c->user_b_id : $c->user_a_id)
            ->all();
        $blocked[] = $user->id;

        $like = '%'.self::escapeLike($term).'%';

        return User::query()
            ->discoverable()
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('username', 'like', $like))
            ->whereNotIn('id', $blocked)
            ->limit($limit)
            ->get(self::ROW);
    }

    /**
     * The new-chat search (yard/index's "New conversation"): name, username
     * or the exact e-mail address, everyone but self; blocked people come
     * back too and show as unavailable.
     *
     * An address is matched whole, never by fragment: "gmail" must not list
     * every member with a Gmail account.
     */
    public function searchForChat(User $user, string $term, int $limit = 20): Collection
    {
        $term = trim($term);
        if ($term === '') {
            return collect();
        }
        $like = '%'.self::escapeLike($term).'%';

        return User::where('tenant_id', $user->tenant_id)
            ->discoverable()
            ->where('id', '!=', $user->id)
            ->where(function ($q) use ($like, $term) {
                $q->where('name', 'like', $like)->orWhere('username', 'like', $like);
                if (str_contains($term, '@') && filter_var($term, FILTER_VALIDATE_EMAIL)) {
                    $q->orWhere('email', $term);
                }
            })
            ->limit($limit)
            ->get(self::ROW);
    }

    /** Ids of everyone this member has any connection row with. */
    private function relatedIds(User $user): array
    {
        return UserConnection::where(fn ($q) => $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id))
            ->get(['user_a_id', 'user_b_id'])
            ->map(fn ($c) => $c->user_a_id === $user->id ? $c->user_b_id : $c->user_a_id)
            ->all();
    }

    private function usersInOrder(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }
        $users = User::whereIn('id', $ids)->get(self::ROW)->keyBy('id');

        return collect($ids)->map(fn ($id) => $users->get($id))->filter()->values();
    }

    private static function escapeLike(string $s): string
    {
        return addcslashes($s, '%_\\');
    }

    // ── The preview card ─────────────────────────────────────────────────

    /**
     * Everything the profile preview card needs, by id or username (an
     * @mention carries a username). Null when there is no such member.
     */
    public function preview(User $viewer, string $identifier): ?array
    {
        $other = ctype_digit($identifier)
            ? User::discoverable()->find((int) $identifier)
            : User::discoverable()->where('username', ltrim($identifier, '@'))->first();

        if (! $other) {
            return null;
        }

        $state = $this->state($viewer, $other->id);
        $isSelf = $other->id === $viewer->id;
        $blocked = in_array($state, ['blocked-by-me', 'blocked-by-them'], true);

        // Country only: a person's town is not shown to other people.
        $location = trim((string) config("cameroon.countries.{$other->current_country}", $other->current_country));

        // Message opens the conversation that already exists.
        $dmRoom = $isSelf ? null : $this->existingDm($viewer->id, $other->id);

        return [
            'id' => $other->id,
            'name' => $viewer->displayNameFor($other),   // a saved nickname wins
            'username' => $other->username,
            'avatar' => $other->avatar ? StorageUrl::for($other->avatar) : null,
            'avatar_bg' => AvatarPalette::colorClass('user:'.$other->id),
            // Withheld when either side has blocked: the card shows only the notice.
            'bio' => $blocked ? null : $other->bio,
            'location' => $blocked ? null : ($location ?: null),
            'nickname' => $isSelf ? null : $viewer->nicknameFor($other->id),
            'state' => $state,
            'is_self' => $isSelf,
            'dm_room_id' => $dmRoom?->id,
            'dm_room_slug' => $dmRoom?->slug,
            'profile_url' => $other->profileUrl(),
        ];
    }

    public function existingDm(int $a, int $b): ?YardRoom
    {
        return YardRoom::where('room_type', RoomType::DirectMessage)
            ->whereHas('members', fn ($q) => $q->where('user_id', $a))
            ->whereHas('members', fn ($q) => $q->where('user_id', $b))
            ->first(['id', 'slug']);
    }

    /**
     * Save (or, with an empty name, clear) what the viewer calls another
     * member. Returns the saved nickname, or null when cleared.
     */
    public function saveNickname(User $viewer, int $otherId, ?string $nickname): ?string
    {
        $nickname = trim((string) $nickname);

        if ($nickname === '') {
            UserContactName::where('owner_user_id', $viewer->id)->where('contact_user_id', $otherId)->delete();
            Cache::driver('array')->forget("ucn.{$viewer->id}.{$otherId}");

            return null;
        }

        UserContactName::updateOrCreate(
            ['owner_user_id' => $viewer->id, 'contact_user_id' => $otherId],
            ['tenant_id' => $viewer->tenant_id, 'nickname' => $nickname],
        );
        Cache::driver('array')->forget("ucn.{$viewer->id}.{$otherId}");

        return $nickname;
    }
}
