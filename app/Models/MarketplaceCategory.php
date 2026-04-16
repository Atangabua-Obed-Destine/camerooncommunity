<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceCategory extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'sort_order',
        'is_active',
        'parent_id',
    ];

    public function listings(): HasMany
    {
        return $this->hasMany(MarketplaceListing::class, 'category_id');
    }
}
