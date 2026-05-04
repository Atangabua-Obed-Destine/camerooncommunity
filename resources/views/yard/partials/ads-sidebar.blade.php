<aside class="yard-panel yard-panel--ads" x-data="yardAds()" x-cloak>
    <div class="yard-ads__header">
        <span class="yard-ads__label" x-text="$store.lang.t('Sponsored', 'Sponsorisé')"></span>
    </div>

    <div class="yard-ads__scroll">
        <template x-for="ad in ads" :key="ad.id">
            <div class="yard-ads__card">
                {{-- YouTube Video --}}
                <div class="yard-ads__card-video" x-show="ad.video">
                    <iframe :src="ad.video ? (ad.video + '?autoplay=1&mute=1&loop=1&playlist=' + ad.video.split('/').pop()) : ''" frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen loading="lazy"></iframe>
                </div>

                {{-- Image (when no video) --}}
                <a :href="'{{ url('/') }}/ad/' + ad.id + '/click'"
                   target="_blank" rel="noopener noreferrer"
                   x-show="!ad.video && ad.image" class="yard-ads__card-img">
                    <img :src="ad.image" :alt="ad.title" loading="lazy">
                </a>
                <div class="yard-ads__card-img yard-ads__card-img--placeholder" x-show="!ad.video && !ad.image">
                    <span>📢</span>
                </div>

                {{-- Body --}}
                <a :href="'{{ url('/') }}/ad/' + ad.id + '/click'"
                   target="_blank" rel="noopener noreferrer"
                   class="yard-ads__card-body">
                    <p class="yard-ads__card-title" x-text="ad.title"></p>
                    <p class="yard-ads__card-desc" x-text="ad.description" x-show="ad.description"></p>
                    <div class="yard-ads__card-footer">
                        <span class="yard-ads__card-advertiser" x-text="ad.advertiser" x-show="ad.advertiser"></span>
                        <span class="yard-ads__card-cta" x-text="ad.cta || 'Learn More'"></span>
                    </div>
                </a>

                {{-- Sponsored badge --}}
                <div class="yard-ads__badge">Ad</div>
            </div>
        </template>

        {{-- Empty state --}}
        <div x-show="ads.length === 0" class="yard-ads__empty">
            <span class="text-3xl">📢</span>
            <p x-text="$store.lang.t('No ads right now', 'Aucune annonce')"></p>
        </div>
    </div>
</aside>

@once
    @push('scripts')
    <script>
        if (typeof window.yardAds !== 'function') {
            window.yardAds = function() {
                return {
                    ads: [],
                    init() {
                        this.loadAds();
                        setInterval(() => this.loadAds(), 300000);
                    },
                    async loadAds() {
                        try {
                            const res = await fetch('{{ route("ads.yard") }}', { headers: { 'Accept': 'application/json' } });
                            if (res.ok) this.ads = await res.json();
                        } catch (e) { console.warn('Failed to load ads', e); }
                    },
                };
            };
        }
    </script>
    @endpush
@endonce
