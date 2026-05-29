<div class="min-h-[calc(100vh-96px)] bg-slate-100">
    <div class="max-w-[1400px] mx-auto px-3 sm:px-4 lg:px-5 py-4 lg:py-5">
        <div class="grid lg:grid-cols-[300px_1fr] gap-5">

            {{-- ─── Sidebar (re-uses marketplace partial) ─── --}}
            <aside class="hidden lg:block lg:sticky lg:top-4 lg:self-start lg:max-h-[calc(100vh-128px)] lg:overflow-y-auto">
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-3">
                    @include('livewire.marketplace.partials.sidebar')
                </div>
            </aside>

            {{-- ─── Main ─── --}}
            <main class="min-w-0">
                {{-- Header --}}
                <div class="flex items-center justify-between mb-4">
                    <div class="min-w-0">
                        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">
                            <span x-data x-text="$store.lang.t('Saved searches','Recherches enregistrées')"></span>
                        </h1>
                        <p class="text-sm text-slate-600 mt-0.5">
                            <span x-data x-text="$store.lang.t('We\'ll notify you when new matching listings appear.','Nous vous avertirons lorsque de nouvelles annonces correspondent.')"></span>
                        </p>
                    </div>
                    @if ($this->totalNewMatches > 0)
                        <button wire:click="markAllSeen"
                                class="text-sm font-semibold text-cm-green hover:underline">
                            <span x-data x-text="$store.lang.t('Mark all as seen','Tout marquer comme vu')"></span>
                        </button>
                    @endif
                </div>

                @if ($this->searches->isEmpty())
                    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-10 text-center">
                        <div class="w-16 h-16 mx-auto mb-3 grid place-items-center rounded-full bg-cm-green/10 text-cm-green">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 3v5h5"/></svg>
                        </div>
                        <h2 class="text-lg font-bold text-slate-900">
                            <span x-data x-text="$store.lang.t('No saved searches yet','Aucune recherche enregistrée')"></span>
                        </h2>
                        <p class="text-sm text-slate-600 mt-1 max-w-md mx-auto">
                            <span x-data x-text="$store.lang.t('Apply filters on the Marketplace feed and tap “Save this search” to get alerts when matching items are listed.','Appliquez des filtres sur le Marketplace et cliquez sur « Enregistrer cette recherche » pour recevoir des alertes.')"></span>
                        </p>
                        <a href="{{ route('marketplace.index') }}" wire:navigate
                           class="inline-flex items-center gap-2 mt-5 bg-cm-green hover:bg-cm-green/90 text-white font-semibold rounded-full px-5 py-2 text-sm shadow-md transition">
                            <span x-data x-text="$store.lang.t('Browse Marketplace','Parcourir le Marketplace')"></span>
                        </a>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($this->searches as $s)
                            <div wire:key="ss-{{ $s->id }}"
                                 class="bg-white rounded-2xl shadow-sm ring-1 ring-slate-200 p-4 lg:p-5 hover:shadow-md transition">
                                <div class="flex items-start gap-4">
                                    {{-- Left: name + summary --}}
                                    <div class="flex-1 min-w-0">
                                        @if ($editingId === $s->id)
                                            <div class="flex items-center gap-2 mb-2">
                                                <input type="text" wire:model="editName" maxlength="120"
                                                       wire:keydown.enter="saveRename"
                                                       wire:keydown.escape="cancelRename"
                                                       class="flex-1 rounded-lg ring-1 ring-slate-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-cm-green focus:outline-none">
                                                <button wire:click="saveRename"
                                                        class="text-xs font-semibold text-white bg-cm-green hover:bg-cm-green/90 rounded-full px-3 py-1.5">
                                                    <span x-data x-text="$store.lang.t('Save','Enregistrer')"></span>
                                                </button>
                                                <button wire:click="cancelRename"
                                                        class="text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-full px-3 py-1.5">
                                                    <span x-data x-text="$store.lang.t('Cancel','Annuler')"></span>
                                                </button>
                                            </div>
                                        @else
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <h3 class="text-base font-bold text-slate-900 truncate">{{ $s->name }}</h3>
                                                @if ($s->new_matches > 0)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-cm-red text-white text-[11px] font-bold">
                                                        +{{ $s->new_matches }}
                                                        <span x-data x-text="$store.lang.t('new','nouveau')"></span>
                                                    </span>
                                                @endif
                                                <button wire:click="startRename({{ $s->id }})"
                                                        class="text-[12px] text-slate-500 hover:text-cm-green font-medium">
                                                    <span x-data x-text="$store.lang.t('Rename','Renommer')"></span>
                                                </button>
                                            </div>
                                        @endif
                                        <p class="text-[13px] text-slate-600 mt-1 line-clamp-2">{{ $s->summary }}</p>
                                        <p class="text-[11px] text-slate-400 mt-1">
                                            <span x-data x-text="$store.lang.t('Saved','Enregistré')"></span>
                                            {{ $s->created_at?->diffForHumans() }}
                                        </p>
                                    </div>

                                    {{-- Right: actions --}}
                                    <div class="flex flex-col items-end gap-2 shrink-0">
                                        <a href="{{ $s->matches_url }}" wire:navigate
                                           wire:click="markSeen({{ $s->id }})"
                                           class="inline-flex items-center gap-1.5 bg-cm-green hover:bg-cm-green/90 text-white font-semibold rounded-full px-4 py-1.5 text-xs shadow-sm transition">
                                            <span x-data x-text="$store.lang.t('View matches','Voir les résultats')"></span>
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                        </a>
                                    </div>
                                </div>

                                {{-- Toggles row --}}
                                <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <label class="flex items-center gap-2 text-[12px] text-slate-700 cursor-pointer select-none">
                                            <input type="checkbox"
                                                   {{ $s->notify_push ? 'checked' : '' }}
                                                   wire:click="togglePush({{ $s->id }})"
                                                   class="rounded text-cm-green focus:ring-cm-green">
                                            <span class="font-medium">🔔 <span x-data x-text="$store.lang.t('In-app','Dans l\'app')"></span></span>
                                        </label>
                                        <label class="flex items-center gap-2 text-[12px] text-slate-700 cursor-pointer select-none">
                                            <input type="checkbox"
                                                   {{ $s->notify_email ? 'checked' : '' }}
                                                   wire:click="toggleEmail({{ $s->id }})"
                                                   class="rounded text-cm-green focus:ring-cm-green">
                                            <span class="font-medium">✉️ <span x-data x-text="$store.lang.t('Email','E-mail')"></span></span>
                                        </label>
                                    </div>
                                    <button wire:click="delete({{ $s->id }})"
                                            wire:confirm="{{ __('Delete this saved search?') }}"
                                            class="text-[12px] text-cm-red hover:underline font-medium">
                                        <span x-data x-text="$store.lang.t('Delete','Supprimer')"></span>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </main>
        </div>
    </div>
</div>
