<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE yard_messages MODIFY COLUMN message_type ENUM('text','image','video','audio','file','system','solidarity_card','gif','sticker','call_log') NOT NULL DEFAULT 'text'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE yard_messages MODIFY COLUMN message_type ENUM('text','image','video','audio','file','system','solidarity_card','gif','sticker') NOT NULL DEFAULT 'text'");
    }
};
