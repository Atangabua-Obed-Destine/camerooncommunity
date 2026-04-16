<x-layouts.admin :title="'Audit Log'">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold" x-text="$store.lang.t('Audit Log', 'Journal d\'Audit')"></h1>
            <a href="{{ route('admin.audit', ['export' => 'csv']) }}"
               class="px-4 py-2 bg-white border border-slate-200 text-sm font-medium rounded-lg hover:bg-slate-50 text-slate-700 transition-colors">
                📥 <span x-text="$store.lang.t('Export CSV', 'Exporter CSV')"></span>
            </a>
        </div>

        {{-- Log Table --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-left">
                            <th class="px-4 py-3 font-semibold text-slate-600">Date</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">User</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Action</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Subject</th>
                            <th class="px-4 py-3 font-semibold text-slate-600">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($logs as $log)
                        <tr class="hover:bg-slate-50/50" x-data="{ showDetails: false }">
                            <td class="px-4 py-3 text-slate-500 text-xs whitespace-nowrap">
                                {{ $log->created_at->format('M d, Y H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-slate-700">{{ $log->causer?->name ?? 'System' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $actionColors = [
                                        'created' => 'bg-blue-100 text-blue-700',
                                        'updated' => 'bg-blue-100 text-blue-700',
                                        'deleted' => 'bg-red-100 text-red-700',
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $actionColors[$log->description] ?? 'bg-slate-100 text-slate-600' }}">
                                    {{ ucfirst($log->description) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 text-xs">
                                {{ class_basename($log->subject_type ?? '') }} #{{ $log->subject_id }}
                            </td>
                            <td class="px-4 py-3">
                                @if($log->properties && $log->properties->count())
                                    <button @click="showDetails = !showDetails"
                                            class="text-xs text-cm-green hover:underline">
                                        <span x-text="showDetails ? 'Hide' : 'Show'"></span> changes
                                    </button>
                                    <div x-show="showDetails" x-transition class="mt-2 p-2 bg-slate-50 rounded-lg text-xs font-mono text-slate-600 max-w-md overflow-auto">
                                        <pre>{{ json_encode($log->properties->toArray(), JSON_PRETTY_PRINT) }}</pre>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center">
                                <div class="text-3xl mb-2">📋</div>
                                <p class="text-slate-400" x-text="$store.lang.t('No audit entries yet', 'Aucune entrée d\'audit encore')"></p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
                <div class="px-4 py-3 border-t border-slate-200">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
