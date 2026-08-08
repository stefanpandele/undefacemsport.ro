<?php

use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationLocation;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\Sport;

/**
 * A practice in the city whose service is ticked for the given sports.
 *
 * @param  list<Sport>  $sports
 */
function clinicFor(Location $location, Specialty $specialty, string $name, array $sports, ?float $price = 250): Organization
{
    $clinic = Organization::factory()->create(['name' => $name]);

    OrganizationLocation::create([
        'organization_id' => $clinic->getKey(),
        'location_id' => $location->getKey(),
    ]);

    Service::factory()->create([
        'organization_id' => $clinic->getKey(),
        'specialty_id' => $specialty->getKey(),
        'price' => $price,
    ])->sports()->sync(collect($sports)->map->getKey()->all());

    return $clinic;
}

test('an injured athlete finds the clinics that treat their sport', function () {
    // The practitioner ticked football, so a footballer finds them. Nothing is
    // inferred from the specialty.
    $football = Sport::factory()->create(['slug' => 'fotbal', 'name' => 'Fotbal']);
    $rehab = Specialty::factory()->create(['name' => 'Recuperare', 'slug' => 'recuperare', 'icon' => '🩹']);

    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    clubAt($location, $football, 'CS Test');
    $clinic = clinicFor($location, $rehab, 'Clinica Recuperare', [$football]);

    $this->get(route('sports.show', ['slug' => 'fotbal', 'city' => 'cluj-napoca']))->assertInertia(
        fn ($page) => $page
            ->has('care', 1)
            ->where('care.0.name', 'Clinica Recuperare')
            ->where('care.0.slug', $clinic->slug)
            ->where('care.0.specialties.0.label', 'Recuperare')
            ->where('care.0.price', '250 lei'),
    );
});

test('care is separate from the ways in, because it is not a way to play', function () {
    $football = Sport::factory()->create(['slug' => 'fotbal', 'name' => 'Fotbal']);
    $rehab = Specialty::factory()->create(['slug' => 'recuperare', 'name' => 'Recuperare']);

    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    clubAt($location, $football, 'CS Test');
    clinicFor($location, $rehab, 'Clinica', [$football]);

    $this->get(route('sports.show', ['slug' => 'fotbal', 'city' => 'cluj-napoca']))->assertInertia(
        fn ($page) => $page
            // One way to play, and the clinic is not among them.
            ->has('ways', 1)
            ->where('ways.0.key', 'organizat')
            ->has('care', 1),
    );
});

test('a clinic that did not tick this sport stays out', function () {
    // Physiotherapy in general involves footballers; this physiotherapist said
    // otherwise, and the page takes them at their word.
    $football = Sport::factory()->create(['slug' => 'fotbal', 'name' => 'Fotbal']);
    $chess = Sport::factory()->create(['slug' => 'sah', 'name' => 'Șah']);
    $rehab = Specialty::factory()->create(['slug' => 'recuperare', 'name' => 'Recuperare']);

    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    clubAt($location, $football, 'CS Test');
    clinicFor($location, $rehab, 'Clinica de șah', [$chess]);

    $this->get(route('sports.show', ['slug' => 'fotbal', 'city' => 'cluj-napoca']))->assertInertia(
        fn ($page) => $page->where('care', []),
    );
});

test('only the services ticked for this sport are listed', function () {
    // The same clinic does recovery for footballers and nutrition for swimmers.
    // A footballer should see the first and not the second.
    $football = Sport::factory()->create(['slug' => 'fotbal', 'name' => 'Fotbal']);
    $swimming = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);

    $rehab = Specialty::factory()->create(['name' => 'Recuperare', 'slug' => 'recuperare', 'sort_order' => 0]);
    $nutrition = Specialty::factory()->create(['name' => 'Nutriție', 'slug' => 'nutritie', 'sort_order' => 1]);

    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    $clinic = clinicFor($location, $rehab, 'Clinica', [$football]);
    Service::factory()->create([
        'organization_id' => $clinic->getKey(),
        'specialty_id' => $nutrition->getKey(),
    ])->sports()->sync([$swimming->getKey()]);

    $this->get(route('sports.show', ['slug' => 'fotbal', 'city' => 'cluj-napoca']))->assertInertia(
        fn ($page) => $page
            ->has('care.0.specialties', 1)
            ->where('care.0.specialties.0.label', 'Recuperare'),
    );
});

test('a studio that sells massage on the side is not listed under recovery', function () {
    // Massage sits alongside a real activity rather than being one. The line is
    // drawn on the specialty, not on the organization, so it holds now that
    // nothing records what an organization "is".
    $football = Sport::factory()->create(['slug' => 'fotbal', 'name' => 'Fotbal']);
    $massage = Specialty::factory()->create(['slug' => 'masaj', 'name' => 'Masaj', 'is_medical' => false]);

    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    $studio = clubAt($location, $football, 'Pilates Studio');

    Service::factory()->create([
        'organization_id' => $studio->getKey(),
        'specialty_id' => $massage->getKey(),
    ])->sports()->sync([$football->getKey()]);

    $this->get(route('sports.show', ['slug' => 'fotbal', 'city' => 'cluj-napoca']))->assertInertia(
        fn ($page) => $page->where('care', []),
    );
});

