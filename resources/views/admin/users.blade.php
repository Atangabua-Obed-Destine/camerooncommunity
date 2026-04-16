<x-layouts.admin :title="'Users'">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold" x-text="$store.lang.t('User Management', 'Gestion des Utilisateurs')"></h1>
            <span class="text-sm text-slate-500">{{ $users->total() }} total</span>
        </div>

        {{-- Filters --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <form method="GET" class="flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="text-xs font-medium text-slate-500 mb-1 block" x-text="$store.lang.t('Search', 'Rechercher')"></label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Name or email..."
                           class="w-full rounded-lg border-slate-300 text-sm focus:ring-cm-green focus:border-cm-green">
                </div>
                <div class="w-44">
                    <label class="text-xs font-medium text-slate-500 mb-1 block" x-text="$store.lang.t('Country', 'Pays')"></label>
                    <select name="country" class="w-full rounded-lg border-slate-300 text-sm focus:ring-cm-green focus:border-cm-green">
                        <option value="">All</option>
                        @foreach(config('cameroon.countries', []) as $code => $name)
                            <option value="{{ $code }}" {{ request('country') === $code ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-cm-green text-white text-sm font-medium rounded-lg hover:bg-cm-green/90">
                    <span x-text="$store.lang.t('Filter', 'Filtrer')"></span>
                </button>
                @if(request()->hasAny(['search', 'country']))
                    <a href="{{ route('admin.users') }}" class="px-4 py-2 text-slate-500 text-sm hover:text-slate-700">Clear</a>
                @endif
            </form>
        </div>

        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        {{-- Table --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-semibold text-slate-600">User</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Email</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Country</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Region</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Status</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Role</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Joined</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($users as $user)
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-cm-green/10 flex items-center justify-center text-cm-green font-bold text-xs">
                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-900">{{ $user->name }}</p>
                                        @if($user->is_founding_member)
                                            <span class="text-[10px] bg-cm-yellow/20 text-yellow-700 px-1.5 py-0.5 rounded-full font-medium">⭐ Founding</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $user->current_country ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $user->current_region ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if($user->is_banned)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Banned</span>
                                @elseif($user->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">Inactive</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($user->hasRole('super_admin'))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">Super Admin</span>
                                @elseif($user->hasRole('admin'))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-cm-green/10 text-cm-green">Admin</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-500">Member</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500 text-xs">{{ $user->created_at->format('M d, Y') }}</td>
                            <td class="px-4 py-3">
                                <div class="relative flex items-center gap-2" x-data="{ open: false }">
                                    <button @click="open = !open" class="p-1 rounded hover:bg-slate-100 text-slate-400 hover:text-slate-700">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="4" r="2"/><circle cx="10" cy="10" r="2"/><circle cx="10" cy="16" r="2"/></svg>
                                    </button>
                                    <div x-show="open" @click.away="open = false" x-transition
                                         class="absolute right-0 top-full mt-1 w-48 bg-white rounded-lg shadow-lg border border-slate-200 py-1 z-20">
                                        <a href="#" class="block px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">View Profile</a>
                                        @if(!$user->hasRole('super_admin') && $user->id !== auth()->id())
                                            <form method="POST" action="{{ route('admin.users.toggle-admin', $user) }}">
                                                @csrf
                                                @if($user->hasRole('admin'))
                                                    <button type="submit" class="w-full text-left px-3 py-1.5 text-sm text-orange-600 hover:bg-orange-50"
                                                            onclick="return confirm('Remove admin role from {{ $user->name }}?')">
                                                        Remove Admin
                                                    </button>
                                                @else
                                                    <button type="submit" class="w-full text-left px-3 py-1.5 text-sm text-cm-green hover:bg-cm-green/5"
                                                            onclick="return confirm('Make {{ $user->name }} an admin?')">
                                                        Make Admin
                                                    </button>
                                                @endif
                                            </form>
                                        @endif
                                        @if(!$user->is_banned)
                                            <button class="w-full text-left px-3 py-1.5 text-sm text-red-600 hover:bg-red-50">Ban User</button>
                                        @else
                                            <button class="w-full text-left px-3 py-1.5 text-sm text-blue-600 hover:bg-blue-50">Unban</button>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-slate-400">
                                <span x-text="$store.lang.t('No users found', 'Aucun utilisateur trouvé')"></span>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($users->hasPages())
                <div class="px-4 py-3 border-t border-slate-200">
                    {{ $users->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
