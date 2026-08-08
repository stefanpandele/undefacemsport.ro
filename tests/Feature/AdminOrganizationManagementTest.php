<?php

use App\Enums\FacilityStatus;
use App\Enums\ScheduleSlotKind;
use App\Enums\SpaceAccessMode;
use App\Enums\Weekday;
use App\Filament\Admin\Resources\Organizations\OrganizationResource;
use App\Filament\Admin\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Admin\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Admin\Resources\Organizations\RelationManagers\LocationsRelationManager;
use App\Filament\Admin\Resources\Organizations\RelationManagers\OrganizationSportsRelationManager;
use App\Filament\Admin\Resources\Organizations\RelationManagers\PeopleRelationManager;
use App\Filament\Admin\Resources\Organizations\RelationManagers\ScheduleSlotsRelationManager;
use App\Models\Organization;
use App\Models\ScheduleSlot;
use App\Models\Service;
use App\Models\Space;
use App\Models\Sport;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('auth.super_admins', ['boss@undefacemsport.ro']);
    $this->admin = User::factory()->create(['email' => 'boss@undefacemsport.ro', 'is_admin' => true]);
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('an admin can open a club edit page with its relation managers', function () {
    $organization = Organization::factory()->create();

    $this->get(OrganizationResource::getUrl('edit', ['record' => $organization]))
        ->assertSuccessful();
});

test('every club sub-entity relation manager renders for an admin', function () {
    $organization = Organization::factory()->create();

    $managers = [
        OrganizationSportsRelationManager::class,
        LocationsRelationManager::class,
        PeopleRelationManager::class,
        ScheduleSlotsRelationManager::class,
    ];

    foreach ($managers as $manager) {
        Livewire::test($manager, [
            'ownerRecord' => $organization,
            'pageClass' => EditOrganization::class,
        ])->assertSuccessful();
    }
});

test('the organizations table counts counties distinct from locations', function () {
    // Three halls in one town is a one-county operation, and the column says so —
    // otherwise "prezență județe" would just be the location count again.
    $sport = Sport::factory()->create();
    $club = Organization::factory()->create(['name' => 'CS Reach']);

    foreach ([
        ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Sala A'],
        ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. B 2', 'name' => 'Sala B'],
        ['county' => 'Brașov', 'city' => 'Brașov', 'address' => 'Str. C 3', 'name' => 'Sala C'],
    ] as $address) {
        $club->syncLocation($address, [$sport->getKey()]);
    }

    $row = Livewire::test(ListOrganizations::class)
        ->assertSuccessful()
        ->instance()
        ->getFilteredTableQuery()
        ->find($club->getKey());

    expect((int) $row->counties_count)->toBe(2)
        ->and((int) $row->locations_count)->toBe(3);
});

test('an organization with no address at all counts zero counties', function () {
    $club = Organization::factory()->create();

    $row = Livewire::test(ListOrganizations::class)
        ->instance()
        ->getFilteredTableQuery()
        ->find($club->getKey());

    expect((int) $row->counties_count)->toBe(0)
        ->and((int) $row->locations_count)->toBe(0);
});

test('the organizations table says what each one offers, and in what quantity', function () {
    $sport = Sport::factory()->create();
    $club = Organization::factory()->create(['name' => 'CS Ofertă']);
    $club->organizationSports()->create(['sport_id' => $sport->getKey()]);

    $presence = $club->syncLocation(
        ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Sala A'],
        [$sport->getKey()],
    );

    Space::factory()->for($presence, 'organizationLocation')->create([
        'location_id' => $presence->location_id,
        'access_mode' => SpaceAccessMode::OpenAccess,
        'status' => FacilityStatus::Approved,
    ]);
    Service::factory()->for($club)->count(2)->create();

    $row = Livewire::test(ListOrganizations::class)
        ->instance()
        ->getFilteredTableQuery()
        ->find($club->getKey());

    expect((int) $row->courses_count)->toBe(1)
        ->and((int) $row->open_access_count)->toBe(1)
        ->and((int) $row->rental_count)->toBe(0)
        ->and((int) $row->services_count)->toBe(2);
});

test('a hall rented by the hour that opens on friday evenings counts as both', function () {
    // The access mode lives on the space and may be overridden per interval. The
    // admin has to see the same two ways in the public pages show.
    $club = Organization::factory()->create();
    $presence = $club->syncLocation(
        ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. B 2', 'name' => 'Sala B'],
    );

    $hall = Space::factory()->for($presence, 'organizationLocation')->create([
        'location_id' => $presence->location_id,
        'access_mode' => SpaceAccessMode::ExclusiveRental,
        'status' => FacilityStatus::Approved,
    ]);

    ScheduleSlot::create([
        'kind' => ScheduleSlotKind::Access,
        'space_id' => $hall->getKey(),
        'day_of_week' => Weekday::Friday,
        'start_time' => '20:00',
        'end_time' => '22:00',
        'access_mode' => SpaceAccessMode::OpenAccess,
    ]);

    $row = Livewire::test(ListOrganizations::class)
        ->instance()
        ->getFilteredTableQuery()
        ->find($club->getKey());

    expect((int) $row->rental_count)->toBe(1)
        ->and((int) $row->open_access_count)->toBe(1);
});

test('a space still waiting for moderation is not offered to the admin either', function () {
    $club = Organization::factory()->create();
    $presence = $club->syncLocation(
        ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. C 3', 'name' => 'Sala C'],
    );

    Space::factory()->for($presence, 'organizationLocation')->create([
        'location_id' => $presence->location_id,
        'access_mode' => SpaceAccessMode::OpenAccess,
        'status' => FacilityStatus::Pending,
    ]);

    $row = Livewire::test(ListOrganizations::class)
        ->instance()
        ->getFilteredTableQuery()
        ->find($club->getKey());

    expect((int) $row->open_access_count)->toBe(0);
});
