@props(['title' => null, 'active' => 'marketplace', 'sidebar_filters' => null])

<x-layouts.rails :title="$title" :active="$active">
    <div class="flex items-start min-h-full">
        {{-- ─── Desktop sidebar (Yard-style panel) ─── --}}
        <aside class="hidden lg:flex flex-col w-[320px] shrink-0 border-r border-slate-200 bg-white sticky top-0 h-[calc(100vh-64px)] overflow-y-auto">
            <div class="p-3 w-full">
                @include('livewire.marketplace.partials.sidebar-nav', ['sidebar_filters' => $sidebar_filters ?? null])
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
