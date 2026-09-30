<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\RemoteAvatar;
use Illuminate\Console\Command;

/**
 * Repairs accounts created before avatars were copied locally.
 *
 * Their `avatar` column holds an absolute Google URL, which every view turns
 * into /storage/https://lh3... — a 404 and a broken image wherever that person
 * appears. Anything that cannot be downloaded is cleared, so the UI falls back
 * to the coloured initial instead of a broken image.
 */
class LocaliseRemoteAvatars extends Command
{
    protected $signature = 'users:localise-avatars {--dry-run : List what would change without writing}';

    protected $description = 'Copy remote (Google) profile pictures onto local storage';

    public function handle(): int
    {
        $users = User::withoutGlobalScopes()
            ->where('avatar', 'like', 'http%')
            ->get(['id', 'username', 'avatar']);

        if ($users->isEmpty()) {
            $this->info('No remote avatars found.');

            return self::SUCCESS;
        }

        $this->info($users->count() . ' account(s) with a remote avatar.');
        $copied = $cleared = 0;

        foreach ($users as $user) {
            if ($this->option('dry-run')) {
                $this->line('  would fetch: ' . ($user->username ?? $user->id));
                continue;
            }

            $path = RemoteAvatar::fetch($user->avatar);

            User::withoutGlobalScopes()->whereKey($user->id)->update(['avatar' => $path]);

            if ($path) {
                $copied++;
                $this->line('  copied: ' . ($user->username ?? $user->id) . ' -> ' . $path);
            } else {
                $cleared++;
                $this->line('  cleared (unreachable): ' . ($user->username ?? $user->id));
            }
        }

        if (! $this->option('dry-run')) {
            $this->info("Done. {$copied} copied, {$cleared} cleared.");
        }

        return self::SUCCESS;
    }
}
