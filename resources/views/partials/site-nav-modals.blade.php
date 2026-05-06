{{--
    Site nav modals — Features, How It Works, Solidarity, Community.
    Each modal teleports to <body> to escape parent stacking contexts.
    Triggered via:
      • open-features-modal
      • open-how-it-works-modal
      • open-solidarity-modal
      • open-community-modal
--}}

{{-- ═══════════════════════════════════════════════════════════════
     FEATURES MODAL
     ═══════════════════════════════════════════════════════════════ --}}
<div x-data="{ open: false }"
     x-on:open-features-modal.window="open = true"
     x-on:keydown.escape.window="open = false">
    <template x-teleport="body">
        <div x-cloak>
            <div x-show="open" x-transition.opacity.duration.300ms @click="open = false"
                 class="fixed inset-0 z-[9998] bg-slate-900/70 backdrop-blur-md"></div>
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-8 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="fixed inset-0 z-[9999] flex items-center justify-center p-3 sm:p-4 pointer-events-none">
                <div @click.stop
                     class="pointer-events-auto relative w-full max-w-4xl max-h-[92vh] overflow-y-auto rounded-3xl bg-white shadow-2xl">
                    <div class="relative h-44 overflow-hidden rounded-t-3xl bg-gradient-to-br from-cm-green via-emerald-600 to-cm-green-dark">
                        <div class="absolute inset-0 opacity-20" style="background-image:radial-gradient(circle at 20% 30%, white 1px, transparent 1px), radial-gradient(circle at 70% 80%, white 1px, transparent 1px); background-size: 60px 60px;"></div>
                        <button @click="open = false" class="absolute top-4 right-4 z-10 h-9 w-9 rounded-full bg-white/20 backdrop-blur text-white hover:bg-white/30 transition-colors flex items-center justify-center">✕</button>
                        <div class="relative h-full flex flex-col items-center justify-center px-6 text-center text-white">
                            <span class="inline-flex items-center gap-2 rounded-full bg-white/20 px-3 py-1 text-[10px] font-bold uppercase tracking-wider"
                                  x-text="$store.lang.t('Features', 'Fonctionnalités')"></span>
                            <h2 class="mt-3 text-2xl sm:text-3xl font-extrabold"
                                x-text="$store.lang.t('Everything you need, in one place.', 'Tout ce qu\'il vous faut, en un seul endroit.')"></h2>
                        </div>
                    </div>
                    <div class="p-6 sm:p-8">
                        @php
                            $features = [
                                ['icon' => '💬', 'en' => 'The Yard', 'fr' => 'Le Yard', 'desc_en' => 'Real-time chat rooms grouped by country, region, and city.', 'desc_fr' => 'Salons de discussion en temps réel par pays, région et ville.', 'live' => true, 'color' => 'from-emerald-500 to-emerald-600'],
                                ['icon' => '🤝', 'en' => 'Solidarity', 'fr' => 'Solidarité', 'desc_en' => 'Community-powered fundraising for those who need it most.', 'desc_fr' => 'Collectes communautaires pour ceux qui en ont le plus besoin.', 'live' => true, 'color' => 'from-rose-500 to-pink-600'],
                                ['icon' => '✨', 'en' => 'Kamer AI', 'fr' => 'Kamer AI', 'desc_en' => 'Your personal guide — answers in English, French, or Pidgin.', 'desc_fr' => 'Votre guide personnel — réponses en anglais, français ou pidgin.', 'live' => true, 'color' => 'from-indigo-500 to-purple-600'],
                                ['icon' => '🛒', 'en' => 'Marketplace', 'fr' => 'Marché', 'desc_en' => 'Buy and sell within your trusted local community.', 'desc_fr' => 'Achetez et vendez au sein de votre communauté locale.', 'live' => false, 'color' => 'from-amber-500 to-orange-600'],
                                ['icon' => '📦', 'en' => 'EasyGoParcel', 'fr' => 'EasyGoParcel', 'desc_en' => 'Send parcels home with verified Cameroonian travellers.', 'desc_fr' => 'Envoyez des colis avec des voyageurs camerounais vérifiés.', 'live' => false, 'color' => 'from-sky-500 to-cyan-600'],
                                ['icon' => '🚗', 'en' => 'RoadFam', 'fr' => 'RoadFam', 'desc_en' => 'Carpool to events, family visits, and weekend getaways.', 'desc_fr' => 'Covoiturage pour événements, visites familiales et escapades.', 'live' => false, 'color' => 'from-fuchsia-500 to-pink-600'],
                            ];
                        @endphp
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($features as $f)
                                <div class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                                    <div class="absolute -top-8 -right-8 h-24 w-24 rounded-full bg-gradient-to-br {{ $f['color'] }} opacity-10 group-hover:opacity-20 transition-opacity"></div>
                                    <div class="relative">
                                        <div class="text-3xl mb-2">{{ $f['icon'] }}</div>
                                        <div class="flex items-center gap-2">
                                            <h3 class="font-bold text-slate-900"
                                                x-text="$store.lang.t(@js($f['en']), @js($f['fr']))"></h3>
                                            @if($f['live'])
                                                <span class="rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 text-[9px] font-bold uppercase">Live</span>
                                            @else
                                                <span class="rounded-full bg-amber-100 text-amber-700 px-2 py-0.5 text-[9px] font-bold uppercase" x-text="$store.lang.t('Soon', 'Bientôt')"></span>
                                            @endif
                                        </div>
                                        <p class="mt-2 text-xs text-slate-600 leading-relaxed"
                                           x-text="$store.lang.t(@js($f['desc_en']), @js($f['desc_fr']))"></p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-6 flex justify-center">
                            <a href="{{ route('register') }}" class="rounded-full bg-cm-green px-6 py-3 text-sm font-bold text-white hover:bg-cm-green-light transition-colors"
                               x-text="$store.lang.t('Join the community →', 'Rejoindre la communauté →')"></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     HOW IT WORKS MODAL
     ═══════════════════════════════════════════════════════════════ --}}
