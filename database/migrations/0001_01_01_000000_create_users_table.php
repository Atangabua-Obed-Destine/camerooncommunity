<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Tenants ───
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('domain')->nullable()->unique();
            $table->string('country', 100);
            $table->string('flag_emoji', 10)->default('🇨🇲');
            $table->string('primary_color', 7)->default('#006B3F');
            $table->string('accent_color', 7)->default('#CE1126');
            $table->string('tertiary_color', 7)->default('#FCD116');
            $table->enum('language', ['en', 'fr', 'bilingual'])->default('bilingual');
            $table->enum('plan', ['owned', 'licensed'])->default('owned');
            $table->decimal('license_fee', 10, 2)->default(0.00);
            $table->decimal('solidarity_platform_cut_percent', 5, 2)->default(5.00);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        // ─── Users ───
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->string('avatar', 500)->nullable();
            $table->text('bio')->nullable();
            $table->string('country_of_origin', 100)->default('Cameroon');
            $table->string('home_region', 100)->nullable();
            $table->string('home_city', 100)->nullable();
            $table->string('current_country', 100)->nullable();
            $table->string('current_city', 100)->nullable();
            $table->decimal('current_lat', 10, 8)->nullable();
            $table->decimal('current_lng', 11, 8)->nullable();
            $table->timestamp('location_updated_at')->nullable();
            $table->enum('language_pref', ['en', 'fr'])->default('en');
            $table->enum('account_type', ['free', 'premium'])->default('free');
            $table->integer('community_points')->default(0);
            $table->integer('residency_months')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_identity_verified')->default(false);
            $table->boolean('is_founding_member')->default(false);
            $table->boolean('is_community_leader')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'current_country']);
            $table->index(['tenant_id', 'current_city']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // ─── Platform Settings ───
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->string('group', 100)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('tenants');
    }
};
