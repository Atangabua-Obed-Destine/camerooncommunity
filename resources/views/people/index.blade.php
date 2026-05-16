{{--
    People directory — Facebook /friends-style.
    Left sidebar of categories, right area with cards that link to public profiles.

    Variables:
        $tab                    string   home|requests|suggestions|all|connections
        $q                      string   search query
        $incomingRequestsCount  int
        $myConnectionsCount     int
        $incoming               Collection of ['user' => User, 'connection_id' => int]
        $suggestions            Collection<User>
        $allPeople              Paginator|null
        $myConnections          Collection<User>
--}}
<x-layouts.app :title="'People · ' . ($__siteName ?? 'Cameroon Network')">

@php
    $tabs = [
        'home'        => ['en' => 'Home',                'fr' => 'Accueil',        'icon' => 'home'],
        'requests'    => ['en' => 'Connection requests', 'fr' => 'Demandes',       'icon' => 'plus',  'badge' => $incomingRequestsCount],
        'suggestions' => ['en' => 'Suggestions',         'fr' => 'Suggestions',    'icon' => 'spark'],
        'all'         => ['en' => 'All people',          'fr' => 'Tous',           'icon' => 'globe'],
        'connections' => ['en' => 'My connections',      'fr' => 'Mes contacts',   'icon' => 'check', 'badge' => $myConnectionsCount],
    ];
@endphp

