<?php

namespace App\Services\Market;

use App\Models\User;
use App\Support\MarketplaceQueryBuilder;

/**
 * The Home screen's GoMarket shelf, for the web feed and the app: the
 * member's region first, then their country, then anywhere, so a quiet region
 * still shows a full shelf. Ordering carries "near me first"; nothing is hidden.
 */
class NearbyListings
{
    /** @return array{items: \Illuminate\Support\Collection, label: string, region: string} */
    public function forUser(User $user, int $limit = 8): array
    {
        $region = trim((string) ($user->current_region ?? ''));
        $country = trim((string) ($user->current_country ?? ''));

        // Listings store the country as a code ("CM"), members as a name
        // ("Cameroon"): the country step never matched until it was converted.
        $countryCode = $country !== ''
            ? (array_search($country, config('cameroon.seeded_countries', []), true) ?: (strlen($country) === 2 ? strtoupper($country) : null))
            : null;

        $items = collect();
        $with = ['cover', 'category'];

        if ($region !== '') {
            $items = MarketplaceQueryBuilder::build(['region' => $region, 'sort' => 'newest'])->with($with)->limit($limit)->get();
        }

        if ($items->count() < $limit && $countryCode) {
            $items = $items->concat(
                MarketplaceQueryBuilder::build(['country' => $countryCode, 'sort' => 'newest'])
                    ->with($with)
                    ->whereNotIn('id', $items->pluck('id')->all())
                    ->limit($limit - $items->count())
                    ->get()
            );
        }

        if ($items->count() < $limit) {
            $items = $items->concat(
                MarketplaceQueryBuilder::build(['sort' => 'newest'])
                    ->with($with)
                    ->whereNotIn('id', $items->pluck('id')->all())
                    ->limit($limit - $items->count())
                    ->get()
            );
        }

        return ['items' => $items, 'label' => $region !== '' ? $region : $country, 'region' => $region];
    }
}
