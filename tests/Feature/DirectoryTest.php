<?php

use App\Enums\FacilityStatus;
use App\Enums\LocationWay;
use App\Enums\SpaceAccessMode;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\Sport;

/*
|--------------------------------------------------------------------------
| /cluburi — who teaches
|--------------------------------------------------------------------------
*/

test('the club index lists whoever teaches, and nobody else', function () {
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $location = Location::factory()->create(['city' => 'Brașov', 'county' => 'Brașov']);

    clubAt($location, $sport, 'CS Aqua');
    // Present at the same address, selling entry rather than teaching.
    spaceAt($location, $sport, SpaceAccessMode::OpenAccess, 25);

    $this->get(route('directory.clubs'))->assertInertia(
        fn ($page) => $page
            ->component('public/directory/Clubs')
            ->has('clubs', 1)
            ->where('clubs.0.name', 'CS Aqua')
            ->where('clubs.0.sports.0.label', 'Înot')
            ->where('clubs.0.cities.0', 'Brașov'),
    );
});

test('the pool that also teaches appears in both lists, out of two offers', function () {
    // The case the whole redesign exists for. One company, one address, two
    // answers — and nothing anywhere records which of them it "is".
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $location = Location::factory()->create(['city' => 'Brașov', 'county' => 'Brașov']);

    $pool = clubAt($location, $sport, 'Bazinul Olimpia');
    $presence = $pool->organizationLocations()->firstOrFail();

    $space = spaceAt($location, $sport, SpaceAccessMode::OpenAccess, 25);
    $space->update(['organization_location_id' => $presence->getKey()]);

    $this->get(route('directory.clubs'))->assertInertia(
        fn ($page) => $page->has('clubs', 1)->where('clubs.0.name', 'Bazinul Olimpia'),
    );

    $this->get(route('directory.venues'))->assertInertia(
        fn ($page) => $page->has('venues', 1)->where('venues.0.name', $location->name),
    );
});

test('the club index narrows by county and by sport', function () {
    $swimming = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $basketball = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);

    $brasov = Location::factory()->create(['city' => 'Brașov', 'county' => 'Brașov']);
    $cluj = Location::factory()->create(['city' => 'Cluj-Napoca', 'county' => 'Cluj']);

    clubAt($brasov, $swimming, 'CS Aqua');
    clubAt($cluj, $basketball, 'CS Baschet');

    $this->get(route('directory.clubs', ['judet' => 'Cluj']))->assertInertia(
        fn ($page) => $page->has('clubs', 1)->where('clubs.0.name', 'CS Baschet'),
    );

    $this->get(route('directory.clubs', ['sport' => 'inot']))->assertInertia(
        fn ($page) => $page->has('clubs', 1)->where('clubs.0.name', 'CS Aqua'),
    );
});

/*
|--------------------------------------------------------------------------
| /baze-sportive — where you can walk in
|--------------------------------------------------------------------------
*/

test('the venue index lists places, not the companies behind them', function () {
    $sport = Sport::factory()->create(['slug' => 'tenis', 'name' => 'Tenis']);
    $location = Location::factory()->create(['name' => 'Baza Olimpia', 'city' => 'Brașov', 'county' => 'Brașov']);

    spaceAt($location, $sport, SpaceAccessMode::OpenAccess, 25);

    $this->get(route('directory.venues'))->assertInertia(
        fn ($page) => $page
            ->component('public/directory/Venues')
            ->has('venues', 1)
            ->where('venues.0.name', 'Baza Olimpia')
            ->where('venues.0.city', 'Brașov')
            ->where('venues.0.ways.0.key', 'agrement')
            ->where('venues.0.ways.0.price', '25 lei / intrare'),
    );
});

test('a park court with nobody behind it is still a place you can go', function () {
    // The reason this list is of locations: there is no organization here to
    // list, and the visitor asking "where can I play" would not care if there
    // were.
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);
    $location = Location::factory()->create(['name' => 'Parcul Central', 'city' => 'Brașov', 'county' => 'Brașov']);

    spaceAt($location, $sport, SpaceAccessMode::OpenAccess, 0, managed: false);

    $this->get(route('directory.venues'))->assertInertia(
        fn ($page) => $page
            ->has('venues', 1)
            ->where('venues.0.name', 'Parcul Central')
            ->where('venues.0.unmanaged', true),
    );
});

test('a hall nobody can walk into stays off the venue index', function () {
    $sport = Sport::factory()->create(['slug' => 'handbal', 'name' => 'Handbal']);
    $location = Location::factory()->create(['city' => 'Brașov', 'county' => 'Brașov']);

    // A club trains here and that is all: no space, so no way in of your own.
    clubAt($location, $sport, 'CS Handbal');

    $this->get(route('directory.venues'))->assertInertia(
        fn ($page) => $page->where('venues', []),
    );
});

test('a space still waiting for moderation is not offered to anybody', function () {
    $sport = Sport::factory()->create(['slug' => 'tenis', 'name' => 'Tenis']);
    $location = Location::factory()->create(['city' => 'Brașov', 'county' => 'Brașov']);

    spaceAt($location, $sport, SpaceAccessMode::OpenAccess, 25)
        ->forceFill(['status' => FacilityStatus::Pending])
        ->save();

    $this->get(route('directory.venues'))->assertInertia(
        fn ($page) => $page->where('venues', []),
    );
});

/*
|--------------------------------------------------------------------------
| /specialisti — who patches you up
|--------------------------------------------------------------------------
*/

