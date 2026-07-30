<?php

use App\Enums\LocationCorrectionField;
use App\Filament\Club\Resources\Locations\LocationResource;
use App\Filament\Club\Resources\Locations\Pages\ManageLocations;
use App\Models\Club;
use App\Models\County;
use App\Models\Locality;
use App\Models\Location;
use App\Models\LocationCorrection;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Location::distanceInMeters
|--------------------------------------------------------------------------
*/

test('distanceInMeters measures a known pair', function () {
    // Cluj Arena to the olympic pool beside it: a few hundred metres.
    $distance = Location::distanceInMeters(46.7686, 23.5720, 46.7660, 23.5735);

    expect($distance)->toBeGreaterThan(280.0)
        ->and($distance)->toBeLessThan(330.0);
});

test('distanceInMeters is zero for the same point', function () {
    expect(Location::distanceInMeters(46.77, 23.59, 46.77, 23.59))->toBe(0.0);
});

/*
|--------------------------------------------------------------------------
| Location::exactMatch
|--------------------------------------------------------------------------
*/

test('exactMatch finds a location by place id even when the address was typed differently', function () {
    $existing = Location::factory()
        ->withPlaceId('ChIJRamada')
        ->create([
            'county' => 'București',
            'city' => 'București',
            'address' => 'Calea Dorobanților 5A',
        ]);

    $found = Location::exactMatch('ChIJRamada', 'București', 'București', 'Calea Dorobantilor nr. 5A');

    expect($found?->is($existing))->toBeTrue();
});

test('exactMatch falls back to a character-for-character address match', function () {
    $existing = Location::factory()->create([
        'county' => 'Cluj',
        'city' => 'Cluj-Napoca',
        'address' => 'Aleea Stadionului 2',
    ]);

    expect(Location::exactMatch(null, 'Cluj', 'Cluj-Napoca', 'Aleea Stadionului 2')?->is($existing))->toBeTrue();
});

test('exactMatch returns null when neither the place id nor the address matches', function () {
    Location::factory()->withPlaceId('ChIJOther')->create([
        'county' => 'Cluj',
        'city' => 'Cluj-Napoca',
        'address' => 'Aleea Stadionului 2',
    ]);

    expect(Location::exactMatch('ChIJUnknown', 'Cluj', 'Cluj-Napoca', 'Strada Necunoscută 1'))->toBeNull();
});

