<?php

namespace App\Console\Commands;

use App\Models\UserLocationHistory;
use Illuminate\Console\Command;

/**
 * Keeps the position trail to a fixed window.
 *
 * Movement history is sensitive, and a table that grows forever is both a
 * storage problem and a liability if the database ever leaks. 90 days is long
 * enough to investigate a report or see a pattern, and short enough to defend.
 */
class PruneLocationHistory extends Command
{
    protected $signature = 'locations:prune {--days=90 : Keep points newer than this}';

    protected $description = 'Delete location history older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        // Chunked so a long-neglected table does not lock for a single huge
        // delete; withoutGlobalScopes because this runs with no tenant bound.
        $deleted = 0;
        do {
            $batch = UserLocationHistory::withoutGlobalScopes()
                ->where('created_at', '<', $cutoff)
                ->limit(1000)
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Pruned {$deleted} location point(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
