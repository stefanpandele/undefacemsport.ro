<?php

use App\Enums\FacilityStatus;
use App\Enums\OrganizationType;
use App\Enums\PersonProfession;
use App\Filament\Organization\Resources\Locations\LocationResource;
use App\Filament\Organization\Resources\OrganizationSports\OrganizationSportResource;
use App\Filament\Organization\Resources\People\PersonResource;
use App\Filament\Organization\Resources\ScheduleSlots\ScheduleSlotResource;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationSport;
use App\Models\Person;
use App\Models\Service;
use App\Models\Space;
use App\Models\Sport;
use App\Models\User;
use Filament\Facades\Filament;

/**
 * @return array{county: string, city: string, address: string, name: string}
 */
function clujAddress(): array
{
    return ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. Test 1', 'name' => 'Baza Test'];
}

/*
|--------------------------------------------------------------------------
| What an organization is, read from what it publishes
|--------------------------------------------------------------------------
*/

test('an organization that has published nothing is nothing yet', function () {
    expect(Organization::factory()->create()->offeredTypes())->toBe([]);
});

test('a training programme makes it a club', function () {
    $organization = Organization::factory()->create();
    OrganizationSport::factory()->for($organization)->create();

    expect($organization->offers(OrganizationType::Club))->toBeTrue()
        ->and($organization->offeredTypes())->toBe([OrganizationType::Club]);
});

test('an approved space makes it a venue', function () {
    $organization = Organization::factory()->create();
    $presence = $organization->syncLocation(clujAddress());
    Space::factory()->for($presence, 'organizationLocation')->create([
        'location_id' => $presence->location_id,
        'status' => FacilityStatus::Approved,
    ]);

    expect($organization->offers(OrganizationType::Venue))->toBeTrue();
});

test('a space still waiting for moderation makes it nothing', function () {
    $organization = Organization::factory()->create();
    $presence = $organization->syncLocation(clujAddress());
    Space::factory()->for($presence, 'organizationLocation')->create([
        'location_id' => $presence->location_id,
        'status' => FacilityStatus::Pending,
    ]);

    expect($organization->offers(OrganizationType::Venue))->toBeFalse();
});

test('a service makes it a practice', function () {
    $organization = Organization::factory()->create();
    Service::factory()->for($organization)->create();

    expect($organization->offers(OrganizationType::Practice))->toBeTrue();
});

test('the pool with a swimming club is both, and declared neither', function () {
    $organization = Organization::factory()->create();
    OrganizationSport::factory()->for($organization)->create();
    $presence = $organization->syncLocation(clujAddress());
    Space::factory()->for($presence, 'organizationLocation')->create([
        'location_id' => $presence->location_id,
        'status' => FacilityStatus::Approved,
    ]);

    expect($organization->offeredTypes())
        ->toBe([OrganizationType::Club, OrganizationType::Venue]);
});

test('deleting the last space takes the venue facet away with it', function () {
    // The whole reason this is computed and not stored: a column would still say
    // "venue" here, and the agrement listing would promise a pool nobody can
    // enter.
    $organization = Organization::factory()->create();
    $presence = $organization->syncLocation(clujAddress());
    $space = Space::factory()->for($presence, 'organizationLocation')->create([
        'location_id' => $presence->location_id,
        'status' => FacilityStatus::Approved,
    ]);

    expect($organization->offers(OrganizationType::Venue))->toBeTrue();

    $space->delete();

    expect($organization->offers(OrganizationType::Venue))->toBeFalse();
});

test('the offering scope answers the same question in SQL', function (OrganizationType $type) {
    $organization = Organization::factory()->create();
    OrganizationSport::factory()->for($organization)->create();

    expect(Organization::query()->offering($type)->exists())
        ->toBe($organization->offers($type));
})->with(OrganizationType::cases());

test('every type has a Romanian label, singular and plural', function () {
    foreach (OrganizationType::cases() as $type) {
        expect($type->label())->not->toBe('')
            ->and($type->pluralLabel())->not->toBe('');
    }

    expect(OrganizationType::options())->toHaveKeys(['club', 'venue', 'practice']);
});

/*
|--------------------------------------------------------------------------
| The pivot table Eloquent would have guessed wrong
|--------------------------------------------------------------------------
*/

