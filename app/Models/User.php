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
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'username',
        'email',
        'phone',
        'password',
        'google_id',
        'avatar',
        'cover_photo',
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
        'momo_number',
        'momo_provider',
        'active_country',
        'active_region',
        'language_pref',
        'show_archived_away',
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
            'anonymised_at' => 'datetime',
            'password' => 'hashed',
            'language_pref' => Language::class,
            'account_type' => AccountType::class,
            'is_verified' => 'boolean',
            'is_identity_verified' => 'boolean',
            'is_founding_member' => 'boolean',
            'is_community_leader' => 'boolean',
            'is_active' => 'boolean',
            'show_archived_away' => 'boolean',
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

    /** Reviews received as a seller (Marketplace). */
    public function sellerReviews(): HasMany
    {
        return $this->hasMany(MarketplaceReview::class, 'seller_id');
    }

    /** Reviews this user has written about other sellers. */
    public function writtenReviews(): HasMany
    {
        return $this->hasMany(MarketplaceReview::class, 'reviewer_id');
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
     * True when the other member has blocked this one (not the other way round).
     * Profiles 404 for the blocked side only; the blocker still sees the page,
     * with Unblock on it.
     */
    public function isBlockedBy(int $otherUserId): bool
    {
        $c = $this->connectionWith($otherUserId);
        return $c !== null && $c->status === UserConnection::STATUS_BLOCKED && (int) $c->requested_by === $otherUserId;
    }

    /**
     * True when either side has blocked the other.
     */
    public function hasBlockedOrIsBlockedBy(int $otherUserId): bool
    {
        $c = $this->connectionWith($otherUserId);
        return $c !== null && $c->status === UserConnection::STATUS_BLOCKED;
    }

    /**
     * IDs of users with whom there is *any* block in either direction
     * (this user blocked them OR they blocked this user). Cached per request
     * so chat-room renders don't run the query for every message.
     */
    public function blockedOrBlockingUserIds(): array
    {
        // Remembered on this object, for the request. It used to be a static
        // keyed by user id, which lives as long as the PHP process: in a queue
        // worker or Reverb a block made after the first call was never seen.
        if ($this->blockedIdsMemo !== null) {
            return $this->blockedIdsMemo;
        }

        $rows = UserConnection::where('status', UserConnection::STATUS_BLOCKED)
            ->where(function ($q) {
                $q->where('user_a_id', $this->id)->orWhere('user_b_id', $this->id);
            })
            ->get(['user_a_id', 'user_b_id']);

        return $this->blockedIdsMemo = $rows
            ->map(fn ($r) => (int) ($r->user_a_id === $this->id ? $r->user_b_id : $r->user_a_id))
            ->unique()
            ->values()
            ->all();
    }

    /** @var array<int>|null see blockedOrBlockingUserIds() */
    protected ?array $blockedIdsMemo = null;

    /**
     * The custom name *this* user has saved for `$otherUserId`, or null.
     */
    public function nicknameFor(int $otherUserId): ?string
    {
        return UserContactName::nickname($this->id, $otherUserId);
    }

    /**
     * Display name for `$other` from this user's perspective:
     * nickname if saved, else username, else name.
     */
    public function displayNameFor(?User $other): string
    {
        return UserContactName::displayName($this->id, $other);
    }

    /**
     * Where this user's profile page lives.
     *
     * The public profile is the GoMarket-style page keyed by username. A handful
     * of accounts have no username yet, so those fall back to the account page,
     * which is also where avatar and cover uploads live.
     */
    public function profileUrl(): string
    {
        return $this->username
            ? route('marketplace.seller', ['username' => $this->username])
            : route('profile');
    }

    /**
     * An absolute URL for the avatar, or null to fall back to initials.
     *
     * Two shapes are stored in `avatar`: a path on our own disk, and a full
     * URL for accounts that arrived through Google. The blade avatar component
     * has always told them apart inline; the app needs the same answer and has
     * no `asset()` of its own, so the rule lives here now and both callers can
     * share it.
     */
    /** Deleted their account (App\Services\AccountDeletion). */
    public function isAnonymised(): bool
    {
        return $this->anonymised_at !== null;
    }

    /**
     * Members who can be found: in search, People, suggestions, @mentions and
     * by profile link. Deleted accounts are left out. Not a global scope on
     * purpose: their old messages still need $message->user.
     */
    public function scopeDiscoverable($query)
    {
        return $query->whereNull($this->qualifyColumn('anonymised_at'));
    }

    public function avatarUrl(): ?string
    {
        return $this->publicUrlFor($this->avatar);
    }

    public function coverUrl(): ?string
    {
        return $this->publicUrlFor($this->cover_photo);
    }

    private function publicUrlFor(?string $path): ?string
    {
        return \App\Support\StorageUrl::for($path);
    }
}
