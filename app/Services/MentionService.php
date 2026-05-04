<?php

namespace App\Services;

use App\Events\MessageMentioned;
use App\Models\User;
use App\Models\YardMessage;
use App\Models\YardRoomMember;
use Illuminate\Support\Collection;

/**
 * Resolves @mentions inside chat message content and broadcasts
 * MessageMentioned events to each mentioned room member.
 *
 * Strategy:
 *  - Pattern: @username (alphanumeric + underscore, 3-32 chars)
 *  - Only users who are members of the same room are valid mentions
 *  - The sender is never notified of mentioning themselves
 *  - Persists `mentioned_user_ids` on the message for highlight rendering
 */
class MentionService
{
    /** Regex matching `@username` tokens in free text. */
    public const PATTERN = '/(?<![\w@])@([a-zA-Z0-9_]{3,32})/';

    /**
     * Parse @mentions out of a message's content and persist them on
     * the YardMessage row. Returns the IDs of users who were notified.
     *
     * @return array<int, int>
     */
    public function processMessageMentions(YardMessage $message, User $sender): array
    {
        if (!is_string($message->content) || $message->content === '') {
            return [];
        }

        $usernames = $this->extractUsernames($message->content);
        if (empty($usernames)) {
            return [];
        }

        // Resolve usernames → user IDs that are room members (and not the sender).
        $userIds = User::query()
            ->whereIn('username', $usernames)
            ->where('id', '!=', $sender->id)
            ->where('tenant_id', $sender->tenant_id)
            ->pluck('id')
            ->all();

        if (empty($userIds)) {
            return [];
        }

        $memberIds = YardRoomMember::query()
            ->where('room_id', $message->room_id)
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->all();

        if (empty($memberIds)) {
            return [];
        }

        // Persist on the message so the front-end can highlight tokens.
        $message->mentioned_user_ids = array_values(array_map('intval', $memberIds));
        $message->save();

        // Broadcast one event per mentioned user so they get a toast even
        // if their room is muted (mention overrides notification pref).
        $mentioned = User::whereIn('id', $memberIds)->get();
        foreach ($mentioned as $u) {
            try {
                broadcast(new MessageMentioned($message, $u, $sender));
            } catch (\Throwable $e) {
                \Log::warning('Mention broadcast failed: ' . $e->getMessage());
            }
        }

        return $message->mentioned_user_ids;
    }

    /**
     * Pull unique @usernames out of a string in document order.
     *
     * @return array<int, string>
     */
    public function extractUsernames(string $text): array
    {
        if (!preg_match_all(self::PATTERN, $text, $matches)) {
            return [];
        }
        return array_values(array_unique($matches[1]));
    }

    /**
     * Suggest matching room members for an in-progress @mention.
     * Returns minimal fields suitable for an autocomplete dropdown.
     *
     * @return Collection<int, array{id:int,name:string,username:?string,avatar:?string}>
     */
    public function suggest(int $roomId, string $query, int $limit = 8): Collection
    {
        $query = trim($query);

        $base = User::query()
            ->whereIn('id', YardRoomMember::where('room_id', $roomId)->pluck('user_id'))
            ->where('id', '!=', auth()->id())
            ->select('id', 'name', 'username', 'avatar');

        if ($query !== '') {
            $like = '%' . $query . '%';
            $base->where(function ($q) use ($like) {
                $q->where('username', 'like', $like)
                  ->orWhere('name', 'like', $like);
            });
        }

        return $base->orderBy('name')->limit($limit)->get()->map(fn($u) => [
            'id'       => $u->id,
            'name'     => $u->name,
            'username' => $u->username,
            'avatar'   => $u->avatar,
        ]);
    }
}
