@props(['title' => null, 'active' => 'marketplace'])
{{--
    Shared "rails" shell — same chrome as /yard (icon sidebar left, sponsored ads
    sidebar right) but with a free-form middle column. Used by /marketplace and
    can be reused by any page that wants the GoConnect-style layout.
--}}
<x-layouts.app :title="$title" :yardMode="true">
    <div class="yard-container">
        @include('yard.partials.icon-sidebar', ['active' => $active])

        <main class="flex-1 min-w-0 overflow-y-auto bg-slate-50">
            {{ $slot }}
        </main>

        @include('yard.partials.ads-sidebar')
    </div>
</x-layouts.app>
