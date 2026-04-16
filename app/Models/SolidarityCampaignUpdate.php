<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolidarityCampaignUpdate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'campaign_id',
        'posted_by',
        'body',
        'photo',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(SolidarityCampaign::class, 'campaign_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
