<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add region column to yard_rooms
        Schema::table('yard_rooms', function (Blueprint $table) {
            $table->string('region', 100)->nullable()->after('city')->index();
        });

        // 2. Expand room_type enum to include 'regional'
        DB::statement("ALTER TABLE yard_rooms MODIFY COLUMN room_type ENUM('national','regional','city','private_group','direct_message') NOT NULL");

        // 3. Add composite index for region-based queries
        Schema::table('yard_rooms', function (Blueprint $table) {
            $table->index(['tenant_id', 'region', 'room_type'], 'yard_rooms_tenant_region_type_index');
        });

        // 4. Add current_region to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('current_region', 100)->nullable()->after('current_city');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('current_region');
        });

        Schema::table('yard_rooms', function (Blueprint $table) {
            $table->dropIndex('yard_rooms_tenant_region_type_index');
            $table->dropColumn('region');
        });

        DB::statement("ALTER TABLE yard_rooms MODIFY COLUMN room_type ENUM('national','city','private_group','direct_message') NOT NULL");
    }
};
