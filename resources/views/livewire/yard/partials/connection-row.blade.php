@php
    /** @var \App\Models\User $u */
    /** @var string $state */
    $initial = strtoupper(substr($u->username ?? $u->name ?? '?', 0, 1));
    $colors = ['bg-blue-600', 'bg-violet-600', 'bg-emerald-600', 'bg-amber-600', 'bg-rose-600', 'bg-cyan-600'];
    $colorIdx = crc32($u->name ?? '') % count($colors);
    $avatarColor = $colors[$colorIdx];
@endphp
<div class="flex items-center gap-3 px-3 py-3 border-b border-slate-100 hover:bg-slate-50 transition-colors"
     wire:key="conn-row-{{ $u->id }}-{{ $state }}">
    <div class="w-10 h-10 rounded-full {{ $avatarColor }} flex items-center justify-center shrink-0 overflow-hidden">
        @if($u->avatar)
            <img src="{{ asset('storage/' . $u->avatar) }}" alt="" class="w-full h-full object-cover">
        @else
            <span class="text-white font-bold text-sm">{{ $initial }}</span>
        @endif
    </div>

    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-slate-800 truncate">{{ $u->name }}</p>
        <p class="text-xs text-slate-500 truncate">
            &#64;{{ $u->username }}
            @if($u->current_country)
                · {{ $u->current_country }}
            @endif
        </p>
    </div>

    <div class="flex items-center gap-2 shrink-0">
        @switch($state)
            @case('connected')
                <button wire:click="openDm({{ $u->id }})"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-cm-green text-white hover:bg-cm-green/90 transition-colors">
                    <span x-text="$store.lang.t('Message', 'Message')"></span>
                </button>
                <button wire:click="disconnect({{ $u->id }})"
                        wire:confirm="{{ app()->getLocale() === 'fr' ? 'Se déconnecter de cette personne ?' : 'Disconnect from this person?' }}"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors">
                    <span x-text="$store.lang.t('Disconnect', 'Déconnecter')"></span>
                </button>
                <button wire:click="block({{ $u->id }})"
                        wire:confirm="{{ app()->getLocale() === 'fr' ? 'Bloquer cette personne ?' : 'Block this person?' }}"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 transition-colors">
                    <span x-text="$store.lang.t('Block', 'Bloquer')"></span>
                </button>
                @break

            @case('incoming')
                <button wire:click="acceptRequest({{ $u->id }})"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-cm-green text-white hover:bg-cm-green/90 transition-colors">
                    <span x-text="$store.lang.t('Accept', 'Accepter')"></span>
                </button>
                <button wire:click="declineRequest({{ $u->id }})"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors">
                    <span x-text="$store.lang.t('Decline', 'Refuser')"></span>
                </button>
                @break

            @case('outgoing')
                <span class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-amber-50 text-amber-700 border border-amber-200">
                    <span x-text="$store.lang.t('Pending', 'En attente')"></span>
                </span>
                <button wire:click="cancelRequest({{ $u->id }})"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors">
                    <span x-text="$store.lang.t('Cancel', 'Annuler')"></span>
                </button>
                @break

            @case('blocked-by-me')
                <span class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-rose-50 text-rose-600 border border-rose-200">
                    <span x-text="$store.lang.t('Blocked', 'Bloqué')"></span>
                </span>
                <button wire:click="unblock({{ $u->id }})"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors">
                    <span x-text="$store.lang.t('Unblock', 'Débloquer')"></span>
                </button>
                @break

            @case('blocked-by-them')
                {{-- Don't reveal that the other side blocked: just hide actions --}}
                <span class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 text-slate-400 cursor-not-allowed">
                    <span x-text="$store.lang.t('Unavailable', 'Indisponible')"></span>
                </span>
                @break

            @default
                <button wire:click="sendRequest({{ $u->id }})"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-cm-green text-white hover:bg-cm-green/90 transition-colors">
                    <span x-text="$store.lang.t('Connect', 'Se connecter')"></span>
                </button>
        @endswitch
    </div>
</div>
