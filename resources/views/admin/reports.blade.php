<x-layouts.admin :title="'Reports'">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold" x-text="$store.lang.t('Reports', 'Signalements')"></h1>
            <span class="text-sm text-slate-500">{{ $reports->total() }} total</span>
        </div>

        {{-- Reports List --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-semibold text-slate-600">#</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Type</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Reporter</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Reason</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Status</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Date</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($reports as $report)
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-4 py-3 text-slate-400 font-mono text-xs">{{ $report->id }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
                                    {{ class_basename($report->reportable_type ?? 'Unknown') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $report->reporter?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="text-sm text-slate-700">{{ $report->reason?->value ?? $report->reason ?? '—' }}</span>
                                @if($report->details)
                                    <p class="text-xs text-slate-400 mt-0.5 line-clamp-1">{{ $report->details }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-700',
                                        'under_review' => 'bg-blue-100 text-blue-700',
                                        'resolved' => 'bg-blue-100 text-blue-700',
                                        'dismissed' => 'bg-slate-100 text-slate-500',
                                    ];
                                    $statusVal = is_object($report->status) ? $report->status->value : $report->status;
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$statusVal] ?? 'bg-slate-100 text-slate-600' }}">
                                    {{ ucfirst(str_replace('_', ' ', $statusVal)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500 text-xs">{{ $report->created_at->format('M d, Y') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <button class="px-2 py-1 text-xs rounded bg-blue-50 text-blue-600 hover:bg-blue-100">Review</button>
                                    <button class="px-2 py-1 text-xs rounded bg-blue-50 text-blue-600 hover:bg-blue-100">Resolve</button>
                                    <button class="px-2 py-1 text-xs rounded bg-slate-50 text-slate-500 hover:bg-slate-100">Dismiss</button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                                <span x-text="$store.lang.t('No reports', 'Aucun signalement')"></span>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($reports->hasPages())
                <div class="px-4 py-3 border-t border-slate-200">
                    {{ $reports->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
