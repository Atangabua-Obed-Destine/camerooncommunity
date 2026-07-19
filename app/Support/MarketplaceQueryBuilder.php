<?php

namespace App\Support;

use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Shared filter pipeline for marketplace listings.
 *
 * Used by FeedBrowse (interactive UI), SavedSearches (preview matches), and
 * RunSavedSearches (cron). Keeps a single source of truth so a saved search
 * always re-runs with exactly the same logic as the original feed.
 *
 * Filters schema (all optional):
 *   query         string  Free-text on title/description.
 *   categorySlug  string  Category slug (root or leaf — children included if root).
 *   region        string  Substring match on listings.region.
 *   country       string  Exact match on listings.country.
 *   condition     string  Listing condition enum value.
 *   fulfillment   string  Listing fulfillment enum value.
 *   priceMin      int     Inclusive lower bound.
 *   priceMax      int     Inclusive upper bound.
 *   sort          string  newest | price_asc | price_desc | popular | ranked.
 */
class MarketplaceQueryBuilder
{
    /** Build a base feed query (public + active) with the given filters applied. */
    public static function build(array $filters): Builder
    {
        $q = MarketplaceListing::query()->forFeed();

        // Hide listings from sellers the viewer has blocked (or who blocked the viewer).
        if ($user = auth()->user()) {
            $blocked = $user->blockedOrBlockingUserIds();
            if (! empty($blocked)) {
                $q->whereNotIn('user_id', $blocked);
            }
        }

        $term = trim((string) ($filters['query'] ?? ''));
        if ($term !== '') {
            self::applySearchTerm($q, $term);
        }

        $slug = trim((string) ($filters['categorySlug'] ?? ''));
        if ($slug !== '') {
            $cat = MarketplaceCategory::where('slug', $slug)->first();
            if ($cat) {
                $childIds = $cat->children()->pluck('id')->all();
                $q->whereIn('category_id', array_merge([$cat->id], $childIds));
            }
        }

        if (! empty($filters['region']))      { $q->where('region', 'like', '%' . $filters['region'] . '%'); }
        if (! empty($filters['country']))     { $q->where('country', $filters['country']); }
        if (! empty($filters['condition']))   { $q->where('condition', $filters['condition']); }
        if (! empty($filters['fulfillment'])) { $q->whereJsonContains('fulfillment', $filters['fulfillment']); }
        if (isset($filters['priceMin']) && $filters['priceMin'] !== '' && $filters['priceMin'] !== null) {
            $q->where('price', '>=', (int) $filters['priceMin']);
        }
        if (isset($filters['priceMax']) && $filters['priceMax'] !== '' && $filters['priceMax'] !== null) {
            $q->where('price', '<=', (int) $filters['priceMax']);
        }

        // Phase 4: category-specific attribute filters. Stored on listings as JSON.
        // We use JSON_EXTRACT (portable on MySQL 5.7+ / MariaDB 10.2+) so the
        // filter works even without virtual generated columns.
        if (! empty($filters['attrs']) && is_array($filters['attrs']) && DB::getDriverName() === 'mysql') {
            foreach ($filters['attrs'] as $k => $v) {
                if ($v === '' || $v === null) { continue; }
                $key = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $k);
                if ($key === '') { continue; }
                $q->whereRaw(
                    "LOWER(JSON_UNQUOTE(JSON_EXTRACT(attributes, ?))) = ?",
                    ['$."'.$key.'"', mb_strtolower((string) $v)]
                );
            }
        }

