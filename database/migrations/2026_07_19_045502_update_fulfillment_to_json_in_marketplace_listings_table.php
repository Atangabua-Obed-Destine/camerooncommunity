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
        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->dropColumn('fulfillment');
        });
        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->json('fulfillment')->nullable()->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->dropColumn('fulfillment');
        });
        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->enum('fulfillment', ['pickup', 'local_delivery', 'diaspora_shippable', 'digital'])->default('pickup')->after('quantity');
        });
    }
};
