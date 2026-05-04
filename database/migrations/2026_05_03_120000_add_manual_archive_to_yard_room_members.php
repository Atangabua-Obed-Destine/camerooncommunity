<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual (WhatsApp-style) chat archiving.
 *
 * `auto_archived_at` already exists, but it is reserved for location-driven
 * auto-archiving (the user moved away from a room's region). Manual archive
 * is a separate, user-initiated action that hides a chat from the main list
 * until they explicitly unarchive it OR — like WhatsApp — until a new
 * mention/DM message arrives (depending on the keep-archived setting).
 *
 * We model it as a nullable timestamp so we can sort the Archived view by
 * most-recently-archived.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yard_room_members', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('auto_archived_at');
            $table->index(['user_id', 'archived_at'], 'yard_room_members_user_manual_archived_idx');
        });
    }

    public function down(): void
    {
        Schema::table('yard_room_members', function (Blueprint $table) {
            $table->dropIndex('yard_room_members_user_manual_archived_idx');
            $table->dropColumn('archived_at');
        });
    }
};
