<x-layouts.guest>
    <x-slot name="title">{{ app()->getLocale() === 'fr' ? 'À Propos' : 'About' }} — Cameroon Network</x-slot>

    @php($forceScrolled = true)
    @include('partials.site-nav')

    <div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50 pt-24 sm:pt-28">
        {{-- Hero --}}
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-400/20 via-transparent to-transparent pointer-events-none"></div>
            <div class="relative mx-auto max-w-3xl px-6 pt-10 pb-10 text-center">
                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ring-1 bg-emerald-50 text-emerald-700 ring-emerald-200"
                      x-text="$store.lang.t('About', 'À Propos')"></span>
                <h1 class="mt-5 text-4xl sm:text-5xl font-extrabold tracking-tight text-slate-900"
                    x-text="$store.lang.t('A network built by Cameroonians, for Cameroonians.', 'Un réseau bâti par des Camerounais, pour des Camerounais.')"></h1>
                <p class="mt-4 text-base sm:text-lg text-slate-600 leading-relaxed"
                   x-text="$store.lang.t(
                       'Cameroon Network is a home online — a place to find your people, share what matters, and lift each other up, wherever life has taken you.',
                       'Cameroon Network est une maison en ligne — un endroit pour retrouver les vôtres, partager ce qui compte et vous entraider, où que la vie vous ait menés.'
                   )"></p>
            </div>
        </section>

        {{-- Mission --}}
        <section class="mx-auto max-w-5xl px-6 py-8">
            <div class="grid md:grid-cols-3 gap-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-6">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 text-2xl ring-1 ring-emerald-100">🤝</div>
                    <h3 class="mt-4 font-bold text-slate-900" x-text="$store.lang.t('Connect', 'Connecter')"></h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed"
                       x-text="$store.lang.t(
                           'Find friends, family and neighbours from your village, region or city — across continents.',
                           'Retrouvez amis, famille et voisins de votre village, région ou ville — à travers les continents.'
                       )"></p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-amber-50 text-amber-600 text-2xl ring-1 ring-amber-100">💛</div>
                    <h3 class="mt-4 font-bold text-slate-900" x-text="$store.lang.t('Support', 'Soutenir')"></h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed"
                       x-text="$store.lang.t(
                           'Mobilise solidarity in moments that matter — funerals, weddings, scholarships, emergencies.',
                           'Mobilisez la solidarité aux moments qui comptent — funérailles, mariages, bourses, urgences.'
                       )"></p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-rose-50 text-rose-600 text-2xl ring-1 ring-rose-100">🇨🇲</div>
                    <h3 class="mt-4 font-bold text-slate-900" x-text="$store.lang.t('Belong', 'Appartenir')"></h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed"
                       x-text="$store.lang.t(
                           'Speak your language, share your story, celebrate your culture — and feel at home, always.',
                           'Parlez votre langue, partagez votre histoire, célébrez votre culture — et sentez-vous chez vous, toujours.'
                       )"></p>
                </div>
            </div>
        </section>

        {{-- Story --}}
        <section class="mx-auto max-w-3xl px-6 py-10">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
                <h2 class="text-2xl font-bold text-slate-900"
                    x-text="$store.lang.t('Our story', 'Notre histoire')"></h2>
                <div class="mt-4 space-y-4 text-slate-600 leading-relaxed text-[15px]">
                    <p x-text="$store.lang.t(
                        'Cameroon Network started from a simple frustration: our diaspora is everywhere, our talent is everywhere, our love for home is everywhere — but the tools to find each other are not built for us.',
                        'Cameroon Network est né d\'une frustration simple : notre diaspora est partout, nos talents sont partout, notre amour du pays est partout — mais les outils pour nous retrouver ne sont pas faits pour nous.'
                    )"></p>
                    <p x-text="$store.lang.t(
                        'We built a platform in English and French, where your village, your region and your story are first-class — not buried in a profile field. From mutual-aid yards to private community rooms, every feature is designed to make distance feel a little smaller.',
                        'Nous avons construit une plateforme en français et en anglais, où votre village, votre région et votre histoire sont au premier plan — pas enfouis dans un champ de profil. Des cours d\'entraide aux salons privés de communauté, chaque fonctionnalité est conçue pour rendre la distance plus petite.'
                    )"></p>
                    <p x-text="$store.lang.t(
                        'We are independent, ad-free, and proudly Cameroonian. We grow because you tell a friend.',
                        'Nous sommes indépendants, sans publicité et fièrement camerounais. Nous grandissons parce que vous en parlez à un ami.'
                    )"></p>
                </div>
            </div>
        </section>

        {{-- Values --}}
        <section class="mx-auto max-w-5xl px-6 py-8">
            <h2 class="text-center text-2xl font-bold text-slate-900"
                x-text="$store.lang.t('What we stand for', 'Ce que nous défendons')"></h2>
            <div class="mt-6 grid sm:grid-cols-2 gap-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-6">
                    <h3 class="font-bold text-slate-900" x-text="$store.lang.t('Privacy first', 'La vie privée d\'abord')"></h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed"
                       x-text="$store.lang.t(
                           'No selling your data. No background tracking. You own your story.',
                           'Aucune revente de vos données. Aucun suivi en arrière-plan. Votre histoire vous appartient.'
                       )"></p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6">
                    <h3 class="font-bold text-slate-900" x-text="$store.lang.t('Bilingual by design', 'Bilingue par conception')"></h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed"
                       x-text="$store.lang.t(
                           'Every screen, every button, every email — French and English, equally.',
                           'Chaque écran, chaque bouton, chaque e-mail — en français et en anglais, à égalité.'
                       )"></p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6">
                    <h3 class="font-bold text-slate-900" x-text="$store.lang.t('Safety as a feature', 'La sécurité comme fonctionnalité')"></h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed"
                       x-text="$store.lang.t(
                           'Strict moderation, easy reporting, and zero tolerance for tribalism or harassment.',
                           'Modération stricte, signalement facile et tolérance zéro envers le tribalisme ou le harcèlement.'
                       )"></p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6">
                    <h3 class="font-bold text-slate-900" x-text="$store.lang.t('Built in the open', 'Construit en transparence')"></h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed"
                       x-text="$store.lang.t(
                           'We listen. Tell us what you need and watch the platform grow with you.',
                           'Nous écoutons. Dites-nous ce dont vous avez besoin et regardez la plateforme grandir avec vous.'
                       )"></p>
                </div>
            </div>
        </section>

        {{-- CTA --}}
        <section class="mx-auto max-w-3xl px-6 py-12 text-center">
            <div class="rounded-2xl bg-gradient-to-br from-cm-green to-emerald-700 p-8 text-white">
                <h2 class="text-2xl sm:text-3xl font-extrabold"
                    x-text="$store.lang.t('Join the network', 'Rejoignez le réseau')"></h2>
                <p class="mt-2 text-white/85"
                   x-text="$store.lang.t('Free, forever. Your community is waiting.', 'Gratuit, pour toujours. Votre communauté vous attend.')"></p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('register') }}"
                       class="inline-flex items-center gap-2 rounded-full bg-cm-yellow px-6 py-2.5 text-sm font-bold text-slate-900 hover:brightness-110 transition-all">
                        <span x-text="$store.lang.t('Create account', 'Créer un compte')"></span>
                    </a>
                    <a href="{{ route('contact') }}"
                       class="inline-flex items-center gap-2 rounded-full bg-white/10 hover:bg-white/20 px-6 py-2.5 text-sm font-bold text-white transition-all">
                        <span x-text="$store.lang.t('Contact us', 'Nous contacter')"></span>
                    </a>
                </div>
            </div>

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
