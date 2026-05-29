<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('marketplace_listings', 'bumped_at')) {
                $table->timestamp('bumped_at')->nullable()->after('renewed_at')->index();
            }
            if (! Schema::hasColumn('marketplace_listings', 'bump_count')) {
                $table->unsignedInteger('bump_count')->default(0)->after('bumped_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_listings', 'bump_count')) {
                $table->dropColumn('bump_count');
            }
            if (Schema::hasColumn('marketplace_listings', 'bumped_at')) {
                $table->dropColumn('bumped_at');
            }
        });
    }
};
