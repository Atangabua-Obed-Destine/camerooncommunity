<?php

namespace App\Http\Controllers;

use App\Models\ComingSoonSignup;
use App\Models\User;
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

        return view('home', [
            'memberCount' => $memberCount,
            'regionCount' => max($regionCount, 1),
        ]);
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
