<?php

namespace App\Http\Middleware;

use App\Services\LocationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserLocation
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $lat = $request->header('X-User-Latitude');
            $lng = $request->header('X-User-Longitude');
            $country = $request->header('X-User-Country');
            $region = $request->header('X-User-Region');

            if ($lat && $lng && is_numeric($lat) && is_numeric($lng)) {
                $shouldUpdate = ! $user->current_lat
                    || ! $user->current_lng
                    || abs($user->current_lat - (float) $lat) > 0.01
                    || abs($user->current_lng - (float) $lng) > 0.01;

                if ($shouldUpdate) {
                    $user->updateQuietly([
                        'current_lat' => (float) $lat,
                        'current_lng' => (float) $lng,
                        'location_updated_at' => now(),
                    ]);

                    // If client sent country/region, run location assignment
                    if ($country) {
                        app(LocationService::class)->handleUserLocation($user, $country, '', $region);
                    }
                }
            }
        }

        return $next($request);
    }
}
