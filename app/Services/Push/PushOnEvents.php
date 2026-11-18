<?php

namespace App\Services\Push;

use App\Enums\MessageType;
use App\Enums\RoomType;
use App\Events\ConnectionAccepted;
use App\Events\ConnectionRequested;
use App\Events\MessageMentioned;
use App\Events\MessageSent;
use App\Models\User;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Support\Str;

/**
 * Turns the realtime events into pushes, for when the app is closed.
 *
 * Every message already goes out as MessageSent, and `broadcast()` sends it
 * through the event dispatcher too, so listening here covers each place a
 * message can be sent without touching any of them.
 *
 * Who gets a push, and the rule behind it:
 *   - a direct message (GoConnect or a GoMarket product chat): the other member;
 *   - a private group: every member but the sender;
 *   - the national, regional and city rooms: only members @mentioned, since a
 *     public room pinging every member at every message would be noise;
 *   - a connection request, and its acceptance: the member it is for.
 * A muted chat does not push its messages, but an @mention still gets
 * through, as its toast does on the web. System lines and call logs never push.
 */
class PushOnEvents
{
    public function __construct(private PushService $push) {}

    public function messageSent(MessageSent $event): void
    {
        $this->safely(function () use ($event) {
            $message = $event->message->loadMissing(['room', 'user']);
            $room = $message->room;
            $sender = $message->user;
            if (! $room || ! $sender || in_array($message->message_type, [MessageType::System, MessageType::CallLog], true)) {
                return;
            }
            if (! in_array($room->room_type, [RoomType::DirectMessage, RoomType::PrivateGroup], true)) {
                return;
            }

            $preview = YardRoom::whereKey($room->id)->value('last_message_preview')
                ?: Str::limit(strip_tags((string) $message->content), 100);

            $recipients = YardRoomMember::where('room_id', $room->id)
                ->where('user_id', '!=', $sender->id)
                ->where(fn ($q) => $q->where('is_muted', false)->orWhereNull('is_muted'))
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter(fn (?User $u) => $u && $u->is_active);

            foreach ($recipients as $recipient) {
                $senderName = $recipient->displayNameFor($sender) ?: $sender->username;
                [$title, $body] = $room->room_type === RoomType::DirectMessage
                    ? [$senderName, $preview]
                    : [(string) ($room->name ?: $senderName), $senderName.': '.$preview];

                $this->push->toUser($recipient->id, $title, $body, [
                    'kind' => 'message',
                    'room_id' => $room->id,
                    'link' => $this->roomLink($room),
                ], 'messages', 'room-'.$room->id);
            }
        });
    }

    public function mentioned(MessageMentioned $event): void
    {
        $this->safely(function () use ($event) {
            $room = $event->message->loadMissing('room')->room;
            // In a DM or a private group the message push already went.
            if (! $room || in_array($room->room_type, [RoomType::DirectMessage, RoomType::PrivateGroup], true)) {
                return;
            }
            // A mention gets through a muted room, as its toast does on the
            // web (MentionService: "mention overrides notification pref").
            if (! $event->mentionedUser->is_active) {
                return;
            }

            $sender = $event->mentionedUser->displayNameFor($event->sender) ?: $event->sender->username;
            $this->push->toUser(
                $event->mentionedUser->id,
                $this->in($event->mentionedUser, ':name mentioned you in :room', ['name' => $sender, 'room' => (string) $room->name]),
                Str::limit(strip_tags((string) $event->message->content), 100),
                ['kind' => 'mention', 'room_id' => $room->id, 'link' => $this->roomLink($room)],
                'messages',
                'room-'.$room->id,
            );
        });
    }

    public function connectionRequested(ConnectionRequested $event): void
    {
        $this->safely(fn () => $this->push->toUser(
            $event->to->id,
            $this->in($event->to, 'New connection request'),
            $this->in($event->to, ':name wants to connect with you.', ['name' => $event->to->displayNameFor($event->from) ?: $event->from->username]),
            ['kind' => 'connection', 'user_id' => $event->from->id, 'link' => route('yard').'?open=connections&tab=requests'],
        ));
    }

    public function connectionAccepted(ConnectionAccepted $event): void
    {
        $this->safely(fn () => $this->push->toUser(
            $event->requester->id,
            $this->in($event->requester, 'Connection accepted'),
            $this->in($event->requester, ':name accepted your connection request.', ['name' => $event->requester->displayNameFor($event->accepter) ?: $event->accepter->username]),
            ['kind' => 'connection', 'user_id' => $event->accepter->id, 'link' => route('yard').'?open=connections'],
        ));
    }

    /** The web's own link for a chat, as the bell's rows build it. */
    private function roomLink(YardRoom $room): string
    {
        return $room->origin === 'marketplace'
            ? route('marketplace.inbox').'?c='.$room->id
            : route('yard').'?room='.$room->id;
    }

    /** In the recipient's language, not the sender's. */
    private function in(User $user, string $text, array $replace = []): string
    {
        $lang = $user->language_pref?->value ?? $user->language_pref;

        return __($text, $replace, in_array($lang, ['en', 'fr'], true) ? $lang : 'en');
    }

    /** A push must never break the message, request or answer behind it. */
    private function safely(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            logger()->warning('Push skipped: '.$e->getMessage());
        }
    }
}
