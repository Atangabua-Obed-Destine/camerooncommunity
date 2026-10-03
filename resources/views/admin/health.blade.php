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

        {{-- Browser side. The probe above proves PHP can hand an event to
             Reverb; this proves one actually reaches a browser, which is the
             half that fails silently behind a proxy or CDN. --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5"
             x-data="realtimeBrowserCheck()" x-init="watch()">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-slate-900">Realtime (this browser)</h2>
                    <p class="text-sm text-slate-500">Dialling <span class="font-mono" x-text="endpoint"></span></p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-bold"
                      :class="{
                          'bg-emerald-50 text-emerald-700': state === 'connected',
                          'bg-amber-50 text-amber-700': state === 'connecting',
                          'bg-rose-50 text-rose-700': state !== 'connected' && state !== 'connecting',
                      }"
                      x-text="state"></span>
            </div>

            @if($edge)
                <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    This request came through <strong>{{ $edge }}</strong>. A proxy in front of
                    the site must be configured to allow WebSockets, or the handshake dies at
                    the edge — which looks exactly like this while a check run on the server
                    itself passes.
                </p>
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="button" @click="ping()" :disabled="busy"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50">
                    <span x-text="busy ? 'Waiting…' : 'Send test event'"></span>
                </button>
                <span class="text-sm" :class="result.ok ? 'text-emerald-700' : 'text-rose-700'" x-text="result.message"></span>
            </div>

            <p x-show="socketError" x-cloak class="mt-3 rounded-lg bg-rose-50 px-3 py-2 font-mono text-xs text-rose-700"
               x-text="'socket: ' + socketError"></p>

            <p class="mt-3 text-xs text-slate-500">
                The server publishes an event on your private health channel and this page
                listens for it. Published but never received means the break is between
                Reverb and the browser — a proxy, a CDN with websockets off, or a firewall —
                not in the application.
            </p>
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
    @push('scripts')
    <script>
        function realtimeBrowserCheck() {
            return {
                state: 'unknown',
                endpoint: '(unknown)',
                socketError: '',
                busy: false,
                result: { ok: false, message: '' },
                _heard: false,

                watch() {
                    const echo = window.Echo;
                    if (! echo) {
                        this.state = 'no Echo on this page';
                        return;
                    }

                    const options = echo.connector?.options ?? {};
                    const scheme = options.forceTLS ? 'wss' : 'ws';
                    const port = options.forceTLS ? (options.wssPort ?? 443) : (options.wsPort ?? 80);
                    this.endpoint = `${scheme}://${options.wsHost}:${port}`;

                    const connection = echo.connector?.pusher?.connection;
                    if (! connection) return;

                    this.state = connection.state;
                    connection.bind('state_change', (s) => { this.state = s.current; });

                    // The close code is the most diagnostic thing available:
                    // 4001 = unknown app key, 4004 = over limit, 1006 = the
                    // connection was cut without a close frame, which is what a
                    // proxy or firewall blocking the upgrade looks like.
                    connection.bind('error', (err) => {
                        const data = err?.error?.data ?? err?.data ?? {};
                        const code = data.code ?? err?.code ?? '';
                        const message = data.message ?? err?.error?.message ?? err?.message ?? '';
                        this.socketError = [code, message].filter(Boolean).join(' · ') || 'connection error';
                    });

                    // Our own channel, so nothing else can make this look healthy.
                    echo.channel('admin-health.{{ auth()->id() }}')
                        .listen('.HealthPing', () => { this._heard = true; });
                },

                async ping() {
                    this.busy = true;
                    this._heard = false;
                    this.result = { ok: false, message: '' };

                    const started = performance.now();

                    try {
                        const res = await fetch('{{ route('admin.health.ping') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']')?.content ?? '',
                            },
                            body: JSON.stringify({ token: String(Date.now()) }),
                        });

                        const data = await res.json();

                        if (! data.ok) {
                            this.result = { ok: false, message: 'Server could not publish: ' + (data.error ?? 'unknown error') };
                            this.busy = false;
                            return;
                        }

                        // Give the websocket a moment to deliver it.
                        await new Promise((resolve) => setTimeout(resolve, 2500));

                        const ms = Math.round(performance.now() - started);

                        this.result = this._heard
                            ? { ok: true, message: `Received in ${ms} ms — realtime is working end to end.` }
                            : { ok: false, message: `Published in ${data.ms} ms but never arrived. The break is between Reverb and this browser.` };
                    } catch (e) {
                        this.result = { ok: false, message: 'Request failed: ' + e.message };
                    }

                    this.busy = false;
                },
            };
        }
    </script>
    @endpush
</x-layouts.admin>
