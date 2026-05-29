<div class="min-h-[calc(100vh-96px)] bg-slate-100">
    <div class="max-w-5xl mx-auto px-3 sm:px-4 py-6">

        {{-- Header --}}
        <div class="flex items-end justify-between mb-4">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">
                    <span x-data x-text="$store.lang.t('Orders','Commandes')"></span>
                </h1>
                <p class="text-sm text-slate-600">
                    <span x-data x-text="$store.lang.t('Track your purchases and your sales.','Suivez vos achats et vos ventes.')"></span>
                </p>
            </div>
            @if ($this->counts['open_selling'] > 0 && $tab !== 'selling')
                <button wire:click="$set('tab','selling')"
                        class="text-xs font-bold bg-cm-red text-white rounded-full px-3 py-1.5 shadow">
                    {{ $this->counts['open_selling'] }} <span x-data x-text="$store.lang.t('need attention','à traiter')"></span>
                </button>
            @endif
        </div>

        {{-- Tabs --}}
        <div class="flex gap-1 bg-white rounded-2xl ring-1 ring-slate-200 p-1 mb-3 shadow-sm">
            <button wire:click="$set('tab','buying')"
                    class="flex-1 text-sm font-bold py-2.5 rounded-xl transition
                    {{ $tab === 'buying' ? 'bg-cm-green text-white shadow' : 'text-slate-700 hover:bg-slate-100' }}">
                <span x-data x-text="$store.lang.t('I bought','J\'ai acheté')"></span>
                <span class="ml-1 text-[11px] opacity-80">({{ $this->counts['buying'] }})</span>
            </button>
            <button wire:click="$set('tab','selling')"
                    class="flex-1 text-sm font-bold py-2.5 rounded-xl transition
                    {{ $tab === 'selling' ? 'bg-cm-green text-white shadow' : 'text-slate-700 hover:bg-slate-100' }}">
                <span x-data x-text="$store.lang.t('I sold','J\'ai vendu')"></span>
                <span class="ml-1 text-[11px] opacity-80">({{ $this->counts['selling'] }})</span>
            </button>
        </div>

        {{-- Status filter --}}
        <div class="flex flex-wrap gap-1.5 mb-4">
            @foreach (['' => 'All', 'awaiting_payment' => 'Awaiting', 'paid' => 'Paid', 'released' => 'Completed', 'disputed' => 'Disputed', 'cancelled' => 'Cancelled'] as $val => $label)
                <button wire:click="$set('status','{{ $val }}')"
                        class="text-[12px] font-semibold rounded-full px-3 py-1 ring-1 transition
                        {{ $status === $val ? 'bg-slate-900 text-white ring-slate-900' : 'bg-white text-slate-700 ring-slate-300 hover:ring-slate-500' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- List --}}
        @if ($this->orders->isEmpty())
            <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 text-center py-16">
                <div class="text-6xl mb-3">🧾</div>
                <div class="text-lg font-bold text-slate-900">
                    <span x-data x-text="$store.lang.t('No orders yet','Aucune commande')"></span>
                </div>
                <p class="text-sm text-slate-500 mt-1">
                    <span x-data x-text="$store.lang.t('Your purchases and sales will show up here.','Vos achats et ventes apparaîtront ici.')"></span>
                </p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($this->orders as $o)
                    @php($mine = $tab === 'buying' ? $o->seller : $o->buyer)
                    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 overflow-hidden">
                        <div class="p-4 flex items-start gap-3">
                            @if ($o->listing?->coverUrl())
                                <img src="{{ $o->listing->coverUrl() }}" alt="" class="w-16 h-16 rounded-xl object-cover ring-1 ring-slate-200 shrink-0">
                            @else
                                <div class="w-16 h-16 rounded-xl bg-slate-200 grid place-items-center text-2xl shrink-0">📦</div>
                            @endif
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <a href="{{ route('marketplace.show', ['slug' => $o->listing?->slug]) }}" wire:navigate
                                       class="font-bold text-slate-900 hover:text-cm-green truncate">{{ $o->listing?->title ?? '—' }}</a>
                                    <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full ring-1 {{ $o->status->chip() }} shrink-0">
                                        {{ app()->getLocale() === 'fr' ? $o->status->labelFr() : $o->status->label() }}
                                    </span>
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span class="font-mono tracking-wider">{{ $o->reference }}</span>
                                    <span>•</span>
                                    <span class="font-bold text-cm-green">{{ $o->formattedAmount() }}</span>
                                    <span>•</span>
                                    <span>{{ $tab === 'buying' ? __('seller:') : __('buyer:') }} <span class="font-semibold text-slate-800">{{ $mine?->name ?: $mine?->username ?: '—' }}</span></span>
                                    <span>•</span>
                                    <span>{{ $o->created_at->diffForHumans() }}</span>
                                </div>
                                @if ($o->provider_ref)
                                    <div class="text-[11px] text-slate-500 mt-1">
                                        Tx: <span class="font-mono text-slate-700">{{ $o->provider_ref }}</span>
                                    </div>
                                @endif
                                @if ($o->buyer_note)
                                    <div class="text-[12px] text-slate-700 mt-1 italic">"{{ $o->buyer_note }}"</div>
                                @endif

                                {{-- Actions --}}
                                <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                                    @if ($tab === 'buying' && in_array($o->status, [\App\Enums\OrderStatus::Initiated, \App\Enums\OrderStatus::AwaitingPayment]))
                                        <a href="{{ route('marketplace.checkout', ['slug' => $o->listing?->slug]) }}" wire:navigate
                                           class="text-xs font-bold bg-cm-green text-white rounded-full px-3 py-1.5 hover:bg-cm-green/90">
                                            <span x-data x-text="$store.lang.t('Continue payment','Continuer le paiement')"></span>
                                        </a>
                                    @endif

                                    @if ($tab === 'selling' && $o->status === \App\Enums\OrderStatus::AwaitingPayment)
                                        <button wire:click="markPaid({{ $o->id }})"
                                                wire:confirm="{{ app()->getLocale() === 'fr' ? 'Confirmer la réception du paiement ?' : 'Confirm you received the payment?' }}"
                                                class="text-xs font-bold bg-cm-green text-white rounded-full px-3 py-1.5 hover:bg-cm-green/90">
                                            ✓ <span x-data x-text="$store.lang.t('Mark as paid','Confirmer paiement')"></span>
                                        </button>
                                    @endif

                                    @if ($o->status === \App\Enums\OrderStatus::Paid)
                                        <button wire:click="release({{ $o->id }})"
                                                wire:confirm="{{ app()->getLocale() === 'fr' ? 'Marquer comme terminé ?' : 'Mark as completed?' }}"
                                                class="text-xs font-bold bg-emerald-600 text-white rounded-full px-3 py-1.5 hover:bg-emerald-700">
                                            <span x-data x-text="$store.lang.t('Mark complete','Marquer terminé')"></span>
                                        </button>
                                    @endif

                                    @if ($o->status->isOpen())
                                        <button wire:click="dispute({{ $o->id }})"
                                                wire:confirm="{{ app()->getLocale() === 'fr' ? 'Ouvrir un litige ?' : 'Open a dispute?' }}"
                                                class="text-xs font-semibold text-cm-red hover:underline px-2 py-1.5">
                                            <span x-data x-text="$store.lang.t('Open dispute','Ouvrir un litige')"></span>
                                        </button>
                                    @endif

                                    @if ($o->status === \App\Enums\OrderStatus::Released && $tab === 'buying' && $o->listing)
                                        <a href="{{ route('marketplace.review', ['slug' => $o->listing->slug]) }}" wire:navigate
                                           class="text-xs font-semibold text-cm-yellow hover:underline px-2 py-1.5">
                                            ★ <span x-data x-text="$store.lang.t('Leave a review','Laisser un avis')"></span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-5">
                {{ $this->orders->links() }}
            </div>
        @endif
    </div>
</div>
