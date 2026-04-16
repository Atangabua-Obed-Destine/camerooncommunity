@php
    $progress = $campaign->progressPercent;
    $categoryIcons = [
        'bereavement' => '🕊️',
        'medical' => '🏥',
        'disaster' => '🌊',
        'education' => '🎓',
        'repatriation' => '✈️',
        'other' => '🤝',
    ];
    $icon = $categoryIcons[$campaign->category?->value ?? 'other'] ?? '🤲';
@endphp
<div class="bg-gradient-to-br from-slate-50 to-white rounded-xl border border-slate-200 overflow-hidden w-[320px]">
    {{-- Header --}}
    <div class="px-4 py-2.5 bg-cm-red/5 border-b border-cm-red/10 flex items-center gap-2">
        <span>{{ $icon }}</span>
        <span class="text-xs font-bold uppercase tracking-wider text-cm-red">Solidarity</span>
        <span class="text-xs text-slate-400">· {{ $campaign->category?->name ?? 'Other' }}</span>
    </div>

    {{-- Body --}}
    <div class="p-4 space-y-3">
        <h4 class="font-bold text-slate-900 text-sm leading-tight">{{ $campaign->title }}</h4>
        <p class="text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($campaign->description, 120) }}</p>

        {{-- Progress --}}
        <div>
            <div class="h-2 rounded-full bg-slate-200 overflow-hidden">
                <div class="h-full rounded-full bg-cm-green transition-all duration-700" style="width: {{ min($progress, 100) }}%"></div>
            </div>
            <div class="flex items-center justify-between mt-1.5 text-[11px] text-slate-500">
                <span>{{ $campaign->currency ?? '£' }}{{ number_format($campaign->current_amount, 2) }} / {{ $campaign->currency ?? '£' }}{{ number_format($campaign->target_amount, 2) }}</span>
                <span>{{ $campaign->contributor_count ?? 0 }} <span x-text="$store.lang.t('contributors', 'contributeurs')"></span></span>
            </div>
            @if($campaign->daysRemaining !== null)
            <p class="text-[11px] text-slate-400 mt-0.5">
                {{ $campaign->daysRemaining }} <span x-text="$store.lang.t('days left', 'jours restants')"></span>
            </p>
            @endif
        </div>

        {{-- Action --}}
        <a href="#contribute-{{ $campaign->id }}" class="block w-full text-center rounded-lg bg-cm-green py-2 text-xs font-bold text-white hover:bg-cm-green-light transition-colors">
            <span x-text="$store.lang.t('Contribute Now', 'Contribuer Maintenant')"></span>
        </a>
    </div>
</div>
