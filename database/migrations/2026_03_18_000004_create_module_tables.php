<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Marketplace (Marché) ───
        Schema::create('marketplace_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('marketplace_categories')->nullOnDelete();
        });

        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('GBP');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->enum('condition', ['new', 'like_new', 'good', 'fair'])->default('good');
            $table->string('country', 100)->index();
            $table->string('city', 100)->nullable();
            $table->json('images')->nullable();
            $table->enum('status', ['active', 'sold', 'removed', 'expired'])->default('active');
            $table->integer('views_count')->default(0);
            $table->boolean('ai_description_generated')->default(false);
            $table->boolean('ai_verified')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('marketplace_categories')->nullOnDelete();
            $table->index(['tenant_id', 'country', 'status']);
        });

        // ─── EasyGoParcel ───
        Schema::create('parcel_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('traveler_id')->constrained('users')->cascadeOnDelete();
            $table->string('origin_country', 100);
            $table->string('origin_city', 100)->nullable();
            $table->string('destination_country', 100);
            $table->string('destination_city', 100)->nullable();
            $table->date('travel_date');
            $table->decimal('available_kg', 5, 2);
            $table->decimal('remaining_kg', 5, 2);
            $table->decimal('price_per_kg', 10, 2);
            $table->string('currency', 3)->default('GBP');
            $table->text('item_restrictions')->nullable();
            $table->enum('status', ['open', 'partial', 'full', 'completed', 'cancelled'])->default('open');
            $table->decimal('ai_risk_score', 3, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'origin_country', 'destination_country', 'travel_date'], 'parcel_trips_route_date_idx');
        });

        Schema::create('parcel_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained('parcel_trips')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('booked_kg', 5, 2);
            $table->decimal('total_price', 10, 2);
            $table->decimal('platform_commission', 10, 2)->default(0);
            $table->string('currency', 3)->default('GBP');
            $table->text('item_description')->nullable();
            $table->json('item_photos')->nullable();
            $table->boolean('ai_items_cleared')->default(false);
            $table->text('ai_flag_reason')->nullable();
            $table->enum('status', [
                'pending', 'confirmed', 'handed_over', 'delivered', 'disputed', 'cancelled',
            ])->default('pending');
            $table->tinyInteger('traveler_rating')->nullable();
            $table->tinyInteger('sender_rating')->nullable();
            $table->text('traveler_review')->nullable();
            $table->text('sender_review')->nullable();
            $table->timestamps();
        });

        // ─── RoadFam (Rides) ───
        Schema::create('rides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->string('origin_address');
            $table->decimal('origin_lat', 10, 8)->nullable();
            $table->decimal('origin_lng', 11, 8)->nullable();
            $table->string('destination_address');
            $table->decimal('destination_lat', 10, 8)->nullable();
            $table->decimal('destination_lng', 11, 8)->nullable();
            $table->dateTime('departure_time');
            $table->integer('seats_total');
            $table->integer('seats_available');
            $table->decimal('price_per_seat', 10, 2)->default(0);
            $table->string('currency', 3)->default('GBP');
            $table->text('notes')->nullable();
            $table->enum('status', ['open', 'full', 'completed', 'cancelled'])->default('open');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ride_passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('ride_id')->constrained('rides')->cascadeOnDelete();
            $table->foreignId('passenger_id')->constrained('users')->cascadeOnDelete();
            $table->string('pickup_address')->nullable();
            $table->decimal('pickup_lat', 10, 8)->nullable();
            $table->decimal('pickup_lng', 11, 8)->nullable();
            $table->enum('status', ['requested', 'confirmed', 'cancelled'])->default('requested');
            $table->timestamps();
        });

        // ─── CamEvents ───
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('organiser_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('category', [
                'cultural', 'social', 'professional', 'religious', 'sports', 'other',
            ])->default('social');
            $table->string('country', 100);
            $table->string('city', 100)->nullable();
            $table->string('venue_name')->nullable();
            $table->string('venue_address')->nullable();
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime')->nullable();
            $table->string('cover_image', 500)->nullable();
            $table->boolean('is_free')->default(true);
            $table->decimal('ticket_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('GBP');
            $table->integer('total_tickets')->nullable();
            $table->integer('tickets_sold')->default(0);
            $table->enum('status', ['draft', 'published', 'cancelled', 'completed'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });

        // FK for rides.event_id
        Schema::table('rides', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
        });

        Schema::create('event_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ticket_code', 50)->unique();
            $table->enum('status', ['valid', 'used', 'refunded'])->default('valid');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->decimal('price_paid', 10, 2)->default(0);
            $table->string('currency', 3)->default('GBP');
            $table->timestamps();
        });

        // ─── KamerNest (Housing) ───
        Schema::create('housing_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('property_type', ['room', 'flat', 'house', 'studio'])->default('flat');
            $table->string('country', 100);
            $table->string('city', 100)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            $table->decimal('price', 10, 2);
            $table->enum('price_period', ['week', 'month'])->default('month');
            $table->integer('bedrooms')->default(1);
            $table->integer('bathrooms')->default(1);
            $table->boolean('is_furnished')->default(false);
            $table->date('available_from')->nullable();
            $table->json('images')->nullable();
            $table->json('amenities')->nullable();
            $table->decimal('ai_scam_score', 3, 2)->nullable();
            $table->enum('status', ['active', 'let', 'removed'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── WorkConnect (Jobs) ───
        Schema::create('job_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('poster_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('company_name')->nullable();
            $table->text('description')->nullable();
            $table->enum('job_type', [
                'full_time', 'part_time', 'contract', 'freelance', 'volunteer',
            ])->default('full_time');
            $table->string('category')->nullable();
            $table->string('country', 100);
            $table->string('city', 100)->nullable();
            $table->decimal('salary_min', 10, 2)->nullable();
            $table->decimal('salary_max', 10, 2)->nullable();
            $table->string('currency', 3)->default('GBP');
            $table->boolean('is_remote')->default(false);
            $table->text('requirements')->nullable();
            $table->text('benefits')->nullable();
            $table->enum('apply_method', ['in_app', 'external_url', 'email'])->default('in_app');
            $table->string('apply_url', 500)->nullable();
            $table->date('application_deadline')->nullable();
            $table->enum('status', ['active', 'filled', 'expired', 'removed'])->default('active');
            $table->json('ai_match_vector')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── KamerEats (Food) ───
        Schema::create('food_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['restaurant', 'grocery_store', 'home_cook', 'catering'])->default('restaurant');
            $table->text('description')->nullable();
            $table->string('country', 100);
            $table->string('city', 100)->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('website', 500)->nullable();
            $table->json('images')->nullable();
            $table->json('opening_hours')->nullable();
            $table->json('specialties')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->integer('ratings_count')->default(0);
            $table->enum('status', ['active', 'closed', 'removed'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        // ─── KamerSOS ───
        Schema::create('sos_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('current_country', 100);
            $table->string('current_city', 100)->nullable();
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
            $table->enum('emergency_type', [
                'lost_documents', 'medical', 'legal', 'housing',
                'stranded', 'deportation', 'safety', 'financial', 'other',
            ]);
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'responding', 'resolved'])->default('active');
            $table->unsignedBigInteger('assigned_leader_id')->nullable();
            $table->boolean('escalated_to_all')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->text('ai_guidance_provided')->nullable();
            $table->timestamps();

            $table->foreign('assigned_leader_id')->references('id')->on('users')->nullOnDelete();
        });

        // ─── CamStories ───
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('current_country', 100)->nullable();
            $table->enum('media_type', ['image', 'video']);
            $table->string('media_path', 500);
            $table->string('thumbnail_path', 500)->nullable();
            $table->text('caption')->nullable();
            $table->enum('linked_module', [
                'none', 'marche', 'easygoparcel', 'camevents',
                'kamernest', 'roadfam', 'solidarity',
            ])->default('none');
            $table->unsignedBigInteger('linked_module_id')->nullable();
            $table->integer('views_count')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'current_country', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stories');
        Schema::dropIfExists('sos_alerts');
        Schema::dropIfExists('food_listings');
        Schema::dropIfExists('job_listings');
        Schema::dropIfExists('housing_listings');
        Schema::dropIfExists('event_tickets');
        Schema::table('rides', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
        });
        Schema::dropIfExists('events');
        Schema::dropIfExists('ride_passengers');
        Schema::dropIfExists('rides');
        Schema::dropIfExists('parcel_bookings');
        Schema::dropIfExists('parcel_trips');
        Schema::dropIfExists('marketplace_listings');
        Schema::dropIfExists('marketplace_categories');
    }
};
