<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lang' => 'required|in:en,fr',
        ]);

        if ($request->user()) {
            $request->user()->update(['language_pref' => $validated['lang']]);
        }

        session(['locale' => $validated['lang']]);

        return response()->json(['status' => 'ok']);
    }
}