test('exactMatch ignores a blank place id instead of matching every null row', function () {
    $existing = Location::factory()->create([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Strada A 1',
    ]);
    Location::factory()->create([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Strada B 2',
    ]);

    expect(Location::exactMatch('', 'Cluj', 'Cluj-Napoca', 'Strada A 1')?->is($existing))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Location::nearby
|--------------------------------------------------------------------------
*/

test('nearby returns close locations first, each with its distance', function () {
    $far = Location::factory()->at(46.7800, 23.5900)->create(['name' => 'Departe']);
    $near = Location::factory()->at(46.76605, 23.57355)->create(['name' => 'Aproape']);
    $middle = Location::factory()->at(46.7670, 23.5745)->create(['name' => 'Mijloc']);

    $found = Location::nearby(46.7660, 23.5735);

    expect($found->pluck('name')->all())->toBe(['Aproape', 'Mijloc'])
        ->and($found->first()->distance_meters)->toBeLessThan(10.0)
        ->and($found->contains(fn (Location $location): bool => $location->is($far)))->toBeFalse();
});

test('nearby cuts an exact circle, not the bounding box around it', function () {
    // ~250m north-east: inside the box that a 200m radius implies, outside the
    // circle. The corners of the box are what this guards against.
    Location::factory()->at(46.76780, 23.57583)->create(['name' => 'În colț']);

    expect(Location::nearby(46.7660, 23.5735, 200))->toBeEmpty();
});

test('nearby honours a wider radius', function () {
    Location::factory()->at(46.7686, 23.5720)->create(['name' => 'Arena']);

    expect(Location::nearby(46.7660, 23.5735, 200))->toBeEmpty()
        ->and(Location::nearby(46.7660, 23.5735, 500))->toHaveCount(1);
});

test('nearby can exclude the location being edited', function () {
    $self = Location::factory()->at(46.7660, 23.5735)->create();

    expect(Location::nearby(46.7660, 23.5735))->toHaveCount(1)
        ->and(Location::nearby(46.7660, 23.5735, excludeId: $self->getKey()))->toBeEmpty();
});

test('nearby skips locations that have no coordinates', function () {
    Location::factory()->create(['latitude' => null, 'longitude' => null]);

    expect(Location::nearby(46.7660, 23.5735))->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| Club::syncLocation
|--------------------------------------------------------------------------
*/

test('two clubs typing the address differently land on one location, via the place id', function () {
    $clubA = Club::factory()->create();
    $clubB = Club::factory()->create();

    $clubA->syncLocation([
        'county' => 'București',
        'city' => 'București',
        'address' => 'Calea Dorobanților 5A',
        'name' => 'Hotel Ramada',
        'latitude' => 44.4600,
        'longitude' => 26.0980,
        'google_place_id' => 'ChIJRamada',
    ]);

    $b = $clubB->syncLocation([
        'county' => 'București',
        'city' => 'București',
        'address' => 'Calea Dorobantilor nr. 5A',
        'name' => 'Bazin Ramada',
        'latitude' => 44.4600,
        'longitude' => 26.0980,
        'google_place_id' => 'ChIJRamada',
    ]);

    expect(Location::count())->toBe(1)
        ->and($b->location->name)->toBe('Hotel Ramada'); // canonical name preserved
});

test('syncLocation backfills the place id onto a location first created without one', function () {
    $club = Club::factory()->create();
    $other = Club::factory()->create();

    $existing = Location::factory()->create([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Aleea Stadionului 2',
        'google_place_id' => null,
    ]);

    $club->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Aleea Stadionului 2',
        'name' => 'Cluj Arena', 'google_place_id' => 'ChIJArena',
    ]);

    expect($existing->refresh()->google_place_id)->toBe('ChIJArena');

    // Which is the point: the next club matches by id, whatever it types.
    $other->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Al. Stadionului nr. 2',
        'name' => 'Arena', 'google_place_id' => 'ChIJArena',
    ]);

    expect(Location::count())->toBe(1);
});

test('syncLocation attaches to a location the club explicitly chose, whatever it typed', function () {
    $club = Club::factory()->create();
    $chosen = Location::factory()->create([
        'name' => 'Sala Polivalentă',
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Aleea Stadionului 4',
    ]);

    $clubLocation = $club->syncLocation(
        [
            'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Aleea Stadionului 4 bis',
            'name' => 'Cu totul alt nume',
        ],
        [],
        null,
        $chosen->getKey(),
    );

    expect(Location::count())->toBe(1)
        ->and($clubLocation->location->is($chosen))->toBeTrue()
        ->and($clubLocation->location->name)->toBe('Sala Polivalentă');
});

test('syncLocation never attaches on proximity alone', function () {
    // Two halls 60m apart really are two halls. Deciding by radius is the panel's
    // question to ask a human, never something the model does behind their back.
    $club = Club::factory()->create();
    Location::factory()->at(46.7660, 23.5735)->create(['name' => 'Sala 1']);

    $club->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Strada Vecină 2',
        'name' => 'Sala 2', 'latitude' => 46.76655, 'longitude' => 23.57385,
    ]);

    expect(Location::count())->toBe(2);
});

test('syncLocation stores the place id on a brand new location', function () {
    $club = Club::factory()->create();

    $clubLocation = $club->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Strada Nouă 1',
        'name' => 'Sala Nouă', 'google_place_id' => 'ChIJNew',
    ]);

    expect($clubLocation->location->google_place_id)->toBe('ChIJNew');
});

/*
|--------------------------------------------------------------------------
| The save-time guard
|--------------------------------------------------------------------------
*/

test('a pin next to an existing location raises a question at save time', function () {
    $neighbour = Location::factory()->at(46.7660, 23.5735)->create(['name' => 'Bazinul Olimpic']);

    $unresolved = LocationResource::neighboursNeedingAnswer([
        'location' => ['lat' => 46.76655, 'lng' => 23.57385],
        'county' => 'Cluj',
        'city' => 'Cluj-Napoca',
        'address' => 'Strada Vecină 2',
        'google_place_id' => null,
        'chosen_location_id' => null,
        'location_choice' => null,
    ]);

    expect($unresolved->pluck('id')->all())->toBe([$neighbour->getKey()]);
});

