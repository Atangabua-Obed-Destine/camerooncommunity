<?php

namespace App\Console\Commands;

use App\Enums\RoomType;
use App\Models\User;
use App\Models\YardRoom;
use Illuminate\Console\Command;

class CleanupUkRegions extends Command
{
    protected $signature = 'cleanup:uk-regions {--apply : Actually delete bad rooms and clear bad user regions}';
    protected $description = 'Find/clean UK regional rooms and users with non-ITL regions like "England"';

    public function handle(): int
    {
        $valid = config('cameroon.seeded_regions.GB', []);
        $this->info('Valid GB ITL regions: ' . implode(', ', $valid));
        $this->newLine();

        $badRooms = YardRoom::where('country', 'United Kingdom')
            ->where('room_type', RoomType::Regional)
            ->whereNotIn('region', $valid)
            ->get();

        $this->warn("Bad UK regional rooms ({$badRooms->count()}):");
        foreach ($badRooms as $r) {
            $this->line("  - id={$r->id} region='{$r->region}' name='{$r->name}' members={$r->members_count}");
        }

        $badUsers = User::where(function ($q) use ($valid) {
                $q->whereNotNull('current_region')
                  ->where('current_country', 'United Kingdom')
                  ->whereNotIn('current_region', $valid);
            })
            ->orWhere(function ($q) use ($valid) {
                $q->whereNotNull('active_region')
                  ->where('active_country', 'United Kingdom')
                  ->whereNotIn('active_region', $valid);
            })
            ->get();

        $this->newLine();
        $this->warn("Users with non-ITL UK region ({$badUsers->count()}):");
        foreach ($badUsers as $u) {
            $this->line("  - id={$u->id} username='{$u->username}' current='{$u->current_region}' active='{$u->active_region}'");
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->info('DRY RUN. Re-run with --apply to delete bad rooms and clear bad user regions.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Applying cleanup...');

        foreach ($badRooms as $r) {
            $r->members()->delete();
            $r->delete();
            $this->line("  Deleted room id={$r->id}");
        }

        foreach ($badUsers as $u) {
            $updates = [];
            if ($u->current_region && ! in_array($u->current_region, $valid, true)) {
                $updates['current_region'] = null;
            }
            if ($u->active_region && ! in_array($u->active_region, $valid, true)) {
                $updates['active_region'] = null;
            }
            if ($updates) {
                $u->updateQuietly($updates);
                $this->line("  Cleared user id={$u->id} regions: " . json_encode($updates));
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
