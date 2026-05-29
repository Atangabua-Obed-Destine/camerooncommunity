<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1: Reviews + sold-to attribution.
 *
 * 1. Extends marketplace_listings with buyer_id / sold_price / sold_currency.
 * 2. Adds denormalized seller_rating_avg + seller_rating_count on users.
 * 3. Creates marketplace_reviews table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (! Schema::hasColumn('marketplace_listings', 'buyer_id')) {
                $table->foreignId('buyer_id')->nullable()->after('user_id')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('marketplace_listings', 'sold_price')) {
                $table->decimal('sold_price', 12, 2)->nullable()->after('sold_at');
            }
            if (! Schema::hasColumn('marketplace_listings', 'sold_currency')) {
                $table->string('sold_currency', 4)->nullable()->after('sold_price');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'seller_rating_avg')) {
                $table->decimal('seller_rating_avg', 3, 2)->default(0)->after('avatar');
            }
            if (! Schema::hasColumn('users', 'seller_rating_count')) {
                $table->unsignedInteger('seller_rating_count')->default(0)->after('seller_rating_avg');
            }
        });

        if (! Schema::hasTable('marketplace_reviews')) {
            Schema::create('marketplace_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('listing_id')->constrained('marketplace_listings')->cascadeOnDelete();
                $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedTinyInteger('rating')->comment('1..5');
                $table->text('comment')->nullable();
                $table->boolean('is_buyer_verified')->default(false)
                    ->comment('true when reviewer is the listing buyer_id');
                $table->text('reply')->nullable()->comment('Seller response to the review');
                $table->timestamp('replied_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['listing_id', 'reviewer_id'], 'mp_reviews_listing_reviewer_unique');
                $table->index(['tenant_id', 'seller_id']);
                $table->index(['tenant_id', 'reviewer_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_reviews');

        Schema::table('users', function (Blueprint $table) {
            foreach (['seller_rating_avg', 'seller_rating_count'] as $col) {
                if (Schema::hasColumn('users', $col)) { $table->dropColumn($col); }
            }
        });

        Schema::table('marketplace_listings', function (Blueprint $table) {
            foreach (['buyer_id', 'sold_price', 'sold_currency'] as $col) {
                if (Schema::hasColumn('marketplace_listings', $col)) {
                    if ($col === 'buyer_id') { $table->dropConstrainedForeignId('buyer_id'); }
                    else { $table->dropColumn($col); }
                }
            }
        });
    }
};
