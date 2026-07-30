<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class Geocoder
{
    /**
     * Geocode a composed address string via the Google Geocoding API. Returns
     * null when no key is configured, the input is blank, or Google returns no
     * usable result.
     *
     * The response's `place_id` is read alongside the coordinates: it costs
     * nothing extra on a call that already happens, and it turns location dedup
     * from a guess about distance into an exact identity check.
     */
    public function geocode(string $address): ?GeocodedAddress
    {
        $key = config('filament-google-maps.keys.server_key');

        if (blank($key) || blank(trim($address))) {
            return null;
        }

        $result = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $address,
            'region' => 'ro',
            'key' => $key,
        ])->json('results.0');

        if (! is_array($result)) {
            return null;
        }

        $coordinates = $result['geometry']['location'] ?? null;

        if (! is_array($coordinates) || ! isset($coordinates['lat'], $coordinates['lng'])) {
            return null;
        }

        $placeId = $result['place_id'] ?? null;

        return new GeocodedAddress(
            (float) $coordinates['lat'],
            (float) $coordinates['lng'],
            is_string($placeId) && filled($placeId) ? $placeId : null,
        );
    }
}
