<?php

use App\Filament\Organization\Resources\Locations\Pages\ManageLocations;
use App\Filament\Organization\Resources\OrganizationSports\OrganizationSportResource;
use App\Filament\Organization\Resources\OrganizationSports\Pages\ManageOrganizationSports;
use App\Models\Organization;
use App\Models\OrganizationSport;
use App\Models\Sport;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

test('a club sport belongs to a club and a global sport', function () {
    $organization = Organization::factory()->create();
    $sport = Sport::factory()->create();

    $organizationSport = $organization->organizationSports()->create(['sport_id' => $sport->id]);

    expect($organizationSport)->toBeInstanceOf(OrganizationSport::class)
        ->and($organizationSport->organization->is($organization))->toBeTrue()
        ->and($organizationSport->sport->is($sport))->toBeTrue();
});

test('a free club can add one sport then hits the plan limit', function () {
    $organization = Organization::factory()->create(); // free plan: sports limit 1

    expect($organization->canAddSport())->toBeTrue();

    $organization->organizationSports()->create(['sport_id' => Sport::factory()->create()->id]);

    expect($organization->canAddSport())->toBeFalse();
});

test('a pro club can add sports up to its higher limit', function () {
    $organization = Organization::factory()->pro()->create(); // sports limit 5

    Sport::factory()->count(4)->create()->each(
        fn (Sport $sport) => $organization->organizationSports()->create(['sport_id' => $sport->id]),
    );

    expect($organization->canAddSport())->toBeTrue(); // 4 < 5

    $organization->organizationSports()->create(['sport_id' => Sport::factory()->create()->id]);

    expect($organization->canAddSport())->toBeFalse(); // 5 == 5
});

test('a premium club has no sport limit', function () {
    $organization = Organization::factory()->premium()->create();

    Sport::factory()->count(10)->create()->each(
        fn (Sport $sport) => $organization->organizationSports()->create(['sport_id' => $sport->id]),
    );

    expect($organization->canAddSport())->toBeTrue();
});

test('a club sport carries its benefits and gallery, and nothing it cannot answer for', function () {
    $organization = Organization::factory()->create();
    $organizationSport = $organization->organizationSports()->create([
        'sport_id' => Sport::factory()->create()->id,
    ]);

    $organizationSport->benefits()->create(['icon' => '🏅', 'label' => 'Licențiat FR Natație']);
    $organizationSport->images()->create(['path' => 'club-sports/gallery/1.jpg', 'collection' => 'gallery']);
    $organizationSport->images()->create(['path' => 'club-sports/gallery/2.jpg', 'collection' => 'cover']);

    $organizationSport->refresh();

    expect($organizationSport->benefits->first()->label)->toBe('Licențiat FR Natație')
        ->and($organizationSport->images)->toHaveCount(2)
        ->and($organizationSport->galleryImages)->toHaveCount(1) // only the gallery-collection image
        // Groups and levels vary by address and 1:1 belongs to a named person,
        // so the club-wide row must not be able to claim any of them.
        ->and(method_exists($organizationSport, 'ageGroups'))->toBeFalse()
        ->and(method_exists($organizationSport, 'levels'))->toBeFalse()
        ->and(Schema::hasColumn('organization_sport', 'offers_private_sessions'))->toBeFalse();
});

test('saying where you teach a sport is what declares it', function () {
    // No visit to the sports screen first: the row appears because the club
    // said it teaches this at an address.
    $organization = Organization::factory()->pro()->create();
    $swimming = Sport::factory()->create();
    $basketball = Sport::factory()->create();

    $organization->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Bazinul A',
    ], [$swimming->getKey(), $basketball->getKey()]);

    expect($organization->organizationSports()->pluck('sport_id')->all())
        ->toEqualCanonicalizing([$swimming->getKey(), $basketball->getKey()])
        ->and($organization->organizationSports()->pluck('sort_order')->all())->toBe([0, 1]);
});

test('declaring a sport a second address also teaches keeps its presentation', function () {
    $organization = Organization::factory()->pro()->create();
    $sport = Sport::factory()->create();

    $first = $organization->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Bazinul A',
    ], [$sport->getKey()]);

    $organizationSport = $organization->organizationSports()->firstOrFail();
    $organizationSport->benefits()->create(['icon' => '🏅', 'label' => 'Licențiat FR Natație']);

    $organization->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. B 2', 'name' => 'Bazinul B',
    ], [$sport->getKey()]);

    expect($organization->organizationSports()->count())->toBe(1)
        ->and($organizationSport->refresh()->benefits)->toHaveCount(1)
        ->and($first->refresh()->sports)->toHaveCount(1);
});

