<x-layouts.admin :title="'Moderation'">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold" x-text="$store.lang.t('Content Moderation', 'Modération de Contenu')"></h1>
            <span class="text-sm text-slate-500">{{ $flaggedMessages->total() }} flagged</span>
        </div>

        {{-- Flagged Messages --}}
        <div class="space-y-3">
            @if(session('success'))
            <div class="rounded-lg bg-blue-50 border border-blue-200 p-3 text-sm text-blue-700">{{ session('success') }}</div>
            @endif

            @forelse($flaggedMessages as $message)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5" x-data="{ expanded: false }">
                <div class="flex items-start gap-4">
                    {{-- User Avatar --}}
                    <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center text-red-500 font-bold text-sm shrink-0">
                        {{ strtoupper(substr($message->user?->name ?? '?', 0, 2)) }}
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-medium text-slate-900">{{ $message->user?->name ?? 'Deleted User' }}</span>
                            <span class="text-xs text-slate-400">in</span>
                            <span class="text-xs font-medium text-cm-green">{{ $message->room?->name ?? '—' }}</span>
                            <span class="text-xs text-slate-400">•</span>
                            <span class="text-xs text-slate-400">{{ $message->created_at->diffForHumans() }}</span>
                        </div>

                        {{-- Message Content --}}
                        <div class="p-3 bg-red-50/50 rounded-lg border border-red-100 mb-3">
                            @if($message->message_type->value === 'text')
                                <p class="text-sm text-slate-700">{{ Str::limit($message->content, 300) }}</p>
                            @elseif($message->message_type->value === 'image')
                                <p class="text-sm text-slate-500 italic">📷 Image message</p>
                            @else
                                <p class="text-sm text-slate-500 italic">{{ ucfirst($message->message_type->value) }} message</p>
                            @endif
                        </div>

                        {{-- AI Score Detail --}}
                        @if($message->ai_moderation_score)
                            <div class="flex items-center gap-3 mb-3">
                                <span class="text-xs text-slate-500">🤖 AI Score:</span>
                                @php $score = $message->ai_moderation_score; @endphp
                                <div class="flex-1 max-w-xs h-2 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full {{ $score >= 70 ? 'bg-red-500' : ($score >= 40 ? 'bg-yellow-500' : 'bg-blue-500') }}"
                                         style="width: {{ $score }}%"></div>
                                </div>
                                <span class="text-xs font-medium {{ $score >= 70 ? 'text-red-600' : ($score >= 40 ? 'text-yellow-600' : 'text-blue-600') }}">{{ $score }}%</span>
                            </div>
                            @if(is_array($message->ai_moderation_detail) && count($message->ai_moderation_detail) > 0)
                            <div class="flex flex-wrap gap-2 mb-3">
                                @foreach($message->ai_moderation_detail as $cat => $val)
                                    @if($val > 0.1)
                                    <span class="text-[10px] bg-slate-100 text-slate-600 rounded px-1.5 py-0.5">{{ $cat }}: {{ is_numeric($val) ? number_format($val * 100, 0) . '%' : $val }}</span>
                                    @endif
                                @endforeach
                            </div>
                            @endif
                        @endif

                        {{-- Actions --}}
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('admin.moderation.dismiss', $message) }}" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors">
                                    ✅ Dismiss
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.moderation.delete', $message) }}" class="inline"
                                  onsubmit="return confirm('Delete this message permanently?')">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors">
                                    🗑️ Delete Message
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center">
                <div class="text-4xl mb-3">🎉</div>
                <p class="text-slate-400 font-medium" x-text="$store.lang.t('No flagged content', 'Aucun contenu signalé')"></p>
                <p class="text-xs text-slate-400 mt-1" x-text="$store.lang.t('All clear! Community is behaving well.', 'Tout est clair ! La communauté se comporte bien.')"></p>
            </div>
            @endforelse
        </div>

        @if($flaggedMessages->hasPages())
            <div>{{ $flaggedMessages->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
