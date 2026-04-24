<?php

namespace App\Livewire;

use App\Models\PlatformSetting;
use App\Services\LocationService;
use App\Services\LocationSwitchService;
use Livewire\Component;

class LocationTracker extends Component
{
    /**
     * Called from Alpine.js when the client detects a new location.
     *
     * Behavior:
     *   - Always saves the latest detected coordinates / country / region
     *     into `current_*` (so we know where the user actually is right now).
     *   - First-ever detection: bootstrap `active_*` to current, no prompt.
     *   - Detected location matches `active_*`: silently restore any
     *     auto-archived rooms (the user came back home).
     *   - Detected differs from `active_*`: dispatch `location-switch-prompt`
     *     so the toast can ask the user whether to switch their active
     *     location.
     */
    public function updateLocation(float $lat, float $lng, string $country, string $region, string $city = ''): void
    {
        // Basic bounds validation
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return;
        }

        $country = trim(strip_tags($country));
        $region = trim(strip_tags($region));
        $city = trim(strip_tags($city));

        if (! $country || mb_strlen($country) > 100 || mb_strlen($region) > 100 || mb_strlen($city) > 100) {
            return;
        }

        // Normalise UK regions: ip-api / Nominatim return the constituent
        // country (England, Scotland, Wales, Northern Ireland) but our
        // diaspora communities are organised by city. Prefer the city
        // when we have it.
        if (in_array($country, ['United Kingdom', 'UK'], true) && $city !== '') {
            $region = $city;
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

        app(LocationService::class)->handleUserLocation($user, $country, $city, $region);

        $user = $user->fresh();

        // Bootstrap: first-ever detection — adopt as active, no prompt.
        if (! $user->active_country) {
            app(LocationSwitchService::class)->switchTo($user, $country, $region ?: null);
            $this->dispatch('location-changed');

            return;
        }

        // Silent return: detected location matches active → restore any rooms
        // that were auto-archived for this location.
        $sameCountry = $user->active_country === $country;
        $sameRegion = ! $user->active_region || ! $region || $user->active_region === $region;

        if ($sameCountry && $sameRegion) {
            $restored = app(LocationSwitchService::class)->silentRestoreOnReturn($user);
            if ($restored > 0) {
                $this->dispatch('location-changed');
            }

            return;
        }

        // Different from active — ask the user what to do.
        $this->dispatch(
            'location-switch-prompt',
            detectedCountry: $country,
            detectedRegion: $region,
            activeCountry: $user->active_country,
            activeRegion: $user->active_region,
            isCountryChange: ! $sameCountry,
        );
    }

    /**
     * The user confirmed the switch from the toast.
     */
    public function confirmSwitch(string $country, string $region = ''): void
    {
        $user = auth()->user();
        $country = trim(strip_tags($country));
        $region = trim(strip_tags($region));

        if (! $country || mb_strlen($country) > 100 || mb_strlen($region) > 100) {
            return;
        }

        $result = app(LocationSwitchService::class)->switchTo($user, $country, $region ?: null);

        $this->dispatch('location-changed');
        $this->dispatch('refreshRoomList');
        $this->dispatch('location-switch-completed',
            archived: $result['archived'],
            joinedRoomId: $result['joined_room_id'],
        );
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.location-tracker', [
            'knownCountry'  => $user->current_country ?? '',
            'knownRegion'   => $user->current_region ?? '',
            'activeCountry' => $user->active_country ?? '',
            'activeRegion'  => $user->active_region ?? '',
            'locationMode'  => PlatformSetting::getValue('location_detection_mode', 'gps'),
        ]);
    }
}

