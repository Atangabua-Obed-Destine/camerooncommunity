<x-layouts.app>
    <x-slot:title>{{ $room->name }} — The Yard</x-slot:title>

    <div class="h-[calc(100vh-4rem)] flex overflow-hidden" x-data="{ showInfo: false }">
        {{-- LEFT SIDEBAR: Room List (desktop only) --}}
        <div class="w-80 shrink-0 border-r border-slate-200 bg-white max-md:hidden overflow-y-auto">
            {{-- Branded Sidebar Header --}}
            <div class="yard-sidebar-header">
                <div class="yard-sidebar-header__bg"></div>
                <div class="yard-sidebar-header__content">
                    <div class="yard-sidebar-header__title-row">
                        <div class="yard-sidebar-header__icon">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </div>
                        <h1 class="yard-sidebar-header__title" x-text="$store.lang.t('The Yard', 'Le Yard')"></h1>
                    </div>
                    <p class="yard-sidebar-header__subtitle" x-text="$store.lang.t('Your community chats', 'Vos discussions communautaires')"></p>
                </div>
            </div>
            <livewire:yard.room-list :activeRoomId="$room->id" />
        </div>

        {{-- MAIN CHAT PANEL --}}
        <div class="flex-1 flex flex-col min-w-0">
            <livewire:yard.chat-room :room="$room" />
        </div>

        {{-- RIGHT SIDEBAR --}}
        <div class="w-72 shrink-0 border-l border-slate-200 bg-white overflow-y-auto max-lg:hidden"
             x-show="showInfo" x-transition @toggle-room-info.window="showInfo = !showInfo">
            <div class="p-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-slate-900" x-text="$store.lang.t('Room Info', 'Info Salle')"></h3>
                    <button @click="showInfo = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Room details --}}
                <div class="text-center mb-6">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-cm-green/10 flex items-center justify-center text-3xl mb-3">
                        @switch($room->room_type)
                            @case(\App\Enums\RoomType::National) 🏳️ @break
                            @case(\App\Enums\RoomType::City) 📍 @break
                            @case(\App\Enums\RoomType::PrivateGroup) 🔒 @break
                            @case(\App\Enums\RoomType::DirectMessage) 👤 @break
                        @endswitch
                    </div>
                    <h4 class="font-bold text-slate-900">{{ $room->name }}</h4>
                    <p class="text-xs text-slate-500 mt-1">{{ $room->members_count }} <span x-text="$store.lang.t('members', 'membres')"></span></p>
                    @if($room->description)
                    <p class="text-sm text-slate-600 mt-2">{{ $room->description }}</p>
                    @endif
                </div>

                {{-- Members preview --}}
                <div>
                    <h5 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3"
                        x-text="$store.lang.t('Members', 'Membres')"></h5>
                    <div class="space-y-2">
                        @foreach($room->members()->with('user:id,name,username,avatar')->limit(20)->get() as $member)
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-cm-green/10 flex items-center justify-center text-xs font-bold text-cm-green">
                                @if($member->user?->avatar)
                                    <img src="{{ $member->user->avatar }}" alt="" class="w-8 h-8 rounded-full object-cover">
                                @else
                                    {{ strtoupper(substr($member->user?->username ?? $member->user?->name ?? '?', 0, 1)) }}
                                @endif
                            </div>
                            <span class="text-sm text-slate-700 truncate">{{ $member->user?->username ?? $member->user?->name ?? 'Unknown' }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