test('an organization and a location meet through organization_location, not the alphabetical guess', function () {
    // Eloquent would derive `location_organization` from the model names. The
    // table is `organization_location`, so both sides name it explicitly — and a
    // missing argument would only show up as a query against a table that does
    // not exist.
    $organization = Organization::factory()->create();
    $location = Location::factory()->create();

    $organization->locations()->attach($location);

    expect($organization->locations()->getTable())->toBe('organization_location')
        ->and($location->organizations()->getTable())->toBe('organization_location')
        ->and($organization->refresh()->locations)->toHaveCount(1)
        ->and($location->refresh()->organizations->first()->is($organization))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| People
|--------------------------------------------------------------------------
*/

test('a person is a coach unless told otherwise', function () {
    expect((new Person)->profession)->toBe(PersonProfession::Coach)
        ->and(Person::factory()->create()->profession)->toBe(PersonProfession::Coach);
});

test('a person can be a doctor, physiotherapist or nutritionist', function () {
    $physio = Person::factory()->professional(PersonProfession::Physiotherapist)->create();

    expect($physio->profession)->toBe(PersonProfession::Physiotherapist)
        // `role` is the job title, a separate thing from the profession.
        ->and($physio->role)->toBe('Fizioterapeut');
});

test('every profession has a Romanian label', function () {
    foreach (PersonProfession::cases() as $profession) {
        expect($profession->label())->not->toBe('');
    }

    expect(PersonProfession::options())->toHaveCount(4);
});

/*
|--------------------------------------------------------------------------
| Nothing in the panel is gated by what an organization is
|--------------------------------------------------------------------------
*/

/**
 * Sign a member in and put the panel on the given organization.
 */
function tenantContext(Organization $organization): User
{
    $member = User::factory()->create();
    $organization->addMember($member);

    test()->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('organization'));
    Filament::setTenant($organization);

    return $member;
}

test('every organization sees every resource, whatever it has published', function () {
    tenantContext(Organization::factory()->create());

    expect(OrganizationSportResource::canAccess())->toBeTrue()
        ->and(ScheduleSlotResource::canAccess())->toBeTrue()
        ->and(PersonResource::canAccess())->toBeTrue()
        ->and(LocationResource::canAccess())->toBeTrue();
});

test('a pool operator can reach the programme resources it needs to become a club', function () {
    // The chicken and egg the old gating created: adding a programme required
    // already being a club, and being a club required a programme.
    $venue = Organization::factory()->create();
    $presence = $venue->syncLocation(clujAddress());
    Space::factory()->for($presence, 'organizationLocation')->create([
        'location_id' => $presence->location_id,
        'status' => FacilityStatus::Approved,
    ]);

    $member = tenantContext($venue);

    $this->actingAs($member)
        ->get(OrganizationSportResource::getUrl(panel: 'organization', tenant: $venue))
        ->assertSuccessful();
});

/*
|--------------------------------------------------------------------------
| Discovery counts what is taught, not who the organization is
|--------------------------------------------------------------------------
*/

test('a presence that teaches nothing there is not counted as teaching', function () {
    $sport = Sport::factory()->create(['slug' => 'padel', 'name' => 'Padel']);
    $address = ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. Padel 1', 'name' => 'Baza Padel'];

    Organization::factory()->create()->syncLocation($address, [$sport->getKey()]);
    // Present at the same hall, teaching nothing — it only rents it out.
    Organization::factory()->create()->syncLocation($address);

    $reach = collect(Sport::withReach())->firstWhere('key', 'padel');

    expect($reach['clubCount'])->toBe(1)
        ->and($reach['locationCount'])->toBe(1);
});

test('an organization that rents out spaces still counts where it teaches', function () {
    // The case the old type filter got wrong: the pool operator with a swimming
    // club used to vanish from the club count because it was typed as a venue.
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $address = ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. B 2', 'name' => 'Bazinul B'];

    $pool = Organization::factory()->create();
    $presence = $pool->syncLocation($address, [$sport->getKey()]);
    Space::factory()->for($presence, 'organizationLocation')->create([
        'location_id' => $presence->location_id,
        'status' => FacilityStatus::Approved,
    ]);

    $reach = collect(Sport::withReach())->firstWhere('key', 'inot');

    expect($reach['clubCount'])->toBe(1);
});

test('the homepage club count leaves out organizations that teach nowhere', function () {
    $sport = Sport::factory()->create();
    $address = ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Sala A'];

    Organization::factory()->create()->syncLocation($address, [$sport->getKey()]);
    Organization::factory()->create()->syncLocation($address);
    Organization::factory()->create();

    $this->get(route('home'))->assertInertia(
        fn ($page) => $page->where('stats.clubs', 1),
    );
});

test('the explore page counts the presences that teach at a location', function () {
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $address = ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. B 2', 'name' => 'Bazinul B'];

    Organization::factory()->create()->syncLocation($address, [$sport->getKey()]);
    Organization::factory()->create()->syncLocation($address, [$sport->getKey()]);
    Organization::factory()->create()->syncLocation($address);

    $this->get(route('explore', ['oras' => 'Cluj-Napoca']))->assertInertia(
        fn ($page) => $page
            ->where('cities.0.clubCount', 2)
            ->where('locations.0.clubCount', 2)
            ->where('sports.0.clubCount', 2),
    );
});

test('a city whose locations host no programme still appears in the picker', function () {
    // Counted with a left join on purpose: dropping the city would hide a real
    // place from the visitor just because nobody teaches there yet.
    $address = ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. C 3', 'name' => 'Baza C'];
    Organization::factory()->create()->syncLocation($address);

    $this->get(route('explore'))->assertInertia(
        fn ($page) => $page
            ->where('cities.0.name', 'Cluj-Napoca')
            ->where('cities.0.locationCount', 1)
            ->where('cities.0.clubCount', 0),
    );
});
