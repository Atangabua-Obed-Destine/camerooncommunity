{{-- 
    Pulsing pending-request badge.
    Usage:  <livewire:yard.connections-badge :variant="'dot'" />
            <livewire:yard.connections-badge :variant="'pill'" />
    Defaults to a small floating dot suitable for layering on top of an icon button.
--}}
@php($n = $this->pendingCount)

<span class="conn-badge-root" style="display:contents;">
@if($n > 0)
    @if($variant === 'pill')
        {{-- Inline pill: red rounded badge with the count, used inside menu rows --}}
        <span class="conn-badge conn-badge--pill" title="{{ $n }} pending {{ Str::plural('request', $n) }}">
            <span class="conn-badge__pulse"></span>
            {{ $n > 99 ? '99+' : $n }}
        </span>
    @else
        {{-- Floating dot: absolute-positioned, designed to overlay a button --}}
        <span class="conn-badge conn-badge--dot" title="{{ $n }} pending {{ Str::plural('request', $n) }}">
            <span class="conn-badge__pulse"></span>
            <span class="conn-badge__count">{{ $n > 9 ? '9+' : $n }}</span>
        </span>
    @endif
@endif
</span>

@once
<style>
    .conn-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: #fff;
        font-weight: 700;
        font-size: 10px;
        line-height: 1;
        box-shadow: 0 2px 6px rgba(239, 68, 68, .45);
        position: relative;
    }
    .conn-badge--dot {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 18px;
        height: 18px;
        padding: 0 4px;
        border-radius: 9999px;
        border: 2px solid #fff;
        z-index: 5;
    }
    .conn-badge--pill {
        margin-left: auto;
        min-width: 22px;
        padding: 2px 8px;
        border-radius: 9999px;
        font-size: 11px;
    }
    .conn-badge__pulse {
        position: absolute;
        inset: -2px;
        border-radius: inherit;
        background: rgba(239, 68, 68, .55);
        animation: conn-badge-pulse 1.8s ease-out infinite;
        z-index: -1;
    }
    .conn-badge--pill .conn-badge__pulse { inset: -3px; }
    .conn-badge__count { position: relative; z-index: 1; }
    @keyframes conn-badge-pulse {
        0%   { transform: scale(.8); opacity: .8; }
        70%  { transform: scale(1.6); opacity: 0; }
        100% { transform: scale(1.6); opacity: 0; }
    }
</style>
@endonce
