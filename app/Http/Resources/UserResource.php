<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member, as the app sees them.
 *
 * Shaped deliberately rather than handed the model: `users` carries an email,
 * a phone number, a MoMo number, coordinates and a Google id, none of which
 * belongs in a payload about somebody else. Those fields appear only when the
 * member being described is the one asking — so this one resource is safe to
 * reuse for a profile, a message sender and a search result alike.
 *
 * URLs are absolute. The app has no `asset()` to resolve a path against.
 *
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'username' => $this->username,
            'bio' => $this->bio,

            'avatar_url' => $this->avatarUrl(),
            'cover_url' => $this->coverUrl(),

            // Where they are from, and where they are now. Both are public:
            // the whole point of the platform is finding your people nearby.
            'country_of_origin' => $this->country_of_origin,
            'home_region' => $this->home_region,
            'home_city' => $this->home_city,
            'current_country' => $this->current_country,
            'current_region' => $this->current_region,
            'current_city' => $this->current_city,

            'account_type' => $this->account_type?->value,
            'community_points' => $this->community_points,
            'is_verified' => (bool) $this->is_verified,
            'is_identity_verified' => (bool) $this->is_identity_verified,
            'is_founding_member' => (bool) $this->is_founding_member,
            'is_community_leader' => (bool) $this->is_community_leader,

            'created_at' => $this->created_at?->toIso8601String(),

            ...$this->privateFields($request),
        ];
    }

    /**
     * The fields only the account holder may see.
     *
     * `active_country` / `active_region` are in here not because they are
     * sensitive but because they are the client's scoping context — the
     * equivalent of E-ride's zoneId. They decide which rooms, listings and
     * home feed the member sees, and a stale value shows an empty screen
     * rather than an error, so the app needs to read its own back.
     *
     * @return array<string, mixed>
     */
    private function privateFields(Request $request): array
    {
        if ($request->user()?->id !== $this->id) {
            return [];
        }

        return [
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'phone' => $this->phone,
            'momo_number' => $this->momo_number,
            'momo_provider' => $this->momo_provider,

            'active_country' => $this->active_country,
            'active_region' => $this->active_region,
            'current_lat' => $this->current_lat,
            'current_lng' => $this->current_lng,

            'language_pref' => $this->language_pref?->value,

            // The account menu and the drawer show "Admin Panel" to these two
            // roles, as the web's do. The panel itself stays on the web.
            'is_admin' => $this->hasRole('super_admin') || $this->hasRole('admin'),
            'show_archived_away' => (bool) $this->show_archived_away,
            'residency_months' => $this->residency_months,

            // The app routes on this: a member without it goes to onboarding
            // before anything else, exactly as the web login does.
            'onboarded' => $this->onboarded_at !== null,
            'onboarded_at' => $this->onboarded_at?->toIso8601String(),
        ];
    }
}
