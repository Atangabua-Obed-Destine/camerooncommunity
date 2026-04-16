<x-layouts.admin :title="'Sponsored Ads'">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold" x-text="$store.lang.t('Sponsored Ads', 'Annonces Sponsorisées')"></h1>
            <a href="{{ route('admin.sponsored-ads.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-cm-green text-white text-sm font-medium rounded-lg hover:bg-cm-green/90 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                <span x-text="$store.lang.t('New Ad', 'Nouvelle Annonce')"></span>
            </a>
        </div>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide" x-text="$store.lang.t('Total Ads', 'Total Annonces')"></p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ $stats['total'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide" x-text="$store.lang.t('Active', 'Actives')"></p>
                <p class="text-2xl font-bold text-green-600 mt-1">{{ $stats['active'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Impressions</p>
                <p class="text-2xl font-bold text-blue-600 mt-1">{{ number_format($stats['totalImpressions']) }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Clicks</p>
                <p class="text-2xl font-bold text-amber-600 mt-1">{{ number_format($stats['totalClicks']) }}</p>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-1 bg-white rounded-xl shadow-sm border border-slate-200 p-1">
            @php
                $tabs = [
                    'all'     => ['en' => 'All',     'fr' => 'Toutes'],
                    'active'  => ['en' => 'Active',  'fr' => 'Actives'],
                    'draft'   => ['en' => 'Draft',   'fr' => 'Brouillon'],
                    'paused'  => ['en' => 'Paused',  'fr' => 'En pause'],
                    'expired' => ['en' => 'Expired', 'fr' => 'Expirées'],
                ];
            @endphp
            @foreach($tabs as $key => $t)
                <a href="{{ route('admin.sponsored-ads', ['tab' => $key]) }}"
                   class="flex-1 text-center px-4 py-2 rounded-lg text-sm font-medium transition-colors
                          {{ $tab === $key ? 'bg-cm-green text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span x-text="$store.lang.t('{{ addslashes($t['en']) }}', '{{ addslashes($t['fr']) }}')"></span>
                </a>
            @endforeach
        </div>

        {{-- Success message --}}
        @if(session('success'))
            <div class="p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        {{-- Ad Cards --}}
        <div class="space-y-4">
            @forelse($ads as $ad)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 hover:shadow-md transition-shadow">
                <div class="flex items-start gap-4">
                    {{-- Thumbnail --}}
                    <div class="w-24 h-24 rounded-lg overflow-hidden bg-slate-100 flex-shrink-0">
                        @if($ad->imageUrl())
                            <img src="{{ $ad->imageUrl() }}" alt="{{ $ad->title }}"
                                 class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-3xl text-slate-300">📢</div>
                        @endif
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="font-semibold text-slate-900 truncate">{{ $ad->title }}</h3>
                            @php
                                $statusColors = [
                                    'active' => 'bg-green-100 text-green-700',
                                    'draft' => 'bg-slate-100 text-slate-600',
                                    'paused' => 'bg-yellow-100 text-yellow-700',
                                    'expired' => 'bg-red-100 text-red-700',
                                ];
                            @endphp
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$ad->status] ?? 'bg-slate-100 text-slate-600' }}">
                                {{ ucfirst($ad->status) }}
                            </span>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-600">
                                {{ $ad->placement === 'yard_sidebar' ? '💬 Yard' : '🏠 Home' }}
                            </span>
                            @if($ad->video_url)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-600">
                                🎬 Video
                            </span>
                            @endif
                        </div>

                        @if($ad->description)
                            <p class="text-sm text-slate-500 line-clamp-1 mb-2">{{ $ad->description }}</p>
                        @endif

                        <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500">
                            @if($ad->advertiser_name)
                                <span>🏢 {{ $ad->advertiser_name }}</span>
                            @endif
                            @if($ad->link_url)
                                <span title="{{ $ad->link_url }}">🔗 {{ Str::limit($ad->link_url, 30) }}</span>
                            @endif
                            <span>⭐ Priority: {{ $ad->priority }}</span>
                            <span>👁️ {{ number_format($ad->impressions) }} views</span>
                            <span>👆 {{ number_format($ad->clicks) }} clicks</span>
                            <span>📊 CTR: {{ number_format($ad->ctr(), 2) }}%</span>
                            @if($ad->budget)
                                <span>💰 {{ number_format($ad->spent, 2) }}/{{ number_format($ad->budget, 2) }}</span>
                            @endif
                        </div>

                        @if($ad->starts_at || $ad->expires_at)
                            <div class="flex flex-wrap items-center gap-3 text-xs text-slate-400 mt-1">
                                @if($ad->starts_at)
                                    <span>📅 Start: {{ $ad->starts_at->format('M d, Y') }}</span>
                                @endif
                                @if($ad->expires_at)
                                    <span>⏰ Expires: {{ $ad->expires_at->format('M d, Y') }}</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="flex flex-col gap-2 shrink-0">
                        <a href="{{ route('admin.sponsored-ads.edit', $ad) }}"
                           class="px-3 py-1.5 bg-slate-50 text-slate-600 text-xs font-medium rounded-lg hover:bg-slate-100 transition-colors text-center">
                            ✏️ Edit
                        </a>
                        <form method="POST" action="{{ route('admin.sponsored-ads.toggle', $ad) }}">
                            @csrf
                            <button type="submit" class="w-full px-3 py-1.5 text-xs font-medium rounded-lg transition-colors text-center
                                {{ $ad->status === 'active' ? 'bg-yellow-50 text-yellow-700 hover:bg-yellow-100' : 'bg-green-50 text-green-700 hover:bg-green-100' }}">
                                {{ $ad->status === 'active' ? '⏸ Pause' : '▶️ Activate' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.sponsored-ads.delete', $ad) }}"
                              onsubmit="return confirm('Delete this ad permanently?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full px-3 py-1.5 bg-red-50 text-red-600 text-xs font-medium rounded-lg hover:bg-red-100 transition-colors text-center">
                                🗑 Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @empty
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center">
                <div class="text-5xl mb-3">📢</div>
                <p class="text-slate-500 font-medium" x-text="$store.lang.t('No ads found', 'Aucune annonce trouvée')"></p>
                <p class="text-sm text-slate-400 mt-1" x-text="$store.lang.t('Create your first sponsored ad to get started', 'Créez votre première annonce sponsorisée')"></p>
                <a href="{{ route('admin.sponsored-ads.create') }}"
                   class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 bg-cm-green text-white text-sm font-medium rounded-lg hover:bg-cm-green/90 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    <span x-text="$store.lang.t('Create Ad', 'Créer une Annonce')"></span>
                </a>
            </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if($ads->hasPages())
            <div>{{ $ads->withQueryString()->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
