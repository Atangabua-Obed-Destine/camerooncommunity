<x-layouts.admin :title="'AI Management'">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold" x-text="$store.lang.t('AI Management', 'Gestion IA')"></h1>
            @php $aiAvailable = app(\App\Services\AIService::class)->isAvailable(); @endphp
            <span class="text-xs font-medium rounded-full px-3 py-1
                {{ $aiAvailable ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700' }}">
                {{ $aiAvailable ? '🟢 AI Active' : '🔴 AI Offline' }}
            </span>
        </div>

        @if(session('success'))
        <div class="rounded-lg bg-blue-50 border border-blue-200 p-3 text-sm text-blue-700">{{ session('success') }}</div>
        @endif

        {{-- Stats Row --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center text-purple-600 text-lg">🤖</div>
                    <div>
                        <p class="text-xs text-slate-500" x-text="$store.lang.t('AI Conversations', 'Conversations IA')"></p>
                        <p class="text-xl font-bold text-slate-900">{{ number_format($conversationCount) }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center text-red-600 text-lg">🛡️</div>
                    <div>
                        <p class="text-xs text-slate-500" x-text="$store.lang.t('Flagged Messages', 'Messages Signalés')"></p>
                        <p class="text-xl font-bold text-slate-900">{{ number_format($moderationCount) }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600 text-lg">⚙️</div>
                    <div>
                        <p class="text-xs text-slate-500" x-text="$store.lang.t('Model', 'Modèle')"></p>
                        <p class="text-sm font-bold text-slate-900">{{ \App\Models\PlatformSetting::getValue('openai_model', 'gpt-4o-mini') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.ai.update') }}" class="space-y-6">
            @csrf

            {{-- AI Toggle --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-semibold text-slate-900" x-text="$store.lang.t('AI Features', 'Fonctionnalités IA')"></h2>
                        <p class="text-xs text-slate-500 mt-1">Toggle all AI features on or off</p>
                    </div>
                    <select name="openai_enabled" class="rounded-lg border-slate-300 text-sm focus:ring-cm-green focus:border-cm-green">
                        <option value="true" {{ \App\Models\PlatformSetting::getValue('openai_enabled', 'true') === 'true' ? 'selected' : '' }}>Enabled</option>
                        <option value="false" {{ \App\Models\PlatformSetting::getValue('openai_enabled', 'true') === 'false' ? 'selected' : '' }}>Disabled</option>
                    </select>
                </div>
            </div>

            {{-- Kamer AI Prompt Editor --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h2 class="font-semibold text-slate-900 mb-4" x-text="$store.lang.t('Kamer AI System Prompt', 'Prompt Système Kamer IA')"></h2>
                <textarea name="ai_system_prompt" rows="8"
                          class="w-full rounded-lg border-slate-300 text-sm font-mono focus:ring-cm-green focus:border-cm-green"
                          placeholder="Enter the system prompt for Kamer AI...">{{ \App\Models\PlatformSetting::getValue('ai_system_prompt', '') }}</textarea>
                <p class="text-xs text-slate-400 mt-2">Defines Kamer's personality. Leave blank to use the built-in default.</p>
            </div>

            {{-- Moderation Rules --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h2 class="font-semibold text-slate-900 mb-4" x-text="$store.lang.t('AI Moderation Thresholds', 'Seuils de Modération IA')"></h2>
                <div class="space-y-4">
                    @php
                        $rules = [
                            ['key' => 'auto_flag_threshold', 'label' => 'Auto-flag threshold', 'default' => 70, 'desc' => 'Messages above this score are auto-flagged for review'],
                            ['key' => 'auto_delete_threshold', 'label' => 'Auto-delete threshold', 'default' => 95, 'desc' => 'Messages above this are auto-hidden (extremely harmful)'],
                            ['key' => 'solidarity_risk_threshold', 'label' => 'Solidarity risk alert', 'default' => 60, 'desc' => 'Campaigns above this are marked for closer review'],
                        ];
                    @endphp
                    @foreach($rules as $rule)
                    <div class="flex items-center justify-between py-3 border-b border-slate-100 last:border-0">
                        <div>
                            <p class="text-sm font-medium text-slate-700">{{ $rule['label'] }}</p>
                            <p class="text-xs text-slate-400">{{ $rule['desc'] }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="number" name="{{ $rule['key'] }}" min="0" max="100"
                                   value="{{ \App\Models\PlatformSetting::getValue($rule['key'], $rule['default']) }}"
                                   class="w-20 rounded-lg border-slate-300 text-sm text-center focus:ring-cm-green focus:border-cm-green">
                            <span class="text-sm text-slate-400">%</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-6 py-2.5 bg-cm-green text-white text-sm font-semibold rounded-lg hover:bg-cm-green/90 transition-colors">
                    <span x-text="$store.lang.t('Save AI Settings', 'Enregistrer les Paramètres IA')"></span>
                </button>
            </div>
        </form>

        {{-- Recent AI Flagged Activity --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-900 mb-4" x-text="$store.lang.t('Recent AI Flags', 'Signalements IA Récents')"></h2>
            @forelse($recentFlagged as $msg)
            <div class="flex items-center gap-3 py-2 border-b border-slate-100 last:border-0">
                <div class="w-8 h-8 rounded-full bg-red-50 flex items-center justify-center text-xs font-bold text-red-500">
                    {{ strtoupper(substr($msg->user?->name ?? '?', 0, 2)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-slate-700 truncate">{{ Str::limit($msg->content, 60) }}</p>
                    <p class="text-xs text-slate-400">{{ $msg->user?->name ?? 'Unknown' }} · {{ $msg->room?->name ?? '—' }} · {{ $msg->created_at->diffForHumans() }}</p>
                </div>
                @if($msg->ai_moderation_score)
                <span class="text-xs font-bold {{ $msg->ai_moderation_score >= 70 ? 'text-red-600' : 'text-yellow-600' }}">
                    {{ $msg->ai_moderation_score }}%
                </span>
                @endif
            </div>
            @empty
            <div class="text-center py-6 text-slate-400">
                <div class="text-3xl mb-2">🎉</div>
                <p class="text-sm" x-text="$store.lang.t('No AI flags yet — the community is behaving!', 'Aucun signalement IA — la communauté se comporte bien !')"></p>
            </div>
            @endforelse
        </div>
    </div>
</x-layouts.admin>
