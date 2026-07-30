<?php

use App\Enums\LocationCorrectionField;
use App\Enums\LocationCorrectionStatus;
use App\Filament\Admin\Resources\LocationCorrections\LocationCorrectionResource;
use App\Models\Club;
use App\Models\Location;
use App\Models\LocationCorrection;
use App\Models\User;

test('a correction starts pending and unreviewed', function () {
    $correction = LocationCorrection::factory()->create();

    expect($correction->status)->toBe(LocationCorrectionStatus::Pending)
        ->and($correction->isPending())->toBeTrue()
        ->and($correction->reviewed_at)->toBeNull()
        ->and($correction->reviewed_by)->toBeNull();
});

test('a club cannot set the status or the reviewer by mass assignment', function () {
    $location = Location::factory()->create();
    $club = Club::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);

    $correction = LocationCorrection::create([
        'location_id' => $location->getKey(),
        'club_id' => $club->getKey(),
        'field' => LocationCorrectionField::Name,
        'suggested_value' => 'Numele corect',
        'status' => LocationCorrectionStatus::Approved,
        'reviewed_by' => $admin->getKey(),
    ]);

    expect($correction->status)->toBe(LocationCorrectionStatus::Pending)
        ->and($correction->reviewed_by)->toBeNull();
});

test('applying a correction writes the value onto the shared location', function () {
    $location = Location::factory()->create(['address' => 'Aleea Stadionului 2']);
    $admin = User::factory()->create(['is_admin' => true]);

    $correction = LocationCorrection::factory()
        ->forField(LocationCorrectionField::Address, 'Aleea Stadionului 2-4')
        ->create(['location_id' => $location->getKey()]);

    $correction->apply($admin);

    expect($location->refresh()->address)->toBe('Aleea Stadionului 2-4')
        ->and($correction->status)->toBe(LocationCorrectionStatus::Approved)
        ->and($correction->reviewed_by)->toBe($admin->getKey())
        ->and($correction->reviewed_at)->not->toBeNull();
});

test('applying a name correction renews the slug, so the public URL stops advertising the old name', function () {
    $location = Location::factory()->create([
        'name' => 'Bazinul Vechi',
        'city' => 'Cluj-Napoca',
    ]);
    $originalSlug = $location->slug;

    LocationCorrection::factory()
        ->forField(LocationCorrectionField::Name, 'Bazinul Olimpic')
        ->create(['location_id' => $location->getKey()])
        ->apply(User::factory()->create(['is_admin' => true]));

    expect($location->refresh()->name)->toBe('Bazinul Olimpic')
        ->and($location->slug)->not->toBe($originalSlug)
        ->and($location->slug)->toStartWith('bazinul-olimpic');
});

test('applying a correction for a field other than the name leaves the slug alone', function () {
    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    $originalSlug = $location->slug;

    LocationCorrection::factory()
        ->forField(LocationCorrectionField::City, 'Florești')
        ->create(['location_id' => $location->getKey()])
        ->apply(User::factory()->create(['is_admin' => true]));

    expect($location->refresh()->city)->toBe('Florești')
        ->and($location->slug)->toBe($originalSlug);
});

test('rejecting a correction records the review without touching the location', function () {
    $location = Location::factory()->create(['name' => 'Numele Original']);
    $admin = User::factory()->create(['is_admin' => true]);

    $correction = LocationCorrection::factory()
        ->forField(LocationCorrectionField::Name, 'Ceva inventat')
        ->create(['location_id' => $location->getKey()]);

    $correction->reject($admin);

    expect($location->refresh()->name)->toBe('Numele Original')
        ->and($correction->status)->toBe(LocationCorrectionStatus::Rejected)
        ->and($correction->reviewed_by)->toBe($admin->getKey())
        ->and($correction->isPending())->toBeFalse();
});

test('a correction belongs to its location, club and reviewer', function () {
    $location = Location::factory()->create();
    $club = Club::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);

    $correction = LocationCorrection::factory()->create([
        'location_id' => $location->getKey(),
        'club_id' => $club->getKey(),
    ]);

    $correction->reject($admin);

    expect($correction->location->is($location))->toBeTrue()
        ->and($correction->club->is($club))->toBeTrue()
        ->and($correction->reviewer->is($admin))->toBeTrue();
});

test('a correction survives its club being deleted, because the report still stands', function () {
    $club = Club::factory()->create();
    $correction = LocationCorrection::factory()->create(['club_id' => $club->getKey()]);

    $club->delete();

    expect($correction->refresh()->club_id)->toBeNull()
        ->and(LocationCorrection::count())->toBe(1);
});

test('corrections go away with the location they are about', function () {
    $location = Location::factory()->create();
    LocationCorrection::factory()->count(3)->create(['location_id' => $location->getKey()]);

    $location->delete();

    expect(LocationCorrection::count())->toBe(0);
});

test('a location exposes the corrections filed against it', function () {
    $location = Location::factory()->create();
    LocationCorrection::factory()->count(2)->create(['location_id' => $location->getKey()]);
    LocationCorrection::factory()->create();

    expect($location->corrections)->toHaveCount(2);
});

test('an admin can open the corrections review page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    LocationCorrection::factory()->count(5)->create();

    $this->actingAs($admin)
        ->get(LocationCorrectionResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful();
});

test('a club member cannot reach the corrections review page', function () {
    $member = User::factory()->create();
    Club::factory()->create()->addMember($member);

    $this->actingAs($member)
        ->get(LocationCorrectionResource::getUrl('index', panel: 'admin'))
        ->assertRedirect();
});
