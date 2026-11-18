<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A counter-offer used to be a second offer row (parent_offer_id set), which
 * landed in the seller's own Received tab and which the buyer couldn't accept.
 * Now it is `counter_amount` on the buyer's offer, status countered, and the
 * buyer answers it (OfferService). This moves open counters across: the offer
 * they answer gets the amount, and the stray row is closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $strays = DB::table('marketplace_offers')
            ->whereNotNull('parent_offer_id')
            ->whereIn('status', ['pending', 'countered'])
            ->get(['id', 'parent_offer_id', 'amount']);

        foreach ($strays as $stray) {
            DB::table('marketplace_offers')
                ->where('id', $stray->parent_offer_id)
                ->where('status', 'countered')
                ->update(['counter_amount' => $stray->amount, 'updated_at' => now()]);

            DB::table('marketplace_offers')
                ->where('id', $stray->id)
                ->update(['status' => 'withdrawn', 'responded_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the old shape is the bug.
    }
};
