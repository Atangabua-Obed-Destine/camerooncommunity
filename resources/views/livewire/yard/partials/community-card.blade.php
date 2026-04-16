{{-- Community Card — used in Communities modal --}}
@php
    $initials = strtoupper(substr($room->name, 0, 1));
    $colors = ['bg-blue-600', 'bg-violet-600', 'bg-emerald-600', 'bg-amber-600', 'bg-rose-600', 'bg-cyan-600'];
    $colorIdx = crc32($room->name) % count($colors);
    $avatarColor = $colors[$colorIdx];
    $isPublic = in_array($room->room_type, [\App\Enums\RoomType::National, \App\Enums\RoomType::Regional, \App\Enums\RoomType::City]);
    $memberCount = $room->live_members_count ?? $room->members_count ?? 0;
    $messageCount = $room->messages_count ?? 0;
    $isPrivateGroup = $room->is_private ?? false;
    $hasPendingRequest = !$isMember && isset($pendingRequestRoomIds) && in_array($room->id, $pendingRequestRoomIds);
@endphp
<div class="comm-card" wire:key="comm-{{ $room->id }}">
    {{-- Top row: avatar + badges --}}
    <div class="comm-card__top">
        <div class="comm-card__avatar {{ $avatarColor }}">
            @if($room->avatar)
                <img src="{{ asset('storage/' . $room->avatar) }}" alt="" class="w-full h-full rounded-lg object-cover">
            @else
                <span class="text-white font-bold text-lg">{{ $initials }}</span>
            @endif
        </div>
        <div class="comm-card__badges">
            @if($isPrivateGroup)
                <span class="comm-card__badge comm-card__badge--private">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25z"/></svg>
                    <span x-text="$store.lang.t('Private', 'Privé')"></span>
                </span>
            @elseif($isPublic)
                <span class="comm-card__badge comm-card__badge--public">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3"/></svg>
                    Public
                </span>
            @endif
            @if($isMember)
                <span class="comm-card__badge comm-card__badge--member">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                    Member
                </span>
            @endif
            @if($hasPendingRequest)
                <span class="comm-card__badge comm-card__badge--pending">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                    <span x-text="$store.lang.t('Pending', 'En attente')"></span>
                </span>
            @endif
        </div>
    </div>

    {{-- Name --}}
    <h3 class="comm-card__name">{{ Str::upper($room->name) }}</h3>

    {{-- Description --}}
    <p class="comm-card__desc">{{ Str::limit($room->description ?? '', 80) ?: (app()->getLocale() === 'fr' ? 'Aucune description' : 'No description') }}</p>

    {{-- Stats --}}
    <div class="comm-card__stats">
        <span class="comm-card__stat">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
            {{ $memberCount }} {{ $memberCount === 1 ? 'member' : 'members' }}
        </span>
        <span class="comm-card__stat">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>
            {{ $messageCount }} messages
        </span>
    </div>

    {{-- Actions --}}
    <div class="comm-card__actions">
        @if($isMember)
            <button wire:click="openCommunity({{ $room->id }})" class="comm-card__btn comm-card__btn--open">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                <span x-text="$store.lang.t('Open', 'Ouvrir')"></span>
            </button>
            <button wire:click="leaveCommunity({{ $room->id }})"
                    wire:confirm="{{ app()->getLocale() === 'fr' ? 'Quitter cette communauté ?' : 'Leave this community?' }}"
                    class="comm-card__btn comm-card__btn--leave">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
                <span x-text="$store.lang.t('Leave', 'Quitter')"></span>
            </button>
        @elseif($hasPendingRequest)
            {{-- Pending request — cancel button --}}
            <button wire:click="cancelRequest({{ $room->id }})" class="comm-card__btn comm-card__btn--pending">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                <span x-text="$store.lang.t('Pending — Cancel', 'En attente — Annuler')"></span>
            </button>
        @elseif($isPrivateGroup)
            {{-- Private group — request to join --}}
            <button wire:click="requestToJoin({{ $room->id }})" class="comm-card__btn comm-card__btn--request">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25z"/></svg>
                <span x-text="$store.lang.t('Request to Join', 'Demander à rejoindre')"></span>
            </button>
        @else
            <button wire:click="joinCommunity({{ $room->id }})" class="comm-card__btn comm-card__btn--join">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                <span x-text="$store.lang.t('Join', 'Rejoindre')"></span>
            </button>
            <button wire:click="openCommunity({{ $room->id }})" class="comm-card__btn comm-card__btn--preview">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span x-text="$store.lang.t('Preview', 'Aperçu')"></span>
            </button>
        @endif
    </div>
</div>
