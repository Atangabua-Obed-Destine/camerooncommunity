<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;

/**
 * What the app's landing screen needs from the server: the web's guest home
 * page (resources/views/home.blade.php) as a phone sees it.
 *
 * Almost nothing on that page is dynamic at phone width. The community
 * figures only appear in the desktop slider, the location badge is hidden
 * below 640px, and the copy is fixed. What does change is whether the Kamer
 * bubble shows: the guest layout draws it only while the AI is available, and
 * an admin can switch the AI off at /admin/ai. So that is what this says.
 *
 * Unauthenticated: it is the screen people see before they have an account.
 */
class LandingController extends Controller
{
    public function __invoke(AIService $ai): JsonResponse
    {
        return response()->json([
            'kamer' => [
                // The guest layout's @if(app(AIService::class)->isAvailable()).
                'available' => $ai->isAvailable(),
            ],
        ]);
    }
}
