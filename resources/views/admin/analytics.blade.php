<x-layouts.admin :title="'Analytics'">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold" x-text="$store.lang.t('Analytics', 'Analytique')"></h1>
            <div class="flex items-center gap-2">
                <select class="rounded-lg border-slate-300 text-sm focus:ring-cm-green focus:border-cm-green">
                    <option>Last 30 days</option>
                    <option>Last 7 days</option>
                    <option>Last 90 days</option>
                </select>
            </div>
        </div>

        {{-- User Growth Chart --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-900 mb-4" x-text="$store.lang.t('User Growth (Last 30 Days)', 'Croissance Utilisateurs (30 Derniers Jours)')"></h2>
            <div x-data="userGrowthChart()" x-init="init()" class="h-64">
                <canvas x-ref="chart"></canvas>
            </div>
        </div>

        {{-- Two Column Layout --}}
        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Geographic Distribution --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h2 class="font-semibold text-slate-900 mb-4" x-text="$store.lang.t('Top Countries', 'Pays Principaux')"></h2>
                <div class="space-y-3">
                    @forelse($countryCounts as $country)
                    @php
                        $maxCount = $countryCounts->max('count');
                        $pct = $maxCount > 0 ? ($country->count / $maxCount) * 100 : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-medium text-slate-700">{{ config("cameroon.countries.{$country->current_country}", $country->current_country) }}</span>
                            <span class="text-slate-500">{{ number_format($country->count) }}</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full bg-cm-green transition-all duration-500" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-slate-400 text-center py-4" x-text="$store.lang.t('No location data yet', 'Aucune donnée de localisation')"></p>
                    @endforelse
                </div>
            </div>

            {{-- Key Metrics --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h2 class="font-semibold text-slate-900 mb-4" x-text="$store.lang.t('Key Metrics', 'Indicateurs Clés')"></h2>
                <div class="space-y-4">
                    @php
                        $metrics = [
                            ['label' => 'Total Users', 'value' => \App\Models\User::count(), 'icon' => '👥', 'color' => 'green'],
                            ['label' => 'Active Rooms', 'value' => \App\Models\YardRoom::where('messages_count', '>', 0)->count(), 'icon' => '💬', 'color' => 'blue'],
                            ['label' => 'Total Messages', 'value' => \App\Models\YardMessage::count(), 'icon' => '✉️', 'color' => 'purple'],
                            ['label' => 'Solidarity Raised', 'value' => number_format(\App\Models\SolidarityCampaign::active()->sum('amount_raised')) . ' XAF', 'icon' => '🤲', 'color' => 'yellow'],
                            ['label' => 'Founding Members', 'value' => \App\Models\User::where('is_founding_member', true)->count(), 'icon' => '⭐', 'color' => 'amber'],
                        ];
                    @endphp
                    @foreach($metrics as $m)
                    <div class="flex items-center gap-3 p-3 rounded-lg bg-{{ $m['color'] }}-50/50">
                        <span class="text-xl">{{ $m['icon'] }}</span>
                        <div class="flex-1">
                            <p class="text-xs text-slate-500">{{ $m['label'] }}</p>
                            <p class="text-lg font-bold text-slate-900">{{ $m['value'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Activity Heatmap Placeholder --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-900 mb-4" x-text="$store.lang.t('Activity Heatmap', 'Carte de Chaleur d\'Activité')"></h2>
            <div class="text-center py-12 text-slate-400">
                <div class="text-4xl mb-3">📊</div>
                <p class="text-sm" x-text="$store.lang.t('Activity heatmap visualization coming soon', 'Visualisation carte de chaleur bientôt disponible')"></p>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function userGrowthChart() {
            return {
                init() {
                    const data = @json($userGrowth);
                    const canvas = this.$refs.chart;
                    const ctx = canvas.getContext('2d');

                    // Simple bar chart without external library
                    const rect = canvas.parentElement.getBoundingClientRect();
                    canvas.width = rect.width;
                    canvas.height = rect.height;

                    if (!data.length) {
                        ctx.fillStyle = '#94a3b8';
                        ctx.font = '14px Inter, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.fillText('No data available', canvas.width / 2, canvas.height / 2);
                        return;
                    }

                    const max = Math.max(...data.map(d => d.count), 1);
                    const padding = { top: 20, right: 20, bottom: 40, left: 50 };
                    const chartW = canvas.width - padding.left - padding.right;
                    const chartH = canvas.height - padding.top - padding.bottom;
                    const barW = Math.max(2, (chartW / data.length) - 2);

                    // Grid lines
                    ctx.strokeStyle = '#f1f5f9';
                    ctx.lineWidth = 1;
                    for (let i = 0; i <= 4; i++) {
                        const y = padding.top + (chartH / 4) * i;
                        ctx.beginPath();
                        ctx.moveTo(padding.left, y);
                        ctx.lineTo(canvas.width - padding.right, y);
                        ctx.stroke();

                        ctx.fillStyle = '#94a3b8';
                        ctx.font = '10px Inter, sans-serif';
                        ctx.textAlign = 'right';
                        ctx.fillText(Math.round(max - (max / 4) * i), padding.left - 8, y + 4);
                    }

                    // Bars
                    data.forEach((d, i) => {
                        const x = padding.left + (chartW / data.length) * i;
                        const h = (d.count / max) * chartH;
                        const y = padding.top + chartH - h;

                        ctx.fillStyle = '#243a5c';
                        ctx.beginPath();
                        ctx.roundRect(x, y, barW, h, [3, 3, 0, 0]);
                        ctx.fill();

                        // X-axis labels (every 5th)
                        if (i % 5 === 0 || i === data.length - 1) {
                            ctx.fillStyle = '#94a3b8';
                            ctx.font = '9px Inter, sans-serif';
                            ctx.textAlign = 'center';
                            const label = d.date.substring(5); // MM-DD
                            ctx.fillText(label, x + barW / 2, canvas.height - 10);
                        }
                    });
                }
            }
        }
    </script>
    @endpush
</x-layouts.admin>
