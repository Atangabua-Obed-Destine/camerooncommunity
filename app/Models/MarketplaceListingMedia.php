<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MarketplaceListingMedia extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'marketplace_listing_media';

    protected $fillable = [
        'uuid', 'listing_id', 'type', 'path', 'thumbnail_path',
        'original_name', 'size_bytes', 'width', 'height', 'mime_type',
        'position', 'is_cover', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_cover' => 'boolean',
            'meta' => 'array',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (! $m->uuid) {
                $m->uuid = (string) Str::uuid();
            }
        });
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'listing_id');
    }

    public function url(): string
    {
        return asset('storage/' . $this->path);
    }

    public function thumbnailUrl(): string
    {
        return asset('storage/' . ($this->thumbnail_path ?: $this->path));
    }
}
