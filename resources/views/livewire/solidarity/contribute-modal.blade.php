<div>
    @if($showModal && $campaign)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data="{ show: true }">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/40" @click="$wire.close()"></div>

        {{-- Modal --}}
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden" x-transition>
            @if(!$submitted)
            {{-- Header --}}
            <div class="bg-cm-green/5 px-6 py-4 border-b border-slate-200">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-900" x-text="$store.lang.t('Contribute', 'Contribuer')"></h3>
                    <button wire:click="close" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <p class="text-sm text-slate-600 mt-1">{{ $campaign->title }}</p>
            </div>

            <div class="p-6 space-y-5">
                {{-- Preset amounts --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2" x-text="$store.lang.t('Select Amount', 'Sélectionner le Montant')"></label>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach(['5', '10', '20', '50'] as $preset)
                        <button wire:click="setPreset('{{ $preset }}')" type="button"
                                class="rounded-lg py-2.5 text-sm font-semibold border transition-colors
                                       {{ $selectedPreset === $preset ? 'bg-cm-green text-white border-cm-green' : 'bg-white text-slate-700 border-slate-300 hover:border-cm-green' }}">
                            {{ $campaign->currency === 'GBP' ? '£' : ($campaign->currency === 'EUR' ? '€' : ($campaign->currency === 'USD' ? '$' : '')) }}{{ $preset }}
                        </button>
                        @endforeach
                    </div>
                </div>

                {{-- Custom amount --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Or enter custom amount', 'Ou entrez un montant personnalisé')"></label>
                    <input type="number" wire:model.live="amount" min="1" step="0.01"
                           class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-cm-green focus:ring-1 focus:ring-cm-green">
                    @error('amount') <p class="mt-1 text-xs text-cm-red">{{ $message }}</p> @enderror
                </div>

                {{-- Anonymous toggle --}}
                @if($campaign->is_anonymous_allowed)
                <div class="flex items-center gap-3">
                    <button wire:click="$toggle('isAnonymous')" type="button"
                            class="relative w-11 h-6 rounded-full transition-colors {{ $isAnonymous ? 'bg-cm-green' : 'bg-slate-300' }}">
                        <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform {{ $isAnonymous ? 'translate-x-5' : '' }}"></span>
                    </button>
                    <span class="text-sm text-slate-700" x-text="$store.lang.t('Contribute anonymously', 'Contribuer de manière anonyme')"></span>
                </div>
                @endif

                {{-- Personal message --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" x-text="$store.lang.t('Personal Message (optional)', 'Message Personnel (optionnel)')"></label>
                    <textarea wire:model="message" rows="2" maxlength="200"
                              :placeholder="$store.lang.t('A word of support...', 'Un mot de soutien...')"
                              class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-cm-green focus:ring-1 focus:ring-cm-green resize-none"></textarea>
                    @if(app(\App\Services\AIService::class)->isAvailable())
                    <button type="button" wire:click="suggestMessage"
                            class="mt-1.5 text-xs text-cm-green hover:underline flex items-center gap-1 disabled:opacity-50"
                            wire:loading.attr="disabled" wire:target="suggestMessage">
                        <span wire:loading.remove wire:target="suggestMessage">🤖</span>
                        <span wire:loading.remove wire:target="suggestMessage" x-text="$store.lang.t('Suggest a message', 'Suggérer un message')"></span>
                        <span wire:loading wire:target="suggestMessage" x-text="$store.lang.t('Thinking...', 'Réflexion...')"></span>
                    </button>
                    @endif
                </div>

                {{-- Fee breakdown --}}
                @if(is_numeric($amount) && $amount > 0)
                <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-600" x-text="$store.lang.t('Your contribution', 'Votre contribution')"></span>
                        <span class="font-semibold text-slate-900">{{ $campaign->currency === 'GBP' ? '£' : '' }}{{ number_format((float)$amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 text-xs" x-text="$store.lang.t('Platform fee (5%)', 'Frais plateforme (5%)')"></span>
                        <span class="text-slate-500 text-xs">{{ $campaign->currency === 'GBP' ? '£' : '' }}{{ number_format(round((float)$amount * ($campaign->platform_cut_percent / 100), 2), 2) }}</span>
                    </div>
                    <div class="flex justify-between pt-1.5 border-t border-slate-200">
                        <span class="font-medium text-slate-700" x-text="$store.lang.t('Amount to beneficiary', 'Montant au bénéficiaire')"></span>
                        <span class="font-bold text-cm-green">{{ $campaign->currency === 'GBP' ? '£' : '' }}{{ number_format(round((float)$amount * (1 - $campaign->platform_cut_percent / 100), 2), 2) }}</span>
                    </div>
                </div>
                @endif

                {{-- Confirm --}}
                <button wire:click="confirm" class="w-full rounded-xl bg-cm-green py-3.5 text-sm font-bold text-white transition-colors hover:bg-cm-green-light"
                        wire:loading.attr="disabled">
                    <span x-text="$store.lang.t('Confirm Contribution', 'Confirmer la Contribution')"></span>
                </button>
            </div>

            @else
            {{-- Thank you state --}}
            <div class="p-8 text-center">
                <div class="text-5xl mb-4">❤️</div>
                <h3 class="text-xl font-bold text-slate-900 mb-2" x-text="$store.lang.t('Thank You!', 'Merci !')"></h3>
                <p class="text-slate-600 text-sm" x-text="$store.lang.t(
                    'Your contribution means everything to this family.',
                    'Votre contribution signifie tout pour cette famille.'
                )"></p>
                <button wire:click="close" class="mt-6 rounded-xl bg-cm-green px-8 py-3 text-sm font-bold text-white transition-colors hover:bg-cm-green-light">
                    <span x-text="$store.lang.t('Close', 'Fermer')"></span>
                </button>
            </div>
            @endif
        </div>
    </div>
    @endif
</div>
