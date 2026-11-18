<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The phones a member gets push notifications on: one Firebase Cloud
 * Messaging token per app install, registered by the app after sign-in and
 * dropped at sign-out or when Firebase reports it dead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // FCM tokens run to ~160 characters; unique because a token is an
            // install, and an install has one member signed in at a time.
            $table->string('token', 512)->unique();
            $table->string('platform', 10);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
