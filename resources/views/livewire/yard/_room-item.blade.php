@php
    $roomIcons = match($room->room_type) {
        \App\Enums\RoomType::National => '🏳️',
        \App\Enums\RoomType::City => '📍',
        \App\Enums\RoomType::PrivateGroup => '🔒',
        \App\Enums\RoomType::DirectMessage => '👤',
    };
@endphp
<button wire:click="selectRoom({{ $room->id }})"
        class="w-full px-4 py-3 flex items-center gap-3 hover:bg-slate-50 transition-colors text-left
               {{ $activeRoomId === $room->id ? 'bg-cm-green/5 border-r-2 border-cm-green' : '' }}">
    {{-- Room avatar --}}
    <div class="w-11 h-11 rounded-full flex items-center justify-center text-lg shrink-0 text-white
                {{ $activeRoomId === $room->id && $room->avatar ? 'bg-cm-green/10' : ($room->avatar ? 'bg-slate-100' : \App\Support\AvatarPalette::colorClass('room:' . $room->id)) }}">
        @if($room->avatar)
            <img src="{{ asset('storage/' . $room->avatar) }}" alt="" class="w-11 h-11 rounded-full object-cover">
        @else
            {{ $roomIcons }}
        @endif
    </div>

    {{-- Room info --}}
    <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between">
            <p class="font-semibold text-sm text-slate-900 truncate">{{ $room->name }}</p>
            @if($room->last_message_at)
                <span class="text-[10px] text-slate-400 shrink-0 ml-2">{{ $room->last_message_at->shortRelativeDiffForHumans() }}</span>
            @endif
        </div>
        <div class="flex items-center justify-between mt-0.5">
            <p class="text-xs text-slate-500 truncate">{{ $room->last_message_preview ?? __('No messages yet') }}</p>
            @if($room->members_count)
                <span class="text-[10px] text-slate-400 shrink-0 ml-2 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ $room->members_count }}
                </span>
            @endif
        </div>
    </div>
</button>
