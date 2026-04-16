<div>
    {{-- Floating Kamer Button --}}
    @if(app(App\Services\AIService::class)->isAvailable())
    <button wire:click="toggle"
            class="fixed bottom-6 right-6 z-40 w-14 h-14 bg-gradient-to-br from-cm-green to-cm-green/80 text-white rounded-full shadow-lg hover:shadow-xl hover:scale-105 transition-all duration-200 flex items-center justify-center group
                   {{ $isOpen ? 'max-lg:hidden' : '' }}"
            aria-label="Open Kamer AI">
        <span class="text-2xl group-hover:scale-110 transition-transform">🤖</span>
        {{-- Pulse dot --}}
        <span class="absolute -top-0.5 -right-0.5 w-3 h-3 bg-cm-yellow rounded-full animate-pulse"></span>
    </button>
    @endif

    {{-- Slide-over Panel --}}
    <div x-data="{ show: @entangle('isOpen') }"
         x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="fixed inset-y-0 right-0 z-50 w-full sm:w-96 flex flex-col bg-white shadow-2xl border-l border-slate-200"
         style="display: none;">

        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-cm-green to-cm-green/90 text-white shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center text-lg">🤖</div>
                <div>
                    <h3 class="font-bold text-sm">Kamer AI</h3>
                    <p class="text-[10px] text-white/70" x-text="$store.lang.t('Your community guide', 'Ton guide communautaire')"></p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button wire:click="clearHistory" class="p-2 rounded-lg hover:bg-white/10 transition-colors" title="Clear chat">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
                <button wire:click="close" class="p-2 rounded-lg hover:bg-white/10 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto p-4 space-y-4" id="kamer-messages" wire:poll.keep-alive>
            @foreach($messages as $i => $msg)
                @if($msg['role'] === 'assistant')
                    {{-- Kamer message --}}
                    <div class="flex gap-2.5 max-w-[90%]" wire:key="kamer-msg-{{ $i }}">
                        <div class="w-7 h-7 rounded-full bg-cm-green/10 flex items-center justify-center text-sm shrink-0 mt-0.5">🤖</div>
                        <div class="bg-slate-50 rounded-2xl rounded-tl-sm px-3.5 py-2.5 text-sm text-slate-700 leading-relaxed">
                            {!! nl2br(e($msg['content'])) !!}
                        </div>
                    </div>
                @else
                    {{-- User message --}}
                    <div class="flex justify-end" wire:key="kamer-msg-{{ $i }}">
                        <div class="max-w-[80%] bg-cm-green text-white rounded-2xl rounded-tr-sm px-3.5 py-2.5 text-sm leading-relaxed">
                            {!! nl2br(e($msg['content'])) !!}
                        </div>
                    </div>
                @endif
            @endforeach

            {{-- Loading indicator --}}
            @if($isLoading)
            <div class="flex gap-2.5 max-w-[90%]">
                <div class="w-7 h-7 rounded-full bg-cm-green/10 flex items-center justify-center text-sm shrink-0 mt-0.5">🤖</div>
                <div class="bg-slate-50 rounded-2xl rounded-tl-sm px-4 py-3">
                    <div class="flex gap-1">
                        <span class="w-2 h-2 bg-cm-green/40 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                        <span class="w-2 h-2 bg-cm-green/40 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                        <span class="w-2 h-2 bg-cm-green/40 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- Quick Actions (shown when few messages) --}}
        @if(count($messages) <= 2)
        <div class="px-4 pb-2 shrink-0">
            <div class="flex flex-wrap gap-2">
                @php
                    $suggestions = auth()->user()?->language_pref === 'fr'
                        ? ['Que puis-je faire ici ?', 'Comment fonctionne la Solidarité ?', 'Aide-moi à trouver des Camerounais près de moi']
                        : ['What can I do here?', 'How does Solidarity work?', 'Help me find Cameroonians near me'];
                @endphp
                @foreach($suggestions as $s)
                <button wire:click="$set('input', '{{ addslashes($s) }}'); $wire.send()"
                        class="px-3 py-1.5 text-xs bg-cm-green/5 text-cm-green rounded-full border border-cm-green/20 hover:bg-cm-green/10 transition-colors">
                    {{ $s }}
                </button>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Input --}}
        <div class="p-3 border-t border-slate-200 bg-white shrink-0">
            <form wire:submit.prevent="send" class="flex items-end gap-2">
                <div class="flex-1 relative">
                    <textarea wire:model="input"
                              rows="1"
                              class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-2.5 text-sm resize-none focus:ring-cm-green focus:border-cm-green focus:bg-white transition-colors"
                              placeholder="{{ auth()->user()?->language_pref === 'fr' ? 'Demandez à Kamer...' : 'Ask Kamer anything...' }}"
                              @keydown.enter.prevent="if (!event.shiftKey) { $wire.send() }"
                              x-data
                              x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 120) + 'px'"
                              {{ $isLoading ? 'disabled' : '' }}></textarea>
                </div>
                <button type="submit"
                        class="w-10 h-10 rounded-xl bg-cm-green text-white flex items-center justify-center hover:bg-cm-green/90 transition-colors shrink-0 disabled:opacity-50"
                        {{ $isLoading || trim($input) === '' ? 'disabled' : '' }}>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19V5m0 0l-7 7m7-7l7 7" transform="rotate(90 12 12)"/>
                    </svg>
                </button>
            </form>
            <p class="text-[10px] text-slate-400 mt-1 text-center" x-text="$store.lang.t('Kamer AI · Powered by the community spirit 🇨🇲', 'Kamer IA · Propulsé par l\'esprit communautaire 🇨🇲')"></p>
        </div>
    </div>

    {{-- Backdrop for mobile --}}
    <div x-data="{ show: @entangle('isOpen') }"
         x-show="show"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="$wire.close()"
         class="fixed inset-0 bg-black/30 z-40 sm:hidden"
         style="display: none;"></div>

    {{-- Auto-scroll script --}}
    <script>
        document.addEventListener('livewire:morph', () => {
            const el = document.getElementById('kamer-messages');
            if (el) el.scrollTop = el.scrollHeight;
        });
    </script>
</div>