test('dropping a sport from an address does not un-declare it at the club', function () {
    // Otherwise a club moving halls would lose that sport's gallery and its
    // benefits on the way.
    $organization = Organization::factory()->pro()->create();
    $sport = Sport::factory()->create();

    $presence = $organization->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Bazinul A',
    ], [$sport->getKey()]);

    $organization->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Bazinul A',
    ], [], $presence);

    expect($presence->refresh()->sports)->toHaveCount(0)
        ->and($organization->organizationSports()->count())->toBe(1);
});

test('the location form refuses more new sports than the plan allows', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create(); // free plan: sports limit 1
    $organization->addMember($member);

    $this->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('organization'));
    Filament::setTenant($organization);

    Livewire::test(ManageLocations::class)
        ->mountAction('create')
        ->set('mountedActions.0.data.county', 'Cluj')
        ->set('mountedActions.0.data.city', 'Cluj-Napoca')
        ->set('mountedActions.0.data.address', 'Str. A 1')
        ->set('mountedActions.0.data.name', 'Bazinul A')
        ->set('mountedActions.0.data.sports', Sport::factory()->count(2)->create()->pluck('id')->all())
        ->callMountedAction()
        ->assertHasActionErrors(['sports']);

    expect($organization->organizationSports()->count())->toBe(0);
});

test('a sport with nothing to show is flagged, and one that is complete is not', function () {
    $organization = Organization::factory()->pro()->create();

    $bare = $organization->organizationSports()->create(['sport_id' => Sport::factory()->create()->id]);

    $photosOnly = $organization->organizationSports()->create(['sport_id' => Sport::factory()->create()->id]);
    $photosOnly->images()->create(['path' => 'a.jpg', 'collection' => 'gallery']);

    $complete = $organization->organizationSports()->create(['sport_id' => Sport::factory()->create()->id]);
    $complete->images()->create(['path' => 'b.jpg', 'collection' => 'gallery']);
    $complete->benefits()->create(['icon' => '🏅', 'label' => 'Licențiat FR Natație']);

    expect($bare->missingPresentation())->toBe(['poze', 'beneficii'])
        ->and($photosOnly->missingPresentation())->toBe(['beneficii'])
        ->and($complete->missingPresentation())->toBe([])
        ->and($complete->needsEnrichment())->toBeFalse();
});

test('a cover photo is not a gallery photo', function () {
    // The club page reads the gallery collection, so a cover alone still leaves
    // the photo strip empty.
    $organization = Organization::factory()->create();
    $organizationSport = $organization->organizationSports()->create(['sport_id' => Sport::factory()->create()->id]);
    $organizationSport->images()->create(['path' => 'cover.jpg', 'collection' => 'cover']);

    expect($organizationSport->refresh()->missingPresentation())->toContain('poze');
});

test('the query finds the same sports the accessor flags', function () {
    // The header badge planned for later asks this of a whole club at once, so
    // the two answers have to agree.
    $organization = Organization::factory()->pro()->create();

    $bare = $organization->organizationSports()->create(['sport_id' => Sport::factory()->create()->id]);

    $complete = $organization->organizationSports()->create(['sport_id' => Sport::factory()->create()->id]);
    $complete->images()->create(['path' => 'b.jpg', 'collection' => 'gallery']);
    $complete->benefits()->create(['icon' => '🏅', 'label' => 'Licențiat FR Natație']);

    expect(OrganizationSport::needingEnrichment()->pluck('id')->all())->toBe([$bare->getKey()]);
});

test('a club member can open the sports page without error', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member);

    $this->actingAs($member)
        ->get(OrganizationSportResource::getUrl(panel: 'organization', tenant: $organization))
        ->assertSuccessful();
});

test('the sport form modals are not closed by clicking away', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member);

    $this->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('organization'));
    Filament::setTenant($organization);

    $page = Livewire::test(ManageOrganizationSports::class)->instance();

    expect($page->getAction('create')->isModalClosedByClickingAway())->toBeFalse()
        ->and(EditAction::make()->isModalClosedByClickingAway())->toBeFalse()
        // Confirmation modals hold no input, so they keep the default.
        ->and(DeleteAction::make()->isModalClosedByClickingAway())->toBeTrue();
});
