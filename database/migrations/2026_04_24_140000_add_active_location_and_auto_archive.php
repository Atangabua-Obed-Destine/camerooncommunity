<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Active location on user — the location whose default rooms are
        // currently un-archived in their chat list. Falls back to current_*
        // on first login. Different from current_* (which is just the
        // last detected position).
        Schema::table('users', function (Blueprint $table) {
            $table->string('active_country', 100)->nullable()->after('current_region');
            $table->string('active_region', 100)->nullable()->after('active_country');
            $table->index(['active_country', 'active_region'], 'users_active_loc_idx');
        });

        // Auto-archive snapshot on each membership row.
        // - auto_archived_at: when the user moved away from this room's location
        // - notification_pref_before_archive: original pref so we can restore it on unarchive
        Schema::table('yard_room_members', function (Blueprint $table) {
            $table->timestamp('auto_archived_at')->nullable()->after('notification_pref');
            $table->string('notification_pref_before_archive', 20)->nullable()->after('auto_archived_at');
            $table->index(['user_id', 'auto_archived_at'], 'yard_room_members_user_archived_idx');
        });
    }

    public function down(): void
    {
        Schema::table('yard_room_members', function (Blueprint $table) {
            $table->dropIndex('yard_room_members_user_archived_idx');
            $table->dropColumn(['auto_archived_at', 'notification_pref_before_archive']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_active_loc_idx');
            $table->dropColumn(['active_country', 'active_region']);
        });
    }
};
