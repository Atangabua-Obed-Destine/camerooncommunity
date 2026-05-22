{{--
    Discover Modal — animated, sliding hero + feature carousel.
    Triggered by `window.dispatchEvent(new CustomEvent('open-discover'))`.
--}}
<div x-data="discoverModal()"
     x-on:open-discover.window="openModal()"
     x-on:keydown.escape.window="open = false"
     x-cloak>

    {{-- Backdrop --}}
    <div x-show="open"
         x-transition.opacity.duration.300ms
         @click="open = false"
         class="fixed inset-0 z-[100] bg-slate-900/70 backdrop-blur-md"></div>

    {{-- Modal shell --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-8 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 z-[101] flex items-center justify-center p-3 sm:p-4 pointer-events-none">

        <div @click.stop
             @mouseenter="paused = true" @mouseleave="paused = false"
             class="discover-modal pointer-events-auto relative w-full max-w-3xl max-h-[92vh] overflow-y-auto rounded-3xl bg-white shadow-2xl">

            {{-- ═══════════════════ HERO SLIDER ═══════════════════ --}}
            <div class="relative h-64 sm:h-72 overflow-hidden rounded-t-3xl">
                <div class="absolute inset-0 discover-hero-bg"></div>

                {{-- Floating orbs --}}
                <div class="absolute inset-0 overflow-hidden pointer-events-none">
                    <div class="discover-orb discover-orb--1"></div>
                    <div class="discover-orb discover-orb--2"></div>
                    <div class="discover-orb discover-orb--3"></div>
                </div>

                {{-- Sparkles --}}
                <template x-for="i in 14" :key="'spark-'+i">
                    <span class="discover-sparkle"
                          :style="`top:${(i*7)%90}%; left:${(i*13)%95}%; animation-delay:${i*0.3}s;`"></span>
                </template>

                {{-- Slides --}}
                <div class="relative h-full">
                    <template x-for="(slide, idx) in slides" :key="idx">
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-6 transition-all duration-700 ease-out"
                             :class="idx === current
                                ? 'opacity-100 translate-y-0 scale-100'
                                : (idx < current ? 'opacity-0 -translate-y-6 scale-95 pointer-events-none' : 'opacity-0 translate-y-6 scale-95 pointer-events-none')">
                            <div class="text-6xl mb-3 discover-bounce" x-text="slide.emoji"></div>
                            <h2 class="text-2xl sm:text-3xl font-extrabold text-white drop-shadow-lg"
                                x-text="$store.lang.isEn ? slide.titleEn : slide.titleFr"></h2>
                            <p class="mt-2 text-sm sm:text-base text-white/95 max-w-md drop-shadow"
                               x-text="$store.lang.isEn ? slide.descEn : slide.descFr"></p>
                        </div>
                    </template>
                </div>

                {{-- Close --}}
                <button @click="open = false"
                        class="absolute top-3 right-3 w-9 h-9 z-10 flex items-center justify-center rounded-full bg-white/95 hover:bg-white text-slate-700 shadow-lg transition hover:rotate-90 duration-300"
                        :title="$store.lang.t('Close', 'Fermer')">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>

                {{-- Prev / Next --}}
                <button @click="prev()"
                        class="absolute left-2 top-1/2 -translate-y-1/2 w-9 h-9 z-10 flex items-center justify-center rounded-full bg-white/30 hover:bg-white/60 text-white transition backdrop-blur">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button @click="next()"
                        class="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 z-10 flex items-center justify-center rounded-full bg-white/30 hover:bg-white/60 text-white transition backdrop-blur">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>

                {{-- Dots --}}
                <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-2 z-10">
                    <template x-for="(slide, idx) in slides" :key="'dot-'+idx">
                        <button @click="goTo(idx)"
                                class="h-1.5 rounded-full transition-all duration-300"
                                :class="idx === current ? 'w-8 bg-white' : 'w-1.5 bg-white/50 hover:bg-white/80'"></button>
                    </template>
                </div>

                {{-- Progress bar --}}
                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-white/20 z-10">
                    <div class="h-full bg-white" :style="`width: ${progress}%;`"></div>
                </div>
            </div>

            {{-- ═══════════════════ LIVE NOW ═══════════════════ --}}
            <div class="px-6 pt-6">
                <div class="flex items-center gap-2 mb-4">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cm-green opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-cm-green"></span>
                    </span>
                    <h3 class="text-xs font-extrabold uppercase tracking-widest text-slate-600"
                        x-text="$store.lang.t('Live Now', 'Disponible Maintenant')"></h3>
                </div>

                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="discover-card group" style="--accent: #009639; --delay: 0ms;"
                         @click="open = false; window.location.href='{{ route('yard') }}'">
                        <div class="discover-card__icon">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        </div>
                        <div class="font-bold text-slate-800 text-sm" x-text="$store.lang.t('GoConnect', 'GoConnect')"></div>
                        <div class="text-[11px] text-slate-500 mt-0.5"
                             x-text="$store.lang.t('Chat & connect', 'Discuter & connecter')"></div>
                    </div>

                    <div class="discover-card group" style="--accent: #F59E0B; --delay: 100ms;"
                         @click="open = false; setTimeout(() => Livewire.dispatch('open-kamer-ai'), 200)">
                        <div class="discover-card__icon">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                        </div>
                        <div class="font-bold text-slate-800 text-sm">Kamer AI</div>
                        <div class="text-[11px] text-slate-500 mt-0.5"
                             x-text="$store.lang.t('Your guide', 'Votre guide')"></div>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════ COMING SOON ═══════════════════ --}}
            <div class="px-6 pt-6 pb-2">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-extrabold uppercase tracking-widest text-slate-600"
                        x-text="$store.lang.t('Coming Soon', 'Bientôt Disponible')"></h3>
                    <span class="text-[10px] uppercase tracking-wider font-bold text-slate-400"
                          x-text="$store.lang.t('Swipe →', 'Glisser →')"></span>
                </div>

                <div class="flex gap-3 overflow-x-auto pb-3 -mx-6 px-6 snap-x snap-mandatory discover-scroll">
                    <template x-for="(item, idx) in upcoming" :key="'up-'+idx">
                        <div class="discover-soon snap-start"
                             :style="`--accent: ${item.color}; --delay: ${idx*80}ms;`">
                            <div class="discover-soon__shine"></div>
                            <div class="discover-soon__icon" x-text="item.emoji"></div>
                            <div class="font-bold text-slate-800 text-sm" x-text="item.name"></div>
                            <div class="text-[11px] text-slate-500 mt-1 leading-snug"
                                 x-text="$store.lang.isEn ? item.descEn : item.descFr"></div>
                            <span class="discover-soon__badge"
                                  x-text="$store.lang.t('Soon', 'Bientôt')"></span>
                        </div>
                    </template>
                </div>
            </div>

            {{-- ═══════════════════ FOOTER ═══════════════════ --}}
            <div class="px-6 pb-6 pt-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="text-xs text-slate-500"
                   x-text="$store.lang.t('Help shape what comes next.', 'Aidez à façonner la suite.')"></p>
                <button @click="open = false; setTimeout(() => Livewire.dispatch('open-kamer-ai'), 200)"
                        class="discover-cta inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-white font-bold text-sm shadow-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                    <span x-text="$store.lang.t('Talk to Kamer AI', 'Parler à Kamer AI')"></span>
                </button>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
