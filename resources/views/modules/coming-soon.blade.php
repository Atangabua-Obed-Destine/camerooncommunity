<x-layouts.app>
    <x-slot:title>{{ $moduleName }} — Cameroon Community</x-slot:title>

    <div class="min-h-[calc(100vh-8rem)] flex items-center justify-center py-12 px-4">
        <div class="max-w-lg w-full text-center" x-data="{ email: '', submitted: false, loading: false }">
            {{-- Icon --}}
            <div class="w-24 h-24 mx-auto rounded-3xl bg-cm-green/10 flex items-center justify-center text-5xl mb-6">
                @switch($moduleSlug)
                    @case('marche') 🛒 @break
                    @case('easygoparcell') 📦 @break
                    @case('roadfam') 🚗 @break
                    @case('camevents') 🎉 @break
                    @case('kamernest') 🏠 @break
                    @case('workconnect') 💼 @break
                    @case('kamereats') 🍽️ @break
                    @case('kamersos') 🆘 @break
                    @case('camstories') 📸 @break
                    @case('kamerpulse') 📊 @break
                    @case('kamersend') 💸 @break
                    @default 🚀
                @endswitch
            </div>

            {{-- Title --}}
            <h1 class="text-3xl font-bold text-slate-900 mb-2">{{ $moduleName }}</h1>

            <p class="text-slate-500 mb-2">
                <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-cm-yellow">
                    <span class="w-2 h-2 rounded-full bg-cm-yellow animate-pulse"></span>
                    <span x-text="$store.lang.t('Coming Soon', 'Bientôt Disponible')"></span>
                </span>
            </p>

            <p class="text-slate-600 mb-8" x-text="$store.lang.t(
                'We\\'re building something amazing for the Cameroon Community. Be the first to know when {{ $moduleName }} launches!',
                'Nous construisons quelque chose d\\'incroyable pour la communauté camerounaise. Soyez le premier informé du lancement de {{ $moduleName }} !'
            )"></p>

            {{-- Notify form --}}
            <template x-if="!submitted">
                <form @submit.prevent="
                    loading = true;
                    fetch('/api/coming-soon/signup', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ email: email, module: '{{ $moduleSlug }}' })
                    }).then(r => { submitted = true; }).catch(() => {}).finally(() => { loading = false; });
                " class="flex gap-2 max-w-sm mx-auto">
                    <input type="email" x-model="email" required
                           :placeholder="$store.lang.t('Your email address', 'Votre adresse email')"
                           class="flex-1 rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-cm-green focus:ring-1 focus:ring-cm-green">
                    <button type="submit" :disabled="loading"
                            class="shrink-0 rounded-xl bg-cm-green px-5 py-3 text-sm font-bold text-white transition-colors hover:bg-cm-green-light disabled:opacity-50">
                        <span x-show="!loading" x-text="$store.lang.t('Notify Me', 'Me Notifier')"></span>
                        <span x-show="loading" class="flex items-center gap-1">
                            <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" class="opacity-25"/><path fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 100 8v4a8 8 0 01-8-8z" class="opacity-75"/></svg>
                        </span>
                    </button>
                </form>
            </template>

            <template x-if="submitted">
                <div class="bg-cm-green/10 border border-cm-green/20 rounded-xl p-4 max-w-sm mx-auto">
                    <p class="text-sm font-semibold text-cm-green flex items-center justify-center gap-2">
                        <span>✅</span>
                        <span x-text="$store.lang.t('We\\'ll notify you when it launches!', 'Nous vous informerons lors du lancement !')"></span>
                    </p>
                </div>
            </template>

            {{-- Back link --}}
            <div class="mt-8">
                <a href="{{ route('yard') }}" class="text-sm text-cm-green font-medium hover:underline" x-text="$store.lang.t('← Back to The Yard', '← Retour au Yard')"></a>
            </div>
        </div>
    </div>
</x-layouts.app>
