<div class="min-h-[calc(100vh-96px)] bg-slate-100">
    <div class="max-w-3xl mx-auto px-3 sm:px-4 py-6">

        {{-- Listing summary --}}
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 overflow-hidden mb-4">
            <div class="px-5 py-4 bg-gradient-to-r from-cm-green to-cm-green/80 text-white">
                <h1 class="text-xl font-extrabold">
                    <span x-data x-text="$store.lang.t('Checkout','Paiement')"></span>
                </h1>
                <p class="text-xs opacity-90 mt-0.5">
                    <span x-data x-text="$store.lang.t('Pay the seller securely with Mobile Money.','Payez le vendeur en toute sécurité avec Mobile Money.')"></span>
                </p>
            </div>
            <div class="p-4 flex items-center gap-3">
                @if ($listing->coverUrl())
                    <img src="{{ $listing->coverUrl() }}" alt="" class="w-16 h-16 rounded-xl object-cover ring-1 ring-slate-200">
                @else
                    <div class="w-16 h-16 rounded-xl bg-slate-200 grid place-items-center text-2xl">📦</div>
                @endif
                <div class="min-w-0 flex-1">
                    <a href="{{ route('marketplace.show', ['slug' => $listing->slug]) }}" wire:navigate class="font-bold text-slate-900 hover:text-cm-green truncate block">{{ $listing->title }}</a>
                    <div class="text-[12px] text-slate-500 truncate">
                        <span x-data x-text="$store.lang.t('Sold by','Vendu par')"></span>
                        <span class="font-semibold">{{ $listing->seller?->name ?: $listing->seller?->username }}</span>
                    </div>
                </div>
                <div class="text-lg font-extrabold text-cm-green shrink-0">
                    {{ number_format((float) $listing->price, 0) }} {{ $listing->currency }}
                </div>
            </div>
        </div>

        {{-- Step 1: pick a payment method (only if no open order yet) --}}
        @if (! $order)
            <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-5">
                <h2 class="text-base font-bold text-slate-900 mb-3">
                    <span x-data x-text="$store.lang.t('Choose how you want to pay','Choisissez le mode de paiement')"></span>
                </h2>
                @if (empty($this->drivers))
                    <div class="p-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 text-sm text-amber-900">
                        <div class="font-semibold mb-1">
                            <span x-data x-text="$store.lang.t('Seller has not set up Mobile Money yet','Le vendeur n\'a pas encore configuré Mobile Money')"></span>
                        </div>
                        <div class="text-[13px]">
                            <span x-data x-text="$store.lang.t('Message the seller to arrange payment directly.','Contactez le vendeur pour organiser le paiement.')"></span>
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                        @foreach ($this->drivers as $d)
                            <button type="button" wire:click="$set('provider','{{ $d->id() }}')"
                                    class="text-left p-4 rounded-2xl ring-2 transition
                                    {{ $provider === $d->id() ? 'ring-cm-green bg-cm-green/5' : 'ring-slate-200 hover:ring-cm-green/50 hover:bg-slate-50' }}">
                                <div class="text-2xl">{{ $d->icon() }}</div>
                                <div class="mt-1 text-sm font-bold text-slate-900">{{ $d->label(app()->getLocale()) }}</div>
                            </button>
                        @endforeach
                    </div>
                    <div class="flex items-center justify-between">
                        <a href="{{ route('marketplace.show', ['slug' => $listing->slug]) }}" wire:navigate
                           class="text-sm text-slate-600 hover:text-slate-900 font-semibold">
                            ← <span x-data x-text="$store.lang.t('Back to listing','Retour à l\'annonce')"></span>
                        </a>
                        <button wire:click="startCheckout"
                                class="bg-cm-green hover:bg-cm-green/90 text-white text-sm font-bold rounded-full px-6 py-2.5 shadow">
                            <span x-data x-text="$store.lang.t('Continue','Continuer')"></span>
                        </button>
                    </div>
                @endif
            </div>
        @else
            {{-- Step 2: instructions + evidence form --}}
            @php($inst = $this->instructions)
            @php($status = $order->status)
            <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 overflow-hidden">
                <div class="px-5 py-3 flex items-center justify-between border-b border-slate-100">
                    <div class="text-sm font-semibold text-slate-700">
                        {{ $inst['title'] ?? '' }}
                    </div>
                    <span class="text-[11px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-full ring-1 {{ $status->chip() }}">
                        {{ app()->getLocale() === 'fr' ? $status->labelFr() : $status->label() }}
                    </span>
                </div>

                <div class="p-5 space-y-5">
                    <div class="grid grid-cols-2 gap-3 text-center">
                        <div class="p-3 rounded-xl bg-slate-50 ring-1 ring-slate-200">
                            <div class="text-[11px] text-slate-500 uppercase font-bold tracking-wide">
                                <span x-data x-text="$store.lang.t('Amount','Montant')"></span>
                            </div>
                            <div class="text-lg font-extrabold text-cm-green">{{ $inst['amount'] ?? '' }}</div>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 ring-1 ring-slate-200">
                            <div class="text-[11px] text-slate-500 uppercase font-bold tracking-wide">
                                <span x-data x-text="$store.lang.t('Reference','Référence')"></span>
                            </div>
                            <div class="text-lg font-extrabold text-slate-900 tracking-wider select-all">{{ $inst['reference'] ?? $order->reference }}</div>
                        </div>
                    </div>

                    @if (! empty($inst['recipient']['number']))
                        <div class="p-4 rounded-xl bg-cm-green/5 ring-1 ring-cm-green/20">
                            <div class="text-[11px] font-bold uppercase tracking-wide text-cm-green mb-1">
                                <span x-data x-text="$store.lang.t('Send to','Envoyer à')"></span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-base font-extrabold text-slate-900 tracking-wider select-all">{{ $inst['recipient']['number'] }}</div>
                                    <div class="text-xs text-slate-600 truncate">{{ $inst['recipient']['provider'] }} • {{ $inst['recipient']['name'] }}</div>
                                </div>
                                <button type="button"
                                        x-data="{ copied: false }"
                                        @click="navigator.clipboard.writeText('{{ $inst['recipient']['number'] }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                        class="text-xs font-bold rounded-full px-3 py-1.5 bg-white ring-1 ring-cm-green text-cm-green hover:bg-cm-green hover:text-white transition shrink-0">
                                    <span x-show="!copied" x-data x-text="$store.lang.t('Copy','Copier')"></span>
                                    <span x-show="copied" x-cloak x-text="$store.lang.t('Copied ✓','Copié ✓')"></span>
                                </button>
                            </div>
                        </div>
                    @endif

                    @if (! empty($inst['steps']))
                        <ol class="list-decimal pl-5 space-y-1.5 text-[14px] text-slate-700">
                            @foreach ($inst['steps'] as $step)
                                <li>{{ $step }}</li>
                            @endforeach
                        </ol>
                    @endif

                    @if ($status === \App\Enums\OrderStatus::Initiated || $status === \App\Enums\OrderStatus::AwaitingPayment)
                        <div class="space-y-3 pt-2 border-t border-slate-100">
                            @foreach (($inst['fields'] ?? []) as $f)
                                <div>
                                    <label class="block text-[13px] font-semibold text-slate-700 mb-1">
                                        {{ $f['label'] }}
                                        @if (! empty($f['required'])) <span class="text-cm-red">*</span> @endif
                                    </label>
                                    @if (($f['type'] ?? 'text') === 'textarea')
                                        <textarea wire:model="{{ $f['key'] }}" rows="2" maxlength="2000"
                                                  placeholder="{{ $f['placeholder'] ?? '' }}"
                                                  class="w-full rounded-xl ring-1 ring-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-cm-green focus:outline-none"></textarea>
                                    @else
                                        <input type="text" wire:model="{{ $f['key'] }}" maxlength="120"
                                               placeholder="{{ $f['placeholder'] ?? '' }}"
                                               class="w-full rounded-xl ring-1 ring-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-cm-green focus:outline-none">
                                    @endif
                                    @error($f['key']) <p class="text-xs text-cm-red mt-1">{{ $message }}</p> @enderror
                                </div>
                            @endforeach

                            <div class="flex items-center justify-between pt-1">
                                <button type="button" wire:click="cancelOrder"
                                        wire:confirm="{{ app()->getLocale() === 'fr' ? 'Annuler cette commande ?' : 'Cancel this order?' }}"
                                        class="text-sm font-semibold text-cm-red hover:underline">
                                    <span x-data x-text="$store.lang.t('Cancel order','Annuler la commande')"></span>
                                </button>
                                <button type="button" wire:click="submitProof"
                                        class="bg-cm-green hover:bg-cm-green/90 text-white text-sm font-bold rounded-full px-6 py-2.5 shadow">
                                    @if ($status === \App\Enums\OrderStatus::AwaitingPayment)
                                        <span x-data x-text="$store.lang.t('Update reference','Mettre à jour')"></span>
                                    @else
                                        <span x-data x-text="$store.lang.t('I have paid','J\'ai payé')"></span>
                                    @endif
                                </button>
                            </div>
                        </div>
                    @elseif ($status === \App\Enums\OrderStatus::Paid)
                        <div class="p-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 text-sm text-emerald-900">
                            <div class="font-bold mb-1">✓ <span x-data x-text="$store.lang.t('Payment confirmed by seller','Paiement confirmé par le vendeur')"></span></div>
                            <div class="text-[13px]"><span x-data x-text="$store.lang.t('Coordinate handover details in chat.','Organisez la remise via la messagerie.')"></span></div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-3 text-center text-[12px] text-slate-500">
                <a href="{{ route('marketplace.orders') }}" wire:navigate class="hover:text-cm-green hover:underline font-semibold">
                    <span x-data x-text="$store.lang.t('See all my orders','Voir toutes mes commandes')"></span> →
                </a>
            </div>
        @endif
    </div>
</div>
