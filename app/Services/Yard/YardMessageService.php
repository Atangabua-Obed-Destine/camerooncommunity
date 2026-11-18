<?php

namespace App\Services\Yard;

use App\Enums\MessageType;
use App\Enums\RoomType;
use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\YardMessage;
use App\Models\YardMessageReaction;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Services\AIService;
use App\Services\MentionService;
use App\Services\ReceiptService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Chat messages: who may see which, and every write. Called by the web's
 * ChatRoom component and by /api/v1/yard, so a message sent from a phone is
 * the same row, with the same side effects, as one sent from a browser.
 *
 * Every write checks membership. The web's react, star and delete had no
 * such check (a crafted call could react in a room you weren't in); going
 * through here closes that for both clients.
 */
class YardMessageService
{
    public const MEDIA_RULES = [
        'image' => 'required|file|mimes:jpeg,jpg,png,webp,gif|max:10240',
        'document' => 'required|file|mimes:pdf,doc,docx,xlsx,pptx,txt,csv,zip|max:20480',
        'audio' => 'required|file|mimes:webm,ogg,mp3,wav,m4a,mp4,aac|max:25600',
    ];

    public function __construct(
        private ReceiptService $receipts,
        private MentionService $mentions,
    ) {}

    /** The member's row, or null. A location-locked membership doesn't count. */
    public function membership(User $user, YardRoom $room): ?YardRoomMember
    {
        return YardRoomMember::where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->whereNull('auto_archived_at')
            ->first();
    }

    /**
     * Messages this member may see in this room: nothing from before they
     * joined (DMs excepted), nothing by anyone they've blocked or who blocked
     * them, and admin-only system lines only for the room's creator.
     */
    public function visible(User $user, YardRoom $room): Builder
    {
        $q = YardMessage::where('room_id', $room->id);

        if ($room->room_type !== RoomType::DirectMessage) {
            $joinedAt = YardRoomMember::where('room_id', $room->id)->where('user_id', $user->id)->value('joined_at');
            if ($joinedAt) {
                $q->where('created_at', '>=', $joinedAt);
            }
        }

        $blocked = $user->blockedOrBlockingUserIds();
        if (! empty($blocked)) {
            $q->whereNotIn('user_id', $blocked);
        }

        if ($room->created_by !== $user->id) {
            $visibility = DB::getDriverName() === 'sqlite'
                ? "JSON_EXTRACT(media_metadata, '$.visibility')"
                : "JSON_UNQUOTE(JSON_EXTRACT(media_metadata, '$.visibility'))";
            $q->where(function ($w) use ($visibility) {
                $w->where('message_type', '!=', MessageType::System->value)
                    ->orWhereNull('media_metadata')
                    ->orWhereRaw("JSON_EXTRACT(media_metadata, '$.visibility') IS NULL")
                    ->orWhereRaw("$visibility <> 'admins'");
            });
        }

        return $q;
    }

    /**
     * A page of messages, oldest first. `before` is a message id: the page is
     * the `limit` messages older than it. A cursor rather than the web's
     * growing limit, so a phone fetches only what it hasn't got.
     *
     * @return \Illuminate\Support\Collection<int, YardMessage>
     */
    public function page(User $user, YardRoom $room, ?int $beforeId = null, int $limit = 30)
    {
        $q = $this->visible($user, $room)
            ->with(['user:id,name,username,avatar', 'parent:id,content,user_id,message_type,is_deleted', 'parent.user:id,name,username', 'poll.options']);

        if ($beforeId) {
            $q->where('id', '<', $beforeId);
        }

        return $q->orderByDesc('id')->limit(max(1, min($limit, 100)))->get()->reverse()->values();
    }

    /** The first message from someone else since the member last read the room. */
    public function firstUnreadId(User $user, YardRoom $room): ?int
    {
        $lastRead = YardRoomMember::where('room_id', $room->id)->where('user_id', $user->id)->value('last_read_at');
        if (! $lastRead) {
            return null;
        }

        return $this->visible($user, $room)
            ->where('user_id', '!=', $user->id)
            ->where('created_at', '>', $lastRead)
            ->orderBy('id')
            ->value('id');
    }

    /**
     * Why this member can't post here, or null when they can. A DM needs an
     * accepted connection, except a marketplace chat (a buyer may message a
     * seller about a listing, as on Facebook Marketplace).
     */
    public function cannotSend(User $user, YardRoom $room): ?string
    {
        if (! $this->membership($user, $room)) {
            return 'not_member';
        }

        if ($room->room_type === RoomType::DirectMessage && $room->origin !== 'marketplace') {
            $partner = YardRoomMember::where('room_id', $room->id)->where('user_id', '!=', $user->id)->value('user_id');
            if ($partner && ! $user->isConnectedWith((int) $partner)) {
                return 'not_connected';
            }
        }

        return null;
    }

    public function sendText(User $user, YardRoom $room, string $content, ?int $parentId = null): YardMessage
    {
        $message = YardMessage::create([
            'tenant_id' => $user->tenant_id,
            'uuid' => Str::uuid()->toString(),
            'room_id' => $room->id,
            'user_id' => $user->id,
            'parent_message_id' => $this->validParent($room, $parentId),
            'message_type' => MessageType::Text,
            'content' => $content,
        ]);

        $this->mentions->processMessageMentions($message, $user);
        $this->touchRoom($room, $user, $content);
        $this->broadcast(new MessageSent($message));
        $this->moderate($message);

        return $message;
    }

    /**
     * A photo, document or voice note, stored where the web stores them
     * (public disk, yard/media/{room}). `type` is image, document or audio;
     * `duration` is a voice note's length in seconds, when the client knows it.
     */
    public function sendMedia(User $user, YardRoom $room, UploadedFile $file, string $type, ?string $caption = null, ?int $parentId = null, ?float $duration = null): YardMessage
    {
        $messageType = match ($type) {
            'image' => MessageType::Image,
            'audio' => MessageType::Audio,
            default => MessageType::File,
        };
        $caption = trim((string) $caption) ?: null;

        $message = YardMessage::create([
            'tenant_id' => $user->tenant_id,
            'uuid' => Str::uuid()->toString(),
            'room_id' => $room->id,
            'user_id' => $user->id,
            'parent_message_id' => $this->validParent($room, $parentId),
            'message_type' => $messageType,
            'content' => $caption,
            'media_path' => $file->store('yard/media/'.$room->id, 'public'),
            'media_original_name' => $file->getClientOriginalName(),
            'media_size' => $file->getSize(),
            // A voice note's length, as the recorder knew it: the app shows it
            // before the file is fetched (webm from a browser has none to read).
            'media_metadata' => $type === 'audio' && $duration > 0 ? ['duration' => round($duration, 1)] : null,
        ]);

        $preview = match ($type) {
            'image' => '📷 Photo'.($caption ? ': '.Str::limit($caption, 60) : ''),
            'audio' => '🎤 Voice note',
            default => '📄 '.$file->getClientOriginalName(),
        };

        $this->touchRoom($room, $user, $preview);
        $this->broadcast(new MessageSent($message));
        if ($caption) {
            $this->moderate($message);
        }

        return $message;
    }

    /** Edit your own, undeleted text message. */
    public function edit(User $user, YardMessage $message, string $content): bool
    {
        if ($message->user_id !== $user->id || $message->is_deleted || $message->message_type !== MessageType::Text) {
            return false;
        }

        $message->update(['content' => $content, 'is_edited' => true, 'edited_at' => now()]);
        $this->broadcast(new MessageSent($message));

        return true;
    }

    /** Delete your own message, for everyone (the web has no delete-for-me). */
    public function delete(User $user, YardMessage $message): bool
    {
        if ($message->user_id !== $user->id || $message->is_deleted) {
            return false;
        }

        $message->update(['is_deleted' => true, 'content' => null]);
        $this->broadcast(new MessageDeleted($message));

        return true;
    }

    /**
     * Toggle one emoji reaction. Returns the message's counts, emoji => n.
     *
     * @return array<string, int>|null null when the member can't react here
     */
    public function toggleReaction(User $user, YardMessage $message, string $emoji): ?array
    {
        if (! $message->room || ! $this->membership($user, $message->room) || $message->is_deleted) {
            return null;
        }

        $emoji = mb_substr($emoji, 0, 10);
        $existing = YardMessageReaction::where('message_id', $message->id)->where('user_id', $user->id)->where('emoji', $emoji)->first();
        if ($existing) {
            $existing->delete();
        } else {
            YardMessageReaction::create(['tenant_id' => $user->tenant_id, 'message_id' => $message->id, 'user_id' => $user->id, 'emoji' => $emoji]);
        }

        $counts = YardMessageReaction::where('message_id', $message->id)
            ->selectRaw('emoji, count(*) as cnt')->groupBy('emoji')->pluck('cnt', 'emoji')
            ->map(fn ($n) => (int) $n)->all();
        $message->update(['reactions_count' => $counts ?: null]);
        $this->broadcast(new MessageSent($message->fresh()));

        return $counts;
    }

    /**
     * The member has read the room: last_read_at moves on, per-message read
     * receipts are written and the senders' ticks turn blue. Skipped when
     * nothing arrived since the last read. True when something was marked.
     */
    public function markRead(User $user, YardRoom $room): bool
    {
        $member = YardRoomMember::where('room_id', $room->id)->where('user_id', $user->id)->first(['id', 'last_read_at']);
        if (! $member) {
            return false;
        }
        if ($room->last_message_at && $member->last_read_at && $member->last_read_at->gte($room->last_message_at)) {
            return false;
        }

        YardRoomMember::whereKey($member->id)->update(['last_read_at' => now()]);
        $this->receipts->markRoomRead($room, $user->id);

        return true;
    }

    /** The message reached this member's device: the sender's second tick. */
    public function markDelivered(User $user, YardMessage $message): void
    {
        if ($message->user_id !== $user->id && $this->membership($user, $message->room)) {
            $this->receipts->markDelivered($message, $user->id);
        }
    }

    /** "… is typing", at most once per two seconds per member per room. */
    public function typing(User $user, YardRoom $room): void
    {
        if (! Cache::add("yard:typing:{$room->id}:{$user->id}", 1, now()->addSeconds(2))) {
            return;
        }
        $this->broadcast(new UserTyping(roomId: $room->id, userId: $user->id, userName: $user->name, tenantId: $user->tenant_id));
    }

    /** A reply may only quote a message from the same room. */
    private function validParent(YardRoom $room, ?int $parentId): ?int
    {
        if (! $parentId) {
            return null;
        }

        return YardMessage::where('id', $parentId)->where('room_id', $room->id)->exists() ? $parentId : null;
    }

    private function touchRoom(YardRoom $room, User $user, string $preview): void
    {
        $room->update([
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($preview, 100),
            'last_message_user_id' => $user->id,
            'messages_count' => $room->messages_count + 1,
        ]);
    }

    /** To everyone in the room except the sender's own open connection (X-Socket-ID). */
    private function broadcast(object $event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Broadcast failed: '.$e->getMessage());
        }
    }

    /** AI moderation, queued: flags the message past auto_flag_threshold. */
    private function moderate(YardMessage $message): void
    {
        $id = $message->id;
        $content = $message->content;

        dispatch(function () use ($id, $content) {
            $ai = app(AIService::class);
            if (! $ai->isAvailable() || ! $content) {
                return;
            }
            try {
                $result = $ai->moderateText($content);
                $threshold = (int) PlatformSetting::getValue('auto_flag_threshold', 70);
                if (($result['flagged'] ?? false) || ($result['score'] ?? 0) >= $threshold) {
                    YardMessage::where('id', $id)->update([
                        'is_flagged' => true,
                        'ai_moderation_score' => $result['score'] ?? null,
                        'ai_moderation_detail' => $result['categories'] ?? [],
                    ]);
                }
            } catch (\Throwable $e) {
                // Moderation never breaks the chat.
            }
        });
    }
}