test('the practice index lists whoever sells medical care', function () {
    $rehab = Specialty::factory()->create(['name' => 'Recuperare', 'slug' => 'recuperare']);
    $location = Location::factory()->create(['city' => 'Brașov', 'county' => 'Brașov']);

    $clinic = Organization::factory()->create(['name' => 'Clinica Test']);
    $clinic->organizationLocations()->create(['location_id' => $location->getKey()]);
    Service::factory()->create([
        'organization_id' => $clinic->getKey(),
        'specialty_id' => $rehab->getKey(),
    ]);

    $this->get(route('directory.practices'))->assertInertia(
        fn ($page) => $page
            ->component('public/directory/Practices')
            ->has('practices', 1)
            ->where('practices.0.name', 'Clinica Test')
            ->where('practices.0.specialties.0.label', 'Recuperare'),
    );
});

test('a studio selling nothing but massage stays off the practice index', function () {
    // The same line the sport page draws: massage sits alongside a real activity
    // rather than being one, and nobody browsing recovery is looking for it.
    $massage = Specialty::factory()->create(['name' => 'Masaj', 'slug' => 'masaj', 'is_medical' => false]);
    $location = Location::factory()->create(['city' => 'Brașov', 'county' => 'Brașov']);

    $studio = Organization::factory()->create(['name' => 'Pilates Studio']);
    $studio->organizationLocations()->create(['location_id' => $location->getKey()]);
    Service::factory()->create([
        'organization_id' => $studio->getKey(),
        'specialty_id' => $massage->getKey(),
    ]);

    $this->get(route('directory.practices'))->assertInertia(
        fn ($page) => $page->where('practices', []),
    );
});

test('a clinic is listed for its medical work and not for its massage', function () {
    $rehab = Specialty::factory()->create(['name' => 'Recuperare', 'slug' => 'recuperare', 'sort_order' => 0]);
    $massage = Specialty::factory()->create(['name' => 'Masaj', 'slug' => 'masaj', 'is_medical' => false, 'sort_order' => 1]);
    $location = Location::factory()->create(['city' => 'Brașov', 'county' => 'Brașov']);

    $clinic = Organization::factory()->create(['name' => 'Clinica Test']);
    $clinic->organizationLocations()->create(['location_id' => $location->getKey()]);

    foreach ([$rehab, $massage] as $specialty) {
        Service::factory()->create([
            'organization_id' => $clinic->getKey(),
            'specialty_id' => $specialty->getKey(),
        ]);
    }

    $this->get(route('directory.practices'))->assertInertia(
        fn ($page) => $page
            ->has('practices', 1)
            ->has('practices.0.specialties', 1)
            ->where('practices.0.specialties.0.label', 'Recuperare'),
    );
});

test('the practice index narrows by specialty', function () {
    $rehab = Specialty::factory()->create(['name' => 'Recuperare', 'slug' => 'recuperare']);
    $nutrition = Specialty::factory()->create(['name' => 'Nutriție', 'slug' => 'nutritie']);
    $location = Location::factory()->create(['city' => 'Brașov', 'county' => 'Brașov']);

    foreach ([['Clinica A', $rehab], ['Clinica B', $nutrition]] as [$name, $specialty]) {
        $clinic = Organization::factory()->create(['name' => $name]);
        $clinic->organizationLocations()->create(['location_id' => $location->getKey()]);
        Service::factory()->create([
            'organization_id' => $clinic->getKey(),
            'specialty_id' => $specialty->getKey(),
        ]);
    }

    $this->get(route('directory.practices', ['specialitate' => 'nutritie']))->assertInertia(
        fn ($page) => $page->has('practices', 1)->where('practices.0.name', 'Clinica B'),
    );

    // Only the medical ones are offered as a filter at all.
    $this->get(route('directory.practices'))->assertInertia(
        fn ($page) => $page->has('specialties', 2),
    );
});

/*
|--------------------------------------------------------------------------
| The badges on a location card
|--------------------------------------------------------------------------
*/

test('a location card says which ways in it offers', function () {
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);
    $location = Location::factory()->create(['city' => 'Cluj-Napoca', 'county' => 'Cluj']);

    clubAt($location, $sport, 'CS Test');
    spaceAt($location, $sport, SpaceAccessMode::ExclusiveRental, 180);

    $this->get(route('explore', ['oras' => 'Cluj-Napoca']))->assertInertia(
        fn ($page) => $page
            ->has('locations.0.ways', 2)
            ->where('locations.0.ways.0.key', 'cursuri')
            ->where('locations.0.ways.0.label', 'Cursuri')
            ->where('locations.0.ways.1.key', 'inchiriere'),
    );
});

test('a way nobody offers here gets no badge', function () {
    // A badge leading to an empty page is a promise the card cannot keep.
    $sport = Sport::factory()->create(['slug' => 'baschet', 'name' => 'Baschet']);
    $location = Location::factory()->create(['city' => 'Cluj-Napoca', 'county' => 'Cluj']);

    clubAt($location, $sport, 'CS Test');

    $this->get(route('explore', ['oras' => 'Cluj-Napoca']))->assertInertia(
        fn ($page) => $page
            ->has('locations.0.ways', 1)
            ->where('locations.0.ways.0.key', 'cursuri'),
    );
});

test('the badge words are the same three everywhere', function () {
    // One question asked in one vocabulary: the badge, the explore filter, the
    // tab on an organization page and the fragment that opens it.
    expect(collect(LocationWay::cases())->map->value->all())
        ->toBe(['cursuri', 'agrement', 'inchiriere']);
});