<div x-data="{ open: false }"
     x-on:open-how-it-works-modal.window="open = true"
     x-on:keydown.escape.window="open = false">
    <template x-teleport="body">
        <div x-cloak>
            <div x-show="open" x-transition.opacity.duration.300ms @click="open = false"
                 class="fixed inset-0 z-[9998] bg-slate-900/70 backdrop-blur-md"></div>
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-8 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="fixed inset-0 z-[9999] flex items-center justify-center p-3 sm:p-4 pointer-events-none">
                <div @click.stop
                     class="pointer-events-auto relative w-full max-w-2xl max-h-[92vh] overflow-y-auto rounded-3xl bg-white shadow-2xl">
                    <div class="relative h-40 overflow-hidden rounded-t-3xl bg-gradient-to-br from-indigo-600 via-violet-600 to-purple-700">
                        <button @click="open = false" class="absolute top-4 right-4 z-10 h-9 w-9 rounded-full bg-white/20 backdrop-blur text-white hover:bg-white/30 transition-colors flex items-center justify-center">✕</button>
                        <div class="relative h-full flex flex-col items-center justify-center px-6 text-center text-white">
                            <span class="inline-flex items-center gap-2 rounded-full bg-white/20 px-3 py-1 text-[10px] font-bold uppercase tracking-wider"
                                  x-text="$store.lang.t('How It Works', 'Comment Ça Marche')"></span>
                            <h2 class="mt-3 text-2xl sm:text-3xl font-extrabold"
                                x-text="$store.lang.t('Four steps. One community.', 'Quatre étapes. Une communauté.')"></h2>
                        </div>
                    </div>
                    <div class="p-6 sm:p-8">
                        @php
                            $steps = [
                                ['icon' => '👤', 'en' => 'Create your free account', 'fr' => 'Créez votre compte gratuit', 'desc_en' => 'Just your name, email and a password. No phone numbers, no ID upload.', 'desc_fr' => 'Juste votre nom, e-mail et un mot de passe. Pas de numéro, pas de pièce d\'identité.'],
                                ['icon' => '📍', 'en' => 'Tell us where you are', 'fr' => 'Dites-nous où vous êtes', 'desc_en' => 'GPS or city pick — we use it to suggest the right rooms for you.', 'desc_fr' => 'GPS ou choix de ville — on l\'utilise pour vous suggérer les bons salons.'],
                                ['icon' => '🏠', 'en' => 'Step into The Yard', 'fr' => 'Entrez dans Le Yard', 'desc_en' => 'Join your country, region, and city rooms instantly. Say "ashia" 👋', 'desc_fr' => 'Rejoignez vos salons pays, région et ville instantanément. Dites « ashia » 👋'],
                                ['icon' => '🚀', 'en' => 'Build, share, support', 'fr' => 'Construisez, partagez, soutenez', 'desc_en' => 'Help a neighbour, raise funds, find friends — that\'s the Cameroonian way.', 'desc_fr' => 'Aidez un voisin, levez des fonds, trouvez des amis — c\'est la voie camerounaise.'],
                            ];
                        @endphp
                        <ol class="relative space-y-6 before:absolute before:left-[19px] before:top-2 before:bottom-2 before:w-[2px] before:bg-gradient-to-b before:from-indigo-200 before:via-violet-200 before:to-transparent">
                            @foreach($steps as $i => $s)
                                <li class="relative flex items-start gap-4">
                                    <div class="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-white text-lg shadow-lg ring-4 ring-white">
                                        {{ $s['icon'] }}
                                    </div>
                                    <div class="flex-1 pt-1">
                                        <span class="text-[10px] font-bold text-violet-500">STEP {{ $i + 1 }}</span>
                                        <h3 class="mt-0.5 font-bold text-slate-900"
                                            x-text="$store.lang.t(@js($s['en']), @js($s['fr']))"></h3>
                                        <p class="mt-1 text-sm text-slate-600 leading-relaxed"
                                           x-text="$store.lang.t(@js($s['desc_en']), @js($s['desc_fr']))"></p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                        <div class="mt-8 flex justify-center">
                            <a href="{{ route('register') }}" class="rounded-full bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-3 text-sm font-bold text-white hover:brightness-110 transition-all"
                               x-text="$store.lang.t('Start now — it\'s free', 'Commencer — c\'est gratuit')"></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     SOLIDARITY MODAL
     ═══════════════════════════════════════════════════════════════ --}}
