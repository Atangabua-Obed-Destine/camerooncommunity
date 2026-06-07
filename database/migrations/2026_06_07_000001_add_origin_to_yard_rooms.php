<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tags how a room was created. 'marketplace' rooms are buyer↔seller chats
 * started from a GoMarket listing — these bypass the mutual-connection
 * requirement that normal DMs enforce (Facebook Marketplace behaviour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yard_rooms', function (Blueprint $table) {
            if (! Schema::hasColumn('yard_rooms', 'origin')) {
                $table->string('origin', 24)->nullable()->after('room_type')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('yard_rooms', function (Blueprint $table) {
            if (Schema::hasColumn('yard_rooms', 'origin')) {
                $table->dropColumn('origin');
            }
        });
    }
};
