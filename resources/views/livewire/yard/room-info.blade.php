<div class="yard-room-info" wire:poll.30s
     x-data="{ joinToast: null }"
     x-on:join-request-received.window="
        @if($room && $room->created_by === auth()->id())
            joinToast = $event.detail;
            $wire.$refresh();
            setTimeout(() => { joinToast = null }, 6000);
        @endif
     ">

    {{-- Join request toast notification (admin only) --}}
    <template x-if="joinToast">
        <div class="wa-join-toast" x-transition.opacity x-transition:leave.duration.300ms>
            <div class="wa-join-toast__icon">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/></svg>
            </div>
            <div class="wa-join-toast__body">
                <p class="wa-join-toast__title" x-text="joinToast.user_name"></p>
                <p class="wa-join-toast__sub" x-text="$store.lang.t('wants to join this group', 'souhaite rejoindre ce groupe')"></p>
            </div>
            <button @click="joinToast = null" class="wa-join-toast__close">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </template>

    @if($room)
    @php
        $isDm = $room->room_type->value === 'direct_message';
        $dmPartner = $isDm ? $members->first(fn($m) => $m->user_id !== auth()->id())?->user : null;
    @endphp

    {{-- ═══════════════════════════════════════════════════
         ADD MEMBERS OVERLAY (WhatsApp-style)
    ═══════════════════════════════════════════════════ --}}
    @if($addMemberOpen)
    <div class="wa-add-member">
        {{-- Header --}}
        <div class="wa-add-member__header">
            <button wire:click="closeAddMember" class="wa-add-member__back">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            </button>
            <div class="flex-1">
                <h3 class="text-base font-semibold text-slate-900" x-text="$store.lang.t('Add members', 'Ajouter des membres')"></h3>
                @if(count($selectedUsers) > 0)
                    <p class="text-xs text-cm-green">{{ count($selectedUsers) }} <span x-text="$store.lang.t('selected', 'sélectionné(s)')"></span></p>
                @endif
            </div>
        </div>

        {{-- Selected chips --}}
        @if(count($selectedUsers) > 0)
        <div class="wa-add-member__chips">
            @foreach($selectedUsers as $su)
            <button wire:click="toggleUserSelection({{ $su['id'] }}, '{{ e($su['name']) }}')" class="wa-add-member__chip">
                <span>{{ $su['name'] }}</span>
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
            @endforeach
        </div>
        @endif

        {{-- Search input --}}
        <div class="wa-add-member__search">
            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
            <input type="text" wire:model.live.debounce.300ms="memberSearch"
                   class="wa-add-member__input"
                   placeholder="{{ __('Search name or username...') }}">
        </div>

        {{-- Results list --}}
        <div class="wa-add-member__list">
            @foreach($this->searchResults as $user)
            @php $isSelected = in_array($user->id, array_column($selectedUsers, 'id')); @endphp
            <button wire:click="toggleUserSelection({{ $user->id }}, '{{ e($user->username ?? $user->name) }}')"
                    class="wa-add-member__user {{ $isSelected ? 'wa-add-member__user--selected' : '' }}">
                <div class="wa-add-member__avatar">
                    @if($user->avatar)
                        <img src="{{ asset('storage/' . $user->avatar) }}" alt="" class="w-full h-full rounded-full object-cover">
                    @else
                        <span>{{ strtoupper(substr($user->username ?? $user->name, 0, 1)) }}</span>
                    @endif
                </div>
                <div class="flex-1 min-w-0 text-left">
                    <p class="text-sm font-medium text-slate-800 truncate">{{ $user->username ?? $user->name }}</p>
                    @if($user->current_region)
                        <p class="text-xs text-slate-400 truncate">{{ $user->current_region }}</p>
                    @endif
                </div>
                @if($isSelected)
                <div class="w-5 h-5 rounded-full bg-cm-green flex items-center justify-center shrink-0">
                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                </div>
                @endif
            </button>
            @endforeach

            @if(strlen($memberSearch) >= 1 && $this->searchResults->isEmpty())
            <div class="flex flex-col items-center py-8 text-slate-400">
                <span class="text-2xl mb-1">🔍</span>
                <p class="text-xs" x-text="$store.lang.t('No users found', 'Aucun utilisateur trouvé')"></p>
            </div>
            @endif

            @if(strlen($memberSearch) < 1)
            <div class="flex flex-col items-center py-8 text-slate-400">
                <span class="text-2xl mb-1">👥</span>
                <p class="text-xs" x-text="$store.lang.t('Search for users to add', 'Rechercher des utilisateurs')"></p>
            </div>
            @endif
        </div>

        {{-- Confirm FAB --}}
        @if(count($selectedUsers) > 0)
        <button wire:click="addSelectedMembers" class="wa-add-member__fab">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
        </button>
        @endif
    </div>
    @else

    {{-- ═══════════════════════════════════════════════════
         HERO — WhatsApp-style profile card
    ═══════════════════════════════════════════════════ --}}
    <div class="wa-info-hero">
        {{-- Avatar --}}
        <div class="wa-info-hero__avatar {{ match($room->room_type->value) { 'national' => 'bg-cm-green', 'regional' => 'bg-amber-500', 'city' => 'bg-blue-500', 'private_group' => 'bg-violet-500', 'direct_message' => 'bg-cm-green', default => 'bg-cm-green' } }}">
            @if($isDm && $dmPartner?->avatar)
                <img src="{{ asset('storage/' . $dmPartner->avatar) }}" alt="" class="w-full h-full object-cover rounded-full">
            @elseif($isDm && $dmPartner)
                <span class="text-6xl font-bold text-white">{{ strtoupper(substr($dmPartner->username ?? $dmPartner->name ?? '?', 0, 1)) }}</span>
            @elseif($room->avatar)
                <img src="{{ asset('storage/' . $room->avatar) }}" alt="" class="w-full h-full object-cover rounded-full">
            @else
                <span class="text-4xl">{{ match($room->room_type->value) { 'national' => '🇨🇲', 'regional' => '🌍', 'city' => '📍', 'private_group' => '👥', default => '💬' } }}</span>
            @endif
        </div>

        {{-- Name --}}
        <h3 class="wa-info-hero__name">
            {{ $isDm && $dmPartner ? ($dmPartner->username ?? $dmPartner->name) : $room->name }}
        </h3>

        {{-- Subtitle --}}
        <p class="wa-info-hero__sub">
            @if($isDm && $dmPartner)
                {{ $dmPartner->email ?? '' }}
            @else
                {{ ucfirst(str_replace('_', ' ', $room->room_type->value)) }} · <span class="text-cm-green font-semibold">{{ $room->members_count }} <span x-text="$store.lang.t('members', 'membres')"></span></span>
            @endif
        </p>

        {{-- Quick action buttons --}}
        <div class="wa-info-hero__actions">
            @if($isDm)
                <button class="wa-info-action" @click="$dispatch('toggle-room-info')" title="Search">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
                    <span x-text="$store.lang.t('Search', 'Chercher')"></span>
                </button>
                <button class="wa-info-action" @click="$dispatch('initiate-call', { roomId: {{ $room->id }}, type: 'video' })">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25z"/></svg>
                    <span>Video</span>
                </button>
                <button class="wa-info-action" @click="$dispatch('initiate-call', { roomId: {{ $room->id }}, type: 'voice' })">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25z"/></svg>
                    <span x-text="$store.lang.t('Voice', 'Appel')"></span>
                </button>
            @else
                @if(in_array($room->room_type->value, ['private_group', 'city']))
                <button class="wa-info-action" wire:click="openAddMember">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/></svg>
                    <span x-text="$store.lang.t('Add', 'Ajouter')"></span>
                </button>
                @endif
                <button class="wa-info-action" @click="$dispatch('toggle-room-info')">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/></svg>
                    <span x-text="$store.lang.t('Search', 'Chercher')"></span>
                </button>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
         DESCRIPTION / ABOUT — (WhatsApp card style)
    ═══════════════════════════════════════════════════ --}}
    @if($room->description || ($isDm && $dmPartner?->bio))
    <div class="wa-info-card">
        <p class="wa-info-card__label" x-text="$store.lang.t('{{ $isDm ? 'About' : 'Description' }}', '{{ $isDm ? 'À propos' : 'Description' }}')"></p>
        <p class="wa-info-card__text">{{ $isDm ? ($dmPartner->bio ?? '') : $room->description }}</p>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════
         MEDIA, LINKS AND DOCS — horizontal preview strip
    ═══════════════════════════════════════════════════ --}}
    <div class="wa-info-section" x-data="{ showAllMedia: false }">
        <button @click="showAllMedia = !showAllMedia" class="wa-info-section__row">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21z"/></svg>
                <span class="text-sm text-slate-700 font-medium" x-text="$store.lang.t('Media, links and docs', 'Médias, liens et docs')"></span>
            </div>
            <span class="text-sm text-slate-400">{{ $media->count() }}</span>
        </button>

        @if($media->count() > 0)
        <div class="wa-info-media-strip">
            @foreach($media->take(4) as $item)
                @if($item->message_type->value === 'image')
                <a href="{{ asset('storage/' . $item->media_path) }}" target="_blank" class="wa-info-media-thumb">
                    <img src="{{ asset('storage/' . $item->media_path) }}" alt="" class="w-full h-full object-cover" loading="lazy">
                </a>
                @else
                <a href="{{ asset('storage/' . $item->media_path) }}" target="_blank" download class="wa-info-media-thumb wa-info-media-thumb--file">
                    @if($item->message_type->value === 'audio')
                        <span class="text-lg">🎤</span>
                        <span class="text-[9px] text-slate-400 mt-0.5">{{ \Illuminate\Support\Str::limit($item->media_original_name ?? 'Audio', 8) }}</span>
                    @else
                        <span class="text-lg">📄</span>
                        <span class="text-[9px] text-slate-400 mt-0.5">{{ \Illuminate\Support\Str::limit($item->media_original_name ?? 'File', 8) }}</span>
                    @endif
                </a>
                @endif
            @endforeach
        </div>
        @endif

        {{-- Expanded media grid --}}
        <div x-show="showAllMedia" x-collapse x-cloak class="wa-info-media-grid">
            @foreach($media as $item)
                @if($item->message_type->value === 'image')
                <a href="{{ asset('storage/' . $item->media_path) }}" target="_blank" class="wa-info-media-thumb">
                    <img src="{{ asset('storage/' . $item->media_path) }}" alt="" class="w-full h-full object-cover" loading="lazy">
                </a>
                @else
                <a href="{{ asset('storage/' . $item->media_path) }}" target="_blank" download class="wa-info-media-thumb wa-info-media-thumb--file">
                    <span class="text-lg">{{ $item->message_type->value === 'audio' ? '🎤' : '📄' }}</span>
                    <span class="text-[9px] text-slate-400 truncate w-full text-center">{{ $item->media_original_name ?? 'File' }}</span>
                </a>
                @endif
            @endforeach
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
         STARRED MESSAGES
    ═══════════════════════════════════════════════════ --}}
    <div class="wa-info-section" x-data="{ showStars: false }">
        <button @click="showStars = !showStars" class="wa-info-section__row">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5z"/></svg>
                <span class="text-sm text-slate-700 font-medium" x-text="$store.lang.t('Starred messages', 'Messages favoris')"></span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm text-slate-400">{{ $starred->count() }}</span>
                <svg class="w-4 h-4 text-slate-400 transition-transform" :class="showStars && 'rotate-90'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            </div>
        </button>
        <div x-show="showStars" x-collapse x-cloak class="px-4 pb-2">
            @forelse($starred as $star)
            <div class="wa-info-pin-item">
                <div class="wa-info-pin-item__sender">{{ $star->user?->username ?? $star->user?->name }}</div>
                @if($star->message_type->value === 'image')
                    <p class="wa-info-pin-item__text">📷 {{ $star->content ? \Illuminate\Support\Str::limit($star->content, 100) : 'Photo' }}</p>
                @elseif($star->message_type->value === 'audio')
                    <p class="wa-info-pin-item__text">🎤 Voice note</p>
                @elseif($star->message_type->value === 'file')
                    <p class="wa-info-pin-item__text">📄 {{ $star->media_original_name ?? 'File' }}</p>
                @else
                    <p class="wa-info-pin-item__text">{{ \Illuminate\Support\Str::limit($star->content, 120) }}</p>
                @endif
                <span class="wa-info-pin-item__date">{{ $star->created_at->format('M j, H:i') }}</span>
            </div>
            @empty
            <p class="text-xs text-slate-400 py-2" x-text="$store.lang.t('No starred messages', 'Aucun message favori')"></p>
            @endforelse
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
         PINNED MESSAGES
    ═══════════════════════════════════════════════════ --}}
    @if($pinned->count() > 0)
    <div class="wa-info-section" x-data="{ showPins: false }">
        <button @click="showPins = !showPins" class="wa-info-section__row">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-slate-500" fill="currentColor" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                <span class="text-sm text-slate-700 font-medium" x-text="$store.lang.t('Pinned messages', 'Messages épinglés')"></span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm text-slate-400">{{ $pinned->count() }}</span>
                <svg class="w-4 h-4 text-slate-400 transition-transform" :class="showPins && 'rotate-90'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            </div>
        </button>
        <div x-show="showPins" x-collapse x-cloak class="px-4 pb-2">
            @foreach($pinned as $pin)
            <div class="wa-info-pin-item">
                <div class="wa-info-pin-item__sender">{{ $pin->user?->username ?? $pin->user?->name }}</div>
                <p class="wa-info-pin-item__text">{{ \Illuminate\Support\Str::limit($pin->content, 120) }}</p>
                <span class="wa-info-pin-item__date">{{ $pin->created_at->format('M j, H:i') }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════
         PENDING JOIN REQUESTS (Admin only — private groups)
    ═══════════════════════════════════════════════════ --}}
    @if($pendingRequests->count() > 0)
    <div class="wa-info-section" x-data="{ showRequests: true }">
        <button @click="showRequests = !showRequests" class="wa-info-section__row">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                <span class="text-sm text-slate-700 font-medium">
                    <span x-text="$store.lang.t('Join requests', 'Demandes d\'adhésion')"></span>
                </span>
            </div>
            <div class="flex items-center gap-2">
                <span class="wa-info-requests__count">{{ $pendingRequests->count() }}</span>
                <svg class="w-4 h-4 text-slate-400 transition-transform" :class="showRequests && 'rotate-90'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            </div>
        </button>

        <div x-show="showRequests" x-collapse class="wa-info-requests">
            @foreach($pendingRequests as $req)
            @php $reqUser = $req->user; @endphp
            @if($reqUser)
            <div class="wa-info-request" wire:key="req-{{ $req->id }}">
                <div class="wa-info-request__user">
                    <div class="wa-info-member__avatar">
                        @if($reqUser->avatar)
                            <img src="{{ asset('storage/' . $reqUser->avatar) }}" alt="" class="w-full h-full rounded-full object-cover">
                        @else
                            <span>{{ strtoupper(substr($reqUser->username ?? $reqUser->name, 0, 1)) }}</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-800 truncate">{{ $reqUser->username ?? $reqUser->name }}</p>
                        @if($reqUser->current_region)
                            <p class="text-xs text-slate-400 truncate">{{ $reqUser->current_region }}</p>
                        @endif
                        <p class="text-[10px] text-slate-400">{{ $req->created_at->diffForHumans() }}</p>
                    </div>
                </div>
                <div class="wa-info-request__actions">
                    <button wire:click="rejectRequest({{ $req->id }})" class="wa-info-request__btn wa-info-request__btn--reject" title="{{ app()->getLocale() === 'fr' ? 'Refuser' : 'Reject' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                    <button wire:click="approveRequest({{ $req->id }})" class="wa-info-request__btn wa-info-request__btn--approve" title="{{ app()->getLocale() === 'fr' ? 'Accepter' : 'Approve' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    </button>
                </div>
            </div>
            @endif
            @endforeach
        </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════
         MEMBERS LIST (Group only — not DM)
    ═══════════════════════════════════════════════════ --}}
    @unless($isDm)
    <div class="wa-info-section" x-data="{ showMembers: true }">
        <button @click="showMembers = !showMembers" class="wa-info-section__row">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0z"/></svg>
                <span class="text-sm text-slate-700 font-medium">
                    {{ $room->members_count }} <span x-text="$store.lang.t('members', 'membres')"></span>
                </span>
            </div>
            <svg class="w-4 h-4 text-slate-400 transition-transform" :class="showMembers && 'rotate-90'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
        </button>

        <div x-show="showMembers" x-collapse class="wa-info-members">
            {{-- Add member row (WhatsApp-style) — only for private_group / city --}}
            @if(in_array($room->room_type->value, ['private_group', 'city']))
            <button wire:click="openAddMember" class="wa-info-member wa-info-member--add">
                <div class="wa-info-member__avatar !bg-cm-green">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-cm-green" x-text="$store.lang.t('Add member', 'Ajouter un membre')"></p>
                </div>
            </button>
            @endif

            @forelse($members as $membership)
                @php $member = $membership->user; @endphp
                @if($member)
                <div class="wa-info-member">
                    <div class="wa-info-member__avatar">
                        @if($member->avatar)
                            <img src="{{ asset('storage/' . $member->avatar) }}" alt="" class="w-full h-full rounded-full object-cover">
                        @else
                            <span>{{ strtoupper(substr($member->username ?? $member->name, 0, 1)) }}</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="wa-info-member__name">
                            {{ $member->username ?? $member->name }}
                            @if($member->id === $room->created_by)
                                <span class="wa-info-member__badge" x-text="$store.lang.t('Admin', 'Admin')"></span>
                            @endif
                        </p>
                        <p class="wa-info-member__meta">
                            @if($member->bio) {{ \Illuminate\Support\Str::limit($member->bio, 40) }}
                            @elseif($member->current_region) {{ $member->current_region }}
                            @else {{ $membership->role ?? '' }}
                            @endif
                        </p>
                    </div>
                    @if($member->last_active_at && $member->last_active_at->gt(now()->subMinutes(5)))
                        <span class="wa-info-member__online"></span>
                    @endif
                    {{-- Remove button (admin only, not on self) --}}
                    @if($room->created_by === auth()->id() && $member->id !== auth()->id())
                        <button wire:click="removeMember({{ $member->id }})"
                                wire:confirm="{{ app()->getLocale() === 'fr' ? 'Retirer ce membre du groupe ?' : 'Remove this member from the group?' }}"
                                class="wa-info-member__remove"
                                title="{{ app()->getLocale() === 'fr' ? 'Retirer' : 'Remove' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                        </button>
                    @endif
                </div>
                @endif
            @empty
                <p class="text-xs text-slate-400 p-4 text-center" x-text="$store.lang.t('No members', 'Aucun membre')"></p>
            @endforelse
        </div>
    </div>
    @endunless

    {{-- ═══════════════════════════════════════════════════
         DANGER ZONE — Exit / Report
    ═══════════════════════════════════════════════════ --}}
    <div class="wa-info-danger">
        @if($isDm)
            <button class="wa-info-danger__btn text-red-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                <span x-text="$store.lang.t('Block', 'Bloquer')"></span>
            </button>
        @else
            <button class="wa-info-danger__btn text-red-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
                <span x-text="$store.lang.t('Exit group', 'Quitter le groupe')"></span>
            </button>
            <button class="wa-info-danger__btn text-red-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.498 15.25H4.372c-1.026 0-1.945-.694-2.054-1.715A12.137 12.137 0 0 1 2.25 12c0-1.025.1-2.024.29-2.99.174-.896.92-1.51 1.882-1.51h3.19m7.878 0h3.126c1.026 0 1.945.694 2.054 1.715.086.49.132.992.132 1.5s-.046 1.01-.132 1.5c-.109 1.021-1.028 1.715-2.054 1.715h-3.126M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/></svg>
                <span x-text="$store.lang.t('Report group', 'Signaler le groupe')"></span>
            </button>
        @endif
    </div>

    @endif {{-- end addMemberOpen @else --}}

    @else
    <div class="p-6 text-center text-slate-400">
        <p x-text="$store.lang.t('Select a room', 'Sélectionnez une salle')"></p>
    </div>
    @endif
</div>
