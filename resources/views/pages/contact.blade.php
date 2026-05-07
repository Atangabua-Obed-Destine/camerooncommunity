<x-layouts.guest>
    <x-slot name="title">{{ app()->getLocale() === 'fr' ? 'Nous contacter' : 'Contact Us' }} — Cameroon Network</x-slot>

    {{-- Sticky navbar (white state, like the legal pages) --}}
    @php($forceScrolled = true)
    @include('partials.site-nav')

    <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50 pt-24 sm:pt-28">
        {{-- Hero --}}
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-400/20 via-transparent to-transparent pointer-events-none"></div>
            <div class="relative mx-auto max-w-3xl px-6 pt-10 pb-8 text-center">
                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ring-1 bg-emerald-50 text-emerald-700 ring-emerald-200"
                      x-text="$store.lang.t('Contact', 'Contact')"></span>
                <h1 class="mt-5 text-4xl sm:text-5xl font-extrabold tracking-tight text-slate-900"
                    x-text="$store.lang.t('We\'d love to hear from you.', 'Nous serions ravis de vous entendre.')"></h1>
                <p class="mt-4 text-base sm:text-lg text-slate-600 leading-relaxed"
                   x-text="$store.lang.t(
                       'Questions, partnerships, press, or just a story to share — pick the route that suits you.',
                       'Questions, partenariats, presse, ou simplement une histoire à partager — choisissez le moyen qui vous convient.'
                   )"></p>
            </div>
        </section>

        {{-- Contact channels --}}
        <section class="mx-auto max-w-5xl px-6 pb-10">
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {{-- General --}}
                <a href="mailto:hello@cameroonnetwork.org"
                   class="group rounded-2xl border border-slate-200 bg-white p-6 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 text-2xl ring-1 ring-emerald-100">
                        💬
                    </div>
                    <h3 class="mt-4 font-bold text-slate-900"
                        x-text="$store.lang.t('General questions', 'Questions générales')"></h3>
                    <p class="mt-1 text-xs text-slate-500"
                       x-text="$store.lang.t('Anything else not listed here.', 'Tout ce qui n\'est pas listé ici.')"></p>
                    <p class="mt-3 text-sm font-semibold text-emerald-600 group-hover:text-emerald-700">hello@cameroonnetwork.org</p>
                </a>

                {{-- Privacy --}}
                <a href="mailto:privacy@cameroonnetwork.org"
                   class="group rounded-2xl border border-slate-200 bg-white p-6 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 text-2xl ring-1 ring-indigo-100">
                        🔐
                    </div>
                    <h3 class="mt-4 font-bold text-slate-900"
                        x-text="$store.lang.t('Privacy & data', 'Confidentialité & données')"></h3>
                    <p class="mt-1 text-xs text-slate-500"
                       x-text="$store.lang.t('Export, correction, or deletion requests.', 'Export, correction ou suppression de données.')"></p>
                    <p class="mt-3 text-sm font-semibold text-indigo-600 group-hover:text-indigo-700">privacy@cameroonnetwork.org</p>
                </a>

                {{-- Security --}}
                <a href="mailto:security@cameroonnetwork.org"
                   class="group rounded-2xl border border-slate-200 bg-white p-6 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-rose-50 text-rose-600 text-2xl ring-1 ring-rose-100">
                        🛡️
                    </div>
                    <h3 class="mt-4 font-bold text-slate-900"
                        x-text="$store.lang.t('Security', 'Sécurité')"></h3>
                    <p class="mt-1 text-xs text-slate-500"
                       x-text="$store.lang.t('Report a vulnerability or unauthorised access.', 'Signaler une vulnérabilité ou un accès non autorisé.')"></p>
                    <p class="mt-3 text-sm font-semibold text-rose-600 group-hover:text-rose-700">security@cameroonnetwork.org</p>
                </a>

                {{-- Partnerships --}}
                <a href="mailto:partnerships@cameroonnetwork.org"
                   class="group rounded-2xl border border-slate-200 bg-white p-6 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-amber-50 text-amber-600 text-2xl ring-1 ring-amber-100">
                        🤝
                    </div>
                    <h3 class="mt-4 font-bold text-slate-900"
                        x-text="$store.lang.t('Partnerships', 'Partenariats')"></h3>
                    <p class="mt-1 text-xs text-slate-500"
                       x-text="$store.lang.t('Collaborate with the community or sponsor a campaign.', 'Collaborez avec la communauté ou parrainez une campagne.')"></p>
                    <p class="mt-3 text-sm font-semibold text-amber-600 group-hover:text-amber-700">partnerships@cameroonnetwork.org</p>
                </a>

                {{-- Press --}}
                <a href="mailto:press@cameroonnetwork.org"
                   class="group rounded-2xl border border-slate-200 bg-white p-6 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-sky-50 text-sky-600 text-2xl ring-1 ring-sky-100">
                        📰
                    </div>
                    <h3 class="mt-4 font-bold text-slate-900"
                        x-text="$store.lang.t('Press & media', 'Presse & médias')"></h3>
                    <p class="mt-1 text-xs text-slate-500"
                       x-text="$store.lang.t('Interviews, stories, brand assets.', 'Interviews, reportages, ressources de marque.')"></p>
                    <p class="mt-3 text-sm font-semibold text-sky-600 group-hover:text-sky-700">press@cameroonnetwork.org</p>
                </a>

                {{-- Support --}}
                <a href="mailto:support@cameroonnetwork.org"
                   class="group rounded-2xl border border-slate-200 bg-white p-6 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-violet-50 text-violet-600 text-2xl ring-1 ring-violet-100">
                        🛠️
                    </div>
                    <h3 class="mt-4 font-bold text-slate-900"
                        x-text="$store.lang.t('Account help', 'Aide compte')"></h3>
                    <p class="mt-1 text-xs text-slate-500"
                       x-text="$store.lang.t('Trouble logging in, lost password, missing data.', 'Problème de connexion, mot de passe perdu, données manquantes.')"></p>
                    <p class="mt-3 text-sm font-semibold text-violet-600 group-hover:text-violet-700">support@cameroonnetwork.org</p>
                </a>
            </div>
        </section>

        {{-- Quick message form (uses mailto so no backend yet) --}}
        <section class="mx-auto max-w-3xl px-6 pb-16">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm"
                 x-data="{
                    name: '',
                    email: '',
                    subject: '',
                    body: '',
                    get mailto() {
                        const lines = [
                            this.body || '',
                            '',
                            '— ' + (this.name || 'Anonymous'),
                            this.email ? '(' + this.email + ')' : '',
                        ].join('\n');
                        const subject = this.subject || ($store.lang.isEn ? 'Hello from the website' : 'Bonjour depuis le site');
                        return 'mailto:hello@cameroonnetwork.org'
                            + '?subject=' + encodeURIComponent(subject)
                            + '&body=' + encodeURIComponent(lines);
                    }
                 }">
                <h2 class="text-xl font-bold text-slate-900"
                    x-text="$store.lang.t('Send a quick message', 'Envoyer un message rapide')"></h2>
                <p class="mt-1 text-sm text-slate-500"
                   x-text="$store.lang.t(
                       'This opens your email app pre-filled — no account needed.',
                       'Ceci ouvre votre application e-mail pré-remplie — aucun compte requis.'
                   )"></p>

                <div class="mt-5 grid sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="text-xs font-semibold text-slate-600"
                              x-text="$store.lang.t('Your name', 'Votre nom')"></span>
                        <input type="text" x-model="name" autocomplete="name"
                               class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cm-green focus:ring-1 focus:ring-cm-green"
                               placeholder="Ndongo">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-slate-600"
                              x-text="$store.lang.t('Your email', 'Votre e-mail')"></span>
                        <input type="email" x-model="email" autocomplete="email"
                               class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cm-green focus:ring-1 focus:ring-cm-green"
                               placeholder="you@example.com">
                    </label>
                </div>

                <label class="mt-4 block">
                    <span class="text-xs font-semibold text-slate-600"
                          x-text="$store.lang.t('Subject', 'Sujet')"></span>
                    <input type="text" x-model="subject"
                           class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cm-green focus:ring-1 focus:ring-cm-green"
                           :placeholder="$store.lang.t('A short title for your message', 'Un titre court pour votre message')">
                </label>

                <label class="mt-4 block">
                    <span class="text-xs font-semibold text-slate-600"
                          x-text="$store.lang.t('Message', 'Message')"></span>
                    <textarea x-model="body" rows="5"
                              class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-cm-green focus:ring-1 focus:ring-cm-green"
                              :placeholder="$store.lang.t('Tell us what\'s on your mind…', 'Dites-nous ce que vous avez en tête…')"></textarea>
                </label>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <a :href="mailto"
                       class="inline-flex items-center gap-2 rounded-full bg-cm-green px-5 py-2.5 text-sm font-bold text-white hover:bg-cm-green-light transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <span x-text="$store.lang.t('Open in email app', 'Ouvrir dans l\'app e-mail')"></span>
                    </a>
                    <a href="mailto:hello@cameroonnetwork.org"
                       class="text-sm font-semibold text-slate-500 hover:text-slate-900 transition-colors"
                       x-text="$store.lang.t('Or just write to hello@cameroonnetwork.org', 'Ou écrivez simplement à hello@cameroonnetwork.org')"></a>
                </div>
            </div>

            {{-- Sister page links --}}
            <div class="mt-8 flex flex-wrap items-center justify-center gap-4 text-sm">
                <a href="{{ route('legal.privacy') }}" class="text-slate-500 hover:text-slate-900 transition-colors"
                   x-text="$store.lang.t('Privacy Policy', 'Politique de Confidentialité')"></a>
                <span class="text-slate-300">·</span>
                <a href="{{ route('legal.terms') }}" class="text-slate-500 hover:text-slate-900 transition-colors"
                   x-text="$store.lang.t('Terms of Service', 'Conditions d\'Utilisation')"></a>
                <span class="text-slate-300">·</span>
                <a href="{{ route('home') }}" class="text-slate-500 hover:text-slate-900 transition-colors"
                   x-text="$store.lang.t('Home', 'Accueil')"></a>
            </div>
        </section>
    </div>
</x-layouts.guest>
