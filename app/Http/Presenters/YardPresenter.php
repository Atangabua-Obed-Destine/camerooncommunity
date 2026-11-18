<?php

namespace App\Http\Presenters;

use App\Enums\MessageType;
use App\Enums\RoomType;
use App\Models\User;
use App\Models\UserContactName;
use App\Models\YardMessage;
use App\Models\YardMessageReaction;
use App\Models\YardRoom;
use App\Services\ReceiptService;
use App\Services\ShareableResolver;
use App\Support\StorageUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * GoConnect as JSON for the app, shaped for one viewer: their nicknames for
 * people, their own reactions, their ticks. Built a whole page at a time, so a
 * screen of messages costs a handful of queries, not a few per message.
 *
 * Every URL is absolute (the app has no asset()).
 */
class YardPresenter
{
    /** The type emoji the web shows when a room has no picture. */
    public const ROOM_EMOJI = [
        'national' => '🇨🇲',
        'regional' => '🗺️',
        'city' => '📍',
        'private_group' => '👥',
        'direct_message' => '💬',
    ];

    public function __construct(
        private ReceiptService $receipts,
        private ShareableResolver $shareables,
    ) {}

    /** A chat-list row (from RoomListService::rooms). */
    public function room(YardRoom $room, User $viewer): array
    {
        $isDm = $room->room_type === RoomType::DirectMessage;
        $mine = $room->last_message_user_id === $viewer->id;

        return [
            'id' => $room->id,
            'type' => $room->room_type->value,
            'is_dm' => $isDm,
            'origin' => $room->origin,
            'name' => $isDm && $room->dm_other_name ? $room->dm_other_name : $room->name,
            'avatar_url' => StorageUrl::for($isDm ? $room->dm_other_avatar : $room->avatar),
            'emoji' => self::ROOM_EMOJI[$room->room_type->value] ?? '💬',
            // The avatar gradient's seed, as the web's AvatarPalette uses it.
            'avatar_seed' => $isDm ? 'user:'.($room->dm_other_id ?? $room->dm_other_name ?? $room->id) : 'room:'.$room->id,
            'dm_user_id' => $isDm && $room->dm_other_id ? (int) $room->dm_other_id : null,
            'members_count' => (int) ($room->members_count ?? 0),
            'last_message' => $room->last_message_at ? [
                'at' => $room->last_message_at->toIso8601String(),
                // Carbon's short relative form in the member's language, as the web prints it.
                'ago' => $room->last_message_at->shortRelativeDiffForHumans(),
                'preview' => $room->last_message_preview,
                'is_mine' => $mine,
                'sender' => $mine ? null : ($room->last_message_sender_name ? explode(' ', $room->last_message_sender_name)[0] : null),
            ] : null,
            'unread_count' => (int) ($room->unread_count ?? 0),
            'unread_mentions' => (int) ($room->unread_mention_count ?? 0),
            'is_favorited' => (bool) ($room->is_favorited ?? false),
            'is_archived' => ! empty($room->member_archived_at),
            // The list listens on every room it shows, as RoomList does.
            'channel' => "tenant.{$room->tenant_id}.room.{$room->id}",
        ];
    }

    /** A community room the member could join (RoomListService::suggested). */
    public function suggestion(YardRoom $room): array
    {
        return [
            'id' => $room->id,
            'type' => $room->room_type->value,
            'name' => $room->name,
            'description' => $room->description,
            'emoji' => self::ROOM_EMOJI[$room->room_type->value] ?? '💬',
            'avatar_url' => StorageUrl::for($room->avatar),
            'avatar_seed' => 'room:'.$room->id,
            'members_count' => (int) ($room->members_count ?? 0),
        ];
    }

    /**
     * @param  Collection<int, YardMessage>  $messages
     * @return array<int, array>
     */
    public function messages(Collection $messages, User $viewer, YardRoom $room): array
    {
        if ($messages->isEmpty()) {
            return [];
        }

        $ids = $messages->pluck('id')->all();
        $own = $messages->where('user_id', $viewer->id)->all();
        $statuses = $this->receipts->bulkStatusFor($own, $room->id);

        $myReactions = YardMessageReaction::whereIn('message_id', $ids)->where('user_id', $viewer->id)
            ->get(['message_id', 'emoji'])->groupBy('message_id')
            ->map(fn ($rows) => $rows->pluck('emoji')->all());

        $people = $messages->pluck('user_id')->merge($messages->pluck('parent.user_id'))->filter()->unique()->values()->all();
        $nicknames = UserContactName::nicknamesFor($viewer->id, $people);

        $pollIds = $messages->pluck('poll.id')->filter()->all();
        $myVotes = $pollIds ? DB::table('yard_poll_votes')->whereIn('poll_id', $pollIds)->where('user_id', $viewer->id)
            ->get(['poll_id', 'option_id'])->groupBy('poll_id')->map(fn ($r) => $r->pluck('option_id')->all()) : collect();

        $translateTo = $room->members()->where('user_id', $viewer->id)->value('auto_translate_lang');

        // The viewer's own stars (the selection bar's Star toggles them).
        $stars = DB::table('yard_message_stars')->where('user_id', $viewer->id)->whereIn('message_id', $ids)->pluck('message_id')->flip();

        return $messages->map(fn (YardMessage $m) => $this->message(
            $m, $viewer, $statuses[$m->id] ?? 'sent', $myReactions[$m->id] ?? [], $nicknames, $myVotes[$m->poll?->id] ?? [], $translateTo,
        ) + ['starred' => isset($stars[$m->id])])->values()->all();
    }

