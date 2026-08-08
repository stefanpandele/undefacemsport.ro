<?php

use App\Filament\Admin\Resources\Organizations\OrganizationResource;
use App\Filament\Admin\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Admin\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Admin\Resources\Organizations\RelationManagers\LocationsRelationManager;
use App\Filament\Admin\Resources\Organizations\RelationManagers\OrganizationSportsRelationManager;
use App\Filament\Admin\Resources\Organizations\RelationManagers\PeopleRelationManager;
use App\Filament\Admin\Resources\Organizations\RelationManagers\ScheduleSlotsRelationManager;
use App\Models\Organization;
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
