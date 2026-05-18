<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop legacy minimal stubs from 2026_03_18_000004_create_module_tables
        // so we can install the full Phase-1 schema cleanly.
        Schema::dropIfExists('marketplace_listings');
        Schema::dropIfExists('marketplace_categories');

        // ─────────────────────────────────────────────────────────────
        // 1. CATEGORIES (tenant-scoped taxonomy with EN/FR labels)
        // ─────────────────────────────────────────────────────────────
        Schema::create('marketplace_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('marketplace_categories')->nullOnDelete();
            $table->string('slug', 80);
            $table->string('name_en', 120);
            $table->string('name_fr', 120);
            $table->string('icon', 16)->nullable()->comment('emoji or icon key');
            $table->string('color', 16)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable()->comment('e.g. required custom fields per category');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'parent_id', 'position']);
            $table->index(['tenant_id', 'is_active']);
        });

        // ─────────────────────────────────────────────────────────────
        // 2. LISTINGS (the core marketplace item)
        // ─────────────────────────────────────────────────────────────
        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('slug', 180)->unique();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('marketplace_categories')->restrictOnDelete();

            // Core content
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('language', 5)->default('en')->comment('en|fr|auto');

            // Pricing
            $table->enum('price_type', ['fixed', 'negotiable', 'free', 'contact'])->default('fixed');
            $table->decimal('price', 14, 2)->nullable();
            $table->string('currency', 8)->default('XAF');
            $table->decimal('price_min', 14, 2)->nullable()->comment('for range');
            $table->decimal('price_max', 14, 2)->nullable();

            // Condition / state
            $table->enum('condition', ['new', 'like_new', 'good', 'fair', 'for_parts'])->default('good');
            $table->unsignedInteger('quantity')->default(1);

            // Fulfillment & location
            $table->enum('fulfillment', ['pickup', 'local_delivery', 'diaspora_shippable', 'digital'])->default('pickup');
            $table->string('country', 4)->nullable()->comment('ISO-2, e.g. CM');
            $table->string('region', 80)->nullable()->comment('e.g. Littoral');
            $table->string('city', 120)->nullable();
            $table->string('neighborhood', 160)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Targeting (algorithm + visibility)
            $table->enum('visibility', ['public', 'connections', 'group'])->default('public');
            $table->json('target_regions')->nullable()->comment('array of region slugs');
            $table->json('target_countries')->nullable()->comment('diaspora targeting');
            $table->json('tags')->nullable();

            // Lifecycle
            $table->enum('status', [
                'draft', 'pending_review', 'active', 'paused', 'sold', 'expired', 'removed',
            ])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('renewed_at')->nullable();
            $table->timestamp('sold_at')->nullable();

            // Engagement counters (denormalized for fast ranking)
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('favorites_count')->default(0);
            $table->unsignedBigInteger('messages_count')->default(0);
            $table->unsignedBigInteger('shares_count')->default(0);

            // Ranking score (recomputed nightly + on engagement)
            $table->double('rank_score')->default(0);
            $table->timestamp('rank_updated_at')->nullable();

            // Trust & moderation
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_verified_seller')->default(false);
            $table->boolean('is_flagged')->default(false);
            $table->unsignedInteger('reports_count')->default(0);
            $table->json('moderation_meta')->nullable();

            // Custom per-category fields (JSON for flexibility)
            $table->json('attributes')->nullable()->comment('e.g. {"mileage":12000,"brand":"Toyota"}');

            // SEO / discoverability
            $table->string('seo_title', 200)->nullable();
            $table->string('seo_description', 320)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'published_at']);
            $table->index(['tenant_id', 'category_id', 'status']);
            $table->index(['tenant_id', 'country', 'region', 'status']);
            $table->index(['tenant_id', 'rank_score']);
            $table->index(['user_id', 'status']);
            $table->index(['expires_at']);
            $table->fullText(['title', 'description']);
        });

        // ─────────────────────────────────────────────────────────────
        // 3. LISTING MEDIA (multiple images / videos per listing)
        // ─────────────────────────────────────────────────────────────
        Schema::create('marketplace_listing_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('marketplace_listings')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->enum('type', ['image', 'video'])->default('image');
            $table->string('path', 500);
            $table->string('thumbnail_path', 500)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('mime_type', 80)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'listing_id', 'position']);
        });

        // ─────────────────────────────────────────────────────────────
        // 4. FAVORITES (per-user saved listings)
        // ─────────────────────────────────────────────────────────────
        Schema::create('marketplace_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('marketplace_listings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['listing_id', 'user_id']);
            $table->index(['tenant_id', 'user_id', 'created_at']);
        });

        // ─────────────────────────────────────────────────────────────
        // 5. SAVED SEARCHES (user-defined alerts / quick filters)
        // ─────────────────────────────────────────────────────────────
        Schema::create('marketplace_saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('filters')->comment('query, category_id, region, price_max, condition, fulfillment, sort');
            $table->boolean('notify_email')->default(false);
            $table->boolean('notify_push')->default(true);
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
        });

        // ─────────────────────────────────────────────────────────────
        // 6. LISTING VIEWS (analytics + ranking input, lightweight log)
        // ─────────────────────────────────────────────────────────────
        Schema::create('marketplace_listing_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('marketplace_listings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_hash', 64)->nullable()->comment('hashed IP+UA for anon dedupe');
            $table->string('country', 4)->nullable();
            $table->string('region', 80)->nullable();
            $table->string('source', 40)->nullable()->comment('feed|search|category|share|direct');
            $table->timestamp('viewed_at')->useCurrent();

            $table->index(['tenant_id', 'listing_id', 'viewed_at']);
            $table->index(['tenant_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listing_views');
        Schema::dropIfExists('marketplace_saved_searches');
        Schema::dropIfExists('marketplace_favorites');
        Schema::dropIfExists('marketplace_listing_media');
        Schema::dropIfExists('marketplace_listings');
        Schema::dropIfExists('marketplace_categories');
    }
};
