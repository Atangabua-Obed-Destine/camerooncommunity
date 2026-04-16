<x-layouts.admin :title="'Solidarity'">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold" x-text="$store.lang.t('Solidarity Management', 'Gestion Solidarité')"></h1>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-1 bg-white rounded-xl shadow-sm border border-slate-200 p-1">
            @php
                $tabs = [
                    'pending' => ['en' => 'Pending', 'fr' => 'En attente', 'color' => 'yellow'],
                    'active' => ['en' => 'Active', 'fr' => 'Actives', 'color' => 'green'],
                    'completed' => ['en' => 'Completed', 'fr' => 'Terminées', 'color' => 'blue'],
                    'rejected' => ['en' => 'Rejected', 'fr' => 'Rejetées', 'color' => 'red'],
                ];
            @endphp
            @foreach($tabs as $key => $t)
                <a href="{{ route('admin.solidarity', ['tab' => $key]) }}"
                   class="flex-1 text-center px-4 py-2 rounded-lg text-sm font-medium transition-colors
                          {{ $tab === $key ? 'bg-cm-green text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span x-text="$store.lang.t('{{ addslashes($t['en']) }}', '{{ addslashes($t['fr']) }}')"></span>
                </a>
            @endforeach
        </div>

        {{-- Campaign Cards --}}
        <div class="space-y-4">
            @forelse($campaigns as $campaign)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-lg">{{ $campaign->category->icon() }}</span>
                            <h3 class="font-semibold text-slate-900 truncate">{{ $campaign->title }}</h3>
                            @if($campaign->ai_risk_score)
                                @php $riskColor = $campaign->ai_risk_score >= 70 ? 'red' : ($campaign->ai_risk_score >= 40 ? 'yellow' : 'green'); @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    bg-{{ $riskColor }}-100 text-{{ $riskColor }}-700">
                                    🤖 Risk: {{ $campaign->ai_risk_score }}%
                                </span>
                            @endif
                        </div>
                        <p class="text-sm text-slate-500 line-clamp-2 mb-3">{{ $campaign->description }}</p>

                        <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500">
                            <span>👤 {{ $campaign->creator?->name ?? 'Unknown' }}</span>
                            <span>💰 {{ number_format($campaign->goal_amount) }} {{ $campaign->currency }}</span>
                            <span>📍 {{ $campaign->room?->name ?? '—' }}</span>
                            <span>📅 {{ $campaign->created_at->format('M d, Y') }}</span>
                            @if($campaign->deadline)
                                <span>⏰ Deadline: {{ $campaign->deadline->format('M d, Y') }}</span>
                            @endif
                            @if($campaign->is_anonymous)
                                <span class="text-purple-600">🕵️ Anonymous</span>
                            @endif
                        </div>

                        @if($tab === 'active' || $tab === 'completed')
                            <div class="mt-3">
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="font-medium text-slate-600">{{ number_format($campaign->amount_raised) }} / {{ number_format($campaign->goal_amount) }} {{ $campaign->currency }}</span>
                                    <span class="text-cm-green font-medium">{{ $campaign->progressPercent }}%</span>
                                </div>
                                <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full bg-cm-green transition-all duration-500" style="width: {{ min($campaign->progressPercent, 100) }}%"></div>
                                </div>
                            </div>
                        @endif

                        @if($campaign->rejection_reason)
                            <div class="mt-3 p-2 bg-red-50 rounded-lg text-xs text-red-700">
                                <strong>Rejection reason:</strong> {{ $campaign->rejection_reason }}
                            </div>
                        @endif
                    </div>

                    {{-- Actions --}}
                    @if($tab === 'pending')
                        <div class="flex flex-col gap-2 shrink-0">
                            <form method="POST" action="{{ route('admin.solidarity.approve', $campaign) }}">
                                @csrf
                                <button type="submit" class="w-full px-4 py-2 bg-cm-green text-white text-xs font-medium rounded-lg hover:bg-cm-green/90 transition-colors">
                                    ✅ Approve
                                </button>
                            </form>
                            <div x-data="{ showReject: false }">
                                <button @click="showReject = true" class="w-full px-4 py-2 bg-red-50 text-red-600 text-xs font-medium rounded-lg hover:bg-red-100 transition-colors">
                                    ❌ Reject
                                </button>
                                <div x-show="showReject" x-transition class="mt-2">
                                    <form method="POST" action="{{ route('admin.solidarity.reject', $campaign) }}">
                                        @csrf
                                        <textarea name="reason" required rows="2" placeholder="Rejection reason..."
                                                  class="w-full text-xs rounded-lg border-slate-300 mb-2 focus:ring-red-500 focus:border-red-500"></textarea>
                                        <button type="submit" class="w-full px-3 py-1.5 bg-red-600 text-white text-xs rounded-lg hover:bg-red-700">
                                            Confirm Reject
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            @empty
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center">
                <p class="text-slate-400" x-text="$store.lang.t('No campaigns in this category', 'Aucune campagne dans cette catégorie')"></p>
            </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if($campaigns->hasPages())
            <div>{{ $campaigns->withQueryString()->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