test('the guard stays quiet once the club picked one of the neighbours', function () {
    $neighbour = Location::factory()->at(46.7660, 23.5735)->create();

    expect(LocationResource::neighboursNeedingAnswer([
        'location' => ['lat' => 46.76655, 'lng' => 23.57385],
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Strada Vecină 2',
        'google_place_id' => null,
        'chosen_location_id' => $neighbour->getKey(),
        'location_choice' => (string) $neighbour->getKey(),
    ]))->toBeEmpty();
});

test('the guard stays quiet once the club says out loud that it is a new place', function () {
    Location::factory()->at(46.7660, 23.5735)->create();

    expect(LocationResource::neighboursNeedingAnswer([
        'location' => ['lat' => 46.76655, 'lng' => 23.57385],
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Strada Vecină 2',
        'google_place_id' => null,
        'chosen_location_id' => null,
        'location_choice' => LocationResource::CHOICE_NEW,
    ]))->toBeEmpty();
});

test('the guard stays quiet when the address is already a certain match', function () {
    // Same address string: syncLocation will reuse that record, so there is
    // nothing to ask about.
    Location::factory()->at(46.7660, 23.5735)->create([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Aleea Stadionului 1',
    ]);

    expect(LocationResource::neighboursNeedingAnswer([
        'location' => ['lat' => 46.7660, 'lng' => 23.5735],
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Aleea Stadionului 1',
        'google_place_id' => null,
        'chosen_location_id' => null,
        'location_choice' => null,
    ]))->toBeEmpty();
});

test('the guard ignores the location currently being edited', function () {
    $own = Location::factory()->at(46.7660, 23.5735)->create();

    expect(LocationResource::neighboursNeedingAnswer([
        'location' => ['lat' => 46.76605, 'lng' => 23.57355],
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Adresă corectată 9',
        'google_place_id' => null,
        'chosen_location_id' => null,
        'location_choice' => null,
    ], $own->getKey()))->toBeEmpty();
});

