<?php

namespace App\Http\Controllers;

use App\Enums\MessageType;
use App\Models\ComingSoonSignup;
use App\Models\MarketplaceListing;
use App\Models\User;
use App\Models\YardMessage;
use App\Support\MarketplaceQueryBuilder;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class HomeController extends Controller
{
    public function index()
    {
        $stats = self::communityStats();

        // Authenticated visitors get the in-app feed (uses the standard app
        // layout / Facebook-style header). Guests still see the marketing
        // landing page.
        $view = auth()->check() ? 'feed' : 'home';

        $nearby = auth()->check()
            ? $this->listingsNearUser(auth()->user())
            : ['items' => collect(), 'label' => '', 'region' => ''];

        return view($view, [
            'memberCount'     => $stats['members'],
            'regionCount'     => $stats['regions'],
            'stats'           => $stats,
            'nearbyListings'  => $nearby['items'],
            'nearbyLabel'     => $nearby['label'],
            'nearbyRegion'    => $nearby['region'],
        ]);
    }

    /**
     * The platform's real figures, as shown on the homepage. No floors and no
     * fixed numbers: published claims about the platform have to be true
     * (UK DMCC Act 2024). Cached for ten minutes so a busy homepage does not
     * count every message on each visit.
     *
     * @return array{members: int, countries: int, regions: int, listings: int, messages: int}
     */
    public static function communityStats(): array
    {
        $tenantId = app()->bound('currentTenant') ? app('currentTenant')?->id : null;

        return Cache::remember("home:community-stats:{$tenantId}", now()->addMinutes(10), function () {
            $members = User::query()->where('is_active', true)->whereNull('anonymised_at');

            return [
                'members'   => (clone $members)->count(),
                'countries' => (clone $members)->whereNotNull('current_country')->where('current_country', '!=', '')
                    ->distinct()->count('current_country'),
                'regions'   => (clone $members)->whereNotNull('current_region')->where('current_region', '!=', '')
                    ->distinct()->count('current_region'),
                'listings'  => MarketplaceListing::active()->count(),
                'messages'  => YardMessage::query()->where('is_deleted', false)
                    ->whereNotIn('message_type', [MessageType::System->value, MessageType::CallLog->value])
                    ->count(),
            ];
        });
    }

    /**
     * Listings to show on the home feed, the viewer's own area first. Shared
     * with the app's Home (App\Services\Market\NearbyListings).
     */
    private function listingsNearUser(User $user, int $limit = 8): array
    {
        return app(\App\Services\Market\NearbyListings::class)->forUser($user, $limit);
    }

    /**
     * Public Kamer AI chat endpoint used by the floating chat widget on the
     * landing page (unauthenticated visitors). Lightly rate-limited per IP.
     */
    public function kamerChat(Request $request, AIService $ai): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string|max:1000',
            'lang' => 'nullable|string|in:en,fr',
        ]);

        $key = 'kamer-public-chat:' . ($request->ip() ?? 'anon');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'reply' => ($data['lang'] ?? 'en') === 'fr'
                    ? "Doucement ! Réessaie dans {$seconds}s."
                    : "Slow down! Try again in {$seconds}s.",
            ], 429);
        }
        RateLimiter::hit($key, 60);

        if (! $ai->isAvailable()) {
            return response()->json([
                'reply' => ($data['lang'] ?? 'en') === 'fr'
                    ? "Je suis temporairement indisponible. Réessayez plus tard."
                    : "I'm temporarily unavailable. Please try again later.",
            ]);
        }

        $language = ($data['lang'] ?? 'en') === 'fr' ? 'French' : 'English';
        $reply = $ai->chat([
            ['role' => 'user', 'content' => $data['message']],
        ], $language);

        return response()->json([
            'reply' => $reply ?: (($data['lang'] ?? 'en') === 'fr'
                ? "Désolé, je n'ai pas pu traiter cela."
                : "Sorry, I couldn't process that."),
        ]);
    }
}
