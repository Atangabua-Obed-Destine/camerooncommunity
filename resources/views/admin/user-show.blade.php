<x-layouts.admin :title="'User: ' . ($user->username ?? $user->name)">
    <div class="max-w-5xl mx-auto p-4 sm:p-6 space-y-6">

        {{-- Back link --}}
        <div>
            <a href="{{ route('admin.users') }}" class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-cm-green transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                Back to Users
            </a>
        </div>

        {{-- Hero card --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="h-28 bg-gradient-to-r from-cm-green via-cm-green/80 to-cm-yellow/40"></div>
            <div class="px-6 pb-6">
                <div class="flex flex-col sm:flex-row sm:items-end gap-4 -mt-12">
                    {{-- Avatar --}}
                    <div class="w-24 h-24 rounded-2xl ring-4 ring-white bg-gradient-to-br from-cm-green to-cm-green/70 flex items-center justify-center text-white font-bold text-3xl shadow-lg overflow-hidden shrink-0">
                        @if($user->avatar)
                            <img src="{{ asset('storage/' . $user->avatar) }}" alt="" class="w-full h-full object-cover">
                        @else
                            {{ strtoupper(substr($user->name, 0, 2)) }}
                        @endif
                    </div>

                    {{-- Name + meta --}}
                    <div class="flex-1 min-w-0 pt-2 sm:pt-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-bold text-slate-900 truncate">{{ $user->name }}</h1>
                            @if($user->is_founding_member)
                                <span class="text-[10px] bg-cm-yellow/20 text-yellow-700 px-2 py-0.5 rounded-full font-semibold">⭐ Founding</span>
                            @endif
                            @if($user->is_banned)
                                <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium">Banned</span>
                            @elseif($user->is_active)
                                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">Active</span>
                            @endif
                        </div>
                        @if($user->username)
                            <p class="text-sm text-slate-500 font-mono mt-0.5">{{ '@' . $user->username }}</p>
                        @endif
                    </div>

                    {{-- Quick actions --}}
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="mailto:{{ $user->email }}" class="px-3 py-2 text-sm rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 transition-colors">
                            ✉ Email
                        </a>
                        @if(!$user->hasRole('super_admin') && $user->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.users.toggle-admin', $user) }}">
                                @csrf
                                <button type="submit" class="px-3 py-2 text-sm rounded-lg bg-cm-green text-white hover:bg-cm-green/90 transition-colors"
                                        onclick="return confirm('{{ $user->hasRole('admin') ? 'Remove admin role from' : 'Make admin:' }} {{ $user->name }}?')">
                                    {{ $user->hasRole('admin') ? 'Remove Admin' : 'Make Admin' }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats grid --}}
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm">
                <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">Rooms Joined</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ number_format($stats['rooms_joined']) }}</p>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm">
                <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">Messages Sent</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ number_format($stats['messages_sent']) }}</p>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-100 shadow-sm">
                <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold">Reports Filed</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ number_format($stats['reports_filed']) }}</p>
            </div>
        </div>

        {{-- Detail panels --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Account --}}
            <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
                <div class="px-5 py-3 border-b border-slate-100">
                    <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wide">Account</h2>
                </div>
                <dl class="divide-y divide-slate-50">
                    @php
                        $rows = [
                            'ID' => $user->id,
                            'Email' => $user->email,
                            'Email Verified' => $user->email_verified_at ? $user->email_verified_at->format('M d, Y · H:i') : 'No',
                            'Phone' => $user->phone ?? '—',
                            'Joined' => $user->created_at->format('M d, Y · H:i'),
                            'Last Updated' => $user->updated_at?->format('M d, Y · H:i') ?? '—',
                            'Roles' => $stats['roles']->isEmpty() ? 'Member' : $stats['roles']->implode(', '),
                        ];
                    @endphp
                    @foreach($rows as $label => $value)
                        <div class="flex items-center justify-between px-5 py-2.5 text-sm">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="text-slate-800 font-medium text-right truncate ml-4">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Location & profile --}}
            <div class="bg-white rounded-xl border border-slate-100 shadow-sm">
                <div class="px-5 py-3 border-b border-slate-100">
                    <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wide">Location & Profile</h2>
                </div>
                <dl class="divide-y divide-slate-50">
                    @php
                        $rows2 = [
                            'Country' => $user->current_country ?? '—',
                            'Region' => $user->current_region ?? '—',
                            'City' => $user->current_city ?? '—',
                            'Origin Country' => $user->origin_country ?? '—',
                            'Origin Region' => $user->origin_region ?? '—',
                            'Language' => $user->language ?? '—',
                            'Account Type' => $user->account_type?->value ?? '—',
                        ];
                    @endphp
                    @foreach($rows2 as $label => $value)
                        <div class="flex items-center justify-between px-5 py-2.5 text-sm">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="text-slate-800 font-medium text-right truncate ml-4">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>

        @if($user->bio)
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-2">Bio</h2>
            <p class="text-sm text-slate-700 whitespace-pre-line">{{ $user->bio }}</p>
        </div>
        @endif

    </div>
</x-layouts.admin>
