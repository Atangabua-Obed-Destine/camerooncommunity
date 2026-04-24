<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // Canonical pair: user_a_id < user_b_id (alphabetical / numerical)
            $table->foreignId('user_a_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_b_id')->constrained('users')->cascadeOnDelete();
            // Who initiated the request (or who blocked)
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            // pending | accepted | blocked
            $table->string('status', 20)->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_a_id', 'user_b_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['user_a_id', 'status']);
            $table->index(['user_b_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_connections');
    }
};
