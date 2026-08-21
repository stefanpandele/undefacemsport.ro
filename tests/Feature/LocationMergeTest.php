<?php

use App\Actions\MergeLocations;
use App\Enums\ScheduleSlotKind;
use App\Enums\SpaceAccessMode;
use App\Enums\Weekday;
use App\Filament\Admin\Resources\Locations\LocationResource;
use App\Filament\Admin\Resources\Locations\Pages\ManageLocations;
use App\Models\Facility;
use App\Models\Location;
use App\Models\LocationCorrection;
use App\Models\LocationRedirect;
use App\Models\Organization;
use App\Models\OrganizationLocation;
use App\Models\OrganizationLocationSport;
use App\Models\ScheduleSlot;
use App\Models\Space;
use App\Models\Sport;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

function merge(Location $winner, Location $loser): array
{
    return app(MergeLocations::class)->handle($winner, $loser);
}

test('a presence moves across when the organization is only at one of the two', function () {
    $winner = Location::factory()->create();
    $loser = Location::factory()->create();
    $sport = Sport::factory()->create();

    $club = clubAt($loser, $sport, 'CS Test');

    merge($winner, $loser);

    expect(Location::count())->toBe(1)
        ->and($club->organizationLocations()->first()->location_id)->toBe($winner->getKey())
        ->and(ScheduleSlot::count())->toBe(1);
});

test('a club present at both places keeps one presence, not two', function () {
    // UNIQUE(organization_id, location_id) would reject a plain repoint, so the
    // two rows have to be merged.
    $winner = Location::factory()->create();
    $loser = Location::factory()->create();
    $swimming = Sport::factory()->create(['slug' => 'inot']);
    $basketball = Sport::factory()->create(['slug' => 'baschet']);

    $club = Organization::factory()->create();

    $atWinner = OrganizationLocation::create([
        'organization_id' => $club->getKey(),
        'location_id' => $winner->getKey(),
    ]);
    $atWinner->sports()->sync([$swimming->getKey()]);

    $atLoser = OrganizationLocation::create([
        'organization_id' => $club->getKey(),
        'location_id' => $loser->getKey(),
    ]);
    $atLoser->sports()->sync([$basketball->getKey()]);

    merge($winner, $loser);

    $presences = $club->organizationLocations()->with('sports')->get();

    expect($presences)->toHaveCount(1)
        ->and($presences->first()->location_id)->toBe($winner->getKey())
        // The union of the sports, not one of them.
        ->and($presences->first()->sports->pluck('slug')->sort()->values()->all())
        ->toBe(['baschet', 'inot']);
});

test('the same club and sport at both places keeps every training', function () {
    // The collision one level down: UNIQUE(organization_location_id, sport_id).
    // The slots must survive the husk being removed.
    $winner = Location::factory()->create();
    $loser = Location::factory()->create();
    $sport = Sport::factory()->create();
    $club = Organization::factory()->create();

    foreach ([$winner, $loser] as $index => $location) {
        $presence = OrganizationLocation::create([
            'organization_id' => $club->getKey(),
            'location_id' => $location->getKey(),
        ]);
        $presence->sports()->sync([$sport->getKey()]);

        ScheduleSlot::create([
            'kind' => ScheduleSlotKind::Training,
            'organization_id' => $club->getKey(),
            'organization_location_sport_id' => $presence->organizationLocationSports->first()->getKey(),
            'day_of_week' => Weekday::from($index + 1),
            'start_time' => '17:00',
            'end_time' => '18:30',
        ]);
    }

    expect(ScheduleSlot::count())->toBe(2);

    merge($winner, $loser);

    expect(ScheduleSlot::count())->toBe(2)
        ->and(OrganizationLocationSport::count())->toBe(1)
        ->and(OrganizationLocation::count())->toBe(1);
});

test('nothing is deleted before it has moved', function () {
    // location_id cascades all the way down to schedule slots, so deleting the
    // loser first would silently destroy schedules.
    $winner = Location::factory()->create();
    $loser = Location::factory()->create();
    $sport = Sport::factory()->create();

    clubAt($loser, $sport, 'CS A');
    clubAt($loser, $sport, 'CS B');
    spaceAt($loser, $sport, SpaceAccessMode::OpenAccess, 0, managed: false);

    $slotsBefore = ScheduleSlot::count();

    merge($winner, $loser);

    expect(ScheduleSlot::count())->toBe($slotsBefore)
        ->and(OrganizationLocation::where('location_id', $winner->getKey())->count())->toBe(2)
        ->and(Space::where('location_id', $winner->getKey())->count())->toBe(1);
});

test('the retired slug keeps working, and the surviving one is untouched', function () {
    $winner = Location::factory()->create(['name' => 'Bazinul Olimpic', 'city' => 'Cluj-Napoca']);
    $loser = Location::factory()->create(['name' => 'Bazin Olimpic Cluj', 'city' => 'Cluj-Napoca']);
    $oldSlug = $loser->slug;

    merge($winner, $loser);

    expect(LocationRedirect::query()->where('slug', $oldSlug)->value('location_id'))->toBe($winner->getKey())
        // A redirect for the winner's own slug would shadow the real page.
        ->and(LocationRedirect::query()->where('slug', $winner->slug)->exists())->toBeFalse();

    $this->get('/locatii/'.$oldSlug)
        ->assertStatus(301)
        ->assertRedirect(route('locations.show', $winner->slug));

    $this->get(route('locations.show', $winner->slug))->assertSuccessful();
});

