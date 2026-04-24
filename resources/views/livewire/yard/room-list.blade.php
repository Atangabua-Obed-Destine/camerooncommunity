<div class="yard-room-list"
     x-on:yard-search.window="$wire.set('search', $event.detail.query)">
    @php
        $sections = [
            'national' => ['label_en' => 'National', 'label_fr' => 'National'],
            'regional' => ['label_en' => 'Regional', 'label_fr' => 'Régional'],
            'groups'   => ['label_en' => 'Groups', 'label_fr' => 'Groupes'],
            'dms'      => ['label_en' => 'Messages', 'label_fr' => 'Messages'],
        ];
        $roomIcons = [
            \App\Enums\RoomType::National->value       => '🇨🇲',
            \App\Enums\RoomType::Regional->value       => '🗺️',
            \App\Enums\RoomType::City->value            => '📍',
            \App\Enums\RoomType::PrivateGroup->value    => '👥',
            \App\Enums\RoomType::DirectMessage->value   => '💬',
        ];
        $roomColors = [
            \App\Enums\RoomType::National->value       => 'bg-cm-green',
            \App\Enums\RoomType::Regional->value       => 'bg-amber-500',
            \App\Enums\RoomType::City->value            => 'bg-blue-500',
            \App\Enums\RoomType::PrivateGroup->value    => 'bg-violet-500',
            \App\Enums\RoomType::DirectMessage->value   => 'bg-cm-green',
        ];
        $filters = [
            'all'       => ['en' => 'All',       'fr' => 'Tout'],
            'unread'    => ['en' => 'Unread',    'fr' => 'Non lus'],
            'favorites' => ['en' => 'Favorites', 'fr' => 'Favoris'],
            'groups'    => ['en' => 'Groups',    'fr' => 'Groupes'],
        ];
    @endphp

    {{-- ═══ Filter Pills (WhatsApp-style) ═══ --}}
    @php
        $filterIcons = [
            'all'       => '',
            'unread'    => '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
            'favorites' => '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>',
            'groups'    => '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>',
        ];
    @endphp
    <div class="yard-filters">
        @foreach($filters as $key => $label)
        <button wire:click="setFilter('{{ $key }}')"
                class="yard-filters__pill {{ $activeFilter === $key ? 'yard-filters__pill--active' : '' }}">
            {!! $filterIcons[$key] !!}
            <span x-text="$store.lang.t('{{ $label['en'] }}', '{{ $label['fr'] }}')"></span>
        </button>
        @endforeach
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         ACTIVE LOCATION STRIP — manual switcher entry point.
         Lets the user explicitly change their active location and
         join the new location's default rooms (instead of waiting
         for auto-detection).
         ═══════════════════════════════════════════════════════════ --}}
    @php
        $isDetectedDifferent = $detectedCountry
            && ($detectedCountry !== $activeCountry
                || ($detectedRegion && $activeRegion && $detectedRegion !== $activeRegion));
    @endphp
    <div class="yard-active-loc">
        <button type="button" wire:click="openLocationSwitcher" class="yard-active-loc__btn">
            <span class="yard-active-loc__icon">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </span>
            <span class="yard-active-loc__body">
                <span class="yard-active-loc__label" x-text="$store.lang.t('Active location', 'Lieu actif')"></span>
                <span class="yard-active-loc__place">
                    @if($activeCountry)
                        {{ $activeCountry }}@if($activeRegion) <span class="text-slate-400">·</span> {{ $activeRegion }}@endif
                    @else
                        <span class="text-slate-400" x-text="$store.lang.t('Not set yet', 'Non défini')"></span>
                    @endif
                </span>
            </span>
            @if($isDetectedDifferent)
            <span class="yard-active-loc__dot" title="{{ __('You are detected in a different location') }}"></span>
            @endif
            <svg class="yard-active-loc__chev" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
        </button>
    </div>

    {{-- ═══ Suggested Rooms (rooms user should join) ═══ --}}
    @if($suggested->count())
    <div class="px-3 pt-3 pb-1">
        <div class="rounded-xl bg-gradient-to-br from-cm-green/5 to-blue-50 border border-cm-green/20 overflow-hidden">
            <div class="px-4 py-2.5 flex items-center gap-2">
                <span class="text-sm">✨</span>
                <span class="text-xs font-bold text-cm-green uppercase tracking-wide" x-text="$store.lang.t('Rooms for you', 'Salles pour vous')"></span>
            </div>
            @foreach($suggested as $room)
            <div wire:key="suggested-{{ $room->id }}" class="flex items-center gap-3 px-4 py-3 border-t border-cm-green/10 hover:bg-white/60 transition-colors">
                <div class="w-10 h-10 rounded-full {{ $roomColors[$room->room_type->value] ?? 'bg-slate-400' }} flex items-center justify-center shrink-0">
                    <span class="text-lg">{{ $roomIcons[$room->room_type->value] ?? '💬' }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-800 truncate">{{ $room->name }}</p>
                    <p class="text-[11px] text-slate-500">{{ number_format($room->members_count) }} <span x-text="$store.lang.t('members', 'membres')"></span></p>
                </div>
                <button wire:click="previewRoom({{ $room->id }})"
                        wire:loading.attr="disabled"
                        wire:target="previewRoom({{ $room->id }})"
                        class="px-3 py-1.5 text-xs font-bold rounded-lg bg-cm-green text-white hover:bg-cm-green/90 transition-colors shrink-0 disabled:opacity-50">
                    <span wire:loading.remove wire:target="previewRoom({{ $room->id }})" x-text="$store.lang.t('Join', 'Rejoindre')"></span>
                    <span wire:loading wire:target="previewRoom({{ $room->id }})">
                        <svg class="w-3.5 h-3.5 animate-spin inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    </span>
                </button>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @forelse($groupedRooms as $room)
        <div wire:key="room-{{ $room->id }}" class="yard-room-wrap" x-data="{ ctx: false, longTimer: null }"
             @contextmenu.prevent="ctx = true"
             @touchstart="longTimer = setTimeout(() => ctx = true, 500)"
             @touchend="clearTimeout(longTimer)"
             @touchmove="clearTimeout(longTimer)"
             @click.away="ctx = false">
        <button wire:click="selectRoom({{ $room->id }})"
                wire:loading.class="opacity-60"
                wire:target="selectRoom({{ $room->id }})"
                class="yard-room {{ $activeRoomId === $room->id ? 'yard-room--active' : '' }}"
                wire:loading.attr="disabled">

            {{-- Avatar --}}
            <div class="yard-room__avatar {{ $roomColors[$room->room_type->value] ?? 'bg-slate-400' }} relative">
                @if($room->room_type === \App\Enums\RoomType::DirectMessage)
                    @if($room->dm_other_avatar)
                        <img src="{{ asset('storage/' . $room->dm_other_avatar) }}" alt="" class="w-full h-full rounded-full object-cover">
                    @elseif($room->dm_other_name)
                        <span class="text-lg font-bold text-white">{{ strtoupper(substr($room->dm_other_name, 0, 1)) }}</span>
                    @else
                        <span class="text-lg">💬</span>
                    @endif
                @elseif($room->avatar)
                    <img src="{{ $room->avatar }}" alt="" class="w-full h-full rounded-full object-cover">
                @else
                    <span class="text-lg">{{ $roomIcons[$room->room_type->value] ?? '💬' }}</span>
                @endif
                @if($room->room_type->value !== \App\Enums\RoomType::DirectMessage->value)
                <span class="absolute -bottom-0.5 -right-0.5 w-5 h-5 rounded-full bg-white flex items-center justify-center shadow-sm border border-slate-200">
                    <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                </span>
                @endif
            </div>

            {{-- Content --}}
            <div class="yard-room__body">
                <div class="yard-room__row">
                    <span class="yard-room__name">{{ $room->room_type === \App\Enums\RoomType::DirectMessage && $room->dm_other_name ? $room->dm_other_name : $room->name }}</span>
                    <span class="yard-room__meta">
                        @if($room->last_message_at)
                        <span class="yard-room__time">{{ $room->last_message_at->shortRelativeDiffForHumans() }}</span>
                        @endif
                    </span>
                </div>
                <div class="yard-room__row">
                    <span class="yard-room__preview">
                        @if($room->last_message_preview)
                            <span class="font-semibold text-slate-600">{{ $room->last_message_user_id === auth()->id() ? __('You') : ($room->last_message_sender_name ? explode(' ', $room->last_message_sender_name)[0] : '') }}:</span>
                            {{ $room->last_message_preview }}
                        @else
                            {{ __('No messages yet') }}
                        @endif
                    </span>
                    <span class="yard-room__badges">
                        @if(($room->unread_count ?? 0) > 0)
                        <span class="yard-room__unread">{{ $room->unread_count > 99 ? '99+' : $room->unread_count }}</span>
                        @endif
                        @if($room->is_favorited)
                        <svg class="yard-room__pin" viewBox="0 0 24 24" fill="currentColor"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                        @endif
                    </span>
                </div>
            </div>
        </button>
        {{-- Context menu --}}
        <div x-show="ctx" x-transition.scale.origin.top x-cloak
             class="yard-room-ctx" @click="ctx = false">
            <button wire:click="toggleFavorite({{ $room->id }})" class="yard-room-ctx__item">
                @if($room->is_favorited)
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M3 12h18M3 18h18"/></svg>
                <span x-text="$store.lang.t('Unpin', 'Désépingler')"></span>
                @else
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                <span x-text="$store.lang.t('Pin chat', 'Épingler')"></span>
                @endif
            </button>
        </div>
        </div>
    @empty
        @if($suggested->isEmpty())
        <div class="yard-room-list__empty">
            @if($activeFilter === 'unread')
                <div class="text-4xl mb-3">✅</div>
                <p class="text-sm text-slate-500" x-text="$store.lang.t('All caught up!', 'Tout est lu !')"></p>
            @elseif($activeFilter === 'favorites')
                <div class="text-4xl mb-3">📌</div>
                <p class="text-sm text-slate-500" x-text="$store.lang.t('No favorites yet', 'Aucun favori')"></p>
                <p class="text-xs text-slate-400 mt-1" x-text="$store.lang.t('Long-press a chat to pin it', 'Appuyez longuement pour épingler')"></p>
            @elseif($activeFilter === 'groups')
                <div class="text-4xl mb-3">👥</div>
                <p class="text-sm text-slate-500" x-text="$store.lang.t('No groups yet', 'Aucun groupe')"></p>
            @else
                <div class="text-4xl mb-3">🏠</div>
                <p class="text-sm text-slate-500" x-text="$store.lang.t('No conversations yet', 'Aucune conversation')"></p>
            @endif
        </div>
        @endif
    @endforelse

    {{-- ═══════════════════════════════════════════════════════════
         ARCHIVED (AWAY) — Default rooms auto-hidden because the user
         switched their active location. They auto-resurface when the
         user returns to that location.
         ═══════════════════════════════════════════════════════════ --}}
    @if($archived && $archived->count())
    <div class="yard-room-archived" x-data="{ open: false }">
        <button type="button"
                class="yard-room-archived__toggle"
                @click="open = !open">
            <span class="flex items-center gap-2">
                <svg class="w-4 h-4 text-cm-green/80" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                </svg>
                <span x-text="$store.lang.t('Archived (away)', 'Archivés (absent)')"></span>
                <span class="yard-room-archived__count">{{ $archived->count() }}</span>
            </span>
            <svg class="w-3.5 h-3.5 text-slate-400 transition-transform" :class="{ 'rotate-180': open }"
                 fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <p class="yard-room-archived__hint" x-show="open" x-cloak
           x-text="$store.lang.t('These rooms come back automatically when you return to their location.', 'Ces salons réapparaîtront automatiquement à votre retour.')"></p>
        <div x-show="open" x-cloak x-collapse>
            @foreach($archived as $room)
            {{-- Non-interactive: archived rooms are visible but locked until
                 the user returns to that location. Use <div>, no wire:click. --}}
            <div wire:key="archived-{{ $room->id }}"
                 class="yard-room yard-room--archived yard-room--locked"
                 role="group"
                 aria-disabled="true"
                 :title="$store.lang.t('Locked — switch back to this location to reopen', 'Verrouillé — revenez à ce lieu pour rouvrir')">
                <div class="yard-room__avatar {{ $roomColors[$room->room_type->value] ?? 'bg-slate-400' }} relative opacity-60">
                    @if($room->avatar)
                        <img src="{{ $room->avatar }}" alt="" class="w-full h-full rounded-full object-cover">
                    @else
                        <span class="text-lg">{{ $roomIcons[$room->room_type->value] ?? '💬' }}</span>
                    @endif
                    <span class="yard-room-archived__lock" aria-hidden="true">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 11v4m-6-4V7a6 6 0 1112 0v4M5 11h14v9a1 1 0 01-1 1H6a1 1 0 01-1-1v-9z"/>
                        </svg>
                    </span>
                </div>
                <div class="yard-room__body">
                    <div class="yard-room__row">
                        <span class="yard-room__name text-slate-500">{{ $room->name }}</span>
                        <span class="yard-room__meta">
                            <span class="yard-room-archived__pill">
                                {{ $room->country }}{{ $room->region ? ' · ' . $room->region : '' }}
                            </span>
                        </span>
                    </div>
                    <div class="yard-room__row">
                        <span class="yard-room__preview text-slate-400 italic">
                            <span x-text="$store.lang.t('Locked — return to this location to reopen', 'Verrouillé — revenez ici pour rouvrir')"></span>
                        </span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════
         JOIN PREVIEW MODAL — Interactive AI-powered room welcome
    ═══════════════════════════════════════════════════════════ --}}
    @if($showJoinPreview && $previewRoomId)
    <div class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center"
         x-data="{ entering: false }"
         x-init="$nextTick(() => entering = true)"
         @keydown.escape.window="$wire.closeJoinPreview()">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"
             x-show="entering" x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             wire:click="closeJoinPreview"></div>

        {{-- Modal Card --}}
        <div class="relative z-10 w-full max-w-sm mx-4 sm:mx-auto"
             x-show="entering"
             x-transition:enter="transition ease-out duration-400"
             x-transition:enter-start="opacity-0 translate-y-8 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100">

            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden border border-slate-200/60">

                {{-- Room Header Visual --}}
                @php
                    $previewIcons = [
                        'national' => '🇨🇲',
                        'city'     => '📍',
                        'private_group' => '👥',
                        'direct_message' => '💬',
                    ];
                    $previewBg = [
                        'national' => 'from-cm-green to-blue-800',
                        'city'     => 'from-blue-500 to-blue-700',
                        'private_group' => 'from-violet-500 to-violet-700',
                        'direct_message' => 'from-amber-500 to-amber-700',
                    ];
                @endphp
                <div class="relative bg-gradient-to-br {{ $previewBg[$previewRoomType] ?? 'from-cm-green to-blue-800' }} px-6 pt-8 pb-14 text-center overflow-hidden">
                    {{-- Floating emoji particles --}}
                    <div class="absolute inset-0 pointer-events-none overflow-hidden"
                         x-data="{ emojis: ['🇨🇲','🎉','�','⭐','🔥','🙌','✨','🎊'] }"
                         x-init="$nextTick(() => {
                             const container = $el;
                             emojis.forEach((e, i) => {
                                 const span = document.createElement('span');
                                 span.textContent = e;
                                 span.style.cssText = `position:absolute;font-size:${14 + Math.random()*10}px;left:${Math.random()*90}%;top:-20px;opacity:0;animation:emoji-fall ${2+Math.random()*2}s ease-out ${i*0.15}s forwards;pointer-events:none;`;
                                 container.appendChild(span);
                             });
                         })">
                    </div>

                    {{-- Close button --}}
                    <button wire:click="closeJoinPreview" class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-white/80 hover:bg-white/30 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>

                    {{-- Room Icon --}}
                    <div class="inline-flex w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-sm items-center justify-center mb-3 shadow-lg">
                        <span class="text-3xl">{{ $previewIcons[$previewRoomType] ?? '💬' }}</span>
                    </div>

                    <h3 class="text-lg font-extrabold text-white">{{ $previewRoomName }}</h3>

                    <div class="mt-2 flex items-center justify-center gap-4 text-white/80">
                        <span class="flex items-center gap-1 text-xs font-medium">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ number_format($previewMemberCount) }} <span x-text="$store.lang.t('members', 'membres')"></span>
                        </span>
                        @if($previewRoomRegion)
                        <span class="flex items-center gap-1 text-xs font-medium">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ $previewRoomRegion }}
                        </span>
                        @elseif($previewRoomCountry)
                        <span class="flex items-center gap-1 text-xs font-medium">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/></svg>
                            {{ $previewRoomCountry }}
                        </span>
                        @endif
                    </div>
                </div>

                {{-- AI Greeting Section --}}
                <div class="-mt-8 mx-4 mb-4">
                    <div class="bg-white rounded-xl shadow-lg border border-slate-100 p-4">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-9 h-9 rounded-full bg-gradient-to-br from-cm-green to-blue-700 flex items-center justify-center shadow-md">
                                <span class="text-sm">🤖</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <span class="text-xs font-bold text-cm-green">Kamer AI</span>
                                    <span class="w-1.5 h-1.5 rounded-full bg-cm-green animate-pulse"></span>
                                </div>
                                @if($previewAiGreeting)
                                <p class="text-sm text-slate-700 leading-relaxed">{{ $previewAiGreeting }}</p>
                                @else
                                <div class="space-y-2">
                                    <div class="h-3 bg-slate-100 rounded-full w-full animate-pulse"></div>
                                    <div class="h-3 bg-slate-100 rounded-full w-3/4 animate-pulse"></div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Room Vibe / What to Expect --}}
                <div class="px-5 pb-2">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2"
                       x-text="$store.lang.t('What\'s inside', 'Ce qui vous attend')"></p>
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-slate-50 py-2.5 px-1">
                            <span class="text-lg block">💬</span>
                            <span class="text-[10px] font-semibold text-slate-600 block mt-0.5"
                                  x-text="$store.lang.t('Live Chat', 'Discussion')"></span>
                        </div>
                        <div class="rounded-lg bg-slate-50 py-2.5 px-1">
                            <span class="text-lg block">📸</span>
                            <span class="text-[10px] font-semibold text-slate-600 block mt-0.5"
                                  x-text="$store.lang.t('Media', 'Médias')"></span>
                        </div>
                        <div class="rounded-lg bg-slate-50 py-2.5 px-1">
                            <span class="text-lg block">🤝</span>
                            <span class="text-[10px] font-semibold text-slate-600 block mt-0.5"
                                  x-text="$store.lang.t('Support', 'Entraide')"></span>
                        </div>
                    </div>
                </div>

                {{-- CTA Buttons --}}
                <div class="px-5 pt-3 pb-5 space-y-2">
                    <button wire:click="confirmJoin"
                            wire:loading.attr="disabled"
                            wire:target="confirmJoin"
                            class="w-full rounded-xl bg-gradient-to-r from-cm-green to-blue-700 py-3.5 text-sm font-bold text-white shadow-lg shadow-cm-green/20 transition-all hover:shadow-xl hover:-translate-y-0.5 disabled:opacity-50 flex items-center justify-center gap-2">
                        <span wire:loading.remove wire:target="confirmJoin">
                            <span x-text="$store.lang.t('Enter the Room 🚀', 'Entrer dans la salle 🚀')"></span>
                        </span>
                        <span wire:loading wire:target="confirmJoin" class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span x-text="$store.lang.t('Joining...', 'Connexion...')"></span>
                        </span>
                    </button>
                    <button wire:click="closeJoinPreview"
                            class="w-full rounded-xl border border-slate-200 py-3 text-sm font-medium text-slate-500 hover:bg-slate-50 transition-colors"
                            x-text="$store.lang.t('Maybe Later', 'Peut-être plus tard')"></button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════
         LOCATION SWITCHER MODAL
         Manual entry point so the user doesn't have to wait for the
         auto-detection toast. Quick-switch to detected location, or
         pick any seeded country/region.
         ═══════════════════════════════════════════════════════════ --}}
    @if($showLocationSwitcher)
    <div class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center"
         x-data="{ entering: false }"
         x-init="$nextTick(() => entering = true)"
         @keydown.escape.window="$wire.closeLocationSwitcher()">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"
             x-show="entering" x-transition.opacity
             wire:click="closeLocationSwitcher"></div>

        <div class="relative z-10 w-full sm:max-w-md mx-0 sm:mx-4 yard-loc-modal"
             x-show="entering"
             x-transition:enter="transform transition ease-out duration-300"
             x-transition:enter-start="translate-y-8 sm:translate-y-0 sm:scale-95 opacity-0"
             x-transition:enter-end="translate-y-0 sm:scale-100 opacity-100">

            <div class="yard-loc-modal__bar"></div>

            <div class="yard-loc-modal__head">
                <div>
                    <h3 class="yard-loc-modal__title" x-text="$store.lang.t('Switch active location', 'Changer de lieu actif')"></h3>
                    <p class="yard-loc-modal__sub" x-text="$store.lang.t('Confirm a switch to where we detected you. Default rooms for that location will appear, others will be archived.', 'Confirmez le passage au lieu détecté. Les salons par défaut apparaîtront, les autres seront archivés.')"></p>
                </div>
                <button wire:click="closeLocationSwitcher" class="yard-loc-modal__x">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="yard-loc-modal__body">
                {{-- Current active --}}
                <div class="yard-loc-modal__current">
                    <span class="yard-loc-modal__current-label" x-text="$store.lang.t('Currently active', 'Actuellement actif')"></span>
                    <span class="yard-loc-modal__current-value">
                        @if($activeCountry)
                            {{ $activeCountry }}@if($activeRegion) · {{ $activeRegion }}@endif
                        @else
                            <span class="italic text-slate-400" x-text="$store.lang.t('Not set', 'Non défini')"></span>
                        @endif
                    </span>
                </div>

                {{-- Detected location card (the only switch target).
                     Users can't pick an arbitrary country/region — they can
                     only switch to where they actually are right now, so
                     room membership stays grounded in physical reality. --}}
                @php
                    $hasDetected = (bool) $detectedCountry;
                    $detectedDiffers = $hasDetected
                        && ($detectedCountry !== $activeCountry || $detectedRegion !== $activeRegion);
                @endphp

                @if($hasDetected && $detectedDiffers)
                <div class="yard-loc-modal__detected">
                    <div class="yard-loc-modal__detected-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-semibold uppercase tracking-wide text-cm-green" x-text="$store.lang.t('We detected you in', 'Lieu détecté')"></div>
                        <div class="text-sm font-bold text-slate-800 mt-0.5">
                            {{ $detectedCountry }}@if($detectedRegion) · {{ $detectedRegion }}@endif
                        </div>
                    </div>
                </div>
                @elseif($hasDetected)
                <div class="yard-loc-modal__detected yard-loc-modal__detected--match">
                    <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span class="text-sm text-slate-700" x-text="$store.lang.t('You are already in your active location.', 'Vous êtes déjà dans votre lieu actif.')"></span>
                </div>
                @else
                <div class="yard-loc-modal__detected yard-loc-modal__detected--unknown">
                    <svg class="w-5 h-5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-sm text-slate-600" x-text="$store.lang.t('We couldn\'t detect your location yet. Please enable location access and try again.', 'Impossible de détecter votre position. Veuillez activer la géolocalisation et réessayer.')"></span>
                </div>
                @endif

                {{-- Impact preview --}}
                @if($hasDetected && $detectedDiffers)
                <div class="yard-loc-modal__warn">
                    <svg class="w-4 h-4 flex-shrink-0 text-cm-yellow mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2L1 21h22L12 2zm0 6l7.53 13H4.47L12 8zm-1 4v4h2v-4h-2zm0 6v2h2v-2h-2z"/>
                    </svg>
                    <span x-text="$store.lang.t('Default rooms (national + regional) for other locations will be archived. Your private chats and groups stay open.', 'Les salons par défaut (national + régional) des autres lieux seront archivés. Vos discussions privées et groupes restent ouverts.')"></span>
                </div>
                @endif
            </div>

            <div class="yard-loc-modal__footer">
                <button wire:click="closeLocationSwitcher"
                        class="yard-loc-modal__btn yard-loc-modal__btn--ghost"
                        x-text="$store.lang.t('Close', 'Fermer')"></button>
                @if($hasDetected && $detectedDiffers)
                <button wire:click="confirmDetectedSwitch"
                        wire:loading.attr="disabled"
                        class="yard-loc-modal__btn yard-loc-modal__btn--primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span x-text="$store.lang.t('Switch & join rooms', 'Changer & rejoindre')"></span>
                </button>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>
