<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yard_message_reads', function (Blueprint $table) {
            // delivered_at marks when the recipient's client received the message.
            // read_at marks when the recipient actually viewed the chat.
            // A row exists as soon as delivery happens; read_at is filled later.
            $table->timestamp('delivered_at')->nullable()->after('user_id');
        });

        // Make read_at nullable (it used CURRENT_TIMESTAMP default before).
        DB::statement('ALTER TABLE yard_message_reads MODIFY read_at TIMESTAMP NULL DEFAULT NULL');

        // Backfill: every existing row already represents a "read" event,
        // so mark them as delivered too.
        DB::statement('UPDATE yard_message_reads SET delivered_at = read_at WHERE delivered_at IS NULL');

        Schema::table('yard_message_reads', function (Blueprint $table) {
            $table->index(['message_id', 'read_at'], 'ymr_msg_read_idx');
            $table->index(['message_id', 'delivered_at'], 'ymr_msg_delivered_idx');
        });
    }

    public function down(): void
    {
        Schema::table('yard_message_reads', function (Blueprint $table) {
            $table->dropIndex('ymr_msg_read_idx');
            $table->dropIndex('ymr_msg_delivered_idx');
            $table->dropColumn('delivered_at');
        });

        DB::statement('ALTER TABLE yard_message_reads MODIFY read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }
};
