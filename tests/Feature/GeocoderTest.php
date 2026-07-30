<?php

use App\Services\Geocoder;
use Illuminate\Support\Facades\Http;

test('geocode returns coordinates and the place id from a Google response', function () {
    config()->set('filament-google-maps.keys.server_key', 'test-key');
    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'results' => [[
                'place_id' => 'ChIJTest123',
                'geometry' => ['location' => ['lat' => 46.77, 'lng' => 23.59]],
            ]],
        ]),
    ]);

    $geocoded = app(Geocoder::class)->geocode('Strada Memorandumului 1, Cluj-Napoca, Cluj, România');

    expect($geocoded->latitude)->toBe(46.77)
        ->and($geocoded->longitude)->toBe(23.59)
        ->and($geocoded->placeId)->toBe('ChIJTest123')
        ->and($geocoded->coordinates())->toBe(['lat' => 46.77, 'lng' => 23.59]);
});

test('geocode still resolves coordinates when Google returns no place id', function () {
    config()->set('filament-google-maps.keys.server_key', 'test-key');
    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'results' => [['geometry' => ['location' => ['lat' => 44.43, 'lng' => 26.10]]]],
        ]),
    ]);

    $geocoded = app(Geocoder::class)->geocode('Bulevardul Basarabia 37, București, România');

    expect($geocoded->latitude)->toBe(44.43)
        ->and($geocoded->placeId)->toBeNull();
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

test('geocode returns null when a result carries no usable coordinates', function () {
    config()->set('filament-google-maps.keys.server_key', 'k');
    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'results' => [['place_id' => 'ChIJNoGeometry', 'geometry' => []]],
        ]),
    ]);

    expect(app(Geocoder::class)->geocode('somewhere vague'))->toBeNull();
});
