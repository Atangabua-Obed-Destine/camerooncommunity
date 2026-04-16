<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CmsPage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'slug',
        'title_en',
        'title_fr',
        'body_en',
        'body_fr',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
