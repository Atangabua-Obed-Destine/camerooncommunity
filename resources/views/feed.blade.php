{{--
    In-app Home / Feed page for authenticated users.
    Uses the standard app layout so the Facebook-style header is shown.
    This is intentionally minimal for now — a real feed will be added later.
--}}
<x-layouts.rails :title="'Home · ' . ($__siteName ?? 'Cameroon Network')" active="home">
    <div class="max-w-3xl mx-auto px-4 py-8">

        {{-- Welcome card --}}
        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6 mb-6"
             x-data>
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-cm-yellow text-2xl font-bold text-cm-green shrink-0">
                    {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-slate-900 truncate"
                        x-text="$store.lang.t('Welcome back, {{ explode(' ', auth()->user()->name ?? 'friend')[0] }}!', 'Bon retour, {{ explode(' ', auth()->user()->name ?? 'ami')[0] }} !')">
                    </h1>
                    <p class="text-sm text-slate-500" x-text="$store.lang.t('Connecting Cameroonians, wherever they are.', 'Connecter les Camerounais, où qu’ils soient.')"></p>
                </div>
            </div>
        </div>

        {{-- Quick shortcuts grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <a href="{{ route('yard') }}" class="group rounded-xl bg-white border border-slate-200 p-4 hover:border-cm-green hover:shadow-md transition flex flex-col items-center gap-2">
                <div class="h-12 w-12 rounded-full bg-cm-green/10 flex items-center justify-center text-cm-green">
                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.13 2 11.2c0 2.88 1.46 5.45 3.75 7.13V22l3.43-1.88c.9.25 1.85.38 2.82.38 5.52 0 10-4.13 10-9.2S17.52 2 12 2z"/></svg>
                </div>
                <span class="text-sm font-semibold text-slate-700" x-text="$store.lang.t('GoConnect', 'GoConnect')"></span>
            </a>
            <a href="{{ route('people') }}" class="group rounded-xl bg-white border border-slate-200 p-4 hover:border-cm-green hover:shadow-md transition flex flex-col items-center gap-2">
                <div class="h-12 w-12 rounded-full bg-cm-yellow/20 flex items-center justify-center text-amber-600">
                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M16 11c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                </div>
                <span class="text-sm font-semibold text-slate-700" x-text="$store.lang.t('People', 'Personnes')"></span>
            </a>
            <a href="{{ route('profile') }}" class="group rounded-xl bg-white border border-slate-200 p-4 hover:border-cm-green hover:shadow-md transition flex flex-col items-center gap-2">
                <div class="h-12 w-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">
                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                </div>
                <span class="text-sm font-semibold text-slate-700" x-text="$store.lang.t('Profile', 'Profil')"></span>
            </a>
            <button type="button" @click="window.dispatchEvent(new CustomEvent('open-discover'))" class="group rounded-xl bg-white border border-slate-200 p-4 hover:border-cm-green hover:shadow-md transition flex flex-col items-center gap-2">
                <div class="h-12 w-12 rounded-full bg-rose-50 flex items-center justify-center text-rose-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <span class="text-sm font-semibold text-slate-700" x-text="$store.lang.t('Discover', 'Découvrir')"></span>
            </button>
        </div>

        {{-- Stats card --}}
        <div class="rounded-2xl bg-gradient-to-br from-cm-green to-emerald-700 text-white p-6 mb-6 shadow-md">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <p class="text-3xl font-extrabold">{{ number_format($memberCount ?? 0) }}</p>
                    <p class="text-sm text-white/80" x-text="$store.lang.t('Members worldwide', 'Membres dans le monde')"></p>
                </div>
                <div>
                    <p class="text-3xl font-extrabold">{{ number_format($regionCount ?? 0) }}</p>
                    <p class="text-sm text-white/80" x-text="$store.lang.t('Regions represented', 'Régions représentées')"></p>
                </div>
            </div>
        </div>

        {{-- Feed placeholder --}}
        <div class="rounded-2xl border-2 border-dashed border-slate-200 p-10 text-center">
            <div class="mx-auto mb-3 h-14 w-14 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
            </div>
            <h2 class="text-lg font-bold text-slate-700" x-text="$store.lang.t('Your feed is coming soon', 'Votre fil arrive bientôt')"></h2>
            <p class="text-sm text-slate-500 mt-1 max-w-md mx-auto" x-text="$store.lang.t('We are putting the finishing touches on the community feed. Meanwhile, jump into GoConnect to chat with fellow Cameroonians.', 'Nous peaufinons le fil de la communauté. En attendant, rejoignez GoConnect pour discuter avec d’autres Camerounais.')"></p>
            <a href="{{ route('yard') }}" class="inline-flex items-center gap-2 mt-4 px-5 py-2 rounded-full bg-cm-green text-white text-sm font-semibold hover:bg-emerald-700 transition">
                <span x-text="$store.lang.t('Open GoConnect', 'Ouvrir GoConnect')"></span>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
        </div>

    </div>
</x-layouts.rails>
