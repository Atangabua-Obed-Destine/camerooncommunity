<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bearer tokens for the mobile app.
 *
 * The web app has never needed this: Blade and Livewire ride on the session
 * cookie. A phone cannot — it holds a token in its own storage and sends it on
 * every request, which is how the E-ride app talks to its backend.
 *
 * `laravel/sanctum` has been in composer.json all along but was never wired up,
 * and its migration is published rather than auto-loaded, so the table simply
 * did not exist. This is Sanctum's own schema, written out here so it lives
 * with the rest of ours and is not re-published by accident.
 *
 * One token per device, named after the device, so signing out of a phone
 * revokes that phone and leaves the others alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();

            // Indexed because `sanctum:prune-expired` sweeps on it.
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
