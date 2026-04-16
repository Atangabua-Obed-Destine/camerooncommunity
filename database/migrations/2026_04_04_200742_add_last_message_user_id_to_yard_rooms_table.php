<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('yard_rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('last_message_user_id')->nullable()->after('last_message_preview');
            $table->foreign('last_message_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('yard_rooms', function (Blueprint $table) {
            $table->dropForeign(['last_message_user_id']);
            $table->dropColumn('last_message_user_id');
        });
    }
};
