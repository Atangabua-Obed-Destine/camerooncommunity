@props([
    'status' => 'sent', // 'sending' | 'sent' | 'delivered' | 'read'
    'messageId' => null,
])

{{-- WhatsApp-style ticks. Alpine reactivity via x-bind on the wrapper to
     allow real-time upgrades without re-rendering the whole bubble. --}}
<span
    @if($messageId !== null)
        x-data
        x-bind:data-status="(($store.msgStatus && $store.msgStatus[{{ (int) $messageId }}]) || '{{ $status }}')"
        x-bind:class="{
            'yard-tick--read': (($store.msgStatus && $store.msgStatus[{{ (int) $messageId }}]) || '{{ $status }}') === 'read',
            'yard-tick--delivered': (($store.msgStatus && $store.msgStatus[{{ (int) $messageId }}]) || '{{ $status }}') === 'delivered',
            'yard-tick--sent': (($store.msgStatus && $store.msgStatus[{{ (int) $messageId }}]) || '{{ $status }}') === 'sent',
            'yard-tick--sending': (($store.msgStatus && $store.msgStatus[{{ (int) $messageId }}]) || '{{ $status }}') === 'sending',
        }"
    @else
        data-status="{{ $status }}"
        class="yard-tick--{{ $status }}"
    @endif
    {{ $attributes->merge(['class' => 'yard-tick']) }}
    aria-label="Message status: {{ $status }}"
>
    {{-- Sending: small clock icon --}}
    <template x-if="(($store.msgStatus && $store.msgStatus[{{ (int) ($messageId ?? 0) }}]) || '{{ $status }}') === 'sending'">
        <svg class="yard-tick__svg yard-tick__svg--clock" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="8" cy="8" r="6.25" stroke="currentColor" stroke-width="1.4"/>
            <path d="M8 4.5V8L10.5 9.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </template>

    {{-- Sent: single check --}}
    <template x-if="(($store.msgStatus && $store.msgStatus[{{ (int) ($messageId ?? 0) }}]) || '{{ $status }}') === 'sent'">
        <svg class="yard-tick__svg yard-tick__svg--single" viewBox="0 0 18 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M2 7.5L6.5 12L16 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </template>

    {{-- Delivered: double check (grey) --}}
    <template x-if="(($store.msgStatus && $store.msgStatus[{{ (int) ($messageId ?? 0) }}]) || '{{ $status }}') === 'delivered'">
        <svg class="yard-tick__svg yard-tick__svg--double" viewBox="0 0 22 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M1 7.5L5.5 12L15 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M7 7.5L11.5 12L21 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </template>

    {{-- Read: double check (blue) --}}
    <template x-if="(($store.msgStatus && $store.msgStatus[{{ (int) ($messageId ?? 0) }}]) || '{{ $status }}') === 'read'">
        <svg class="yard-tick__svg yard-tick__svg--double yard-tick__svg--read" viewBox="0 0 22 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M1 7.5L5.5 12L15 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M7 7.5L11.5 12L21 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </template>
</span>
