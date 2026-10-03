<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions for the admin powers that need to be narrower than "admin".
 *
 * Roles alone were too blunt for these: following a member's movements and
 * signing in as them are not things every admin should be able to do, and the
 * line between roles should be movable without a code change. Both are granted
 * to super_admin here and can be given to any other role from the database.
 */
class PermissionSeeder extends Seeder
{
    /** permission => what it unlocks (kept here so the list is self-documenting). */
    public const PERMISSIONS = [
        'view_user_location' => 'See exact coordinates and a member\'s movement history',
        'impersonate_users'  => 'Sign in as another member to reproduce a problem',
        'view_system_health' => 'See realtime, queue and configuration diagnostics',
    ];

    public function run(): void
    {
        foreach (array_keys(self::PERMISSIONS) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $superAdmin = Role::where('name', 'super_admin')->where('guard_name', 'web')->first();

        if ($superAdmin) {
            $superAdmin->givePermissionTo(array_keys(self::PERMISSIONS));
        }
    }
}
