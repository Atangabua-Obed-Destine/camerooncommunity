<?php

namespace App\Livewire;

use App\Models\PlatformSetting;
use App\Services\LocationService;
use Livewire\Component;

class LocationTracker extends Component
{
    /**
     * Called from Alpine.js when the client detects a new location.
     * Updates the user's coordinates and country/region, then notifies
     * other Livewire components (e.g. RoomList) so suggested rooms refresh.
     */
    public function updateLocation(float $lat, float $lng, string $country, string $region): void
    {
        // Basic bounds validation
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return;
        }

        $country = trim(strip_tags($country));
        $region = trim(strip_tags($region));

        if (! $country || mb_strlen($country) > 100 || mb_strlen($region) > 100) {
            return;
        }

        $user = auth()->user();

        $shouldUpdate = ! $user->current_lat
            || ! $user->current_lng
            || abs($user->current_lat - $lat) > 0.01
            || abs($user->current_lng - $lng) > 0.01;

        if ($shouldUpdate) {
            $user->updateQuietly([
                'current_lat' => $lat,
                'current_lng' => $lng,
                'location_updated_at' => now(),
            ]);
        }

        app(LocationService::class)->handleUserLocation($user, $country, '', $region);

        // Tell RoomList (and any other listener) to refresh
        $this->dispatch('location-changed');
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.location-tracker', [
            'knownCountry' => $user->current_country ?? '',
            'knownRegion'  => $user->current_region ?? '',
            'locationMode' => PlatformSetting::getValue('location_detection_mode', 'gps'),
        ]);
    }
}
