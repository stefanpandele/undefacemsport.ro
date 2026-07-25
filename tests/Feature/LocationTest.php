<?php

use App\Filament\Club\Resources\Locations\LocationResource;
use App\Models\Club;
use App\Models\Location;
use App\Models\Sport;
use App\Models\User;

test('a club location belongs to a club and a shared location and offers sports', function () {
    $club = Club::factory()->create();
    $sports = Sport::factory()->count(2)->create();

    $clubLocation = $club->syncLocation([
        'county' => 'Cluj',
        'city' => 'Cluj-Napoca',
        'address' => 'Strada Memorandumului 1',
        'name' => 'Bazinul Central',
    ], $sports->pluck('id')->all());

    expect($clubLocation->club->is($club))->toBeTrue()
        ->and($clubLocation->location->name)->toBe('Bazinul Central')
        ->and($clubLocation->sports)->toHaveCount(2);
});

test('two clubs share one physical location with its canonical name', function () {
    $clubA = Club::factory()->create();
    $clubB = Club::factory()->create();

    $address = ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Strada Y 1'];

    $a = $clubA->syncLocation($address + ['name' => 'Bazinul Olimpic']);
    $b = $clubB->syncLocation($address + ['name' => 'Alt nume, ignorat']);

    expect(Location::count())->toBe(1)
        ->and($a->location->is($b->location))->toBeTrue()
        ->and($b->location->name)->toBe('Bazinul Olimpic'); // canonical name preserved
});

test('syncLocation on an existing presence updates sports without duplicating', function () {
    $club = Club::factory()->create();
    $sports = Sport::factory()->count(3)->create();

    $address = ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Strada Z 3', 'name' => 'Sala 1'];

    $first = $club->syncLocation($address, [$sports[0]->id]);
    $again = $club->syncLocation($address, [$sports[1]->id, $sports[2]->id], $first);

    expect($again->is($first))->toBeTrue()
        ->and($club->clubLocations()->count())->toBe(1)
        ->and($again->sports->pluck('id')->all())->toEqualCanonicalizing([$sports[1]->id, $sports[2]->id]);
});

test('a free club can add one location then hits the plan limit', function () {
    $club = Club::factory()->create(); // free: locations limit 1

    expect($club->canAddLocation())->toBeTrue();

    $club->syncLocation(['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. 1', 'name' => 'L1']);

    expect($club->canAddLocation())->toBeFalse();
});

test('a pro club can add locations up to its higher limit', function () {
    $club = Club::factory()->pro()->create(); // locations limit 3

    $club->syncLocation(['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. 1', 'name' => 'L1']);
    $club->syncLocation(['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. 2', 'name' => 'L2']);

    expect($club->canAddLocation())->toBeTrue(); // 2 < 3

    $club->syncLocation(['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. 3', 'name' => 'L3']);

    expect($club->canAddLocation())->toBeFalse(); // 3 == 3
});

test('a club member can open the locations page without error', function () {
    $member = User::factory()->create();
    $club = Club::factory()->create();
    $club->addMember($member);

    $this->actingAs($member)
        ->get(LocationResource::getUrl(panel: 'club', tenant: $club))
        ->assertSuccessful();
});

test('clubLocationAt finds the club presence at an address, scoped and excludable', function () {
    $club = Club::factory()->create();
    $other = Club::factory()->create();

    $clubLocation = $club->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Strada Memorandumului 1', 'name' => 'Bazin',
    ]);

    expect($club->clubLocationAt('Cluj', 'Cluj-Napoca', 'Strada Memorandumului 1')?->is($clubLocation))->toBeTrue()
        ->and($club->clubLocationAt('Cluj', 'Cluj-Napoca', 'Altă stradă 9'))->toBeNull()
        ->and($club->clubLocationAt('Cluj', 'Cluj-Napoca', 'Strada Memorandumului 1', $clubLocation->id))->toBeNull()
        ->and($other->clubLocationAt('Cluj', 'Cluj-Napoca', 'Strada Memorandumului 1'))->toBeNull()
        ->and($club->clubLocationAt(null, 'Cluj-Napoca', 'Strada Memorandumului 1'))->toBeNull();
});

test('Location::atAddress returns the shared location regardless of club', function () {
    $club = Club::factory()->create();
    $club->syncLocation(['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Strada W 5', 'name' => 'Sala W']);

    expect(Location::atAddress('Cluj', 'Cluj-Napoca', 'Strada W 5')?->name)->toBe('Sala W')
        ->and(Location::atAddress('Cluj', 'Cluj-Napoca', 'Necunoscută'))->toBeNull();
});