function discoverModal() {
    return {
        open: false,
        current: 0,
        progress: 0,
        paused: false,
        timer: null,
        slideDuration: 5000,
        slides: [
            { emoji: '🌍',
              titleEn: 'One Diaspora, One Home',
              titleFr: 'Une Diaspora, Une Maison',
              descEn: 'KAMER connects Cameroonians across the world — wherever you are, you belong.',
              descFr: 'KAMER connecte les Camerounais du monde entier — où que vous soyez, vous êtes chez vous.' },
            { emoji: '💬',
              titleEn: 'GoConnect is Buzzing',
              titleFr: 'GoConnect Bouge',
              descEn: 'Public rooms, private chats, group conversations — your daily meeting place.',
              descFr: 'Salons publics, discussions privées, groupes — votre lieu de rencontre quotidien.' },
            { emoji: '❤️',
              titleEn: 'Solidarity in Action',
              titleFr: 'La Solidarité en Action',
              descEn: 'Fundraisers, mutual aid, community support — we lift each other up.',
              descFr: "Collectes, entraide, soutien communautaire — on s'élève ensemble." },
            { emoji: '✨',
              titleEn: 'Meet Kamer AI',
              titleFr: 'Rencontrez Kamer AI',
              descEn: 'Your bilingual guide to the diaspora. Ask anything, anytime.',
              descFr: 'Votre guide bilingue de la diaspora. Demandez tout, à tout moment.' },
            { emoji: '🚀',
              titleEn: 'Much More Coming',
              titleFr: 'Bien Plus Arrive',
              descEn: 'GoMarket, parcels home, carpooling, jobs — built by us, for us.',
              descFr: 'GoMarket, colis, covoiturage, emplois — fait par nous, pour nous.' },
        ],
        upcoming: [
            { name: 'Solidarity', emoji: '❤️', color: '#CE1126',
              descEn: 'Mutual aid & community fundraising.',
              descFr: 'Entraide & cagnottes communautaires.' },
            { name: 'GoMarket', emoji: '🛒', color: '#009639',
              descEn: 'Buy & sell within the diaspora.',
              descFr: 'Achetez & vendez dans la diaspora.' },
            { name: 'EasyGoParcel', emoji: '📦', color: '#0EA5E9',
              descEn: 'Send parcels home with trusted travelers.',
              descFr: 'Envoyez des colis avec des voyageurs vérifiés.' },
            { name: 'GoRide', emoji: '🚗', color: '#CE1126',
              descEn: 'Carpool across cities & borders.',
              descFr: 'Covoiturez entre villes et frontières.' },
            { name: 'WorkConnect', emoji: '💼', color: '#7C3AED',
              descEn: 'Jobs & gigs for the community.',
              descFr: 'Emplois & missions pour la communauté.' },
            { name: 'KamerEvents', emoji: '🎉', color: '#F59E0B',
              descEn: 'Weddings, parties, conferences near you.',
              descFr: 'Mariages, fêtes, conférences près de chez vous.' },
        ],
        openModal() {
            this.open = true;
            this.current = 0;
            this.progress = 0;
            this.startAuto();
        },
        startAuto() {
            this.stopAuto();
            const tick = 100;
            this.timer = setInterval(() => {
                if (!this.open) return;
                if (this.paused) return;
                this.progress += (tick / this.slideDuration) * 100;
                if (this.progress >= 100) this.next();
            }, tick);
        },
        stopAuto() {
            if (this.timer) { clearInterval(this.timer); this.timer = null; }
        },
        next() {
            this.current = (this.current + 1) % this.slides.length;
            this.progress = 0;
        },
        prev() {
            this.current = (this.current - 1 + this.slides.length) % this.slides.length;
            this.progress = 0;
        },
        goTo(i) {
            this.current = i;
            this.progress = 0;
        },
    };
}
</script>
<style>
[x-cloak] { display: none !important; }

