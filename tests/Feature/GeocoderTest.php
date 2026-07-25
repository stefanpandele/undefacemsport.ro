<?php

use App\Services\Geocoder;
use Illuminate\Support\Facades\Http;

test('geocode returns lat/lng from a Google response', function () {
    config()->set('filament-google-maps.keys.server_key', 'test-key');
    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'results' => [['geometry' => ['location' => ['lat' => 46.77, 'lng' => 23.59]]]],
        ]),
    ]);

    expect(app(Geocoder::class)->geocode('Strada Memorandumului 1, Cluj-Napoca, Cluj, România'))
        ->toBe(['lat' => 46.77, 'lng' => 23.59]);
});

test('geocode makes no request and returns null without a key', function () {
    config()->set('filament-google-maps.keys.server_key', null);
    Http::fake();

    expect(app(Geocoder::class)->geocode('anything'))->toBeNull();

    Http::assertNothingSent();
});

test('geocode returns null when Google has no results', function () {
    config()->set('filament-google-maps.keys.server_key', 'k');
    Http::fake(['maps.googleapis.com/*' => Http::response(['results' => []])]);

    expect(app(Geocoder::class)->geocode('nowhere at all'))->toBeNull();
});
