<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LocationService;
use App\Services\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Where a position is, for register step 1.
 *
 * The web wizard geocodes in the browser and then normalises on the server,
 * which let the two disagree (central London showed "England" and saved
 * "London"). The app sends coordinates and shows what comes back, which is
 * the region registration will save.
 */
class LocationController extends Controller
{
    public function resolve(Request $request, LocationService $locations): JsonResponse
    {
        $data = $request->validate([
            'lat' => 'nullable|numeric|between:-90,90|required_with:lng',
            'lng' => 'nullable|numeric|between:-180,180|required_with:lat',
        ]);

        $lat = isset($data['lat']) ? (float) $data['lat'] : null;
        $lng = isset($data['lng']) ? (float) $data['lng'] : null;
        $source = 'gps';

        $place = ($lat !== null && $lng !== null) ? $locations->placeFor($lat, $lng) : null;

        // No position, or the geocoder couldn't place it: the phone's own IP,
        // as the web falls back to. (From a phone the request IP is the
        // member's, unlike the web's server-side proxy.)
        if (! $place) {
            $ip = $locations->placeForIp($request->ip());
            if (empty($ip['country_name'])) {
                return response()->json(['message' => __('We could not work out where you are.')], 422);
            }
            $source = 'ip';
            $lat = isset($ip['latitude']) ? (float) $ip['latitude'] : null;
            $lng = isset($ip['longitude']) ? (float) $ip['longitude'] : null;
            $place = ['country' => $ip['country_name'], 'region' => (string) ($ip['region'] ?? ''), 'city' => (string) ($ip['city'] ?? '')];
        }

        $region = $locations->signupRegion($place['country'], $place['region'], $place['city'], $lat, $lng);

        return response()->json([
            'country' => $place['country'],
            'region' => $region,
            'city' => $place['city'],
            'lat' => $lat,
            'lng' => $lng,
            'source' => $source,
            'country_users' => Registration::countryMemberCount($place['country']),
        ]);
    }

    /** Members already in a country, when the member picks it by hand. */
    public function countryMembers(Request $request): JsonResponse
    {
        $country = (string) $request->validate(['country' => 'required|string|max:100'])['country'];

        return response()->json(['country_users' => Registration::countryMemberCount($country)]);
    }
}
