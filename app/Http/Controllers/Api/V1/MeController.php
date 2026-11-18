<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in member changing their own account. Only the fields the app's
 * screens edit so far: onboarding's bio, and the language switch in the
 * header and the drawer (the web's /language toggle).
 */
class MeController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bio' => 'sometimes|nullable|string|max:500',
            'language_pref' => 'sometimes|in:en,fr',
        ]);

        $user = $request->user();
        $user->update($data);

        return response()->json(['user' => new UserResource($user->refresh())]);
    }
}
