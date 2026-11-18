<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HomeController as WebHome;
use App\Http\Presenters\MarketPresenter;
use App\Models\SponsoredAd;
use App\Services\Market\NearbyListings;
use App\Support\CameroonGeo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The signed-in Home (feed.blade.php): the welcome, the sponsored stories,
 * the GoMarket shelf and the community figures, in one request.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, NearbyListings $nearby, MarketPresenter $present): JsonResponse
    {
        $user = $request->user();
        $shelf = $nearby->forUser($user);
        $stats = WebHome::communityStats();

        $from = $user->current_lat !== null && $user->current_lng !== null
            ? ['lat' => (float) $user->current_lat, 'lng' => (float) $user->current_lng]
            : ($user->current_region ? CameroonGeo::centroidForRegion((string) $user->current_region) : null);

        return response()->json([
            // "Welcome back, Ama!": the first word of the name, as the web does.
            'first_name' => trim(explode(' ', trim((string) $user->name))[0] ?? '') ?: null,
            'ads' => $this->ads(),
            'shelf' => [
                'label' => $shelf['label'] ?: null,
                'region' => $shelf['region'] ?: null,
                'listings' => $shelf['items']->map(fn ($l) => $present->card($l, $from['lat'] ?? null, $from['lng'] ?? null))->values(),
            ],
            'stats' => ['members' => $stats['members'], 'regions' => $stats['regions']],
        ]);
    }

    /** The stories strip: up to ten active ads, as GET /ads/home returns them. */
    private function ads(): array
    {
        return SponsoredAd::active()->orderByDesc('priority')->orderByDesc('id')->limit(10)->get()
            ->map(function (SponsoredAd $ad) {
                $embed = $ad->youtubeEmbedUrl();
                $youtubeId = $embed ? basename(parse_url($embed, PHP_URL_PATH) ?: '') : null;

                return [
                    'id' => $ad->id,
                    'title' => $ad->title,
                    'description' => $ad->description,
                    'image_url' => $ad->imageUrl() ?: ($youtubeId ? "https://i.ytimg.com/vi/{$youtubeId}/hqdefault.jpg" : null),
                    'youtube_id' => $youtubeId ?: null,
                    'advertiser' => $ad->advertiser_name,
                    'cta' => $ad->link_label,
                    'has_link' => (bool) $ad->link_url,
                ];
            })->values()->all();
    }

    /** POST /ads/{ad}/impression: once per member per ad per day (the web: per session). */
    public function impression(Request $request, SponsoredAd $ad): JsonResponse
    {
        if (Cache::add("ad-seen:{$ad->id}:{$request->user()->id}", 1, now()->endOfDay())) {
            $ad->recordImpression();
        }

        return response()->json(['ok' => true]);
    }

    /** POST /ads/{ad}/click: counted, then the app opens the link it gets back. */
    public function click(SponsoredAd $ad): JsonResponse
    {
        $ad->recordClick();

        return response()->json(['url' => $ad->link_url]);
    }
}
