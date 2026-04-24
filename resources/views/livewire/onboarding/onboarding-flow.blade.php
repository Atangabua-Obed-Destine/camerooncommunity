<div class="min-h-[calc(100vh-4rem)] flex items-center justify-center py-8 px-4"
     x-data="onboardingFlow()"
     x-init="$nextTick(() => typewriterInit())">

    <div class="w-full max-w-2xl">

        {{-- ═══ Progress Bar ═══ --}}
        <div class="flex items-center justify-center gap-2 mb-8">
            @for($i = 1; $i <= 4; $i++)
            <button wire:click="goToStep({{ $i }})"
                    class="h-2 rounded-full transition-all duration-500 cursor-pointer"
                    :class="{
                        'w-10 bg-cm-green': {{ $i }} === {{ $step }},
                        'w-6 bg-cm-green/40': {{ $i }} < {{ $step }},
                        'w-2 bg-slate-300': {{ $i }} > {{ $step }}
                    }">
            </button>
            @endfor
        </div>

        {{-- Step labels --}}
        <div class="flex justify-between px-2 mb-6">
            @php
                $labels = [
                    1 => ['en' => '🤖 Chat with Kamer', 'fr' => '🤖 Parler à Kamer'],
                    2 => ['en' => '🏠 Discover', 'fr' => '🏠 Découvrir'],
                    3 => ['en' => '✨ Profile', 'fr' => '✨ Profil'],
                    4 => ['en' => '🚀 Launch', 'fr' => '🚀 Lancer'],
                ];
            @endphp
            @foreach($labels as $num => $text)
            <span class="text-[11px] font-medium transition-colors duration-300 {{ $step >= $num ? 'text-cm-green' : 'text-slate-400' }}"
                  x-text="$store.lang.t('{{ $text['en'] }}', '{{ $text['fr'] }}')">
            </span>
            @endforeach
        </div>

        {{-- ═══════════════════════════════════════════════ --}}
        {{-- STEP 1: Kamer AI Conversational Welcome --}}
        {{-- ═══════════════════════════════════════════════ --}}
        @if($step === 1)
        <div x-transition:enter="transition ease-out duration-400"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">

            {{-- Chat header --}}
            <div class="bg-gradient-to-r from-cm-green to-blue-700 px-6 py-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center backdrop-blur-sm">
                    <span class="text-xl">🤖</span>
                </div>
                <div>
                    <h2 class="text-white font-bold text-lg">Kamer AI</h2>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-blue-300 animate-pulse"></span>
                        <span class="text-white/70 text-xs" x-text="$store.lang.t('Your personal guide', 'Votre guide personnel')"></span>
                    </div>
                </div>
            </div>

            {{-- Chat messages --}}
            <div class="h-72 overflow-y-auto px-6 py-4 space-y-4 scroll-smooth" id="chat-container"
                 x-ref="chatContainer">
                @foreach($chatMessages as $index => $msg)
                <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }} animate-fade-in"
                     wire:key="msg-{{ $index }}">
                    @if($msg['role'] === 'assistant')
                    <div class="flex items-start gap-2 max-w-[85%]">
                        <div class="w-7 h-7 rounded-full bg-cm-green/10 flex items-center justify-center shrink-0 mt-1">
                            <span class="text-sm">🤖</span>
                        </div>
                        <div class="bg-slate-50 rounded-2xl rounded-tl-md px-4 py-3 text-sm text-slate-700 leading-relaxed prose prose-sm"
                             x-data x-html="(window.marked && (window.marked.parse ? window.marked.parse(@js($msg['content'])) : window.marked(@js($msg['content'])))) || @js($msg['content'])">
                        </div>
                    </div>
                    @else
                    <div class="max-w-[80%]">
                        <div class="bg-cm-green text-white rounded-2xl rounded-tr-md px-4 py-3 text-sm leading-relaxed">
                            {{ $msg['content'] }}
                        </div>
                    </div>
                    @endif
                </div>
                @endforeach

                {{-- Typing indicator --}}
                @if($chatLoading)
                <div class="flex justify-start animate-fade-in">
                    <div class="flex items-start gap-2">
                        <div class="w-7 h-7 rounded-full bg-cm-green/10 flex items-center justify-center shrink-0 mt-1">
                            <span class="text-sm">🤖</span>
                        </div>
                        <div class="bg-slate-50 rounded-2xl rounded-tl-md px-4 py-3">
                            <div class="flex gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-slate-400 animate-bounce" style="animation-delay: 0ms"></span>
                                <span class="w-2 h-2 rounded-full bg-slate-400 animate-bounce" style="animation-delay: 150ms"></span>
                                <span class="w-2 h-2 rounded-full bg-slate-400 animate-bounce" style="animation-delay: 300ms"></span>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Quick reply chips --}}
            @if(count($chatMessages) <= 2)
            <div class="px-6 pb-3 flex flex-wrap gap-2">
                @php
                    $chips = [
                        ['en' => '🤝 Connect with Cameroonians near me', 'fr' => '🤝 Retrouver des Camerounais près de moi'],
                        ['en' => '🤲 Support the community', 'fr' => '🤲 Soutenir la communauté'],
                        ['en' => '🔍 Just exploring', 'fr' => '🔍 Je découvre'],
                    ];
                @endphp
                @foreach($chips as $chip)
                <button wire:click="sendChat('{{ $chip['en'] }}')"
                        wire:loading.attr="disabled"
                        wire:target="sendChat"
                        class="text-xs bg-cm-green/5 text-cm-green font-medium rounded-full px-4 py-2 border border-cm-green/20 hover:bg-cm-green/10 hover:border-cm-green/40 transition-all duration-200 disabled:opacity-50"
                        x-text="$store.lang.t('{{ $chip['en'] }}', '{{ $chip['fr'] }}')">
                </button>
                @endforeach
            </div>
            @endif

            {{-- Chat input --}}
            <div class="border-t border-slate-100 px-4 py-3">
                <form wire:submit="sendChat" class="flex items-center gap-2">
                    <input type="text"
                           wire:model="chatInput"
                           placeholder="{{ auth()->user()->language_pref?->value === 'fr' ? 'Écrivez à Kamer...' : 'Type a message to Kamer...' }}"
                           class="flex-1 rounded-full border-slate-200 bg-slate-50 px-4 py-2.5 text-sm focus:ring-cm-green focus:border-cm-green placeholder:text-slate-400"
                           autocomplete="off">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="sendChat"
                            class="w-10 h-10 rounded-full bg-cm-green text-white flex items-center justify-center hover:bg-cm-green/90 transition-colors disabled:opacity-50 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    </button>
                </form>
            </div>

            {{-- Continue button --}}
            <div class="border-t border-slate-100 px-6 py-4 flex justify-between items-center bg-slate-50/50">
                <p class="text-[11px] text-slate-400" x-text="$store.lang.t('You can always chat with Kamer later ✨',  'Vous pourrez reparler à Kamer plus tard ✨')"></p>
                <button wire:click="nextStep"
                        class="rounded-xl bg-cm-green px-6 py-2.5 text-sm font-bold text-white hover:bg-cm-green/90 transition-all duration-200 flex items-center gap-2 shadow-lg shadow-cm-green/20">
                    <span x-text="$store.lang.t('Discover Communities', 'Découvrir les Communautés')"></span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </button>
            </div>
        </div>
        @endif

        {{-- ═══════════════════════════════════════════════ --}}
        {{-- STEP 2: Community Discovery (WhatsApp-style) --}}
        {{-- ═══════════════════════════════════════════════ --}}
        @if($step === 2)
        <div x-transition:enter="transition ease-out duration-400"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">

            {{-- Header --}}
            <div class="relative bg-gradient-to-br from-blue-600 to-indigo-700 px-8 py-6 text-white text-center overflow-hidden">
                <div class="absolute inset-0 opacity-10">
                    <svg class="w-full h-full" viewBox="0 0 800 300" fill="none">
                        <circle cx="150" cy="60" r="100" fill="currentColor"/>
                        <circle cx="650" cy="220" r="120" fill="currentColor"/>
                    </svg>
                </div>
                <div class="relative">
                    <div class="text-4xl mb-3">🏠</div>
                    <h2 class="text-2xl font-bold mb-1" x-text="$store.lang.t('Your Communities', 'Vos Communautés')"></h2>
                    <p class="text-white/70 text-sm" x-text="$store.lang.t(
                        'Choose which rooms to join — just like WhatsApp groups, you decide.',
                        'Choisissez les salles à rejoindre — comme les groupes WhatsApp, c\'est vous qui décidez.'
                    )"></p>
                </div>
            </div>

            <div class="p-6 space-y-4">
                {{-- Country badge --}}
                <div class="flex items-center gap-3 bg-slate-50 rounded-xl p-3">
                    <div class="w-9 h-9 rounded-full bg-cm-green/10 flex items-center justify-center">
                        <span class="text-base">📍</span>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-slate-800">{{ auth()->user()->current_country }}</p>
                        @if(auth()->user()->current_region)
                        <p class="text-xs text-slate-500">{{ auth()->user()->current_region }}</p>
                        @endif
                    </div>
                    <span class="text-xs text-cm-green font-medium bg-cm-green/10 rounded-full px-2.5 py-1">
                        {{ $this->memberCount }} <span x-text="$store.lang.t('Cameroonians', 'Camerounais')"></span>
                    </span>
                </div>

                {{-- Discoverable rooms --}}
                @forelse($this->discoverableRooms as $room)
                <div wire:key="discover-{{ $room->id }}"
                     class="group border-2 rounded-xl p-4 transition-all duration-300 cursor-pointer
                            {{ in_array($room->id, $selectedRoomIds) ? 'border-cm-green bg-cm-green/5 shadow-md shadow-cm-green/10' : 'border-slate-200 hover:border-slate-300 bg-white' }}"
                     wire:click="toggleRoom({{ $room->id }})">

                    <div class="flex items-center gap-4">
                        {{-- Room icon --}}
                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center shrink-0 transition-transform duration-300 group-hover:scale-105
                             {{ $room->room_type === \App\Enums\RoomType::National ? 'bg-cm-green/10' : 'bg-blue-500/10' }}">
                            <span class="text-2xl">{{ $room->room_type === \App\Enums\RoomType::National ? '🇨🇲' : '📍' }}</span>
                        </div>

                        {{-- Room info --}}
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-slate-900">{{ $room->name }}</h3>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $room->description }}</p>
                            <div class="flex items-center gap-3 mt-2">
                                <span class="text-[11px] text-slate-400 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    {{ number_format($room->members_count) }} <span x-text="$store.lang.t('members', 'membres')"></span>
                                </span>
                                @if($room->last_message_at)
                                <span class="text-[11px] text-slate-400 flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                    <span x-text="$store.lang.t('Active', 'Actif')"></span>
                                </span>
                                @endif
                            </div>
                        </div>

                        {{-- Checkbox --}}
                        <div class="shrink-0">
                            <div class="w-7 h-7 rounded-full border-2 flex items-center justify-center transition-all duration-300
                                 {{ in_array($room->id, $selectedRoomIds) ? 'border-cm-green bg-cm-green' : 'border-slate-300 group-hover:border-slate-400' }}">
                                @if(in_array($room->id, $selectedRoomIds))
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-8">
                    <div class="text-4xl mb-3">🌍</div>
                    <p class="text-sm text-slate-500" x-text="$store.lang.t(
                        'No communities found for your location yet. You can discover more in The Yard!',
                        'Aucune communauté trouvée pour votre localisation. Vous en trouverez plus dans le Yard !'
                    )"></p>
                </div>
                @endforelse

                {{-- Info tip --}}
                @if($this->discoverableRooms->count())
                <div class="flex items-start gap-2 bg-blue-50 rounded-xl p-3 border border-blue-100">
                    <span class="text-base shrink-0">💡</span>
                    <p class="text-[11px] text-blue-700 leading-relaxed" x-text="$store.lang.t(
                        'Tap a room card to select it. You\'ll be able to discover more rooms later in The Yard sidebar.',
                        'Appuyez sur une carte pour la sélectionner. Vous pourrez découvrir plus de salles dans la barre latérale du Yard.'
                    )"></p>
                </div>
                @endif
            </div>

            {{-- Actions --}}
            <div class="border-t border-slate-100 px-6 py-4 flex justify-between items-center bg-slate-50/50">
                <button wire:click="skipRooms"
                        class="text-sm text-slate-500 hover:text-slate-700 transition-colors"
                        x-text="$store.lang.t('Skip for now', 'Passer pour l\'instant')">
                </button>
                <button wire:click="joinSelectedRooms"
                        @if(empty($selectedRoomIds)) disabled @endif
                        class="rounded-xl bg-cm-green px-6 py-2.5 text-sm font-bold text-white hover:bg-cm-green/90 transition-all duration-200 flex items-center gap-2 shadow-lg shadow-cm-green/20 disabled:opacity-40 disabled:cursor-not-allowed disabled:shadow-none">
                    @if(count($selectedRoomIds) > 0)
                        <span x-text="$store.lang.t('Join {{ count($selectedRoomIds) }} Room{{ count($selectedRoomIds) > 1 ? 's' : '' }}', 'Rejoindre {{ count($selectedRoomIds) }} Salle{{ count($selectedRoomIds) > 1 ? 's' : '' }}')"></span>
                    @else
                        <span x-text="$store.lang.t('Select rooms to join', 'Sélectionnez des salles')"></span>
                    @endif
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </button>
            </div>
        </div>
        @endif

        {{-- ═══════════════════════════════════════════════ --}}
        {{-- STEP 3: Profile Polish + Founding Member --}}
        {{-- ═══════════════════════════════════════════════ --}}
        @if($step === 3)
        <div x-transition:enter="transition ease-out duration-400"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">

            {{-- Header --}}
            <div class="relative bg-gradient-to-br from-violet-600 to-purple-700 px-8 py-6 text-white text-center overflow-hidden">
                <div class="absolute inset-0 opacity-10">
                    <svg class="w-full h-full" viewBox="0 0 800 300" fill="none">
                        <circle cx="200" cy="100" r="80" fill="currentColor"/>
                        <circle cx="600" cy="200" r="100" fill="currentColor"/>
                    </svg>
                </div>
                <div class="relative">
                    <div class="text-4xl mb-3">✨</div>
                    <h2 class="text-2xl font-bold mb-1" x-text="$store.lang.t('Make it yours', 'Personnalisez')"></h2>
                    <p class="text-white/70 text-sm" x-text="$store.lang.t(
                        'Help others know a little about you.',
                        'Aidez les autres à vous connaître un peu.'
                    )"></p>
                </div>
            </div>

            <div class="p-6 space-y-5">
                {{-- Founding Member Badge --}}
                @if(auth()->user()->is_founding_member)
                <div class="relative bg-gradient-to-br from-amber-50 to-yellow-50 border border-amber-200/50 rounded-2xl p-6 text-center overflow-hidden"
                     x-data="{ revealed: false }" x-init="setTimeout(() => revealed = true, 300)">

                    {{-- Sparkle effect --}}
                    <div x-show="revealed" x-transition:enter="transition ease-out duration-700"
                         x-transition:enter-start="opacity-0 scale-50"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="absolute inset-0 pointer-events-none">
                        <div class="absolute top-2 left-4 w-2 h-2 bg-amber-400 rounded-full animate-ping"></div>
                        <div class="absolute top-6 right-8 w-1.5 h-1.5 bg-yellow-500 rounded-full animate-ping" style="animation-delay: 500ms"></div>
                        <div class="absolute bottom-4 left-12 w-1 h-1 bg-amber-300 rounded-full animate-ping" style="animation-delay: 1000ms"></div>
                        <div class="absolute bottom-8 right-4 w-2 h-2 bg-yellow-400 rounded-full animate-ping" style="animation-delay: 700ms"></div>
                    </div>

                    <div x-show="revealed" x-transition:enter="transition ease-out duration-500 delay-200"
                         x-transition:enter-start="opacity-0 scale-75 rotate-12"
                         x-transition:enter-end="opacity-100 scale-100 rotate-0"
                         class="text-6xl mb-3">🏅</div>

                    <h3 class="text-lg font-bold text-amber-900" x-show="revealed"
                        x-transition:enter="transition ease-out duration-500 delay-500"
                        x-transition:enter-start="opacity-0 translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-text="$store.lang.t('Founding Member!', 'Membre Fondateur !')"></h3>

                    <p class="text-sm text-amber-700 mt-1" x-show="revealed"
                       x-transition:enter="transition ease-out duration-500 delay-700"
                       x-transition:enter-start="opacity-0"
                       x-transition:enter-end="opacity-100"
                       x-text="$store.lang.t(
                           'You\'re one of the first 20 members! This exclusive badge is yours forever.',
                           'Vous êtes parmi les 20 premiers membres ! Ce badge exclusif est à vous pour toujours.'
                       )"></p>
                </div>
                @endif

                {{-- Bio input --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-800 mb-2" x-text="$store.lang.t(
                        '📝 Tell the community a bit about yourself',
                        '📝 Parlez un peu de vous à la communauté'
                    )"></label>
                    <textarea wire:model="bio"
                              rows="3"
                              maxlength="500"
                              class="w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-cm-green focus:border-cm-green resize-none placeholder:text-slate-400"
                              placeholder="{{ auth()->user()->language_pref?->value === 'fr' ? 'Ex: Camerounais de Douala, je vis à Londres depuis 2020...' : 'E.g: From Douala, living in London since 2020...' }}"></textarea>
                    <div class="flex justify-between mt-1">
                        <p class="text-[10px] text-slate-400" x-text="$store.lang.t('Optional — you can always add this later', 'Optionnel — vous pouvez l\'ajouter plus tard')"></p>
                        <p class="text-[10px] text-slate-400">{{ strlen($bio) }}/500</p>
                    </div>
                </div>

                {{-- Rooms joined summary --}}
                @if(count($selectedRoomIds) > 0)
                <div class="bg-blue-50 border border-blue-200/50 rounded-xl p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-base">✅</span>
                        <span class="text-sm font-semibold text-blue-800"
                              x-text="$store.lang.t('Rooms joined', 'Salles rejointes')"></span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($selectedRoomIds as $rid)
                        @php $joinedRoom = \App\Models\YardRoom::find($rid); @endphp
                        @if($joinedRoom)
                        <span class="text-xs bg-white rounded-lg px-3 py-1.5 text-blue-700 border border-blue-200">
                            {{ $joinedRoom->room_type === \App\Enums\RoomType::National ? '🇨🇲' : '📍' }} {{ $joinedRoom->name }}
                        </span>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            {{-- Actions --}}
            <div class="border-t border-slate-100 px-6 py-4 flex justify-between items-center bg-slate-50/50">
                <button wire:click="skipBio"
                        class="text-sm text-slate-500 hover:text-slate-700 transition-colors"
                        x-text="$store.lang.t('Skip', 'Passer')">
                </button>
                <button wire:click="saveBio"
                        class="rounded-xl bg-cm-green px-6 py-2.5 text-sm font-bold text-white hover:bg-cm-green/90 transition-all duration-200 flex items-center gap-2 shadow-lg shadow-cm-green/20">
                    <span x-text="$store.lang.t('Continue', 'Continuer')"></span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </button>
            </div>
        </div>
        @endif

        {{-- ═══════════════════════════════════════════════ --}}
        {{-- STEP 4: Launch —  You're Ready! --}}
        {{-- ═══════════════════════════════════════════════ --}}
        @if($step === 4)
        <div x-transition:enter="transition ease-out duration-400"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden text-center"
             x-data="{ launched: false }" x-init="setTimeout(() => launched = true, 200)">

            {{-- Confetti canvas --}}
            <canvas id="launch-confetti" class="absolute inset-0 z-10 pointer-events-none" x-show="launched"></canvas>

            {{-- Hero --}}
            <div class="relative bg-gradient-to-br from-cm-green via-blue-700 to-cm-green-light px-8 py-10 text-white overflow-hidden">
                <div class="absolute inset-0 opacity-10">
                    <svg class="w-full h-full" viewBox="0 0 800 400" fill="none">
                        <circle cx="100" cy="80" r="120" fill="currentColor"/>
                        <circle cx="700" cy="300" r="150" fill="currentColor"/>
                        <circle cx="400" cy="350" r="80" fill="currentColor"/>
                    </svg>
                </div>
                <div class="relative">
                    <div x-show="launched"
                         x-transition:enter="transition ease-out duration-700"
                         x-transition:enter-start="opacity-0 scale-0 -rotate-180"
                         x-transition:enter-end="opacity-100 scale-100 rotate-0"
                         class="text-7xl mb-4">🎉</div>
                    <h2 class="text-3xl font-bold mb-2"
                        x-show="launched"
                        x-transition:enter="transition ease-out duration-500 delay-300"
                        x-transition:enter-start="opacity-0 translate-y-4"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-text="$store.lang.t('You\'re all set, {{ auth()->user()->name }}!', 'Vous êtes prêt(e), {{ auth()->user()->name }} !')"></h2>
                    <p class="text-white/80 text-base"
                       x-show="launched"
                       x-transition:enter="transition ease-out duration-500 delay-500"
                       x-transition:enter-start="opacity-0"
                       x-transition:enter-end="opacity-100"
                       x-text="$store.lang.t('Welcome to the family. The Yard awaits.', 'Bienvenue dans la famille. Le Yard vous attend.')"></p>
                </div>
            </div>

            <div class="p-8 space-y-5"
                 x-show="launched"
                 x-transition:enter="transition ease-out duration-500 delay-700"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0">

                {{-- What you can do --}}
                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                        <div class="text-2xl mb-2">💬</div>
                        <p class="text-xs font-semibold text-slate-700" x-text="$store.lang.t('Chat', 'Discuter')"></p>
                        <p class="text-[10px] text-slate-500 mt-0.5" x-text="$store.lang.t('The Yard', 'Le Yard')"></p>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                        <div class="text-2xl mb-2">🤲</div>
                        <p class="text-xs font-semibold text-slate-700" x-text="$store.lang.t('Support', 'Soutenir')"></p>
                        <p class="text-[10px] text-slate-500 mt-0.5" x-text="$store.lang.t('Solidarity', 'Solidarité')"></p>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                        <div class="text-2xl mb-2">🤖</div>
                        <p class="text-xs font-semibold text-slate-700" x-text="$store.lang.t('Ask', 'Demander')"></p>
                        <p class="text-[10px] text-slate-500 mt-0.5">Kamer AI</p>
                    </div>
                </div>

                {{-- CTA Button --}}
                <button wire:click="completeOnboarding"
                        class="w-full rounded-xl bg-cm-green px-8 py-4 text-base font-bold text-white hover:bg-cm-green/90 transition-all duration-200 flex items-center justify-center gap-3 shadow-xl shadow-cm-green/30 hover:shadow-2xl hover:shadow-cm-green/40 hover:-translate-y-0.5">
                    <span x-text="$store.lang.t('Enter The Yard 🚀', 'Entrer dans le Yard 🚀')"></span>
                </button>
            </div>
        </div>
        @endif

    </div>

    @push('scripts')
    {{-- Marked.js for rendering Kamer AI markdown (v12 exports marked.parse) --}}
    <script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js"></script>
    <script>
        function onboardingFlow() {
            return {
                typewriterInit() {
                    this.$nextTick(() => this.scrollChat());
                },
                scrollChat() {
                    const container = document.getElementById('chat-container');
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                    }
                }
            };
        }

        // Scroll chat on Livewire updates
        document.addEventListener('livewire:morphed', () => {
            const container = document.getElementById('chat-container');
            if (container) container.scrollTop = container.scrollHeight;
        });

        // Mini confetti for launch step
        document.addEventListener('livewire:navigated', () => launchConfetti());
        document.addEventListener('livewire:morphed', () => launchConfetti());
        function launchConfetti() {
            const canvas = document.getElementById('launch-confetti');
            if (!canvas) return;
            if (canvas.dataset.fired) return;
            canvas.dataset.fired = '1';
            const ctx = canvas.getContext('2d');
            canvas.width = canvas.parentElement.offsetWidth;
            canvas.height = canvas.parentElement.offsetHeight;
            const colors = ['#243a5c', '#CE1126', '#FCD116', '#FFFFFF', '#FFD700', '#38bdf8'];
            const particles = [];
            for (let i = 0; i < 120; i++) {
                particles.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height - canvas.height,
                    w: Math.random() * 8 + 3,
                    h: Math.random() * 4 + 2,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    speed: Math.random() * 3 + 2,
                    angle: Math.random() * Math.PI * 2,
                    spin: (Math.random() - 0.5) * 0.2,
                    drift: (Math.random() - 0.5) * 1,
                });
            }
            let frame = 0, max = 250;
            function animate() {
                if (frame >= max) { ctx.clearRect(0, 0, canvas.width, canvas.height); return; }
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                particles.forEach(p => {
                    p.y += p.speed; p.x += p.drift; p.angle += p.spin;
                    ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.angle);
                    ctx.fillStyle = p.color; ctx.globalAlpha = Math.max(0, 1 - frame / max);
                    ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h); ctx.restore();
                });
                frame++;
                requestAnimationFrame(animate);
            }
            animate();
        }
    </script>
    @endpush

    @push('styles')
    <style>
        @keyframes fade-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fade-in 0.3s ease-out both; }
    </style>
    @endpush
</div>
