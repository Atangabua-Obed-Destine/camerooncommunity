<?php

namespace App\Models;

use App\Enums\Solidarity\CampaignCategory;
use App\Enums\Solidarity\CampaignStatus;
use App\Enums\Solidarity\RiskScore;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SolidarityCampaign extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'room_id',
        'created_by',
        'approved_by',
        'title',
        'description',
        'beneficiary_name',
        'beneficiary_relationship',
        'category',
        'target_amount',
        'current_amount',
        'platform_cut_percent',
        'currency',
        'status',
        'rejection_reason',
        'admin_note',
        'is_anonymous_allowed',
        'deadline',
        'proof_document',
        'proof_verified_by',
        'proof_verified_at',
        'total_contributors',
        'ai_risk_score',
        'ai_risk_reason',
        'disbursed_amount',
        'disbursed_at',
        'disbursement_proof',
        'system_message_id',
    ];

    protected function casts(): array
    {
        return [
            'category' => CampaignCategory::class,
            'status' => CampaignStatus::class,
            'ai_risk_score' => RiskScore::class,
            'target_amount' => 'decimal:2',
            'current_amount' => 'decimal:2',
            'platform_cut_percent' => 'decimal:2',
            'disbursed_amount' => 'decimal:2',
            'is_anonymous_allowed' => 'boolean',
            'deadline' => 'date',
            'proof_verified_at' => 'datetime',
            'disbursed_at' => 'datetime',
        ];
    }

    // ─── Relationships ───

    public function room(): BelongsTo
    {
        return $this->belongsTo(YardRoom::class, 'room_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function proofVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proof_verified_by');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(SolidarityContribution::class, 'campaign_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(SolidarityCampaignUpdate::class, 'campaign_id');
    }

    public function systemMessage(): BelongsTo
    {
        return $this->belongsTo(YardMessage::class, 'system_message_id');
    }

    // ─── Computed ───

    public function getProgressPercentAttribute(): float
    {
        if ($this->target_amount <= 0) {
            return 0;
        }

        return min(100, round(($this->current_amount / $this->target_amount) * 100, 1));
    }

    public function getNetAmountRaisedAttribute(): float
    {
        return round($this->current_amount * (1 - $this->platform_cut_percent / 100), 2);
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (! $this->deadline) {
            return null;
        }

        return max(0, now()->diffInDays($this->deadline, false));
    }

    // ─── Scopes ───

    public function scopeActive($query)
    {
        return $query->where('status', CampaignStatus::Active);
    }

    public function scopePending($query)
    {
        return $query->where('status', CampaignStatus::PendingApproval);
    }
}
