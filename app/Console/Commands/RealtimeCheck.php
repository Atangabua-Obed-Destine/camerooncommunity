<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Broadcast;

/**
 * Checks the half of realtime that a browser cannot show you: whether PHP can
 * publish an event to Reverb, how long that takes, and which server it is
 * actually talking to.
 *
 * Every broadcast in this app is ShouldBroadcastNow, so publishing happens
 * inline during the web request. If it is slow, sending a message is slow for
 * everyone; if it fails, messages save but never arrive, calls never ring, and
 * missed calls only appear on the next page load.
 */
class RealtimeCheck extends Command
{
    protected $signature = 'realtime:check
        {--channel=diagnostic-channel : Channel to publish the test event on}
        {--event=DiagEvent : Event name to publish, e.g. CallStarted}
        {--user= : Publish to this user id\'s personal call channel instead}';

    protected $description = 'Publish a test event to Reverb and report timing and configuration';

    public function handle(): int
    {
        $options = config('broadcasting.connections.reverb.options', []);
        $scheme = $options['scheme'] ?? 'https';
        $host = $options['host'] ?? '(unset)';
        $port = $options['port'] ?? '(unset)';

        $this->line('Broadcasting driver : ' . config('broadcasting.default'));
        $this->line('Publishing to       : ' . $scheme . '://' . $host . ':' . $port);
        $this->line('App id              : ' . (config('broadcasting.connections.reverb.app_id') ?: '(unset)'));

        // A publish that leaves the machine is the usual cause of slow sending:
        // the web request waits for it.
        if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $this->warn('  ! Reverb is not local to this server. Every broadcast makes an');
            $this->warn('    external round trip while the user waits. Set REVERB_HOST=127.0.0.1,');
            $this->warn('    REVERB_PORT=8080, REVERB_SCHEME=http unless Reverb runs elsewhere.');
        }

        $channel = (string) $this->option('channel');
        $event = (string) $this->option('event');

        // The question that matters when calls do not ring: can THIS user's
        // browser receive an event on the exact channel its call engine joined?
        if ($userId = $this->option('user')) {
            $user = \App\Models\User::withoutGlobalScopes()->find($userId);

            if (! $user) {
                $this->error("No user {$userId}.");

                return self::FAILURE;
            }

            $channel = 'tenant.' . $user->tenant_id . '.user.' . $user->id . '.calls';
            $this->line('Target user         : ' . ($user->username ?: $user->id) . ' (tenant ' . $user->tenant_id . ')');
        }

        $started = microtime(true);

        try {
            Broadcast::connection('reverb')->broadcast([$channel], $event, [
                'ok' => true,
                'at' => now()->toIso8601String(),
                // Enough shape for the call engine to log it as an incoming call.
                'call_uuid' => 'diagnostic',
                'call_id' => 0,
                'call_type' => 'voice',
                'room_id' => 0,
                'caller_name' => 'Realtime check',
                'initiated_by' => -1,
            ]);
        } catch (\Throwable $e) {
            $this->error('Publish FAILED: ' . $e->getMessage());
            $this->line('Reverb is not reachable from PHP. Messages will save but never arrive,');
            $this->line('and calls will not ring. Check that reverb:start is running on that port.');

            return self::FAILURE;
        }

        $ms = round((microtime(true) - $started) * 1000);
        $this->info("Published '{$event}' on '{$channel}' in {$ms}ms.");

        if ($ms > 250) {
            $this->warn('  ! That is slow for a local publish; it is added to every message send.');
        }

        $this->newLine();
        $this->line('This proves PHP can reach Reverb. To prove a browser can receive it,');
        $this->line('subscribe to the same channel while running this command again:');
        $this->line('  wscat -c "wss://' . (request()->getHost() ?: 'your-domain') . '/app/' . config('broadcasting.connections.reverb.key') . '?protocol=7&client=cli&version=1"');
        $this->line('  then send: {"event":"pusher:subscribe","data":{"auth":"","channel":"' . $channel . '"}}');

        return self::SUCCESS;
    }
}
