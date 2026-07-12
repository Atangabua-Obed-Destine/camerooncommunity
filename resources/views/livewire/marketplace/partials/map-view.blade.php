{{--
    Marketplace map view — Leaflet (CDN).
    Listings are placed at their region centroid with a deterministic jitter so
    same-region pins fan out. Each pin opens a popup with the cover photo,
    title, price, and a link to the listing.

    Required props:
      $points  array[]  — output of FeedBrowse::mapPoints (id, slug, title, price, lat, lng, thumb, url)
      $center  array    — ['lat'=>..,'lng'=>..,'zoom'=>..]  initial view (defaults to country centre)
--}}
<div x-data="mpMap(@js($points), @js($center))"
     x-init="initMap()"
     wire:key="mp-map-view"
     class="bg-white rounded-2xl ring-1 ring-slate-200 shadow-sm overflow-hidden">
    <div class="px-4 py-2.5 flex items-center justify-between border-b border-slate-100">
        <div class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <svg class="w-4 h-4 text-cm-green" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0L6.343 16.657a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span x-data x-text="$store.lang.t('Map view','Vue carte')"></span>
        </div>
        <div class="text-[12px] text-slate-500">
            <span x-text="$root.points.length"></span>
            <span x-data x-text="$store.lang.t('locations','emplacements')"></span>
        </div>
    </div>
    <div id="mpMapCanvas" wire:ignore x-ref="canvas" class="h-[640px] w-full"></div>
    @if (empty($points))
        <div class="px-4 py-6 text-center text-sm text-slate-500 italic">
            {{ app()->getLocale() === 'fr'
                ? 'Aucune annonce avec une région reconnue dans cette recherche.'
                : 'No listings with a recognized region for this search.' }}
        </div>
    @endif
</div>

@once
    @push('scripts')
        <script>
            function mpMap(points, center) {
                return {
                    points: points || [],
                    center: center || { lat: 6.5, lng: 12.5, zoom: 6 },
                    _map: null,
                    _layer: null,

                    initMap() {
                        if (typeof L === 'undefined') {
                            if (!document.getElementById('leaflet-css')) {
                                let link = document.createElement('link');
                                link.id = 'leaflet-css';
                                link.rel = 'stylesheet';
                                link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                                document.head.appendChild(link);
                                
                                let script = document.createElement('script');
                                script.id = 'leaflet-js';
                                script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                                document.head.appendChild(script);
                            }
                            // Leaflet not ready yet — try again next tick
                            setTimeout(() => this.initMap(), 100);
                            return;
                        }
                        const el = this.$refs.canvas;
                        if (!el || el._leaflet_id) { return; }

                        this._map = L.map(el, { scrollWheelZoom: true })
                            .setView([this.center.lat, this.center.lng], this.center.zoom || 6);

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 18,
                            attribution: '© OpenStreetMap contributors',
                        }).addTo(this._map);

                        this.renderPins();

                        // Re-render when Livewire pushes new map points (filter change)
                        this.$watch('points', () => this.renderPins());
                    },

                    renderPins() {
                        if (!this._map) { return; }
                        if (this._layer) { this._map.removeLayer(this._layer); }
                        this._layer = L.layerGroup();

                        const bounds = [];
                        this.points.forEach(p => {
                            const html = `
                                <div style="min-width:180px">
                                    ${p.thumb ? `<img src="${p.thumb}" alt="" style="width:100%;height:90px;object-fit:cover;border-radius:8px;margin-bottom:6px">` : ''}
                                    <div style="font-weight:700;font-size:13px;color:#0f172a;line-height:1.2;margin-bottom:2px">${this.escape(p.title)}</div>
                                    <div style="font-weight:800;font-size:14px;color:#1e7a3e;margin-bottom:6px">${this.escape(p.price)}</div>
                                    <a href="${p.url}" wire:navigate style="display:inline-block;font-size:11px;font-weight:700;background:#1e7a3e;color:#fff;padding:5px 10px;border-radius:9999px;text-decoration:none">
                                        ${this.$store.lang ? this.$store.lang.t('View listing','Voir l\'annonce') : 'View'}
                                    </a>
                                </div>
                            `;
                            const marker = L.marker([p.lat, p.lng]).bindPopup(html);
                            marker.addTo(this._layer);
                            bounds.push([p.lat, p.lng]);
                        });
                        this._layer.addTo(this._map);

                        if (bounds.length > 0) {
                            this._map.fitBounds(bounds, { padding: [30, 30], maxZoom: 10 });
                        }
                    },

                    escape(s) {
                        return String(s ?? '').replace(/[&<>"']/g, c => ({
                            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
                        }[c]));
                    },
                };
            }
        </script>
    @endpush
@endonce
