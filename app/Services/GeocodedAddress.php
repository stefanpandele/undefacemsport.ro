<?php

namespace App\Services;

/**
 * A resolved address: where it is, plus Google's identity for the place.
 *
 * The place id is kept apart from the coordinates on purpose — the map component
 * and `Location::$location` only ever want a `{lat, lng}` pin, while dedup wants
 * the identity.
 */
readonly class GeocodedAddress
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?string $placeId = null,
    ) {}

    /**
     * The pin shape the map component expects.
     *
     * @return array{lat: float, lng: float}
     */
    public function coordinates(): array
    {
        return ['lat' => $this->latitude, 'lng' => $this->longitude];
    }
}
