<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — Mobile-money checkout foundation.
 *
 * Adds:
 *   - marketplace_orders: one row per buyer-initiated checkout.
 *   - users.momo_number / momo_provider: where to send funds (used by the
 *     Manual driver instructions and any future API integration).
 *
 * Status enum stays on the application side (App\Enums\OrderStatus) so we can
 * add transitions without DDL changes.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_orders')) {
            Schema::create('marketplace_orders', function (Blueprint $t) {
                $t->id();
                $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $t->foreignId('listing_id')->constrained('marketplace_listings')->cascadeOnDelete();
                $t->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
                $t->foreignId('seller_id')->constrained('users')->cascadeOnDelete();

                $t->string('reference', 24)->unique();        // human-shown, e.g. "GM-ABC-2A9F"
                $t->string('status', 32)->index();             // OrderStatus enum value
                $t->string('provider', 32);                    // manual | mtn_momo | orange_money | cash_on_pickup
                $t->string('provider_ref', 120)->nullable();   // tx reference pasted by buyer / returned by API

                $t->decimal('amount', 12, 2);
                $t->string('currency', 4)->default('XAF');
                $t->unsignedSmallInteger('quantity')->default(1);

                $t->text('buyer_note')->nullable();
                $t->text('seller_note')->nullable();

                $t->timestamp('paid_at')->nullable();
                $t->timestamp('released_at')->nullable();
                $t->timestamp('cancelled_at')->nullable();

                $t->timestamps();
                $t->softDeletes();

                $t->index(['buyer_id', 'status']);
                $t->index(['seller_id', 'status']);
                $t->index(['listing_id', 'status']);
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $t) {
                if (! Schema::hasColumn('users', 'momo_number')) {
                    $t->string('momo_number', 32)->nullable()->after('current_country');
                }
                if (! Schema::hasColumn('users', 'momo_provider')) {
                    $t->string('momo_provider', 24)->nullable()->after('momo_number');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_orders');
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $t) {
                if (Schema::hasColumn('users', 'momo_provider')) { $t->dropColumn('momo_provider'); }
                if (Schema::hasColumn('users', 'momo_number'))   { $t->dropColumn('momo_number'); }
            });
        }
    }
};
