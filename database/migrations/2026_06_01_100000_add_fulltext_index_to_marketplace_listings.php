<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 — Marketplace search/relevance.
 *
 * Adds a MySQL FULLTEXT index on (title, description) so MarketplaceQueryBuilder
 * can switch from LIKE %term% to MATCH … AGAINST for far better relevance
 * (and bonus: free relevance score for "smart" sort).
 *
 * Safe to run on existing data — FULLTEXT in InnoDB is fully online since 5.6.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_listings')) { return; }
        if (DB::getDriverName() !== 'mysql') { return; }

        // Skip if already present (idempotent — safe on re-run)
        $exists = DB::selectOne(
            "SELECT COUNT(1) AS c FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name   = 'marketplace_listings'
               AND index_name   = 'marketplace_listings_title_desc_fulltext'"
        );
        if ((int) ($exists->c ?? 0) > 0) { return; }

        DB::statement(
            "ALTER TABLE marketplace_listings
             ADD FULLTEXT INDEX marketplace_listings_title_desc_fulltext (title, description)"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_listings')) { return; }
        if (DB::getDriverName() !== 'mysql') { return; }

        $exists = DB::selectOne(
            "SELECT COUNT(1) AS c FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name   = 'marketplace_listings'
               AND index_name   = 'marketplace_listings_title_desc_fulltext'"
        );
        if ((int) ($exists->c ?? 0) === 0) { return; }

        DB::statement("ALTER TABLE marketplace_listings DROP INDEX marketplace_listings_title_desc_fulltext");
    }
};
