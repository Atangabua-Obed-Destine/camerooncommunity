<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add is_private flag to yard_rooms
        Schema::table('yard_rooms', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('is_system_room');
        });

        // Join requests table for private groups
        Schema::create('yard_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('yard_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'user_id']);
            $table->index(['room_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yard_join_requests');

        Schema::table('yard_rooms', function (Blueprint $table) {
            $table->dropColumn('is_private');
        });
    }
};
