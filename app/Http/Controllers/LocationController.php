<?php

namespace App\Http\Controllers;

use App\Services\LocationService;
use App\Services\LocationSwitchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    /**
     * Handle location update from client-side detection.
     * 
     * Returns event data that the frontend uses to show the location switch prompt.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|min:-90|max:90',
            'lng' => 'required|numeric|min:-180|max:180',
            'country' => 'required|string|max:100',
            'region' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];
        $country = $validated['country'];
        $region = $validated['region'] ?? '';

        // Check if location has actually changed (more than 0.01 degrees)
        $shouldUpdate = !$user->current_lat
            || !$user->current_lng
            || abs($user->current_lat - $lat) > 0.01
            || abs($user->current_lng - $lng) > 0.01;

        if ($shouldUpdate) {
            $user->updateQuietly([
                'current_lat' => $lat,
                'current_lng' => $lng,
                'location_updated_at' => now(),
            ]);
        }

        // Handle location assignment (bootstrap or prompt)
        app(LocationService::class)->handleUserLocation($user, $country, '', $region, $lat, $lng);
        $user->refresh();

        // Use the NORMALIZED region (e.g. "England" → "London") for downstream logic
        $normalizedRegion = $user->current_region;

        // Bootstrap: first-ever detection — adopt as active, no prompt
        if (!$user->active_country) {
            app(LocationSwitchService::class)->switchTo($user, $country, $normalizedRegion ?: null);
            return response()->json(['bootstrapped' => true, 'normalizedRegion' => $normalizedRegion]);
        }

        // Check if detected location differs from active
        $sameCountry = $user->active_country === $country;

        // Auto-adopt: same country, no active region yet, but we now have a
        // detected region (e.g. user joined as "United Kingdom" only and we've
        // just resolved them to "London"). No need to prompt — silently pin
        // them to the sub-region.
        if ($sameCountry && ! $user->active_region && $normalizedRegion) {
            app(LocationSwitchService::class)->switchTo($user, $country, $normalizedRegion);
            return response()->json(['ok' => true, 'autoAdoptedRegion' => $normalizedRegion]);
        }

        $sameRegion = !$normalizedRegion || $user->active_region === $normalizedRegion;

        if ($sameCountry && $sameRegion) {
            // Silent restore: detected location matches active
            app(LocationSwitchService::class)->silentRestoreOnReturn($user);
            return response()->json(['ok' => true, 'noChange' => true]);
        }

        // Different from active — return event data for the frontend to show prompt
        return response()->json([
            'event' => 'location-switch-prompt',
            'data' => [
                'detectedCountry' => $country,
                'detectedRegion' => $normalizedRegion,
                'activeCountry' => $user->active_country,
                'activeRegion' => $user->active_region,
                'isCountryChange' => !$sameCountry,
            ]
        ]);
    }

    /**
     * Handle location switch request.
     */
    public function switch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country' => 'required|string|max:100',
            'region' => 'nullable|string|max:100',
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        app(LocationSwitchService::class)->switchTo($user, $validated['country'], $validated['region'] ?? null);

        return response()->json(['ok' => true, 'switched' => true]);
    }
}
