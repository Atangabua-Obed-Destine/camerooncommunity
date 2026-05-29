<?php

namespace App\Support;

use App\Models\MarketplaceListing;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Compute the small set of "trust badges" we display next to a marketplace seller.
 *
 * Each badge is an associative array:
 *   key       string  Stable identifier (used as CSS hook).
 *   label     string  English label.
 *   labelFr   string  French label.
 *   icon      string  Single emoji.
 *   tone      string  Tailwind color class fragment used for the chip (green|yellow|blue|red|slate).
 *   tooltip   string  Optional longer explanation in English.
 *   tooltipFr string  Same in French.
 *
 * Results are cached per-user for 10 minutes since they're shown on every
 * listing card / detail page.
 */
class TrustBadges
{
    /** Minimum number of reviews before "Top rated" is awarded. */
    public const TOP_RATED_MIN_REVIEWS = 5;
    public const TOP_RATED_MIN_AVG     = 4.6;

    /** Sales required for "Trusted seller". */
    public const TRUSTED_MIN_SALES     = 5;

    /**
     * @return array<int, array{key:string, label:string, labelFr:string, icon:string, tone:string, tooltip?:string, tooltipFr?:string}>
     */
    public static function forSeller(?User $seller): array
    {
        if (! $seller) {
            return [];
        }

        return Cache::remember(
            'mp:badges:seller:' . $seller->id,
            now()->addMinutes(10),
            fn () => self::compute($seller),
        );
    }

    /**
     * Returns true when the seller has fewer than 3 listings and joined recently.
     * Used to show a yellow "New seller — buy with care" notice.
     */
    public static function isNewSeller(User $seller): bool
    {
        return Cache::remember(
            'mp:badges:newseller:' . $seller->id,
            now()->addMinutes(30),
            function () use ($seller) {
                $listings = MarketplaceListing::query()->where('user_id', $seller->id)->count();
                $joinedRecently = $seller->created_at && $seller->created_at->gt(now()->subDays(14));
                return $listings < 3 && $joinedRecently;
            },
        );
    }

    /** Manually drop cached badges (call when seller settings change). */
    public static function forget(int $userId): void
    {
        Cache::forget('mp:badges:seller:' . $userId);
        Cache::forget('mp:badges:newseller:' . $userId);
    }

    /** @return array<int, array<string,string>> */
    protected static function compute(User $seller): array
    {
        $badges = [];

        // Identity verified (KYC).
        if ($seller->is_identity_verified) {
            $badges[] = [
                'key'       => 'identity',
                'label'     => 'ID Verified',
                'labelFr'   => 'Identité vérifiée',
                'icon'      => '🛡️',
                'tone'      => 'blue',
                'tooltip'   => 'This seller has confirmed their identity.',
                'tooltipFr' => 'Ce vendeur a confirmé son identité.',
            ];
        } elseif ($seller->is_verified) {
            $badges[] = [
                'key'       => 'verified',
                'label'     => 'Verified',
                'labelFr'   => 'Vérifié',
                'icon'      => '✓',
                'tone'      => 'blue',
                'tooltip'   => 'Account is verified by Cameroon Network.',
                'tooltipFr' => 'Compte vérifié par Cameroon Network.',
            ];
        }

        // Top rated.
        $count = (int) ($seller->seller_rating_count ?? 0);
        $avg   = (float) ($seller->seller_rating_avg ?? 0);
        if ($count >= self::TOP_RATED_MIN_REVIEWS && $avg >= self::TOP_RATED_MIN_AVG) {
            $badges[] = [
                'key'       => 'top_rated',
                'label'     => 'Top rated',
                'labelFr'   => 'Très bien noté',
                'icon'      => '⭐',
                'tone'      => 'yellow',
                'tooltip'   => sprintf('%.1f★ from %d reviews.', $avg, $count),
                'tooltipFr' => sprintf('%.1f★ sur %d avis.', $avg, $count),
            ];
        }

        // Trusted seller (sold listings).
        $sold = MarketplaceListing::query()
            ->where('user_id', $seller->id)
            ->whereNotNull('sold_at')
            ->count();
        if ($sold >= self::TRUSTED_MIN_SALES) {
            $badges[] = [
                'key'       => 'trusted',
                'label'     => $sold . '+ sold',
                'labelFr'   => $sold . '+ vendus',
                'icon'      => '🤝',
                'tone'      => 'green',
                'tooltip'   => sprintf('Completed %d sales on GoMarket.', $sold),
                'tooltipFr' => sprintf('A conclu %d ventes sur GoMarket.', $sold),
            ];
        }

        // Founding / community leader.
        if ($seller->is_founding_member ?? false) {
            $badges[] = [
                'key'     => 'founder',
                'label'   => 'Founding member',
                'labelFr' => 'Membre fondateur',
                'icon'    => '🇨🇲',
                'tone'    => 'red',
            ];
        } elseif ($seller->is_community_leader ?? false) {
            $badges[] = [
                'key'     => 'leader',
                'label'   => 'Community leader',
                'labelFr' => 'Leader communautaire',
                'icon'    => '👑',
                'tone'    => 'yellow',
            ];
        }

        // Mobile-money ready (payments enabled).
        if (! empty($seller->momo_number)) {
            $badges[] = [
                'key'       => 'momo',
                'label'     => 'MoMo ready',
                'labelFr'   => 'MoMo activé',
                'icon'      => '📱',
                'tone'      => 'green',
                'tooltip'   => 'Accepts Mobile Money payments.',
                'tooltipFr' => 'Accepte les paiements Mobile Money.',
            ];
        }

        return $badges;
    }

    /** Convert a tone keyword into a ring + text + bg Tailwind class string. */
    public static function chipClasses(string $tone): string
    {
        return match ($tone) {
            'green'  => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'yellow' => 'bg-amber-50 text-amber-800 ring-amber-200',
            'blue'   => 'bg-blue-50 text-blue-700 ring-blue-200',
            'red'    => 'bg-cm-red/10 text-cm-red ring-cm-red/30',
            default  => 'bg-slate-100 text-slate-700 ring-slate-200',
        };
    }
}
