<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Create a TURN relay credential and print the .env lines for it.
 *
 * Metered uses two different keys, and sending the wrong one is what kept
 * calls from connecting:
 *
 *   POST /api/v1/turn/credential   authenticates with ?secretKey=   (the
 *                                  account key on the Developers page)
 *   GET  /api/v1/turn/credentials  authenticates with ?apiKey=      (a key
 *                                  belonging to one credential)
 *
 * The secret key was configured as METERED_SECRET_KEY and sent as apiKey, so
 * the provider answered "Invalid API Key" and the app fell back to STUN, which
 * cannot carry media between two devices behind carrier NAT.
 *
 * This creates a credential with the secret key and prints both ways to use
 * it: the username and password to configure directly, or the credential's own
 * apiKey for the fetch-on-demand path.
 */
class CreateTurnCredential extends Command
{
    protected $signature = 'calls:turn-credential
        {--label= : A name to recognise this credential by in the dashboard}
        {--expires= : Seconds until it expires; omit for one that does not}';

    protected $description = 'Create a TURN relay credential at the provider and show how to configure it';

    public function handle(): int
    {
        $domain = config('services.metered.domain');
        $secret = config('services.metered.secret_key');

        if (! $domain || ! $secret) {
            $this->error('METERED_DOMAIN and METERED_SECRET_KEY must both be set.');

            return self::FAILURE;
        }

        $label = $this->option('label') ?: 'cameroon-network-' . now()->format('Y-m-d');

        $body = array_filter([
            'label' => $label,
            'expiryInSeconds' => $this->option('expires') ? (int) $this->option('expires') : null,
        ]);

        $this->line("Creating a credential on {$domain}…");

        try {
            $response = Http::timeout(15)
                ->post("https://{$domain}/api/v1/turn/credential?secretKey={$secret}", $body);
        } catch (\Throwable $e) {
            $this->error('Could not reach the provider: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error('Refused (' . $response->status() . '). The provider said:');
            $this->line('  ' . trim(substr($response->body(), 0, 400)));
            $this->newLine();
            $this->line('This endpoint wants the account secret key from the dashboard\'s');
            $this->line('Developers page, which is what METERED_SECRET_KEY should hold.');

            return self::FAILURE;
        }

        $created = $response->json();
        $username = $created['username'] ?? null;
        $password = $created['password'] ?? null;
        $apiKey = $created['apiKey'] ?? null;

        if (! $username || ! $password) {
            $this->error('The provider accepted the request but returned no credential:');
            $this->line('  ' . substr($response->body(), 0, 300));

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Created \"{$label}\".");
        $this->newLine();

        $urls = $this->relayUrls($apiKey);

        $this->line('Put these in .env, then run: php artisan config:cache');
        $this->newLine();
        $this->line('TURN_URLS="' . implode(',', $urls) . '"');
        $this->line('TURN_USERNAME=' . $username);
        $this->line('TURN_PASSWORD=' . $password);

        if ($apiKey) {
            $this->newLine();
            $this->line('This credential carries its own apiKey, which is what the');
            $this->line('fetch-on-demand path wants. It goes in its own variable, beside');
            $this->line('the account secret rather than over it:');
            $this->newLine();
            $this->line('METERED_API_KEY=' . $apiKey);
        }

        $this->newLine();
        $this->warn('A new credential takes up to 2 minutes to work across the provider\'s');
        $this->warn('network. Wait before testing a call, or it will look like it failed.');

        return self::SUCCESS;
    }

    /**
     * The relay URLs to offer the browser.
     *
     * Asked of the provider when the new credential's own key allows it, so the
     * hostnames are the ones assigned to this account rather than guesses. The
     * fallbacks are Metered's standard global relay: port 443 over TLS/TCP
     * matters most, since that is the one that survives a mobile network which
     * blocks UDP.
     *
     * @return string[]
     */
    private function relayUrls(?string $apiKey): array
    {
        $domain = config('services.metered.domain');

        if ($apiKey) {
            try {
                $response = Http::timeout(8)
                    ->get("https://{$domain}/api/v1/turn/credentials", ['apiKey' => $apiKey]);

                if ($response->successful() && is_array($response->json())) {
                    $urls = collect($response->json())
                        ->pluck('urls')
                        ->flatten()
                        ->filter(fn ($u) => str_starts_with((string) $u, 'turn'))
                        ->unique()
                        ->values()
                        ->all();

                    if ($urls !== []) {
                        return $urls;
                    }
                }
            } catch (\Throwable) {
                // Fall through to the documented defaults.
            }
        }

        $this->warn('Could not read the relay list from the provider; using their');
        $this->warn('standard hosts. Check these against the TURN Server page.');

        return [
            'turn:global.relay.metered.ca:80',
            'turn:global.relay.metered.ca:80?transport=tcp',
            'turn:global.relay.metered.ca:443',
            'turns:global.relay.metered.ca:443?transport=tcp',
        ];
    }
}
