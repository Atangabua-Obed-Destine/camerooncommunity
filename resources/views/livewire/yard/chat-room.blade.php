<div class="yard-chat"
     x-data="chatUi()"
     x-on:message-sent.window="optimistic=[]; scrollToBottom()"
     x-on:optimistic-msg.window="optimistic.push({ id: ++_optId, text: $event.detail.text }); scrollToBottom()"
     x-on:focus-edit-input.window="$nextTick(() => { if($refs.editInput) $refs.editInput.focus() })"
     x-on:echo-subscribe.window="subscribeEcho($event.detail.channel)"
     @keydown.escape.window="ctxClose()"
     x-init="@if(isset($room) && $room->exists) subscribeEcho('{{ 'tenant.' . $room->tenant_id . '.room.' . $room->id }}') @endif">

    {{-- Loading overlay for room switching --}}
    <div wire:loading.flex wire:target="loadRoom" class="yard-chat__loading">
        <div class="yard-upload-spinner" style="width:24px;height:24px;border-width:3px"></div>
    </div>

    @if(isset($room) && $room->exists)

    {{-- ── Chat Header ── --}}
    <header class="yard-chat__header">
        <button class="yard-chat__back"
                @click="$dispatch('room-selected', { roomId: null }); if (window.innerWidth < 768) history.back()">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </button>

        <div class="yard-chat__header-avatar {{ match($room->room_type->value) { 'national' => 'bg-cm-green', 'regional' => '!bg-amber-500', 'city' => '!bg-blue-500', 'private_group' => '!bg-violet-500', default => 'bg-cm-green' } }}">
            @if($room->room_type === \App\Enums\RoomType::DirectMessage)
                @php $dmPartner = $room->members()->where('user_id', '!=', auth()->id())->with('user:id,name,username,avatar')->first()?->user; @endphp
                @if($dmPartner?->avatar)
                    <img src="{{ asset('storage/' . $dmPartner->avatar) }}" alt="" class="w-full h-full rounded-full object-cover">
                @else
                    <span class="text-sm font-bold text-white">{{ strtoupper(substr($dmPartner?->username ?? $dmPartner?->name ?? '?', 0, 1)) }}</span>
                @endif
                @if($this->dmPartnerStatus && $this->dmPartnerStatus['is_online'])
                    <span class="yard-online-dot yard-online-dot--header"></span>
                @endif
            @else
                @switch($room->room_type)
                    @case(\App\Enums\RoomType::National)     <span>🇨🇲</span> @break
                    @case(\App\Enums\RoomType::Regional)     <span>🌍</span> @break
                    @case(\App\Enums\RoomType::City)         <span>📍</span> @break
                    @case(\App\Enums\RoomType::PrivateGroup) <span>👥</span> @break
                    @default                                 <span>💬</span>
                @endswitch
            @endif
        </div>

        <div class="yard-chat__header-info" @click="$dispatch('toggle-room-info')">
            <h2 class="yard-chat__header-name">
                @if($room->room_type === \App\Enums\RoomType::DirectMessage)
                    @php $dmUser = $room->members()->where('user_id', '!=', auth()->id())->first()?->user; @endphp
                    {{ $dmUser?->username ?? $dmUser?->name ?? $room->name }}
                @else
                    {{ $room->name }}
                @endif
            </h2>
            <p class="yard-chat__header-meta">
                <template x-if="typingUsers.length > 0">
                    <span class="yard-typing-text" x-text="typingLabel()"></span>
                </template>
                <template x-if="typingUsers.length === 0">
                    @if($room->room_type === \App\Enums\RoomType::DirectMessage)
                        @php $dmStatus = $this->dmPartnerStatus; @endphp
                        @if($dmStatus && $dmStatus['is_online'])
                            <span class="yard-status yard-status--online" x-text="$store.lang.t('online', 'en ligne')"></span>
                        @elseif($dmStatus && $dmStatus['last_active_at'])
                            <span class="yard-status yard-status--lastseen" x-data="lastSeen('{{ $dmStatus['last_active_at'] }}')" x-text="label"></span>
                        @else
                            <span class="yard-status yard-status--lastseen" x-text="$store.lang.t('offline', 'hors ligne')"></span>
                        @endif
                    @else
                        <span>{{ $room->members_count }} <span x-text="$store.lang.t('members', 'membres')"></span></span>
                    @endif
                </template>
            </p>
        </div>

        <div class="yard-chat__header-actions">
            {{-- Voice Call --}}
            <button class="yard-chat__header-btn" @click="$dispatch('initiate-call', { roomId: {{ $room->id }}, type: 'voice' })" title="Voice Call">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
            </button>
            {{-- Video Call --}}
            <button class="yard-chat__header-btn" @click="$dispatch('initiate-call', { roomId: {{ $room->id }}, type: 'video' })" title="Video Call">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25z"/></svg>
            </button>
            {{-- Search --}}
            <button class="yard-chat__header-btn" wire:click="toggleSearch" title="Search">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
            </button>
            {{-- Refresh --}}
            <button class="yard-chat__header-btn" @click="$wire.$refresh(); Livewire.dispatch('refreshRoomList')" title="Refresh">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182M21.015 4.356v4.992"/></svg>
            </button>
            <button class="yard-chat__header-btn" @click="$dispatch('toggle-room-info')" title="Info">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z"/></svg>
            </button>
        </div>
    </header>

    {{-- ── Search Bar ── --}}
    @if($searchActive)
    <div class="yard-chat__search-bar">
        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
        <input type="text" wire:model.live.debounce.300ms="messageSearch"
               class="yard-chat__search-input"
               placeholder="{{ __('Search messages...') }}" autofocus>
        <button wire:click="toggleSearch" class="text-slate-400 hover:text-slate-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    @endif

    {{-- ── Pinned Messages Bar ── --}}
    @if($pinnedMessages->count() > 0)
    <div class="yard-chat__pinned-bar" x-data="{ expanded: false }">
        <button @click="expanded = !expanded" class="yard-chat__pinned-toggle">
            <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
            <span class="text-xs font-medium text-slate-700">{{ $pinnedMessages->count() }} {{ __('pinned') }}</span>
            <span class="text-xs text-slate-500 truncate flex-1">— {{ Str::limit($pinnedMessages->first()->content, 40) }}</span>
            <svg class="w-3 h-3 text-slate-400 transition-transform" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="expanded" x-collapse class="yard-chat__pinned-list">
            @foreach($pinnedMessages as $pin)
            <div class="yard-chat__pinned-item">
                <span class="font-medium text-xs text-cm-green">{{ $pin->user?->username ?? $pin->user?->name }}</span>
                <span class="text-xs text-slate-600 truncate">{{ Str::limit($pin->content, 60) }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Thread Summary ── --}}
    <div x-data="{ summary: '', loading: false }"
         x-on:thread-summary-ready.window="summary = $event.detail.summary; loading = false"
         class="relative">
        @if(($roomMessages->count() ?? 0) >= 10)
        <div class="flex items-center gap-2 px-4 py-2 border-b border-slate-100">
            <button @click="loading = true; summary = ''; $wire.summariseThread()"
                    :disabled="loading"
                    class="flex items-center gap-1.5 text-xs font-medium text-purple-600 hover:text-purple-700 disabled:opacity-50 transition-colors">
                <span x-show="!loading">🤖</span>
                <span x-show="loading" class="yard-upload-spinner" style="width:12px;height:12px;border-width:2px"></span>
                <span x-text="loading ? ($store.lang?.t?.('Summarising...', 'Résumé en cours...') || 'Summarising...') : ($store.lang?.t?.('Summarise chat', 'Résumer le chat') || 'Summarise chat')"></span>
            </button>
        </div>
        @endif
        <div x-show="summary" x-transition x-cloak class="mx-4 mt-2 mb-1 p-3 bg-purple-50 rounded-lg border border-purple-100">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="text-[10px] text-purple-500 font-semibold mb-1">🤖 Kamer Summary</p>
                    <p class="text-xs text-slate-700 leading-relaxed" x-text="summary"></p>
                </div>
                <button @click="summary = ''" class="text-slate-400 hover:text-slate-600 shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- ── Messages Area ── --}}
    <div class="yard-chat__messages" id="chat-messages"
         x-ref="chatMessages"
         x-init="scrollToBottom()">

        @if($hasMore && $roomMessages->count() >= $perPage)
        <div class="text-center py-3">
            <button wire:click="loadMore" class="yard-chat__load-more">
                <span x-text="$store.lang.t('Load older messages', 'Charger les anciens messages')"></span>
            </button>
        </div>
        @endif

        @php $lastDate = null; @endphp

        @forelse($roomMessages as $msg)
            {{-- Date separator --}}
            @if($lastDate !== $msg->created_at->toDateString())
                @php $lastDate = $msg->created_at->toDateString(); @endphp
                <div class="yard-chat__date">
                    <span>{{ $msg->created_at->isToday() ? __('Today') : ($msg->created_at->isYesterday() ? __('Yesterday') : $msg->created_at->format('M j, Y')) }}</span>
                </div>
            @endif

            {{-- System message --}}
            @if($msg->message_type === \App\Enums\MessageType::System)
                <div class="yard-chat__system" wire:key="msg-{{ $msg->id }}">{{ $msg->content }}</div>

            {{-- Call log message --}}
            @elseif($msg->message_type === \App\Enums\MessageType::CallLog)
                @php
                    $callData = json_decode($msg->content, true) ?? [];
                    $callOutcome = $callData['outcome'] ?? 'ended';
                    $callDuration = $callData['duration'] ?? 0;
                    $callLogType = $callData['call_type'] ?? 'voice';
                    $isCallOwn = $msg->user_id === auth()->id();
                    $isMissed = in_array($callOutcome, ['missed', 'declined']);
                @endphp
                <div class="yard-call-log {{ $isCallOwn ? 'yard-call-log--own' : '' }} {{ $isMissed ? 'yard-call-log--missed' : '' }}"
                     wire:key="msg-{{ $msg->id }}">
                    <div class="yard-call-log__icon">
                        @if($callLogType === 'video')
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25z"/></svg>
                        @else
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25z"/></svg>
                        @endif
                        @if($isMissed)
                            <span class="yard-call-log__arrow yard-call-log__arrow--missed">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 4.5l-15 15"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 4.5L12 4.5M19.5 4.5v7.5"/></svg>
                            </span>
                        @elseif($isCallOwn)
                            <span class="yard-call-log__arrow yard-call-log__arrow--outgoing">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15"/><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5L12 19.5M4.5 19.5V12"/></svg>
                            </span>
                        @else
                            <span class="yard-call-log__arrow yard-call-log__arrow--incoming">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 4.5l-15 15"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 4.5L12 4.5M19.5 4.5v7.5"/></svg>
                            </span>
                        @endif
                    </div>
                    <div class="yard-call-log__info">
                        <span class="yard-call-log__label">
                            @if($isMissed && !$isCallOwn)
                                <span x-text="$store.lang.t('Missed {{ $callLogType }} call', 'Appel {{ $callLogType === 'video' ? 'vidéo' : 'vocal' }} manqué')"></span>
                            @elseif($callOutcome === 'declined')
                                <span x-text="$store.lang.t('Declined {{ $callLogType }} call', 'Appel {{ $callLogType === 'video' ? 'vidéo' : 'vocal' }} refusé')"></span>
                            @elseif($isCallOwn)
                                <span x-text="$store.lang.t('Outgoing {{ $callLogType }} call', 'Appel {{ $callLogType === 'video' ? 'vidéo' : 'vocal' }} sortant')"></span>
                            @else
                                <span x-text="$store.lang.t('Incoming {{ $callLogType }} call', 'Appel {{ $callLogType === 'video' ? 'vidéo' : 'vocal' }} entrant')"></span>
                            @endif
                        </span>
                        <span class="yard-call-log__meta">
                            {{ $msg->created_at->format('H:i') }}
                            @if($callOutcome === 'ended' && $callDuration > 0)
                                · {{ $callDuration < 60 ? $callDuration . 's' : intdiv($callDuration, 60) . ':' . str_pad($callDuration % 60, 2, '0', STR_PAD_LEFT) }}
                            @endif
                        </span>
                    </div>
                    @if($callOutcome === 'ended' || $isCallOwn)
                        <button class="yard-call-log__callback"
                                @click="$dispatch('initiate-call', { roomId: {{ $msg->room_id }}, type: '{{ $callLogType }}' })"
                                title="{{ $callLogType === 'video' ? 'Video call' : 'Voice call' }}">
                            @if($callLogType === 'video')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25z"/></svg>
                            @else
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25z"/></svg>
                            @endif
                        </button>
                    @endif
                </div>

            {{-- Deleted message --}}
            @elseif($msg->is_deleted)
                <div class="yard-msg {{ $msg->user_id === auth()->id() ? 'yard-msg--own' : '' }}" wire:key="msg-{{ $msg->id }}">
                    <div class="yard-msg__bubble yard-msg__bubble--deleted">
                        <span x-text="$store.lang.t('This message was deleted', 'Ce message a été supprimé')"></span>
                    </div>
                </div>

            {{-- Normal message --}}
            @else
                @php $isOwn = $msg->user_id === auth()->id(); @endphp
                <div class="yard-msg {{ $isOwn ? 'yard-msg--own' : '' }}" id="msg-{{ $msg->id }}" wire:key="msg-{{ $msg->id }}">

                    {{-- Avatar (other users only) --}}
                    @unless($isOwn)
                    <button class="yard-msg__avatar" @click="showUserProfile({{ $msg->user_id }}, '{{ e($msg->user?->username ?? $msg->user?->name ?? '?') }}', '{{ e($msg->user?->avatar ? asset('storage/' . $msg->user->avatar) : '') }}')">
                        @if($msg->user?->avatar)
                            <img src="{{ asset('storage/' . $msg->user->avatar) }}" alt="" class="w-full h-full rounded-full object-cover">
                        @else
                            {{ strtoupper(substr($msg->user?->username ?? $msg->user?->name ?? '?', 0, 1)) }}
                        @endif
                    </button>
                    @endunless

                    <div class="yard-msg__content {{ $isOwn ? 'items-end' : 'items-start' }}">
                        {{-- Sender name --}}
                        @unless($isOwn)
                        <button class="yard-msg__sender" @click="showUserProfile({{ $msg->user_id }}, '{{ e($msg->user?->username ?? $msg->user?->name ?? '?') }}', '{{ e($msg->user?->avatar ? asset('storage/' . $msg->user->avatar) : '') }}')">{{ $msg->user?->username ?? $msg->user?->name ?? 'Unknown' }}</button>
                        @endunless

                        {{-- Reply preview --}}
                        @if($msg->parent)
                        <div class="yard-msg__reply-preview">
                            <span class="font-semibold">{{ $msg->parent->user?->username ?? $msg->parent->user?->name }}</span>:
                            {{ \Illuminate\Support\Str::limit($msg->parent->content, 60) }}
                        </div>
                        @endif

                        {{-- Pinned badge --}}
                        @if($msg->is_pinned)
                        <div class="yard-msg__pin-badge">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                            <span x-text="$store.lang.t('Pinned', 'Épinglé')"></span>
                        </div>
                        @endif

                        {{-- Edit mode (inline) --}}
                        @if($editingMessageId === $msg->id)
                        <div class="yard-msg__edit-wrap">
                            <textarea wire:model="editContent" x-ref="editInput"
                                      class="yard-msg__edit-input" rows="2"
                                      @keydown.enter.prevent="$wire.saveEdit()"
                                      @keydown.escape="$wire.cancelEdit()"></textarea>
                            <div class="flex gap-1.5 mt-1">
                                <button wire:click="saveEdit" class="yard-msg__edit-btn yard-msg__edit-btn--save">✓</button>
                                <button wire:click="cancelEdit" class="yard-msg__edit-btn yard-msg__edit-btn--cancel">✕</button>
                            </div>
                        </div>
                        @else

                        {{-- Message bubble --}}
                        <div class="yard-msg__bubble {{ $isOwn ? 'yard-msg__bubble--own' : 'yard-msg__bubble--other' }}"
                             x-data="{ hover: false, emojiPick: false }"
                             @mouseenter="hover = true" @mouseleave="if (!emojiPick) hover = false"
                             @click.outside="emojiPick = false; hover = false"
                             @contextmenu.prevent="ctxOpen({ msgId: {{ $msg->id }}, isOwn: {{ $isOwn ? 'true' : 'false' }}, msgType: '{{ $msg->message_type->value }}', content: {{ json_encode($msg->content ?? '') }}, isPinned: {{ $msg->is_pinned ? 'true' : 'false' }}, x: $event.clientX, y: $event.clientY })">

                            {{-- Dropdown arrow on top of bubble (WhatsApp-style) --}}
                            <button x-show="hover" x-transition.opacity.duration.150ms
                                    class="yard-msg__chevron {{ $isOwn ? 'yard-msg__chevron--own' : '' }}"
                                    @click.stop="ctxOpen({ msgId: {{ $msg->id }}, isOwn: {{ $isOwn ? 'true' : 'false' }}, msgType: '{{ $msg->message_type->value }}', content: {{ json_encode($msg->content ?? '') }}, isPinned: {{ $msg->is_pinned ? 'true' : 'false' }}, x: $event.target.getBoundingClientRect().x, y: $event.target.getBoundingClientRect().bottom })">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </button>

                            {{-- Emoji reaction button beside bubble --}}
                            <div x-show="hover" x-transition.opacity.duration.150ms
                                 class="yard-msg__hover-emoji {{ $isOwn ? 'yard-msg__hover-emoji--own' : '' }}">
                                <button class="yard-msg__hover-btn"
                                        @click.stop="emojiPick = !emojiPick">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0zM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75zm-.375 0h.008v.015h-.008V9.75zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75zm-.375 0h.008v.015h-.008V9.75z"/></svg>
                                </button>
                            </div>

                            {{-- Quick emoji picker (appears above/below bubble) --}}
                            <div x-show="emojiPick" x-transition.scale.90.origin.bottom
                                 @click.stop
                                 class="yard-msg__quick-emoji {{ $isOwn ? 'yard-msg__quick-emoji--own' : '' }}" x-cloak>
                                @foreach(['👍','❤️','😂','😮','😢','🙏'] as $em)
                                <button class="yard-msg__quick-emoji-btn"
                                        wire:click="toggleReaction({{ $msg->id }}, '{{ $em }}')"
                                        @click="emojiPick = false">{{ $em }}</button>
                                @endforeach
                                <button class="yard-msg__quick-emoji-btn yard-msg__quick-emoji-btn--plus"
                                        @click="emojiPick = false; ctxOpen({ msgId: {{ $msg->id }}, isOwn: {{ $isOwn ? 'true' : 'false' }}, msgType: '{{ $msg->message_type->value }}', content: {{ json_encode($msg->content ?? '') }}, isPinned: {{ $msg->is_pinned ? 'true' : 'false' }}, x: $event.target.getBoundingClientRect().x, y: $event.target.getBoundingClientRect().bottom })">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                </button>
                            </div>

                            {{-- Forwarded label --}}
                            @if($msg->is_forwarded)
                            <div class="yard-msg__forwarded">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10l9-7v5c8 0 12 5 12 12-2.5-4-6-6-12-6v5L3 10z"/></svg>
                                <span x-text="$store.lang.t('Forwarded', 'Transféré')"></span>
                            </div>
                            @endif

                            {{-- Content by type --}}
                            @switch($msg->message_type)
                                @case(\App\Enums\MessageType::Text)
                                    <p class="whitespace-pre-wrap">{!! nl2br(e($msg->content)) !!}</p>
                                    @break
                                @case(\App\Enums\MessageType::Image)
                                    @if($msg->media_path)
                                    <div class="yard-img-bubble">
                                        <img src="{{ asset('storage/' . $msg->media_path) }}" alt=""
                                             class="yard-img-bubble__img" loading="lazy"
                                             @click="openLightbox('{{ asset('storage/' . $msg->media_path) }}')">
                                        @if($msg->content)
                                        <p class="yard-img-bubble__caption">{!! nl2br(e($msg->content)) !!}</p>
                                        @endif
                                    </div>
                                    @endif
                                    @break
                                @case(\App\Enums\MessageType::Audio)
                                    <div class="yard-audio" x-data="audioPlayer()" x-ref="audioWrap">
                                        {{-- Speed toggle --}}
                                        <button class="yard-audio__speed {{ $isOwn ? 'yard-audio__speed--own' : '' }}" @click="cycleSpeed($refs.aud{{ $msg->id }})" x-text="speedLabel">1×</button>

                                        {{-- Play / Pause --}}
                                        <button class="yard-audio__play" @click="toggle($refs.aud{{ $msg->id }})">
                                            <svg x-show="!playing" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                            <svg x-show="playing" x-cloak class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 4h4v16H6zm8 0h4v16h-4z"/></svg>
                                        </button>

                                        {{-- Waveform + Seek --}}
                                        <div class="yard-audio__wave-wrap" @click="seek($event, $refs.aud{{ $msg->id }})">
                                            <div class="yard-audio__wave">
                                                @for($b = 0; $b < 36; $b++)
                                                <span class="yard-audio__bar {{ $isOwn ? 'yard-audio__bar--own' : '' }}"
                                                      style="height:{{ rand(20, 100) }}%"
                                                      :class="progress > {{ round(($b / 36) * 100, 1) }} && 'yard-audio__bar--played'"></span>
                                                @endfor
                                            </div>
                                            {{-- Seek dot --}}
                                            <div class="yard-audio__dot {{ $isOwn ? 'yard-audio__dot--own' : '' }}" :style="'left:'+progress+'%'"></div>
                                        </div>

                                        {{-- Time --}}
                                        <span class="yard-audio__time" x-text="timeLabel">0:00</span>

                                        <audio x-ref="aud{{ $msg->id }}" src="{{ asset('storage/' . $msg->media_path) }}"
                                               @ended="onEnded($refs.aud{{ $msg->id }})"
                                               @timeupdate="onTime($event)"
                                               @loadedmetadata="probeDuration($refs.aud{{ $msg->id }})"
                                               x-on:error="console.error('Audio load error:', $refs.aud{{ $msg->id }}.src, $refs.aud{{ $msg->id }}.error)"
                                               preload="metadata"></audio>
                                    </div>
                                    @break
                                @case(\App\Enums\MessageType::File)
                                    @php
                                        $ext = strtolower(pathinfo($msg->media_original_name ?? '', PATHINFO_EXTENSION));
                                        $docIcons = ['pdf'=>'📕','doc'=>'📘','docx'=>'📘','xlsx'=>'📗','xls'=>'📗','csv'=>'📗','pptx'=>'📙','ppt'=>'📙','txt'=>'📝','zip'=>'🗜️','rar'=>'🗜️'];
                                        $docIcon = $docIcons[$ext] ?? '📄';
                                        $sizeLabel = $msg->media_size ? ($msg->media_size < 1048576 ? number_format($msg->media_size / 1024, 1).' KB' : number_format($msg->media_size / 1048576, 1).' MB') : '';
                                    @endphp
                                    <div class="yard-doc-bubble">
                                        <a href="{{ asset('storage/' . $msg->media_path) }}" target="_blank" download
                                           class="yard-doc-bubble__card {{ $isOwn ? 'yard-doc-bubble__card--own' : '' }}">
                                            <div class="yard-doc-bubble__icon">{{ $docIcon }}</div>
                                            <div class="yard-doc-bubble__info">
                                                <p class="yard-doc-bubble__name">{{ $msg->media_original_name ?? 'File' }}</p>
                                                <p class="yard-doc-bubble__meta">
                                                    {{ $sizeLabel }}
                                                    @if($ext) · {{ strtoupper($ext) }} @endif
                                                </p>
                                            </div>
                                            <svg class="yard-doc-bubble__dl" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        </a>
                                        @if($msg->content)
                                        <p class="yard-doc-bubble__caption">{!! nl2br(e($msg->content)) !!}</p>
                                        @endif
                                    </div>
                                    @break
                                @case(\App\Enums\MessageType::Gif)
                                    <img src="{{ $msg->content }}" alt="GIF" class="rounded-lg max-w-[260px]" loading="lazy">
                                    @break
                                @case(\App\Enums\MessageType::SolidarityCard)
                                    @if($msg->solidarityCampaign)
                                        @include('livewire.yard._solidarity-card', ['campaign' => $msg->solidarityCampaign])
                                    @endif
                                    @break
                                @case(\App\Enums\MessageType::Poll)
                                    @php
                                        $poll = $msg->poll;
                                        $myId = auth()->id();
                                        $totalVotes = $poll ? (int) $poll->options->sum('votes_count') : 0;
                                        $myVotes = collect();
                                        if ($poll) {
                                            $myVotes = \DB::table('yard_poll_votes')
                                                ->where('poll_id', $poll->id)
                                                ->where('user_id', $myId)
                                                ->pluck('option_id');
                                        }
                                    @endphp
                                    @if($poll)
                                    <div class="yard-poll {{ $isOwn ? 'yard-poll--own' : '' }}">
                                        <div class="yard-poll__header">
                                            <span class="yard-poll__icon">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 19V11m6 8V5m6 14v-6"/></svg>
                                            </span>
                                            <span class="yard-poll__label">
                                                {{ $poll->allow_multiple ? 'Poll · Select one or more' : 'Poll · Select one' }}
                                            </span>
                                            @if($poll->is_closed)
                                                <span class="yard-poll__closed">Closed</span>
                                            @endif
                                        </div>
                                        <p class="yard-poll__question">{{ $poll->question }}</p>
                                        <div class="yard-poll__options">
                                            @foreach($poll->options as $opt)
                                                @php
                                                    $pct = $totalVotes > 0 ? round(($opt->votes_count / $totalVotes) * 100) : 0;
                                                    $voted = $myVotes->contains($opt->id);
                                                @endphp
                                                <button type="button"
                                                        class="yard-poll__option {{ $voted ? 'yard-poll__option--voted' : '' }}"
                                                        @if(!$poll->is_closed) wire:click="votePoll({{ $opt->id }})" @endif
                                                        @if($poll->is_closed) disabled @endif>
                                                    <span class="yard-poll__bar" style="width: {{ $pct }}%"></span>
                                                    <span class="yard-poll__row">
                                                        <span class="yard-poll__check {{ $voted ? 'yard-poll__check--on' : '' }}">
                                                            @if($poll->allow_multiple)
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                                            @else
                                                                <span class="yard-poll__dot"></span>
                                                            @endif
                                                        </span>
                                                        <span class="yard-poll__text">{{ $opt->text }}</span>
                                                        <span class="yard-poll__count">{{ $opt->votes_count }}</span>
                                                    </span>
                                                    <span class="yard-poll__pct">{{ $pct }}%</span>
                                                </button>
                                            @endforeach
                                        </div>
                                        <div class="yard-poll__footer">
                                            <span>{{ $totalVotes }} {{ \Illuminate\Support\Str::plural('vote', $totalVotes) }}</span>
                                            <div class="yard-poll__footer-actions">
                                                @if($totalVotes > 0)
                                                    <button type="button" class="yard-poll__view-btn"
                                                            @click="$dispatch('open-poll-voters', { pollId: {{ $poll->id }} })">
                                                        View votes
                                                    </button>
                                                @endif
                                                @if(!$poll->is_closed && $poll->user_id === $myId)
                                                    <button type="button" class="yard-poll__close-btn"
                                                            wire:click="closePoll({{ $poll->id }})"
                                                            wire:confirm="End this poll? Voting will be locked.">
                                                        End poll
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                    @break
                                @default
                                    <p class="whitespace-pre-wrap">{!! nl2br(e($msg->content)) !!}</p>
                            @endswitch

                            {{-- Timestamp + edited badge --}}
                            <span class="yard-msg__time {{ $isOwn ? 'text-blue-800/60' : 'text-slate-400' }}">
                                {{ $msg->created_at->format('H:i') }}
                                @if($msg->is_edited) · <span x-text="$store.lang.t('edited', 'modifié')"></span> @endif
                            </span>

                        </div>

                        @endif {{-- end edit mode check --}}

                        {{-- Reactions --}}
                        @if(is_array($msg->reactions_count) && count($msg->reactions_count) > 0)
                        <div class="flex flex-wrap gap-1 {{ $isOwn ? 'justify-end' : '' }}">
                            @foreach($msg->reactions_count as $emoji => $count)
                            <button wire:click="toggleReaction({{ $msg->id }}, '{{ $emoji }}')"
                                    class="yard-msg__reaction">
                                {{ $emoji }} <span>{{ $count }}</span>
                            </button>
                            @endforeach
                        </div>
                        @endif

                        {{-- Translation --}}
                        <div x-data="{ translation: '' }"
                             x-on:translation-ready.window="if ($event.detail.messageId === {{ $msg->id }}) translation = $event.detail.text"
                             x-show="translation" x-transition class="yard-msg__translation {{ $isOwn ? 'text-right' : '' }}">
                            <p class="text-[10px] text-blue-500 font-medium mb-0.5">🌐 Translation</p>
                            <p class="text-xs text-slate-600" x-text="translation"></p>
                        </div>
                    </div>
                </div>
            @endif
        @empty
            <div class="yard-chat__empty">
                <div class="text-5xl mb-3">👋</div>
                <p x-text="$store.lang.t('No messages yet. Say hello!', 'Aucun message. Dites bonjour !')"></p>
            </div>
        @endforelse

        {{-- Optimistic messages (shown instantly, before server responds) --}}
        <template x-for="om in optimistic" :key="om.id">
            <div class="yard-msg yard-msg--own">
                <div class="yard-msg__row">
                    <div class="yard-msg__content">
                        <div class="yard-msg__bubble yard-msg--own__bubble opacity-70">
                            <p class="yard-msg__text whitespace-pre-wrap" x-text="om.text"></p>
                        </div>
                        <div class="yard-msg__meta">
                            <span class="yard-msg__time text-slate-400">{{ __('Sending...') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- ── Typing indicator ── --}}
    <div class="yard-chat__typing" x-show="typingUsers.length > 0" x-transition x-cloak>
        <div class="yard-typing-dots"><span></span><span></span><span></span></div>
        <span class="text-xs text-slate-500" x-text="typingLabel()"></span>
    </div>

    {{-- ── Reply bar ── --}}
    @if($replyToId)
    <div class="yard-chat__reply-bar">
        <div class="yard-chat__reply-accent"></div>
        <p class="flex-1 text-xs text-slate-600 truncate">{{ $replyToPreview }}</p>
        <button wire:click="cancelReply" class="text-slate-400 hover:text-slate-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    @endif

    {{-- ── Input Bar ── --}}
    <div class="yard-chat__input-bar" x-data="inputBar()" @message-sent.window="msgText = ''; if($refs.msgInput) { $refs.msgInput.value = ''; $refs.msgInput.style.height = 'auto'; }" @media-sent.window="closePreview()" @poll-created.window="closePoll()">
        {{-- Hidden file inputs --}}
        <input type="file" x-ref="photoInput" class="hidden" accept="image/*" wire:model="mediaUpload"
               @change="onFileSelected($event, 'image')">
        <input type="file" x-ref="docInput" class="hidden" accept=".pdf,.doc,.docx,.xlsx,.pptx,.txt,.csv,.zip" wire:model="mediaUpload"
               @change="onFileSelected($event, 'document')">
        <input type="file" x-ref="cameraInput" class="hidden" accept="image/*" capture="environment" wire:model="mediaUpload"
               @change="onFileSelected($event, 'image')">

        {{-- ══════════════════════════════════════════════════════════════
             WhatsApp-style Media Preview Overlay
             Shows selected image/document before sending with caption
             ══════════════════════════════════════════════════════════════ --}}
        <div x-show="preview.active" x-transition.opacity.duration.200ms
             class="yard-media-preview" x-cloak @click.self="closePreview()">

            {{-- Top bar: close + filename --}}
            <div class="yard-media-preview__topbar">
                <button type="button" @click="closePreview()" class="yard-media-preview__close">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <span class="yard-media-preview__filename" x-text="preview.fileName"></span>
            </div>

            {{-- Main preview area --}}
            <div class="yard-media-preview__body">
                {{-- Image preview --}}
                <template x-if="preview.type === 'image'">
                    <div class="yard-media-preview__img-wrap">
                        <img :src="preview.url" class="yard-media-preview__img" alt="Preview">
                    </div>
                </template>

                {{-- Document preview --}}
                <template x-if="preview.type === 'document'">
                    <div class="yard-media-preview__doc-card">
                        <div class="yard-media-preview__doc-icon" x-text="preview.fileIcon"></div>
                        <div class="yard-media-preview__doc-info">
                            <p class="yard-media-preview__doc-name" x-text="preview.fileName"></p>
                            <p class="yard-media-preview__doc-meta">
                                <span x-text="preview.fileSize"></span>
                                <span class="mx-1">·</span>
                                <span x-text="preview.fileExt?.toUpperCase()"></span>
                            </p>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Bottom bar: caption + send --}}
            <div class="yard-media-preview__bottom">
                <div class="yard-media-preview__caption-wrap">
                    <input type="text" x-model="preview.caption"
                           :placeholder="$store.lang.t('Add a caption...', 'Ajouter une légende...')"
                           class="yard-media-preview__caption"
                           maxlength="1000"
                           @keydown.enter.prevent="sendPreviewMedia()">
                </div>
                <button type="button" @click="sendPreviewMedia()" class="yard-media-preview__send"
                        wire:loading.attr="disabled" wire:target="mediaUpload,sendMedia">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                </button>
            </div>

            {{-- Upload progress spinner inside preview --}}
            <div wire:loading wire:target="mediaUpload" class="yard-media-preview__uploading">
                <div class="yard-upload-spinner yard-upload-spinner--light"></div>
                <span x-text="$store.lang.t('Uploading...', 'Envoi...')"></span>
            </div>
        </div>

        {{-- ══ Normal mode (text input) ══ --}}
        <form wire:submit="sendMessage" class="yard-chat__input-form" x-show="!recording && !preview.active" x-transition>
            {{-- Unified input pill: buttons + textarea all inside one rounded box --}}
            <div class="yard-chat__input-pill">
                {{-- Attachment button --}}
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" class="yard-chat__pill-btn"
                            x-bind:class="open && 'yard-chat__pill-btn--active'">
                        <svg class="w-5 h-5 transition-transform duration-200" :class="open && 'rotate-45'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    </button>

                    {{-- WhatsApp-style attachment menu (bottom-rising with colored icons) --}}
                    <div x-show="open" @click.away="open = false"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 translate-y-4 scale-95"
                         class="yard-chat__attach-menu">
                        <div class="yard-chat__attach-grid">
                            {{-- Camera --}}
                            <button type="button" class="yard-chat__attach-cell" @click="open = false; $refs.cameraInput.click()">
                                <span class="yard-chat__attach-icon yard-chat__attach-icon--camera">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                </span>
                                <span class="yard-chat__attach-label" x-text="$store.lang.t('Camera', 'Caméra')"></span>
                            </button>
                            {{-- Photo & Video --}}
                            <button type="button" class="yard-chat__attach-cell" @click="open = false; $refs.photoInput.click()">
                                <span class="yard-chat__attach-icon yard-chat__attach-icon--gallery">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                </span>
                                <span class="yard-chat__attach-label" x-text="$store.lang.t('Gallery', 'Galerie')"></span>
                            </button>
                            {{-- Document --}}
                            <button type="button" class="yard-chat__attach-cell" @click="open = false; $refs.docInput.click()">
                                <span class="yard-chat__attach-icon yard-chat__attach-icon--document">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </span>
                                <span class="yard-chat__attach-label" x-text="$store.lang.t('Document', 'Document')"></span>
                            </button>
                            {{-- Solidarity --}}
                            <button type="button" class="yard-chat__attach-cell" @click="open = false">
                                <span class="yard-chat__attach-icon yard-chat__attach-icon--solidarity">🤲</span>
                                <span class="yard-chat__attach-label" x-text="$store.lang.t('Solidarity', 'Solidarité')"></span>
                            </button>
                            {{-- Poll --}}
                            <button type="button" class="yard-chat__attach-cell" @click="open = false; pollOpen = true">
                                <span class="yard-chat__attach-icon yard-chat__attach-icon--poll">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 19V11m6 8V5m6 14v-6"/></svg>
                                </span>
                                <span class="yard-chat__attach-label" x-text="$store.lang.t('Poll', 'Sondage')"></span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Emoji button --}}
                <div class="relative">
                    <button type="button" @click="emojiOpen = !emojiOpen" class="yard-chat__pill-btn">😊</button>
                    <div x-show="emojiOpen" @click.away="emojiOpen = false" x-transition
                         class="yard-emoji-picker">
                        <div class="yard-emoji-picker__grid">
                            @foreach(['😀','😃','😄','😁','😅','😂','🤣','😊','😇','😍','🤩','😘','😗','😋','😝','🤑','🤗','🤭','🤫','🤔','😐','😏','😒','🙄','😬','😌','😔','😴','🤒','🤢','🥵','🥶','😎','🤓','🥳','😤','😡','🤬','😈','💀','💩','🤡','👻','👽','🤖','👍','👎','👏','🤝','🙏','✌️','🤞','🤟','🤘','👌','👋','✋','🖐️','👆','👇','👉','👈','❤️','🧡','💛','💚','💙','💜','🖤','🤍','💔','💕','💖','🔥','💯','🎉','🎊','🎈','🎁','🏆','💎','🌟','⭐','✨','🎵','📸','💰','🍕','🍔','🌍','🇨🇲'] as $em)
                            <button type="button" @click="insertEmoji('{{ $em }}')" class="yard-emoji-picker__item">{{ $em }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Text input --}}
                <textarea x-ref="msgInput"
                          x-model="msgText"
                          :placeholder="$store.lang.t('Type a message', 'Tapez un message')"
                          class="yard-chat__textarea"
                          rows="1"
                          x-on:input="$el.style.height='auto'; $el.style.height=Math.min($el.scrollHeight,120)+'px'; onTyping()"
                          x-on:keydown.enter.prevent="if(!$event.shiftKey && msgText.trim()){ let t=msgText; msgText=''; $el.value=''; $el.style.height='auto'; window.dispatchEvent(new CustomEvent('optimistic-msg',{detail:{text:t}})); $wire.sendMessage(t) }"
                          maxlength="4000"></textarea>

                {{-- Mic button inside pill (only when no text) --}}
                <button type="button" class="yard-chat__mic-btn"
                        @click.prevent="startRecording()"
                        x-show="!msgText">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path stroke-linecap="round" d="M19 10v2a7 7 0 01-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
                </button>
            </div>

            {{-- Send button outside pill (only when text) --}}
            <button type="submit" class="yard-chat__send-btn"
                    wire:loading.attr="disabled"
                    @click.prevent="if(msgText.trim()){ let t=msgText; msgText=''; if($refs.msgInput){ $refs.msgInput.value=''; $refs.msgInput.style.height='auto'; } window.dispatchEvent(new CustomEvent('optimistic-msg',{detail:{text:t}})); $wire.sendMessage(t) }"
                    x-show="msgText">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
            </button>
        </form>

        {{-- ══ Recording mode (WhatsApp-style) ══ --}}
        <div class="yard-voice-recorder" x-show="recording" x-transition x-cloak>
            {{-- Delete / discard --}}
            <button type="button" class="yard-voice-recorder__delete" @click.prevent="discardRecording()" title="Delete">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>

            {{-- Recording indicator: red dot + timer --}}
            <div class="yard-voice-recorder__indicator">
                <span class="yard-voice-recorder__dot"></span>
                <span class="yard-voice-recorder__timer" x-text="recTimerLabel">0:00</span>
            </div>

            {{-- Waveform visualizer --}}
            <div class="yard-voice-recorder__waveform">
                <canvas x-ref="waveCanvas" class="yard-voice-recorder__canvas" width="160" height="32"></canvas>
            </div>

            {{-- Pause / Resume --}}
            <button type="button" class="yard-voice-recorder__pause" @click.prevent="togglePauseRecording()">
                {{-- Pause icon --}}
                <svg x-show="!recPaused" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>
                {{-- Resume/play icon --}}
                <svg x-show="recPaused" x-cloak class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            </button>

            {{-- Send voice --}}
            <button type="button" class="yard-voice-recorder__send" @click.prevent="sendRecording()">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
            </button>
        </div>

        {{-- Upload progress (for audio uploads, which bypass preview) --}}
        <div wire:loading wire:target="mediaUpload" x-show="recording" class="yard-chat__upload-progress">
            <div class="yard-upload-spinner"></div>
            <span class="text-xs text-slate-500" x-text="$store.lang.t('Uploading...', 'Envoi...')"></span>
        </div>

        {{-- ══ Poll Builder Modal (WhatsApp-style) ══ --}}
        <div x-show="pollOpen" x-cloak
             x-transition.opacity
             @keydown.escape.window="closePoll()"
             class="yard-modal-overlay" style="z-index:300">
            <div class="yard-modal-overlay__backdrop" @click="closePoll()"></div>
            <div class="yard-modal-dialog yard-poll-builder"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0">
                <div class="yard-modal-dialog__header">
                    <span class="yard-modal-dialog__icon">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 19V11m6 8V5m6 14v-6"/></svg>
                    </span>
                    <span class="yard-modal-dialog__title" x-text="$store.lang.t('Create poll', 'Créer un sondage')"></span>
                    <button type="button" class="yard-modal-dialog__close" @click="closePoll()">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M6 18L18 6"/></svg>
                    </button>
                </div>

                <div class="yard-poll-builder__body">
                    {{-- Question --}}
                    <label class="yard-poll-builder__label" x-text="$store.lang.t('Question', 'Question')"></label>
                    <div class="yard-poll-builder__field">
                        <textarea x-model="pollDraft.question"
                                  maxlength="300"
                                  rows="2"
                                  :placeholder="$store.lang.t('Ask a question…', 'Posez une question…')"
                                  class="yard-poll-builder__input"></textarea>
                        <span class="yard-poll-builder__counter" x-text="(pollDraft.question || '').length + ' / 300'"></span>
                    </div>

                    {{-- Options --}}
                    <label class="yard-poll-builder__label" x-text="$store.lang.t('Options', 'Options')"></label>
                    <div class="yard-poll-builder__options">
                        <template x-for="(opt, i) in pollDraft.options" :key="i">
                            <div class="yard-poll-builder__option">
                                <span class="yard-poll-builder__handle">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="6" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="18" r="1.5"/></svg>
                                </span>
                                <input type="text"
                                       x-model="pollDraft.options[i]"
                                       maxlength="200"
                                       :placeholder="$store.lang.t('Option', 'Option') + ' ' + (i + 1)"
                                       class="yard-poll-builder__opt-input"
                                       @input="ensureOptionRow()">
                                <button type="button" class="yard-poll-builder__remove"
                                        x-show="pollDraft.options.length > 2"
                                        @click="removeOption(i)">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M6 18L18 6"/></svg>
                                </button>
                            </div>
                        </template>
                        <p class="yard-poll-builder__hint"
                           x-text="$store.lang.t('Add up to 12 options', 'Ajoutez jusqu’à 12 options')"></p>
                    </div>

                    {{-- Allow multiple toggle --}}
                    <label class="yard-poll-builder__toggle">
                        <span>
                            <strong x-text="$store.lang.t('Allow multiple answers', 'Autoriser plusieurs réponses')"></strong>
                            <small x-text="$store.lang.t('People can pick more than one option', 'Les personnes peuvent choisir plus d’une option')"></small>
                        </span>
                        <span class="yard-poll-builder__switch" :class="pollDraft.allowMultiple && 'yard-poll-builder__switch--on'"
                              @click="pollDraft.allowMultiple = !pollDraft.allowMultiple">
                            <span class="yard-poll-builder__switch-thumb"></span>
                        </span>
                    </label>
                </div>

                <div class="yard-modal-dialog__footer">
                    <button type="button" class="yard-modal-dialog__btn yard-modal-dialog__btn--ghost"
                            @click="closePoll()" x-text="$store.lang.t('Cancel', 'Annuler')"></button>
                    <button type="button" class="yard-modal-dialog__btn yard-modal-dialog__btn--primary"
                            :disabled="!canSendPoll()"
                            @click="sendPoll()">
                        <span x-text="$store.lang.t('Send', 'Envoyer')"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ══ Poll Voters Modal (WhatsApp-style "View votes") ══ --}}
        <div x-show="votersOpen" x-cloak
             x-transition.opacity
             @open-poll-voters.window="openVoters($event.detail.pollId)"
             @keydown.escape.window="closeVoters()"
             class="yard-modal-overlay" style="z-index:310">
            <div class="yard-modal-overlay__backdrop" @click="closeVoters()"></div>
            <div class="yard-modal-dialog yard-poll-voters"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0">
                <div class="yard-modal-dialog__header">
                    <span class="yard-modal-dialog__icon">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 19V11m6 8V5m6 14v-6"/></svg>
                    </span>
                    <span class="yard-modal-dialog__title" x-text="$store.lang.t('Poll results', 'Résultats du sondage')"></span>
                    <button type="button" class="yard-modal-dialog__close" @click="closeVoters()">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M6 18L18 6"/></svg>
                    </button>
                </div>

                <div class="yard-poll-voters__body">
                    {{-- Loading --}}
                    <template x-if="votersLoading">
                        <div class="yard-poll-voters__loading">
                            <div class="yard-upload-spinner"></div>
                            <span x-text="$store.lang.t('Loading…', 'Chargement…')"></span>
                        </div>
                    </template>

                    {{-- Content --}}
                    <template x-if="!votersLoading && votersData">
                        <div>
                            <p class="yard-poll-voters__question" x-text="votersData.question"></p>
                            <p class="yard-poll-voters__meta">
                                <span x-text="votersData.totalVotes + ' ' + (votersData.totalVotes === 1 ? 'vote' : 'votes')"></span>
                                <span x-show="votersData.allowMultiple"> · <span x-text="$store.lang.t('Multiple answers', 'Réponses multiples')"></span></span>
                                <span x-show="votersData.isClosed"> · <span x-text="$store.lang.t('Closed', 'Fermé')"></span></span>
                            </p>

                            <div class="yard-poll-voters__list">
                                <template x-for="opt in votersData.options" :key="opt.id">
                                    <div class="yard-poll-voters__group">
                                        <div class="yard-poll-voters__opt-head">
                                            <span class="yard-poll-voters__opt-text" x-text="opt.text"></span>
                                            <span class="yard-poll-voters__opt-count">
                                                <span x-text="opt.votes_count"></span>
                                                <small x-text="'(' + opt.pct + '%)'"></small>
                                            </span>
                                        </div>
                                        <div class="yard-poll-voters__opt-bar">
                                            <span class="yard-poll-voters__opt-fill" :style="'width:' + opt.pct + '%'"></span>
                                        </div>
                                        <template x-if="opt.voters.length === 0">
                                            <p class="yard-poll-voters__empty" x-text="$store.lang.t('No votes yet', 'Aucun vote')"></p>
                                        </template>
                                        <template x-if="opt.voters.length > 0">
                                            <ul class="yard-poll-voters__people">
                                                <template x-for="voter in opt.voters" :key="voter.id">
                                                    <li class="yard-poll-voters__person">
                                                        <span class="yard-poll-voters__avatar"
                                                              x-html="voter.avatar
                                                                  ? '<img src=&quot;' + voter.avatar + '&quot; alt=&quot;&quot;>'
                                                                  : (voter.name || voter.username || '?').charAt(0).toUpperCase()"></span>
                                                        <span class="yard-poll-voters__name">
                                                            <strong x-text="voter.name || voter.username"></strong>
                                                            <small x-show="voter.username" x-text="'@' + voter.username"></small>
                                                        </span>
                                                    </li>
                                                </template>
                                            </ul>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="yard-modal-dialog__footer">
                    <button type="button" class="yard-modal-dialog__btn yard-modal-dialog__btn--ghost"
                            @click="closeVoters()" x-text="$store.lang.t('Close', 'Fermer')"></button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         WhatsApp-style Context Menu + Quick Emoji Bar
         (state lives in chatUi() — accessed via ctx.*)
         ══════════════════════════════════════════════════════════════════ --}}

    {{-- Translucent backdrop --}}
    <div x-show="ctx.open" x-transition.opacity.duration.150ms
         class="yard-ctx-backdrop" @click.stop="ctxClose()" x-cloak></div>

    {{-- Context menu container --}}
    <div x-show="ctx.open" x-transition.scale.80.origin.top
         class="yard-ctx-menu"
         :style="'top:'+ctx.posY+'px; left:'+ctx.posX+'px'"
         @click.stop x-cloak>

        {{-- Quick emoji reaction bar --}}
        <div class="yard-ctx-emoji-bar">
            <template x-for="em in ctx.quickEmojis" :key="em">
                <button class="yard-ctx-emoji-btn"
                        @click="ctxReact(em)"
                        x-text="em"></button>
            </template>
            <button class="yard-ctx-emoji-btn yard-ctx-emoji-btn--more"
                    @click="ctx.moreEmojis = !ctx.moreEmojis">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            </button>
        </div>

        {{-- Extended emoji grid (toggled) --}}
        <div x-show="ctx.moreEmojis" x-transition class="yard-ctx-emoji-grid">
            <template x-for="em in ctx.extraEmojis" :key="em">
                <button class="yard-ctx-emoji-grid__item"
                        @click="ctxReact(em)"
                        x-text="em"></button>
            </template>
        </div>

        {{-- Menu items --}}
        <div class="yard-ctx-items">
            {{-- Reply --}}
            <button class="yard-ctx-item" @click="ctxAction('reply')">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                <span x-text="$store.lang.t('Reply', 'Répondre')"></span>
            </button>

            {{-- Copy (text messages only) --}}
            <button class="yard-ctx-item" x-show="ctx.msgType === 'text'" @click="ctxCopy()">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                <span x-text="$store.lang.t('Copy', 'Copier')"></span>
            </button>

            {{-- Forward --}}
            <button class="yard-ctx-item" @click="ctxAction('forward')">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"/></svg>
                <span x-text="$store.lang.t('Forward', 'Transférer')"></span>
            </button>

            {{-- Star / Unstar --}}
            <button class="yard-ctx-item" @click="ctxAction('star')">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5z"/></svg>
                <span x-text="$store.lang.t('Star', 'Favori')"></span>
            </button>

            {{-- Pin / Unpin --}}
            <button class="yard-ctx-item" @click="ctxAction('pin')">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                <span x-text="ctx.isPinned ? $store.lang.t('Unpin', 'Désépingler') : $store.lang.t('Pin', 'Épingler')"></span>
            </button>

            {{-- Edit (own text messages only) --}}
            <button class="yard-ctx-item" x-show="ctx.isOwn && ctx.msgType === 'text'" @click="ctxAction('edit')">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path stroke-linecap="round" d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                <span x-text="$store.lang.t('Edit', 'Modifier')"></span>
            </button>

            {{-- Translate to English --}}
            <button class="yard-ctx-item" x-show="ctx.msgType === 'text'" @click="ctxAction('translate-en')">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/></svg>
                <span x-text="$store.lang.t('Translate to English', 'Traduire en Anglais')"></span>
            </button>
            {{-- Translate to French --}}
            <button class="yard-ctx-item" x-show="ctx.msgType === 'text'" @click="ctxAction('translate-fr')">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/></svg>
                <span x-text="$store.lang.t('Translate to French', 'Traduire en Français')"></span>
            </button>

            <div class="yard-ctx-separator"></div>

            {{-- Report (other's messages) --}}
            <button class="yard-ctx-item" x-show="!ctx.isOwn" @click="ctxAction('report')">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 9v2m0 4h.01M5.07 19H19a2.12 2.12 0 001.83-3.14L13.83 4.23a2.12 2.12 0 00-3.66 0L3.24 15.86A2.12 2.12 0 005.07 19z"/></svg>
                <span x-text="$store.lang.t('Report', 'Signaler')"></span>
            </button>

            {{-- Delete (own messages) --}}
            <button class="yard-ctx-item yard-ctx-item--danger" x-show="ctx.isOwn" @click="ctxAction('delete')">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                <span x-text="$store.lang.t('Delete', 'Supprimer')"></span>
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         Forward Message Modal
         ══════════════════════════════════════════════════════════════════ --}}
    <div x-data="forwardModal()" x-cloak
         @open-forward.window="openForward($event.detail.msgId)"
         @keydown.escape.window="show = false">
        <div x-show="show" x-transition.opacity class="yard-ctx-backdrop" @click="show = false"></div>
        <div x-show="show" x-transition.scale.90 class="yard-forward-modal" @click.stop>
            <div class="yard-forward-modal__header">
                <h3 x-text="$store.lang.t('Forward to...', 'Transférer à...')"></h3>
                <button @click="show = false" class="yard-forward-modal__close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <input type="text" x-model="search" class="yard-forward-modal__search"
                   :placeholder="$store.lang.t('Search rooms...', 'Rechercher...')">
            <div class="yard-forward-modal__list">
                @foreach($this->forwardRooms as $fwdRoom)
                <button class="yard-forward-modal__item"
                        x-show="!search || '{{ strtolower(e($fwdRoom->name)) }}'.includes(search.toLowerCase())"
                        @click="doForward({{ $fwdRoom->id }})">
                    <span class="yard-forward-modal__avatar">
                        @switch($fwdRoom->room_type)
                            @case(\App\Enums\RoomType::National) 🇨🇲 @break
                            @case(\App\Enums\RoomType::City) 📍 @break
                            @case(\App\Enums\RoomType::PrivateGroup) 👥 @break
                            @case(\App\Enums\RoomType::DirectMessage) 💬 @break
                        @endswitch
                    </span>
                    <span class="yard-forward-modal__name">{{ $fwdRoom->name }}</span>
                </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── User Profile Popup ── --}}
    <div x-show="profileOpen" x-transition.opacity @click.self="profileOpen = false"
         class="yard-user-profile-overlay" x-cloak>
        <div class="yard-user-profile" x-transition.scale.95 @click.stop>
            <div class="yard-user-profile__avatar">
                <template x-if="profileUser.avatar">
                    <img :src="profileUser.avatar" class="w-full h-full rounded-full object-cover">
                </template>
                <template x-if="!profileUser.avatar">
                    <span class="text-3xl font-bold text-white" x-text="profileUser.name?.charAt(0)?.toUpperCase()"></span>
                </template>
            </div>
            <h3 class="text-lg font-bold text-slate-900 mt-3" x-text="profileUser.name"></h3>
            <button @click="startDm(profileUser.id); profileOpen = false"
                    class="yard-user-profile__dm-btn">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                <span x-text="$store.lang.t('Send Message', 'Envoyer un message')"></span>
            </button>
            <button @click="profileOpen = false" class="mt-2 text-xs text-slate-400 hover:text-slate-600">
                <span x-text="$store.lang.t('Close', 'Fermer')"></span>
            </button>
        </div>
    </div>

    {{-- ── Image Lightbox ── --}}
    <div x-show="lightboxOpen" x-transition.opacity @click="lightboxOpen = false"
         class="yard-lightbox" x-cloak>
        <img :src="lightboxSrc" class="max-w-[90vw] max-h-[90vh] rounded-lg shadow-2xl">
    </div>

    @else
    {{-- Empty state --}}
    <div class="yard-empty">
        <div class="yard-empty__icon">💬</div>
        <h2 class="yard-empty__title" x-text="$store.lang.t('Welcome to The Yard', 'Bienvenue au Yard')"></h2>
        <p class="yard-empty__desc" x-text="$store.lang.t(
            'Select a conversation to start chatting.',
            'Choisissez une conversation pour commencer.'
        )"></p>
    </div>
    @endif

    <script>
        function forwardModal() {
            return {
                show: false,
                msgId: null,
                search: '',
                openForward(msgId) {
                    this.msgId = msgId;
                    this.search = '';
                    this.show = true;
                },
                doForward(roomId) {
                    this.$wire.forwardMessage(this.msgId, roomId).then(() => {
                        this.show = false;
                        this.msgId = null;
                        Livewire.dispatch('room-selected', { roomId: roomId });
                        Livewire.dispatch('refreshRoomList');
                    });
                }
            };
        }

        function lastSeen(isoDate) {
            return {
                label: '',
                _interval: null,
                init() {
                    this._update(isoDate);
                    this._interval = setInterval(() => this._update(isoDate), 30000);
                },
                destroy() {
                    if (this._interval) clearInterval(this._interval);
                },
                _update(iso) {
                    const date = new Date(iso);
                    const now = new Date();
                    const diff = now - date;
                    const mins = Math.floor(diff / 60000);
                    const hours = Math.floor(diff / 3600000);
                    const days = Math.floor(diff / 86400000);
                    const isEn = (this.$store?.lang?.current ?? 'en') === 'en';

                    if (mins < 1) {
                        this.label = isEn ? 'last seen just now' : 'vu il y a un instant';
                    } else if (mins < 60) {
                        this.label = isEn
                            ? 'last seen ' + mins + ' min ago'
                            : 'vu il y a ' + mins + ' min';
                    } else if (hours < 24 && date.getDate() === now.getDate()) {
                        const t = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        this.label = isEn
                            ? 'last seen today at ' + t
                            : 'vu aujourd\'hui à ' + t;
                    } else if (days < 2) {
                        const t = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        this.label = isEn
                            ? 'last seen yesterday at ' + t
                            : 'vu hier à ' + t;
                    } else {
                        const d = date.toLocaleDateString([], { day: 'numeric', month: 'short' });
                        const t = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        this.label = isEn
                            ? 'last seen ' + d + ' at ' + t
                            : 'vu le ' + d + ' à ' + t;
                    }
                }
            };
        }

        function chatUi() {
            return {
                typingUsers: [],
                profileOpen: false,
                profileUser: {},
                lightboxOpen: false,
                lightboxSrc: '',
                _typingTimers: {},
                optimistic: [],
                _optId: 0,
                _echoChannel: null,
                _echoChannelName: null,
                _statusPoll: null,

                init() {
                    // Poll DM partner status every 30 seconds
                    this._statusPoll = setInterval(() => {
                        if (this.$wire) this.$wire.pollDmStatus();
                    }, 30000);
                },
                destroy() {
                    if (this._statusPoll) clearInterval(this._statusPoll);
                },

                // ── Context Menu state ──
                ctx: {
                    open: false,
                    moreEmojis: false,
                    msgId: null,
                    isOwn: false,
                    msgType: '',
                    content: '',
                    isPinned: false,
                    posX: 0,
                    posY: 0,
                    quickEmojis: ['👍','❤️','😂','😮','😢','🙏'],
                    extraEmojis: ['👎','🔥','🎉','💯','🤩','😍','🥳','🤔','😎','💀','👏','✨','🤝','😈','🥶','🥵','😤','🤡','💎','🌟'],
                },

                ctxOpen(detail) {
                    this.ctx.msgId = detail.msgId;
                    this.ctx.isOwn = detail.isOwn;
                    this.ctx.msgType = detail.msgType;
                    this.ctx.content = detail.content || '';
                    this.ctx.isPinned = detail.isPinned;
                    this.ctx.moreEmojis = false;

                    // will-change:transform on .yard-panel makes position:fixed
                    // relative to that ancestor — offset coords accordingly
                    const container = this.$root.closest('.yard-panel') || this.$root;
                    const cr = container.getBoundingClientRect();

                    const menuW = 220, menuH = 380;
                    let x = detail.x - cr.left;
                    let y = detail.y - cr.top;
                    const cw = cr.width, ch = cr.height;
                    if (x + menuW > cw) x = cw - menuW - 8;
                    if (y + menuH > ch) y = ch - menuH - 8;
                    if (x < 8) x = 8;
                    if (y < 8) y = 8;
                    this.ctx.posX = x;
                    this.ctx.posY = y;
                    this.ctx.open = true;
                },

                ctxClose() {
                    this.ctx.open = false;
                    this.ctx.moreEmojis = false;
                },

                ctxReact(emoji) {
                    this.$wire.toggleReaction(this.ctx.msgId, emoji);
                    this.ctxClose();
                },

                ctxCopy() {
                    if (this.ctx.content && navigator.clipboard) {
                        navigator.clipboard.writeText(this.ctx.content);
                    }
                    this.ctxClose();
                },

                ctxAction(type) {
                    const id = this.ctx.msgId;
                    this.ctxClose();
                    switch(type) {
                        case 'reply':
                            this.$wire.setReply(id);
                            break;
                        case 'forward':
                            window.dispatchEvent(new CustomEvent('open-forward', { detail: { msgId: id } }));
                            break;
                        case 'star':
                            this.$wire.toggleStar(id);
                            break;
                        case 'pin':
                            this.$wire.togglePin(id);
                            break;
                        case 'edit':
                            this.$wire.startEdit(id);
                            break;
                        case 'translate-en':
                            this.$wire.translateMessage(id, 'en');
                            break;
                        case 'translate-fr':
                            this.$wire.translateMessage(id, 'fr');
                            break;
                        case 'report':
                            if (confirm('Report this message?')) {
                                this.$wire.reportMessage(id);
                            }
                            break;
                        case 'delete':
                            if (confirm('Delete this message?')) {
                                this.$wire.deleteMessage(id);
                            }
                            break;
                    }
                },

                subscribeEcho(channelName) {
                    // No room or same channel — skip
                    if (!channelName || channelName === this._echoChannelName) return;

                    // Unsubscribe from previous channel
                    if (this._echoChannelName && window.Echo) {
                        window.Echo.leave(this._echoChannelName);
                    }

                    this._echoChannelName = channelName;

                    if (!window.Echo) {
                        console.warn('Laravel Echo not available');
                        return;
                    }

                    const component = this.$wire;
                    const self = this;

                    this._echoChannel = window.Echo.channel(channelName)
                        .listen('.MessageSent', (e) => {
                            component.onMessageReceived(e);
                            self.scrollToBottom();
                            // Delay markAsRead so RoomList has time to show the unread badge first
                            setTimeout(() => component.delayedMarkRead(), 1500);
                        })
                        .listen('.MessageDeleted', (e) => {
                            component.onMessageDeleted(e);
                        })
                        .listen('.UserTyping', (e) => {
                            self.onTypingReceived(e);
                        })
                        .listen('.JoinRequestReceived', (e) => {
                            // Refresh room-info panel so admin sees the pending request
                            window.dispatchEvent(new CustomEvent('join-request-received', { detail: e }));
                        });
                },

                scrollToBottom() {
                    this.$nextTick(() => {
                        const el = this.$refs.chatMessages;
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                },

                typingLabel() {
                    if (this.typingUsers.length === 1) return this.typingUsers[0] + ' is typing...';
                    if (this.typingUsers.length === 2) return this.typingUsers.join(' and ') + ' are typing...';
                    return this.typingUsers.length + ' people are typing...';
                },

                showUserProfile(id, name, avatar) {
                    this.profileUser = { id, name, avatar: avatar || null };
                    this.profileOpen = true;
                },

                startDm(userId) {
                    fetch('{{ route("yard.dm.create") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ user_id: userId })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.room_id) {
                            window.dispatchEvent(new CustomEvent('room-selected', { detail: { roomId: data.room_id } }));
                        }
                    });
                },

                openLightbox(src) {
                    this.lightboxSrc = src;
                    this.lightboxOpen = true;
                },

                onTypingReceived(data) {
                    const name = data.user_name;
                    if (!this.typingUsers.includes(name)) {
                        this.typingUsers.push(name);
                    }
                    clearTimeout(this._typingTimers[name]);
                    this._typingTimers[name] = setTimeout(() => {
                        this.typingUsers = this.typingUsers.filter(u => u !== name);
                    }, 3000);
                }
            };
        }

        function inputBar() {
            return {
                emojiOpen: false,
                recording: false,
                recPaused: false,
                recSeconds: 0,
                recTimerLabel: '0:00',
                mediaRecorder: null,
                audioChunks: [],
                _typingTimeout: null,
                _recTimer: null,
                _animFrame: null,
                _analyser: null,
                _stream: null,
                msgText: @js($newMessage ?? ''),

                // ── Poll Builder state ──
                pollOpen: false,
                pollDraft: {
                    question: '',
                    options: ['', ''],
                    allowMultiple: false,
                },
                ensureOptionRow() {
                    // Auto-grow: if last input has text and we're under 12, add a fresh blank.
                    const opts = this.pollDraft.options;
                    if (opts.length < 12 && opts[opts.length - 1].trim() !== '') {
                        opts.push('');
                    }
                },
                removeOption(i) {
                    if (this.pollDraft.options.length > 2) {
                        this.pollDraft.options.splice(i, 1);
                    }
                },
                canSendPoll() {
                    const q = (this.pollDraft.question || '').trim();
                    const opts = this.pollDraft.options.map(o => (o || '').trim()).filter(o => o !== '');
                    const unique = [...new Set(opts.map(o => o.toLowerCase()))];
                    return q !== '' && unique.length >= 2;
                },
                sendPoll() {
                    if (!this.canSendPoll()) return;
                    const q = this.pollDraft.question.trim();
                    const opts = this.pollDraft.options.map(o => o.trim()).filter(o => o !== '');
                    this.$wire.createPoll(q, opts, this.pollDraft.allowMultiple);
                },
                closePoll() {
                    this.pollOpen = false;
                    this.pollDraft = { question: '', options: ['', ''], allowMultiple: false };
                },

                // ── Poll Voters ("View votes") state ──
                votersOpen: false,
                votersLoading: false,
                votersData: null,
                async openVoters(pollId) {
                    this.votersOpen = true;
                    this.votersLoading = true;
                    this.votersData = null;
                    try {
                        const data = await this.$wire.pollVoters(pollId);
                        this.votersData = data && data.options ? data : null;
                    } catch (e) {
                        console.error('pollVoters failed', e);
                        this.votersData = null;
                    } finally {
                        this.votersLoading = false;
                    }
                },
                closeVoters() {
                    this.votersOpen = false;
                    this.votersData = null;
                },

                // ── Media Preview state (WhatsApp-style) ──
                preview: {
                    active: false,
                    type: '',       // 'image' | 'document'
                    url: '',        // object URL for image preview
                    fileName: '',
                    fileSize: '',
                    fileExt: '',
                    fileIcon: '📄',
                    caption: '',
                },

                onFileSelected(event, type) {
                    const file = event.target.files[0];
                    if (!file) return;

                    this.preview.type = type;
                    this.preview.fileName = file.name;
                    this.preview.fileExt = file.name.split('.').pop() || '';
                    this.preview.caption = '';

                    // Human-readable file size
                    if (file.size < 1024) {
                        this.preview.fileSize = file.size + ' B';
                    } else if (file.size < 1048576) {
                        this.preview.fileSize = (file.size / 1024).toFixed(1) + ' KB';
                    } else {
                        this.preview.fileSize = (file.size / 1048576).toFixed(1) + ' MB';
                    }

                    // File type icon for documents
                    const ext = this.preview.fileExt.toLowerCase();
                    const iconMap = {
                        'pdf': '📕', 'doc': '📘', 'docx': '📘',
                        'xlsx': '📗', 'xls': '📗', 'csv': '📗',
                        'pptx': '📙', 'ppt': '📙',
                        'txt': '📝', 'zip': '🗜️', 'rar': '🗜️'
                    };
                    this.preview.fileIcon = iconMap[ext] || '📄';

                    if (type === 'image') {
                        // Revoke previous object URL if any
                        if (this.preview.url) URL.revokeObjectURL(this.preview.url);
                        this.preview.url = URL.createObjectURL(file);
                    } else {
                        this.preview.url = '';
                    }

                    this.preview.active = true;
                },

                sendPreviewMedia() {
                    if (!this.preview.active) return;
                    const type = this.preview.type;
                    const caption = this.preview.caption;

                    // Set caption on Livewire before sending
                    this.$wire.set('mediaCaption', caption).then(() => {
                        this.$wire.sendMedia(type);
                    });
                },

                closePreview() {
                    if (this.preview.url) URL.revokeObjectURL(this.preview.url);
                    this.preview.active = false;
                    this.preview.type = '';
                    this.preview.url = '';
                    this.preview.fileName = '';
                    this.preview.fileSize = '';
                    this.preview.fileExt = '';
                    this.preview.fileIcon = '📄';
                    this.preview.caption = '';
                    // Reset file inputs so re-selecting the same file triggers change
                    if (this.$refs.photoInput) this.$refs.photoInput.value = '';
                    if (this.$refs.docInput) this.$refs.docInput.value = '';
                    if (this.$refs.cameraInput) this.$refs.cameraInput.value = '';
                },

                insertEmoji(emoji) {
                    const ta = this.$refs.msgInput;
                    const start = ta.selectionStart;
                    const end = ta.selectionEnd;
                    const val = ta.value;
                    ta.value = val.substring(0, start) + emoji + val.substring(end);
                    ta.selectionStart = ta.selectionEnd = start + emoji.length;
                    this.msgText = ta.value;
                    ta.dispatchEvent(new Event('input'));
                    this.emojiOpen = false;
                    ta.focus();
                },

                onTyping() {
                    if (this._typingTimeout) return;
                    this.$wire.sendTyping();
                    this._typingTimeout = setTimeout(() => { this._typingTimeout = null; }, 2500);
                },

                // ── Timer helpers ──
                _startTimer() {
                    this.recSeconds = 0;
                    this.recTimerLabel = '0:00';
                    this._recTimer = setInterval(() => {
                        this.recSeconds++;
                        const m = Math.floor(this.recSeconds / 60);
                        const s = this.recSeconds % 60;
                        this.recTimerLabel = m + ':' + String(s).padStart(2, '0');
                    }, 1000);
                },
                _stopTimer() {
                    clearInterval(this._recTimer);
                    this._recTimer = null;
                },

                // ── Waveform visualizer ──
                _startWaveform(stream) {
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const src = ctx.createMediaStreamSource(stream);
                        this._analyser = ctx.createAnalyser();
                        this._analyser.fftSize = 64;
                        src.connect(this._analyser);
                        this._audioCtx = ctx;
                        this._drawWave();
                    } catch(e) { /* silent — waveform is cosmetic */ }
                },
                _drawWave() {
                    if (!this._analyser || !this.recording) return;
                    const canvas = this.$refs.waveCanvas;
                    if (!canvas) { this._animFrame = requestAnimationFrame(() => this._drawWave()); return; }
                    const c = canvas.getContext('2d');
                    const bufLen = this._analyser.frequencyBinCount;
                    const data = new Uint8Array(bufLen);
                    this._analyser.getByteFrequencyData(data);

                    c.clearRect(0, 0, canvas.width, canvas.height);
                    const barW = Math.max(2, (canvas.width / bufLen) - 1);
                    const gap = 1;
                    let x = 0;
                    for (let i = 0; i < bufLen; i++) {
                        const h = (data[i] / 255) * canvas.height * 0.9;
                        const barH = Math.max(2, h);
                        const y = (canvas.height - barH) / 2;
                        c.fillStyle = this.recPaused ? '#94a3b8' : '#CE1126';
                        c.fillRect(x, y, barW, barH);
                        x += barW + gap;
                    }
                    this._animFrame = requestAnimationFrame(() => this._drawWave());
                },
                _stopWaveform() {
                    cancelAnimationFrame(this._animFrame);
                    this._animFrame = null;
                    if (this._audioCtx) { try { this._audioCtx.close(); } catch(e){} this._audioCtx = null; }
                    this._analyser = null;
                },

                // ── Recording actions ──
                async startRecording() {
                    if (this.recording) return;
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        this._stream = stream;
                        const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus'
                            : MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm'
                            : MediaRecorder.isTypeSupported('audio/ogg') ? 'audio/ogg'
                            : MediaRecorder.isTypeSupported('audio/mp4') ? 'audio/mp4'
                            : '';
                        const options = mimeType ? { mimeType } : {};
                        this.mediaRecorder = new MediaRecorder(stream, options);
                        this.audioChunks = [];
                        this.recPaused = false;

                        this.mediaRecorder.ondataavailable = (e) => {
                            if (e.data && e.data.size > 0) this.audioChunks.push(e.data);
                        };
                        this.mediaRecorder.onstop = () => {
                            stream.getTracks().forEach(t => t.stop());
                            this._stopWaveform();
                            this._stopTimer();
                            if (this._shouldSend && this.audioChunks.length) {
                                const actual = mimeType || this.mediaRecorder.mimeType || 'audio/webm';
                                const ext = actual.includes('mp4') ? 'm4a' : actual.includes('ogg') ? 'ogg' : 'webm';
                                const blob = new Blob(this.audioChunks, { type: actual });
                                const file = new File([blob], 'voice-' + Date.now() + '.' + ext, { type: blob.type });
                                this.$wire.upload('mediaUpload', file, () => {
                                    this.$wire.sendMedia('audio');
                                }, () => {
                                    console.error('Voice upload failed');
                                });
                            }
                            this._shouldSend = false;
                        };

                        this._shouldSend = false;
                        this.mediaRecorder.start(250);
                        this.recording = true;
                        this._startTimer();
                        this._startWaveform(stream);
                    } catch (e) {
                        console.warn('Microphone access denied:', e.message);
                        alert(this.$store?.lang?.t?.('Microphone access denied. Please allow microphone in your browser settings.', 'Accès au microphone refusé. Veuillez autoriser le microphone dans les paramètres de votre navigateur.') || 'Microphone access denied.');
                    }
                },

                togglePauseRecording() {
                    if (!this.mediaRecorder) return;
                    if (this.recPaused) {
                        this.mediaRecorder.resume();
                        this.recPaused = false;
                        this._startTimer();
                    } else {
                        this.mediaRecorder.pause();
                        this.recPaused = true;
                        this._stopTimer();
                    }
                },

                discardRecording() {
                    if (!this.mediaRecorder) return;
                    this._shouldSend = false;
                    this.audioChunks = [];
                    if (this.mediaRecorder.state !== 'inactive') {
                        this.mediaRecorder.stop();
                    }
                    this.recording = false;
                    this.recPaused = false;
                    this._stopTimer();
                    this._stopWaveform();
                    if (this._stream) { this._stream.getTracks().forEach(t => t.stop()); this._stream = null; }
                },

                sendRecording() {
                    if (!this.mediaRecorder) return;
                    this._shouldSend = true;
                    if (this.mediaRecorder.state !== 'inactive') {
                        this.mediaRecorder.stop();
                    }
                    this.recording = false;
                    this.recPaused = false;
                }
            };
        }

        function audioPlayer() {
            return {
                playing: false,
                progress: 0,
                timeLabel: '0:00',
                speed: 1,
                speedLabel: '1×',
                _speeds: [1, 1.5, 2],
                _raf: null,
                _audioEl: null,
                _knownDuration: 0,

                _getDuration(el) {
                    if (el.duration && isFinite(el.duration)) {
                        this._knownDuration = el.duration;
                        return el.duration;
                    }
                    return this._knownDuration || 0;
                },

                probeDuration(el) {
                    if (el.duration && isFinite(el.duration)) {
                        this._knownDuration = el.duration;
                        this.timeLabel = this._formatTime(el.duration);
                        return;
                    }
                    // WebM duration fix: seek to huge value to force browser to resolve real duration
                    const onSeeked = () => {
                        el.removeEventListener('seeked', onSeeked);
                        if (el.duration && isFinite(el.duration)) {
                            this._knownDuration = el.duration;
                            this.timeLabel = this._formatTime(el.duration);
                        }
                        el.currentTime = 0;
                    };
                    el.addEventListener('seeked', onSeeked);
                    el.currentTime = 1e101;
                },

                _formatTime(sec) {
                    const m = Math.floor(sec / 60);
                    const s = Math.floor(sec % 60);
                    return m + ':' + String(s).padStart(2, '0');
                },

                _tick() {
                    const el = this._audioEl;
                    if (!el || !this.playing) return;

                    // Always update time label from currentTime
                    this.timeLabel = this._formatTime(el.currentTime);

                    const dur = this._getDuration(el);
                    if (dur > 0) {
                        this.progress = Math.min((el.currentTime / dur) * 100, 100);
                    }

                    this._raf = requestAnimationFrame(() => this._tick());
                },
                _startTick(el) {
                    this._audioEl = el;
                    if (this._raf) cancelAnimationFrame(this._raf);
                    this._raf = requestAnimationFrame(() => this._tick());
                },
                _stopTick() {
                    if (this._raf) { cancelAnimationFrame(this._raf); this._raf = null; }
                },

                toggle(el) {
                    if (el.paused) {
                        el.playbackRate = this.speed;
                        const p = el.play();
                        if (p && p.catch) p.catch(err => { console.error('Audio play failed:', err, el.src); this.playing = false; this._stopTick(); });
                        this.playing = true;
                        this._startTick(el);
                    } else { el.pause(); this.playing = false; this._stopTick(); }
                },
                cycleSpeed(el) {
                    const idx = (this._speeds.indexOf(this.speed) + 1) % this._speeds.length;
                    this.speed = this._speeds[idx];
                    this.speedLabel = this.speed === 1 ? '1×' : this.speed === 1.5 ? '1.5×' : '2×';
                    if (!el.paused) el.playbackRate = this.speed;
                },
                seek(event, el) {
                    const rect = event.currentTarget.getBoundingClientRect();
                    const pct = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
                    const dur = this._getDuration(el);
                    if (dur > 0) {
                        el.currentTime = pct * dur;
                        this.progress = pct * 100;
                    }
                },
                onTime(e) {
                    const el = e.target;
                    this.timeLabel = this._formatTime(el.currentTime);
                    const dur = this._getDuration(el);
                    if (dur > 0) {
                        this.progress = Math.min((el.currentTime / dur) * 100, 100);
                    }
                },
                onEnded(el) {
                    this._stopTick();
                    this.playing = false;
                    // Now duration is known for sure
                    if (el.duration && isFinite(el.duration)) {
                        this._knownDuration = el.duration;
                    }
                    this.progress = 0;
                    this.timeLabel = this._knownDuration ? this._formatTime(this._knownDuration) : '0:00';
                },
                durationLabel(el) {
                    if (!el) return '0:00';
                    const dur = el.duration && isFinite(el.duration) ? el.duration : this._knownDuration;
                    return dur ? this._formatTime(dur) : '0:00';
                }
            };
        }
    </script>
</div>
