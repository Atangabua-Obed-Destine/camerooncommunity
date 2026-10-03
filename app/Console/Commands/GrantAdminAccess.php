<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Grants the narrow admin permissions to a person, and shows what they hold.
 *
 * The admin panel gates location history, impersonation and system health on
 * permissions rather than the admin role, so they can be handed out precisely.
 * The flip side is a 403 that looks like a broken page when the permission was
 * never granted — or was granted but Spatie's permission cache still holds the
 * old set, which is easy to miss after a deploy. This does both and resets the
 * cache.
 */
class GrantAdminAccess extends Command
{
    protected $signature = 'admin:grant
        {email : The account to change}
        {--permission=* : Specific permissions; defaults to all admin permissions}
        {--show : Only report what this account already has}';

    protected $description = 'Grant admin panel permissions to a user (and reset the permission cache)';

    public function handle(): int
    {
        $user = User::withoutGlobalScopes()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No account with that email.');

            return self::FAILURE;
        }

        // A permission that does not exist yet cannot be granted, and the
        // seeder is easy to forget on a deploy.
        (new PermissionSeeder())->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($this->option('show')) {
            return $this->report($user);
        }

        $wanted = $this->option('permission') ?: array_keys(PermissionSeeder::PERMISSIONS);

        foreach ($wanted as $name) {
            if (! Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
                $this->warn("Skipping unknown permission: {$name}");
                continue;
            }

            $user->givePermissionTo($name);
            $this->line("  granted: {$name}");
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();

        return $this->report($user->fresh());
    }

    private function report(User $user): int
    {
        $this->info($user->email);
        $this->line('  roles      : ' . ($user->getRoleNames()->join(', ') ?: '(none)'));
        $this->line('  permissions: ' . ($user->getAllPermissions()->pluck('name')->join(', ') ?: '(none)'));

        return self::SUCCESS;
    }
}
