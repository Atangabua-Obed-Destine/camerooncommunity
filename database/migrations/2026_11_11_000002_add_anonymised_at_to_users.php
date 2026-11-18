<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a member deleted their account (AccountDeletion). The row stays,
 * scrubbed, because deleting it would cascade through other people's
 * conversations, orders and reviews; this marks it as nobody.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('anonymised_at')->nullable()->after('deleted_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['anonymised_at']);
            $table->dropColumn('anonymised_at');
        });
    }
};