test('the guard cannot ask anything without a pin', function () {
    Location::factory()->at(46.7660, 23.5735)->create();

    expect(LocationResource::neighboursNeedingAnswer([
        'location' => null,
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Strada Vecină 2',
        'google_place_id' => null,
        'chosen_location_id' => null,
        'location_choice' => null,
    ]))->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| LocationResource::persist
|--------------------------------------------------------------------------
*/

test('persist carries the place id through to the shared location', function () {
    $club = Club::factory()->create();

    $clubLocation = LocationResource::persist([
        'county' => 'Cluj',
        'city' => 'Cluj-Napoca',
        'address' => 'Strada Memorandumului 1',
        'name' => 'Sala M',
        'location' => ['lat' => 46.77, 'lng' => 23.59],
        'google_place_id' => 'ChIJMemorandumului',
        'sports' => [],
    ], null, null, $club);

    expect($clubLocation->location->google_place_id)->toBe('ChIJMemorandumului');
});

test('persist attaches to the location the club chose over the address it typed', function () {
    $club = Club::factory()->create();
    $chosen = Location::factory()->create(['name' => 'Bazinul Olimpic']);

    $clubLocation = LocationResource::persist([
        'county' => 'Cluj',
        'city' => 'Cluj-Napoca',
        'address' => 'Adresă tastată altfel 1',
        'name' => 'Bazin',
        'location' => ['lat' => 46.77, 'lng' => 23.59],
        'chosen_location_id' => $chosen->getKey(),
        'sports' => [],
    ], null, null, $club);

    expect(Location::count())->toBe(1)
        ->and($clubLocation->location->is($chosen))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| The club panel, end to end
|--------------------------------------------------------------------------
*/

/**
 * Put a club member inside the club panel, with the geography the form's selects
 * validate against.
 *
 * @return array{0: User, 1: Club}
 */
function clubPanelContext(): array
{
    $county = County::create(['name' => 'Cluj']);
    Locality::create(['county_id' => $county->id, 'name' => 'Cluj-Napoca']);

    $member = User::factory()->create();
    $club = Club::factory()->pro()->create();
    $club->addMember($member);

    test()->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('club'));
    Filament::setTenant($club);

    return [$member, $club];
}

test('saving without pressing search is still stopped by the nearby location', function () {
    // The hole this whole phase exists to close: the search button is optional,
    // so a club can fill the address and save straight through.
    clubPanelContext();
    Location::factory()->at(46.7660, 23.5735)->create(['name' => 'Bazinul Olimpic']);

    Livewire::test(ManageLocations::class)
        ->callAction(CreateAction::class, data: [
            'county' => 'Cluj',
            'city' => 'Cluj-Napoca',
            'address' => 'Strada Vecină 2',
            'name' => 'Sala Nouă',
            'location' => ['lat' => 46.76655, 'lng' => 23.57385],
            'sports' => [],
        ])
        ->assertHasActionErrors(['address']);

    expect(Location::count())->toBe(1);
});

test('confirming it is a new place lets the save through', function () {
    clubPanelContext();
    Location::factory()->at(46.7660, 23.5735)->create(['name' => 'Bazinul Olimpic']);

    Livewire::test(ManageLocations::class)
        ->callAction(CreateAction::class, data: [
            'county' => 'Cluj',
            'city' => 'Cluj-Napoca',
            'address' => 'Strada Vecină 2',
            'name' => 'Sala Nouă',
            'location' => ['lat' => 46.76655, 'lng' => 23.57385],
            'location_choice' => LocationResource::CHOICE_NEW,
            'sports' => [],
        ])
        ->assertHasNoActionErrors();

    expect(Location::count())->toBe(2);
});

test('choosing the neighbour attaches the club there instead of creating a second row', function () {
    [, $club] = clubPanelContext();
    $neighbour = Location::factory()->at(46.7660, 23.5735)->create(['name' => 'Bazinul Olimpic']);

    Livewire::test(ManageLocations::class)
        ->callAction(CreateAction::class, data: [
            'county' => 'Cluj',
            'city' => 'Cluj-Napoca',
            'address' => 'Strada Vecină 2',
            'name' => 'Sala Nouă',
            'location' => ['lat' => 46.76655, 'lng' => 23.57385],
            'chosen_location_id' => $neighbour->getKey(),
            'location_choice' => (string) $neighbour->getKey(),
            'sports' => [],
        ])
        ->assertHasNoActionErrors();

    expect(Location::count())->toBe(1)
        ->and($club->clubLocations()->first()->location_id)->toBe($neighbour->getKey());
});

test('a lone address far from anything saves without any question', function () {
    clubPanelContext();

    Livewire::test(ManageLocations::class)
        ->callAction(CreateAction::class, data: [
            'county' => 'Cluj',
            'city' => 'Cluj-Napoca',
            'address' => 'Strada Singuratică 1',
            'name' => 'Sala Singură',
            'location' => ['lat' => 46.7000, 'lng' => 23.5000],
            'sports' => [],
        ])
        ->assertHasNoActionErrors();

    expect(Location::count())->toBe(1);
});

test('a club can propose a correction for the shared location it cannot edit', function () {
    [, $club] = clubPanelContext();

    $clubLocation = $club->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Aleea Stadionului 1',
        'name' => 'Bazinul Olimpik',
    ]);

    Livewire::test(ManageLocations::class)
        ->callAction(TestAction::make('proposeCorrection')->table($clubLocation), data: [
            'field' => LocationCorrectionField::Name->value,
            'suggested_value' => 'Bazinul Olimpic',
            'note' => 'Așa scrie pe clădire.',
        ]);

    $correction = LocationCorrection::query()->first();

    expect($correction)->not->toBeNull()
        ->and($correction->location_id)->toBe($clubLocation->location_id)
        ->and($correction->club_id)->toBe($club->getKey())
        ->and($correction->field)->toBe(LocationCorrectionField::Name)
        ->and($correction->suggested_value)->toBe('Bazinul Olimpic')
        ->and($correction->isPending())->toBeTrue()
        // The club proposes; it does not get to change the shared record itself.
        ->and($clubLocation->location->refresh()->name)->toBe('Bazinul Olimpik');
});
