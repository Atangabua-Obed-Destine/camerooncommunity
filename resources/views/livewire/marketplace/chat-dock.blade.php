{{-- Facebook-Marketplace-style floating chat dock (app-wide) --}}
<div>
@if ($open && $this->room)
    @php
        $lang = app()->getLocale();
        $partner = $this->partner;
        $listing = $this->listing;
        $channel = $this->channelName();
        $pName = $partner ? ($partner->username ?? $partner->name) : __('Seller');
    @endphp

    <div class="fixed z-[70] bottom-0 right-0 sm:right-4 sm:bottom-4 w-full sm:w-[348px]"
         x-data="{
            scrollDown() { $nextTick(() => { const t = $refs.thread; if (t) t.scrollTop = t.scrollHeight; }); }
         }"
         x-init="scrollDown()"
         @gomarket-scroll.window="scrollDown()">

        {{-- Realtime: re-subscribe whenever the room changes (wire:key swaps the node) --}}
        <div wire:key="dock-echo-{{ $this->roomId }}"
             x-data="{
                ch: @js($channel), c: null, h: null,
                init() {
                    if (!window.Echo || !this.ch) return;
                    this.c = window.Echo.channel(this.ch);
                    this.h = () => $wire.refreshThread();
                    this.c.listen('.MessageSent', this.h);
                },
                destroy() { try { this.c?.stopListening('.MessageSent', this.h); } catch(e){} }
             }"></div>

        <div class="bg-white rounded-t-xl sm:rounded-xl shadow-2xl ring-1 ring-slate-200 overflow-hidden flex flex-col"
             style="max-height: 70vh;">

            {{-- ── Header ── --}}
            <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-r from-cm-green to-cm-green-light text-white">
                <div class="w-8 h-8 rounded-full bg-white/20 grid place-items-center overflow-hidden shrink-0">
                    @if ($partner?->avatar)
                        <img src="{{ asset('storage/' . $partner->avatar) }}" alt="" class="w-full h-full object-cover">
                    @else
                        <span class="text-sm font-bold">{{ strtoupper(mb_substr($pName, 0, 1)) }}</span>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    @if ($partner && $partner->username)
                        <a href="{{ route('marketplace.seller', ['username' => $partner->username]) }}" wire:navigate
                           class="block font-bold text-sm truncate hover:underline">{{ $pName }}</a>
                    @else
                        <div class="font-bold text-sm truncate">{{ $pName }}</div>
                    @endif
                    <div class="text-[11px] text-white/80 truncate">{{ $lang === 'fr' ? 'Marketplace' : 'Marketplace' }}</div>
                </div>
                <button type="button" wire:click="toggleMinimize" title="{{ $lang === 'fr' ? 'Réduire' : 'Minimize' }}"
                        class="w-7 h-7 grid place-items-center rounded-full hover:bg-white/15 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 12h14"/></svg>
                </button>
                <button type="button" wire:click="close" title="{{ $lang === 'fr' ? 'Fermer' : 'Close' }}"
                        class="w-7 h-7 grid place-items-center rounded-full hover:bg-white/15 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            @if (! $minimized)
                {{-- ── Pinned product header ── --}}
                @if ($listing)
                    <a href="{{ route('marketplace.show', ['slug' => $listing->slug]) }}" wire:navigate
                       class="flex items-center gap-2.5 px-3 py-2 border-b border-slate-100 hover:bg-slate-50 transition">
                        @if ($listing->coverUrl())
                            <img src="{{ $listing->coverUrl() }}" alt="" class="w-11 h-11 rounded-lg object-cover ring-1 ring-slate-200 shrink-0">
                        @else
                            <div class="w-11 h-11 rounded-lg bg-slate-200 grid place-items-center text-lg shrink-0">🛍️</div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="text-[13px] font-bold text-slate-900 truncate">{{ $listing->formattedPrice($lang) }}</div>
                            <div class="text-[12px] text-slate-600 truncate">{{ $listing->title }}</div>
                        </div>
                        <span class="text-[11px] font-semibold text-cm-green shrink-0">{{ $lang === 'fr' ? 'Voir' : 'See details' }}</span>
                    </a>
                @endif

                {{-- ── Thread ── --}}
                <div x-ref="thread" class="flex-1 overflow-y-auto px-3 py-3 space-y-2 bg-slate-50 chat-scroll" style="min-height: 220px;">
                    @forelse ($this->messages as $m)
                        @php($own = (int) $m->user_id === (int) auth()->id())
                        @php($card = $this->cardPreview($m))
                        @if ($card)
                            {{-- Shared product card in-thread --}}
                            <div class="flex {{ $own ? 'justify-end' : 'justify-start' }}">
                                <a href="{{ $card['url'] ?? '#' }}" wire:navigate
                                   class="w-[78%] rounded-xl bg-white ring-1 ring-slate-200 overflow-hidden hover:ring-cm-green/50 transition">
                                    @if (! empty($card['image']))
                                        <img src="{{ $card['image'] }}" alt="" class="w-full h-28 object-cover">
                                    @endif
                                    <div class="p-2">
                                        <div class="text-[13px] font-bold text-cm-green">{{ $card['price_label'] ?? '' }}</div>
                                        <div class="text-[12px] text-slate-700 truncate">{{ $card['title'] ?? '' }}</div>
                                    </div>
                                </a>
                            </div>
                        @else
                            <div class="flex {{ $own ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[78%] px-3 py-1.5 rounded-2xl text-[13.5px] leading-snug whitespace-pre-line break-words
                                    {{ $own ? 'bg-cm-green text-white rounded-br-md' : 'bg-white text-slate-800 ring-1 ring-slate-200 rounded-bl-md' }}">
                                    {{ $m->content }}
                                </div>
                            </div>
                        @endif
                    @empty
                        <div class="text-center text-[12px] text-slate-400 py-6">
                            {{ $lang === 'fr' ? 'Démarrez la conversation.' : 'Start the conversation.' }}
                        </div>
                    @endforelse
                </div>

                {{-- ── Composer ── --}}
                <form wire:submit="send" class="flex items-center gap-2 p-2 border-t border-slate-100 bg-white">
                    <input type="text" wire:model="newMessage" maxlength="4000"
                           placeholder="{{ $lang === 'fr' ? 'Écrivez un message…' : 'Write a message…' }}"
                           class="flex-1 rounded-full bg-slate-100 border-0 px-3.5 py-2 text-sm text-slate-900 placeholder-slate-500 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
                    <button type="submit"
                            class="w-9 h-9 grid place-items-center rounded-full bg-cm-green text-white hover:bg-cm-green/90 transition shrink-0 disabled:opacity-50"
                            wire:loading.attr="disabled" wire:target="send">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    </button>
                </form>
            @endif
        </div>
    </div>
@endif
</div>
