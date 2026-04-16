<x-layouts.guest>
    <x-slot:title>Verify Email — Cameroon Community</x-slot:title>

    <div class="min-h-screen bg-slate-50 flex items-center justify-center py-12 px-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <a href="{{ route('home') }}" class="inline-flex items-center">
                    @if($__siteLogo ?? null)
                        <img src="{{ $__siteLogo }}" alt="{{ $__siteName ?? 'Cameroon Community' }}" class="h-14 object-contain">
                    @else
                        <span class="text-3xl">🇨🇲</span>
                    @endif
                </a>
            </div>

            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8 text-center">
                <div class="w-16 h-16 rounded-full bg-cm-yellow/20 flex items-center justify-center mx-auto mb-6">
                    <svg class="w-8 h-8 text-cm-yellow-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>

                <h2 class="text-2xl font-bold text-slate-900" x-text="$store.lang.t('Check Your Email', 'Vérifiez Votre Email')"></h2>
                <p class="mt-2 text-sm text-slate-500" x-text="$store.lang.t(
                    'We sent a verification link to your email address. Please click the link to verify your account.',
                    'Nous avons envoyé un lien de vérification à votre adresse email. Veuillez cliquer sur le lien pour vérifier votre compte.'
                )"></p>

                @if(session('message'))
                    <div class="mt-4 rounded-xl bg-cm-green/10 border border-cm-green/20 px-4 py-3 text-sm text-cm-green">
                        {{ session('message') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
                    @csrf
                    <button type="submit" class="w-full rounded-xl bg-cm-green py-3.5 text-sm font-bold text-white transition-colors hover:bg-cm-green-light">
                        <span x-text="$store.lang.t('Resend Verification Email', 'Renvoyer l\'Email de Vérification')"></span>
                    </button>
                </form>

                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="text-sm text-slate-500 hover:text-slate-700" x-text="$store.lang.t('Log out', 'Se déconnecter')"></button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.guest>
