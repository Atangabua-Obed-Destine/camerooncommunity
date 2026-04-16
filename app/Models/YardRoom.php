<?php

namespace App\Models;

use App\Enums\RoomType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class YardRoom extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'country',
        'city',
        'region',
        'room_type',
        'description',
        'avatar',
        'is_active',
        'is_system_room',
        'is_private',
        'created_by',
        'members_count',
        'messages_count',
        'last_message_at',
        'last_message_preview',
        'last_message_user_id',
    ];

    protected function casts(): array
    {
        return [
            'room_type' => RoomType::class,
            'is_active' => 'boolean',
            'is_system_room' => 'boolean',
            'is_private' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    // ─── Relationships ───

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(YardRoomMember::class, 'room_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(YardMessage::class, 'room_id');
    }

    public function solidarityCampaigns(): HasMany
    {
        return $this->hasMany(SolidarityCampaign::class, 'room_id');
    }

    public function joinPrompts(): HasMany
    {
        return $this->hasMany(YardRoomJoinPrompt::class, 'room_id');
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(YardJoinRequest::class, 'room_id');
    }

    // ─── Scopes ───

    public function scopeNational($query)
    {
        return $query->where('room_type', RoomType::National);
    }

    public function scopeCity($query)
    {
        return $query->where('room_type', RoomType::City);
    }

    public function scopeRegional($query)
    {
        return $query->where('room_type', RoomType::Regional);
    }

    public function scopeForCountry($query, string $country)
    {
        return $query->where('country', $country);
    }

    public function scopeForCity($query, string $city)
    {
        return $query->where('city', $city);
    }

    public function scopeForRegion($query, string $region)
    {
        return $query->where('region', $region);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
