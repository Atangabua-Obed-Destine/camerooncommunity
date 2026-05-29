@php $lang = app()->getLocale(); @endphp
<div class="min-h-[calc(100vh-96px)] bg-slate-100">
    <div class="max-w-5xl mx-auto px-3 sm:px-4 py-6">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-3 mb-4">
            <div class="min-w-0">
                <a href="{{ route('marketplace.mine') }}" wire:navigate class="text-xs text-slate-500 hover:text-slate-900 font-semibold">
                    ← {{ $lang === 'fr' ? 'Mes annonces' : 'My listings' }}
                </a>
                <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 truncate mt-1">
                    📊 {{ $listing->title }}
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ $lang === 'fr' ? 'Statistiques de votre annonce.' : 'Performance of your listing.' }}
                </p>
            </div>
            <a href="{{ route('marketplace.show', ['slug' => $listing->slug]) }}" wire:navigate
               class="text-xs font-bold bg-white ring-1 ring-slate-300 hover:ring-cm-green hover:text-cm-green text-slate-700 rounded-full px-3 py-2 shrink-0">
                {{ $lang === 'fr' ? 'Voir l\'annonce' : 'View listing' }} →
            </a>
        </div>

        {{-- Range picker --}}
        <div class="flex gap-1 bg-white rounded-full ring-1 ring-slate-200 p-1 mb-4 w-fit shadow-sm">
            @foreach ([7 => '7d', 30 => '30d', 90 => '90d'] as $val => $label)
                <button wire:click="setRange({{ $val }})"
                        class="text-xs font-bold px-4 py-1.5 rounded-full transition
                        {{ $days === $val ? 'bg-cm-green text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- KPI grid --}}
        @php $s = $this->stats; @endphp
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
            <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-4">
                <div class="text-[11px] uppercase font-bold tracking-wide text-slate-500">{{ $lang === 'fr' ? 'Vues' : 'Views' }}</div>
                <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ number_format($s['views']) }}</div>
                <div class="text-[11px] text-slate-500 mt-0.5">{{ number_format($s['unique']) }} {{ $lang === 'fr' ? 'uniques' : 'unique' }}</div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-4">
                <div class="text-[11px] uppercase font-bold tracking-wide text-slate-500">{{ $lang === 'fr' ? 'Favoris' : 'Favorites' }}</div>
                <div class="text-2xl font-extrabold text-cm-red mt-1">{{ number_format($s['favorites']) }}</div>
                <div class="text-[11px] text-slate-500 mt-0.5">❤</div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-4">
                <div class="text-[11px] uppercase font-bold tracking-wide text-slate-500">{{ $lang === 'fr' ? 'Offres' : 'Offers' }}</div>
                <div class="text-2xl font-extrabold text-cm-yellow mt-1">{{ number_format($s['offers']) }}</div>
                <div class="text-[11px] text-slate-500 mt-0.5">{{ $s['accepted'] }} {{ $lang === 'fr' ? 'acceptées' : 'accepted' }}</div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-4">
                <div class="text-[11px] uppercase font-bold tracking-wide text-slate-500">{{ $lang === 'fr' ? 'Conversion' : 'Engagement' }}</div>
                <div class="text-2xl font-extrabold text-cm-green mt-1">{{ $s['ctr'] }}%</div>
                <div class="text-[11px] text-slate-500 mt-0.5">{{ $s['orders'] }} {{ $lang === 'fr' ? 'commandes' : 'orders' }} · {{ $s['paid'] }} {{ $lang === 'fr' ? 'payées' : 'paid' }}</div>
            </div>
        </div>

        {{-- Views over time (bar chart, pure CSS) --}}
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5 mb-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-bold text-slate-900">{{ $lang === 'fr' ? 'Vues par jour' : 'Daily views' }}</h2>
                <span class="text-[11px] text-slate-500">{{ $days }} {{ $lang === 'fr' ? 'derniers jours' : 'days' }}</span>
            </div>
            @php
                $series = $this->viewsSeries;
                $max = max(1, max(array_column($series, 'count')));
            @endphp
            <div class="flex items-end gap-0.5 h-32">
                @foreach ($series as $point)
                    @php $h = max(2, (int) round(($point['count'] / $max) * 100)); @endphp
                    <div class="flex-1 group relative">
                        <div class="w-full bg-cm-green/70 hover:bg-cm-green rounded-t transition cursor-default"
                             style="height: {{ $h }}%"></div>
                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block whitespace-nowrap text-[10px] font-bold bg-slate-900 text-white px-1.5 py-0.5 rounded">
                            {{ $point['count'] }} · {{ \Illuminate\Support\Carbon::parse($point['date'])->format('M j') }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Two-up: regions + sources --}}
        <div class="grid sm:grid-cols-2 gap-3">
            <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5">
                <h2 class="text-sm font-bold text-slate-900 mb-3">{{ $lang === 'fr' ? 'Régions des visiteurs' : 'Viewer regions' }}</h2>
                @if (empty($this->topRegions))
                    <p class="text-xs text-slate-500">{{ $lang === 'fr' ? 'Aucune donnée pour le moment.' : 'No data yet.' }}</p>
                @else
                    @php $rMax = max(array_column($this->topRegions, 'count')); @endphp
                    <ul class="space-y-2">
                        @foreach ($this->topRegions as $r)
                            <li>
                                <div class="flex items-center justify-between text-[12px] mb-0.5">
                                    <span class="font-semibold text-slate-800 truncate">{{ $r['region'] }}</span>
                                    <span class="text-slate-500 font-bold tabular-nums">{{ $r['count'] }}</span>
                                </div>
                                <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-cm-green" style="width: {{ round($r['count']/$rMax*100) }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5">
                <h2 class="text-sm font-bold text-slate-900 mb-3">{{ $lang === 'fr' ? 'Sources de trafic' : 'Traffic sources' }}</h2>
                @if (empty($this->topSources))
                    <p class="text-xs text-slate-500">{{ $lang === 'fr' ? 'Aucune donnée pour le moment.' : 'No data yet.' }}</p>
                @else
                    @php $sMax = max(array_column($this->topSources, 'count')); @endphp
                    <ul class="space-y-2">
                        @foreach ($this->topSources as $s2)
                            <li>
                                <div class="flex items-center justify-between text-[12px] mb-0.5">
                                    <span class="font-semibold text-slate-800 uppercase tracking-wide">{{ $s2['source'] }}</span>
                                    <span class="text-slate-500 font-bold tabular-nums">{{ $s2['count'] }}</span>
                                </div>
                                <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-cm-yellow" style="width: {{ round($s2['count']/$sMax*100) }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- Tips --}}
        <div class="mt-4 p-4 rounded-2xl bg-cm-yellow/10 ring-1 ring-cm-yellow/30 text-[13px] text-slate-800">
            <div class="font-bold mb-1">💡 {{ $lang === 'fr' ? 'Conseils pour vendre plus vite' : 'Tips to sell faster' }}</div>
            <ul class="list-disc pl-5 space-y-0.5">
                @if ($s['views'] < 20)
                    <li>{{ $lang === 'fr' ? 'Faible visibilité : pensez à booster votre annonce.' : 'Low visibility — consider bumping your listing.' }}</li>
                @endif
                @if ($s['views'] >= 20 && $s['ctr'] < 2)
                    <li>{{ $lang === 'fr' ? 'Beaucoup de vues, peu d\'intérêt : améliorez les photos ou baissez le prix.' : 'Lots of views but low interest — improve photos or lower the price.' }}</li>
                @endif
                @if ($s['offers'] > 0 && $s['accepted'] === 0)
                    <li>{{ $lang === 'fr' ? 'Vous avez des offres en attente — répondez vite !' : 'You have offers waiting — respond quickly!' }}</li>
                @endif
                @if (empty($listing->attributes))
                    <li>{{ $lang === 'fr' ? 'Ajoutez les détails de catégorie pour apparaître dans plus de filtres.' : 'Add category details to appear in more filters.' }}</li>
                @endif
            </ul>
        </div>
    </div>
</div>
