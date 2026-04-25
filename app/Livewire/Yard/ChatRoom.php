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

    // Pagination
    public int $perPage = 50;
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
            $this->markAsRead();
            $this->dispatch('echo-subscribe', channel: 'tenant.' . $room->tenant_id . '.room.' . $room->id);
        }
    }

    public function loadRoom(?int $roomId)
    {
        if (! $roomId) {
            return;
        }

        $this->room = YardRoom::findOrFail($roomId);
        $this->newMessage = '';
        $this->replyToId = null;
        $this->replyToPreview = null;
        $this->editingMessageId = null;
        $this->editContent = '';
        $this->messageSearch = '';
        $this->searchActive = false;
        $this->hasMore = true;
        $this->perPage = 50;
        $this->markAsRead();
        $this->dispatch('echo-subscribe', channel: 'tenant.' . $this->room->tenant_id . '.room.' . $this->room->id);
        $this->dispatch('room-type-changed', roomType: $this->room->room_type->value);
    }

    #[Computed]
    public function roomMessages()
    {
        if (!isset($this->room) || !$this->room->exists) {
            return collect();
        }

        $query = YardMessage::where('room_id', $this->room->id)
            ->with(['user:id,name,username,avatar', 'parent:id,content,user_id', 'parent.user:id,name,username', 'poll.options']);

        if ($this->searchActive && $this->messageSearch) {
            $query->where('content', 'like', '%' . $this->messageSearch . '%');
        }

        return $query->orderByDesc('created_at')
            ->limit($this->perPage)
            ->get()
            ->reverse()
            ->values();
    }

    #[Computed]
    public function pinnedMessages()
    {
        if (!isset($this->room) || !$this->room->exists) {
            return collect();
        }

        return YardMessage::where('room_id', $this->room->id)
            ->where('is_pinned', true)
            ->with('user:id,name,username')
            ->orderByDesc('pinned_at')
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
        // Called by JS polling — forces recompute of dmPartnerStatus
        unset($this->dmPartnerStatus);
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

    #[Computed]
    public function forwardRooms()
    {
        $user = auth()->user();
        $currentRoomId = isset($this->room) && $this->room->exists ? $this->room->id : 0;

        return YardRoom::whereIn('id',
            YardRoomMember::where('user_id', $user->id)->pluck('room_id')
        )
            ->where('id', '!=', $currentRoomId)
            ->orderByDesc('last_message_at')
            ->select('id', 'name', 'room_type')
            ->limit(50)
            ->get();
    }

    public function loadMore()
    {
        $this->perPage += 50;
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

        $isMember = YardRoomMember::where('room_id', $this->room->id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMember) {
            return;
        }

        // DM connection gate: must be mutually connected to send a 1:1 DM.
        if ($this->room->room_type === RoomType::DirectMessage) {
            $partner = $this->room->members()
                ->where('user_id', '!=', $user->id)
                ->value('user_id');
            if ($partner && ! $user->isConnectedWith((int) $partner)) {
                $this->dispatch('toast', type: 'warning', message: app()->getLocale() === 'fr'
                    ? 'Vous devez être connecté avec cet utilisateur pour lui envoyer un message.'
                    : 'You must connect with this user before you can send a message.');
                return;
            }
        }

        $message = YardMessage::create([
            'tenant_id' => $user->tenant_id,
            'uuid' => Str::uuid()->toString(),
            'room_id' => $this->room->id,
            'user_id' => $user->id,
            'parent_message_id' => $this->replyToId,
            'message_type' => MessageType::Text,
            'content' => $this->newMessage,
        ]);

        $this->updateRoomMeta($this->newMessage);

        $this->reset('newMessage', 'replyToId', 'replyToPreview');
        unset($this->roomMessages);
        $this->dispatch('message-sent');
        $this->dispatch('room-updated');

        try {
            broadcast(new \App\Events\MessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            \Log::warning('Broadcast failed: ' . $e->getMessage());
        }

        // Queue AI moderation asynchronously via database queue
        $messageId = $message->id;
        $content = $message->content;
        dispatch(function () use ($messageId, $content) {
            $ai = app(\App\Services\AIService::class);
            if (!$ai->isAvailable() || !$content) {
                return;
            }
            try {
                $result = $ai->moderateText($content);
                $threshold = (int) \App\Models\PlatformSetting::getValue('auto_flag_threshold', 70);
                if (($result['flagged'] ?? false) || ($result['score'] ?? 0) >= $threshold) {
                    YardMessage::where('id', $messageId)->update([
                        'is_flagged' => true,
                        'ai_moderation_score' => $result['score'] ?? null,
                        'ai_moderation_detail' => $result['categories'] ?? [],
                    ]);
                }
            } catch (\Throwable $e) {
                // AI failure should never break chat
            }
        });
    }

    // ─── MEDIA UPLOAD (Photo / Document / Audio) ───

    public function sendMedia(string $type = 'image')
    {
        if (!isset($this->room) || !$this->room->exists) {
            return;
        }

        $rules = match ($type) {
            'image' => ['mediaUpload' => 'required|file|mimes:jpeg,jpg,png,webp,gif|max:10240'],
            'document' => ['mediaUpload' => 'required|file|mimes:pdf,doc,docx,xlsx,pptx,txt,csv,zip|max:20480'],
            'audio' => ['mediaUpload' => 'required|file|mimes:webm,ogg,mp3,wav,m4a,mp4|max:25600'],
            default => ['mediaUpload' => 'required|file|max:20480'],
        };

        $this->validate($rules);

        $user = auth()->user();

        $isMember = YardRoomMember::where('room_id', $this->room->id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMember) {
            return;
        }

        $messageType = match ($type) {
            'image' => MessageType::Image,
            'audio' => MessageType::Audio,
            default => MessageType::File,
        };

        $path = $this->mediaUpload->store('yard/media/' . $this->room->id, 'public');
        $originalName = $this->mediaUpload->getClientOriginalName();
        $size = $this->mediaUpload->getSize();

        // Use caption from preview screen if available, otherwise fall back to newMessage
        $caption = trim($this->mediaCaption) ?: ($this->newMessage ?: null);

        $message = YardMessage::create([
            'tenant_id' => $user->tenant_id,
            'uuid' => Str::uuid()->toString(),
            'room_id' => $this->room->id,
            'user_id' => $user->id,
            'message_type' => $messageType,
            'content' => $caption,
            'media_path' => $path,
            'media_original_name' => $originalName,
            'media_size' => $size,
        ]);

        $preview = match ($type) {
            'image' => '📷 Photo' . ($caption ? ': ' . Str::limit($caption, 60) : ''),
            'audio' => '🎤 Voice note',
            default => '📄 ' . $originalName,
        };

        $this->updateRoomMeta($preview);

        $this->reset('newMessage', 'mediaUpload', 'mediaCaption');
        unset($this->roomMessages);
        $this->dispatch('message-sent');
        $this->dispatch('room-updated');
        $this->dispatch('media-sent');

        try {
            broadcast(new \App\Events\MessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            \Log::warning('Broadcast failed: ' . $e->getMessage());
        }
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

        $message = YardMessage::where('id', $this->editingMessageId)
            ->where('user_id', auth()->id())
            ->first();

        if ($message) {
            $message->update([
                'content' => $this->editContent,
                'is_edited' => true,
                'edited_at' => now(),
            ]);
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

        if (!$message) {
            return;
        }

        if ($message->is_pinned) {
            $message->update([
                'is_pinned' => false,
                'pinned_at' => null,
                'pinned_by' => null,
            ]);
        } else {
            $message->update([
                'is_pinned' => true,
                'pinned_at' => now(),
                'pinned_by' => auth()->id(),
            ]);
        }

        unset($this->roomMessages, $this->pinnedMessages);
    }

    // ─── DELETE ───

    public function deleteMessage(int $messageId)
    {
        $message = YardMessage::where('id', $messageId)
            ->where('user_id', auth()->id())
            ->first();

        if ($message) {
            $message->update([
                'is_deleted' => true,
                'content' => null,
            ]);

            unset($this->roomMessages);

            try {
                broadcast(new \App\Events\MessageDeleted($message))->toOthers();
            } catch (\Throwable $e) {
                \Log::warning('Broadcast failed: ' . $e->getMessage());
            }
        }
    }

    // ─── STAR ───

    public function toggleStar(int $messageId)
    {
        $userId = auth()->id();

        $exists = \DB::table('yard_message_stars')
            ->where('user_id', $userId)
            ->where('message_id', $messageId)
            ->exists();

        if ($exists) {
            \DB::table('yard_message_stars')
                ->where('user_id', $userId)
                ->where('message_id', $messageId)
                ->delete();
        } else {
            \DB::table('yard_message_stars')->insert([
                'user_id' => $userId,
                'message_id' => $messageId,
                'created_at' => now(),
            ]);
        }
    }

    // ─── REACTIONS (full emoji support) ───

    public function toggleReaction(int $messageId, string $emoji)
    {
        $user = auth()->user();
        $emoji = mb_substr($emoji, 0, 10);

        $existing = YardMessageReaction::where('message_id', $messageId)
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            YardMessageReaction::create([
                'tenant_id' => $user->tenant_id,
                'message_id' => $messageId,
                'user_id' => $user->id,
                'emoji' => $emoji,
            ]);
        }

        // Sync reaction counts from source of truth
        $this->refreshReactionCount($messageId);
    }

    protected function refreshReactionCount(int $messageId): void
    {
        $reactions = YardMessageReaction::where('message_id', $messageId)
            ->selectRaw('emoji, count(*) as cnt')
            ->groupBy('emoji')
            ->pluck('cnt', 'emoji')
            ->toArray();

        YardMessage::where('id', $messageId)->update([
            'reactions_count' => !empty($reactions) ? $reactions : null,
        ]);
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
            $user = auth()->user();
            broadcast(new \App\Events\UserTyping(
                roomId: $this->room->id,
                userId: $user->id,
                userName: $user->name,
                tenantId: $user->tenant_id,
            ))->toOthers();
        } catch (\Throwable $e) {
            // Typing indicator is non-critical
        }

        $this->skipRender();
    }

    // ─── FORWARD MESSAGE ───

    public function forwardMessage(int $messageId, int $targetRoomId)
    {
        $originalMessage = YardMessage::find($messageId);
        $user = auth()->user();

        if (!$originalMessage || (!$originalMessage->content && !$originalMessage->media_path)) {
            return;
        }

        $isMember = YardRoomMember::where('room_id', $targetRoomId)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isMember) {
            return;
        }

        $forwarded = YardMessage::create([
            'tenant_id' => $user->tenant_id,
            'uuid' => Str::uuid()->toString(),
            'room_id' => $targetRoomId,
            'user_id' => $user->id,
            'message_type' => $originalMessage->message_type,
            'content' => $originalMessage->content,
            'media_path' => $originalMessage->media_path,
            'media_original_name' => $originalMessage->media_original_name,
            'media_size' => $originalMessage->media_size,
            'is_forwarded' => true,
        ]);

        $targetRoom = YardRoom::find($targetRoomId);
        if ($targetRoom) {
            $preview = $forwarded->content;
            if (!$preview) {
                $preview = match($forwarded->message_type) {
                    \App\Enums\MessageType::Audio => '🎤 Voice note',
                    \App\Enums\MessageType::Image => '📷 Image',
                    \App\Enums\MessageType::File  => '📎 ' . ($forwarded->media_original_name ?? 'File'),
                    default => 'Forwarded',
                };
            }
            $targetRoom->update([
                'last_message_at' => now(),
                'last_message_preview' => Str::limit($preview, 100),
                'last_message_user_id' => $user->id,
                'messages_count' => $targetRoom->messages_count + 1,
            ]);
        }

        try {
            broadcast(new \App\Events\MessageSent($forwarded))->toOthers();
        } catch (\Throwable $e) {
            \Log::warning('Broadcast failed: ' . $e->getMessage());
        }

        return $targetRoomId;
    }

    // ─── BROADCAST LISTENER ───

    public function onMessageReceived($data)
    {
        unset($this->roomMessages);
        $this->dispatch('message-sent');
        // Note: markAsRead is called separately via delayedMarkRead
        // to avoid a race condition with RoomList's unread badge computation.
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

        $userLang = auth()->user()?->language_pref ?? 'en';
        $summary = $aiService->summariseThread($recentMessages, $userLang);

        if ($summary) {
            $this->dispatch('thread-summary-ready', summary: $summary);
        }
    }

    // ─── HELPERS ───

    protected function markAsRead()
    {
        if (!isset($this->room) || !$this->room->exists) {
            return;
        }

        YardRoomMember::where('room_id', $this->room->id)
            ->where('user_id', auth()->id())
            ->update(['last_read_at' => now()]);

        // Per-message read receipts (WhatsApp blue ticks).
        app(ReceiptService::class)->markRoomRead($this->room, auth()->id());
    }

    /**
     * Called by the recipient's browser when it receives a MessageSent broadcast.
     * Records that the message was DELIVERED (double grey ticks for the sender).
     */
    #[On('mark-message-delivered')]
    public function markMessageDelivered(int $messageId): void
    {
        if (!isset($this->room) || !$this->room->exists) {
            return;
        }

        $message = YardMessage::where('id', $messageId)
            ->where('room_id', $this->room->id)
            ->first();

        if (!$message) {
            return;
        }

        app(ReceiptService::class)->markDelivered($message, auth()->id());
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
