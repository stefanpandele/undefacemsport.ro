<?php

use App\Enums\Weekday;
use App\Filament\Club\Resources\ScheduleSlots\Pages\ManageScheduleSlots;
use App\Filament\Club\Resources\ScheduleSlots\ScheduleSlotResource;
use App\Models\AgeGroup;
use App\Models\Club;
use App\Models\ClubLocation;
use App\Models\ClubLocationSport;
use App\Models\Coach;
use App\Models\ScheduleSlot;
use App\Models\Sport;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('a schedule slot ties a club-location-sport, day, age group and coach together', function () {
    $club = Club::factory()->create();
    $sport = Sport::factory()->create();
    $clubLocation = ClubLocation::factory()->create(['club_id' => $club->id]);
    $clubLocationSport = ClubLocationSport::factory()->create([
        'club_location_id' => $clubLocation->id,
        'sport_id' => $sport->id,
    ]);
    $coach = Coach::factory()->create(['club_id' => $club->id]);
    $ageGroup = AgeGroup::factory()->create();

    $slot = ScheduleSlot::factory()->create([
        'club_id' => $club->id,
        'club_location_sport_id' => $clubLocationSport->id,
        'day_of_week' => Weekday::Wednesday,
        'start_time' => '17:00',
        'end_time' => '18:00',
        'age_group_id' => $ageGroup->id,
        'coach_id' => $coach->id,
    ]);

    expect($slot->day_of_week)->toBe(Weekday::Wednesday)
        ->and($slot->club->is($club))->toBeTrue()
        ->and($slot->clubLocationSport->sport->is($sport))->toBeTrue()
        ->and($slot->ageGroup->is($ageGroup))->toBeTrue()
        ->and($slot->coach->is($coach))->toBeTrue()
        ->and($clubLocationSport->scheduleSlots)->toHaveCount(1);
});

test('removing a sport from a location cascades its schedule slots', function () {
    $club = Club::factory()->create();
    $clubLocation = ClubLocation::factory()->create(['club_id' => $club->id]);
    $clubLocationSport = ClubLocationSport::factory()->create([
        'club_location_id' => $clubLocation->id,
        'sport_id' => Sport::factory()->create()->id,
    ]);
    ScheduleSlot::factory()->create([
        'club_id' => $club->id,
        'club_location_sport_id' => $clubLocationSport->id,
    ]);

    $clubLocationSport->delete();

    expect(ScheduleSlot::count())->toBe(0);
});

test('one submit of the bulk form creates a slot per row', function () {
    $club = Club::factory()->create();
    $clubLocation = ClubLocation::factory()->create(['club_id' => $club->id]);
    $clubLocationSport = ClubLocationSport::factory()->create([
        'club_location_id' => $clubLocation->id,
        'sport_id' => Sport::factory()->create()->id,
    ]);
    $ageGroup = AgeGroup::factory()->create();
    $coach = Coach::factory()->create(['club_id' => $club->id]);

    $first = ScheduleSlotResource::persistMany([
        'club_location_sport_id' => $clubLocationSport->id,
        'slots' => [
            ['day_of_week' => Weekday::Monday->value, 'start_time' => '17:00', 'end_time' => '18:00', 'age_group_id' => $ageGroup->id, 'coach_id' => $coach->id],
            ['day_of_week' => Weekday::Wednesday->value, 'start_time' => '17:00', 'end_time' => '18:00', 'age_group_id' => $ageGroup->id, 'coach_id' => null],
        ],
    ]);

    expect(ScheduleSlot::count())->toBe(2)
        ->and($first->day_of_week)->toBe(Weekday::Monday)
        // The club is derived from the location, never trusted from the form.
        ->and(ScheduleSlot::pluck('club_id')->unique()->all())->toBe([$club->id])
        ->and(ScheduleSlot::whereNull('coach_id')->count())->toBe(1);
});

test('a club member adds several intervals from the schedule page', function () {
    $member = User::factory()->create();
    $club = Club::factory()->create();
    $club->addMember($member);

    $clubLocation = ClubLocation::factory()->create(['club_id' => $club->id]);
    $clubLocationSport = ClubLocationSport::factory()->create([
        'club_location_id' => $clubLocation->id,
        'sport_id' => Sport::factory()->create()->id,
    ]);
    $ageGroup = AgeGroup::factory()->create();

    $this->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('club'));
    Filament::setTenant($club);

    // Set the repeater rows wholesale, so they replace the empty row the
    // modal opens with instead of being appended to it.
    Livewire::test(ManageScheduleSlots::class)
        ->mountAction('create')
        ->set('mountedActions.0.data.club_location_sport_id', $clubLocationSport->id)
        ->set('mountedActions.0.data.slots', [
            ['day_of_week' => Weekday::Tuesday->value, 'start_time' => '18:00', 'end_time' => '19:30', 'age_group_id' => $ageGroup->id, 'coach_id' => null],
            ['day_of_week' => Weekday::Thursday->value, 'start_time' => '18:00', 'end_time' => '19:30', 'age_group_id' => $ageGroup->id, 'coach_id' => null],
            ['day_of_week' => Weekday::Saturday->value, 'start_time' => '10:00', 'end_time' => '11:30', 'age_group_id' => $ageGroup->id, 'coach_id' => null],
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(ScheduleSlot::where('club_id', $club->id)->count())->toBe(3)
        ->and(ScheduleSlot::pluck('day_of_week')->map(fn (Weekday $day): int => $day->value)->all())
        ->toBe([Weekday::Tuesday->value, Weekday::Thursday->value, Weekday::Saturday->value]);
});

test('a club member can open the schedule page without error', function () {
    $member = User::factory()->create();
    $club = Club::factory()->create();
    $club->addMember($member);

    $this->actingAs($member)
        ->get(ScheduleSlotResource::getUrl(panel: 'club', tenant: $club))
        ->assertSuccessful();
});
