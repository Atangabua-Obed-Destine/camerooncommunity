<?php

namespace App\Http\Controllers;

use App\Models\ComingSoonSignup;
use App\Models\User;
use App\Support\MarketplaceQueryBuilder;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class HomeController extends Controller
{
    public function index()
    {
        $memberCount = User::count();
        $regionCount = User::whereNotNull('current_region')
            ->distinct('current_region')
            ->count('current_region');

        // Authenticated visitors get the in-app feed (uses the standard app
        // layout / Facebook-style header). Guests still see the marketing
        // landing page.
        $view = auth()->check() ? 'feed' : 'home';

        $nearby = auth()->check()
            ? $this->listingsNearUser(auth()->user())
            : ['items' => collect(), 'label' => '', 'region' => ''];

        return view($view, [
            'memberCount'     => $memberCount,
            'regionCount'     => max($regionCount, 1),
            'nearbyListings'  => $nearby['items'],
            'nearbyLabel'     => $nearby['label'],
            'nearbyRegion'    => $nearby['region'],
        ]);
    }

    /**
     * Listings to show on the home feed, the viewer's own area first.
     *
     * Their region leads; if it cannot fill the row the rest comes from their
     * country, then from anywhere, so a member in a quiet region still sees a
     * full shelf instead of an empty one. Ordering is what carries the
     * "near me first" promise — nothing is hidden.
     */
    private function listingsNearUser(User $user, int $limit = 8): array
    {
        $region  = trim((string) ($user->current_region ?? ''));
        $country = trim((string) ($user->current_country ?? ''));

        $items = collect();

        if ($region !== '') {
            $items = MarketplaceQueryBuilder::build(['region' => $region, 'sort' => 'newest'])
                ->limit($limit)
                ->get();
        }

        if ($items->count() < $limit && $country !== '') {
            $items = $items->concat(
                MarketplaceQueryBuilder::build(['country' => $country, 'sort' => 'newest'])
                    ->whereNotIn('id', $items->pluck('id')->all())
                    ->limit($limit - $items->count())
                    ->get()
            );
        }

        if ($items->count() < $limit) {
            $items = $items->concat(
                MarketplaceQueryBuilder::build(['sort' => 'newest'])
                    ->whereNotIn('id', $items->pluck('id')->all())
                    ->limit($limit - $items->count())
                    ->get()
            );
        }

        return [
            'items'  => $items,
            'label'  => $region !== '' ? $region : $country,
            'region' => $region,
        ];
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
