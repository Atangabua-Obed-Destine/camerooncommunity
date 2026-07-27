{{-- Marketplace sidebar (FB-style) — light theme always --}}
@php
    $mpLang = app()->getLocale();
    $activeRoute = \Illuminate\Support\Facades\Route::currentRouteName();

    // Lightweight badge: count of "new since last seen" across this user's saved searches.
    $savedSearchNewCount = 0;
    if (auth()->check()) {
        $savedSearchNewCount = \Illuminate\Support\Facades\Cache::remember(
            'mp:ss-new:' . auth()->id(),
            now()->addMinutes(2),
            function () {
                $rows = \App\Models\MarketplaceSavedSearch::where('user_id', auth()->id())->get();
                $total = 0;
                foreach ($rows as $r) {
                    $filters = is_array($r->filters) ? $r->filters : [];
                    $since = $r->last_notified_at ?? $r->created_at;
                    $total += \App\Support\MarketplaceQueryBuilder::build($filters)
                        ->where('published_at', '>', $since)
                        ->count();
                }
                return $total;
            }
        );
    }

    // Unread marketplace inbox count
    $mpInboxUnread = 0;
    if (auth()->check()) {
        $mpInboxUnread = \Illuminate\Support\Facades\Cache::remember(
            'mp:inbox-unread:' . auth()->id(),
            60,
            function () {
                $me = auth()->id();
                $members = \App\Models\YardRoomMember::where('user_id', $me)
                    ->whereHas('room', fn ($q) => $q->where('origin', 'marketplace'))
                    ->get(['room_id', 'last_read_at']);
                $total = 0;
                foreach ($members as $m) {
                    $total += \App\Models\YardMessage::where('room_id', $m->room_id)
                        ->where('user_id', '!=', $me)
                        ->where('is_deleted', false)
                        ->when($m->last_read_at, fn ($q, $lr) => $q->where('created_at', '>', $lr))
                        ->count();
                }
                return $total;
            }
        );
    }
@endphp

