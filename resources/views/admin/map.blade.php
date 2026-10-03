<x-layouts.admin :title="'Live map'">
    <div class="space-y-6">

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">Live map</h1>
                <p class="text-sm text-slate-500">
                    {{ $people->count() }} member(s) with a known position.
                    Latest position only — open a member for their history.
                </p>
            </div>

            <form method="GET" class="flex flex-wrap items-end gap-2">
                <label class="text-xs font-semibold text-slate-500">
                    Country
                    <select name="country" class="block rounded-lg border-slate-300 text-sm focus:ring-cm-green focus:border-cm-green">
                        <option value="">All</option>
                        @foreach($countries as $country)
                            <option value="{{ $country }}" @selected(request('country') === $country)>{{ $country }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-500">
                    Active
                    <select name="seen" class="block rounded-lg border-slate-300 text-sm focus:ring-cm-green focus:border-cm-green">
                        <option value="">Any time</option>
                        <option value="24h" @selected(request('seen') === '24h')>Last 24 hours</option>
                        <option value="7d" @selected(request('seen') === '7d')>Last 7 days</option>
                    </select>
                </label>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Apply</button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <div x-data="peopleMap(@js($people->map(fn ($p) => [
                    'lat'   => (float) $p->current_lat,
                    'lng'   => (float) $p->current_lng,
                    'name'  => $p->username ?: $p->name,
                    'place' => trim(collect([$p->current_city, $p->current_region, $p->current_country])->filter()->unique()->join(', ')) ?: 'Unknown',
                    'seen'  => $p->last_active_at?->diffForHumans(),
                    'url'   => route('admin.users.show', $p),
                ])->values()))"
                 x-init="init()"
                 wire:ignore>
                <div x-ref="map" class="h-[600px] w-full rounded-lg bg-slate-100"></div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function peopleMap(people) {
            return {
                people,
                init() {
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

                    // Centred on Cameroon until the data says otherwise.
                    const map = L.map(el).setView([4.0511, 9.7679], 5);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap',
                        maxZoom: 18,
                    }).addTo(map);

                    const coords = [];

                    this.people.forEach((p) => {
                        coords.push([p.lat, p.lng]);

                        L.circleMarker([p.lat, p.lng], {
                            radius: 6,
                            color: '#015083',
                            fillColor: '#015083',
                            fillOpacity: .8,
                            weight: 2,
                        })
                            .addTo(map)
                            .bindPopup(
                                `<strong>${p.name}</strong><br>${p.place}` +
                                (p.seen ? `<br><span style="color:#64748b">active ${p.seen}</span>` : '') +
                                `<br><a href="${p.url}" style="color:#015083">Open profile</a>`
                            );
                    });

                    if (coords.length) {
                        map.fitBounds(L.latLngBounds(coords).pad(0.2));
                    }
                },
            };
        }
    </script>
    @endpush
</x-layouts.admin>
