<x-layouts.admin :title="'The Yard'">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold" x-text="$store.lang.t('Yard Management', 'Gestion du Yard')"></h1>
            <span class="text-sm text-slate-500">{{ $rooms->total() }} rooms</span>
        </div>

        {{-- Room Table --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-semibold text-slate-600">Room</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Type</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Members</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Messages</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Last Activity</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">System</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($rooms as $room)
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center text-lg
                                        {{ $room->room_type->value === 'national' ? 'bg-cm-green/10' : ($room->room_type->value === 'city' ? 'bg-blue-50' : 'bg-slate-100') }}">
                                        @if($room->room_type->value === 'national') 🌍
                                        @elseif($room->room_type->value === 'city') 🏙️
                                        @elseif($room->room_type->value === 'private_group') 👥
                                        @else 💬
                                        @endif
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-900">{{ $room->name }}</p>
                                        <p class="text-xs text-slate-400">{{ $room->slug }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $room->room_type->value === 'national' ? 'bg-cm-green/10 text-cm-green' : '' }}
                                    {{ $room->room_type->value === 'city' ? 'bg-blue-100 text-blue-700' : '' }}
                                    {{ $room->room_type->value === 'private_group' ? 'bg-purple-100 text-purple-700' : '' }}
                                    {{ $room->room_type->value === 'direct_message' ? 'bg-slate-100 text-slate-600' : '' }}">
                                    {{ ucfirst(str_replace('_', ' ', $room->room_type->value)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ number_format($room->members_count) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ number_format($room->messages_count) }}</td>
                            <td class="px-4 py-3 text-slate-500 text-xs">
                                {{ $room->last_message_at ? $room->last_message_at->diffForHumans() : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @if($room->is_system_room)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-cm-yellow/20 text-yellow-700 font-medium">System</span>
                                @else
                                    <span class="text-slate-400 text-xs">Custom</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-slate-400">No rooms found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($rooms->hasPages())
                <div class="px-4 py-3 border-t border-slate-200">
                    {{ $rooms->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
