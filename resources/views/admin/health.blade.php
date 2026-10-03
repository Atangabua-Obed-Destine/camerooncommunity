<x-layouts.admin :title="'System health'">
    @php
        // One place decides what "bad" looks like, so the page reads consistently.
        $realtimeOk = $realtime['ok'] && $realtime['ms'] < \App\Services\RealtimeProbe::SLOW_MS;
        $debugBad   = $config['debug'] && $config['env'] === 'production';
    @endphp

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold">System health</h1>
            <p class="text-sm text-slate-500">
                The parts that fail quietly: realtime, the queue, and configuration that only
                bites in production.
            </p>
        </div>

        {{-- Realtime. Every event in the app is ShouldBroadcastNow, so this
             latency is added to each message send. --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-slate-900">Realtime (Reverb)</h2>
                    <p class="text-sm text-slate-500">Publishing to {{ $realtime['endpoint'] }} · app {{ $realtime['appId'] ?: '(unset)' }}</p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-bold
                      {{ $realtimeOk ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                    {{ $realtime['ok'] ? ($realtimeOk ? 'Healthy' : 'Slow') : 'Unreachable' }}
                </span>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Publish time</dt>
                    <dd class="font-bold {{ $realtimeOk ? 'text-slate-900' : 'text-rose-600' }}">{{ $realtime['ms'] }} ms</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Driver</dt>
                    <dd class="font-bold text-slate-900">{{ $realtime['driver'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Server</dt>
                    <dd class="font-bold {{ $realtime['local'] ? 'text-slate-900' : 'text-amber-600' }}">
                        {{ $realtime['local'] ? 'Local' : 'Remote' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Checked</dt>
                    <dd class="font-bold text-slate-900">just now</dd>
                </div>
            </dl>

            @if($realtime['error'])
                <p class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-xs font-mono text-rose-700">{{ $realtime['error'] }}</p>
                <p class="mt-2 text-xs text-slate-500">
                    Messages will still save, but they will not arrive and calls will not ring
                    until this is fixed. Check that <code>reverb:start</code> is running.
                </p>
            @elseif(! $realtime['local'])
                <p class="mt-3 text-xs text-amber-700">
                    Reverb is not local to this server, so every broadcast makes an external
                    round trip while the user waits.
                </p>
            @endif
        </div>

        {{-- Queue --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-900">Queue</h2>
            <dl class="mt-4 grid grid-cols-3 gap-4 text-sm">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Driver</dt>
                    <dd class="font-bold text-slate-900">{{ $queue['driver'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Pending</dt>
                    <dd class="font-bold text-slate-900">{{ $queue['pending'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Failed</dt>
                    <dd class="font-bold {{ ($queue['failed'] ?? 0) > 0 ? 'text-rose-600' : 'text-slate-900' }}">
                        {{ $queue['failed'] ?? '—' }}
                    </dd>
                </div>
            </dl>

            @if($queue['recentFailures']->isNotEmpty())
                <ul class="mt-4 space-y-2">
                    @foreach($queue['recentFailures'] as $failure)
                        <li class="rounded-lg bg-slate-50 px-3 py-2 text-xs">
                            <span class="font-semibold text-slate-700">{{ $failure->failed_at }}</span>
                            <span class="block font-mono text-slate-500">{{ \Illuminate\Support\Str::limit($failure->exception, 160) }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs text-slate-500">Retry with <code>php artisan queue:retry all</code>.</p>
            @endif
        </div>

        {{-- Configuration --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-900">Configuration</h2>

            @if($debugBad)
                <p class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">
                    APP_DEBUG is on in production. Any unhandled error prints the whole
                    environment — including every key and password — to whoever triggered it.
                </p>
            @endif

            @unless($config['configCached'])
                <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    Config is not cached, so every request reads <code>.env</code> from disk. If
                    that file is ever briefly unreadable the request falls back to framework
                    defaults and fails. Run <code>php artisan config:cache</code>.
                </p>
            @endunless

            <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 text-sm">
                @foreach([
                    'Environment'   => $config['env'],
                    'Debug'         => $config['debug'] ? 'on' : 'off',
                    'App URL'       => $config['url'],
                    'Config cached' => $config['configCached'] ? 'yes' : 'no',
                    'Storage link'  => $config['storageLinked'] ? 'present' : 'missing',
                    'Build'         => $config['buildManifest'] ?: 'no manifest',
                    'Cache'         => $config['cacheDriver'],
                    'Sessions'      => $config['sessionDriver'],
                    'Broadcasting'  => $config['broadcastDriver'],
                ] as $label => $value)
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                        <dd class="font-bold text-slate-900 break-words">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- Recent errors --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <h2 class="font-semibold text-slate-900">Recent errors</h2>
            <p class="text-sm text-slate-500">Grouped from the tail of today's log, most frequent first.</p>

            @if(empty($errors))
                <p class="mt-4 text-sm text-emerald-700">Nothing logged recently.</p>
            @else
                <ul class="mt-4 space-y-2">
                    @foreach($errors as $message => $count)
                        <li class="flex items-start gap-3 rounded-lg bg-slate-50 px-3 py-2">
                            <span class="shrink-0 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-700">{{ $count }}&times;</span>
                            <span class="font-mono text-xs text-slate-600">{{ $message }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-layouts.admin>
