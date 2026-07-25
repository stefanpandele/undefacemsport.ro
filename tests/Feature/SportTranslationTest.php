<?php

use App\Models\Sport;

test('a sport name is translated per locale', function () {
    $sport = Sport::create(['name' => 'Fotbal', 'slug' => 'fotbal']);

    app()->setLocale('en');
    expect($sport->translated_name)->toBe('Football');

    app()->setLocale('ro');
    expect($sport->translated_name)->toBe('Fotbal');
});

test('a sport without a translation falls back to its stored name', function () {
    $sport = Sport::create(['name' => 'Sport Nou', 'slug' => 'sport-nou']);

    app()->setLocale('en');

    expect($sport->translated_name)->toBe('Sport Nou');
});
