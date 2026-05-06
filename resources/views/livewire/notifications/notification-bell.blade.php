{{-- 
    Real-time notification bell — header dropdown.
    Aggregates: DM unreads, mentions, group join requests (admin), connection requests.
    Live updates via Echo on tenant.{tid}.user.{uid} channel.
--}}
@php
    $tenantId = optional(app('currentTenant'))->id;
    $userId   = auth()->id();
    $total    = $this->total;
    $counts   = $this->counts;
    $feed     = $this->feed;
@endphp

<div
    x-data="notificationBell({{ (int) $userId }}, {{ $tenantId ? (int) $tenantId : 'null' }})"
    x-init="init()"
    @keydown.escape.window="open = false"
    class="relative ml-1"
    wire:ignore.self
>
    {{-- Bell button --}}
    <button
        type="button"
        @click="open = !open"
        class="relative rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white transition-colors"
        :class="pulse && 'nb-pulse'"
        :aria-label="$store.lang.t('Notifications', 'Notifications')"
    >
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if($total > 0)
            <span class="nb-badge">
                <span class="nb-badge__pulse"></span>
                <span class="nb-badge__count">{{ $total > 99 ? '99+' : $total }}</span>
            </span>
        @endif
    </button>

    {{-- Dropdown panel --}}
    <div
        x-show="open"
        x-cloak
        @click.outside="open = false"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="nb-panel"
    >
        {{-- Header --}}
        <div class="nb-head">
            <div class="flex items-center gap-2">
                <span class="text-base font-bold text-slate-900" x-text="$store.lang.t('Notifications', 'Notifications')"></span>
                @if($total > 0)
                    <span class="nb-head-count">{{ $total }}</span>
                @endif
            </div>
            @if(($counts['all'] ?? 0) - ($counts['groups'] ?? 0) - ($counts['connections'] ?? 0) > 0)
                <button
                    type="button"
                    wire:click="markAllChatsRead"
                    class="text-xs font-semibold text-cm-green hover:text-cm-green/80"
                    x-text="$store.lang.t('Mark chats read', 'Tout marquer lu')"
                ></button>
            @endif
        </div>

        {{-- Tab strip --}}
        <div class="nb-tabs">
            @foreach (['all' => ['All','Tout'], 'mentions' => ['Mentions','Mentions'], 'groups' => ['Groups','Groupes'], 'connections' => ['Network','Réseau']] as $key => $labels)
                <button
                    type="button"
                    wire:click="setTab('{{ $key }}')"
                    class="nb-tab {{ $tab === $key ? 'nb-tab--on' : '' }}"
                >
                    <span x-text="$store.lang.t(@js($labels[0]), @js($labels[1]))"></span>
                    @if(($counts[$key] ?? 0) > 0)
                        <span class="nb-tab__count">{{ $counts[$key] > 99 ? '99+' : $counts[$key] }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- Feed --}}
        <div class="nb-list">
            @forelse ($feed as $n)
                <a
                    href="{{ $n['link'] }}"
                    class="nb-item nb-item--{{ $n['kind'] }}"
                    @click="open = false"
                >
                    <div class="nb-avatar {{ $n['palette'] }}">
                        <span>{{ $n['initial'] }}</span>
                        <span class="nb-avatar__icon nb-avatar__icon--{{ $n['kind'] }}" aria-hidden="true">
                            @switch($n['icon'])
                                @case('chat')
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M2 12c0-4.4 4.5-8 10-8s10 3.6 10 8-4.5 8-10 8c-1.4 0-2.7-.2-3.9-.6L3 21l1.7-4.2C3 15.5 2 13.8 2 12z"/></svg>
                                    @break
                                @case('at')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="4"/><path d="M16 12v1.5a2.5 2.5 0 005 0V12a9 9 0 10-3.5 7.1"/></svg>
                                    @break
                                @case('user-plus')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="9" cy="8" r="4"/><path d="M2 21v-1a6 6 0 016-6h2a6 6 0 016 6v1"/><path d="M19 8v6M16 11h6"/></svg>
                                    @break
                                @case('link')
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 14a5 5 0 007.07 0l3-3a5 5 0 00-7.07-7.07l-1.5 1.5"/><path d="M14 10a5 5 0 00-7.07 0l-3 3a5 5 0 007.07 7.07l1.5-1.5"/></svg>
                                    @break
                            @endswitch
                        </span>
                    </div>
                    <div class="nb-body">
                        <div class="nb-row1">
                            <span class="nb-title">{{ $n['title'] }}</span>
                            <span class="nb-time">{{ $n['time'] ? \Illuminate\Support\Carbon::parse($n['time'])->diffForHumans(null, true) : '' }}</span>
                        </div>
                        <div class="nb-text">{{ $n['body'] }}</div>
                    </div>
                    @if(($n['unread'] ?? 0) > 1)
                        <span class="nb-pill">{{ $n['unread'] > 99 ? '99+' : $n['unread'] }}</span>
                    @endif
                </a>
            @empty
                <div class="nb-empty">
                    <div class="nb-empty__art">
                        <svg viewBox="0 0 64 64" fill="none">
                            <circle cx="32" cy="32" r="28" fill="#f1f5f9"/>
                            <path d="M22 30c0-5.5 4.5-10 10-10s10 4.5 10 10v6l3 4H19l3-4v-6z" stroke="#94a3b8" stroke-width="2.5" stroke-linejoin="round"/>
                            <path d="M28 44a4 4 0 008 0" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="nb-empty__title" x-text="$store.lang.t('You\'re all caught up', 'Tout est à jour')"></div>
                    <div class="nb-empty__sub" x-text="$store.lang.t('New messages and requests will appear here.', 'Les nouveaux messages et demandes apparaîtront ici.')"></div>
                </div>
            @endforelse
        </div>

        {{-- Footer --}}
        <div class="nb-foot">
            <a href="{{ route('yard') }}" class="nb-foot__link" @click="open = false" x-text="$store.lang.t('Open The Yard', 'Ouvrir Le Yard')"></a>
        </div>
    </div>
