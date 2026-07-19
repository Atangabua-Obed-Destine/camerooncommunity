{{-- GoMarket Inbox — Facebook-Marketplace style (Buying / Selling, list + thread) --}}
@php
    $lang = app()->getLocale();
    $me = auth()->id();
    $convs = $this->conversations;
    $active = $this->active;
    $activeId = $this->resolvedActiveId();
    $counts = $this->counts;
    $channel = $this->channelName();
    $aListing = $active?->listing;
    $aPartner = $active?->partner;
    $pName = $aPartner ? ($aPartner->username ?? $aPartner->name) : __('Seller');
@endphp
<div class="min-h-[calc(100vh-96px)] bg-slate-100"
     x-data="{ scrollDown() { $nextTick(() => { const t = $refs.thread; if (t) t.scrollTop = t.scrollHeight; }); } }"
     x-init="scrollDown()"
     @inbox-scroll.window="scrollDown()">
    <div class="max-w-6xl mx-auto px-2 sm:px-4 py-4">

        {{-- Realtime: refresh the open thread on new messages --}}
        @if ($channel)
            <div wire:key="inbox-echo-{{ $activeId }}"
                 x-data="{ ch: @js($channel), c: null, h: null,
                    init() { if (!window.Echo || !this.ch) return; this.c = window.Echo.channel(this.ch); this.h = () => $wire.dispatch('inbox-refresh'); this.c.listen('.MessageSent', this.h); },
                    destroy() { try { this.c?.stopListening('.MessageSent', this.h); } catch(e){} } }"></div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 overflow-hidden lg:grid lg:grid-cols-[340px_1fr]"
             style="height: calc(100vh - 150px); min-height: 480px;">

            {{-- ─── Conversation list ─── --}}
            <aside class="flex-col border-r border-slate-200 min-h-0 {{ $this->threadOpen ? 'hidden lg:flex' : 'flex' }}">
                <div class="px-4 pt-4 pb-2 shrink-0">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('marketplace.index') }}" class="w-8 h-8 grid place-items-center rounded-full hover:bg-slate-100 text-slate-600 transition" title="{{ $lang === 'fr' ? 'Retour' : 'Back' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        </a>
                        <h1 class="text-xl font-extrabold text-slate-900">{{ $lang === 'fr' ? 'Boîte de réception' : 'Inbox' }}</h1>
                    </div>
                    <div class="mt-3 inline-flex rounded-full bg-slate-100 p-0.5 text-sm font-semibold">
                        <button wire:click="$set('tab','buying')"
                                class="px-4 py-1.5 rounded-full transition {{ $this->tab === 'buying' ? 'bg-white text-cm-green shadow' : 'text-slate-600 hover:text-slate-900' }}">
                            {{ $lang === 'fr' ? 'Achats' : 'Buying' }}
                            @if (($counts['buying'] ?? 0) > 0)<span class="ml-1 text-[10px] font-bold bg-cm-red text-white rounded-full px-1.5 py-0.5">{{ $counts['buying'] }}</span>@endif
                        </button>
                        <button wire:click="$set('tab','selling')"
                                class="px-4 py-1.5 rounded-full transition {{ $this->tab === 'selling' ? 'bg-white text-cm-green shadow' : 'text-slate-600 hover:text-slate-900' }}">
                            {{ $lang === 'fr' ? 'Ventes' : 'Selling' }}
                            @if (($counts['selling'] ?? 0) > 0)<span class="ml-1 text-[10px] font-bold bg-cm-red text-white rounded-full px-1.5 py-0.5">{{ $counts['selling'] }}</span>@endif
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto chat-scroll min-h-0">
                    @forelse ($convs as $c)
                        @php($isActive = $activeId === $c->room_id)
                        <button type="button" wire:click="selectRoom({{ $c->room_id }})"
                                class="w-full flex items-center gap-3 px-3 py-3 text-left border-b border-slate-100 transition {{ $isActive ? 'bg-cm-green/5' : 'hover:bg-slate-50' }}">
                            <div class="relative w-12 h-12 rounded-lg overflow-hidden bg-slate-200 grid place-items-center shrink-0">
                                @if ($c->listing && $c->listing->coverUrl())
                                    <img src="{{ $c->listing->coverUrl() }}" alt="" class="w-full h-full object-cover">
                                @elseif ($c->partner?->avatar)
                                    <img src="{{ asset('storage/' . $c->partner->avatar) }}" alt="" class="w-full h-full object-cover rounded-full">
                                @else
                                    <span class="text-lg">🛍️</span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-[14px] font-bold text-slate-900 truncate">{{ $c->partner?->name ?: $c->partner?->username ?: __('User') }}</span>
                                    <span class="text-[10px] text-slate-400 shrink-0">{{ $c->time ? \Illuminate\Support\Carbon::parse($c->time)->diffForHumans(null, true) : '' }}</span>
                                </div>
                                @if ($c->listing)
                                    <div class="text-[11px] text-cm-green font-semibold truncate">{{ $c->listing->title }}</div>
                                @endif
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-[12px] text-slate-500 truncate {{ $c->unread > 0 ? 'font-semibold text-slate-700' : '' }}">{{ $c->preview ?: __('No messages yet') }}</span>
                                    @if ($c->unread > 0)<span class="shrink-0 w-2 h-2 rounded-full bg-cm-red"></span>@endif
                                </div>
                            </div>
                        </button>
                    @empty
                        <div class="text-center py-16 px-6">
                            <div class="text-4xl mb-2">💬</div>
                            <div class="font-bold text-slate-900 text-sm">{{ $lang === 'fr' ? 'Aucune conversation' : 'No conversations yet' }}</div>
                            <p class="text-[12px] text-slate-500 mt-1">{{ $this->tab === 'selling'
                                ? ($lang === 'fr' ? 'Les messages des acheteurs apparaîtront ici.' : 'Messages from buyers will appear here.')
                                : ($lang === 'fr' ? 'Contactez un vendeur pour démarrer.' : 'Message a seller to start a chat.') }}</p>
                        </div>
                    @endforelse
                </div>
            </aside>

            {{-- ─── Thread ─── --}}
            <section class="flex-col min-h-0 {{ $this->threadOpen ? 'flex' : 'hidden lg:flex' }}">
                @if ($active)
                    {{-- Header --}}
                    <div class="shrink-0 border-b border-slate-200">
                        <div class="flex items-center gap-2 px-3 py-2.5">
                            <button type="button" wire:click="backToList" class="lg:hidden w-8 h-8 grid place-items-center rounded-full hover:bg-slate-100 text-slate-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <div class="w-9 h-9 rounded-full bg-cm-green/15 grid place-items-center text-cm-green font-bold overflow-hidden shrink-0">
                                @if ($aPartner?->avatar)<img src="{{ asset('storage/' . $aPartner->avatar) }}" alt="" class="w-full h-full object-cover">@else{{ strtoupper(mb_substr($pName,0,1)) }}@endif
                            </div>
                            <div class="min-w-0 flex-1">
                                @if ($aPartner && $aPartner->username)
                                    <a href="{{ route('marketplace.seller', ['username' => $aPartner->username]) }}" wire:navigate class="font-bold text-slate-900 text-sm truncate hover:text-cm-green block">{{ $pName }}</a>
                                @else
                                    <div class="font-bold text-slate-900 text-sm truncate">{{ $pName }}</div>
                                @endif
                                <div class="text-[11px] text-slate-400">{{ $active->role === 'selling' ? ($lang === 'fr' ? 'Acheteur' : 'Buyer') : ($lang === 'fr' ? 'Vendeur' : 'Seller') }}</div>
                            </div>
                        </div>
                        @if ($aListing)
                            <a href="{{ route('marketplace.show', ['slug' => $aListing->slug]) }}" wire:navigate
                               class="flex items-center gap-2.5 px-3 py-2 bg-slate-50 hover:bg-slate-100 border-t border-slate-100 transition">
                                @if ($aListing->coverUrl())
                                    <img src="{{ $aListing->coverUrl() }}" alt="" class="w-10 h-10 rounded-lg object-cover ring-1 ring-slate-200 shrink-0">
                                @else
                                    <div class="w-10 h-10 rounded-lg bg-slate-200 grid place-items-center shrink-0">🛍️</div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="text-[13px] font-bold text-slate-900 truncate">{{ $aListing->formattedPrice($lang) }}</div>
                                    <div class="text-[12px] text-slate-600 truncate">{{ $aListing->title }}</div>
                                </div>
                                <span class="text-[11px] font-semibold text-cm-green shrink-0">{{ $lang === 'fr' ? 'Voir' : 'See details' }}</span>
                            </a>
                        @endif
                    </div>

                    {{-- Messages --}}
                    <div x-ref="thread" class="flex-1 overflow-y-auto px-4 py-4 space-y-2 bg-slate-50 chat-scroll min-h-0">
                        @forelse ($this->messages as $m)
                            @php($own = (int) $m->user_id === (int) $me)
                            @php($card = $this->cardPreview($m))
                            @if ($card)
                                <div class="flex {{ $own ? 'justify-end' : 'justify-start' }}">
                                    <a href="{{ $card['url'] ?? '#' }}" wire:navigate class="w-[60%] max-w-xs rounded-xl bg-white ring-1 ring-slate-200 overflow-hidden hover:ring-cm-green/50 transition">
                                        @if (! empty($card['image']))<img src="{{ $card['image'] }}" alt="" class="w-full h-28 object-cover">@endif
                                        <div class="p-2">
                                            <div class="text-[13px] font-bold text-cm-green">{{ $card['price_label'] ?? '' }}</div>
                                            <div class="text-[12px] text-slate-700 truncate">{{ $card['title'] ?? '' }}</div>
                                        </div>
                                    </a>
                                </div>
                            @else
                                <div class="flex {{ $own ? 'justify-end' : 'justify-start' }}">
                                    <div class="max-w-[70%] px-3.5 py-2 rounded-2xl text-[14px] leading-snug whitespace-pre-line break-words {{ $own ? 'bg-cm-green text-white rounded-br-md' : 'bg-white text-slate-800 ring-1 ring-slate-200 rounded-bl-md' }}">{{ $m->content }}</div>
                                </div>
                            @endif
                        @empty
                            <div class="text-center text-[13px] text-slate-400 py-10">{{ $lang === 'fr' ? 'Démarrez la conversation.' : 'Start the conversation.' }}</div>
                        @endforelse
                    </div>

                    {{-- Composer --}}
                    <form wire:submit="send" class="shrink-0 flex items-center gap-2 p-3 border-t border-slate-200 bg-white">
                        <input type="text" wire:model="newMessage" maxlength="4000"
                               placeholder="{{ $lang === 'fr' ? 'Écrivez un message…' : 'Write a message…' }}"
                               class="flex-1 rounded-full bg-slate-100 border-0 px-4 py-2.5 text-sm text-slate-900 placeholder-slate-500 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
                        <button type="submit" wire:loading.attr="disabled" wire:target="send"
                                class="w-10 h-10 grid place-items-center rounded-full bg-cm-green text-white hover:bg-cm-green/90 transition shrink-0 disabled:opacity-50">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                        </button>
                    </form>
                @else
                    <div class="hidden lg:flex flex-1 flex-col items-center justify-center text-center p-8">
                        <div class="text-5xl mb-3">📨</div>
                        <div class="font-bold text-slate-900">{{ $lang === 'fr' ? 'Sélectionnez une conversation' : 'Select a conversation' }}</div>
                        <p class="text-sm text-slate-500 mt-1">{{ $lang === 'fr' ? 'Vos discussions GoMarket apparaissent ici.' : 'Your GoMarket chats appear here.' }}</p>
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
