<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class Geocoder
{
    /**
     * Geocode a composed address string into {lat, lng} via the Google Geocoding
     * API. Returns null when no key is configured, the input is blank, or Google
     * returns no usable result.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $address): ?array
    {
        $key = config('filament-google-maps.keys.server_key');

        if (blank($key) || blank(trim($address))) {
            return null;
        }

        $location = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $address,
            'region' => 'ro',
            'key' => $key,
        ])->json('results.0.geometry.location');

        if (! is_array($location) || ! isset($location['lat'], $location['lng'])) {
            return null;
        }

        return [
            'lat' => (float) $location['lat'],
            'lng' => (float) $location['lng'],
        ];
    }
}