<div x-data="{ open: false }"
     x-on:open-solidarity-modal.window="open = true"
     x-on:keydown.escape.window="open = false">
    <template x-teleport="body">
        <div x-cloak>
            <div x-show="open" x-transition.opacity.duration.300ms @click="open = false"
                 class="fixed inset-0 z-[9998] bg-slate-900/70 backdrop-blur-md"></div>
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-8 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="fixed inset-0 z-[9999] flex items-center justify-center p-3 sm:p-4 pointer-events-none">
                <div @click.stop
                     class="pointer-events-auto relative w-full max-w-2xl max-h-[92vh] overflow-y-auto rounded-3xl bg-white shadow-2xl">
                    <div class="relative h-44 overflow-hidden rounded-t-3xl bg-gradient-to-br from-rose-500 via-pink-600 to-rose-700">
                        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 30% 50%, white 2px, transparent 2px); background-size: 80px 80px;"></div>
                        <div class="absolute top-1/2 -right-8 -translate-y-1/2 text-[180px] opacity-15">🤝</div>
                        <button @click="open = false" class="absolute top-4 right-4 z-10 h-9 w-9 rounded-full bg-white/20 backdrop-blur text-white hover:bg-white/30 transition-colors flex items-center justify-center">✕</button>
                        <div class="relative h-full flex flex-col items-center justify-center px-6 text-center text-white">
                            <span class="inline-flex items-center gap-2 rounded-full bg-white/20 px-3 py-1 text-[10px] font-bold uppercase tracking-wider"
                                  x-text="$store.lang.t('Solidarity', 'Solidarité')"></span>
                            <h2 class="mt-3 text-2xl sm:text-3xl font-extrabold"
                                x-text="$store.lang.t('When one of us hurts, all of us help.', 'Quand l\'un de nous souffre, nous aidons tous.')"></h2>
                        </div>
                    </div>
                    <div class="p-6 sm:p-8">
                        <p class="text-sm text-slate-600 leading-relaxed text-center max-w-md mx-auto"
                           x-text="$store.lang.t('Solidarity turns the Cameroonian tradition of njangi into a transparent, modern way to support each other — funerals, medical bills, school fees, business launches.', 'Solidarité transforme la tradition camerounaise du njangi en une manière transparente et moderne de se soutenir — funérailles, soins médicaux, frais de scolarité, lancements d\'entreprise.')"></p>
                        <div class="mt-6 grid grid-cols-3 gap-3">
                            <div class="rounded-2xl border border-rose-100 bg-rose-50 p-4 text-center">
                                <div class="text-2xl font-extrabold text-rose-600">£12k+</div>
                                <div class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-rose-700"
                                     x-text="$store.lang.t('Raised', 'Levés')"></div>
                            </div>
                            <div class="rounded-2xl border border-rose-100 bg-rose-50 p-4 text-center">
                                <div class="text-2xl font-extrabold text-rose-600">37</div>
                                <div class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-rose-700"
                                     x-text="$store.lang.t('Campaigns', 'Campagnes')"></div>
                            </div>
                            <div class="rounded-2xl border border-rose-100 bg-rose-50 p-4 text-center">
                                <div class="text-2xl font-extrabold text-rose-600">100%</div>
                                <div class="mt-1 text-[10px] font-semibold uppercase tracking-wider text-rose-700"
                                     x-text="$store.lang.t('Transparent', 'Transparent')"></div>
                            </div>
                        </div>
                        <div class="mt-6 rounded-2xl bg-slate-50 p-5">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3"
                                x-text="$store.lang.t('Three simple steps', 'Trois étapes simples')"></h3>
                            <ul class="space-y-2 text-sm text-slate-700">
                                <li class="flex items-start gap-2"><span class="text-rose-500 font-bold">1.</span> <span x-text="$store.lang.t('Start a campaign or browse open ones in your community.', 'Lancez une campagne ou parcourez celles ouvertes dans votre communauté.')"></span></li>
                                <li class="flex items-start gap-2"><span class="text-rose-500 font-bold">2.</span> <span x-text="$store.lang.t('Contribute any amount — every penny is tracked publicly.', 'Contribuez le montant que vous voulez — chaque centime est suivi publiquement.')"></span></li>
                                <li class="flex items-start gap-2"><span class="text-rose-500 font-bold">3.</span> <span x-text="$store.lang.t('Funds reach the family directly. We take 5% to keep the lights on.', 'Les fonds vont directement à la famille. Nous prenons 5% pour faire tourner la plateforme.')"></span></li>
                            </ul>
                        </div>
                        <div class="mt-6 flex justify-center">
                            <a href="{{ route('register') }}" class="rounded-full bg-gradient-to-r from-rose-500 to-pink-600 px-6 py-3 text-sm font-bold text-white hover:brightness-110 transition-all"
                               x-text="$store.lang.t('Be part of it', 'En faire partie')"></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     COMMUNITY MODAL
     ═══════════════════════════════════════════════════════════════ --}}
