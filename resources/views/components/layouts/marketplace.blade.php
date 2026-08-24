@props(['title' => null, 'active' => 'marketplace', 'sidebar_filters' => null])

<x-layouts.rails :title="$title" :active="$active">
    <div class="flex items-start min-h-full">
        {{-- ─── Desktop sidebar (Yard-style panel) ─── --}}
        <aside class="hidden lg:flex flex-col w-[320px] shrink-0 border-r border-slate-200 bg-white sticky top-0 h-[calc(100vh-64px)] overflow-y-auto">
            {{-- Brand-blue panel header (matches the GoConnect chat-list bar) --}}
            <header class="mp-header">
                <h2 class="mp-header__title">
                    <span x-data x-text="$store.lang.t('GoMarket','GoMarket')">GoMarket</span>
                </h2>
                <button type="button" class="mp-header__btn"
                        title="{{ app()->getLocale() === 'fr' ? 'Paramètres' : 'Settings' }}">
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </button>
            </header>
            <div class="p-3 w-full">
                @include('livewire.marketplace.partials.sidebar-nav', ['sidebar_filters' => $sidebar_filters ?? null, 'hideTitle' => true])
            </div>
        </aside>

        {{-- ─── Main column ─── --}}
        <main class="flex-1 min-w-0 p-3 sm:p-4 lg:p-6">
            <div class="max-w-[1100px] mx-auto">
                {{ $slot }}
            </div>
        </main>
    </div>
</x-layouts.rails>
