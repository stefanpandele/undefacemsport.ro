<?php

use App\Enums\ContactRole;
use App\Enums\ContactType;
use App\Models\Club;
use App\Models\Image;
use App\Models\Sport;

test('a club offers sports with per-sport flags on the pivot', function () {
    $club = Club::factory()->create();
    $sport = Sport::factory()->create();

    $club->sports()->attach($sport, ['offers_private_sessions' => true]);

    $attached = $club->sports()->first();

    expect($attached->is($sport))->toBeTrue()
        ->and((bool) $attached->pivot->offers_private_sessions)->toBeTrue();
});

test('a club has polymorphic gallery images', function () {
    $club = Club::factory()->create();

    $club->images()->create(Image::factory()->raw());

    expect($club->images)->toHaveCount(1)
        ->and($club->images->first()->imageable->is($club))->toBeTrue();
});

test('a club has contacts tagged by type and role', function () {
    $club = Club::factory()->create();

    $club->contacts()->create([
        'type' => ContactType::Phone,
        'role' => ContactRole::Accounting,
        'value' => '0722000111',
        'name' => 'Birou contabilitate',
    ]);

    $contact = $club->contacts()->first();

    expect($contact->type)->toBe(ContactType::Phone)
        ->and($contact->role)->toBe(ContactRole::Accounting)
        ->and($contact->contactable->is($club))->toBeTrue();
});

test('logo and cover accessors build a url from the stored path', function () {
    config()->set('filesystems.disks.s3', [
        'driver' => 'local',
        'root' => storage_path('framework/testing/disks/s3'),
        'url' => 'http://localhost/s3',
    ]);

    $club = Club::factory()->create([
        'logo_path' => 'clubs/logos/logo.png',
        'cover_path' => null,
    ]);

    expect($club->logo_url)->toContain('clubs/logos/logo.png')
        ->and($club->cover_url)->toBeNull();
});