    private function message(YardMessage $m, User $viewer, string $status, array $myReactions, array $nicknames, array $myVotes, ?string $translateTo): array
    {
        $mine = $m->user_id === $viewer->id;
        $type = $m->message_type;
        $meta = is_array($m->media_metadata) ? $m->media_metadata : [];

        // A cached translation, when the member turned auto-translate on (never their own).
        $content = $m->content;
        $translated = false;
        if (! $mine && $translateTo && is_string($content) && $content !== '') {
            $cached = is_array($m->translated_content) ? ($m->translated_content[$translateTo] ?? null) : null;
            if ($cached && $cached !== $content) {
                $content = $cached;
                $translated = true;
            }
        }

        $reactions = [];
        foreach ((array) ($m->reactions_count ?? []) as $emoji => $count) {
            $reactions[] = ['emoji' => (string) $emoji, 'count' => (int) $count, 'mine' => in_array($emoji, $myReactions, true)];
        }

        $name = fn (?User $u) => $u ? ($nicknames[$u->id] ?? $u->name) : null;

        return [
            'id' => $m->id,
            'uuid' => $m->uuid,
            'room_id' => $m->room_id,
            'type' => $type->value,
            'content' => $m->is_deleted ? null : $content,
            'translated' => $translated,
            'mine' => $mine,
            'created_at' => $m->created_at?->toIso8601String(),
            'is_deleted' => (bool) $m->is_deleted,
            'is_edited' => (bool) $m->is_edited,
            'is_forwarded' => (bool) $m->is_forwarded,
            'is_pinned' => (bool) $m->is_pinned,
            'mentions_me' => is_array($m->mentioned_user_ids) && in_array($viewer->id, $m->mentioned_user_ids),
            'status' => $mine ? $status : null,
            'user' => $m->user ? [
                'id' => $m->user->id,
                'name' => $name($m->user),
                'username' => $m->user->username,
                'avatar_url' => StorageUrl::for($m->user->avatar),
            ] : null,
            'parent' => $m->parent ? [
                'id' => $m->parent->id,
                'name' => $m->parent->user?->username ?? $m->parent->user?->name,
                'content' => $m->parent->is_deleted ? null : Str::limit((string) $m->parent->content, 60),
                'type' => $m->parent->message_type?->value,
            ] : null,
            'media' => $m->media_path && ! $m->is_deleted ? [
                'url' => StorageUrl::for($m->media_path),
                'name' => $m->media_original_name,
                'size' => $m->media_size ? (int) $m->media_size : null,
                // A voice note's seconds, when its sender's recorder knew them.
                'duration' => isset($meta['duration']) && is_numeric($meta['duration']) ? (float) $meta['duration'] : null,
            ] : null,
            'reactions' => $reactions,
            'system' => $type === MessageType::System ? [
                'kind' => $meta['kind'] ?? null,
                // "X joined" and "X requested to join" open that person's card.
                'person_id' => in_array($meta['kind'] ?? null, ['member_joined', 'join_request'], true)
                    ? (int) ($meta['user_id'] ?? $meta['requester_id'] ?? $m->user_id) : null,
            ] : null,
            'call' => $type === MessageType::CallLog ? (json_decode((string) $m->content, true) ?: []) : null,
            'poll' => $type === MessageType::Poll && $m->poll ? $this->poll($m, $myVotes) : null,
            'share' => $type === MessageType::ShareCard ? $this->share($m) : null,
        ];
    }

    private function poll(YardMessage $m, array $myVotes): array
    {
        $poll = $m->poll;
        $total = (int) $poll->options->sum('votes_count');

        return [
            'id' => $poll->id,
            'question' => $poll->question,
            'allow_multiple' => (bool) $poll->allow_multiple,
            'is_closed' => (bool) $poll->is_closed,
            'is_mine' => $poll->user_id === $m->user_id,
            'total_votes' => $total,
            'options' => $poll->options->map(fn ($o) => [
                'id' => $o->id,
                'text' => $o->text,
                'votes' => (int) $o->votes_count,
                'percent' => $total > 0 ? (int) round($o->votes_count / $total * 100) : 0,
                'voted' => in_array($o->id, $myVotes),
            ])->values()->all(),
        ];
    }

    private function share(YardMessage $m): ?array
    {
        if (! $m->shareable_type || ! $m->shareable_id) {
            return null;
        }

        // Messages store the model class; the resolver is keyed by kind.
        $type = (string) $m->shareable_type;
        $kind = array_search($type, ShareableResolver::TYPES, true) ?: $type;
        $model = $this->shareables->resolve($kind, (int) $m->shareable_id);
        $preview = $this->shareables->preview($model);
        if (! $preview) {
            return null;
        }

        return [
            'kind' => $preview['kind'],
            'id' => $preview['id'],
            'title' => $preview['title'],
            'subtitle' => $preview['subtitle'] ?? null,
            // Solidarity stores a disk path, the marketplace a full URL.
            'image' => StorageUrl::for($preview['image'] ?? null),
            'price_label' => $preview['price_label'] ?? null,
            'cta' => $preview['cta'] ?? __('Open'),
            // The app opens a listing by its slug, not the web's URL.
            'slug' => $preview['kind'] === 'marketplace' ? $model?->slug : null,
        ];
    }
}
