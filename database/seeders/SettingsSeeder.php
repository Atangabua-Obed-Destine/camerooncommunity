<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('domain', 'camerooncommunity.net')->first();

        if (! $tenant) {
            return;
        }

        // Bind tenant so BelongsToTenant trait works during seeding
        app()->instance('currentTenant', $tenant);

        foreach (config('cameroon.default_settings') as $key => $value) {
            PlatformSetting::firstOrCreate(
                ['tenant_id' => $tenant->id, 'key' => $key],
                ['value' => $value],
            );
        }
    }
}
