<div>
    @if($show)
    <div class="fixed inset-0 z-[80] flex items-center justify-center"
         x-data="{}"
         x-init="$nextTick(() => document.body.style.overflow = 'hidden')"
         x-on:keydown.escape.window="$wire.close()">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"
             wire:click="close"></div>

        {{-- Panel --}}
        <div class="relative z-10 w-full max-w-md mx-4 sm:mx-auto bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col"
             style="max-height: 85vh;">

            {{-- Header --}}
            <header class="flex items-center justify-between gap-3 px-5 py-3 border-b border-slate-100 bg-white">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-50 text-amber-500">
                        <svg class="w-4.5 h-4.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                    </span>
                    <h2 class="font-semibold text-slate-800" x-text="$store.lang.t('Starred messages', 'Messages favoris')"></h2>
                </div>
                <button type="button" wire:click="close"
                        class="w-8 h-8 inline-flex items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 transition-colors"
                        :aria-label="$store.lang.t('Close', 'Fermer')">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </header>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto bg-slate-50">
                @forelse($this->messages as $msg)
                    <div wire:key="star-{{ $msg->message_id }}"
                         class="group flex items-start gap-3 px-4 py-3 bg-white border-b border-slate-100 hover:bg-slate-50/80 transition-colors">
                        {{-- Avatar (sender) --}}
                        <div class="shrink-0 w-9 h-9 rounded-full overflow-hidden bg-slate-200 flex items-center justify-center text-xs font-semibold text-slate-500">
                            @if($msg->sender_avatar)
                                <img src="{{ asset('storage/' . $msg->sender_avatar) }}" alt="" class="w-full h-full object-cover">
                            @else
                                {{ strtoupper(substr($msg->sender_name ?? '?', 0, 2)) }}
                            @endif
                        </div>

                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                <span class="font-semibold text-slate-700 truncate">{{ $msg->sender_name ?? __('Unknown') }}</span>
                                <span>·</span>
                                <span class="truncate">{{ $msg->display_room_name }}</span>
                                @if(!$msg->is_dm)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-slate-100 text-[10px] uppercase tracking-wider text-slate-500">{{ __('group') }}</span>
                                @endif
                            </div>
                            <button type="button"
                                    wire:click="jump({{ $msg->room_id }}, {{ $msg->message_id }})"
                                    class="mt-0.5 block text-left text-sm text-slate-800 leading-snug line-clamp-2 hover:text-cm-green transition-colors">
                                {{ \Illuminate\Support\Str::limit($msg->preview, 220) }}
                            </button>
                            <div class="mt-1 flex items-center gap-2 text-[11px] text-slate-400">
                                <span>{{ \Carbon\Carbon::parse($msg->msg_created_at)->diffForHumans() }}</span>
                                <span>·</span>
                                <span class="inline-flex items-center gap-0.5">
                                    <svg class="w-3 h-3 text-amber-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                                    <span>{{ \Carbon\Carbon::parse($msg->starred_at)->diffForHumans() }}</span>
                                </span>
                            </div>
                        </div>

                        {{-- Unstar --}}
                        <button type="button"
                                wire:click="unstar({{ $msg->message_id }})"
                                class="shrink-0 w-7 h-7 inline-flex items-center justify-center rounded-full text-slate-400 hover:text-amber-500 hover:bg-amber-50 transition-colors opacity-0 group-hover:opacity-100"
                                :title="$store.lang.t('Unstar', 'Retirer des favoris')">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                        </button>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center text-center px-6 py-16">
                        <div class="w-14 h-14 rounded-full bg-amber-50 text-amber-400 flex items-center justify-center mb-3">
                            <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                        </div>
                        <p class="text-sm font-medium text-slate-700"
                           x-text="$store.lang.t('No starred messages yet', 'Aucun message favori pour le moment')"></p>
                        <p class="mt-1 text-xs text-slate-500 max-w-xs"
                           x-text="$store.lang.t('Tap and hold (or right-click) any message in a chat and choose Star to bookmark it here.', 'Maintenez ou faites un clic droit sur un message dans une discussion et choisissez Favori pour l\'enregistrer ici.')"></p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
    @endif
</div>
