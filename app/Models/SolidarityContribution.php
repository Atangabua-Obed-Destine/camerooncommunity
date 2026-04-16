<?php

namespace App\Models;

use App\Enums\Solidarity\PaymentMethod;
use App\Enums\Solidarity\PaymentStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolidarityContribution extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'campaign_id',
        'contributor_id',
        'amount',
        'platform_fee',
        'net_amount',
        'currency',
        'payment_method',
        'payment_status',
        'payment_reference',
        'is_anonymous',
        'message',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'is_anonymous' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(SolidarityCampaign::class, 'campaign_id');
    }

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contributor_id');
    }
}