test('a slug merged twice still finds its way', function () {
    $first = Location::factory()->create();
    $second = Location::factory()->create();
    $third = Location::factory()->create();
    $oldest = $first->slug;

    merge($second, $first);
    merge($third, $second);

    expect(LocationRedirect::query()->where('slug', $oldest)->value('location_id'))->toBe($third->getKey());

    $this->get('/locatii/'.$oldest)
        ->assertStatus(301)
        ->assertRedirect(route('locations.show', $third->slug));
});

test('amenities are added, never removed, and a proof photo is never overwritten', function () {
    $winner = Location::factory()->create();
    $loser = Location::factory()->create();

    $shared = Facility::factory()->create(['name' => 'Parcare']);
    $onlyAtLoser = Facility::factory()->create(['name' => 'Tribună']);
    $withoutPhoto = Facility::factory()->create(['name' => 'Dușuri']);

    $winner->facilities()->attach($shared->getKey(), ['photo_path' => 'winner.webp']);
    $winner->facilities()->attach($withoutPhoto->getKey(), ['photo_path' => null]);

    $loser->facilities()->attach($shared->getKey(), ['photo_path' => 'loser.webp']);
    $loser->facilities()->attach($onlyAtLoser->getKey(), ['photo_path' => 'tribuna.webp']);
    $loser->facilities()->attach($withoutPhoto->getKey(), ['photo_path' => 'dusuri.webp']);

    merge($winner, $loser);

    $photos = $winner->facilities()->pluck('photo_path', 'facilities.id');

    expect($winner->facilities()->count())->toBe(3)
        // Kept, not replaced.
        ->and($photos[$shared->getKey()])->toBe('winner.webp')
        ->and($photos[$onlyAtLoser->getKey()])->toBe('tribuna.webp')
        // A gap is filled, though.
        ->and($photos[$withoutPhoto->getKey()])->toBe('dusuri.webp');
});

test('a space operated through the dropped presence follows it', function () {
    $winner = Location::factory()->create();
    $loser = Location::factory()->create();
    $venue = Organization::factory()->create();

    $atWinner = OrganizationLocation::create([
        'organization_id' => $venue->getKey(),
        'location_id' => $winner->getKey(),
    ]);
    $atLoser = OrganizationLocation::create([
        'organization_id' => $venue->getKey(),
        'location_id' => $loser->getKey(),
    ]);

    $space = Space::factory()->create([
        'location_id' => $loser->getKey(),
        'organization_location_id' => $atLoser->getKey(),
    ]);

    merge($winner, $loser);

    expect($space->refresh()->location_id)->toBe($winner->getKey())
        ->and($space->organization_location_id)->toBe($atWinner->getKey())
        ->and($space->isManaged())->toBeTrue();
});

test('pending corrections follow the surviving location', function () {
    $winner = Location::factory()->create();
    $loser = Location::factory()->create();
    LocationCorrection::factory()->count(2)->create(['location_id' => $loser->getKey()]);

    merge($winner, $loser);

    expect(LocationCorrection::count())->toBe(2)
        ->and(LocationCorrection::where('location_id', $winner->getKey())->count())->toBe(2);
});

test('a location cannot be merged into itself', function () {
    $location = Location::factory()->create();

    expect(fn () => merge($location, $location))->toThrow(InvalidArgumentException::class);
});

test('the merge preview counts what would move without moving it', function () {
    $winner = Location::factory()->create();
    $loser = Location::factory()->create();
    $sport = Sport::factory()->create();

    clubAt($loser, $sport, 'CS Test');
    spaceAt($loser, $sport, SpaceAccessMode::OpenAccess, 0, managed: false);

    $preview = app(MergeLocations::class)->preview($winner, $loser);

    expect($preview['presences'])->toBe(1)
        ->and($preview['sports'])->toBe(1)
        ->and($preview['slots'])->toBe(1)
        ->and($preview['spaces'])->toBe(1)
        // Still both there: a preview promises nothing.
        ->and(Location::count())->toBe(2);
});

test('an admin can merge two locations from the panel', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $winner = Location::factory()->at(46.7660, 23.5735)->create(['city' => 'Cluj-Napoca']);
    $loser = Location::factory()->at(46.76605, 23.57355)->create(['city' => 'Cluj-Napoca']);
    $sport = Sport::factory()->create();
    clubAt($loser, $sport, 'CS Test');

    $this->actingAs($admin)
        ->get(LocationResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful();

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ManageLocations::class)
        ->callAction(TestAction::make('merge')->table($loser), data: [
            'winner' => $winner->getKey(),
        ]);

    expect(Location::count())->toBe(1)
        ->and(Location::first()->is($winner))->toBeTrue()
        ->and(LocationRedirect::query()->where('slug', $loser->slug)->exists())->toBeTrue();
});

test('a club member cannot reach the locations screen', function () {
    $member = User::factory()->create();
    Organization::factory()->create()->addMember($member);

    $this->actingAs($member)
        ->get(LocationResource::getUrl('index', panel: 'admin'))
        ->assertRedirect();
});
