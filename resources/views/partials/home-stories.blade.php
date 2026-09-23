{{--
    Sponsored ads as Facebook-style stories, for the authenticated home feed.

    Why this exists: the right-hand ads sidebar (.yard-panel--ads) only appears at
    1280px and up, so every phone, tablet and small laptop saw no ads at all.

    TWO NESTED WRAPPERS ON PURPOSE — do not merge them:
      .cm-stories      outer, hidden/shown by the media query below
      x-data/x-show    inner, hidden when there are no ads
    Alpine's x-show writes an inline `display`, and inline style beats any
    stylesheet rule. If these two live on one element, x-show wins and the strip
    shows on desktop too. Keeping them apart keeps the two mechanisms separate.

    The breakpoint is hand-written CSS rather than a Tailwind class because
    `xl:hidden` is not in the compiled CSS, and adding it would force
    `npm run build`, which dirties the committed public/build.
--}}
@once
<style>
    /* Complement of .yard-panel--ads (app.css: shown at min-width 1280px), so every
       viewport gets ads from exactly one surface: strip here, sidebar there.
       Change this single number to move the cutoff. */
    .cm-stories { display: none; }
    @media (max-width: 1279px) { .cm-stories { display: block; } }
</style>
@endonce

<div class="cm-stories">
    <div x-data="homeStories()" x-show="ads.length > 0" x-cloak class="mb-6">

        {{-- Heading --}}
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wide"
                x-text="$store.lang.t('Sponsored', 'Sponsorisé')"></h2>
        </div>

        {{-- Strip. Scroller classes are the compiled idiom from listing-detail. --}}
        <div class="relative flex overflow-x-auto gap-2 pb-1 -mx-4 px-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <template x-for="(ad, index) in ads" :key="ad.id">
                {{-- 9:16 tile: inline size because w-[104px]/h-[185px] are not compiled. --}}
                <div @click="open(index)"
                     class="shrink-0 relative overflow-hidden cursor-pointer bg-slate-200"
                     style="width:104px; height:185px; border-radius:14px;">

                    {{-- Media: ad image, else the YouTube poster, else a glyph. --}}
                    <template x-if="cardImage(ad)">
                        <img :src="cardImage(ad)" :alt="ad.title" loading="lazy" decoding="async"
                             class="absolute inset-0 w-full h-full object-cover">
                    </template>
                    <template x-if="!cardImage(ad)">
                        <div class="absolute inset-0 flex items-center justify-center text-3xl">📢</div>
                    </template>

                    {{-- Play glyph for video ads --}}
                    <template x-if="ad.video">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span style="width:30px; height:30px; border-radius:9999px; background:rgba(0,0,0,.55); color:#fff; display:grid; place-items:center; font-size:12px;">▶</span>
                        </div>
                    </template>

                    {{-- Scrim so the title stays readable on any image --}}
                    <div class="absolute inset-x-0 bottom-0"
                         style="height:70px; background:linear-gradient(to top, rgba(0,0,0,.75), transparent);"></div>

                    {{-- Advertiser initial --}}
                    <div class="absolute"
                         style="top:8px; left:8px; width:30px; height:30px; border-radius:9999px; border:2.5px solid #015083; background:#fff; display:grid; place-items:center; font-weight:700; font-size:11px; color:#015083;"
                         x-text="(ad.advertiser || ad.title || '?').charAt(0).toUpperCase()"></div>

                    {{-- Ad badge --}}
                    <div class="absolute"
                         style="top:8px; right:8px; background:rgba(0,0,0,.6); color:#fff; font-size:9px; font-weight:700; padding:2px 5px; border-radius:4px; letter-spacing:.04em;"
                         x-text="$store.lang.t('Ad', 'Pub')"></div>

                    {{-- Title --}}
                    <div class="absolute line-clamp-2"
                         style="left:8px; right:8px; bottom:7px; color:#fff; font-size:11px; font-weight:600; line-height:1.25; text-shadow:0 1px 3px rgba(0,0,0,.6);"
                         x-text="ad.title"></div>
                </div>
            </template>
        </div>

        {{-- ─── Fullscreen viewer ───
             Teleported to <body> on purpose. .yard-container is position:fixed,
             which creates a stacking context, so z-[100] inside it is trapped and
             the app header (z-50, at the root) paints over the top of the screen,
             hiding the progress bars and the close button. --}}
        <template x-teleport="body">
        <div x-show="viewing" x-cloak
             class="fixed inset-0 z-[100] flex flex-col overscroll-contain"
             {{-- Translucent rather than solid black, so the page stays visible
                  behind the ad. Blurred so white overlay text stays readable. --}}
             style="background:rgba(0,0,0,.62); -webkit-backdrop-filter:blur(6px); backdrop-filter:blur(6px);"
             @touchstart="touchY = $event.changedTouches[0].clientY"
             @touchend="if ($event.changedTouches[0].clientY - touchY > 70) close()"
             @keydown.escape.window="if (viewing) close()"
             @keydown.arrow-right.window="if (viewing) next()"
             @keydown.arrow-left.window="if (viewing) prev()">

            {{-- Progress bars, one per ad --}}
            <div class="absolute flex" style="top:10px; left:10px; right:10px; gap:4px; z-index:3;">
                <template x-for="(ad, n) in ads" :key="'bar-' + ad.id">
                    <div style="flex:1; height:2.5px; border-radius:2px; background:rgba(255,255,255,.35); overflow:hidden;">
                        <div :style="`width:${n <= i ? 100 : 0}%; height:100%; background:#fff;`"></div>
                    </div>
                </template>
            </div>

            {{-- Advertiser + close --}}
            <div class="absolute flex items-center" style="top:24px; left:12px; right:12px; gap:8px; z-index:3;">
                <div style="width:28px; height:28px; border-radius:9999px; background:#fff; color:#015083; display:grid; place-items:center; font-weight:700; font-size:11px;"
                     x-text="(current?.advertiser || current?.title || '?').charAt(0).toUpperCase()"></div>
                <span style="color:#fff; font-size:13px; font-weight:600; flex:1; min-width:0;"
                      class="truncate" x-text="current?.advertiser || ''"></span>
                {{-- The only way out now that stories do not time out, so it gets a
                     solid hit area and a backing circle to stay visible on any image. --}}
                <button type="button" @click.stop="close()"
                        style="flex-shrink:0; width:38px; height:38px; border-radius:9999px; background:rgba(0,0,0,.45); color:#fff; font-size:24px; line-height:1; display:grid; place-items:center;"
                        :aria-label="$store.lang.t('Close', 'Fermer')">&times;</button>
            </div>

            {{-- Media.
                 @click.self closes: a click that lands on this container rather than
                 on a child is a click on the empty space around the ad. The previous
                 and next tap zones live INSIDE the media box below, not across the
                 full screen, so the empty space stays free to dismiss. --}}
            <div class="absolute inset-0 flex items-center justify-center" style="z-index:1;"
                 @click.self="close()">

                <template x-if="current?.video">
                    <div class="relative" style="width:100%; max-width:900px;">
                        {{-- Created on open and destroyed on close, so nothing keeps
                             playing behind a hidden overlay.
                             Sized to the video's own 16:9 box rather than inset-0, so the
                             translucent backdrop still shows around it. Full-bleed made
                             the overlay look solid black, because YouTube paints its own
                             black behind the picture. --}}
                        <iframe :src="videoSrc(current)" frameborder="0" allow="autoplay; encrypted-media"
                                style="width:100%; aspect-ratio:16/9; max-height:78vh; border:0; pointer-events:none; display:block;"></iframe>
                        <div class="absolute inset-y-0 left-0" style="width:50%;" @click.stop="prev()"></div>
                        <div class="absolute inset-y-0 right-0" style="width:50%;" @click.stop="next()"></div>
                    </div>
                </template>

                <template x-if="!current?.video && current?.image">
                    <div class="relative" style="max-width:100%; max-height:100%;">
                        <img :src="current.image" :alt="current.title"
                             class="max-w-full max-h-full object-contain" style="display:block;">
                        <div class="absolute inset-y-0 left-0" style="width:50%;" @click.stop="prev()"></div>
                        <div class="absolute inset-y-0 right-0" style="width:50%;" @click.stop="next()"></div>
                    </div>
                </template>

                <template x-if="!current?.video && !current?.image">
                    <div style="color:#fff; font-size:48px;" @click.stop="next()">📢</div>
                </template>
            </div>

            {{-- Title, description and CTA --}}
            <div class="absolute" style="left:16px; right:16px; bottom:28px; z-index:3; text-align:center;">
                <p style="color:#fff; font-size:17px; font-weight:700; line-height:1.3;" x-text="current?.title"></p>
                <p class="line-clamp-2" style="color:rgba(255,255,255,.8); font-size:13px; margin-top:6px;"
                   x-show="current?.description" x-text="current?.description"></p>

                <template x-if="current?.has_link">
                    <a :href="'{{ url('/') }}/ad/' + current.id + '/click'"
                       target="_blank" rel="noopener noreferrer" @click.stop
                       style="display:inline-block; margin-top:14px; background:#015083; color:#fff; border-radius:9999px; padding:12px 26px; font-weight:700; font-size:14px;"
                       x-text="current.cta || $store.lang.t('Learn more', 'En savoir plus')"></a>
                </template>
            </div>
        </div>
        </template>
    </div>
