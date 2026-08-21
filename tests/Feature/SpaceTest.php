<?php

use App\Enums\FacilityStatus;
use App\Enums\PriceUnit;
use App\Enums\ScheduleSlotKind;
use App\Enums\SpaceAccessMode;
use App\Enums\Weekday;
use App\Filament\Admin\Resources\Spaces\Pages\ManageSpaces;
use App\Filament\Admin\Resources\Spaces\SpaceResource as AdminSpaceResource;
use App\Filament\Organization\Resources\Spaces\SpaceResource;
use App\Models\Facility;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationLocation;
use App\Models\ScheduleSlot;
use App\Models\Space;
use App\Models\Sport;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Managed vs unmanaged
|--------------------------------------------------------------------------
*/

test('a space with no operator is unmanaged', function () {
    $space = Space::factory()->unmanaged()->create();

    expect($space->isManaged())->toBeFalse()
        ->and($space->isFree())->toBeTrue()
        ->and(Space::query()->unmanaged()->count())->toBe(1)
        ->and(Space::query()->managed()->count())->toBe(0);
});

test('a space survives its operator walking away', function () {
    // The point of nullOnDelete: the hoop in the park is still there when the
    // company that used to rent it out leaves.
    $space = Space::factory()->create();
    $presence = $space->organizationLocation;

    $presence->delete();

    expect(Space::query()->whereKey($space->getKey())->exists())->toBeTrue()
        ->and($space->refresh()->isManaged())->toBeFalse();
});

