<x-layouts.admin :title="'Location history'">
    <div class="space-y-6">

        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('admin.users.show', $user) }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ $user->name }}</a>
                <h1 class="text-2xl font-bold">Location history</h1>
                <p class="text-sm text-slate-500">
                    {{ $points->total() }} point(s) between {{ $from->format('d M Y') }} and {{ $to->format('d M Y') }}
                    · kept for 90 days
                </p>
            </div>

            <div class="flex items-center gap-2">
                <form method="GET" class="flex items-end gap-2">
                    <label class="text-xs font-semibold text-slate-500">
                        From
                        <input type="date" name="from" value="{{ $from->toDateString() }}"
                               class="block rounded-lg border-slate-300 text-sm focus:ring-cm-green focus:border-cm-green">
                    </label>
                    <label class="text-xs font-semibold text-slate-500">
                        To
                        <input type="date" name="to" value="{{ $to->toDateString() }}"
                               class="block rounded-lg border-slate-300 text-sm focus:ring-cm-green focus:border-cm-green">
                    </label>
                    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Apply</button>
                </form>

                <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"
                   class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">CSV</a>
            </div>
        </div>

        {{-- This page exists to answer "where has this account been", which is
             sensitive enough that the visit is written to the audit log. Say so
             to whoever is looking. --}}
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Opening this page is recorded in the audit log against your account.
        </div>

        {{-- Map. Leaflet is lazy-loaded from the CDN exactly as the marketplace
             map does it, so this page costs nothing until it is opened. --}}
        @if($track->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <div x-data="locationTrack(@js($track->map(fn ($p) => [
                        'lat'   => (float) $p->lat,
                        'lng'   => (float) $p->lng,
                        'label' => trim(collect([$p->city, $p->region, $p->country])->filter()->unique()->join(', ')) ?: 'Unknown',
                        'when'  => $p->created_at?->format('d M Y H:i'),
                        'src'   => $p->source,
                    ])->values()))"
                     x-init="init()"
                     wire:ignore>
                    <div x-ref="map" class="h-[420px] w-full rounded-lg bg-slate-100"></div>
                    <p class="mt-2 text-xs text-slate-500">
                        Oldest point in green, newest in red, line follows the order they were recorded.
                    </p>
                </div>
            </div>
        @endif

        {{-- Table --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Recorded</th>
                        <th class="px-4 py-3">Place</th>
                        <th class="px-4 py-3">Coordinates</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($points as $point)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="font-medium text-slate-800">{{ $point->created_at?->format('d M Y H:i') }}</div>
                                <div class="text-xs text-slate-400">{{ $point->created_at?->diffForHumans() }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $point->placeLabel() }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">
                                {{ number_format((float) $point->lat, 5) }}, {{ number_format((float) $point->lng, 5) }}
                                <a href="https://www.openstreetmap.org/?mlat={{ $point->lat }}&mlon={{ $point->lng }}#map=14/{{ $point->lat }}/{{ $point->lng }}"
                                   target="_blank" rel="noopener"
                                   class="ml-1 text-cm-green hover:underline">map</a>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $point->source }}</span>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $point->ip_address ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-slate-500">
                                No positions recorded in this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $points->links() }}
    </div>

    @push('scripts')
    <script>
        function locationTrack(points) {
            return {
                points,
                init() {
                    if (! this.points.length) return;

                    // Same lazy CDN load the marketplace map uses: no npm
                    // dependency and nothing downloaded until this page opens.
                    const ready = () => {
                        if (! window.L) { setTimeout(ready, 100); return; }
                        this.draw();
                    };

                    if (! document.getElementById('leaflet-css')) {
                        const link = document.createElement('link');
                        link.id = 'leaflet-css';
                        link.rel = 'stylesheet';
                        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                        document.head.appendChild(link);
                    }
                    if (! document.getElementById('leaflet-js')) {
                        const script = document.createElement('script');
                        script.id = 'leaflet-js';
                        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                        document.body.appendChild(script);
                    }

                    ready();
                },

                draw() {
                    const el = this.$refs.map;
                    if (! el || el._leaflet_id) return;

                    const coords = this.points.map(p => [p.lat, p.lng]);
                    const map = L.map(el).setView(coords[coords.length - 1], 11);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap',
                        maxZoom: 18,
                    }).addTo(map);

                    L.polyline(coords, { color: '#015083', weight: 2, opacity: .7 }).addTo(map);

                    this.points.forEach((p, i) => {
                        const first = i === 0;
                        const last = i === this.points.length - 1;

                        L.circleMarker([p.lat, p.lng], {
                            radius: last ? 8 : 5,
                            color: first ? '#059669' : (last ? '#e11d48' : '#015083'),
                            fillColor: first ? '#059669' : (last ? '#e11d48' : '#015083'),
                            fillOpacity: .85,
                            weight: 2,
                        })
                            .addTo(map)
                            .bindPopup(`<strong>${p.label}</strong><br>${p.when}<br><span style="color:#64748b">${p.src}</span>`);
                    });

                    map.fitBounds(L.latLngBounds(coords).pad(0.2));
                },
            };
        }
    </script>
    @endpush
</x-layouts.admin>