</div>

@once
@push('scripts')
<script>
if (typeof window.homeStories !== 'function') {
    window.homeStories = function () {
        return {
            ads: [],
            seen: new Set(),
            viewing: false,
            i: 0,
            touchY: 0,
            _dwell: null,

            get current() { return this.ads[this.i] || null; },

            init() { this.load(); },   // one fetch, no polling: polling is what inflated impressions

            async load() {
                try {
                    const res = await fetch('{{ route('ads.home') }}', { headers: { Accept: 'application/json' } });
                    if (!res.ok) return;
                    // A not-yet-onboarded user gets redirected to HTML here, and fetch
                    // follows redirects, so res.ok is true but the body is a page.
                    if (!(res.headers.get('content-type') || '').includes('json')) return;
                    this.ads = await res.json();
                } catch (e) { /* ads are never worth breaking the page for */ }
            },

            cardImage(ad) {
                if (ad.image) return ad.image;
                if (ad.video) {
                    const id = String(ad.video).split('/').pop().split('?')[0];
                    return id ? `https://i.ytimg.com/vi/${id}/hqdefault.jpg` : null;
                }
                return null;
            },

            videoSrc(ad) {
                const id = String(ad.video).split('/').pop().split('?')[0];
                // playsinline is required on iOS; controls off so they don't fight the tap zones.
                return `${ad.video}?autoplay=1&mute=1&loop=1&playlist=${id}&controls=0&playsinline=1&rel=0`;
            },

            // A story stays put until the viewer closes it: no timer, no
            // auto-advance, and reaching the last one does not close anything.
            open(index) {
                this.i = index;
                this.viewing = true;
                this.lockScroll(true);
                this.show();
            },

            close() {
                this.viewing = false;
                this.stop();
                this.lockScroll(false);
            },

            show() {
                this.stop();
                this.markSeen();
            },

            stop() {
                if (this._dwell) { clearTimeout(this._dwell); this._dwell = null; }
            },

            next() {
                if (this.i >= this.ads.length - 1) return;   // stop at the last one
                this.i++;
                this.show();
            },

            prev() {
                if (this.i === 0) return;                    // stop at the first one
                this.i--;
                this.show();
            },

            // An impression means a human actually looked at this story: it needs a
            // second of dwell, and counts once per ad per page. The server also
            // guards per session.
            markSeen() {
                const ad = this.current;
                if (!ad || this.seen.has(ad.id)) return;

                this._dwell = setTimeout(() => {
                    if (!this.viewing || this.current?.id !== ad.id) return;
                    this.seen.add(ad.id);
                    fetch(`{{ url('/') }}/ad/${ad.id}/impression`, {
                        method: 'POST',
                        keepalive: true,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            'Accept': 'application/json',
                        },
                    }).catch(() => {});
                }, 1000);
            },

            // .yard-container is position:fixed and <main> is the real scroller here,
            // so locking the body alone does nothing on this layout.
            lockScroll(on) {
                const main = document.querySelector('main');
                if (on) {
                    this._prevMain = main ? main.style.overflow : '';
                    if (main) main.style.overflow = 'hidden';
                    document.body.style.overflow = 'hidden';
                } else {
                    if (main) main.style.overflow = this._prevMain || '';
                    document.body.style.overflow = '';
                }
            },
        };
    };
}
</script>
@endpush
@endonce