<div class="space-y-3">

    {{-- ─── Title + settings ─── --}}
    <div class="flex items-center justify-between px-1">
        <h2 class="text-xl font-extrabold text-slate-900 tracking-tight">
            <span x-data x-text="$store.lang.t('GoMarket','GoMarket')"></span>
        </h2>
        <button type="button" title="{{ $mpLang === 'fr' ? 'Paramètres' : 'Settings' }}"
                class="w-9 h-9 grid place-items-center rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </button>
    </div>

    {{-- ─── Search ─── --}}
    <div class="relative">
        @if ($activeRoute === 'marketplace.index')
            <input type="text" wire:model.live.debounce.800ms="query"
                   placeholder="{{ $mpLang === 'fr' ? 'Rechercher dans Marketplace' : 'Search Marketplace' }}"
                   class="w-full rounded-full bg-slate-100 border-0 pl-10 pr-3 py-2.5 text-sm text-slate-900 placeholder-slate-500 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
        @else
            <form action="{{ route('marketplace.index') }}" method="GET">
                <input type="text" name="query"
                       placeholder="{{ $mpLang === 'fr' ? 'Rechercher dans Marketplace' : 'Search Marketplace' }}"
                       class="w-full rounded-full bg-slate-100 border-0 pl-10 pr-3 py-2.5 text-sm text-slate-900 placeholder-slate-500 focus:bg-white focus:ring-2 focus:ring-cm-green focus:outline-none transition">
            </form>
        @endif
        <svg class="w-4 h-4 absolute left-3.5 top-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
    </div>

    {{-- ─── Primary nav (FB vertical style) ─── --}}
    <nav class="space-y-0.5">
        <a href="{{ route('marketplace.index') }}" wire:navigate
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-[15px] font-medium transition
           {{ $activeRoute === 'marketplace.index' ? 'bg-cm-green/10 text-cm-green' : 'text-slate-800 hover:bg-slate-100' }}">
            <span class="w-9 h-9 grid place-items-center rounded-full {{ $activeRoute === 'marketplace.index' ? 'bg-cm-green text-white' : 'bg-slate-200 text-slate-700' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1h-5v-7H9v7H4a1 1 0 01-1-1V9.5z"/></svg>
            </span>
            <span x-data x-text="$store.lang.t('Browse all','Tout parcourir')"></span>
        </a>

        {{-- Inbox — marketplace-scoped conversations (Buying / Selling) --}}
        <a href="{{ route('marketplace.inbox') }}" wire:navigate
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-[15px] font-medium transition
           {{ $activeRoute === 'marketplace.inbox' ? 'bg-cm-green/10 text-cm-green' : 'text-slate-800 hover:bg-slate-100' }}">
            <span class="w-9 h-9 grid place-items-center rounded-full {{ $activeRoute === 'marketplace.inbox' ? 'bg-cm-green text-white' : 'bg-slate-200 text-slate-700' }}">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
            </span>
            <span class="flex-1" x-data x-text="$store.lang.t('Inbox','Boîte de réception')"></span>
            @if ($mpInboxUnread > 0)
                <span class="ml-auto inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-cm-red text-white text-[11px] font-bold">{{ $mpInboxUnread > 99 ? '99+' : $mpInboxUnread }}</span>
            @endif
        </a>

        <a href="{{ route('marketplace.favorites') }}" wire:navigate
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-[15px] font-medium transition
           {{ $activeRoute === 'marketplace.favorites' ? 'bg-cm-green/10 text-cm-green' : 'text-slate-800 hover:bg-slate-100' }}">
            <span class="w-9 h-9 grid place-items-center rounded-full {{ $activeRoute === 'marketplace.favorites' ? 'bg-cm-green text-white' : 'bg-slate-200 text-slate-700' }}">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21s-7.5-4.6-9.6-9.2C1 7.7 4.3 4 8 4c2 0 3.4 1.2 4 2 .6-.8 2-2 4-2 3.7 0 7 3.7 5.6 7.8C19.5 16.4 12 21 12 21z"/></svg>
            </span>
            <span x-data x-text="$store.lang.t('Saved','Favoris')"></span>
        </a>

        <a href="{{ route('marketplace.offers') }}" wire:navigate
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-[15px] font-medium transition
           {{ $activeRoute === 'marketplace.offers' ? 'bg-cm-green/10 text-cm-green' : 'text-slate-800 hover:bg-slate-100' }}">
            <span class="w-9 h-9 grid place-items-center rounded-full {{ $activeRoute === 'marketplace.offers' ? 'bg-cm-green text-white' : 'bg-slate-200 text-slate-700' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a2 2 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
            </span>
            <span x-data x-text="$store.lang.t('My Offers','Mes Offres')"></span>
        </a>

        @php
            $pendingOrders = 0;
            if (auth()->check()) {
                $pendingOrders = \Illuminate\Support\Facades\Cache::remember(
                    'mp:orders-pending:'.auth()->id(),
                    120,
                    fn () => \App\Models\MarketplaceOrder::query()
                        ->where('seller_id', auth()->id())
                        ->where('status', \App\Enums\OrderStatus::AwaitingPayment->value)
                        ->count()
                );
            }
        @endphp
        <a href="{{ route('marketplace.orders') }}" wire:navigate
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-[15px] font-medium transition
           {{ $activeRoute === 'marketplace.orders' ? 'bg-cm-green/10 text-cm-green' : 'text-slate-800 hover:bg-slate-100' }}">
            <span class="w-9 h-9 grid place-items-center rounded-full {{ $activeRoute === 'marketplace.orders' ? 'bg-cm-green text-white' : 'bg-slate-200 text-slate-700' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </span>
            <span class="flex-1" x-data x-text="$store.lang.t('Orders','Commandes')"></span>
            @if ($pendingOrders > 0)
                <span class="text-[10px] font-bold bg-cm-red text-white rounded-full px-1.5 py-0.5">{{ $pendingOrders }}</span>
            @endif
        </a>

        <a href="{{ route('marketplace.mine') }}" wire:navigate
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-[15px] font-medium transition
           {{ $activeRoute === 'marketplace.mine' ? 'bg-cm-green/10 text-cm-green' : 'text-slate-800 hover:bg-slate-100' }}">
            <span class="w-9 h-9 grid place-items-center rounded-full {{ $activeRoute === 'marketplace.mine' ? 'bg-cm-green text-white' : 'bg-slate-200 text-slate-700' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l-1 12H6L5 9z"/></svg>
            </span>
            <span x-data x-text="$store.lang.t('My Listings','Mes Annonces')"></span>
        </a>

        <a href="{{ route('marketplace.saved') }}" wire:navigate
           class="flex items-center gap-3 px-3 py-2 rounded-xl text-[15px] font-medium transition
           {{ $activeRoute === 'marketplace.saved' ? 'bg-cm-green/10 text-cm-green' : 'text-slate-800 hover:bg-slate-100' }}">
            <span class="w-9 h-9 grid place-items-center rounded-full {{ $activeRoute === 'marketplace.saved' ? 'bg-cm-green text-white' : 'bg-slate-200 text-slate-700' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5"/></svg>
            </span>
            <span class="flex-1" x-data x-text="$store.lang.t('Saved searches','Recherches enregistrées')"></span>
            @if ($savedSearchNewCount > 0)
                <span class="ml-auto inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-cm-red text-white text-[11px] font-bold">{{ $savedSearchNewCount > 99 ? '99+' : $savedSearchNewCount }}</span>
            @endif
        </a>
    </nav>

    {{-- ─── Create new listing CTA ─── --}}
    <a href="{{ route('marketplace.sell') }}" wire:navigate
       class="flex items-center justify-center gap-2 w-full bg-cm-green/10 hover:bg-cm-green/20 text-cm-green font-bold rounded-xl py-2.5 text-sm transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        <span x-data x-text="$store.lang.t('Create new listing','Créer une annonce')"></span>
    </a>

    {{-- Slot for extra sidebar items (filters) --}}
    @if (isset($sidebar_filters))
        <div class="h-px bg-slate-200 my-4"></div>
        {{ $sidebar_filters }}
    @endif
</div>
