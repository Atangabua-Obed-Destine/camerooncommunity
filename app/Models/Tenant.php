<?php

namespace App\Models;

use App\Enums\TenantLanguage;
use App\Enums\TenantPlan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'country',
        'flag_emoji',
        'primary_color',
        'accent_color',
        'tertiary_color',
        'language',
        'plan',
        'license_fee',
        'solidarity_platform_cut_percent',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'language' => TenantLanguage::class,
            'plan' => TenantPlan::class,
            'license_fee' => 'decimal:2',
            'solidarity_platform_cut_percent' => 'decimal:2',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function platformSettings(): HasMany
    {
        return $this->hasMany(PlatformSetting::class);
    }

    public function yardRooms(): HasMany
    {
        return $this->hasMany(YardRoom::class);
    }
}
