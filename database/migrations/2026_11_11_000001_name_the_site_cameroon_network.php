<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The platform is Cameroon Network (owner, 2026-11-11). The site_name setting,
 * which every page and the app's /configuration display, still said
 * "Cameroon Community". Only that exact value changes: a name set in the admin
 * panel since is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('platform_settings')
            ->where('key', 'site_name')
            ->where('value', 'Cameroon Community')
            ->update(['value' => 'Cameroon Network', 'updated_at' => now()]);

        Cache::forget('site_setting:site_name');
    }

    public function down(): void
    {
        // Nothing to undo.
    }
};
