<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = \App\Models\Tenant::first();

        if (! $tenant) {
            $this->command->error('No tenant found. Run TenantSeeder first.');
            return;
        }

        app()->instance('currentTenant', $tenant);

        $admin = User::firstOrCreate(
            ['email' => 'admin@cameroon.community'],
            [
                'uuid'              => Str::uuid(),
                'tenant_id'         => $tenant->id,
                'name'              => 'Admin',
                'password'          => Hash::make('admin1234'),
                'email_verified_at' => now(),
                'is_active'         => true,
                'current_country'   => 'CM',
                'current_city'      => 'Douala',
                'current_region'    => 'Littoral',
                'language_pref'     => 'en',
            ]
        );

        if (! $admin->hasRole('super_admin')) {
            $admin->assignRole('super_admin');
        }

        $this->command->info("Admin user ready: admin@cameroon.community / admin1234");
    }
}
