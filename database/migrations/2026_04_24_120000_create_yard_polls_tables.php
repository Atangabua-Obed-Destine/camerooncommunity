<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend ENUM with 'poll'
        DB::statement("ALTER TABLE yard_messages MODIFY COLUMN message_type ENUM('text','image','video','audio','file','system','solidarity_card','gif','sticker','call_log','poll') NOT NULL DEFAULT 'text'");

        Schema::create('yard_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('message_id')->constrained('yard_messages')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('yard_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('question', 300);
            $table->boolean('allow_multiple')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->index('message_id');
            $table->index(['room_id', 'created_at']);
        });

        Schema::create('yard_poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('yard_polls')->cascadeOnDelete();
            $table->string('text', 200);
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('votes_count')->default(0);
            $table->timestamps();

            $table->index('poll_id');
        });

        Schema::create('yard_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('yard_polls')->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('yard_poll_options')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['option_id', 'user_id']);
            $table->index(['poll_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yard_poll_votes');
        Schema::dropIfExists('yard_poll_options');
        Schema::dropIfExists('yard_polls');
        DB::statement("ALTER TABLE yard_messages MODIFY COLUMN message_type ENUM('text','image','video','audio','file','system','solidarity_card','gif','sticker','call_log') NOT NULL DEFAULT 'text'");
    }
};
