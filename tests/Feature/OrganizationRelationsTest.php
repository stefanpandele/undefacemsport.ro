<?php

use App\Enums\ContactRole;
use App\Enums\ContactType;
use App\Models\Image;
use App\Models\Organization;
use App\Models\Sport;

test('a club offers sports in the order it presents them', function () {
    $organization = Organization::factory()->create();
    $sport = Sport::factory()->create();

    $organization->sports()->attach($sport, ['sort_order' => 2]);

    $attached = $organization->sports()->first();

    expect($attached->is($sport))->toBeTrue()
        ->and((int) $attached->pivot->sort_order)->toBe(2);
});

test('a club has polymorphic gallery images', function () {
    $organization = Organization::factory()->create();

    $organization->images()->create(Image::factory()->raw());

    expect($organization->images)->toHaveCount(1)
        ->and($organization->images->first()->imageable->is($organization))->toBeTrue();
});

test('a club has contacts tagged by type and role', function () {
    $organization = Organization::factory()->create();

    $organization->contacts()->create([
        'type' => ContactType::Phone,
        'role' => ContactRole::Accounting,
        'value' => '0722000111',
        'name' => 'Birou contabilitate',
    ]);

    $contact = $organization->contacts()->first();

    expect($contact->type)->toBe(ContactType::Phone)
        ->and($contact->role)->toBe(ContactRole::Accounting)
        ->and($contact->contactable->is($organization))->toBeTrue();
});

test('logo and cover accessors build a url from the stored path', function () {
    config()->set('filesystems.disks.s3', [
        'driver' => 'local',
        'root' => storage_path('framework/testing/disks/s3'),
        'url' => 'http://localhost/s3',
    ]);

    $organization = Organization::factory()->create([
        'logo_path' => 'clubs/logos/logo.png',
        'cover_path' => null,
    ]);

    expect($organization->logo_url)->toContain('clubs/logos/logo.png')
        ->and($organization->cover_url)->toBeNull();
});
