<?php

use App\Enums\OrganizationType;
use App\Enums\PersonProfession;
use App\Filament\Organization\Resources\Locations\LocationResource;
use App\Filament\Organization\Resources\OrganizationSports\OrganizationSportResource;
use App\Filament\Organization\Resources\People\PersonResource;
use App\Filament\Organization\Resources\ScheduleSlots\ScheduleSlotResource;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Sport;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| The type itself
|--------------------------------------------------------------------------
*/

test('an organization created without a type is a club', function () {
    $organization = new Organization;

    expect($organization->type)->toBe(OrganizationType::Club)
        ->and($organization->isClub())->toBeTrue()
        ->and($organization->isVenue())->toBeFalse()
        ->and($organization->isPractice())->toBeFalse();
});

test('every existing row migrated to the club type', function () {
    // The column default backfills, so nothing that already existed changes
    // meaning: every organization on the platform today is a club.
    DB::table('organizations')->insert([
        'name' => 'Vechi', 'slug' => 'vechi', 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(Organization::query()->firstWhere('slug', 'vechi')->type)->toBe(OrganizationType::Club);
});

test('the factory can produce each type', function () {
    expect(Organization::factory()->create()->isClub())->toBeTrue()
        ->and(Organization::factory()->venue()->create()->isVenue())->toBeTrue()
        ->and(Organization::factory()->practice()->create()->isPractice())->toBeTrue();
});

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
| Panel resources gated by type
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

test('a club sees the programme resources', function () {
    tenantContext(Organization::factory()->create());

    expect(OrganizationSportResource::canAccess())->toBeTrue()
        ->and(ScheduleSlotResource::canAccess())->toBeTrue()
        ->and(PersonResource::canAccess())->toBeTrue();
});

test('a venue does not, because it runs no training programme', function () {
    tenantContext(Organization::factory()->venue()->create());

    expect(OrganizationSportResource::canAccess())->toBeFalse()
        ->and(ScheduleSlotResource::canAccess())->toBeFalse()
        ->and(PersonResource::canAccess())->toBeFalse()
        ->and(OrganizationSportResource::shouldRegisterNavigation())->toBeFalse();
});

test('a practice does not either', function () {
    tenantContext(Organization::factory()->practice()->create());

    expect(OrganizationSportResource::canAccess())->toBeFalse()
        ->and(ScheduleSlotResource::canAccess())->toBeFalse();
});

test('locations stay open to every type, because every organization has an address', function () {
    tenantContext(Organization::factory()->venue()->create());
    expect(LocationResource::canAccess())->toBeTrue();

    tenantContext(Organization::factory()->practice()->create());
    expect(LocationResource::canAccess())->toBeTrue();
});

test('a venue member is bounced off a programme resource URL, not just the nav link', function () {
    $venue = Organization::factory()->venue()->create();
    $member = tenantContext($venue);

    $this->actingAs($member)
        ->get(OrganizationSportResource::getUrl(panel: 'organization', tenant: $venue))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Discovery counts nothing but clubs
|--------------------------------------------------------------------------
*/

test('a venue at a location is not counted as a club teaching there', function () {
    $sport = Sport::factory()->create(['slug' => 'padel', 'name' => 'Padel']);
    $address = ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. Padel 1', 'name' => 'Baza Padel'];

    $club = Organization::factory()->create();
    $club->syncLocation($address, [$sport->getKey()]);

    $venue = Organization::factory()->venue()->create();
    $venue->syncLocation($address, [$sport->getKey()]);

    $reach = collect(Sport::withReach())->firstWhere('key', 'padel');

    expect($reach['clubCount'])->toBe(1)
        ->and($reach['locationCount'])->toBe(1);
});

test('the homepage club count leaves venues and practices out', function () {
    $address = ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Sala A'];

    Organization::factory()->create()->syncLocation($address);
    Organization::factory()->venue()->create()->syncLocation($address);
    Organization::factory()->practice()->create()->syncLocation($address);

    $this->get(route('home'))->assertInertia(
        fn ($page) => $page->where('stats.clubs', 1),
    );
});

test('the explore page counts only club presences at a location', function () {
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $address = ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. B 2', 'name' => 'Bazinul B'];

    Organization::factory()->create()->syncLocation($address, [$sport->getKey()]);
    Organization::factory()->create()->syncLocation($address, [$sport->getKey()]);
    Organization::factory()->venue()->create()->syncLocation($address, [$sport->getKey()]);

    $this->get(route('explore', ['oras' => 'Cluj-Napoca']))->assertInertia(
        fn ($page) => $page
            ->where('cities.0.clubCount', 2)
            ->where('locations.0.clubCount', 2)
            ->where('sports.0.clubCount', 2),
    );
});

test('a city whose locations host only a venue still appears in the picker', function () {
    // Counted with a left join on purpose: dropping the city would hide a real
    // place from the visitor just because no club has signed up there yet.
    $address = ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. C 3', 'name' => 'Baza C'];
    Organization::factory()->venue()->create()->syncLocation($address);

    $this->get(route('explore'))->assertInertia(
        fn ($page) => $page
            ->where('cities.0.name', 'Cluj-Napoca')
            ->where('cities.0.locationCount', 1)
            ->where('cities.0.clubCount', 0),
    );
});