.discover-hero-bg {
    background: linear-gradient(135deg, #009639 0%, #CE1126 50%, #FCD116 100%);
    background-size: 200% 200%;
    animation: discoverGradient 12s ease infinite;
}
@keyframes discoverGradient {
    0%   { background-position: 0% 50%; }
    50%  { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.discover-orb {
    position: absolute;
    border-radius: 9999px;
    filter: blur(40px);
    opacity: 0.55;
    mix-blend-mode: screen;
}
.discover-orb--1 { width: 200px; height: 200px; background: #FCD116; top: -40px; left: -40px;
    animation: orbFloat1 9s ease-in-out infinite; }
.discover-orb--2 { width: 180px; height: 180px; background: #009639; bottom: -50px; right: 10%;
    animation: orbFloat2 11s ease-in-out infinite; }
.discover-orb--3 { width: 150px; height: 150px; background: #CE1126; top: 30%; right: -30px;
    animation: orbFloat3 10s ease-in-out infinite; }
@keyframes orbFloat1 { 0%,100%{transform:translate(0,0) scale(1);} 50%{transform:translate(60px,40px) scale(1.15);} }
@keyframes orbFloat2 { 0%,100%{transform:translate(0,0) scale(1);} 50%{transform:translate(-50px,-30px) scale(1.1);} }
@keyframes orbFloat3 { 0%,100%{transform:translate(0,0) scale(1);} 50%{transform:translate(-40px,50px) scale(1.2);} }

.discover-sparkle {
    position: absolute;
    width: 4px; height: 4px;
    background: white;
    border-radius: 9999px;
    box-shadow: 0 0 8px 2px rgba(255,255,255,0.9);
    opacity: 0;
    animation: sparkle 3s ease-in-out infinite;
}
@keyframes sparkle {
    0%,100% { opacity: 0; transform: scale(0.5); }
    50%     { opacity: 1; transform: scale(1.4); }
}

.discover-bounce {
    animation: discoverBounce 2.5s ease-in-out infinite;
    display: inline-block;
}
@keyframes discoverBounce {
    0%,100% { transform: translateY(0) rotate(0); }
    25%     { transform: translateY(-8px) rotate(-5deg); }
    75%     { transform: translateY(-4px) rotate(5deg); }
}

.discover-card {
    cursor: pointer;
    padding: 14px;
    border-radius: 14px;
    border: 2px solid #E2E8F0;
    background: white;
    transition: all 0.3s cubic-bezier(.2,.9,.3,1.4);
    position: relative;
    overflow: hidden;
    animation: cardIn 0.6s cubic-bezier(.2,.9,.3,1.4) backwards;
    animation-delay: var(--delay, 0ms);
}
.discover-card:hover {
    transform: translateY(-4px) scale(1.03);
    border-color: var(--accent);
    box-shadow: 0 10px 30px -10px var(--accent);
}
.discover-card__icon {
    width: 36px; height: 36px;
    border-radius: 10px;
    background: color-mix(in srgb, var(--accent) 12%, white);
    color: var(--accent);
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 8px;
    transition: transform 0.3s;
}
.discover-card:hover .discover-card__icon {
    transform: rotate(-8deg) scale(1.1);
}
@keyframes cardIn {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}

.discover-scroll::-webkit-scrollbar { height: 4px; }
.discover-scroll::-webkit-scrollbar-track { background: transparent; }
.discover-scroll::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 9999px; }

.discover-soon {
    position: relative;
    flex: 0 0 200px;
    padding: 16px;
    border-radius: 16px;
    background: linear-gradient(145deg, #fff, #F8FAFC);
    border: 1px dashed color-mix(in srgb, var(--accent) 50%, #CBD5E1);
    overflow: hidden;
    animation: cardIn 0.7s cubic-bezier(.2,.9,.3,1.4) backwards;
    animation-delay: var(--delay, 0ms);
    transition: transform 0.3s, box-shadow 0.3s;
}
.discover-soon:hover {
    transform: translateY(-6px) rotate(-1deg);
    box-shadow: 0 15px 30px -12px color-mix(in srgb, var(--accent) 40%, transparent);
}
.discover-soon__icon {
    font-size: 28px;
    margin-bottom: 8px;
    display: inline-block;
    transition: transform 0.4s cubic-bezier(.2,.9,.3,1.4);
}
.discover-soon:hover .discover-soon__icon {
    transform: scale(1.25) rotate(10deg);
}
.discover-soon__badge {
    position: absolute;
    top: 10px; right: 10px;
    font-size: 9px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    padding: 3px 8px;
    border-radius: 9999px;
    background: var(--accent);
    color: white;
}
.discover-soon__shine {
    position: absolute;
    top: 0; left: -100%;
    width: 50%; height: 100%;
    background: linear-gradient(120deg, transparent, rgba(255,255,255,0.5), transparent);
    transform: skewX(-20deg);
    transition: left 0.7s;
}
.discover-soon:hover .discover-soon__shine { left: 200%; }

.discover-cta {
    background: linear-gradient(135deg, #009639, #00b347);
    background-size: 200% 100%;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}
.discover-cta:hover {
    background-position: 100% 0;
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0,150,57,0.5);
}
.discover-cta::before {
    content: '';
    position: absolute;
    top: 0; left: -100%;
    width: 100%; height: 100%;
    background: linear-gradient(120deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.6s;
}
.discover-cta:hover::before { left: 100%; }

.discover-modal::-webkit-scrollbar { width: 6px; }
.discover-modal::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 9999px; }
</style>
@endpush
@endonce
