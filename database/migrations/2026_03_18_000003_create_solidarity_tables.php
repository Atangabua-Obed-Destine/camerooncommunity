<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Solidarity Campaigns ───
        Schema::create('solidarity_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('room_id')->constrained('yard_rooms')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('beneficiary_name');
            $table->string('beneficiary_relationship');
            $table->enum('category', [
                'bereavement', 'medical', 'disaster', 'education', 'repatriation', 'other',
            ]);
            $table->decimal('target_amount', 10, 2);
            $table->decimal('current_amount', 10, 2)->default(0.00);
            $table->decimal('platform_cut_percent', 5, 2);
            $table->string('currency', 3)->default('GBP');
            $table->enum('status', [
                'pending_approval', 'active', 'paused', 'goal_reached',
                'completed', 'rejected', 'cancelled',
            ])->default('pending_approval');
            $table->text('rejection_reason')->nullable();
            $table->text('admin_note')->nullable();
            $table->boolean('is_anonymous_allowed')->default(true);
            $table->date('deadline')->nullable();
            $table->string('proof_document', 500)->nullable();
            $table->unsignedBigInteger('proof_verified_by')->nullable();
            $table->timestamp('proof_verified_at')->nullable();
            $table->integer('total_contributors')->default(0);
            $table->enum('ai_risk_score', ['low', 'medium', 'high'])->nullable();
            $table->text('ai_risk_reason')->nullable();
            $table->decimal('disbursed_amount', 10, 2)->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->string('disbursement_proof', 500)->nullable();
            $table->unsignedBigInteger('system_message_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('proof_verified_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index(['room_id', 'status']);
            $table->index('created_by');
        });

        // Add the FK from yard_messages to solidarity_campaigns now that both exist
        Schema::table('yard_messages', function (Blueprint $table) {
            $table->foreign('solidarity_campaign_id')
                ->references('id')->on('solidarity_campaigns')
                ->nullOnDelete();
        });

        // ─── Solidarity Contributions ───
        Schema::create('solidarity_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('solidarity_campaigns')->cascadeOnDelete();
            $table->foreignId('contributor_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('platform_fee', 10, 2);
            $table->decimal('net_amount', 10, 2);
            $table->string('currency', 3);
            $table->boolean('is_anonymous')->default(false);
            $table->text('message')->nullable();
            $table->enum('payment_method', ['card', 'bank_transfer', 'mobile_money', 'manual']);
            $table->enum('payment_status', ['pending', 'confirmed', 'failed', 'refunded'])->default('pending');
            $table->string('payment_reference')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'payment_status']);
            $table->index('contributor_id');
        });

        // ─── Solidarity Campaign Updates ───
        Schema::create('solidarity_campaign_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('solidarity_campaigns')->cascadeOnDelete();
            $table->foreignId('posted_by')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->string('media_path', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solidarity_campaign_updates');
        Schema::dropIfExists('solidarity_contributions');

        Schema::table('yard_messages', function (Blueprint $table) {
            $table->dropForeign(['solidarity_campaign_id']);
        });

        Schema::dropIfExists('solidarity_campaigns');
    }
};