        return self::applySort($q, $filters['sort'] ?? 'newest');
    }

    /**
     * Restrict a feed query to listings near a point, at region granularity.
     *
     * Listings don't store precise coordinates (privacy — see App\Support\CameroonGeo),
     * so "within X km" is approximated by keeping only listings whose region's
     * centroid falls inside the radius. A null radius (or missing point) is a
     * no-op = nationwide. Listings whose region text matches no known region are
     * excluded once a radius is active, which mirrors FB hiding out-of-range items.
     */
    public static function applyRadius(Builder $q, ?float $lat, ?float $lng, ?int $radiusKm): Builder
    {
        if ($lat === null || $lng === null || $radiusKm === null || $radiusKm <= 0) {
            return $q;
        }

        $keys = \App\Support\CameroonGeo::regionsWithin($lat, $lng, (float) $radiusKm);
        $aliases = \App\Support\CameroonGeo::aliasesFor($keys);

        if ($aliases === []) {
            // Point resolves to nothing in range → no matches.
            return $q->whereRaw('1 = 0');
        }

        return $q->where(function ($w) use ($aliases) {
            foreach ($aliases as $a) {
                $w->orWhere('region', 'like', '%' . $a . '%');
            }
        });
    }

    /** Apply one of the supported sort strategies to a builder. */
    public static function applySort(Builder $q, string $sort): Builder
    {
        return match ($sort) {
            'price_asc'  => $q->orderBy('price'),
            'price_desc' => $q->orderByDesc('price'),
            'popular'    => $q->orderByDesc('views_count')->orderByDesc('favorites_count'),
            'ranked'     => $q->ranked(),
            'relevance'  => $q->orderByDesc('_relevance')->orderByDesc('published_at'),
            default      => $q
                // Active bumps (last 24h) float above everything; ties broken by recency.
                ->orderByRaw('CASE WHEN bumped_at IS NOT NULL AND bumped_at >= (NOW() - INTERVAL 24 HOUR) THEN bumped_at ELSE NULL END DESC')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at'),
        };
    }

    /**
     * Free-text search. On MySQL uses the FULLTEXT(title, description) index for
     * proper relevance ranking; falls back to LIKE on other drivers and on
     * very-short terms (FULLTEXT default ft_min_word_len=3).
     */
    private static function applySearchTerm(Builder $q, string $term): void
    {
        $driver = DB::connection()->getDriverName();
        $useFt = $driver === 'mysql' && mb_strlen($term) >= 3;

        if ($useFt) {
            // Boolean-mode tokens: treat each whitespace-delimited word as a soft "+word*"
            // so multi-word queries narrow results but short prefixes still match.
            $tokens = preg_split('/\s+/', trim($term)) ?: [];
            $expr = collect($tokens)
                ->filter(fn ($t) => mb_strlen($t) >= 2)
                ->map(fn ($t) => '+' . preg_replace('/[+\-><()~*"@]/', '', $t) . '*')
                ->implode(' ');

            if ($expr !== '') {
                $q->whereRaw('MATCH(title, description) AGAINST (? IN BOOLEAN MODE)', [$expr]);
                // _relevance column for the "relevance" sort
                $q->select('marketplace_listings.*')
                  ->selectRaw('MATCH(title, description) AGAINST (? IN BOOLEAN MODE) AS _relevance', [$expr]);
                return;
            }
        }

        // Fallback: LIKE %term% across title + description
        $like = '%' . $term . '%';
        $q->where(function ($w) use ($like) {
            $w->where('title', 'like', $like)
              ->orWhere('description', 'like', $like);
        });
    }

    /**
     * Normalize raw filter input (e.g. from a Livewire component's public props)
     * down to the keys actually used by the pipeline. Empty values are dropped
     * so two equivalent searches hash identically.
     */
    public static function normalize(array $raw): array
    {
        $allowed = ['query', 'categorySlug', 'region', 'country', 'condition', 'fulfillment', 'priceMin', 'priceMax', 'sort'];
        $out = [];
        foreach ($allowed as $k) {
            $v = $raw[$k] ?? null;
            if ($v === null || $v === '' || $v === false) { continue; }
            $out[$k] = is_string($v) ? trim($v) : $v;
        }
        if (! empty($raw['attrs']) && is_array($raw['attrs'])) {
            $cleanAttrs = [];
            foreach ($raw['attrs'] as $k => $v) {
                if ($v === null || $v === '' || $v === false) { continue; }
                $cleanAttrs[$k] = is_string($v) ? trim($v) : $v;
            }
            if ($cleanAttrs !== []) { $out['attrs'] = $cleanAttrs; }
        }
        if (($out['sort'] ?? 'newest') === 'newest') {
            unset($out['sort']);
        }
        return $out;
    }

    /**
     * Human-readable summary of a filter set (used in saved-searches list and emails).
     * Locale-aware so French users see French labels.
     */
    public static function summarize(array $filters, string $locale = 'en'): string
    {
        $fr = $locale === 'fr';
        $parts = [];

        if (! empty($filters['query'])) {
            $parts[] = '"' . $filters['query'] . '"';
        }
        if (! empty($filters['categorySlug'])) {
            $cat = MarketplaceCategory::where('slug', $filters['categorySlug'])->first();
            if ($cat) { $parts[] = $cat->localizedName(); }
        }
        if (! empty($filters['region']))      { $parts[] = ($fr ? '📍 ' : '📍 ') . $filters['region']; }
        if (! empty($filters['country']))     { $parts[] = '🌍 ' . $filters['country']; }
        if (! empty($filters['condition']))   { $parts[] = ($fr ? 'État: ' : 'Condition: ') . $filters['condition']; }
        if (! empty($filters['fulfillment'])) { $parts[] = ($fr ? 'Livraison: ' : 'Delivery: ') . $filters['fulfillment']; }

        $min = $filters['priceMin'] ?? null;
        $max = $filters['priceMax'] ?? null;
        if ($min !== null || $max !== null) {
            $parts[] = ($fr ? 'Prix: ' : 'Price: ')
                . ($min !== null ? number_format((int) $min) : '–')
                . ' – '
                . ($max !== null ? number_format((int) $max) : '∞');
        }

        // Phase 4 — category-specific attribute filters in the summary.
        if (! empty($filters['attrs']) && is_array($filters['attrs'])) {
            $schema = \App\Support\CategoryAttributeSchema::forCategory($filters['categorySlug'] ?? null);
            foreach ($filters['attrs'] as $k => $v) {
                if ($v === '' || $v === null) { continue; }
                $field = collect($schema)->firstWhere('key', $k);
                $label = $field
                    ? ($fr ? ($field['labelFr'] ?? $field['label']) : $field['label'])
                    : $k;
                $display = $field
                    ? \App\Support\CategoryAttributeSchema::displayValue($field, $v, $fr ? 'fr' : 'en')
                    : (string) $v;
                $parts[] = $label . ': ' . $display;
            }
        }

        return $parts === [] ? ($fr ? 'Tous les articles' : 'All items') : implode(' • ', $parts);
    }

    /** Build a `/marketplace?...` URL for a filter set, so a saved search opens the live feed. */
    public static function toUrl(array $filters): string
    {
        // categorySlug becomes a path segment when present (matches the /c/{slug} route)
        $base = ! empty($filters['categorySlug'])
            ? route('marketplace.category', ['slug' => $filters['categorySlug']])
            : route('marketplace.index');

        $map = [
            'query'       => 'q',
            'region'      => 'region',
            'country'     => 'country',
            'condition'   => 'condition',
            'fulfillment' => 'fulfillment',
            'priceMin'    => 'priceMin',
            'priceMax'    => 'priceMax',
            'sort'        => 'sort',
        ];
        $qs = [];
        foreach ($map as $key => $param) {
            if (isset($filters[$key]) && $filters[$key] !== '' && $filters[$key] !== null) {
                $qs[$param] = $filters[$key];
            }
        }
        if (! empty($filters['attrs']) && is_array($filters['attrs'])) {
            foreach ($filters['attrs'] as $k => $v) {
                if ($v === '' || $v === null) { continue; }
                $qs['a'][$k] = $v;
            }
        }

        return $qs === [] ? $base : $base . '?' . http_build_query($qs);
    }
}