test('a space goes away with the location it is at', function () {
    $space = Space::factory()->create();

    $space->location->delete();

    expect(Space::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Price
|--------------------------------------------------------------------------
*/

test('a space with no tariff at all has nothing to say about price', function () {
    // Not free, not priced: nobody has written a tariff yet, and the page must
    // not invent one.
    $space = Space::factory()->create();

    expect($space->isFree())->toBeFalse()
        ->and($space->priceFrom())->toBeNull()
        ->and($space->priceFromLabel())->toBeNull()
        ->and($space->accessModes())->toBeEmpty();
});

test('an unpriced tariff is unknown, not free', function () {
    $space = Space::factory()->create();
    tariff($space, SpaceAccessMode::OpenAccess, null);

    expect($space->load('accessSlots')->isFree())->toBeFalse()
        ->and($space->priceFrom())->toBeNull()
        ->and($space->priceFromLabel())->toBeNull();
});

test('a price of zero is free, and says so', function () {
    $space = Space::factory()->unmanaged()->create();

    expect($space->isFree())->toBeTrue()
        ->and($space->priceFromLabel())->toBe('Gratuit');
});

test('the unit formats the amount the way it is written on the door', function () {
    expect(PriceUnit::Entry->format(45))->toBe('45 lei / intrare')
        ->and(PriceUnit::Hour->format(120))->toBe('120 lei / oră')
        ->and(PriceUnit::Entry->format(45.5))->toBe('45,50 lei / intrare')
        ->and(PriceUnit::Month->format(1200))->toBe('1.200 lei / lună')
        ->and(PriceUnit::Hour->format(null))->toBe('');
});

test('a single price is quoted plainly, without "de la"', function () {
    $space = Space::factory()->openAccess(45)->create();

    expect($space->priceFromLabel())->toBe('45 lei / intrare');
});

test('a varying price is quoted from its cheapest tariff', function () {
    // The squash hall: one rate until six, a higher one for the evening. The card
    // must not advertise the evening rate, which is wrong all morning.
    $space = Space::factory()->create();

    slot($space, Weekday::Monday, '08:00', '18:00', 80, SpaceAccessMode::ExclusiveRental);
    slot($space, Weekday::Monday, '18:00', '21:00', 110, SpaceAccessMode::ExclusiveRental);

    $space->load('accessSlots');

    expect($space->hasVaryingPrice())->toBeTrue()
        ->and($space->priceFrom())->toBe(80.0)
        ->and($space->priceFromLabel())->toBe('de la 80 lei / oră');
});

test('the price unit falls back to what the access mode implies', function () {
    $rental = Space::factory()->rental(180)->create();

    expect($rental->priceFromLabel())->toBe('180 lei / oră')
        ->and(SpaceAccessMode::OpenAccess->defaultPriceUnit())->toBe(PriceUnit::Entry)
        ->and(SpaceAccessMode::ExclusiveRental->defaultPriceUnit())->toBe(PriceUnit::Hour);
});

test('a tariff with no hours means the programme is unknown, not closed', function () {
    // The park hoop an admin put on the map: the price is known, the timetable is
    // not. Seven rows saying "închis" would be a claim nobody made.
    $hoop = Space::factory()->unmanaged()->create();

    expect($hoop->hoursByDay())->toBeEmpty()
        ->and($hoop->hasKnownHours())->toBeFalse()
        ->and($hoop->priceFromLabel())->toBe('Gratuit')
        ->and($hoop->accessModes()->all())->toBe([SpaceAccessMode::OpenAccess]);
});

/*
|--------------------------------------------------------------------------
| One space, two ways in
|--------------------------------------------------------------------------
*/

test('a hall booked by the hour can also run open-gym evenings', function () {
    // One physical hall, two ways in. Two space rows for the same thing would show
    // up twice on the page, which is why the way in sits on the tariff.
    $hall = Space::factory()->create(['name' => 'Sala mare']);

    slot($hall, Weekday::Monday, '08:00', '22:00', 180, SpaceAccessMode::ExclusiveRental);
    slot($hall, Weekday::Friday, '20:00', '22:00', 25, SpaceAccessMode::OpenAccess);

    $hall->load('accessSlots');

    expect($hall->accessModes()->all())->toBe([
        SpaceAccessMode::ExclusiveRental,
        SpaceAccessMode::OpenAccess,
    ])
        ->and($hall->slotsFor(SpaceAccessMode::OpenAccess))->toHaveCount(1)
        ->and($hall->slotsFor(SpaceAccessMode::ExclusiveRental))->toHaveCount(1);
});

test('each way in is priced in its own unit, never borrowing the other', function () {
    $hall = Space::factory()->create();

    slot($hall, Weekday::Monday, '08:00', '22:00', 180, SpaceAccessMode::ExclusiveRental);
    slot($hall, Weekday::Friday, '20:00', '22:00', 25, SpaceAccessMode::OpenAccess);

    $hall->load('accessSlots');

    expect($hall->priceFromLabel(SpaceAccessMode::ExclusiveRental))->toBe('180 lei / oră')
        ->and($hall->priceFromLabel(SpaceAccessMode::OpenAccess))->toBe('25 lei / intrare');
});

test('an unpriced tariff stays unknown next to a priced one', function () {
    // 180 lei an hour must never be quoted as the price of an open-gym ticket.
    $hall = Space::factory()->create();

    slot($hall, Weekday::Monday, '08:00', '22:00', 180, SpaceAccessMode::ExclusiveRental);
    slot($hall, Weekday::Friday, '20:00', '22:00', null, SpaceAccessMode::OpenAccess);

    $hall->load('accessSlots');

    expect($hall->priceFrom(SpaceAccessMode::OpenAccess))->toBeNull()
        ->and($hall->priceFromLabel(SpaceAccessMode::OpenAccess))->toBeNull()
        ->and($hall->priceFromLabel(SpaceAccessMode::ExclusiveRental))->toBe('180 lei / oră');
});

test('a tariff must say how you get in', function () {
    // The way in lives here and nowhere else, so a tariff without one would be an
    // interval nobody could be told how to use.
    $space = Space::factory()->create();

    expect(fn () => ScheduleSlot::create([
        'kind' => ScheduleSlotKind::Access,
        'space_id' => $space->getKey(),
        'day_of_week' => Weekday::Monday,
        'start_time' => '07:00',
        'end_time' => '22:00',
    ]))->toThrow(LogicException::class, 'access_mode');
});

test('opening hours can be asked for one way in at a time', function () {
    $hall = Space::factory()->create();

    slot($hall, Weekday::Monday, '08:00', '22:00', 180, SpaceAccessMode::ExclusiveRental);
    slot($hall, Weekday::Friday, '20:00', '22:00', 25, SpaceAccessMode::OpenAccess);

    $hall->load('accessSlots');

    $friday = Carbon::parse('2026-08-07')->setTime(20, 30); // a Friday

    expect($hall->hoursByDay(SpaceAccessMode::OpenAccess))->toHaveCount(1)
        ->and($hall->hoursByDay(SpaceAccessMode::ExclusiveRental))->toHaveCount(1)
        ->and($hall->hoursByDay())->toHaveCount(2)
        ->and($hall->openAt($friday, SpaceAccessMode::OpenAccess))->toBeTrue()
        ->and($hall->openAt($friday, SpaceAccessMode::ExclusiveRental))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Opening hours
|--------------------------------------------------------------------------
*/

test('a space is open inside its interval and closed outside it', function () {
    $space = Space::factory()->create();
    slot($space, Weekday::Monday, '07:00', '22:00', null);
    $space->load('accessSlots');

    $monday = Carbon::parse('2026-08-03'); // a Monday

    expect($space->openAt($monday->copy()->setTime(10, 0)))->toBeTrue()
        ->and($space->openAt($monday->copy()->setTime(6, 30)))->toBeFalse()
        ->and($space->openAt($monday->copy()->setTime(22, 30)))->toBeFalse()
        // Tuesday has no interval at all, so it is closed.
        ->and($space->openAt($monday->copy()->addDay()->setTime(10, 0)))->toBeFalse();
});

test('a space says when it closes today', function () {
    $space = Space::factory()->create();
    slot($space, Weekday::Monday, '07:00', '22:00', null);
    $space->load('accessSlots');

    $monday = Carbon::parse('2026-08-03')->setTime(10, 0);

    expect($space->closesAt($monday))->toBe('22:00')
        ->and($space->closesAt($monday->copy()->setTime(23, 0)))->toBeNull();
});

test('hours are grouped by weekday, and a closed day is simply absent', function () {
    $space = Space::factory()->create();
    slot($space, Weekday::Monday, '07:00', '22:00', null);
    slot($space, Weekday::Saturday, '08:00', '20:00', 30);
    $space->load('accessSlots');

    $hours = $space->hoursByDay();

    expect($hours)->toHaveCount(2)
        ->and($hours[Weekday::Monday->value][0]['start'])->toBe('07:00')
        ->and($hours[Weekday::Saturday->value][0]['price'])->toBe(30.0)
        ->and($hours)->not->toHaveKey(Weekday::Sunday->value);
});

/*
|--------------------------------------------------------------------------
| The two kinds of slot
|--------------------------------------------------------------------------
*/

test('a training slot needs the club sport it belongs to', function () {
    expect(fn () => ScheduleSlot::create([
        'kind' => ScheduleSlotKind::Training,
        'day_of_week' => Weekday::Monday,
        'start_time' => '17:00',
        'end_time' => '18:30',
    ]))->toThrow(LogicException::class, 'organization_location_sport_id');
});

test('an access slot needs the space it belongs to', function () {
    expect(fn () => ScheduleSlot::create([
        'kind' => ScheduleSlotKind::Access,
        'day_of_week' => Weekday::Monday,
        'start_time' => '07:00',
        'end_time' => '22:00',
    ]))->toThrow(LogicException::class, 'space_id');
});

test('a space keeps its own hours apart from the trainings inside it', function () {
    $space = Space::factory()->create();
    slot($space, Weekday::Monday, '07:00', '22:00', null);

    $club = Organization::factory()->create();
    $presence = $club->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Sala A',
    ], [Sport::factory()->create()->getKey()]);

    ScheduleSlot::create([
        'kind' => ScheduleSlotKind::Training,
        'organization_id' => $club->getKey(),
        'organization_location_sport_id' => $presence->organizationLocationSports->first()->getKey(),
        'space_id' => $space->getKey(),
        'day_of_week' => Weekday::Monday,
        'start_time' => '17:00',
        'end_time' => '18:30',
    ]);

    expect($space->scheduleSlots()->count())->toBe(2)
        ->and($space->accessSlots()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Plan limits
|--------------------------------------------------------------------------
*/

test('a free organization can operate one space then hits its limit', function () {
    $organization = Organization::factory()->create(); // free: spaces limit 1
    $presence = presenceFor($organization);

    expect($organization->canAddSpace())->toBeTrue();

    Space::factory()->create([
        'location_id' => $presence->location_id,
        'organization_location_id' => $presence->getKey(),
    ]);

    expect($organization->canAddSpace())->toBeFalse();
});

test('a public space never counts against the quota', function () {
    // Putting a park court on the map is a contribution, not an asset. Charging
    // it against the plan would penalise the organization for the favour.
    $organization = Organization::factory()->create();
    $presence = presenceFor($organization);

    Space::factory()->unmanaged()->count(5)->create(['location_id' => $presence->location_id]);

    expect($organization->canAddSpace())->toBeTrue()
        ->and($organization->spaces()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Implied amenities
|--------------------------------------------------------------------------
*/

test('a floodlit space declares the amenity, so the filters cannot contradict the offer', function () {
    $facility = Facility::factory()->create([
        'name' => 'Iluminat nocturn',
        'status' => FacilityStatus::Approved,
    ]);
    $location = Location::factory()->create();

    Space::factory()->unmanaged()->create([
        'location_id' => $location->getKey(),
        'has_floodlights' => true,
    ]);

    expect($location->refresh()->facilities->pluck('id'))->toContain($facility->getKey());
});

test('a space without floodlights declares nothing', function () {
    Facility::factory()->create(['name' => 'Iluminat nocturn', 'status' => FacilityStatus::Approved]);
    $location = Location::factory()->create();

    Space::factory()->unmanaged()->create([
        'location_id' => $location->getKey(),
        'has_floodlights' => false,
    ]);

    expect($location->refresh()->facilities)->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| The panels
|--------------------------------------------------------------------------
*/

/**
 * Put a member of the given organization inside the tenant panel.
 */
function spacePanelContext(Organization $organization): User
{
    $member = User::factory()->create();
    $organization->addMember($member);

    test()->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('organization'));
    Filament::setTenant($organization);

    return $member;
}

test('spaces are open to a venue and to a club alike', function () {
    // Renting out dead hours does not make a club a different kind of
    // organization, so this resource is deliberately not club-gated.
    spacePanelContext(Organization::factory()->create());
    expect(SpaceResource::canAccess())->toBeTrue();

    spacePanelContext(Organization::factory()->create());
    expect(SpaceResource::canAccess())->toBeTrue();
});

test('a venue sees the spaces at its own locations and the public ones there', function () {
    $venue = Organization::factory()->create();
    $presence = presenceFor($venue);
    spacePanelContext($venue);

    $mine = Space::factory()->create([
        'location_id' => $presence->location_id,
        'organization_location_id' => $presence->getKey(),
    ]);
    $publicHere = Space::factory()->unmanaged()->create(['location_id' => $presence->location_id]);
    $somebodyElses = Space::factory()->create(['location_id' => $presence->location_id]);
    $elsewhere = Space::factory()->unmanaged()->create();

    $visible = SpaceResource::getEloquentQuery()->pluck('id');

    expect($visible)->toContain($mine->getKey(), $publicHere->getKey())
        ->and($visible)->not->toContain($somebodyElses->getKey())
        ->and($visible)->not->toContain($elsewhere->getKey());
});

test('the ownership toggle decides whether a space becomes the organization own', function () {
    $venue = Organization::factory()->create();
    $presence = presenceFor($venue);
    spacePanelContext($venue);

    $mine = SpaceResource::resolveOwnership([
        'location_id' => $presence->location_id,
        'is_operated_by_us' => true,
    ]);
    $contribution = SpaceResource::resolveOwnership([
        'location_id' => $presence->location_id,
        'is_operated_by_us' => false,
    ]);

    expect($mine['organization_location_id'])->toBe($presence->getKey())
        ->and($contribution['organization_location_id'])->toBeNull()
        ->and($mine)->not->toHaveKey('is_operated_by_us');
});

test('an admin can open the spaces screen and mark a public space verified', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $space = Space::factory()->unmanaged()->create(['last_verified_at' => null]);

    $this->actingAs($admin)
        ->get(AdminSpaceResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful();

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ManageSpaces::class)
        ->callAction(TestAction::make('verify')->table($space));

    expect($space->refresh()->last_verified_at)->not->toBeNull();
});

test('the review queue holds the public spaces nobody has checked lately', function () {
    $never = Space::factory()->unmanaged()->create(['last_verified_at' => null]);
    $old = Space::factory()->unmanaged()->verifiedAt(now()->subMonths(Space::STALE_AFTER_MONTHS + 1)->toDateTimeString())->create();
    $fresh = Space::factory()->unmanaged()->verifiedAt(now()->subMonth()->toDateTimeString())->create();
    // An operated space has somebody with a reason to keep it true.
    $managed = Space::factory()->create(['last_verified_at' => null]);

    $stale = Space::query()->stale()->pluck('id');

    expect($stale)->toContain($never->getKey(), $old->getKey())
        ->and($stale)->not->toContain($fresh->getKey())
        ->and($stale)->not->toContain($managed->getKey());
});

/**
 * Attach the organization to a fresh location and return the presence.
 */
function presenceFor(Organization $organization): OrganizationLocation
{
    $location = Location::factory()->create();

    return OrganizationLocation::create([
        'organization_id' => $organization->getKey(),
        'location_id' => $location->getKey(),
    ]);
}

/**
 * A tariff on a space, limited to one interval of one day.
 */
function slot(
    Space $space,
    Weekday $day,
    string $start,
    string $end,
    ?float $price,
    SpaceAccessMode $mode = SpaceAccessMode::OpenAccess,
    ?PriceUnit $unit = null,
): ScheduleSlot {
    return tariff($space, $mode, $price, $unit, $day, $start, $end);
}

/**
 * A tariff on a space: how you get in, what it costs, and when — the hours left
 * out when nobody knows them.
 */
function tariff(
    Space $space,
    SpaceAccessMode $mode = SpaceAccessMode::OpenAccess,
    ?float $price = null,
    ?PriceUnit $unit = null,
    ?Weekday $day = null,
    ?string $start = null,
    ?string $end = null,
): ScheduleSlot {
    return ScheduleSlot::create([
        'kind' => ScheduleSlotKind::Access,
        'organization_id' => $space->organizationLocation?->organization_id,
        'space_id' => $space->getKey(),
        'access_mode' => $mode,
        'price' => $price,
        'price_unit' => $unit,
        'day_of_week' => $day,
        'start_time' => $start,
        'end_time' => $end,
    ]);
}
