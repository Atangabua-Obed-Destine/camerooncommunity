<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Ask the TURN provider for credentials and report exactly what came back.
 *
 * A call between two devices on different networks needs a TURN relay; without
 * one the peers find no media path and the call connects and then goes silent.
 * When the provider answers 401 the application quietly falls back to STUN, so
 * the only trace is one warning line in the log and calls that "just don't
 * work". This asks the question directly and prints the answer, including the
 * provider's own error message, which is what says whether the key is wrong,
 * the account is out of quota, or the domain does not match the key.
 *
 * It never prints the key itself — only enough to tell two keys apart.
 */
class CheckTurnCredentials extends Command
{
    protected $signature = 'calls:turn-check {--fresh : Ignore the 5-minute cache}';

    protected $description = 'Check that the TURN provider is handing out relay credentials';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            Cache::forget('turn_credentials');
            $this->line('Cleared the cached credentials.');
        }

        $static = config('services.turn.urls');

        if ($static) {
            $this->info('Static TURN configured (TURN_URLS):');
            foreach ((array) $static as $url) {
                $this->line('  ' . $url);
            }
            $this->line('  username: ' . (config('services.turn.username') ? 'set' : 'MISSING'));
            $this->line('  password: ' . (config('services.turn.password') ? 'set' : 'MISSING'));
            $this->newLine();
        }

        $domain = config('services.metered.domain');
        $key = config('services.metered.secret_key');

        if (! $domain || ! $key) {
            $this->warn('Metered is not configured (METERED_DOMAIN / METERED_SECRET_KEY).');

            return $static ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Metered');
        $this->line('  domain : ' . $domain);
        $this->line('  api key: …' . substr($key, -4) . ' (' . strlen($key) . ' chars)');

        try {
            $response = Http::timeout(8)->get(
                "https://{$domain}/api/v1/turn/credentials",
                ['apiKey' => $key]
            );
        } catch (\Throwable $e) {
            $this->error('  could not reach the provider: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->line('  status : ' . $response->status());

        if ($response->successful() && is_array($response->json())) {
            $servers = $response->json();
            $relays = collect($servers)->pluck('urls')->flatten()->filter(
                fn ($u) => str_starts_with((string) $u, 'turn')
            );

            $this->newLine();
            $this->info('  Working. ' . $relays->count() . ' relay URL(s):');
            foreach ($relays as $url) {
                $this->line('    ' . $url);
            }

            if ($relays->isEmpty()) {
                $this->warn('    None of them are turn: URLs — STUN alone will not cross carrier NAT.');
            }

            return self::SUCCESS;
        }

        // The provider's own message is the useful part.
        $this->newLine();
        $this->error('  Refused. The provider said:');
        $this->line('    ' . trim(substr($response->body(), 0, 400)));
        $this->newLine();

        $this->line(match ($response->status()) {
            401, 403 => '  401/403 means the key was not accepted. If a freshly regenerated key is '
                . 'also refused, the credentials API wants a different key than the account secret '
                . 'on the Developers page — and the quicker way through is to stop asking for '
                . 'credentials at all: create a fixed TURN credential in the dashboard and put its '
                . 'username and password in TURN_USERNAME / TURN_PASSWORD, with the relay URLs in '
                . 'TURN_URLS. Those are used directly, no API call involved.',
            404      => '  404 usually means METERED_DOMAIN is wrong; it is the app subdomain, '
                . 'something like yourapp.metered.live.',
            429      => '  429 is the free tier running out. The account needs topping up.',
            default  => '  Until this returns credentials, calls between two different networks '
                . 'will not find a media path.',
        });

        $this->newLine();
        if ($static) {
            $this->newLine();
            $this->info('  Calls still work: the relay configured above is served to the browser');
            $this->info('  ahead of anything the provider returns, so this refusal costs nothing.');

            return self::SUCCESS;
        }

        $this->line('  Independent of the provider, TURN_URLS / TURN_USERNAME / TURN_PASSWORD in .env');
        $this->line('  point the app at any other relay, including a coturn on this server.');

        return self::FAILURE;
    }
}
