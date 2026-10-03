<?php

namespace App\Services;

use Illuminate\Support\Facades\Broadcast;

/**
 * Can PHP reach the websocket server, and how fast?
 *
 * Every event in this app is ShouldBroadcastNow, so publishing happens inline
 * in the web request: if it is slow, sending a message is slow for everyone,
 * and if it fails, messages save but never arrive and calls never ring. That
 * failure is invisible from the browser, which is why it gets a probe.
 *
 * Shared by the `realtime:check` command and the admin health page so both
 * report the same thing.
 */
class RealtimeProbe
{
    /** Over this, the delay is being added to every single message send. */
    public const SLOW_MS = 250;

    /**
     * @return array{ok: bool, ms: int|null, driver: string, endpoint: string, appId: ?string, local: bool, error: ?string}
     */
    public function run(string $channel = 'diagnostic-channel', string $event = 'DiagEvent'): array
    {
        $options  = config('broadcasting.connections.reverb.options', []);
        $host     = $options['host'] ?? '(unset)';
        $endpoint = ($options['scheme'] ?? 'https') . '://' . $host . ':' . ($options['port'] ?? '?');

        $result = [
            'ok'       => false,
            'ms'       => null,
            'driver'   => (string) config('broadcasting.default'),
            'endpoint' => $endpoint,
            'appId'    => config('broadcasting.connections.reverb.app_id'),
            // A non-local endpoint means every broadcast leaves the machine
            // while the user waits.
            'local'    => in_array($host, ['127.0.0.1', 'localhost', '::1'], true),
            'error'    => null,
        ];

        $started = microtime(true);

        try {
            Broadcast::connection('reverb')->broadcast([$channel], $event, [
                'ok' => true,
                'at' => now()->toIso8601String(),
            ]);

            $result['ok'] = true;
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        $result['ms'] = (int) round((microtime(true) - $started) * 1000);

        return $result;
    }
}
