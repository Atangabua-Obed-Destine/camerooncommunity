<x-layouts.app>
    <x-slot:title>{{ __('Welcome') }} — Cameroon Community</x-slot:title>

    {{-- Confetti canvas for founding members --}}
    @if(auth()->user()->is_founding_member)
    <canvas id="confetti-canvas" class="fixed inset-0 z-50 pointer-events-none" width="1" height="1"></canvas>
    @endif

    <div class="min-h-[calc(100vh-4rem)] flex items-center justify-center py-8 px-4" x-data="onboarding()">
        {{-- Onboarding card --}}
        <div class="w-full max-w-2xl">
            {{-- Step indicators --}}
            <div class="flex items-center justify-center gap-2 mb-6">
                <template x-for="i in totalSlides" :key="i">
                    <button @click="slide = i"
                            class="h-2 rounded-full transition-all duration-300"
                            :class="slide === i ? 'w-8 bg-cm-green' : 'w-2 bg-slate-300'">
                    </button>
                </template>
            </div>

            {{-- SLIDE 1: Welcome --}}
            <div x-show="slide === 1" x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0"
                 class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">

                {{-- Hero banner --}}
                <div class="relative bg-gradient-to-br from-cm-green to-cm-green-light p-8 text-white text-center overflow-hidden">
                    <div class="absolute inset-0 opacity-10">
                        <svg class="w-full h-full" viewBox="0 0 800 400" fill="none">
                            <circle cx="100" cy="80" r="120" fill="currentColor"/>
                            <circle cx="700" cy="300" r="150" fill="currentColor"/>
                            <circle cx="400" cy="350" r="80" fill="currentColor"/>
                        </svg>
                    </div>

                    <div class="relative">
                        <div class="text-5xl mb-4">🇨🇲</div>
                        <h1 class="text-3xl font-bold mb-2">
                            <span x-text="$store.lang.t(
                                'Welcome to the ' + country + ' Yard, ' + username + '! 🎉',
                                'Bienvenue au Yard du ' + country + ', ' + username + ' ! 🎉'
                            )"></span>
                        </h1>
                        <p class="text-white/80 text-lg" x-text="$store.lang.t(
                            'You\\'re now part of the Cameroon Community family.',
                            'Vous faites maintenant partie de la famille Cameroon Community.'
                        )"></p>
                    </div>
                </div>

                <div class="p-8 space-y-6">
                    {{-- Founding Member Badge --}}
                    @if(auth()->user()->is_founding_member)
                    <div class="bg-cm-yellow/10 border border-cm-yellow/30 rounded-xl p-5 flex items-start gap-4">
                        <div class="text-4xl shrink-0">🏅</div>
                        <div>
                            <h3 class="font-bold text-slate-900" x-text="$store.lang.t('Founding Member!', 'Membre Fondateur !')"></h3>
                            <p class="text-sm text-slate-600 mt-1" x-text="$store.lang.t(
                                'You\\'re one of our first 1,000 members! You\\'ve earned the exclusive Founding Member badge.',
                                'Vous êtes l\\'un de nos 1 000 premiers membres ! Vous avez obtenu le badge exclusif de Membre Fondateur.'
                            )"></p>
                        </div>
                    </div>
                    @endif

                    {{-- Country stats --}}
                    <div class="bg-slate-50 rounded-xl p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-full bg-cm-green/10 flex items-center justify-center text-lg">👥</div>
                            <div>
                                <p class="font-semibold text-slate-900" x-text="memberCount + ' ' + $store.lang.t('Cameroonians in', 'Camerounais au') + ' ' + country"></p>
                                <p class="text-xs text-slate-500" x-text="$store.lang.t('and growing every day!', 'et ça grandit chaque jour !')"></p>
                            </div>
                        </div>
                    </div>

                    {{-- Regional Room prompt --}}
                    <template x-if="regionPrompt === 'ask_to_join'">
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-5">
                            <div class="flex items-start gap-4">
                                <div class="text-3xl shrink-0">📍</div>
                                <div class="flex-1">
                                    <p class="font-semibold text-slate-900" x-text="regionMemberCount + ' ' + $store.lang.t('Cameroonians are already in the', 'Camerounais sont déjà dans le') + ' ' + region + ' Room'"></p>
                                    <p class="text-sm text-slate-600 mt-1" x-text="$store.lang.t('Want to join your regional room?', 'Voulez-vous rejoindre votre salle régionale ?')"></p>
                                    <div class="flex gap-3 mt-4">
                                        <button @click="joinRegionRoom()" :disabled="joining"
                                                class="rounded-lg bg-cm-green px-5 py-2 text-sm font-bold text-white transition-colors hover:bg-cm-green-light disabled:opacity-50">
                                            <span x-show="!joining" x-text="$store.lang.t('Join Now', 'Rejoindre')"></span>
                                            <span x-show="joining" class="flex items-center gap-2">
                                                <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" class="opacity-25"/><path fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 100 8v4a8 8 0 01-8-8z" class="opacity-75"/></svg>
                                                <span x-text="$store.lang.t('Joining...', 'Rejoindre...')"></span>
                                            </span>
                                        </button>
                                        <button @click="regionPrompt = 'dismissed'"
                                                class="rounded-lg border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
                                                x-text="$store.lang.t('Maybe Later', 'Peut-être Plus Tard')">
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template x-if="regionPrompt === 'first_in_region'">
                        <div class="bg-cm-green/5 border border-cm-green/20 rounded-xl p-5">
                            <div class="flex items-start gap-4">
                                <div class="text-3xl shrink-0">🌟</div>
                                <div>
                                    <p class="font-semibold text-slate-900" x-text="$store.lang.t(
                                        'You\'re the first Cameroonian we\'ve found in ' + region + '!',
                                        'Vous êtes le premier Camerounais que nous trouvons à ' + region + ' !'
                                    )"></p>
                                    <p class="text-sm text-slate-600 mt-1" x-text="$store.lang.t(
                                        'Your regional room is ready — invite others to join you.',
                                        'Votre salle régionale est prête — invitez d\'autres à vous rejoindre.'
                                    )"></p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template x-if="regionPrompt === 'joined'">
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 flex items-center gap-3">
                            <span class="text-2xl">✅</span>
                            <p class="font-semibold text-blue-800" x-text="$store.lang.t('You\'ve joined the ' + region + ' Room!', 'Vous avez rejoint la salle de ' + region + ' !')"></p>
                        </div>
                    </template>

                    <div class="flex justify-end">
                        <button @click="slide = 2" class="rounded-xl bg-cm-green px-6 py-3 text-sm font-bold text-white transition-colors hover:bg-cm-green-light flex items-center gap-2">
                            <span x-text="$store.lang.t('Next', 'Suivant')"></span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- SLIDE 2: Quick Tour — The Yard --}}
            <div x-show="slide === 2" x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0"
                 class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">

                <div class="p-8 text-center">
                    <div class="w-20 h-20 mx-auto rounded-2xl bg-cm-green/10 flex items-center justify-center text-4xl mb-4">💬</div>
                    <h2 class="text-2xl font-bold text-slate-900 mb-2" x-text="$store.lang.t('The Yard', 'Le Yard')"></h2>
                    <p class="text-slate-600 max-w-md mx-auto" x-text="$store.lang.t(
                        'Your digital gathering space. Chat with Cameroonians in your country, city, or private groups. Share moments, get advice, stay connected.',
                        'Votre espace de rencontre numérique. Discutez avec des Camerounais dans votre pays, votre ville, ou des groupes privés.'
                    )"></p>
                </div>

                {{-- Mockup preview --}}
                <div class="px-8 pb-4">
                    <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                        <div class="space-y-3">
                            <div class="flex items-center gap-3 p-3 bg-white rounded-lg border border-slate-100">
                                <div class="text-2xl">🇬🇧</div>
                                <div class="flex-1">
                                    <p class="font-semibold text-sm text-slate-900" x-text="country + ' National Room'"></p>
                                    <p class="text-xs text-slate-500" x-text="$store.lang.t('All Cameroonians in your country', 'Tous les Camerounais de votre pays')"></p>
                                </div>
                                <span class="w-2 h-2 rounded-full bg-cm-green animate-pulse"></span>
                            </div>
                            <div class="flex items-center gap-3 p-3 bg-white rounded-lg border border-slate-100">
                                <div class="text-2xl">📍</div>
                                <div class="flex-1">
                                    <p class="font-semibold text-sm text-slate-900" x-text="city + ' Room'"></p>
                                    <p class="text-xs text-slate-500" x-text="$store.lang.t('Your city\\'s community', 'La communauté de votre ville')"></p>
                                </div>
                                <span class="w-2 h-2 rounded-full bg-cm-green animate-pulse"></span>
                            </div>
                            <div class="flex items-center gap-3 p-3 bg-white rounded-lg border border-slate-100">
                                <div class="text-2xl">🔒</div>
                                <div class="flex-1">
                                    <p class="font-semibold text-sm text-slate-900" x-text="$store.lang.t('Private Groups', 'Groupes Privés')"></p>
                                    <p class="text-xs text-slate-500" x-text="$store.lang.t('Create or join private groups', 'Créez ou rejoignez des groupes privés')"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-8 pb-8 flex justify-between">
                    <button @click="slide = 1" class="rounded-xl border border-slate-300 px-6 py-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50">
                        <span x-text="$store.lang.t('Back', 'Retour')"></span>
                    </button>
                    <button @click="slide = 3" class="rounded-xl bg-cm-green px-6 py-3 text-sm font-bold text-white transition-colors hover:bg-cm-green-light flex items-center gap-2">
                        <span x-text="$store.lang.t('Next', 'Suivant')"></span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>

            {{-- SLIDE 3: Quick Tour — Solidarity + Modules --}}
            <div x-show="slide === 3" x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0"
                 class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">

                <div class="p-8 text-center">
                    <div class="w-20 h-20 mx-auto rounded-2xl bg-cm-red/10 flex items-center justify-center text-4xl mb-4">🤲</div>
                    <h2 class="text-2xl font-bold text-slate-900 mb-2" x-text="$store.lang.t('Solidarity & More', 'Solidarité et Plus')"></h2>
                    <p class="text-slate-600 max-w-md mx-auto" x-text="$store.lang.t(
                        'Support fellow Cameroonians through community fundraising. Plus, discover marketplace, events, housing and more — coming soon!',
                        'Soutenez vos compatriotes camerounais par le financement communautaire. En plus, découvrez le marché, les événements, le logement et plus encore — bientôt disponible !'
                    )"></p>
                </div>

                {{-- Feature grid preview --}}
                <div class="px-8 pb-4">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="bg-slate-50 rounded-xl p-4 text-center border border-slate-200">
                            <div class="text-2xl mb-1">🤲</div>
                            <p class="text-xs font-semibold text-slate-700" x-text="$store.lang.t('Solidarity', 'Solidarité')"></p>
                            <span class="inline-block mt-1 text-[10px] font-bold text-cm-green bg-cm-green/10 rounded-full px-2 py-0.5" x-text="$store.lang.t('Live', 'Actif')"></span>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4 text-center border border-slate-200">
                            <div class="text-2xl mb-1">🛒</div>
                            <p class="text-xs font-semibold text-slate-700" x-text="$store.lang.t('Marketplace', 'Marché')"></p>
                            <span class="inline-block mt-1 text-[10px] font-bold text-cm-yellow bg-cm-yellow/10 rounded-full px-2 py-0.5" x-text="$store.lang.t('Coming Soon', 'Bientôt')"></span>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4 text-center border border-slate-200">
                            <div class="text-2xl mb-1">📦</div>
                            <p class="text-xs font-semibold text-slate-700">EasyGoParcel</p>
                            <span class="inline-block mt-1 text-[10px] font-bold text-cm-yellow bg-cm-yellow/10 rounded-full px-2 py-0.5" x-text="$store.lang.t('Coming Soon', 'Bientôt')"></span>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4 text-center border border-slate-200">
                            <div class="text-2xl mb-1">🚗</div>
                            <p class="text-xs font-semibold text-slate-700">RoadFam</p>
                            <span class="inline-block mt-1 text-[10px] font-bold text-cm-yellow bg-cm-yellow/10 rounded-full px-2 py-0.5" x-text="$store.lang.t('Coming Soon', 'Bientôt')"></span>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4 text-center border border-slate-200">
                            <div class="text-2xl mb-1">🎉</div>
                            <p class="text-xs font-semibold text-slate-700">CamEvents</p>
                            <span class="inline-block mt-1 text-[10px] font-bold text-cm-yellow bg-cm-yellow/10 rounded-full px-2 py-0.5" x-text="$store.lang.t('Coming Soon', 'Bientôt')"></span>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4 text-center border border-slate-200">
                            <div class="text-2xl mb-1">🏠</div>
                            <p class="text-xs font-semibold text-slate-700">KamerNest</p>
                            <span class="inline-block mt-1 text-[10px] font-bold text-cm-yellow bg-cm-yellow/10 rounded-full px-2 py-0.5" x-text="$store.lang.t('Coming Soon', 'Bientôt')"></span>
                        </div>
                    </div>
                </div>

                <div class="px-8 pb-8 flex justify-between">
                    <button @click="slide = 2" class="rounded-xl border border-slate-300 px-6 py-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50">
                        <span x-text="$store.lang.t('Back', 'Retour')"></span>
                    </button>
                    <button @click="slide = 4" class="rounded-xl bg-cm-green px-6 py-3 text-sm font-bold text-white transition-colors hover:bg-cm-green-light flex items-center gap-2">
                        <span x-text="$store.lang.t('Next', 'Suivant')"></span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>

            {{-- SLIDE 4: Kamer AI + Go --}}
            <div x-show="slide === 4" x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0"
                 class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">

                <div class="p-8 text-center">
                    <div class="w-20 h-20 mx-auto rounded-2xl bg-cm-yellow/20 flex items-center justify-center text-4xl mb-4">🤖</div>
                    <h2 class="text-2xl font-bold text-slate-900 mb-2" x-text="$store.lang.t('Meet Kamer AI', 'Découvrez Kamer AI')"></h2>
                    <p class="text-slate-600 max-w-md mx-auto" x-text="$store.lang.t(
                        'Your personal guide to everything Cameroon Community. Ask Kamer anything — from navigating the platform to understanding UK immigration rules.',
                        'Votre guide personnel pour tout ce qui concerne Cameroon Community. Demandez n\\'importe quoi à Kamer — de la navigation sur la plateforme aux règles d\\'immigration.'
                    )"></p>
                </div>

                {{-- Kamer AI chat preview --}}
                <div class="px-8 pb-4">
                    <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 space-y-3">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-cm-yellow flex items-center justify-center text-sm shrink-0">🤖</div>
                            <div class="bg-white rounded-xl rounded-tl-none p-3 border border-slate-200">
                                <p class="text-sm text-slate-700" x-text="$store.lang.t(
                                    'Hi ' + username + '! I\\'m Kamer, your guide on Cameroon Community. What would you like to explore first?',
                                    'Salut ' + username + ' ! Je suis Kamer, votre guide sur Cameroon Community. Que souhaitez-vous explorer en premier ?'
                                )"></p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 pl-11">
                            <span class="inline-block text-xs bg-cm-green/10 text-cm-green font-medium rounded-full px-3 py-1.5 cursor-pointer hover:bg-cm-green/20 transition-colors"
                                  x-text="$store.lang.t('Show me The Yard', 'Montre-moi le Yard')"></span>
                            <span class="inline-block text-xs bg-cm-green/10 text-cm-green font-medium rounded-full px-3 py-1.5 cursor-pointer hover:bg-cm-green/20 transition-colors"
                                  x-text="$store.lang.t('How does Solidarity work?', 'Comment fonctionne la Solidarité ?')"></span>
                            <span class="inline-block text-xs bg-cm-green/10 text-cm-green font-medium rounded-full px-3 py-1.5 cursor-pointer hover:bg-cm-green/20 transition-colors"
                                  x-text="$store.lang.t('Find Cameroonians near me', 'Trouver des Camerounais près de moi')"></span>
                        </div>
                    </div>
                </div>

                <div class="px-8 pb-8 flex justify-between">
                    <button @click="slide = 3" class="rounded-xl border border-slate-300 px-6 py-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50">
                        <span x-text="$store.lang.t('Back', 'Retour')"></span>
                    </button>
                    <a href="{{ route('yard') }}" class="rounded-xl bg-cm-green px-8 py-3 text-sm font-bold text-white transition-colors hover:bg-cm-green-light flex items-center gap-2">
                        <span x-text="$store.lang.t('Enter The Yard 🎉', 'Entrer dans le Yard 🎉')"></span>
                    </a>
                </div>
            </div>

            {{-- Skip link --}}
            <div class="text-center mt-4">
                <a href="{{ route('yard') }}" class="text-sm text-slate-400 hover:text-slate-600 transition-colors" x-text="$store.lang.t('Skip tour →', 'Passer la visite →')"></a>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function onboarding() {
            return {
                slide: 1,
                totalSlides: 4,
                username: @js(auth()->user()->name),
                country: @js(auth()->user()->current_country ?? 'your country'),
                region: @js(auth()->user()->current_region ?? 'your region'),
                memberCount: @js($memberCount ?? 0),
                regionPrompt: @js($regionPrompt ?? null),
                regionMemberCount: @js($regionMemberCount ?? 0),
                regionRoomId: @js($regionRoomId ?? null),
                joining: false,

                async joinRegionRoom() {
                    if (!this.regionRoomId) return;
                    this.joining = true;
                    try {
                        const res = await fetch('/yard/room/' + this.regionRoomId + '/join', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                        });
                        if (res.ok) {
                            this.regionPrompt = 'joined';
                        }
                    } catch (e) {
                        console.error('Failed to join region room:', e);
                    } finally {
                        this.joining = false;
                    }
                }
            };
        }

        @if(auth()->user()->is_founding_member)
        // Simple confetti effect for founding members
        document.addEventListener('DOMContentLoaded', () => {
            const canvas = document.getElementById('confetti-canvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;

            const colors = ['#243a5c', '#CE1126', '#FCD116', '#FFFFFF', '#FFD700'];
            const particles = [];

            for (let i = 0; i < 150; i++) {
                particles.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height - canvas.height,
                    w: Math.random() * 8 + 4,
                    h: Math.random() * 4 + 2,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    speed: Math.random() * 3 + 2,
                    angle: Math.random() * Math.PI * 2,
                    spin: (Math.random() - 0.5) * 0.2,
                    drift: (Math.random() - 0.5) * 1,
                });
            }

            let frame = 0;
            const maxFrames = 300;

            function animate() {
                if (frame >= maxFrames) {
                    canvas.remove();
                    return;
                }
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                particles.forEach(p => {
                    p.y += p.speed;
                    p.x += p.drift;
                    p.angle += p.spin;
                    ctx.save();
                    ctx.translate(p.x, p.y);
                    ctx.rotate(p.angle);
                    ctx.fillStyle = p.color;
                    ctx.globalAlpha = Math.max(0, 1 - frame / maxFrames);
                    ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
                    ctx.restore();
                });
                frame++;
                requestAnimationFrame(animate);
            }
            animate();
        });
        @endif
    </script>
    @endpush
</x-layouts.app>
