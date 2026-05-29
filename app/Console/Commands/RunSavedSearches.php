<?php

namespace App\Console\Commands;

use App\Mail\SavedSearchMatchesMail;
use App\Models\MarketplaceSavedSearch;
use App\Services\NotificationService;
use App\Support\MarketplaceQueryBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Run every saved search whose last_notified_at < now() - minInterval.
 * For each search that produces new matches:
 *   - Always create an in-app notification (via NotificationService) when notify_push is on.
 *   - Send a digest email when notify_email is on.
 *   - Bump last_notified_at to now() so we never double-alert.
 */
class RunSavedSearches extends Command
{
    protected $signature = 'marketplace:run-saved-searches
        {--user= : Limit to a single user id (debug)}
        {--dry-run : Don\'t actually notify, just print what would happen}
        {--min-interval=55 : Minimum minutes between runs per saved search}';

    protected $description = 'Scan saved marketplace searches and notify users of fresh matches.';

    public function handle(NotificationService $notifier): int
    {
        $minInterval = (int) $this->option('min-interval');
        $dry = (bool) $this->option('dry-run');
        $userId = $this->option('user');

        $cutoff = now()->subMinutes(max(1, $minInterval));

        $q = MarketplaceSavedSearch::query()
            ->where(function ($w) {
                $w->where('notify_push', true)->orWhere('notify_email', true);
            })
            ->where(function ($w) use ($cutoff) {
                $w->whereNull('last_notified_at')->orWhere('last_notified_at', '<=', $cutoff);
            });
        if ($userId) { $q->where('user_id', (int) $userId); }

        $total = $q->count();
        $this->info("Scanning {$total} saved search(es)…");

        $sent = 0;
        $errors = 0;

        $q->with('user')->chunk(100, function ($searches) use (&$sent, &$errors, $notifier, $dry) {
            foreach ($searches as $s) {
                try {
                    if (! $s->user) { continue; } // orphaned

                    $filters = is_array($s->filters) ? $s->filters : [];
                    $since = $s->last_notified_at ?? $s->created_at;

                    $matches = MarketplaceQueryBuilder::build($filters)
                        ->where('published_at', '>', $since)
                        ->where('user_id', '!=', $s->user_id) // don't alert sellers about their own listings
                        ->with(['media' => fn ($m) => $m->limit(1)])
                        ->limit(20)
                        ->get();

                    if ($matches->isEmpty()) {
                        // Bump the cursor anyway so we don't re-scan the same window every minute.
                        if (! $dry) { $s->update(['last_notified_at' => now()]); }
                        continue;
                    }

                    $count = $matches->count();
                    $this->line("  · user={$s->user_id} search=\"{$s->name}\" → {$count} new");

                    if ($dry) { continue; }

                    if ($s->notify_push) {
                        $notifier->send(
                            $s->user,
                            'marketplace.saved_search',
                            trans_choice(
                                '{1} 1 new match for ":name"|[2,*] :count new matches for ":name"',
                                $count,
                                ['count' => $count, 'name' => $s->name],
                            ),
                            MarketplaceQueryBuilder::summarize($filters, $s->user->language?->value ?? app()->getLocale()),
                            [
                                'saved_search_id' => $s->id,
                                'url'             => route('marketplace.saved'),
                                'matches_url'     => MarketplaceQueryBuilder::toUrl($filters),
                                'count'           => $count,
                            ],
                        );
                    }

                    if ($s->notify_email && $s->user->email) {
                        try {
                            Mail::to($s->user->email)->send(new SavedSearchMatchesMail($s, $matches));
                        } catch (\Throwable $mailEx) {
                            Log::warning('SavedSearch email failed', ['user' => $s->user_id, 'error' => $mailEx->getMessage()]);
                        }
                    }

                    $s->update(['last_notified_at' => now()]);
                    $sent++;

                    // Bust the sidebar badge cache so the user sees the badge appear immediately.
                    \Illuminate\Support\Facades\Cache::forget('mp:ss-new:' . $s->user_id);
                } catch (\Throwable $e) {
                    $errors++;
                    Log::error('RunSavedSearches failed', [
                        'saved_search_id' => $s->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $this->info("Done. Notified: {$sent}. Errors: {$errors}." . ($dry ? ' (dry-run)' : ''));
        return self::SUCCESS;
    }
}
