{{--
    Profile card — used on the People page.
    Props:
        $user         User
        $mode         'request' | 'suggest' | 'view' | 'connected'
        $connectionId int|null   (for 'request' mode)
--}}
@php
    $viewer = auth()->user();
    // Match Yard's logic: saved nickname → username → name. Falls back gracefully.
    $name = $viewer ? $viewer->displayNameFor($user) : ($user->username ?? $user->name ?? '—');
    $username = $user->username ?? null;
    $profileUrl = $username ? route('user.profile', ['username' => $username]) : null;
    $location = trim(collect([$user->current_region ?? null, $user->current_country ?? null])->filter()->join(', '));
@endphp

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition flex flex-col">
    {{-- Avatar area (clickable when a profile URL is available) --}}
    @if($profileUrl)
        <a href="{{ $profileUrl }}" class="block aspect-square bg-slate-100 relative group">
    @else
        <div class="block aspect-square bg-slate-100 relative group">
    @endif
        @if(!empty($user->avatar))
            <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $name }}"
                 class="w-full h-full object-cover {{ $profileUrl ? 'group-hover:scale-105 transition-transform duration-300' : '' }}">
        @else
            <div class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-cm-green to-emerald-700 text-white text-5xl font-bold">
                {{ strtoupper(mb_substr($name, 0, 1)) }}
            </div>
        @endif
    @if($profileUrl)
        </a>
    @else
        </div>
    @endif

    {{-- Body --}}
    <div class="p-3 flex-1 flex flex-col">
        @if($profileUrl)
            <a href="{{ $profileUrl }}" class="block">
                <h3 class="font-bold text-slate-900 truncate hover:underline" title="{{ $name }}">{{ $name }}</h3>
            </a>
        @else
            <h3 class="font-bold text-slate-900 truncate" title="{{ $name }}">{{ $name }}</h3>
        @endif
        @if($location !== '')
            <p class="text-xs text-slate-500 truncate mt-0.5">{{ $location }}</p>
        @endif

        {{-- Actions --}}
        <div class="mt-3 space-y-2"
             x-data="{
                pending: false,
                done: false,
                act: 'idle',  // 'request' | 'accept' | 'cancel'
                token: @js(csrf_token()),
                async send(url, body) {
                    if (this.pending) return;
                    this.pending = true;
                    try {
                        const r = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.token,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify(body),
                        });
                        const data = await r.json().catch(() => ({}));
                        if (r.ok) { this.done = true; }
                    } finally { this.pending = false; }
                }
             }">

            @switch($mode)
                @case('request')
                    <template x-if="!done">
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button"
                                    @click="act='accept'; send('{{ route('yard.connections.accept') }}', { user_id: {{ $user->id }} })"
                                    :disabled="pending"
                                    class="px-2 py-1.5 rounded-lg bg-cm-green text-white text-sm font-semibold hover:bg-emerald-700 disabled:opacity-60">
                                <span x-show="!pending || act!=='accept'" x-text="$store.lang.t('Confirm', 'Accepter')"></span>
                                <span x-show="pending && act==='accept'" x-text="$store.lang.t('Accepting…', 'Acceptation…')"></span>
                            </button>
                            <a href="{{ $profileUrl ?? '#' }}" @class(['px-2 py-1.5 rounded-lg bg-slate-100 text-slate-700 text-sm font-semibold text-center', 'hover:bg-slate-200' => $profileUrl, 'opacity-50 pointer-events-none' => !$profileUrl])
                               x-text="$store.lang.t('View', 'Voir')"></a>
                        </div>
                    </template>
                    <template x-if="done">
                        <a href="{{ $profileUrl ?? '#' }}" @class(['block w-full text-center px-2 py-1.5 rounded-lg bg-slate-100 text-slate-700 text-sm font-semibold', 'hover:bg-slate-200' => $profileUrl, 'opacity-50 pointer-events-none' => !$profileUrl])
                           x-text="$store.lang.t('Connected ✓', 'Connecté ✓')"></a>
                    </template>
                    @break

                @case('suggest')
                    <template x-if="!done">
                        <button type="button"
                                @click="act='request'; send('{{ route('yard.connections.request') }}', { user_id: {{ $user->id }} })"
                                :disabled="pending"
                                class="w-full px-2 py-1.5 rounded-lg bg-cm-green text-white text-sm font-semibold hover:bg-emerald-700 disabled:opacity-60">
                            <span x-show="!pending" x-text="$store.lang.t('Add', 'Ajouter')"></span>
                            <span x-show="pending" x-text="$store.lang.t('Sending…', 'Envoi…')"></span>
                        </button>
                    </template>
                    <template x-if="done">
                        <button type="button" disabled class="w-full px-2 py-1.5 rounded-lg bg-slate-100 text-slate-500 text-sm font-semibold cursor-default"
                                x-text="$store.lang.t('Request sent', 'Demande envoyée')"></button>
                    </template>
                    <a href="{{ $profileUrl ?? '#' }}" @class(['block w-full text-center px-2 py-1.5 rounded-lg bg-slate-100 text-slate-700 text-sm font-semibold', 'hover:bg-slate-200' => $profileUrl, 'opacity-50 pointer-events-none' => !$profileUrl])
                       x-text="$store.lang.t('View profile', 'Voir le profil')"></a>
                    @break

                @case('connected')
                    <a href="{{ $profileUrl ?? '#' }}" @class(['block w-full text-center px-2 py-1.5 rounded-lg bg-cm-green text-white text-sm font-semibold', 'hover:bg-emerald-700' => $profileUrl, 'opacity-60 pointer-events-none' => !$profileUrl])
                       x-text="$store.lang.t('View profile', 'Voir le profil')"></a>
                    @break

                @default
                    <a href="{{ $profileUrl ?? '#' }}" @class(['block w-full text-center px-2 py-1.5 rounded-lg bg-slate-100 text-slate-700 text-sm font-semibold', 'hover:bg-slate-200' => $profileUrl, 'opacity-50 pointer-events-none' => !$profileUrl])
                       x-text="$store.lang.t('View profile', 'Voir le profil')"></a>
            @endswitch
        </div>
    </div>
</div>
