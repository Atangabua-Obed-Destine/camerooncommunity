<?php

namespace App\Services\Yard;

use App\Enums\MessageType;
use App\Enums\RoomType;
use App\Events\MessageSent;
use App\Models\User;
use App\Models\UserContactName;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Star, pin and forward: the selection bar's actions, for the web's ChatRoom
 * and for /api/v1/yard.
 *
 * The web's forward copied any message id it was given, from any room, into
 * a room of yours: a way to read chats you were never in. Here a message can
 * only be starred, pinned or forwarded by someone who can see it.
 */
class MessageActionsService
{
    /** What can be forwarded: a copy of these stands on its own. */
    public const FORWARDABLE = ['text', 'image', 'video', 'audio', 'file', 'gif'];

    public function __construct(private YardMessageService $messages) {}

    /**
     * The messages, of these ids, that this member can see.
     *
     * @param  int[]  $ids
     * @return Collection<int, YardMessage>
     */
    public function seeable(User $user, array $ids): Collection
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->filter()->unique()->take(100)->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return YardMessage::whereIn('id', $ids)->where('is_deleted', false)->get()
            ->groupBy('room_id')
            ->flatMap(function (Collection $group, $roomId) use ($user) {
                $room = YardRoom::find($roomId);
                if (! $room || ! $this->messages->membership($user, $room)) {
                    return [];
                }

                return $this->messages->visible($user, $room)->whereIn('id', $group->pluck('id'))->get();
            })
            ->sortBy('id')
            ->values();
    }

    /**
     * Stars a selection, or clears the stars when every one already has one
     * (WhatsApp's rule: a mixed selection ends up all starred).
     *
     * @param  int[]  $ids
     * @return array{starred: bool, ids: int[]}
     */
    public function star(User $user, array $ids): array
    {
        $ids = $this->seeable($user, $ids)->pluck('id')->all();
        if (! $ids) {
            return ['starred' => false, 'ids' => []];
        }

        $already = DB::table('yard_message_stars')->where('user_id', $user->id)->whereIn('message_id', $ids)->pluck('message_id')->all();
        if (count($already) === count($ids)) {
            DB::table('yard_message_stars')->where('user_id', $user->id)->whereIn('message_id', $ids)->delete();

            return ['starred' => false, 'ids' => $ids];
        }

        $now = now();
        DB::table('yard_message_stars')->insert(array_map(
            fn ($id) => ['user_id' => $user->id, 'message_id' => $id, 'created_at' => $now],
            array_values(array_diff($ids, $already)),
        ));

        return ['starred' => true, 'ids' => $ids];
    }

    /** Pins or unpins for the whole room. Null when the member can't. */
    public function togglePin(User $user, YardMessage $message): ?bool
    {
        if ($this->seeable($user, [$message->id])->isEmpty()) {
            return null;
        }

        $on = ! $message->is_pinned;
        $message->update([
            'is_pinned' => $on,
            'pinned_at' => $on ? now() : null,
            'pinned_by' => $on ? $user->id : null,
        ]);
        // Everyone else refetches the message, and with it the pin.
        $this->broadcast(new MessageSent($message->fresh()));

        return $on;
    }

    /**
     * Rooms this member can forward into: active (not archived) memberships,
     * newest conversation first, DMs named after the other person.
     */
    public function targets(User $user, ?int $exceptRoomId = null): Collection
    {
        $roomIds = YardRoomMember::where('user_id', $user->id)
            ->whereNull('archived_at')
            ->whereNull('auto_archived_at')
            ->pluck('room_id');

        $rooms = YardRoom::whereIn('id', $roomIds)
            ->when($exceptRoomId, fn ($q) => $q->where('id', '!=', $exceptRoomId))
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get(['id', 'tenant_id', 'name', 'room_type', 'avatar', 'origin']);

        $dmIds = $rooms->where('room_type', RoomType::DirectMessage)->pluck('id');
        if ($dmIds->isNotEmpty()) {
            $others = YardRoomMember::whereIn('room_id', $dmIds)->where('user_id', '!=', $user->id)
                ->with('user:id,name,username,avatar')->get()->keyBy('room_id');
            $nicknames = UserContactName::nicknamesFor($user->id, $others->pluck('user_id')->all());
            foreach ($rooms as $room) {
                $other = $others[$room->id]->user ?? null;
                if ($other) {
                    $room->dm_other_id = $other->id;
                    $room->dm_other_name = $nicknames[$other->id] ?? ($other->username ?? $other->name);
                    $room->dm_other_avatar = $other->avatar;
                }
            }
        }

        return $rooms;
    }

    /**
     * Copies the messages into the target room, oldest first.
     *
     * @param  int[]  $ids
     * @return int|string how many were forwarded, or why not: 'not_member',
     *                    'not_connected', 'blocked', 'nothing'
     */
    public function forward(User $user, array $ids, YardRoom $target): int|string
    {
        $member = YardRoomMember::where('room_id', $target->id)->where('user_id', $user->id)
            ->whereNull('archived_at')->whereNull('auto_archived_at')->exists();
        if (! $member) {
            return 'not_member';
        }
        if ($why = $this->messages->cannotSend($user, $target)) {
            return $why;
        }
        if ($target->room_type === RoomType::DirectMessage) {
            $partner = (int) YardRoomMember::where('room_id', $target->id)->where('user_id', '!=', $user->id)->value('user_id');
            if ($partner && in_array($partner, $user->blockedOrBlockingUserIds(), true)) {
                return 'blocked';
            }
        }

        $sources = $this->seeable($user, $ids)
            ->filter(fn (YardMessage $m) => in_array($m->message_type->value, self::FORWARDABLE, true) && ($m->content || $m->media_path));
        if ($sources->isEmpty()) {
            return 'nothing';
        }

        $last = null;
        foreach ($sources as $source) {
            $last = YardMessage::create([
                'tenant_id' => $user->tenant_id,
                'uuid' => Str::uuid()->toString(),
                'room_id' => $target->id,
                'user_id' => $user->id,
                'message_type' => $source->message_type,
                'content' => $source->content,
                'media_path' => $source->media_path,
                'media_original_name' => $source->media_original_name,
                'media_size' => $source->media_size,
                'media_metadata' => $source->message_type === MessageType::Audio ? $source->media_metadata : null,
                'is_forwarded' => true,
            ]);
            $this->broadcast(new MessageSent($last));
        }

        $preview = $last->content ?: match ($last->message_type) {
            MessageType::Audio => '🎤 Voice note',
            MessageType::Image => '📷 Image',
            MessageType::File => '📎 '.($last->media_original_name ?? 'File'),
            default => 'Forwarded',
        };
        $target->update([
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($preview, 100),
            'last_message_user_id' => $user->id,
            'messages_count' => $target->messages_count + $sources->count(),
        ]);

        return $sources->count();
    }

    private function broadcast(object $event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Broadcast failed: '.$e->getMessage());
        }
    }
}
