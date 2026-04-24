<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\Language;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'username',
        'email',
        'phone',
        'password',
        'avatar',
        'bio',
        'country_of_origin',
        'home_region',
        'home_city',
        'current_country',
        'current_city',
        'current_region',
        'current_lat',
        'current_lng',
        'location_updated_at',
        'active_country',
        'active_region',
        'language_pref',
        'account_type',
        'community_points',
        'residency_months',
        'is_verified',
        'is_identity_verified',
        'is_founding_member',
        'is_community_leader',
        'is_active',
        'last_active_at',
        'onboarded_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'current_lat',
        'current_lng',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'location_updated_at' => 'datetime',
            'last_active_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'password' => 'hashed',
            'language_pref' => Language::class,
            'account_type' => AccountType::class,
            'is_verified' => 'boolean',
            'is_identity_verified' => 'boolean',
            'is_founding_member' => 'boolean',
            'is_community_leader' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ───

    public function badges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    public function followers(): HasMany
    {
        return $this->hasMany(UserFollow::class, 'following_id');
    }

    public function following(): HasMany
    {
        return $this->hasMany(UserFollow::class, 'follower_id');
    }

    public function roomMemberships(): HasMany
    {
        return $this->hasMany(YardRoomMember::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(YardMessage::class);
    }

    public function solidarityCampaigns(): HasMany
    {
        return $this->hasMany(SolidarityCampaign::class, 'created_by');
    }

    public function solidarityContributions(): HasMany
    {
        return $this->hasMany(SolidarityContribution::class, 'contributor_id');
    }

    public function pointsLog(): HasMany
    {
        return $this->hasMany(CommunityPointsLog::class);
    }

    /*
    |----------------------------------------------------------------------
    | Connections (mutual friend / DM permission system)
    |----------------------------------------------------------------------
    */

    /**
     * Returns the connection record between this user and another, or null.
     */
    public function connectionWith(int $otherUserId): ?UserConnection
    {
        return UserConnection::between($this->id, $otherUserId);
    }

    /**
     * True when both users have an accepted (mutual) connection.
     */
    public function isConnectedWith(int $otherUserId): bool
    {
        $c = $this->connectionWith($otherUserId);
        return $c !== null && $c->status === UserConnection::STATUS_ACCEPTED;
    }

    /**
     * True when either side has blocked the other.
     */
    public function hasBlockedOrIsBlockedBy(int $otherUserId): bool
    {
        $c = $this->connectionWith($otherUserId);
        return $c !== null && $c->status === UserConnection::STATUS_BLOCKED;
    }
}
