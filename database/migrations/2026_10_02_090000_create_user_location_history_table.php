<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a member has been, not just where they are now.
 *
 * `users.current_lat/lng` is a snapshot: every move overwrites it, so the
 * platform could never answer "where was this account last week" — needed for
 * safety reports, fraud and disputes. Each meaningful move appends a row here
 * instead.
 *
 * Rows are pruned after 90 days by `locations:prune`, and reading them is
 * gated by the `view_user_location` permission and written to the audit log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_location_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Same precision as users.current_lat/lng so a point round-trips
            // without drifting.
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();

            $table->string('country', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();

            // gps     – browser geolocation
            // ip      – IP lookup fallback
            // manual  – the member picked a location themselves
            // switch  – active-location switch in GoConnect
            // header  – X-User-* headers on a normal request
            $table->string('source', 16)->default('gps');

            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // The two reads: one member's trail, and everything recently.
            $table->index(['user_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_location_history');
    }
};
