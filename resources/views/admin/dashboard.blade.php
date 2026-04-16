<x-layouts.admin>
    <x-slot:header>Dashboard</x-slot:header>

    {{-- Metric cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
        @php
            $metrics = [
                ['label_en' => 'Total Users', 'label_fr' => 'Total Utilisateurs', 'value' => $totalUsers, 'icon' => '👥', 'color' => 'bg-blue-50 text-blue-600'],
                ['label_en' => 'Active Today', 'label_fr' => 'Actifs Aujourd\'hui', 'value' => $activeToday, 'icon' => '🟢', 'color' => 'bg-blue-50 text-blue-600'],
                ['label_en' => 'Messages Today', 'label_fr' => 'Messages Aujourd\'hui', 'value' => $messagesToday, 'icon' => '💬', 'color' => 'bg-purple-50 text-purple-600'],
                ['label_en' => 'Active Campaigns', 'label_fr' => 'Campagnes Actives', 'value' => $activeCampaigns, 'icon' => '🤲', 'color' => 'bg-yellow-50 text-yellow-700'],
                ['label_en' => 'Pending Reports', 'label_fr' => 'Signalements En Attente', 'value' => $pendingReports, 'icon' => '🚩', 'color' => 'bg-red-50 text-red-600'],
                ['label_en' => 'Pending Solidarity', 'label_fr' => 'Solidarité En Attente', 'value' => $pendingSolidarity, 'icon' => '⏳', 'color' => 'bg-orange-50 text-orange-600'],
            ];
        @endphp

        @foreach($metrics as $m)
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg {{ $m['color'] }} flex items-center justify-center text-lg">{{ $m['icon'] }}</div>
            </div>
            <p class="text-2xl font-bold text-slate-900">{{ number_format($m['value']) }}</p>
            <p class="text-xs text-slate-500 mt-0.5" x-text="$store.lang.t('{{ addslashes($m['label_en']) }}', '{{ addslashes($m['label_fr']) }}')"></p>
        </div>
        @endforeach
    </div>

    {{-- AI Insight --}}
    @if($aiInsight)
    <div class="bg-gradient-to-r from-purple-50 to-indigo-50 rounded-xl border border-purple-200 p-5 mb-8">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center text-lg shrink-0">🤖</div>
            <div>
                <h3 class="font-bold text-purple-900 text-sm mb-1" x-text="$store.lang.t('Kamer AI Insight', 'Insight IA Kamer')"></h3>
                <p class="text-sm text-purple-800 leading-relaxed">{{ $aiInsight }}</p>
            </div>
        </div>
    </div>
    @endif

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Recent registrations --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="font-bold text-slate-900 mb-4" x-text="$store.lang.t('Recent Registrations', 'Inscriptions Récentes')"></h3>
            <div class="space-y-3">
                @forelse($recentUsers as $user)
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-cm-green/10 flex items-center justify-center text-xs font-bold text-cm-green">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-900 truncate">{{ $user->name }}</p>
                        <p class="text-xs text-slate-400">{{ $user->current_country ?? 'Unknown' }} · {{ $user->created_at->diffForHumans() }}</p>
                    </div>
                    @if($user->is_founding_member)
                    <span class="text-xs bg-cm-yellow/10 text-cm-yellow font-bold rounded-full px-2 py-0.5">🏅 Founder</span>
                    @endif
                </div>
                @empty
                <p class="text-sm text-slate-400" x-text="$store.lang.t('No recent registrations', 'Aucune inscription récente')"></p>
                @endforelse
            </div>
        </div>

        {{-- Pending Solidarity campaigns --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-slate-900" x-text="$store.lang.t('Pending Solidarity Campaigns', 'Campagnes de Solidarité En Attente')"></h3>
                <a href="{{ route('admin.solidarity') }}" class="text-xs text-cm-green font-medium hover:underline" x-text="$store.lang.t('View All', 'Voir Tout')"></a>
            </div>
            <div class="space-y-3">
                @forelse($pendingCampaigns as $campaign)
                <div class="bg-slate-50 rounded-lg p-3 border border-slate-100">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $campaign->title }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $campaign->category?->icon() }} {{ $campaign->category?->label() }} · by {{ $campaign->creator?->name }}</p>
                        </div>
                        <span class="text-sm font-bold text-slate-700">{{ $campaign->currency }} {{ number_format($campaign->target_amount, 2) }}</span>
                    </div>
                    @if($campaign->ai_risk_score)
                    <div class="mt-2">
                        <span class="text-xs font-medium rounded-full px-2 py-0.5
                            {{ $campaign->ai_risk_score->value === 'low' ? 'bg-blue-100 text-blue-700' :
                               ($campaign->ai_risk_score->value === 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                            AI: {{ ucfirst($campaign->ai_risk_score->value) }}
                        </span>
                    </div>
                    @endif
                </div>
                @empty
                <p class="text-sm text-slate-400" x-text="$store.lang.t('No pending campaigns', 'Aucune campagne en attente')"></p>
                @endforelse
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="font-bold text-slate-900 mb-4" x-text="$store.lang.t('Quick Actions', 'Actions Rapides')"></h3>
            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('admin.solidarity') }}" class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-100 hover:border-cm-green transition-colors">
                    <span>🤲</span>
                    <span class="text-sm font-medium text-slate-700" x-text="$store.lang.t('Review Solidarity', 'Revue Solidarité')"></span>
                </a>
                <a href="{{ route('admin.moderation') }}" class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-100 hover:border-cm-green transition-colors">
                    <span>🛡️</span>
                    <span class="text-sm font-medium text-slate-700" x-text="$store.lang.t('Review Flagged', 'Contenu Signalé')"></span>
                </a>
                <a href="{{ route('admin.users') }}" class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-100 hover:border-cm-green transition-colors">
                    <span>👥</span>
                    <span class="text-sm font-medium text-slate-700" x-text="$store.lang.t('Manage Users', 'Gérer Utilisateurs')"></span>
                </a>
                <a href="{{ route('admin.settings') }}" class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-100 hover:border-cm-green transition-colors">
                    <span>⚙️</span>
                    <span class="text-sm font-medium text-slate-700" x-text="$store.lang.t('Settings', 'Paramètres')"></span>
                </a>
            </div>
        </div>

        {{-- Active rooms --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h3 class="font-bold text-slate-900 mb-4" x-text="$store.lang.t('Most Active Rooms', 'Salles les Plus Actives')"></h3>
            <div class="space-y-3">
                @forelse($topRooms as $room)
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span>{{ $room->room_type === \App\Enums\RoomType::National ? '🏳️' : '📍' }}</span>
                        <div>
                            <p class="text-sm font-medium text-slate-900">{{ $room->name }}</p>
                            <p class="text-xs text-slate-400">{{ $room->members_count }} members</p>
                        </div>
                    </div>
                    <span class="text-xs font-semibold text-slate-500">{{ number_format($room->messages_count) }} msgs</span>
                </div>
                @empty
                <p class="text-sm text-slate-400">No room activity yet</p>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.admin>
