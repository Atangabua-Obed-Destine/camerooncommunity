<?php

namespace App\Services\Market;

use App\Models\MarketplaceSavedSearch;
use App\Models\User;
use App\Support\MarketplaceQueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Saved searches: the web's "Save this search" strip and Saved searches
 * page, and the app's, through one place. Filters are stored in
 * MarketplaceQueryBuilder's schema, so the hourly RunSavedSearches job re-runs
 * exactly what was saved.
 */
class SavedSearchService
{
    /** More than this and the alerts become noise. */
    public const LIMIT = 20;

    /**
     * The member's searches, newest change first, each with its summary, the
     * web feed URL that reproduces it and how many listings appeared since
     * they last looked.
     */
    public function list(User $user, string $locale): Collection
    {
        return MarketplaceSavedSearch::where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (MarketplaceSavedSearch $s) use ($locale) {
                $filters = $this->filters($s);
                $s->setAttribute('matches_url', MarketplaceQueryBuilder::toUrl($filters));
                $s->setAttribute('summary', MarketplaceQueryBuilder::summarize($filters, $locale));
                $s->setAttribute('new_matches', $this->newMatches($s));

                return $s;
            });
    }

    public function newMatches(MarketplaceSavedSearch $s): int
    {
        return MarketplaceQueryBuilder::build($this->filters($s))
            ->where('published_at', '>', $s->last_seen_at ?? $s->created_at)
            ->count();
    }

    /** The menu badge: new matches across every saved search. */
    public function totalNew(User $user): int
    {
        return (int) MarketplaceSavedSearch::where('user_id', $user->id)->get()
            ->sum(fn (MarketplaceSavedSearch $s) => $this->newMatches($s));
    }

    /**
     * Saves the filters (already normalised) under [name], or a name made
     * from them. Errors: 'empty' (nothing chosen), 'limit'. The same filters
     * saved again answer the existing search with existing = true, rather
     * than a second copy sending every alert twice.
     *
     * @return array{search?: MarketplaceSavedSearch, existing?: bool, error?: string}
     */
    public function save(User $user, array $filters, ?string $name = null): array
    {
        $filters = MarketplaceQueryBuilder::normalize($filters);
        if ($filters === []) {
            return ['error' => 'empty'];
        }

        $mine = MarketplaceSavedSearch::where('user_id', $user->id)->get();
        $same = $mine->first(fn (MarketplaceSavedSearch $s) => $this->filters($s) == $filters);
        if ($same) {
            return ['search' => $same, 'existing' => true];
        }
        if ($mine->count() >= self::LIMIT) {
            return ['error' => 'limit'];
        }

        $label = trim((string) $name);
        if ($label === '') {
            $label = Str::limit(MarketplaceQueryBuilder::summarize($filters, app()->getLocale()), 100);
        }

        $search = MarketplaceSavedSearch::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'name' => mb_substr($label, 0, 120),
            'filters' => $filters,
            'notify_email' => false,
            'notify_push' => true,
            // Now, so the first run doesn't alert on the whole existing feed.
            'last_notified_at' => now(),
            'last_seen_at' => now(),
        ]);
        $this->forgetBadge($user);

        return ['search' => $search, 'existing' => false];
    }

    public function owned(User $user, int $id): ?MarketplaceSavedSearch
    {
        return MarketplaceSavedSearch::where('id', $id)->where('user_id', $user->id)->first();
    }

    /** False for an empty name. */
    public function rename(MarketplaceSavedSearch $s, string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }
        $s->update(['name' => mb_substr($name, 0, 120)]);

        return true;
    }

    /** [channel] is 'push' (in-app) or 'email'. */
    public function setNotify(MarketplaceSavedSearch $s, string $channel, bool $on): void
    {
        $s->update([$channel === 'email' ? 'notify_email' : 'notify_push' => $on]);
    }

    public function delete(MarketplaceSavedSearch $s): void
    {
        $user = $s->user;
        $s->delete();
        $this->forgetBadge($user);
    }

    public function markSeen(MarketplaceSavedSearch $s): void
    {
        $s->update(['last_seen_at' => now()]);
        $this->forgetBadge($s->user);
    }

    public function markAllSeen(User $user): void
    {
        MarketplaceSavedSearch::where('user_id', $user->id)->update(['last_seen_at' => now()]);
        $this->forgetBadge($user);
    }

    public function filters(MarketplaceSavedSearch $s): array
    {
        return is_array($s->filters) ? $s->filters : [];
    }

    public static function message(string $code): string
    {
        return match ($code) {
            'empty' => __('Pick at least one filter before saving'),
            'limit' => __('You can save up to 20 searches'),
            default => __('Something went wrong.'),
        };
    }

    /** The sidebar's count is cached for two minutes (sidebar-nav.blade.php). */
    private function forgetBadge(?User $user): void
    {
        if ($user) {
            Cache::forget('mp:ss-new:'.$user->id);
        }
    }
}
