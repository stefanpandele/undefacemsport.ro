<?php

use App\Models\Location;
use App\Models\Organization;
use App\Models\Sport;

/**
 * The names below are read by the Vue components. Nothing else checks that the
 * two sides agree: TypeScript cannot see the controllers, and no test renders a
 * template, so renaming `coaches` to `people` in PHP left both public pages
 * blank in the browser while the whole suite stayed green.
 *
 * If one of these fails, rename on the other side too rather than editing the
 * list — `resources/js/types/sports.ts` for the location page, the `ClubProfile`
 * type in `resources/js/pages/public/clubs/Show.vue` for the club page.
 */
test('the club block a location renders carries the keys Vue reads', function () {
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $location = Location::factory()->create(['slug' => 'bazinul-test', 'city' => 'Brașov']);
    clubAt($location, $sport, 'CS Test');

    $response = $this->get(route('locations.show', 'bazinul-test'));

    $club = $response->viewData('page')['props']['location']['clubs'][0];

    // ClubBlock.vue dereferences `club.people[0]` without a guard, so a missing
    // key is not a missing avatar — it is a TypeError and an empty page.
    expect(array_keys($club))->toEqualCanonicalizing([
        'key', 'slug', 'sport', 'name', 'representative', 'about', 'photos',
        'trustChips', 'ages', 'levels', 'people', 'schedule', 'contactName',
        'contactPhone',
    ]);
});

test('the club page carries the keys Vue reads', function () {
    $organization = Organization::factory()->create(['slug' => 'clubul-test']);
    $organization->organizationSports()->create([
        'sport_id' => Sport::factory()->create(['slug' => 'inot'])->getKey(),
    ]);

    $response = $this->get(route('clubs.show', 'clubul-test'));

    expect(array_keys($response->viewData('page')['props']['club']))->toEqualCanonicalizing([
        'slug', 'name', 'representative', 'about', 'phone', 'socials', 'sports',
        'sportDetails', 'people', 'locationsBySport', 'extras',
    ]);
});
