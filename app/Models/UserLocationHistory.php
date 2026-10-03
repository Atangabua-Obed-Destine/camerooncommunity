<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded position for a member. Append-only: nothing updates a point,
 * and `locations:prune` removes them once they age out.
 */
class UserLocationHistory extends Model
{
    use BelongsToTenant;

    protected $table = 'user_location_history';

    /** Points are never updated, so only created_at is kept. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'lat',
        'lng',
        'country',
        'region',
        'city',
        'source',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'lat'        => 'float',
            'lng'        => 'float',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "Bamenda, Northwest, Cameroon" from whichever parts are known. */
    public function placeLabel(): string
    {
        return collect([$this->city, $this->region, $this->country])
            ->filter()
            ->unique()
            ->join(', ') ?: '—';
    }
}
