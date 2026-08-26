<?php

use App\Filament\Organization\Resources\Locations\LocationResource;
use App\Models\Facility;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Sport;
use App\Models\User;

test('a club location belongs to a club and a shared location and offers sports', function () {
    $organization = Organization::factory()->create();
    $sports = Sport::factory()->count(2)->create();

    $organizationLocation = $organization->syncLocation([
        'county' => 'Cluj',
        'city' => 'Cluj-Napoca',
        'address' => 'Strada Memorandumului 1',
        'name' => 'Bazinul Central',
    ], $sports->pluck('id')->all());

    expect($organizationLocation->organization->is($organization))->toBeTrue()
        ->and($organizationLocation->location->name)->toBe('Bazinul Central')
        ->and($organizationLocation->sports)->toHaveCount(2);
});

test('two clubs share one physical location with its canonical name', function () {
    $clubA = Organization::factory()->create();
    $clubB = Organization::factory()->create();

    $address = ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Strada Y 1'];

    $a = $clubA->syncLocation($address + ['name' => 'Bazinul Olimpic']);
    $b = $clubB->syncLocation($address + ['name' => 'Alt nume, ignorat']);

    expect(Location::count())->toBe(1)
        ->and($a->location->is($b->location))->toBeTrue()
        ->and($b->location->name)->toBe('Bazinul Olimpic'); // canonical name preserved
});

test('syncLocation on an existing presence updates sports without duplicating', function () {
    $organization = Organization::factory()->create();
    $sports = Sport::factory()->count(3)->create();

    $address = ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Strada Z 3', 'name' => 'Sala 1'];

    $first = $organization->syncLocation($address, [$sports[0]->id]);
    $again = $organization->syncLocation($address, [$sports[1]->id, $sports[2]->id], $first);

    expect($again->is($first))->toBeTrue()
        ->and($organization->organizationLocations()->count())->toBe(1)
        ->and($again->sports->pluck('id')->all())->toEqualCanonicalizing([$sports[1]->id, $sports[2]->id]);
});

test('a free club can add one location then hits the plan limit', function () {
    $organization = Organization::factory()->create(); // free: locations limit 1

    expect($organization->canAddLocation())->toBeTrue();

    $organization->syncLocation(['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. 1', 'name' => 'L1']);

    expect($organization->canAddLocation())->toBeFalse();
});

test('a pro club can add locations up to its higher limit', function () {
    $organization = Organization::factory()->pro()->create(); // locations limit 3

    $organization->syncLocation(['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. 1', 'name' => 'L1']);
    $organization->syncLocation(['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. 2', 'name' => 'L2']);

    expect($organization->canAddLocation())->toBeTrue(); // 2 < 3

    $organization->syncLocation(['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. 3', 'name' => 'L3']);

    expect($organization->canAddLocation())->toBeFalse(); // 3 == 3
});

test('a club member can open the locations page without error', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member);

    $this->actingAs($member)
        ->get(LocationResource::getUrl(panel: 'organization', tenant: $organization))
        ->assertSuccessful();
});

test('organizationLocationAt finds the club presence at an address, scoped and excludable', function () {
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();

    $organizationLocation = $organization->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Strada Memorandumului 1', 'name' => 'Bazin',
    ]);

    expect($organization->organizationLocationAt('Cluj', 'Cluj-Napoca', 'Strada Memorandumului 1')?->is($organizationLocation))->toBeTrue()
        ->and($organization->organizationLocationAt('Cluj', 'Cluj-Napoca', 'Altă stradă 9'))->toBeNull()
        ->and($organization->organizationLocationAt('Cluj', 'Cluj-Napoca', 'Strada Memorandumului 1', $organizationLocation->id))->toBeNull()
        ->and($other->organizationLocationAt('Cluj', 'Cluj-Napoca', 'Strada Memorandumului 1'))->toBeNull()
        ->and($organization->organizationLocationAt(null, 'Cluj-Napoca', 'Strada Memorandumului 1'))->toBeNull();
});

test('Location::atAddress returns the shared location regardless of club', function () {
    $organization = Organization::factory()->create();
    $organization->syncLocation(['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Strada W 5', 'name' => 'Sala W']);

    expect(Location::atAddress('Cluj', 'Cluj-Napoca', 'Strada W 5')?->name)->toBe('Sala W')
        ->and(Location::atAddress('Cluj', 'Cluj-Napoca', 'Necunoscută'))->toBeNull();
});

test('location facilities are shared and only ever added', function () {
    $location = Location::factory()->create();
    $facilities = Facility::factory()->count(3)->create();

    $location->facilities()->attach([$facilities[0]->id, $facilities[1]->id]);

    // Another club adds a facility without touching the existing ones.
    $location->facilities()->syncWithoutDetaching([$facilities[2]->id]);
    expect($location->facilities()->count())->toBe(3);

    // Re-saving a subset never removes the others.
    $location->facilities()->syncWithoutDetaching([$facilities[0]->id]);
    expect($location->facilities()->count())->toBe(3);
});

test('saving a location adds the chosen facilities to the shared location', function () {
    $organization = Organization::factory()->create();
    $address = ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. F 1', 'name' => 'Sala F'];
    $organizationLocation = $organization->syncLocation($address);
    $facility = Facility::factory()->create();

    LocationResource::persist($address + [
        'location' => ['lat' => 46.77, 'lng' => 23.59],
        'sports' => [],
        'new_facilities' => [$facility->id],
    ], $organizationLocation);

    expect($organizationLocation->location->facilities()->count())->toBe(1)
        ->and($organizationLocation->location->facilities->first()->is($facility))->toBeTrue();
});

test('the location form saves and reloads the contacts of that address', function () {
    $organization = Organization::factory()->create();

    $presence = LocationResource::persist([
        'county' => 'Cluj',
        'city' => 'Cluj-Napoca',
        'address' => 'Str. A 1',
        'name' => 'Bazinul A',
        'sports' => [],
        'contacts' => [
            ['type' => 'phone', 'role' => 'person', 'name' => 'Andrei Popescu', 'value' => '0722111111'],
            // Blank rows are the repeater's, not the club's: dropped rather
            // than stored as a contact nobody can reach.
            ['type' => 'phone', 'role' => 'general', 'name' => null, 'value' => ''],
        ],
    ], organization: $organization);

    expect($presence->contacts)->toHaveCount(1)
        ->and($presence->contacts->first()->value)->toBe('0722111111')
        ->and($presence->contacts->first()->name)->toBe('Andrei Popescu');

    $filled = LocationResource::fillFromRecord([], $presence->refresh());

    expect($filled['contacts'])->toBe([
        ['type' => 'phone', 'role' => 'person', 'name' => 'Andrei Popescu', 'value' => '0722111111'],
    ]);
});

test('removing a contact row stops publishing that number', function () {
    $organization = Organization::factory()->create();

    $presence = LocationResource::persist([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Bazinul A',
        'sports' => [],
        'contacts' => [['type' => 'phone', 'role' => 'person', 'name' => 'Andrei', 'value' => '0722111111']],
    ], organization: $organization);

    LocationResource::persist([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Bazinul A',
        'sports' => [],
        'contacts' => [],
    ], $presence, organization: $organization);

    expect($presence->refresh()->contacts)->toBeEmpty();
});