test('a clinic is listed for its medical work and not for its massage', function () {
    // The same line, applied to a clinic: what gets it listed is the recovery, and
    // the card says so. Drawing it on the organization would have let the massage
    // through here while blocking it at the studio.
    $football = Sport::factory()->create(['slug' => 'fotbal', 'name' => 'Fotbal']);
    $rehab = Specialty::factory()->create(['slug' => 'recuperare', 'name' => 'Recuperare', 'sort_order' => 0]);
    $massage = Specialty::factory()->create(['slug' => 'masaj', 'name' => 'Masaj', 'is_medical' => false, 'sort_order' => 1]);

    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    clubAt($location, $football, 'CS Cluj');
    $clinic = clinicFor($location, $rehab, 'Clinica Recuperare', [$football]);

    Service::factory()->create([
        'organization_id' => $clinic->getKey(),
        'specialty_id' => $massage->getKey(),
    ])->sports()->sync([$football->getKey()]);

    $this->get(route('sports.show', ['slug' => 'fotbal', 'city' => 'cluj-napoca']))->assertInertia(
        fn ($page) => $page
            ->has('care', 1)
            ->has('care.0.specialties', 1)
            ->where('care.0.specialties.0.label', 'Recuperare'),
    );
});

test('a service with no specialty at all does not reach the recovery section', function () {
    $football = Sport::factory()->create(['slug' => 'fotbal', 'name' => 'Fotbal']);

    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    $studio = clubAt($location, $football, 'Pilates Studio');

    Service::factory()->create([
        'organization_id' => $studio->getKey(),
        'specialty_id' => null,
    ])->sports()->sync([$football->getKey()]);

    $this->get(route('sports.show', ['slug' => 'fotbal', 'city' => 'cluj-napoca']))->assertInertia(
        fn ($page) => $page->where('care', []),
    );
});

test('a clinic in another city stays in its own city', function () {
    $football = Sport::factory()->create(['slug' => 'fotbal', 'name' => 'Fotbal']);
    $rehab = Specialty::factory()->create(['slug' => 'recuperare', 'name' => 'Recuperare']);

    $cluj = Location::factory()->create(['city' => 'Cluj-Napoca']);
    clubAt($cluj, $football, 'CS Cluj');
    clinicFor(Location::factory()->create(['city' => 'București']), $rehab, 'Clinica București', [$football]);

    $this->get(route('sports.show', ['slug' => 'fotbal', 'city' => 'cluj-napoca']))->assertInertia(
        fn ($page) => $page->where('care', []),
    );
});

test('care is absent before a city is chosen', function () {
    $football = Sport::factory()->create(['slug' => 'fotbal', 'name' => 'Fotbal']);
    $rehab = Specialty::factory()->create(['slug' => 'recuperare', 'name' => 'Recuperare']);

    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    clubAt($location, $football, 'CS Test');
    clinicFor($location, $rehab, 'Clinica', [$football]);

    $this->get(route('sports.show', 'fotbal'))->assertInertia(
        fn ($page) => $page->where('care', []),
    );
});

test('a sport with clinics but no clubs still shows the clinics', function () {
    // Somebody may need recovery for a sport nobody teaches in that city.
    $football = Sport::factory()->create(['slug' => 'fotbal', 'name' => 'Fotbal']);
    $rehab = Specialty::factory()->create(['slug' => 'recuperare', 'name' => 'Recuperare']);

    $location = Location::factory()->create(['city' => 'Cluj-Napoca']);
    clinicFor($location, $rehab, 'Clinica', [$football]);
    // A club too, so the city picker has this city at all.
    clubAt($location, $football, 'CS Test');

    $this->get(route('sports.show', ['slug' => 'fotbal', 'city' => 'cluj-napoca']))->assertInertia(
        fn ($page) => $page->has('care', 1),
    );
});

test('every seeded practice service says which athletes it is for', function () {
    // A club's extra need not: the studio's massage is for whoever is there, not
    // for a sport it wants to be found under. Only a practice's own work makes
    // the claim.
    $this->seed();

    $unticked = Service::query()
        ->doesntHave('sports')
        ->whereHas('organization', fn ($query) => $query->doesntHave('organizationSports'))
        ->pluck('name');

    expect(Service::query()->has('sports')->count())->toBeGreaterThan(0)
        ->and($unticked)->toBeEmpty('Practice services with no sport ticked: '.$unticked->implode(', '));
});

test('a city with a clinic and no club is still offered in the picker', function () {
    // Somebody with a bad knee in a town where nobody teaches the sport still
    // needs an answer. Leaving the city out would make it unreachable.
    $football = Sport::factory()->create(['slug' => 'fotbal', 'name' => 'Fotbal']);
    $rehab = Specialty::factory()->create(['slug' => 'recuperare', 'name' => 'Recuperare']);

    clinicFor(Location::factory()->create(['city' => 'Zalău']), $rehab, 'Clinica', [$football]);

    $this->get(route('sports.show', 'fotbal'))->assertInertia(
        fn ($page) => $page
            ->has('cities', 1)
            ->where('cities.0.name', 'Zalău')
            ->where('cities.0.care', 1)
            ->where('cities.0.ways', []),
    );

    $this->get(route('sports.show', ['slug' => 'fotbal', 'city' => 'zalau']))->assertInertia(
        fn ($page) => $page->where('ways', [])->has('care', 1),
    );
});

test('the organization page shows which athletes each service is for', function () {
    $football = Sport::factory()->create(['slug' => 'fotbal', 'name' => 'Fotbal', 'icon' => '⚽']);
    $clinic = Organization::factory()->create();

    Service::factory()->create([
        'organization_id' => $clinic->getKey(),
        'name' => 'Recuperare post-accidentare',
    ])->sports()->sync([$football->getKey()]);

    $this->get(route('organizations.show', $clinic->slug))->assertInertia(
        fn ($page) => $page
            ->has('organization.services.0.sports', 1)
            ->where('organization.services.0.sports.0.label', 'Fotbal'),
    );
});
