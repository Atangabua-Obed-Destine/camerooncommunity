<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityPointsLog extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'community_points_log';

    protected $fillable = [
        'user_id',
        'action',
        'points_awarded',
        'description',
        'reference_type',
        'reference_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
