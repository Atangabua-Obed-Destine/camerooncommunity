<?php

namespace App\Console\Commands;

use App\Models\YardCall;
use Illuminate\Console\Command;

/**
 * Show what actually happened in the most recent call.
 *
 * Diagnosing a call from browser consoles means catching the right browser at
 * the right moment, and from the log means tailing it after the right instant.
 * The database has neither problem: answering writes the participant row, so
 * whether the callee's answer reached the server is a fact on disk long after
 * the call is over.
 *
 * Read it top to bottom — the first thing that did not happen is the fault.
 */
class ShowLastCall extends Command
{
    protected $signature = 'calls:last {--count=1 : How many recent calls to show}';

    protected $description = 'Show the most recent call: who was invited, who answered, and when';

    public function handle(): int
    {
        $calls = YardCall::withoutGlobalScopes()
            ->with(['participants.user:id,name,username', 'initiator:id,name,username'])
            ->latest('id')
            ->limit(max(1, (int) $this->option('count')))
            ->get();

        if ($calls->isEmpty()) {
            $this->warn('No calls recorded.');

            return self::SUCCESS;
        }

        foreach ($calls as $call) {
            $this->newLine();
            $this->info(sprintf(
                'Call %s  (#%d, room %d, %s)',
                $call->uuid,
                $call->id,
                $call->room_id,
                $call->call_type,
            ));

            $name = fn ($u) => $u?->username ?? $u?->name ?? 'unknown';

            $this->line('  started by : ' . $name($call->initiator) . ' (#' . $call->initiated_by . ')');
            $this->line('  status     : ' . $call->status);
            $this->line('  created    : ' . $call->created_at);
            $this->line('  answered   : ' . ($call->started_at ?: '— never'));
            $this->line('  ended      : ' . ($call->ended_at ?: '— still open'));

            if ($call->started_at) {
                $this->line('  rang for   : ' . $call->created_at->diffInSeconds($call->started_at) . 's');
            }

            $this->newLine();
            $this->line('  Participants');

            foreach ($call->participants as $p) {
                $this->line(sprintf(
                    '    %-20s %-10s %s',
                    $name($p->user) . ' (#' . $p->user_id . ')',
                    $p->status,
                    $p->joined_at ? 'joined at ' . $p->joined_at : '',
                ));
            }

            $this->newLine();
            $this->verdict($call);
        }

        return self::SUCCESS;
    }

    /**
     * Say in one line which step did not happen.
     */
    private function verdict(YardCall $call): void
    {
        $others = $call->participants->where('user_id', '!=', $call->initiated_by);
        $joined = $others->where('status', 'joined');

        if ($others->isEmpty()) {
            $this->error('  Nobody was invited. The call rang on no device.');

            return;
        }

        if ($joined->isEmpty()) {
            $this->error('  Nobody answered: every invitee is still ' . $others->pluck('status')->unique()->join('/') . '.');
            $this->line('    The callee\'s accept never reached the server, so the caller was');
            $this->line('    right to keep ringing. Look at the callee, not the caller.');

            return;
        }

        $this->info('  The answer reached the server: ' . $joined->count() . ' of ' . $others->count() . ' joined.');

        if (! $call->started_at) {
            $this->error('    …but the call was never marked active, so the caller was never told.');

            return;
        }

        $this->line('    From here the caller should have received the \'joined\' broadcast and');
        $this->line('    sent an offer. If it did not, the fault is on the caller:');
        $this->line('      grep -E "Call signal" storage/logs/laravel.log | tail');
    }
}