<div class="bg-slate-50 min-h-[calc(100vh-92px)]">
    <div class="flex">

        {{-- ── Sidebar ─────────────────────────────────────────────────── --}}
        <aside class="hidden lg:flex flex-col w-[320px] shrink-0 bg-white border-r border-slate-200 min-h-[calc(100vh-92px)] sticky top-[92px] self-start">
            <div class="px-5 pt-3 pb-2 flex items-center justify-between">
                <h1 class="text-2xl font-extrabold text-slate-900" x-text="$store.lang.t('People', 'Personnes')"></h1>
            </div>
            <nav class="px-2 pb-6 space-y-1">
                @foreach($tabs as $key => $meta)
                    @php $active = $tab === $key; @endphp
                    <a href="{{ route('people', ['tab' => $key]) }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition
                              {{ $active ? 'bg-cm-green/10 text-cm-green font-semibold' : 'text-slate-700 hover:bg-slate-100' }}">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full {{ $active ? 'bg-cm-green text-white' : 'bg-slate-100 text-slate-600' }}">
                            @switch($meta['icon'])
                                @case('home')
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.69 3 11h2v9h5v-6h4v6h5v-9h2L12 2.69z"/></svg>
                                    @break
                                @case('plus')
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v6m3-3h-6M6 21v-2a4 4 0 014-4h2a4 4 0 014 4v2M10 7a4 4 0 108 0 4 4 0 00-8 0z"/></svg>
                                    @break
                                @case('spark')
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l1.8 5.6L19.4 9 14 11.4 12.2 17 10.4 11.4 5 9l5.6-1.4z"/></svg>
                                    @break
                                @case('globe')
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.6 9h16.8M3.6 15h16.8M12 3a14 14 0 010 18M12 3a14 14 0 000 18M12 3a9 9 0 100 18 9 9 0 000-18z"/></svg>
                                    @break
                                @case('check')
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    @break
                            @endswitch
                        </span>
                        <span class="flex-1" x-text="$store.lang.t(@js($meta['en']), @js($meta['fr']))"></span>
                        @if(($meta['badge'] ?? 0) > 0)
                            <span class="inline-flex min-w-[20px] h-5 px-1.5 items-center justify-center rounded-full bg-red-500 text-white text-[11px] font-bold">
                                {{ $meta['badge'] > 99 ? '99+' : $meta['badge'] }}
                            </span>
                        @endif
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                @endforeach
            </nav>
        </aside>

        {{-- ── Main area ───────────────────────────────────────────────── --}}
        <main class="flex-1 min-w-0 px-4 sm:px-6 lg:px-10 pt-3 pb-6 max-w-6xl mx-auto">

            {{-- Mobile horizontal tab strip --}}
            <nav class="lg:hidden -mx-4 px-4 overflow-x-auto mb-3">
                <div class="flex gap-2 min-w-max pb-1">
                    @foreach($tabs as $key => $meta)
                        @php $active = $tab === $key; @endphp
                        <a href="{{ route('people', ['tab' => $key]) }}"
                           class="px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap transition
                                  {{ $active ? 'bg-cm-green text-white' : 'bg-white text-slate-700 border border-slate-200' }}">
                            <span x-text="$store.lang.t(@js($meta['en']), @js($meta['fr']))"></span>
                            @if(($meta['badge'] ?? 0) > 0)
                                <span class="ml-1 inline-flex min-w-[18px] h-4 px-1 items-center justify-center rounded-full {{ $active ? 'bg-white/30' : 'bg-red-500 text-white' }} text-[10px] font-bold">{{ $meta['badge'] > 99 ? '99+' : $meta['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </nav>

            {{-- Search bar (visible on all tabs that show lists) --}}
            @if(in_array($tab, ['suggestions', 'all', 'connections']))
            <form method="GET" action="{{ route('people') }}" class="mb-4">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="search" name="q" value="{{ $q }}"
                           :placeholder="$store.lang.t('Search people…', 'Rechercher des personnes…')"
                           class="w-full pl-11 pr-4 py-3 rounded-full bg-white border border-slate-200 focus:border-cm-green focus:ring-2 focus:ring-cm-green/20 outline-none text-sm">
                </div>
            </form>
            @endif

            {{-- Tab content --}}
            @switch($tab)

                {{-- ────────── HOME ────────── --}}
                @case('home')
                    @if($incoming->isNotEmpty())
                        <section class="mb-8">
                            <div class="flex items-baseline justify-between mb-3">
                                <h2 class="text-xl font-bold text-slate-900" x-text="$store.lang.t('Connection requests', 'Demandes de connexion')"></h2>
                                <a href="{{ route('people', ['tab' => 'requests']) }}" class="text-sm font-semibold text-cm-green hover:underline" x-text="$store.lang.t('See all', 'Voir tout')"></a>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                                @foreach($incoming->take(10) as $item)
                                    @include('people._card', ['user' => $item['user'], 'mode' => 'request', 'connectionId' => $item['connection_id']])
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <section>
                        <div class="flex items-baseline justify-between mb-3">
                            <h2 class="text-xl font-bold text-slate-900" x-text="$store.lang.t('People you may know', 'Personnes que vous pourriez connaître')"></h2>
                            <a href="{{ route('people', ['tab' => 'suggestions']) }}" class="text-sm font-semibold text-cm-green hover:underline" x-text="$store.lang.t('See all', 'Voir tout')"></a>
                        </div>
                        @if($suggestions->isEmpty())
                            @include('people._empty', ['message' => __('No suggestions for now — try browsing all people.')])
                        @else
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                                @foreach($suggestions as $u)
                                    @include('people._card', ['user' => $u, 'mode' => 'suggest'])
                                @endforeach
                            </div>
                        @endif
                    </section>
                    @break

                {{-- ────────── REQUESTS ────────── --}}
                @case('requests')
                    <h2 class="text-2xl font-extrabold text-slate-900 mb-4" x-text="$store.lang.t('Connection requests', 'Demandes de connexion')"></h2>
                    @if($incoming->isEmpty())
                        @include('people._empty', ['message' => __('No pending requests.')])
                    @else
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                            @foreach($incoming as $item)
                                @include('people._card', ['user' => $item['user'], 'mode' => 'request', 'connectionId' => $item['connection_id']])
                            @endforeach
                        </div>
                    @endif
                    @break

                {{-- ────────── SUGGESTIONS ────────── --}}
                @case('suggestions')
                    <h2 class="text-2xl font-extrabold text-slate-900 mb-4" x-text="$store.lang.t('Suggestions', 'Suggestions')"></h2>
                    @if($suggestions->isEmpty())
                        @include('people._empty', ['message' => __('No suggestions match your search.')])
                    @else
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                            @foreach($suggestions as $u)
                                @include('people._card', ['user' => $u, 'mode' => 'suggest'])
                            @endforeach
                        </div>
                    @endif
                    @break

                {{-- ────────── ALL PEOPLE ────────── --}}
                @case('all')
                    <h2 class="text-2xl font-extrabold text-slate-900 mb-4" x-text="$store.lang.t('All people', 'Tous les membres')"></h2>
                    @if(!$allPeople || $allPeople->isEmpty())
                        @include('people._empty', ['message' => __('No people found.')])
                    @else
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 mb-6">
                            @foreach($allPeople as $u)
                                @include('people._card', ['user' => $u, 'mode' => 'view'])
                            @endforeach
                        </div>
                        <div class="mt-6">{{ $allPeople->links() }}</div>
                    @endif
                    @break

                {{-- ────────── MY CONNECTIONS ────────── --}}
                @case('connections')
                    <h2 class="text-2xl font-extrabold text-slate-900 mb-4" x-text="$store.lang.t('My connections', 'Mes contacts')"></h2>
                    @if($myConnections->isEmpty())
                        @include('people._empty', ['message' => __('You have no connections yet. Visit Suggestions to start.')])
                    @else
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                            @foreach($myConnections as $u)
                                @include('people._card', ['user' => $u, 'mode' => 'connected'])
                            @endforeach
                        </div>
                    @endif
                    @break

            @endswitch

        </main>
    </div>
</div>
</x-layouts.app>
