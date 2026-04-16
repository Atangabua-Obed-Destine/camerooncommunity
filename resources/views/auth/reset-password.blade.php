<x-layouts.guest>
    <x-slot:title>Reset Password — Cameroon Community</x-slot:title>

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
                <h2 class="text-2xl font-bold text-slate-900" x-text="$store.lang.t('Set New Password', 'Définir un Nouveau Mot de Passe')"></h2>

                <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Email Address', 'Adresse Email')"></label>
                        <input name="email" type="email" value="{{ old('email', $email) }}" required
                               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition-colors focus:border-cm-green focus:ring-1 focus:ring-cm-green">
                        @error('email') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('New Password', 'Nouveau Mot de Passe')"></label>
                        <input name="password" type="password" required
                               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition-colors focus:border-cm-green focus:ring-1 focus:ring-cm-green">
                        @error('password') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Confirm Password', 'Confirmer le Mot de Passe')"></label>
                        <input name="password_confirmation" type="password" required
                               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none transition-colors focus:border-cm-green focus:ring-1 focus:ring-cm-green">
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-cm-green py-3.5 text-sm font-bold text-white transition-colors hover:bg-cm-green-light">
                        <span x-text="$store.lang.t('Reset Password', 'Réinitialiser')"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.guest>
