<x-layouts.app :title="'Profile'">
    <div class="max-w-2xl mx-auto px-4 py-8 space-y-6">
        {{-- Profile Header --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            {{-- Banner --}}
            <div class="h-28 bg-gradient-to-r from-cm-green via-cm-green/80 to-cm-yellow relative">
                @if($user->is_founding_member)
                    <div class="absolute top-3 right-3 px-3 py-1 bg-white/90 rounded-full text-xs font-bold text-cm-green flex items-center gap-1">
                        ⭐ <span x-text="$store.lang.t('Founding Member', 'Membre Fondateur')"></span>
                    </div>
                @endif
            </div>

            <div class="px-6 pb-6 relative -mt-12">
                {{-- Avatar (WhatsApp-style clickable) --}}
                <div x-data="{ showAvatarMenu: false }" class="relative w-24 h-24">
                    <button @click="showAvatarMenu = !showAvatarMenu"
                            class="w-24 h-24 rounded-full border-4 border-white shadow-lg overflow-hidden flex items-center justify-center text-3xl font-bold text-cm-green bg-cm-green/10 relative group cursor-pointer">
                        @if($user->avatar)
                            <img src="{{ asset('storage/' . $user->avatar) }}" alt="" class="w-full h-full object-cover">
                        @else
                            {{ strtoupper(substr($user->name, 0, 2)) }}
                        @endif
                        {{-- Camera overlay on hover --}}
                        <div class="absolute inset-0 bg-black/40 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                        </div>
                    </button>

                    {{-- Avatar action menu --}}
                    <div x-show="showAvatarMenu" @click.away="showAvatarMenu = false" x-transition
                         class="absolute left-0 top-full mt-2 w-48 rounded-xl border border-slate-200 bg-white py-1.5 shadow-xl z-50">
                        <button @click="$refs.avatarInput.click(); showAvatarMenu = false"
                                class="flex items-center gap-3 w-full px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors text-left">
                            <span>📷</span>
                            <span x-text="$store.lang.t('{{ $user->avatar ? 'Change Photo' : 'Upload Photo' }}', '{{ $user->avatar ? 'Changer la photo' : 'Télécharger une photo' }}')"></span>
                        </button>
                        @if($user->avatar)
                        <form method="POST" action="{{ route('profile.avatar.remove') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="flex items-center gap-3 w-full px-4 py-2 text-sm text-cm-red hover:bg-red-50 transition-colors text-left">
                                <span>🗑️</span>
                                <span x-text="$store.lang.t('Remove Photo', 'Supprimer la photo')"></span>
                            </button>
                        </form>
                        @endif
                    </div>

                    {{-- Hidden avatar upload form --}}
                    <form id="avatarUploadForm" method="POST" action="{{ route('profile.avatar') }}" enctype="multipart/form-data" class="hidden">
                        @csrf
                        <input type="file" x-ref="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp"
                               onchange="this.form.submit()">
                    </form>
                    @error('avatar')
                        <p class="text-xs text-cm-red mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-3">
                    <h1 class="text-xl font-bold text-slate-900">{{ $user->name }}</h1>
                    <p class="text-sm text-slate-500">{{ $user->email }}</p>

                    <div class="flex flex-wrap items-center gap-3 mt-2 text-xs text-slate-500">
                        @if($user->current_country)
                            <span class="flex items-center gap-1">📍 {{ config("cameroon.countries.{$user->current_country}", $user->current_country) }}</span>
                        @endif
                        @if($user->current_region)
                            <span>🗺️ {{ $user->current_region }}</span>
                        @endif
                        @if($user->home_region)
                            <span>🏠 {{ $user->home_region }}</span>
                        @endif
                        <span>📅 <span x-text="$store.lang.t('Joined', 'Rejoint')"></span> {{ $user->created_at->format('M Y') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-3 gap-3">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-cm-green">{{ number_format($totalPoints) }}</p>
                <p class="text-xs text-slate-500 mt-0.5" x-text="$store.lang.t('Points', 'Points')"></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-cm-green">{{ $roomsJoined }}</p>
                <p class="text-xs text-slate-500 mt-0.5" x-text="$store.lang.t('Rooms', 'Salons')"></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-cm-green">{{ $contributions }}</p>
                <p class="text-xs text-slate-500 mt-0.5" x-text="$store.lang.t('Contributions', 'Contributions')"></p>
            </div>
        </div>

        {{-- Badges --}}
        @if($badges->count())
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-900 mb-3" x-text="$store.lang.t('Badges', 'Badges')"></h2>
            <div class="flex flex-wrap gap-2">
                @foreach($badges as $badge)
                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-cm-yellow/10 rounded-full border border-cm-yellow/20">
                    <span class="text-sm">{{ $badge->icon ?? '🏅' }}</span>
                    <span class="text-xs font-medium text-slate-700">{{ $badge->name }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Settings Form --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-900 mb-4" x-text="$store.lang.t('Settings', 'Paramètres')"></h2>

            @if(session('success'))
                <div class="p-3 bg-blue-50 text-blue-700 text-sm rounded-lg border border-blue-200 mb-4">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Display Name', 'Nom d\'Affichage')"></label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-cm-green focus:ring-1 focus:ring-cm-green">
                    @error('name') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Language', 'Langue')"></label>
                    <div class="flex gap-3">
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="language_pref" value="en" {{ $user->language_pref === 'en' ? 'checked' : '' }} class="peer sr-only">
                            <div class="rounded-xl border-2 border-slate-200 px-4 py-3 text-center text-sm peer-checked:border-cm-green peer-checked:bg-cm-green/5 transition-colors">
                                🇬🇧 English
                            </div>
                        </label>
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="language_pref" value="fr" {{ $user->language_pref === 'fr' ? 'checked' : '' }} class="peer sr-only">
                            <div class="rounded-xl border-2 border-slate-200 px-4 py-3 text-center text-sm peer-checked:border-cm-green peer-checked:bg-cm-green/5 transition-colors">
                                🇫🇷 Français
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Region of Origin', 'Région d\'Origine')"></label>
                    <select name="home_region" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-cm-green focus:ring-1 focus:ring-cm-green">
                        <option value="">—</option>
                        @foreach(config('cameroon.regions', []) as $region)
                            <option value="{{ $region }}" {{ $user->home_region === $region ? 'selected' : '' }}>{{ $region }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('City of Origin', 'Ville d\'Origine')"></label>
                    <input type="text" name="home_city" value="{{ old('home_city', $user->home_city) }}"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-cm-green focus:ring-1 focus:ring-cm-green"
                           :placeholder="$store.lang.t('e.g. Bamenda, Douala...', 'ex. Bamenda, Douala...')">
                </div>

                <button type="submit" class="w-full rounded-xl bg-cm-green py-3 text-sm font-bold text-white transition-colors hover:bg-cm-green/90">
                    <span x-text="$store.lang.t('Save Changes', 'Enregistrer')"></span>
                </button>
            </form>
        </div>

        {{-- Account Actions --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 space-y-3">
            <h2 class="font-semibold text-slate-900 mb-2" x-text="$store.lang.t('Account', 'Compte')"></h2>

            @if($user->hasRole('super_admin') || $user->hasRole('admin'))
            <a href="{{ route('admin.dashboard') }}" class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-50 transition-colors group">
                <div class="flex items-center gap-3">
                    <span class="text-lg">⚙️</span>
                    <span class="text-sm font-medium text-slate-700" x-text="$store.lang.t('Admin Panel', 'Panneau Admin')"></span>
                </div>
                <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @endif

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center justify-between p-3 rounded-xl hover:bg-red-50 transition-colors group text-left">
                    <div class="flex items-center gap-3">
                        <span class="text-lg">🚪</span>
                        <span class="text-sm font-medium text-red-600" x-text="$store.lang.t('Log Out', 'Déconnexion')"></span>
                    </div>
                    <svg class="w-4 h-4 text-red-400 group-hover:text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </form>
        </div>
    </div>
</x-layouts.app>
