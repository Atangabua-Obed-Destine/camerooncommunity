<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds three feature layers to the chat system:
 *  1. @mentions       — `mentioned_user_ids` JSON on yard_messages.
 *  2. Share-to-chat   — polymorphic `shareable_type` + `shareable_id`
 *                       on yard_messages, plus a new `share_card`
 *                       message_type enum value.
 *  3. Auto-translate  — per-room language preference stored on the
 *                       member row (`auto_translate_lang`: 'en' | 'fr' | null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yard_messages', function (Blueprint $table) {
            $table->json('mentioned_user_ids')->nullable()->after('translated_content');
            $table->string('shareable_type', 80)->nullable()->after('solidarity_campaign_id');
            $table->unsignedBigInteger('shareable_id')->nullable()->after('shareable_type');
            $table->index(['shareable_type', 'shareable_id'], 'yard_messages_shareable_idx');
        });

        // Extend the message_type enum to accept a generic share card.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE yard_messages MODIFY COLUMN message_type ENUM('text','image','video','audio','file','system','solidarity_card','gif','sticker','call_log','poll','share_card') NOT NULL DEFAULT 'text'");
        }

        Schema::table('yard_room_members', function (Blueprint $table) {
            // 'en' or 'fr' — when set, incoming messages are auto-translated
            // into this language for the member if the source differs.
            $table->string('auto_translate_lang', 5)->nullable()->after('notification_pref_before_archive');
        });
    }

    public function down(): void
    {
        Schema::table('yard_messages', function (Blueprint $table) {
            $table->dropIndex('yard_messages_shareable_idx');
            $table->dropColumn(['mentioned_user_ids', 'shareable_type', 'shareable_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE yard_messages MODIFY COLUMN message_type ENUM('text','image','video','audio','file','system','solidarity_card','gif','sticker','call_log','poll') NOT NULL DEFAULT 'text'");
        }

        Schema::table('yard_room_members', function (Blueprint $table) {
            $table->dropColumn('auto_translate_lang');
        });
    }
};
