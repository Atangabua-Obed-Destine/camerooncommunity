<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the member last looked at a saved search's matches. The "+N new" badge
 * counted from last_notified_at, which the hourly alert job moves forward, so
 * the badge cleared itself within the hour whether or not anyone looked; and
 * "Mark as seen" moved it too, which postponed the next alert. Two jobs, two
 * columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_saved_searches', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('last_notified_at');
        });

        // Start from what the badge counted from until now.
        DB::table('marketplace_saved_searches')->update(['last_seen_at' => DB::raw('COALESCE(last_notified_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('marketplace_saved_searches', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });
    }
};
