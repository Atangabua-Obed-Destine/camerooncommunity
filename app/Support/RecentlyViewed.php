<?php

namespace App\Support;

use App\Models\MarketplaceListing;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Request;

/**
 * Tracks marketplace listings the visitor has looked at recently.
 *
 * Storage: a tiny JSON-encoded cookie (slugs only, capped at 12). Works for
 * guests + logged-in users without any DB writes. Cookie is signed by Laravel
 * so it can't be tampered with to slip arbitrary slugs in.
 */
class RecentlyViewed
{
    private const COOKIE = 'mp_recent';
    private const MAX    = 12;
    private const TTL    = 60 * 24 * 30; // 30 days

    /** Push a slug to the front; trims to MAX; queues an updated cookie. */
    public static function track(string $slug): void
    {
        $slug = trim($slug);
        if ($slug === '') { return; }

        $list = self::list();
        $list = array_values(array_filter($list, fn ($s) => $s !== $slug));
        array_unshift($list, $slug);
        $list = array_slice($list, 0, self::MAX);

        Cookie::queue(self::COOKIE, json_encode($list), self::TTL);
    }

    /** Current slug list (most-recent first). */
    public static function list(): array
    {
        $raw = Request::cookie(self::COOKIE);
        if (! $raw) { return []; }
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /**
     * Hydrate slugs into live (active) listings, preserving recency order
     * and excluding the optional $exceptSlug (handy on a detail page).
     */
    public static function listings(?string $exceptSlug = null, int $limit = 8): Collection
    {
        $slugs = array_values(array_filter(self::list(), fn ($s) => $s !== $exceptSlug));
        $slugs = array_slice($slugs, 0, $limit);
        if ($slugs === []) { return new Collection(); }

        $rows = MarketplaceListing::query()
            ->forFeed()
            ->whereIn('slug', $slugs)
            ->with(['media' => fn ($q) => $q->orderBy('position')->limit(1), 'category'])
            ->get()
            ->keyBy('slug');

        return new Collection(array_values(array_filter(array_map(fn ($s) => $rows->get($s), $slugs))));
    }

    public static function forget(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE));
    }
}
