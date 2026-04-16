<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yard_messages', function (Blueprint $table) {
            // Speeds up pinned messages query (WHERE room_id = ? AND is_pinned = true)
            $table->index(['room_id', 'is_pinned', 'pinned_at'], 'yard_msg_pinned_idx');
            // Speeds up unread count (WHERE room_id = ? AND user_id != ? AND is_deleted = false AND created_at > ?)
            $table->index(['room_id', 'is_deleted', 'user_id', 'created_at'], 'yard_msg_unread_idx');
        });
    }

    public function down(): void
    {
        Schema::table('yard_messages', function (Blueprint $table) {
            $table->dropIndex('yard_msg_pinned_idx');
            $table->dropIndex('yard_msg_unread_idx');
        });
    }
};
