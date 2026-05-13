<div class="yard-room-info" wire:poll.30s
     x-data="{ joinToast: null }"
     @if($room && $room->created_by === auth()->id())
     x-on:join-request-received.window="
            joinToast = $event.detail;
            $wire.$refresh();
            setTimeout(() => { joinToast = null }, 6000);
     "
     @endif
     >

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
        $blockConn = ($isDm && $dmPartner) ? \App\Models\UserConnection::between(auth()->id(), $dmPartner->id) : null;
        $isBlockedByMe = $blockConn && $blockConn->status === \App\Models\UserConnection::STATUS_BLOCKED && $blockConn->requested_by === auth()->id();

        // Current user's membership state for this room — drives the
        // Pin / Archive toggles inside this contact-info pane.
        $myMembership = $members->firstWhere('user_id', auth()->id());
        $isPinned = (bool) ($myMembership->is_favorited ?? false);
        $isArchived = (bool) ($myMembership->archived_at ?? null);
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
        <button wire:click="addSelectedMembers"
                wire:loading.attr="disabled"
                wire:target="addSelectedMembers"
                class="wa-add-member__fab">
            <svg wire:loading.remove wire:target="addSelectedMembers" class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
            <svg wire:loading wire:target="addSelectedMembers" class="w-6 h-6 text-white animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356M2.985 19.644V14.65h4.992M16.023 9.348a8.001 8.001 0 0 0-15.046 4.65A8.001 8.001 0 0 0 7.977 14.65"/></svg>
        </button>
        @endif
    </div>
    @else

    {{-- ═══════════════════════════════════════════════════
         HERO — WhatsApp-style profile card
    ═══════════════════════════════════════════════════ --}}
    @php
        $canEditAvatar = !$isDm
            && !$room->is_system_room
            && in_array($room->room_type->value, ['private_group'], true)
            && $room->created_by === auth()->id();
        $heroHasImg = ($isDm && $dmPartner?->avatar) || (!$isDm && $room->avatar);
        $heroBgClass = $heroHasImg
            ? (match($room->room_type->value) { 'national' => 'bg-cm-green', 'regional' => 'bg-amber-500', 'city' => 'bg-blue-500', 'private_group' => 'bg-violet-500', 'direct_message' => 'bg-cm-green', default => 'bg-cm-green' })
            : ($isDm
                ? \App\Support\AvatarPalette::colorClass('user:' . ($dmPartner?->id ?? $dmPartner?->name ?? '?'))
                : \App\Support\AvatarPalette::colorClass('room:' . $room->id));
    @endphp
    <div class="wa-info-hero">
        {{-- DM partner cover photo (Facebook/WhatsApp-style banner) --}}
        @if($isDm && $dmPartner?->cover_photo)
            <div class="wa-info-hero__cover">
                <img src="{{ asset('storage/' . $dmPartner->cover_photo) }}" alt="" class="w-full h-full object-cover">
                <div class="absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-white to-transparent pointer-events-none"></div>
            </div>
        @endif

        {{-- Avatar --}}
        <div class="wa-info-hero__avatar relative group {{ $heroBgClass }}">
            @if($isDm && $dmPartner?->avatar)
                <img src="{{ asset('storage/' . $dmPartner->avatar) }}" alt="" class="w-full h-full object-cover rounded-full">
            @elseif($isDm && $dmPartner)
                <span class="text-6xl font-bold text-white">{{ strtoupper(substr($dmPartner->username ?? $dmPartner->name ?? '?', 0, 1)) }}</span>
            @elseif($room->avatar)
                <img src="{{ asset('storage/' . $room->avatar) }}" alt="" class="w-full h-full object-cover rounded-full">
            @else
                <span class="text-4xl">{{ match($room->room_type->value) { 'national' => '🇨🇲', 'regional' => '🌍', 'city' => '📍', 'private_group' => '👥', default => '💬' } }}</span>
            @endif

            {{-- Admin avatar editor overlay --}}
            @if($canEditAvatar)
                <label for="room-avatar-upload-{{ $room->id }}"
                       class="absolute inset-0 rounded-full flex flex-col items-center justify-center gap-1 bg-black/50 text-white opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer"
                       wire:loading.class="!opacity-100"
                       wire:target="newAvatar,updatedNewAvatar,removeAvatar">
                    <svg wire:loading.remove wire:target="newAvatar,updatedNewAvatar,removeAvatar"
                         class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
                    </svg>
                    <span wire:loading.remove wire:target="newAvatar,updatedNewAvatar,removeAvatar"
                          class="text-[10px] font-semibold uppercase tracking-wide"
                          x-text="$store.lang.t('Change photo', 'Modifier la photo')"></span>
                    <svg wire:loading wire:target="newAvatar,updatedNewAvatar,removeAvatar"
                         class="w-7 h-7 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity=".25"/>
                        <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                </label>
                <input id="room-avatar-upload-{{ $room->id }}" type="file"
                       wire:model="newAvatar"
                       accept="image/jpeg,image/png,image/webp"
                       class="hidden">
                @if($room->avatar)
                    <button type="button" wire:click="removeAvatar"
                            wire:confirm="{{ __('Remove the group photo?') }}"
                            class="absolute -top-1 -right-1 w-7 h-7 rounded-full bg-white text-rose-500 shadow-md flex items-center justify-center hover:bg-rose-50 transition-colors"
                            title="{{ __('Remove photo') }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                @endif
                @error('newAvatar')
                    <div class="absolute -bottom-7 left-1/2 -translate-x-1/2 whitespace-nowrap text-[11px] text-rose-600 bg-rose-50 px-2 py-0.5 rounded">{{ $message }}</div>
                @enderror
            @endif
        </div>

        {{-- Name --}}
        <h3 class="wa-info-hero__name">
            @if($isDm && $dmPartner)
                @php $dmDisplayName = auth()->user()->displayNameFor($dmPartner); @endphp
                @if($dmPartner->username)
                    <a href="{{ route('user.profile', $dmPartner->username) }}" class="hover:text-cm-green hover:underline transition">{{ $dmDisplayName }}</a>
                @else
                    {{ $dmDisplayName }}
                @endif
            @else
                {{ $room->name }}
            @endif
        </h3>

        {{-- Subtitle --}}
        <p class="wa-info-hero__sub">
            @if($isDm && $dmPartner)
                @php $dmRealHandle = $dmPartner->username ?? $dmPartner->name; @endphp
                @if(isset($dmDisplayName) && $dmDisplayName !== $dmRealHandle)
                    <span class="text-slate-400">@</span>{{ $dmRealHandle }}{{ $dmPartner->email ? ' · ' . $dmPartner->email : '' }}
                @else
                    {{ $dmPartner->email ?? '' }}
                @endif
            @else
                {{ ucfirst(str_replace('_', ' ', $room->room_type->value)) }} · <span class="text-cm-green font-semibold">{{ $room->members_count }} <span x-text="$store.lang.t('members', 'membres')"></span></span>
            @endif
        </p>

        {{-- Save-as-contact (per-viewer nickname) — DM rooms only --}}
        @if($isDm && $dmPartner)
            @php $existingNick = auth()->user()->nicknameFor($dmPartner->id); @endphp
            <div class="wa-info-nickname">
                @if(! $editingNickname)
                    <button type="button" wire:click="startEditingNickname" class="yard-nickname-trigger">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897l11.932-11.93z"/>
                        </svg>
                        <span>{{ $existingNick ? __('Edit saved name') : __('Save as a contact') }}</span>
                    </button>
                @else
                    <div class="yard-nickname-edit w-full max-w-xs mx-auto">
                        <input type="text" maxlength="60"
                               wire:model.live="nicknameDraft"
                               wire:keydown.enter="saveContactNickname"
                               wire:keydown.escape="cancelEditingNickname"
                               placeholder="{{ __('Enter a name you\'ll recognize') }}"
                               class="yard-nickname-input" autofocus>
                        <div class="flex items-center gap-2 mt-2">
                            <button type="button" wire:click="saveContactNickname" class="yard-nickname-save">
                                <span wire:loading.remove wire:target="saveContactNickname">{{ __('Save') }}</span>
                                <span wire:loading wire:target="saveContactNickname">{{ __('Saving...') }}</span>
                            </button>
                            @if($existingNick)
                                <button type="button" wire:click="$set('nicknameDraft', '')" class="yard-nickname-clear">{{ __('Clear') }}</button>
                            @endif
                            <button type="button" wire:click="cancelEditingNickname" class="yard-nickname-cancel">{{ __('Cancel') }}</button>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Quick action buttons --}}
        <div class="wa-info-hero__actions">
            @if($isDm)
                @if($dmPartner && $dmPartner->username)
                    <a class="wa-info-action" href="{{ route('user.profile', $dmPartner->username) }}" title="{{ __('View profile') }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                        <span x-text="$store.lang.t('Profile', 'Profil')"></span>
                    </a>
                @endif
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
            @php
                $pinType = $pin->message_type?->value ?? (string) $pin->message_type;
                $pinPreview = trim((string) $pin->content);
                if ($pinPreview === '') {
                    $pinPreview = match($pinType) {
                        'image' => '📷 ' . __('Photo'),
                        'video' => '🎥 ' . __('Video'),
                        'audio', 'voice' => '🎙️ ' . __('Voice message'),
                        'file', 'document' => '📎 ' . ($pin->media_original_name ?: __('Attachment')),
                        'poll' => '📊 ' . __('Poll'),
                        'location' => '📍 ' . __('Location'),
                        default => __('Message'),
                    };
                }
            @endphp
            <button type="button"
                    @click="$dispatch('scroll-to-message', { messageId: {{ $pin->id }} })"
                    class="wa-info-pin-item w-full text-left hover:bg-slate-50 transition rounded-lg cursor-pointer">
                <div class="wa-info-pin-item__sender">{{ $pin->user?->username ?? $pin->user?->name }}</div>
                <p class="wa-info-pin-item__text">{{ \Illuminate\Support\Str::limit($pinPreview, 120) }}</p>
                <span class="wa-info-pin-item__date">{{ $pin->created_at->format('M j, H:i') }}</span>
            </button>
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
                    <div class="wa-info-member__avatar {{ $reqUser->avatar ? '' : \App\Support\AvatarPalette::colorClass('user:' . $reqUser->id) }}">
                        @if($reqUser->avatar)
                            <img src="{{ asset('storage/' . $reqUser->avatar) }}" alt="" class="w-full h-full rounded-full object-cover">
                        @else
                            <span class="text-white">{{ strtoupper(substr($reqUser->username ?? $reqUser->name, 0, 1)) }}</span>
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
                    <button wire:click="rejectRequest({{ $req->id }})"
                            wire:loading.attr="disabled"
                            wire:target="rejectRequest({{ $req->id }})"
                            class="wa-info-request__btn wa-info-request__btn--reject" title="{{ app()->getLocale() === 'fr' ? 'Refuser' : 'Reject' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                    <button wire:click="approveRequest({{ $req->id }})"
                            wire:loading.attr="disabled"
                            wire:target="approveRequest({{ $req->id }})"
                            class="wa-info-request__btn wa-info-request__btn--approve" title="{{ app()->getLocale() === 'fr' ? 'Accepter' : 'Approve' }}">
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
            {{-- Search members (always available for groups) --}}
            @if($room->members_count >= 2 || $memberFilter !== '')
            <div class="px-4 py-2">
                <div class="relative">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                    <input type="text"
                           wire:model.live.debounce.250ms="memberFilter"
                           :placeholder="$store.lang.t('Search members', 'Rechercher des membres')"
                           class="w-full pl-9 pr-8 py-2 text-sm bg-slate-100 border-0 rounded-lg focus:ring-2 focus:ring-cm-green focus:bg-white">
                    @if($memberFilter !== '')
                        <button wire:click="$set('memberFilter', '')" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                        </button>
                    @endif
                </div>
            </div>
            @endif

            {{-- Add member row (WhatsApp-style) — only for private_group / city --}}
            @if(in_array($room->room_type->value, ['private_group', 'city']) && $memberFilter === '')
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
                    <div class="wa-info-member__avatar {{ $member->avatar ? '' : \App\Support\AvatarPalette::colorClass('user:' . $member->id) }}">
                        @if($member->avatar)
                            <img src="{{ asset('storage/' . $member->avatar) }}" alt="" class="w-full h-full rounded-full object-cover">
                        @else
                            <span class="text-white">{{ strtoupper(substr($member->username ?? $member->name, 0, 1)) }}</span>
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

    {{-- ═══════════════════════════════════════════════════
         PAST MEMBERS — admin only (WhatsApp "Past participants")
    ═══════════════════════════════════════════════════ --}}
    @php $pastMembers = $this->pastMembers; @endphp
    @if($room->created_by === auth()->id() && $pastMembers->isNotEmpty())
    <div class="wa-info-section" x-data="{ showPast: false }">
        <button @click="showPast = !showPast" class="wa-info-section__row">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                <span class="text-sm text-slate-700 font-medium">
                    <span x-text="$store.lang.t('Past members', 'Anciens membres')"></span>
                </span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-sm text-slate-400">{{ $pastMembers->count() }}</span>
                <svg class="w-4 h-4 text-slate-400 transition-transform" :class="showPast && 'rotate-90'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            </div>
        </button>

        <div x-show="showPast" x-collapse x-cloak class="wa-info-members">
            @foreach($pastMembers as $entry)
                @php $past = $entry->user; @endphp
                @if($past)
                <div class="wa-info-member">
                    <div class="wa-info-member__avatar {{ $past->avatar ? '' : \App\Support\AvatarPalette::colorClass('user:' . $past->id) }}">
                        @if($past->avatar)
                            <img src="{{ asset('storage/' . $past->avatar) }}" alt="" class="w-full h-full rounded-full object-cover opacity-70">
                        @else
                            <span class="text-white opacity-80">{{ strtoupper(substr($past->username ?? $past->name, 0, 1)) }}</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="wa-info-member__name text-slate-600">
                            {{ $past->username ?? $past->name }}
                        </p>
                        <p class="wa-info-member__meta">
                            @if($entry->reason === 'removed')
                                @if($entry->remover)
                                    <span x-text="$store.lang.t('Removed by', 'Retiré par')"></span>
                                    {{ $entry->remover->username ?? $entry->remover->name }} ·
                                @else
                                    <span x-text="$store.lang.t('Removed', 'Retiré')"></span> ·
                                @endif
                            @else
                                <span x-text="$store.lang.t('Left', 'A quitté')"></span> ·
                            @endif
                            {{ $entry->exited_at?->diffForHumans() }}
                        </p>
                    </div>
                </div>
                @endif
            @endforeach
        </div>
    </div>
    @endif
    @endunless

    {{-- ═══════════════════════════════════════════════════
         CHAT ACTIONS — Pin / Archive (matches the chat-list ctx menu)
    ═════════════════════════════════════════════════ --}}
    <div class="wa-info-section">
        <button wire:click="togglePinChat" class="wa-info-section__row">
            <div class="flex items-center gap-3">
                @if($isPinned)
                    <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M3 12h18M3 18h18"/></svg>
                    <span class="text-sm text-slate-700 font-medium" x-text="$store.lang.t('Unpin chat', 'Désépingler')"></span>
                @else
                    <svg class="w-5 h-5 text-slate-500" viewBox="0 0 24 24" fill="currentColor"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                    <span class="text-sm text-slate-700 font-medium" x-text="$store.lang.t('Pin chat', 'Épingler')"></span>
                @endif
            </div>
        </button>

        <button wire:click="toggleArchiveChat"
                @if($isArchived)
                    wire:confirm="{{ app()->getLocale() === 'fr' ? 'Désarchiver cette discussion ?' : 'Unarchive this chat?' }}"
                @else
                    wire:confirm="{{ app()->getLocale() === 'fr' ? 'Archiver cette discussion ?' : 'Archive this chat?' }}"
                @endif
                class="wa-info-section__row">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M6 8l1 11a2 2 0 002 2h6a2 2 0 002-2l1-11M10 12h4M4 4h16v4H4z"/></svg>
                @if($isArchived)
                    <span class="text-sm text-slate-700 font-medium" x-text="$store.lang.t('Unarchive chat', 'Désarchiver')"></span>
                @else
                    <span class="text-sm text-slate-700 font-medium" x-text="$store.lang.t('Archive chat', 'Archiver')"></span>
                @endif
            </div>
        </button>
    </div>
    {{-- ═══════════════════════════════════════════════════
         DANGER ZONE — Exit / Report
    ═══════════════════════════════════════════════════ --}}
    <div class="wa-info-danger"
         x-data="{
            reportOpen: false,
            reportReason: 'inappropriate',
            reportDetails: '',
            submitReport() {
                $wire.reportRoom(this.reportReason, this.reportDetails);
                this.reportOpen = false;
                this.reportDetails = '';
            }
         }">
        @if($isDm)
            @if($dmPartner)
                @if($isBlockedByMe)
                    <button class="wa-info-danger__btn text-emerald-600"
                            wire:click="unblockUser({{ $dmPartner->id }})"
                            wire:confirm="{{ app()->getLocale() === 'fr' ? 'Débloquer cet utilisateur ?' : 'Unblock this user?' }}"
                            wire:loading.attr="disabled"
                            wire:target="unblockUser">
                        <svg wire:loading.remove wire:target="unblockUser" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                        <svg wire:loading wire:target="unblockUser" class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356M2.985 19.644V14.65h4.992M16.023 9.348a8.001 8.001 0 0 0-15.046 4.65A8.001 8.001 0 0 0 7.977 14.65"/></svg>
                        <span x-text="$store.lang.t('Unblock', 'Débloquer')"></span>
                    </button>
                @else
                    <button class="wa-info-danger__btn text-red-500"
                            wire:click="blockUser({{ $dmPartner->id }})"
                            wire:confirm="{{ app()->getLocale() === 'fr' ? 'Bloquer cet utilisateur ? Vous ne recevrez plus ses messages.' : 'Block this user? You will no longer receive their messages.' }}"
                            wire:loading.attr="disabled"
                            wire:target="blockUser">
                        <svg wire:loading.remove wire:target="blockUser" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        <svg wire:loading wire:target="blockUser" class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356M2.985 19.644V14.65h4.992M16.023 9.348a8.001 8.001 0 0 0-15.046 4.65A8.001 8.001 0 0 0 7.977 14.65"/></svg>
                        <span x-text="$store.lang.t('Block', 'Bloquer')"></span>
                    </button>
                @endif
            @endif
        @else
            @if($room && $room->created_by !== auth()->id() && ! in_array($room->room_type->value, ['national', 'regional', 'city'], true))
            <button class="wa-info-danger__btn text-red-500"
                    wire:click="leaveRoom"
                    wire:confirm="{{ app()->getLocale() === 'fr' ? 'Quitter définitivement ce groupe ?' : 'Permanently leave this group?' }}"
                    wire:loading.attr="disabled"
                    wire:target="leaveRoom">
                <svg wire:loading.remove wire:target="leaveRoom" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
                <svg wire:loading wire:target="leaveRoom" class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356M2.985 19.644V14.65h4.992M16.023 9.348a8.001 8.001 0 0 0-15.046 4.65A8.001 8.001 0 0 0 7.977 14.65"/></svg>
                <span x-text="$store.lang.t('Exit group', 'Quitter le groupe')"></span>
            </button>
            @endif
            <button class="wa-info-danger__btn text-red-500" @click="reportOpen = true">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                <span x-text="$store.lang.t('Report group', 'Signaler le groupe')"></span>
            </button>

            {{-- Report modal --}}
            <div x-show="reportOpen" x-cloak
                 x-transition.opacity
                 @click.self="reportOpen = false"
                 @keydown.escape.window="reportOpen = false"
                 class="fixed inset-0 z-[10000] bg-black/50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-5" @click.stop>
                    <h3 class="text-base font-semibold text-slate-900 mb-1" x-text="$store.lang.t('Report this group', 'Signaler ce groupe')"></h3>
                    <p class="text-xs text-slate-500 mb-4" x-text="$store.lang.t('Tell us what’s wrong. Reports are anonymous and reviewed by our team.', 'Dites-nous ce qui ne va pas. Les signalements sont anonymes et examinés par notre équipe.')"></p>

                    <label class="block text-xs font-medium text-slate-600 mb-1" x-text="$store.lang.t('Reason', 'Raison')"></label>
                    <select x-model="reportReason" class="w-full rounded-lg border-slate-300 text-sm mb-3">
                        <option value="spam" x-text="$store.lang.t('Spam', 'Spam')"></option>
                        <option value="harassment" x-text="$store.lang.t('Harassment', 'Harcèlement')"></option>
                        <option value="scam" x-text="$store.lang.t('Scam', 'Arnaque')"></option>
                        <option value="misinformation" x-text="$store.lang.t('Misinformation', 'Désinformation')"></option>
                        <option value="inappropriate" x-text="$store.lang.t('Inappropriate content', 'Contenu inapproprié')"></option>
                        <option value="other" x-text="$store.lang.t('Other', 'Autre')"></option>
                    </select>

                    <label class="block text-xs font-medium text-slate-600 mb-1" x-text="$store.lang.t('Details (optional)', 'Détails (facultatif)')"></label>
                    <textarea x-model="reportDetails" rows="3" maxlength="1000"
                              class="w-full rounded-lg border-slate-300 text-sm mb-4"
                              :placeholder="$store.lang.t('Add any context that might help our team…', 'Ajoutez tout contexte utile pour notre équipe…')"></textarea>

                    <div class="flex justify-end gap-2">
                        <button @click="reportOpen = false" class="px-3 py-1.5 text-sm rounded-lg text-slate-600 hover:bg-slate-100" x-text="$store.lang.t('Cancel', 'Annuler')"></button>
                        <button @click="submitReport()"
                                wire:loading.attr="disabled"
                                wire:target="reportRoom"
                                class="px-4 py-1.5 text-sm rounded-lg bg-red-500 text-white font-semibold hover:bg-red-600 disabled:opacity-60"
                                x-text="$store.lang.t('Submit report', 'Envoyer le signalement')"></button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @endif {{-- end addMemberOpen @else --}}

    @else
    <div class="p-6 text-center text-slate-400">
        <p x-text="$store.lang.t('Select a room', 'Sélectionnez une salle')"></p>
    </div>
    @endif
</div>
