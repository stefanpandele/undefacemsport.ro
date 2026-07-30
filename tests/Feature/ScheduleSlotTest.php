<?php

use App\Enums\Weekday;
use App\Filament\Organization\Resources\ScheduleSlots\Pages\ManageScheduleSlots;
use App\Filament\Organization\Resources\ScheduleSlots\ScheduleSlotResource;
use App\Models\AgeGroup;
use App\Models\Organization;
use App\Models\OrganizationLocation;
use App\Models\OrganizationLocationSport;
use App\Models\Person;
use App\Models\ScheduleSlot;
use App\Models\Sport;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('a schedule slot ties a club-location-sport, day, age group and person together', function () {
    $organization = Organization::factory()->create();
    $sport = Sport::factory()->create();
    $organizationLocation = OrganizationLocation::factory()->create(['organization_id' => $organization->id]);
    $organizationLocationSport = OrganizationLocationSport::factory()->create([
        'organization_location_id' => $organizationLocation->id,
        'sport_id' => $sport->id,
    ]);
    $person = Person::factory()->create(['organization_id' => $organization->id]);
    $ageGroup = AgeGroup::factory()->create();

    $slot = ScheduleSlot::factory()->create([
        'organization_id' => $organization->id,
        'organization_location_sport_id' => $organizationLocationSport->id,
        'day_of_week' => Weekday::Wednesday,
        'start_time' => '17:00',
        'end_time' => '18:00',
        'age_group_id' => $ageGroup->id,
        'person_id' => $person->id,
    ]);

    expect($slot->day_of_week)->toBe(Weekday::Wednesday)
        ->and($slot->organization->is($organization))->toBeTrue()
        ->and($slot->organizationLocationSport->sport->is($sport))->toBeTrue()
        ->and($slot->ageGroup->is($ageGroup))->toBeTrue()
        ->and($slot->person->is($person))->toBeTrue()
        ->and($organizationLocationSport->scheduleSlots)->toHaveCount(1);
});

test('removing a sport from a location cascades its schedule slots', function () {
    $organization = Organization::factory()->create();
    $organizationLocation = OrganizationLocation::factory()->create(['organization_id' => $organization->id]);
    $organizationLocationSport = OrganizationLocationSport::factory()->create([
        'organization_location_id' => $organizationLocation->id,
        'sport_id' => Sport::factory()->create()->id,
    ]);
    ScheduleSlot::factory()->create([
        'organization_id' => $organization->id,
        'organization_location_sport_id' => $organizationLocationSport->id,
    ]);

    $organizationLocationSport->delete();

    expect(ScheduleSlot::count())->toBe(0);
});

test('one submit of the bulk form creates a slot per row', function () {
    $organization = Organization::factory()->create();
    $organizationLocation = OrganizationLocation::factory()->create(['organization_id' => $organization->id]);
    $organizationLocationSport = OrganizationLocationSport::factory()->create([
        'organization_location_id' => $organizationLocation->id,
        'sport_id' => Sport::factory()->create()->id,
    ]);
    $ageGroup = AgeGroup::factory()->create();
    $person = Person::factory()->create(['organization_id' => $organization->id]);

    $first = ScheduleSlotResource::persistMany([
        'organization_location_sport_id' => $organizationLocationSport->id,
        'slots' => [
            ['day_of_week' => Weekday::Monday->value, 'start_time' => '17:00', 'end_time' => '18:00', 'age_group_id' => $ageGroup->id, 'person_id' => $person->id],
            ['day_of_week' => Weekday::Wednesday->value, 'start_time' => '17:00', 'end_time' => '18:00', 'age_group_id' => $ageGroup->id, 'person_id' => null],
        ],
    ]);

    expect(ScheduleSlot::count())->toBe(2)
        ->and($first->day_of_week)->toBe(Weekday::Monday)
        // The club is derived from the location, never trusted from the form.
        ->and(ScheduleSlot::pluck('organization_id')->unique()->all())->toBe([$organization->id])
        ->and(ScheduleSlot::whereNull('person_id')->count())->toBe(1);
});

test('a club member adds several intervals from the schedule page', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member);

    $organizationLocation = OrganizationLocation::factory()->create(['organization_id' => $organization->id]);
    $organizationLocationSport = OrganizationLocationSport::factory()->create([
        'organization_location_id' => $organizationLocation->id,
        'sport_id' => Sport::factory()->create()->id,
    ]);
    $ageGroup = AgeGroup::factory()->create();

    $this->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('organization'));
    Filament::setTenant($organization);

    // Set the repeater rows wholesale, so they replace the empty row the
    // modal opens with instead of being appended to it.
    Livewire::test(ManageScheduleSlots::class)
        ->mountAction('create')
        ->set('mountedActions.0.data.organization_location_sport_id', $organizationLocationSport->id)
        ->set('mountedActions.0.data.slots', [
            ['day_of_week' => Weekday::Tuesday->value, 'start_time' => '18:00', 'end_time' => '19:30', 'age_group_id' => $ageGroup->id, 'person_id' => null],
            ['day_of_week' => Weekday::Thursday->value, 'start_time' => '18:00', 'end_time' => '19:30', 'age_group_id' => $ageGroup->id, 'person_id' => null],
            ['day_of_week' => Weekday::Saturday->value, 'start_time' => '10:00', 'end_time' => '11:30', 'age_group_id' => $ageGroup->id, 'person_id' => null],
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(ScheduleSlot::where('organization_id', $organization->id)->count())->toBe(3)
        ->and(ScheduleSlot::pluck('day_of_week')->map(fn (Weekday $day): int => $day->value)->all())
        ->toBe([Weekday::Tuesday->value, Weekday::Thursday->value, Weekday::Saturday->value]);
});

test('a club member can open the schedule page without error', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member);

    $this->actingAs($member)
        ->get(ScheduleSlotResource::getUrl(panel: 'organization', tenant: $organization))
        ->assertSuccessful();
});
