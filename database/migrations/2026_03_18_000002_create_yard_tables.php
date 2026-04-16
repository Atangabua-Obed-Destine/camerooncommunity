<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Yard Rooms ───
        Schema::create('yard_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 100);
            $table->string('country', 100)->index();
            $table->string('city', 100)->nullable()->index();
            $table->enum('room_type', ['national', 'city', 'private_group', 'direct_message']);
            $table->text('description')->nullable();
            $table->string('avatar', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system_room')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('members_count')->default(0);
            $table->integer('messages_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'country', 'room_type']);
            $table->index(['tenant_id', 'city', 'room_type']);
            $table->index('last_message_at');
        });

        // ─── Yard Room Members ───
        Schema::create('yard_room_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('yard_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['admin', 'moderator', 'member'])->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('last_read_at')->nullable();
            $table->unsignedBigInteger('last_seen_message_id')->nullable();
            $table->boolean('is_muted')->default(false);
            $table->timestamp('muted_until')->nullable();
            $table->enum('notification_pref', ['all', 'mentions', 'none'])->default('all');
            $table->timestamps();

            $table->unique(['room_id', 'user_id']);
            $table->index(['user_id', 'tenant_id']);
        });

        // ─── Yard Room Join Prompts ───
        Schema::create('yard_room_join_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('yard_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('prompted_at')->useCurrent();
            $table->enum('action', ['joined', 'dismissed', 'pending'])->default('pending');
            $table->timestamp('actioned_at')->nullable();

            $table->unique(['room_id', 'user_id']);
        });

        // ─── Yard Messages ───
        Schema::create('yard_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('room_id')->constrained('yard_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_message_id')->nullable();
            $table->enum('message_type', [
                'text', 'image', 'video', 'audio', 'file',
                'system', 'solidarity_card', 'gif', 'sticker',
            ])->default('text');
            $table->text('content')->nullable();
            $table->string('media_path', 500)->nullable();
            $table->string('media_thumbnail', 500)->nullable();
            $table->string('media_original_name', 255)->nullable();
            $table->unsignedBigInteger('media_size')->nullable();
            $table->json('media_metadata')->nullable();
            $table->json('translated_content')->nullable();
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->string('flag_reason', 255)->nullable();
            $table->decimal('ai_moderation_score', 3, 2)->nullable();
            $table->json('ai_moderation_detail')->nullable();
            $table->json('reactions_count')->nullable();
            $table->integer('reply_count')->default(0);
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('pinned_at')->nullable();
            $table->unsignedBigInteger('pinned_by')->nullable();
            $table->unsignedBigInteger('solidarity_campaign_id')->nullable();
            $table->timestamps();

            $table->foreign('parent_message_id')->references('id')->on('yard_messages')->nullOnDelete();
            $table->foreign('pinned_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['room_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['tenant_id', 'is_flagged']);
        });

        // ─── Yard Message Reactions ───
        Schema::create('yard_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('message_id')->constrained('yard_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 10);
            $table->timestamp('created_at')->nullable();

            $table->unique(['message_id', 'user_id', 'emoji']);
            $table->index('message_id');
        });

        // ─── Yard Message Reads ───
        Schema::create('yard_message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('message_id')->constrained('yard_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();

            $table->unique(['message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yard_message_reads');
        Schema::dropIfExists('yard_message_reactions');
        Schema::dropIfExists('yard_messages');
        Schema::dropIfExists('yard_room_join_prompts');
        Schema::dropIfExists('yard_room_members');
        Schema::dropIfExists('yard_rooms');
    }
};