<div x-data="{ open: false }"
     x-on:open-community-modal.window="open = true"
     x-on:keydown.escape.window="open = false">
    <template x-teleport="body">
        <div x-cloak>
            <div x-show="open" x-transition.opacity.duration.300ms @click="open = false"
                 class="fixed inset-0 z-[9998] bg-slate-900/70 backdrop-blur-md"></div>
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-8 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="fixed inset-0 z-[9999] flex items-center justify-center p-3 sm:p-4 pointer-events-none">
                <div @click.stop
                     class="pointer-events-auto relative w-full max-w-3xl max-h-[92vh] overflow-y-auto rounded-3xl bg-white shadow-2xl">
                    <div class="relative h-48 overflow-hidden rounded-t-3xl"
                         style="background: linear-gradient(135deg, #007a3d 0%, #009639 35%, #ce1126 70%, #fcd116 100%);">
                        <div class="absolute inset-0 opacity-15" style="background-image: radial-gradient(circle, white 1.5px, transparent 1.5px); background-size: 30px 30px;"></div>
                        <button @click="open = false" class="absolute top-4 right-4 z-10 h-9 w-9 rounded-full bg-white/20 backdrop-blur text-white hover:bg-white/30 transition-colors flex items-center justify-center">✕</button>
                        <div class="relative h-full flex flex-col items-center justify-center px-6 text-center text-white">
                            <span class="inline-flex items-center gap-2 rounded-full bg-white/25 px-3 py-1 text-[10px] font-bold uppercase tracking-wider"
                                  x-text="$store.lang.t('Community', 'Communauté')"></span>
                            <h2 class="mt-3 text-2xl sm:text-3xl font-extrabold drop-shadow"
                                x-text="$store.lang.t('From Bamenda to Birmingham. From Douala to Dallas.', 'De Bamenda à Birmingham. De Douala à Dallas.')"></h2>
                        </div>
                    </div>
                    <div class="p-6 sm:p-8">
                        <p class="text-sm text-slate-600 leading-relaxed text-center max-w-lg mx-auto"
                           x-text="$store.lang.t('Cameroon Network is for every Cameroonian, every kontri pikin — back home, in the diaspora, English-speaking, French-speaking, Pidgin-speaking, all the tribes, all the regions.', 'Cameroon Network est pour chaque Camerounais, chaque kontri pikin — au pays, dans la diaspora, anglophone, francophone, pidgin, toutes les tribus, toutes les régions.')"></p>
                        <div class="mt-6 grid sm:grid-cols-2 gap-4">
                            <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-5">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-2xl">🇨🇲</span>
                                    <h3 class="font-bold text-slate-900" x-text="$store.lang.t('Back home', 'Au pays')"></h3>
                                </div>
                                <p class="text-xs text-slate-600 leading-relaxed">Yaoundé · Douala · Bamenda · Buea · Limbe · Bafoussam · Garoua · Maroua · Kumba · Bertoua</p>
                            </div>
                            <div class="rounded-2xl bg-amber-50 border border-amber-100 p-5">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-2xl">🌍</span>
                                    <h3 class="font-bold text-slate-900" x-text="$store.lang.t('In the diaspora', 'Dans la diaspora')"></h3>
                                </div>
                                <p class="text-xs text-slate-600 leading-relaxed">London · Paris · Brussels · Berlin · Toronto · New York · Dubai · Johannesburg · Sydney · Lagos</p>
                            </div>
                        </div>
                        <div class="mt-6 grid grid-cols-3 gap-3 text-center">
                            <div>
                                <div class="text-2xl mb-1">🇬🇧</div>
                                <p class="text-xs font-semibold text-slate-700" x-text="$store.lang.t('English', 'Anglais')"></p>
                            </div>
                            <div>
                                <div class="text-2xl mb-1">🇫🇷</div>
                                <p class="text-xs font-semibold text-slate-700" x-text="$store.lang.t('Français', 'Français')"></p>
                            </div>
                            <div>
                                <div class="text-2xl mb-1">💛</div>
                                <p class="text-xs font-semibold text-slate-700" x-text="$store.lang.t('Pidgin too', 'Pidgin aussi')"></p>
                            </div>
                        </div>
                        <div class="mt-6 rounded-2xl bg-slate-900 text-white p-5 text-center">
                            <p class="text-sm italic"
                               x-text="$store.lang.t('“Where you stand, Cameroon stands with you.”', '« Où que vous soyez, le Cameroun est avec vous. »')"></p>
                        </div>
                        <div class="mt-6 flex justify-center">
                            <a href="{{ route('register') }}" class="rounded-full bg-cm-yellow px-6 py-3 text-sm font-bold text-cm-green-dark hover:brightness-105 transition-all"
                               x-text="$store.lang.t('Find your kontri pipo', 'Trouver vos kontri pipo')"></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
