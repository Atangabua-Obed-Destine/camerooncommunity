<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records every membership exit so group admins can review who has
 * left or been removed (WhatsApp "Past participants" parity).
 *
 * Kept in a separate table — rather than soft-deleting `yard_room_members`
 * — to preserve the `(room_id, user_id)` UNIQUE constraint that allows
 * the same user to re-join a group later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yard_room_member_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('yard_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Who triggered the exit. NULL = the user left voluntarily.
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            // 'left'    – user left voluntarily
            // 'removed' – admin removed the user
            $table->enum('reason', ['left', 'removed'])->default('left');
            $table->timestamp('exited_at')->useCurrent();
            $table->timestamps();

            $table->index(['room_id', 'exited_at'], 'yard_rmh_room_exited_idx');
            $table->index(['user_id', 'exited_at'], 'yard_rmh_user_exited_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yard_room_member_history');
    }
};
