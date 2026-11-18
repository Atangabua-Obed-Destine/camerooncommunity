<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The phone's push token: registered after sign-in (and whenever Firebase
 * rotates it), dropped at sign-out so a shared phone stops getting the last
 * member's messages.
 */
class DeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|max:512',
            'platform' => 'required|in:android,ios',
        ]);

        // A token is an install: if another member signed in on this phone
        // before, the token moves to whoever is signed in now.
        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $request->user()->id, 'platform' => $data['platform'], 'last_seen_at' => now()],
        );

        return response()->json(['registered' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|max:512']);

        DeviceToken::where('token', $data['token'])->where('user_id', $request->user()->id)->delete();

        return response()->json(['registered' => false]);
    }
}
