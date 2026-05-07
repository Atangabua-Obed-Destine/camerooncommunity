@props([
    'badge' => ['en' => 'Legal', 'fr' => 'Légal'],
    'title' => ['en' => '', 'fr' => ''],
    'subtitle' => ['en' => '', 'fr' => ''],
    'sections' => [],
    'updated' => '',
    'accent' => 'green', // green | yellow | red
])

@php
    $accentMap = [
        'green' => ['badge' => 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'glow' => 'from-emerald-400/20', 'bar' => 'bg-emerald-500'],
        'yellow' => ['badge' => 'bg-amber-50 text-amber-800 ring-amber-200', 'glow' => 'from-amber-400/25', 'bar' => 'bg-amber-500'],
        'red' => ['badge' => 'bg-rose-50 text-rose-700 ring-rose-200', 'glow' => 'from-rose-400/20', 'bar' => 'bg-rose-500'],
    ];
    $a = $accentMap[$accent] ?? $accentMap['green'];
@endphp

{{-- Same sticky navbar used across the marketing site --}}
@php($forceScrolled = true)
@include('partials.site-nav')

<div class="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50 pt-24 sm:pt-28">
    {{-- Hero --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br {{ $a['glow'] }} via-transparent to-transparent pointer-events-none"></div>
        <div class="relative mx-auto max-w-3xl px-6 pt-10 pb-10 text-center">
            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider ring-1 {{ $a['badge'] }}"
                  x-text="$store.lang.t({{ \Illuminate\Support\Js::from($badge['en']) }}, {{ \Illuminate\Support\Js::from($badge['fr']) }})"></span>
            <h1 class="mt-5 text-4xl sm:text-5xl font-extrabold tracking-tight text-slate-900"
                x-text="$store.lang.t({{ \Illuminate\Support\Js::from($title['en']) }}, {{ \Illuminate\Support\Js::from($title['fr']) }})"></h1>
            <p class="mt-4 text-base sm:text-lg text-slate-600 leading-relaxed"
               x-text="$store.lang.t({{ \Illuminate\Support\Js::from($subtitle['en']) }}, {{ \Illuminate\Support\Js::from($subtitle['fr']) }})"></p>
            @if($updated)
                <p class="mt-6 text-xs text-slate-400">
                    <span x-text="$store.lang.t('Last updated', 'Dernière mise à jour')"></span>: <span class="font-semibold text-slate-500">{{ $updated }}</span>
                </p>
            @endif
        </div>
    </section>

    {{-- Sections --}}
    <section class="mx-auto max-w-3xl px-6 pb-16">
        <ol class="space-y-4">
            @foreach($sections as $i => $s)
                <li class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="absolute left-0 top-0 bottom-0 w-1 {{ $a['bar'] }} opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-2xl ring-1 ring-slate-200">
                            {{ $s['icon'] }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] font-bold text-slate-400">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                <h2 class="text-lg font-bold text-slate-900"
                                    x-text="$store.lang.t({{ \Illuminate\Support\Js::from($s['en']['title']) }}, {{ \Illuminate\Support\Js::from($s['fr']['title']) }})"></h2>
                            </div>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600"
                               x-text="$store.lang.t({{ \Illuminate\Support\Js::from($s['en']['body']) }}, {{ \Illuminate\Support\Js::from($s['fr']['body']) }})"></p>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>

        {{-- Contact card --}}
        <div class="mt-10 rounded-2xl border border-slate-900 bg-slate-900 p-6 text-white">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold" x-text="$store.lang.t('Questions? We\'re listening.', 'Des questions ? Nous écoutons.')"></h3>
                    <p class="mt-1 text-sm text-slate-300"
                       x-text="$store.lang.t('Reach our team directly — we reply within 7 days.', 'Contactez notre équipe — nous répondons sous 7 jours.')"></p>
                </div>
                <a href="mailto:hello@cameroonnetwork.org"
                   class="shrink-0 rounded-full px-5 py-2 text-sm font-bold text-slate-900 hover:brightness-110 transition-all"
                   style="background-color: var(--color-cm-yellow, #fcd116);">
                    hello@cameroonnetwork.org
                </a>
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
