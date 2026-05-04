<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user nicknames for other users (WhatsApp-style "Save contact as...").
 *
 * Each row stores the local label that one user has assigned to another.
 * Display logic falls back to username/name when no nickname is set.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_contact_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('contact_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nickname', 60);
            $table->timestamps();

            $table->unique(['owner_user_id', 'contact_user_id']);
            $table->index(['owner_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_contact_names');
    }
};