</div>

@once
<style>
    [x-cloak] { display: none !important; }

    .nb-badge {
        position: absolute; top: -2px; right: -2px;
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 18px; height: 18px; padding: 0 4px;
        background: linear-gradient(135deg,#ef4444,#dc2626);
        color: #fff; font-size: 10px; font-weight: 800; line-height: 1;
        border-radius: 9999px; border: 2px solid #2c4a3e;
        box-shadow: 0 2px 6px rgba(239,68,68,.45);
    }
    .nb-badge__pulse {
        position: absolute; inset: -4px; border-radius: 9999px;
        background: rgba(239,68,68,.45);
        animation: nb-pulse 1.6s ease-out infinite;
    }
    .nb-badge__count { position: relative; z-index: 1; }
    @keyframes nb-pulse {
        0%   { transform: scale(.6); opacity: .8; }
        80%  { transform: scale(1.6); opacity: 0; }
        100% { transform: scale(1.6); opacity: 0; }
    }
    .nb-pulse { animation: nb-shake .5s ease-out; }
    @keyframes nb-shake {
        0%,100% { transform: rotate(0); }
        20% { transform: rotate(-12deg); }
        40% { transform: rotate(10deg); }
        60% { transform: rotate(-6deg); }
        80% { transform: rotate(4deg); }
    }

    .nb-panel {
        position: absolute; top: calc(100% + 8px); right: 0;
        width: 380px; max-width: calc(100vw - 24px);
        background: #fff; border-radius: 16px;
        border: 1px solid rgba(15,23,42,.08);
        box-shadow: 0 20px 50px -10px rgba(15,23,42,.25), 0 8px 20px -8px rgba(15,23,42,.18);
        overflow: hidden; z-index: 1000;
        transform-origin: top right;
    }
    .nb-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 12px 16px;
        background: linear-gradient(135deg,#f0fdf4,#ecfdf5);
        border-bottom: 1px solid rgba(15,23,42,.06);
    }
    .nb-head-count {
        background: #10b981; color: #fff; font-size: 11px; font-weight: 700;
        border-radius: 9999px; padding: 1px 8px; line-height: 1.4;
    }
    .nb-tabs {
        display: flex; gap: 4px; padding: 8px 8px 0;
        border-bottom: 1px solid rgba(15,23,42,.06);
    }
    .nb-tab {
        flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 4px;
        padding: 8px 6px; border-radius: 8px 8px 0 0;
        font-size: 12px; font-weight: 600; color: #64748b;
        background: transparent; border: none; cursor: pointer;
        transition: background .15s, color .15s;
        position: relative;
    }
    .nb-tab:hover { background: rgba(15,23,42,.04); color: #0f172a; }
    .nb-tab--on  { color: #047857; background: rgba(16,185,129,.1); }
    .nb-tab--on::after {
        content: ''; position: absolute; bottom: 0; left: 8px; right: 8px; height: 2px;
        background: #10b981; border-radius: 2px 2px 0 0;
    }
    .nb-tab__count {
        background: #ef4444; color: #fff; font-size: 9px; font-weight: 700;
        min-width: 16px; height: 16px; border-radius: 9999px;
        display: inline-flex; align-items: center; justify-content: center;
        padding: 0 4px;
    }

    .nb-list {
        max-height: 420px; overflow-y: auto;
        scrollbar-width: thin;
    }
    .nb-list::-webkit-scrollbar { width: 6px; }
    .nb-list::-webkit-scrollbar-thumb { background: rgba(15,23,42,.15); border-radius: 3px; }

    .nb-item {
        display: flex; align-items: flex-start; gap: 12px;
        padding: 12px 16px; text-decoration: none; color: inherit;
        border-bottom: 1px solid rgba(15,23,42,.04);
        transition: background .12s;
        position: relative;
    }
    .nb-item:hover { background: #f8fafc; }
    .nb-item:last-child { border-bottom: none; }
    .nb-item--mention { background: linear-gradient(90deg, rgba(251,191,36,.08), transparent); }
    .nb-item--join_request { background: linear-gradient(90deg, rgba(16,185,129,.08), transparent); }
    .nb-item--connection { background: linear-gradient(90deg, rgba(59,130,246,.08), transparent); }

    .nb-avatar {
        position: relative; flex-shrink: 0;
        width: 40px; height: 40px; border-radius: 9999px;
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: 14px;
    }
    .nb-avatar__icon {
        position: absolute; bottom: -3px; right: -3px;
        width: 18px; height: 18px; border-radius: 9999px;
        display: inline-flex; align-items: center; justify-content: center;
        background: #fff; border: 2px solid #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,.15);
    }
    .nb-avatar__icon svg { width: 11px; height: 11px; }
    .nb-avatar__icon--chat        { color: #10b981; }
    .nb-avatar__icon--at          { color: #f59e0b; background: #fef3c7; }
    .nb-avatar__icon--user-plus   { color: #10b981; background: #d1fae5; }
    .nb-avatar__icon--link        { color: #3b82f6; background: #dbeafe; }

    .nb-body { flex: 1; min-width: 0; }
    .nb-row1 {
        display: flex; align-items: baseline; justify-content: space-between; gap: 8px;
    }
    .nb-title {
        font-size: 13px; font-weight: 700; color: #0f172a;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .nb-time {
        flex-shrink: 0; font-size: 10px; color: #94a3b8; font-weight: 500;
    }
    .nb-text {
        font-size: 12px; color: #64748b; margin-top: 2px;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .nb-pill {
        flex-shrink: 0; align-self: center;
        background: #10b981; color: #fff; font-size: 10px; font-weight: 700;
        min-width: 20px; height: 20px; border-radius: 9999px;
        display: inline-flex; align-items: center; justify-content: center; padding: 0 6px;
    }

    .nb-empty {
        padding: 36px 24px; text-align: center;
    }
    .nb-empty__art svg { width: 64px; height: 64px; margin: 0 auto; }
    .nb-empty__title { margin-top: 8px; font-size: 14px; font-weight: 700; color: #334155; }
    .nb-empty__sub   { margin-top: 4px; font-size: 12px; color: #94a3b8; }

    .nb-foot {
        padding: 10px 16px; text-align: center;
        background: #f8fafc; border-top: 1px solid rgba(15,23,42,.06);
    }
    .nb-foot__link { font-size: 12px; font-weight: 600; color: #047857; text-decoration: none; }
    .nb-foot__link:hover { text-decoration: underline; }
</style>

<script>
    function notificationBell(userId, tenantId) {
        return {
            open: false,
            pulse: false,
            _channel: null,
            _pulseTimer: null,

            init() {
                if (!window.Echo || !tenantId || !userId) return;
                this._channel = window.Echo.channel(`tenant.${tenantId}.user.${userId}`);
                const refresh = () => {
                    this.flash();
                    try { window.Livewire?.dispatch('bell-refresh'); } catch (_) {}
                };
                this._channel.listen('.connection.requested', refresh);
                this._channel.listen('.connection.accepted',  refresh);
                this._channel.listen('.connection.state',     refresh);
                this._channel.listen('.message.mentioned',    refresh);

                // Also listen to our own already-emitted events on the window so a
                // new chat message anywhere triggers a recount.
                window.addEventListener('yard:new-message',  refresh);
                window.addEventListener('yard:join-request', refresh);
            },

            flash() {
                this.pulse = true;
                clearTimeout(this._pulseTimer);
                this._pulseTimer = setTimeout(() => { this.pulse = false; }, 600);
            },
        };
    }
</script>
@endonce
