<x-layouts.guest>
    <x-slot:title>Forgot Password — Cameroon Community</x-slot:title>

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

            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
                <h2 class="text-2xl font-bold text-slate-900" x-text="$store.lang.t('Reset Password', 'Réinitialiser le Mot de Passe')"></h2>
                <p class="mt-1 text-sm text-slate-500" x-text="$store.lang.t('Enter your email and we\'ll send you a reset link.', 'Entrez votre email et nous vous enverrons un lien de réinitialisation.')"></p>

                @if(session('status'))
                    <div class="mt-4 rounded-xl bg-cm-green/10 border border-cm-green/20 px-4 py-3 text-sm text-cm-green">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Email Address', 'Adresse Email')"></label>
                        <input name="email" type="email" value="{{ old('email') }}" required autofocus
                               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition-colors focus:border-cm-green focus:ring-1 focus:ring-cm-green">
                        @error('email') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-cm-green py-3.5 text-sm font-bold text-white transition-colors hover:bg-cm-green-light">
                        <span x-text="$store.lang.t('Send Reset Link', 'Envoyer le Lien')"></span>
                    </button>
                </form>

                <p class="mt-4 text-center text-sm text-slate-500">
                    <a href="{{ route('login') }}" class="text-cm-green hover:underline" x-text="$store.lang.t('Back to login', 'Retour à la connexion')"></a>
                </p>
            </div>
        </div>
    </div>
</x-layouts.guest>
