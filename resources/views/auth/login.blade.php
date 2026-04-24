<x-layouts.guest>
    <x-slot:title>Login — Cameroon Community</x-slot:title>

    <div class="min-h-screen flex flex-col lg:flex-row">

        {{-- ════════════════════════════════════════════════════════════ --}}
        {{-- LEFT PANEL — Branding, Features & Stats                      --}}
        {{-- Hidden on mobile, shown lg+                                   --}}
        {{-- ════════════════════════════════════════════════════════════ --}}
        <div class="hidden lg:flex lg:w-[42%] xl:w-[38%] relative overflow-hidden flex-col justify-between
                    bg-gradient-to-br from-cm-green-deeper via-cm-green to-cm-green-deep">

            {{-- Decorative Elements --}}
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-white/[.04] blur-3xl"></div>
                <div class="absolute -bottom-32 -left-20 w-96 h-96 rounded-full bg-cm-yellow/[.06] blur-3xl"></div>
                <div class="absolute top-1/3 right-0 w-64 h-64 rounded-full bg-cm-red/[.04] blur-3xl"></div>
                <div class="absolute inset-0 opacity-[.03]" style="background-image: radial-gradient(circle, #fff 1px, transparent 1px); background-size: 32px 32px;"></div>

                {{-- Concentric circles (matches register page) --}}
                <svg class="absolute -top-20 -left-20 w-96 h-96 animate-pulse-soft opacity-[.02]" viewBox="0 0 200 200">
                    <circle cx="100" cy="100" r="80" fill="none" stroke="#FCD116" stroke-width="1.5"/>
                    <circle cx="100" cy="100" r="60" fill="none" stroke="#FCD116" stroke-width="1"/>
                    <circle cx="100" cy="100" r="40" fill="none" stroke="#FCD116" stroke-width="0.5"/>
                </svg>
                <svg class="absolute -bottom-16 -right-16 w-80 h-80 animate-pulse-soft opacity-[.02]" viewBox="0 0 200 200" style="animation-delay: 1s">
                    <circle cx="100" cy="100" r="80" fill="none" stroke="#FCD116" stroke-width="1.5"/>
                    <circle cx="100" cy="100" r="60" fill="none" stroke="#FCD116" stroke-width="1"/>
                    <circle cx="100" cy="100" r="40" fill="none" stroke="#FCD116" stroke-width="0.5"/>
                </svg>
            </div>

            <div class="relative z-10 flex flex-col justify-between h-full p-8 xl:p-10">
                {{-- Logo --}}
                <div>
                    <a href="{{ route('home') }}" class="inline-flex items-center group">
                        @if($__siteLogo ?? null)
                            <img src="{{ $__siteLogo }}" alt="{{ $__siteName ?? 'Cameroon Community' }}" class="h-[120px] object-contain transition-transform group-hover:scale-110">
                        @else
                            <span class="text-3xl transition-transform group-hover:scale-110">🇨🇲</span>
                        @endif
                    </a>
                </div>

                {{-- Hero Section --}}
                <div class="mt-10 mb-8">
                    <h1 class="text-3xl xl:text-4xl font-extrabold text-white leading-tight whitespace-nowrap"
                        x-text="$store.lang.t('Welcome Back!', 'Bon Retour !')"></h1>
                    <p class="mt-3 text-sm text-white/70 leading-relaxed max-w-xs"
                       x-text="$store.lang.t(
                           'Your community is always here for you. Sign in to reconnect.',
                           'Votre communauté est toujours là pour vous. Connectez-vous pour retrouver les vôtres.'
                       )"></p>
                </div>

                {{-- Feature Cards --}}
                <div class="space-y-3 mb-8">
                    <div class="flex items-center gap-3 rounded-xl bg-white/[.08] backdrop-blur-sm border border-white/[.08] px-4 py-3 transition-all hover:bg-white/[.12]">
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-cm-yellow/20 flex items-center justify-center">
                            <span class="text-lg">💬</span>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-white" x-text="$store.lang.t('The Yard', 'Le Yard')"></p>
                            <p class="text-xs text-white/60" x-text="$store.lang.t('Live chat rooms by city & country', 'Salons de discussion par ville et pays')"></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl bg-white/[.08] backdrop-blur-sm border border-white/[.08] px-4 py-3 transition-all hover:bg-white/[.12]">
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-cm-red/20 flex items-center justify-center">
                            <span class="text-lg">🤝</span>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-white" x-text="$store.lang.t('Solidarity', 'Solidarité')"></p>
                            <p class="text-xs text-white/60" x-text="$store.lang.t('Crowdfund & support each other', 'Financez et soutenez-vous mutuellement')"></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl bg-white/[.08] backdrop-blur-sm border border-white/[.08] px-4 py-3 transition-all hover:bg-white/[.12]">
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-400/20 flex items-center justify-center">
                            <span class="text-lg">🤖</span>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-white">Kamer AI</p>
                            <p class="text-xs text-white/60" x-text="$store.lang.t('Your personal Cameroon guide', 'Votre guide personnel du Cameroun')"></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl bg-white/[.08] backdrop-blur-sm border border-white/[.08] px-4 py-3 transition-all hover:bg-white/[.12]">
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-400/20 flex items-center justify-center">
                            <span class="text-lg">🌍</span>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-white" x-text="$store.lang.t('Global Network', 'Réseau Mondial')"></p>
                            <p class="text-xs text-white/60" x-text="$store.lang.t('Cameroonians in 20+ countries', 'Camerounais dans 20+ pays')"></p>
                        </div>
                    </div>
                </div>

                {{-- Stats Footer --}}
                <div class="flex items-center gap-6 pt-6 border-t border-white/10">
                    <div>
                        <p class="text-2xl font-extrabold text-white">{{ number_format(\App\Models\User::withoutGlobalScopes()->count()) }}+</p>
                        <p class="text-[11px] text-white/50 font-medium" x-text="$store.lang.t('Members', 'Membres')"></p>
                    </div>
                    <div class="w-px h-8 bg-white/10"></div>
                    <div>
                        <p class="text-2xl font-extrabold text-white">{{ \App\Models\YardRoom::where('is_active', true)->distinct('country')->count('country') }}+</p>
                        <p class="text-[11px] text-white/50 font-medium" x-text="$store.lang.t('Countries', 'Pays')"></p>
                    </div>
                    <div class="w-px h-8 bg-white/10"></div>
                    <div>
                        <p class="text-2xl font-extrabold text-white">24/7</p>
                        <p class="text-[11px] text-white/50 font-medium" x-text="$store.lang.t('Active', 'Actif')"></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════ --}}
        {{-- RIGHT PANEL — Login Form                                     --}}
        {{-- ════════════════════════════════════════════════════════════ --}}
        <div class="flex-1 min-h-screen bg-slate-50/50 flex items-center justify-center overflow-y-auto relative">
            {{-- Cameroon Flag Ribbon --}}
            <x-cameroon-ribbon size="sm" />

            <div class="w-full max-w-md px-5 sm:px-8 py-10 lg:py-6 relative z-10">

                {{-- Mobile-only logo --}}
                <div class="text-center mb-6 lg:hidden">
                    <a href="{{ route('home') }}" class="inline-flex items-center group">
                        @if($__siteLogo ?? null)
                            <img src="{{ $__siteLogo }}" alt="{{ $__siteName ?? 'Cameroon Community' }}" class="h-20 object-contain transition-transform group-hover:scale-110">
                        @else
                            <span class="text-3xl transition-transform group-hover:scale-110">🇨🇲</span>
                        @endif
                    </a>
                </div>

                {{-- User Icon --}}
                <div class="flex justify-center mb-5">
                    <div class="w-16 h-16 rounded-full bg-gradient-to-br from-cm-green to-blue-700 flex items-center justify-center shadow-lg shadow-cm-green/20">
                        <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-xl shadow-black/5 border border-slate-200/80 overflow-hidden">
                    {{-- Tricolour top line --}}
                    <div class="h-1 bg-gradient-to-r from-cm-green via-cm-red to-cm-yellow"></div>

                    <div class="p-6 sm:p-8">
                        <h2 class="text-2xl font-extrabold text-slate-900 text-center" x-text="$store.lang.t('Welcome Back', 'Bienvenue')"></h2>
                        <p class="mt-1 text-sm text-slate-500 text-center" x-text="$store.lang.t('Sign in to your community.', 'Connectez-vous à votre communauté.')"></p>

                        @if(session('status'))
                            <div class="mt-4 rounded-xl bg-cm-green/10 border border-cm-green/20 px-4 py-3 text-sm text-cm-green">
                                {{ session('status') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                            @csrf
                            {{-- Email --}}
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Email Address', 'Adresse Email')"></label>
                                <div class="relative">
                                    <div class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
                                    </div>
                                    <input name="email" type="email" value="{{ old('email') }}" required autofocus
                                           class="w-full rounded-xl border border-slate-300 pl-11 pr-4 py-3 text-sm outline-none transition-colors focus:border-cm-green focus:ring-1 focus:ring-cm-green"
                                           placeholder="you@example.com">
                                </div>
                                @error('email') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                            </div>

                            {{-- Password --}}
                            <div x-data="{ show: false }">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-sm font-medium text-slate-700" x-text="$store.lang.t('Password', 'Mot de Passe')"></label>
                                    <a href="{{ route('password.request') }}" class="text-xs text-cm-green hover:underline font-medium" x-text="$store.lang.t('Forgot password?', 'Mot de passe oublié ?')"></a>
                                </div>
                                <div class="relative">
                                    <div class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                                    </div>
                                    <input name="password" :type="show ? 'text' : 'password'" required
                                           class="w-full rounded-xl border border-slate-300 pl-11 pr-12 py-3 text-sm outline-none transition-colors focus:border-cm-green focus:ring-1 focus:ring-cm-green"
                                           placeholder="••••••••">
                                    <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                        <svg x-show="!show" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <svg x-show="show" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                    </button>
                                </div>
                                @error('password') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                            </div>

                            {{-- Remember Me --}}
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input name="remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-cm-green focus:ring-cm-green">
                                <span class="text-sm text-slate-600" x-text="$store.lang.t('Remember me', 'Se souvenir de moi')"></span>
                            </label>

                            <button type="submit" class="w-full rounded-xl bg-gradient-to-r from-cm-green to-blue-700 py-3.5 text-sm font-bold text-white shadow-lg shadow-cm-green/20 transition-all hover:shadow-xl hover:shadow-cm-green/30 hover:-translate-y-0.5">
                                <span x-text="$store.lang.t('Sign In', 'Se Connecter')"></span>
                            </button>
                        </form>
                    </div>
                </div>

                <p class="mt-6 text-center text-sm text-slate-500">
                    <span x-text="$store.lang.t('Don\'t have an account?', 'Vous n\'avez pas de compte ?')"></span>
                    <a href="{{ route('register') }}" class="ml-1 font-semibold text-cm-green hover:underline" x-text="$store.lang.t('Sign up', 'Inscrivez-vous')"></a>
                </p>
            </div>
        </div>
    </div>
</x-layouts.guest>
