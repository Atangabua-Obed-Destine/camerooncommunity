<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::firstOrCreate(
            ['domain' => 'camerooncommunity.net'],
            [
                'name' => 'Cameroon Community',
                'slug' => 'cameroon-community',
                'domain' => 'camerooncommunity.net',
                'country' => 'Cameroon',
                'flag_emoji' => '🇨🇲',
                'primary_color' => '#006B3F',
                'accent_color' => '#CE1126',
                'tertiary_color' => '#FCD116',
                'language' => 'bilingual',
                'plan' => 'owned',
                'solidarity_platform_cut_percent' => 5.00,
                'is_active' => true,
            ]
        );
    }
}
