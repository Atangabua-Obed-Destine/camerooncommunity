<?php

namespace App\Livewire\Yard;

use App\Enums\MessageType;
use App\Enums\RoomType;
use App\Models\User;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Models\YardMessageReaction;
use App\Models\YardPoll;
use App\Models\YardPollOption;
use App\Models\YardPollVote;
use App\Services\ReceiptService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ChatRoom extends Component
{
    use WithFileUploads;

    public YardRoom $room;
    public string $newMessage = '';
    public ?int $replyToId = null;
    public ?string $replyToPreview = null;
    public $mediaUpload;
    public string $mediaCaption = '';

    // Edit mode
    public ?int $editingMessageId = null;
    public string $editContent = '';

    // Search
    public string $messageSearch = '';
    public bool $searchActive = false;

    // Pagination. The first page is what the user waits for when a room opens,
    // and each rendered bubble is several KB of HTML, so it is deliberately
    // small — about a screenful. Scrolling up adds 50 at a time.
    public int $perPage = 25;
    public bool $hasMore = true;

    protected $listeners = [
        'room-selected' => 'loadRoom',
    ];

    public function getListeners(): array
    {
        return $this->listeners;
    }

    public function mount(?YardRoom $room = null)
    {
        if ($room && $room->exists) {
            $this->room = $room;
            $this->positionChatAtFirstUnreadOrBottom();
            $this->markAsRead();
            $this->dispatch('echo-subscribe', channel: 'tenant.' . $room->tenant_id . '.room.' . $room->id);
        }
    }

    public function loadRoom(?int $roomId)
    {
        if (! $roomId) {
            return;
        }

        // The room list opens a room twice on purpose — once optimistically from
        // the browser and once from its own server response — so that the two
        // Livewire updates travel together instead of one after the other. The
        // second one lands here and must cost nothing.
        if (isset($this->room) && $this->room->exists && $this->room->id === $roomId) {
            return;
        }

        $room = YardRoom::findOrFail($roomId);

        // 'room-selected' is a browser event, so the id cannot be trusted: without
        // this, anyone could dispatch it from the console and read a private group
        // or someone else's DM. Membership is the gate, decided here.
        $isMember = YardRoomMember::where('room_id', $room->id)
            ->where('user_id', auth()->id())
            ->exists();

        if (! $isMember) {
            $this->dispatch('toast', type: 'warning', message: __('You are not a member of that chat.'));

            return;
        }

        // Away/archived memberships are visible in the sidebar but locked; the
        // room list refuses to open them, and so does this path.
        $isArchived = YardRoomMember::where('room_id', $room->id)
            ->where('user_id', auth()->id())
            ->whereNotNull('auto_archived_at')
            ->exists();

        if ($isArchived) {
            $this->dispatch('toast', type: 'info', message: __('This room is locked. Switch back to its location to reopen it.'));

            return;
        }

        $this->room = $room;
        $this->newMessage = '';
        $this->replyToId = null;
        $this->replyToPreview = null;
        $this->editingMessageId = null;
        $this->editContent = '';
        $this->messageSearch = '';
        $this->searchActive = false;
        $this->hasMore = true;
        $this->perPage = 25;
        // Drop any cached DM state from the previously-open room so a
        // "blocked-by-them" banner from a DM cannot leak into a group's
        // input bar after switching rooms.
        unset($this->dmConnectionState, $this->dmPartnerStatus, $this->roomMessages, $this->pinnedMessages, $this->autoTranslateLang);
        // Per-request memos belong to the room that was open, not this one.
        $this->joinedAtMemo = false;
        $this->translateMemo = null;
        $this->translateMemoLoaded = false;
        $this->translationsPending = false;
        $this->positionChatAtFirstUnreadOrBottom();
        $this->markAsRead();
        $this->dispatch('echo-subscribe', channel: 'tenant.' . $this->room->tenant_id . '.room.' . $this->room->id);
        $this->dispatch('room-type-changed', roomType: $this->room->room_type->value);
    }

    /**
     * WhatsApp-style positioning: when the user opens a room, jump to the
     * first unread message (sent by someone else after their last_read_at).
     * If there are no unread messages, fall back to the bottom of the chat.
     * Must be called BEFORE markAsRead() so last_read_at still reflects the
     * state from the previous visit.
     */
    protected function positionChatAtFirstUnreadOrBottom(): void
    {
        if (!isset($this->room) || !$this->room->exists) {
            return;
        }

        $lastRead = YardRoomMember::where('room_id', $this->room->id)
            ->where('user_id', auth()->id())
            ->value('last_read_at');

        $firstUnreadId = null;
        if ($lastRead) {
            $firstUnreadId = YardMessage::where('room_id', $this->room->id)
                ->where('user_id', '!=', auth()->id())
                ->where('created_at', '>', $lastRead)
                ->orderBy('created_at')
                ->value('id');
        }

        $this->dispatch('chat-position-target', messageId: $firstUnreadId);
    }

    #[Computed]
    public function roomMessages()
    {
        if (!isset($this->room) || !$this->room->exists) {
            return collect();
        }

        // Who may see what is decided in one place, shared with the app's API.
        $query = app(\App\Services\Yard\YardMessageService::class)->visible(auth()->user(), $this->room)
            ->with(['user:id,name,username,avatar', 'parent:id,content,user_id', 'parent.user:id,name,username', 'poll.options']);

        if ($this->searchActive && $this->messageSearch) {
            $query->where('content', 'like', '%' . $this->messageSearch . '%');
        }

        // Resolved once here, not once per message: this is a database read and
        // the list can be 50 messages long.
        $translateTo = $this->autoTranslateLang;

        $messages = $query->orderByDesc('created_at')
            ->limit($this->perPage)
            ->get();

        // Every author's saved nickname in one query, before the view asks for
        // them one at a time.
        \App\Models\UserContactName::prime(
            (int) auth()->id(),
            $messages->pluck('user_id')->filter()->unique()->all()
        );

        return $messages
            ->reverse()
            ->values()
            ->map(function (YardMessage $m) use ($translateTo) {
                $m->display_content = $this->resolveDisplayContent($m, $translateTo);
                return $m;
            });
    }

    /**
     * If the current user enabled auto-translate for this room and the
     * message has a cached translation in the target language, expose it
     * as `display_content` on the message instance so the view renders it
     * inline. Falls back to the original content otherwise.
     *
     * Rendering is cache-only by default. Translating calls OpenAI over the
     * network, and doing that while a room opens meant the whole chat waited on
     * up to a page of round trips. The remote work happens in translatePending()
     * instead, once the messages are already on screen.
     */
    protected function resolveDisplayContent(YardMessage $m, ?string $translateTo = null, bool $allowRemote = false): ?string
    {
        $original = $m->content;

        if (!is_string($original) || $original === '') {
            return $original;
        }
        if ($m->user_id === auth()->id()) {
            return $original; // never translate the viewer's own messages
        }

        $target = $translateTo;
        if (!$target) {
            return $original;
        }

        $cached = is_array($m->translated_content) ? ($m->translated_content[$target] ?? null) : null;
        if ($cached) {
            return $cached;
        }

        if (! $allowRemote) {
            $this->translationsPending = true;

            return $original;
        }

        // Lazy-translate via the AI service (cached 30 days inside the service).
        try {
            $translated = app(\App\Services\AIService::class)->translate($original, $target);
            if ($translated && $translated !== $original) {
                // Persist into the JSON column so other viewers reuse it.
                $bag = is_array($m->translated_content) ? $m->translated_content : [];
                $bag[$target] = $translated;
                YardMessage::where('id', $m->id)->update(['translated_content' => $bag]);
                return $translated;
            }
        } catch (\Throwable $e) {
            \Log::warning('Auto-translate failed: ' . $e->getMessage());
        }

        return $original;
    }

    #[Computed]
    public function pinnedMessages()
    {
        if (!isset($this->room) || !$this->room->exists) {
            return collect();
        }

        $query = YardMessage::where('room_id', $this->room->id)
            ->where('is_pinned', true)
            ->with('user:id,name,username');

        // Same WhatsApp-style history cut-off as the main message feed:
        // don't expose pinned messages that pre-date the viewer's join.
        if ($this->room->room_type !== \App\Enums\RoomType::DirectMessage) {
            $joinedAt = $this->joinedAt();
            if ($joinedAt) {
                $query->where('created_at', '>=', $joinedAt);
            }
        }

        return $query->orderByDesc('pinned_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function dmPartnerStatus()
    {
        if (!isset($this->room) || !$this->room->exists) {
            return null;
        }
        if ($this->room->room_type !== RoomType::DirectMessage) {
            return null;
        }

        $partnerId = YardRoomMember::where('room_id', $this->room->id)
            ->where('user_id', '!=', auth()->id())
            ->value('user_id');

        if (!$partnerId) return null;

        $partner = User::select('id', 'last_active_at')->find($partnerId);
        if (!$partner) return null;

        $lastActive = $partner->last_active_at;
        $isOnline = $lastActive && $lastActive->gt(now()->subMinutes(2));

        return [
            'is_online' => $isOnline,
            'last_active_at' => $lastActive?->toIso8601String(),
        ];
    }

    public function pollDmStatus()
    {
        // Called by JS polling — forces recompute of dmPartnerStatus AND of
        // dmConnectionState. The latter is the safety net so the chat input
        // reappears within 30s even if the websocket missed the
        // unblock/accept broadcast.
        unset($this->dmPartnerStatus, $this->dmConnectionState);
    }

    /**
     * Connection state with the DM partner — drives the "not connected" banner.
     * Returns: ['state' => 'connected'|'outgoing'|'incoming'|'blocked-by-me'|'blocked-by-them'|'none', 'partner_id' => int]
     */
    #[Computed]
    public function dmConnectionState()
    {
        if (!isset($this->room) || !$this->room->exists) return null;
        if ($this->room->room_type !== RoomType::DirectMessage) return null;
        // Marketplace chats never show the "connect first" banner.
        if ($this->room->origin === 'marketplace') return null;

        $partnerId = (int) YardRoomMember::where('room_id', $this->room->id)
            ->where('user_id', '!=', auth()->id())
            ->value('user_id');
        if (!$partnerId) return null;

        $c = \App\Models\UserConnection::between(auth()->id(), $partnerId);
        $state = 'none';
        if ($c) {
            if ($c->status === \App\Models\UserConnection::STATUS_ACCEPTED) {
                $state = 'connected';
            } elseif ($c->status === \App\Models\UserConnection::STATUS_PENDING) {
                $state = $c->requested_by === auth()->id() ? 'outgoing' : 'incoming';
            } elseif ($c->status === \App\Models\UserConnection::STATUS_BLOCKED) {
                $state = $c->requested_by === auth()->id() ? 'blocked-by-me' : 'blocked-by-them';
            }
        }
        return ['state' => $state, 'partner_id' => $partnerId];
    }

    public function requestDmConnection(): void
    {
        $info = $this->dmConnectionState;
        if (! $info || $info['state'] !== 'none') return;
        $partner = User::find($info['partner_id']);
        if (! $partner) return;
        app(\App\Services\ConnectionService::class)->request(auth()->user(), $partner);
        unset($this->dmConnectionState);
        $this->dispatch('toast', type: 'success', message: app()->getLocale() === 'fr'
            ? 'Demande de connexion envoyée.'
            : 'Connection request sent.');
    }

    public function acceptDmConnection(): void
    {
        $info = $this->dmConnectionState;
        if (! $info || $info['state'] !== 'incoming') return;
        app(\App\Services\ConnectionService::class)->accept(auth()->user(), $info['partner_id']);
        unset($this->dmConnectionState);
    }

    /**
     * Allow the blocker to lift the block directly from the chat banner so they
     * don't have to navigate to Connections. Only succeeds when *they* placed
     * the block (state === 'blocked-by-me').
     */
    public function unblockDmConnection(): void
    {
        $info = $this->dmConnectionState;
        if (! $info || $info['state'] !== 'blocked-by-me') return;
        $ok = app(\App\Services\ConnectionService::class)->unblock(auth()->user(), $info['partner_id']);
        unset($this->dmConnectionState);
        if ($ok) {
            $this->dispatch('toast', type: 'success', message: app()->getLocale() === 'fr'
                ? 'Utilisateur débloqué. Vous pouvez à nouveau discuter. 🤝'
                : 'User unblocked. You can chat again. 🤝');
        }
    }

    /**
     * Re-evaluate the DM connection banner when the global notifier reports
     * a state change (declined / cancelled / disconnected / blocked / unblocked
     * / accepted). Wired from connection-notifier.blade.php which dispatches
     * `connection-updated` after each .connection.* echo event.
     */
    #[On('connection-updated')]
    public function refreshDmConnectionState(): void
    {
        unset($this->dmConnectionState);
    }

    /**
     * Reload the active room's metadata (avatar, name, members_count) when
     * a `RoomUpdated` echo event fires or when RoomInfo dispatches
     * `room-updated` locally. Keeps the chat header in sync without a refresh.
     */
    #[On('room-updated')]
    public function refreshRoomMetadata(): void
    {
        if (isset($this->room) && $this->room->exists) {
            $this->room = $this->room->fresh();
            unset($this->roomMessages, $this->pinnedMessages);
        }
    }

    #[Computed]
    public function forwardRooms()
    {
        $user = auth()->user();
        $currentRoomId = isset($this->room) && $this->room->exists ? $this->room->id : 0;

        // Only rooms the user is actively a member of — exclude both manually
        // archived ("Away") and auto-archived (location-switched) rooms so
        // forwarding can't deliver to chats hidden from the active list.
        $activeRoomIds = YardRoomMember::where('user_id', $user->id)
            ->whereNull('archived_at')
            ->whereNull('auto_archived_at')
            ->pluck('room_id');

        $rooms = YardRoom::whereIn('id', $activeRoomIds)
            ->where('id', '!=', $currentRoomId)
            ->orderByDesc('last_message_at')
            ->select('id', 'name', 'room_type', 'avatar')
            ->limit(50)
            ->get();

        // For DMs, replace the stored "userA & userB" name with the OTHER
        // participant's display name so the modal reads like a contact list.
        $dmRoomIds = $rooms->where('room_type', \App\Enums\RoomType::DirectMessage)
            ->pluck('id');

        if ($dmRoomIds->isNotEmpty()) {
            $partnerNames = YardRoomMember::whereIn('room_id', $dmRoomIds)
                ->where('user_id', '!=', $user->id)
                ->join('users', 'users.id', '=', 'yard_room_members.user_id')
                ->get(['yard_room_members.room_id', 'users.id as uid', 'users.name', 'users.username', 'users.avatar'])
                ->keyBy('room_id');

            $rooms->transform(function ($r) use ($partnerNames) {
                if ($r->room_type === \App\Enums\RoomType::DirectMessage) {
                    $partner = $partnerNames->get($r->id);
                    if ($partner) {
                        $r->name = $partner->username ?: $partner->name;
                        // Use partner's avatar for DM rooms (rooms themselves
                        // typically have no avatar stored).
                        $r->avatar = $partner->avatar;
                        $r->dm_partner_id = $partner->uid;
                    }
                }
                return $r;
            });
        }

        return $rooms;
    }

    public function loadMore()
    {
        $this->perPage += 50;
        // Tell the front-end to restore scroll position after the morph,
        // so the user stays anchored at the message they were reading.
        $this->dispatch('messages-prepended');
    }

    // ─── SEND MESSAGE ───

    public function sendMessage(?string $text = null)
    {
        if (!isset($this->room) || !$this->room->exists) {
            return;
        }

        if ($text !== null) {
            $this->newMessage = $text;
        }

        $this->validate([
            'newMessage' => 'required|string|max:4000',
        ]);

        $user = auth()->user();
        $messages = app(\App\Services\Yard\YardMessageService::class);

        $blocked = $messages->cannotSend($user, $this->room);
        if ($blocked === 'not_member') {
            $this->dispatch('toast', type: 'warning', message: app()->getLocale() === 'fr'
                ? 'Vous n\'êtes plus membre de ce salon.'
                : 'You are no longer a member of this room.');
            return;
        }
        if ($blocked === 'not_connected') {
            $this->dispatch('toast', type: 'warning', message: app()->getLocale() === 'fr'
                ? 'Vous devez être connecté avec cet utilisateur pour lui envoyer un message.'
                : 'You must connect with this user before you can send a message.');
            return;
        }

        $messages->sendText($user, $this->room, $this->newMessage, $this->replyToId);

        $this->reset('newMessage', 'replyToId', 'replyToPreview');
        unset($this->roomMessages);
        $this->dispatch('message-sent');
        $this->dispatch('room-updated');
    }

    // ─── MEDIA UPLOAD (Photo / Document / Audio) ───

    public function sendMedia(string $type = 'image')
    {
        if (!isset($this->room) || !$this->room->exists) {
            return;
        }

        $messages = app(\App\Services\Yard\YardMessageService::class);
        $this->validate(['mediaUpload' => $messages::MEDIA_RULES[$type] ?? 'required|file|max:20480']);

        $user = auth()->user();

        // The same gate as text: the web used to let a photo through to a DM
        // with someone you weren't connected to.
        if ($messages->cannotSend($user, $this->room)) {
            return;
        }

        $messages->sendMedia($user, $this->room, $this->mediaUpload, $type, trim($this->mediaCaption) ?: ($this->newMessage ?: null));

        $this->reset('newMessage', 'mediaUpload', 'mediaCaption');
        unset($this->roomMessages);
        $this->dispatch('message-sent');
        $this->dispatch('room-updated');
        $this->dispatch('media-sent');
    }

    // ─── POLLS ───

    public function createPoll(string $question, array $options, bool $allowMultiple = false): void
    {
        if (!isset($this->room) || !$this->room->exists) {
            return;
        }

        $user = auth()->user();

        $isMember = YardRoomMember::where('room_id', $this->room->id)
            ->where('user_id', $user->id)
            ->exists();
        if (!$isMember) {
            return;
        }

        $question = trim($question);
        if ($question === '' || mb_strlen($question) > 300) {
            $this->dispatch('toast', type: 'error', message: 'Invalid poll question.');
            return;
        }

        // Sanitise option list — drop blanks, trim, dedupe (case-insensitive).
        $clean = collect($options)
            ->map(fn ($o) => is_string($o) ? trim($o) : '')
            ->filter(fn ($o) => $o !== '' && mb_strlen($o) <= 200)
            ->unique(fn ($o) => mb_strtolower($o))
            ->values();

        if ($clean->count() < 2 || $clean->count() > 12) {
            $this->dispatch('toast', type: 'error', message: 'A poll needs 2 to 12 options.');
            return;
        }

        \DB::transaction(function () use ($user, $question, $clean, $allowMultiple, &$message) {
            $message = YardMessage::create([
                'tenant_id'         => $user->tenant_id,
                'uuid'              => Str::uuid()->toString(),
                'room_id'           => $this->room->id,
                'user_id'           => $user->id,
                'parent_message_id' => $this->replyToId,
                'message_type'      => MessageType::Poll,
                'content'           => $question,
            ]);

            $poll = YardPoll::create([
                'tenant_id'      => $user->tenant_id,
                'message_id'     => $message->id,
                'room_id'        => $this->room->id,
                'user_id'        => $user->id,
                'question'       => $question,
                'allow_multiple' => $allowMultiple,
                'is_closed'      => false,
            ]);

            foreach ($clean as $i => $text) {
                YardPollOption::create([
                    'poll_id'     => $poll->id,
                    'text'        => $text,
                    'position'    => $i,
                    'votes_count' => 0,
                ]);
            }
        });

        $this->updateRoomMeta('📊 Poll: ' . $question);

        $this->reset('replyToId', 'replyToPreview');
        unset($this->roomMessages);
        $this->dispatch('message-sent');
        $this->dispatch('room-updated');
        $this->dispatch('poll-created');

        if (isset($message)) {
            try {
                broadcast(new \App\Events\MessageSent($message))->toOthers();
            } catch (\Throwable $e) {
                \Log::warning('Poll broadcast failed: ' . $e->getMessage());
            }
        }
    }

    public function votePoll(int $optionId): void
    {
        $user = auth()->user();
        $option = YardPollOption::find($optionId);
        if (!$option) {
            return;
        }

        $poll = YardPoll::find($option->poll_id);
        if (!$poll || $poll->is_closed) {
            return;
        }

        // Membership check via the poll's room.
        $isMember = YardRoomMember::where('room_id', $poll->room_id)
            ->where('user_id', $user->id)
            ->exists();
        if (!$isMember) {
            return;
        }

        \DB::transaction(function () use ($poll, $option, $user) {
            $existing = YardPollVote::where('poll_id', $poll->id)
                ->where('user_id', $user->id)
                ->get();

            $alreadyOnThis = $existing->firstWhere('option_id', $option->id);

            if ($poll->allow_multiple) {
                if ($alreadyOnThis) {
                    // Toggle off
                    $alreadyOnThis->delete();
                    YardPollOption::where('id', $option->id)->decrement('votes_count');
                } else {
                    YardPollVote::create([
                        'poll_id'   => $poll->id,
                        'option_id' => $option->id,
                        'user_id'   => $user->id,
                    ]);
                    YardPollOption::where('id', $option->id)->increment('votes_count');
                }
            } else {
                if ($alreadyOnThis) {
                    // Toggle off the only vote
                    $alreadyOnThis->delete();
                    YardPollOption::where('id', $option->id)->decrement('votes_count');
                } else {
                    // Remove any previous single vote(s)
                    foreach ($existing as $prev) {
                        YardPollOption::where('id', $prev->option_id)->decrement('votes_count');
                        $prev->delete();
                    }
                    YardPollVote::create([
                        'poll_id'   => $poll->id,
                        'option_id' => $option->id,
                        'user_id'   => $user->id,
                    ]);
                    YardPollOption::where('id', $option->id)->increment('votes_count');
                }
            }
        });

        unset($this->roomMessages);
        $this->dispatch('poll-voted', pollId: $poll->id);

        // Re-broadcast the host message so other clients refresh.
        try {
            $msg = YardMessage::find($poll->message_id);
            if ($msg) {
                broadcast(new \App\Events\MessageSent($msg))->toOthers();
            }
        } catch (\Throwable $e) {
            \Log::warning('Poll vote broadcast failed: ' . $e->getMessage());
        }
    }

    public function closePoll(int $pollId): void
    {
        $poll = YardPoll::find($pollId);
        if (!$poll) {
            return;
        }
        if ($poll->user_id !== auth()->id()) {
            return;
        }
        $poll->update(['is_closed' => true]);
        unset($this->roomMessages);
        $this->dispatch('poll-closed', pollId: $poll->id);

        // Rebroadcast the host message so all viewers see the closed badge.
        try {
            $msg = YardMessage::find($poll->message_id);
            if ($msg) {
                broadcast(new \App\Events\MessageSent($msg))->toOthers();
            }
        } catch (\Throwable $e) {
            \Log::warning('Poll close broadcast failed: ' . $e->getMessage());
        }
    }

    /**
     * Fetch grouped voters per option for the "View votes" panel.
     * Returns: [['id'=>..,'text'=>..,'votes_count'=>..,'pct'=>..,'voters'=>[['id'=>,'name'=>,'username'=>,'avatar'=>], ...]], ...]
     */
    public function pollVoters(int $pollId): array
    {
        $poll = YardPoll::with('options')->find($pollId);
        if (!$poll) {
            return [];
        }

        // Membership check via the poll's room.
        $isMember = YardRoomMember::where('room_id', $poll->room_id)
            ->where('user_id', auth()->id())
            ->exists();
        if (!$isMember) {
            return [];
        }

        $optionIds = $poll->options->pluck('id');

        $votes = \DB::table('yard_poll_votes as v')
            ->join('users as u', 'u.id', '=', 'v.user_id')
            ->whereIn('v.option_id', $optionIds)
            ->orderBy('v.created_at')
            ->get(['v.option_id', 'u.id', 'u.name', 'u.username', 'u.avatar'])
            ->groupBy('option_id');

        $totalVotes = (int) $poll->options->sum('votes_count');

        return [
            'pollId'        => $poll->id,
            'question'      => $poll->question,
            'allowMultiple' => (bool) $poll->allow_multiple,
            'isClosed'      => (bool) $poll->is_closed,
            'totalVotes'    => $totalVotes,
            'options'       => $poll->options->map(function ($opt) use ($votes, $totalVotes) {
                $voters = ($votes[$opt->id] ?? collect())->map(fn ($v) => [
                    'id'       => $v->id,
                    'name'     => $v->name,
                    'username' => $v->username,
                    'avatar'   => $v->avatar,
                ])->values()->all();

                return [
                    'id'           => $opt->id,
                    'text'         => $opt->text,
                    'votes_count'  => (int) $opt->votes_count,
                    'pct'          => $totalVotes > 0 ? (int) round(($opt->votes_count / $totalVotes) * 100) : 0,
                    'voters'       => $voters,
                ];
            })->all(),
        ];
    }

    // ─── EDIT MESSAGE ───

    public function startEdit(int $messageId)
    {
        $message = YardMessage::where('id', $messageId)
            ->where('user_id', auth()->id())
            ->where('message_type', MessageType::Text)
            ->where('is_deleted', false)
            ->first();

        if ($message) {
            $this->editingMessageId = $message->id;
            $this->editContent = $message->content ?? '';
            $this->dispatch('focus-edit-input');
        }
    }

    public function saveEdit()
    {
        if (!$this->editingMessageId) {
            return;
        }

        $this->validate([
            'editContent' => 'required|string|max:4000',
        ]);

        $message = YardMessage::find($this->editingMessageId);
        if ($message) {
            app(\App\Services\Yard\YardMessageService::class)->edit(auth()->user(), $message, $this->editContent);
        }

        unset($this->roomMessages);
        $this->cancelEdit();
    }

    public function cancelEdit()
    {
        $this->editingMessageId = null;
        $this->editContent = '';
    }

    // ─── PIN / UNPIN MESSAGE ───

    public function togglePin(int $messageId)
    {
        $message = YardMessage::where('id', $messageId)
            ->where('room_id', $this->room->id)
            ->where('is_deleted', false)
            ->first();

        // The service checks the member can see it, and rebroadcasts so
        // other clients refresh the pin badge and the pinned bar.
        if ($message && app(\App\Services\Yard\MessageActionsService::class)->togglePin(auth()->user(), $message) !== null) {
            unset($this->roomMessages, $this->pinnedMessages);
        }
    }

    // ─── DELETE ───

    public function deleteMessage(int $messageId)
    {
        $message = YardMessage::find($messageId);

        if ($message && app(\App\Services\Yard\YardMessageService::class)->delete(auth()->user(), $message)) {
            unset($this->roomMessages);
        }
    }

    /**
     * Delete several of your own messages at once (selection mode).
     *
     * Each id goes through deleteMessage(), which already refuses messages that
     * are not yours — selecting someone else's and hitting delete quietly skips
     * it rather than failing the whole batch.
     */
    public function deleteMessages(array $ids)
    {
        foreach (array_slice(array_filter(array_map('intval', $ids)), 0, 100) as $id) {
            $this->deleteMessage($id);
        }

        unset($this->roomMessages, $this->pinnedMessages);
    }

    /**
     * Star or unstar a selection. Mixed selections are starred, matching
     * WhatsApp: the action only clears stars when every message already has one.
     */
    public function starMessages(array $ids)
    {
        app(\App\Services\Yard\MessageActionsService::class)->star(auth()->user(), $ids);
        $this->dispatch('starred-changed');
    }

    /**
     * Forward a selection into another room, oldest first. Returns how many
     * went, or false (with a toast) when the target won't take them.
     */
    public function forwardMessages(array $ids, int $targetRoomId)
    {
        $target = YardRoom::find($targetRoomId);
        $result = $target
            ? app(\App\Services\Yard\MessageActionsService::class)->forward(auth()->user(), $ids, $target)
            : 'not_member';

        if (in_array($result, ['not_connected', 'blocked'], true)) {
            $this->dispatch('toast', type: 'warning', message: app()->getLocale() === 'fr'
                ? 'Impossible de transférer à cet utilisateur.'
                : 'Cannot forward to this user.');
        }

        return is_int($result) ? $result : false;
    }

    // ─── STAR ───

    public function toggleStar(int $messageId)
    {
        $this->starMessages([$messageId]);
    }

    // ─── REACTIONS (full emoji support) ───

    public function toggleReaction(int $messageId, string $emoji)
    {
        $message = YardMessage::find($messageId);

        if ($message) {
            app(\App\Services\Yard\YardMessageService::class)->toggleReaction(auth()->user(), $message, $emoji);
        }
    }

    // ─── REPLY ───

    public function setReply(int $messageId)
    {
        $msg = YardMessage::with('user:id,name')->find($messageId);
        if ($msg) {
            $this->replyToId = $msg->id;
            $this->replyToPreview = ($msg->user->name ?? 'Unknown') . ': ' . Str::limit($msg->content, 80);
        }
    }

    public function cancelReply()
    {
        $this->replyToId = null;
        $this->replyToPreview = null;
    }

    // ─── SEARCH ───

    public function toggleSearch()
    {
        $this->searchActive = !$this->searchActive;
        if (!$this->searchActive) {
            $this->messageSearch = '';
        }
    }

    // ─── TYPING INDICATOR ───

    public function sendTyping()
    {
        try {
            if (auth()->user() && isset($this->room) && $this->room->exists) {
                app(\App\Services\Yard\YardMessageService::class)->typing(auth()->user(), $this->room);
            }
        } catch (\Throwable $e) {
            // Typing indicator is non-critical
        }

        $this->skipRender();
    }

    // ─── FORWARD MESSAGE ───

    public function forwardMessage(int $messageId, int $targetRoomId)
    {
        // Through the service, which only forwards what this member can see
        // (this used to copy any message id, from any room).
        return $this->forwardMessages([$messageId], $targetRoomId) ? $targetRoomId : false;
    }

    // ─── BROADCAST LISTENER ───

    public function onMessageReceived($data)
    {
        unset($this->roomMessages);

        // Record delivery here rather than making the browser fire a second
        // request for it: every incoming message used to cost three Livewire
        // round-trips (receive, delivered, read), each re-rendering the chat.
        $id = is_array($data) ? ($data['id'] ?? null) : null;
        $senderId = is_array($data) ? ($data['user_id'] ?? null) : null;

        if ($id && $senderId && (int) $senderId !== (int) auth()->id() && isset($this->room) && $this->room->exists) {
            $message = YardMessage::where('id', $id)->where('room_id', $this->room->id)->first();
            if ($message) {
                app(ReceiptService::class)->markDelivered($message, auth()->id());
            }
        }

        // Marking read used to be a second round trip 1.5s later, on the
        // reasoning that the room list needed a moment to show the unread
        // badge. It never did: RoomList suppresses the badge for the room that
        // is already open. So that was a second full re-render of the thread
        // per message, bought for nothing.
        $this->markAsRead();

        $this->dispatch('message-sent');
        $this->dispatch('room-updated');
    }

    /**
     * Called after a short delay so RoomList has time to re-render with the unread badge.
     */
    public function delayedMarkRead(): void
    {
        $this->markAsRead();
        $this->dispatch('room-updated');
    }

    public function onMessageDeleted($data)
    {
        unset($this->roomMessages);
    }

    // ─── TRANSLATE ───

    // ─── REPORT MESSAGE ───

    public function reportMessage(int $messageId)
    {
        $user = auth()->user();
        $message = YardMessage::where('id', $messageId)
            ->where('room_id', $this->room->id)
            ->where('is_deleted', false)
            ->first();

        if (!$message || $message->user_id === $user->id) {
            return;
        }

        // Prevent duplicate reports
        $exists = \App\Models\Report::where('reporter_id', $user->id)
            ->where('reportable_type', YardMessage::class)
            ->where('reportable_id', $messageId)
            ->exists();

        if ($exists) {
            return;
        }

        \App\Models\Report::create([
            'tenant_id' => $user->tenant_id,
            'uuid' => Str::uuid()->toString(),
            'reporter_id' => $user->id,
            'reportable_type' => YardMessage::class,
            'reportable_id' => $messageId,
            'reason' => \App\Enums\ReportReason::Inappropriate,
            'status' => \App\Enums\ReportStatus::Pending,
        ]);
    }

    public function translateMessage(int $messageId, string $targetLang = 'en')
    {
        $message = YardMessage::find($messageId);
        if (!$message || !$message->content) {
            return;
        }

        $targetLang = in_array($targetLang, ['en', 'fr']) ? $targetLang : 'en';
        $aiService = app(\App\Services\AIService::class);
        $translation = $aiService->translate($message->content, $targetLang);

        if ($translation) {
            $this->dispatch('translation-ready', messageId: $messageId, text: $translation);
        }
    }

    // ─── SUMMARISE THREAD ───

    public function summariseThread()
    {
        if (!isset($this->room) || !$this->room->exists) {
            return;
        }

        $aiService = app(\App\Services\AIService::class);
        if (!$aiService->isAvailable()) {
            return;
        }

        $recentMessages = YardMessage::where('room_id', $this->room->id)
            ->where('is_deleted', false)
            ->whereIn('message_type', ['text'])
            ->with('user:id,name')
            ->latest()
            ->take(60)
            ->get()
            ->reverse()
            ->map(fn ($m) => ['user' => $m->user?->name ?? 'User', 'content' => $m->content])
            ->values()
            ->toArray();

        if (count($recentMessages) < 10) {
            return;
        }

        $userLang = auth()->user()?->language_pref?->value ?? 'en';
        $summary = $aiService->summariseThread($recentMessages, $userLang);

        if ($summary) {
            $this->dispatch('thread-summary-ready', summary: $summary);
        }
    }

    // ─── HELPERS ───

    /**
     * Return matching room members for an in-progress @mention.
     * Called from the chat input's autocomplete dropdown.
     *
     * @return array<int, array{id:int,name:string,username:?string,avatar:?string}>
     */
    public function mentionSuggest(string $query = ''): array
    {
        if (!isset($this->room) || !$this->room->exists) {
            return [];
        }
        return app(\App\Services\MentionService::class)
            ->suggest($this->room->id, $query, 8)
            ->all();
    }

    /**
     * Set the per-room auto-translate target language for the current user.
     * Pass null/empty to disable; otherwise 'en' or 'fr'.
     */
    public function setAutoTranslate(?string $lang = null): void
    {
        if (!isset($this->room) || !$this->room->exists) {
            return;
        }

        $lang = in_array($lang, ['en', 'fr'], true) ? $lang : null;

        YardRoomMember::where('room_id', $this->room->id)
            ->where('user_id', auth()->id())
            ->update(['auto_translate_lang' => $lang]);

        $this->translateMemoLoaded = false;
        unset($this->roomMessages, $this->autoTranslateLang);

        $this->dispatch('toast', type: 'success', message: $lang
            ? __('Auto-translate enabled.')
            : __('Auto-translate disabled.'));
    }

    /**
     * Set during render when a visible message has no cached translation yet.
     * Not public: it is a per-request fact, and the view asks for the follow-up.
     */
    protected bool $translationsPending = false;

    public function hasPendingTranslations(): bool
    {
        // The flag is only meaningful once the messages have been built.
        $this->roomMessages;

        return $this->translationsPending;
    }

    /**
     * Translate the visible messages that had no cached translation. Called by
     * the view right after the chat paints, so opening a room never waits on the
     * network. Bounded, so one very long page cannot stall a request either.
     */
    public function translatePending(): void
    {
        $target = $this->autoTranslateLang;

        if (! $target || ! isset($this->room) || ! $this->room->exists) {
            return;
        }

        $done = 0;
        foreach ($this->roomMessages as $m) {
            if ($done >= 25) {
                break;
            }
            if ($m->user_id === auth()->id() || ! is_string($m->content) || $m->content === '') {
                continue;
            }
            $cached = is_array($m->translated_content) ? ($m->translated_content[$target] ?? null) : null;
            if ($cached) {
                continue;
            }

            $this->resolveDisplayContent($m, $target, allowRemote: true);
            $done++;
        }

        if ($done > 0) {
            unset($this->roomMessages);
        }
    }

    /**
     * The viewer's join date for the open room, read once per request. Both the
     * message feed and the pinned list need it as a history cut-off.
     */
    protected $joinedAtMemo = false;

    protected function joinedAt()
    {
        if ($this->joinedAtMemo === false) {
            $this->joinedAtMemo = YardRoomMember::where('room_id', $this->room->id)
                ->where('user_id', auth()->id())
                ->value('joined_at');
        }

        return $this->joinedAtMemo;
    }

    /** Per-request memo. Not public, so Livewire never serialises it. */
    protected ?string $translateMemo = null;
    protected bool $translateMemoLoaded = false;

    #[Computed]
    public function autoTranslateLang(): ?string
    {
        if (!isset($this->room) || !$this->room->exists) {
            return null;
        }

        // The computed cache is dropped by the many unset($this->roomMessages, ...)
        // calls in this class, so this memo is what actually stops the repeat reads.
        if (! $this->translateMemoLoaded) {
            $this->translateMemo = YardRoomMember::where('room_id', $this->room->id)
                ->where('user_id', auth()->id())
                ->value('auto_translate_lang');
            $this->translateMemoLoaded = true;
        }

        return $this->translateMemo;
    }

    protected function markAsRead()
    {
        if (!isset($this->room) || !$this->room->exists) {
            return;
        }

        // The bell counts unread chats, and nothing told it when they stopped
        // being unread, so it refreshes whenever something was marked.
        if (app(\App\Services\Yard\YardMessageService::class)->markRead(auth()->user(), $this->room)) {
            $this->dispatch('bell-refresh');
        }
    }

    protected function updateRoomMeta(string $preview): void
    {
        $this->room->update([
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($preview, 100),
            'last_message_user_id' => auth()->id(),
            'messages_count' => $this->room->messages_count + 1,
        ]);
    }

    public function render()
    {
        $messages = $this->roomMessages;

        // Compute WhatsApp-style tick statuses for the current user's OWN messages.
        $ownMessages = $messages->where('user_id', auth()->id())->all();
        $messageStatuses = isset($this->room) && $this->room->exists
            ? app(ReceiptService::class)->bulkStatusFor($ownMessages, $this->room->id)
            : [];

        return view('livewire.yard.chat-room', [
            'roomMessages' => $messages,
            'pinnedMessages' => $this->pinnedMessages,
            'messageStatuses' => $messageStatuses,
        ]);
    }
}
