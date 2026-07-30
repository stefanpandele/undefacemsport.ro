<?php

use App\Filament\Admin\Resources\Organizations\OrganizationResource;
use App\Filament\Admin\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Admin\Resources\Organizations\RelationManagers\LocationsRelationManager;
use App\Filament\Admin\Resources\Organizations\RelationManagers\OrganizationSportsRelationManager;
use App\Filament\Admin\Resources\Organizations\RelationManagers\PeopleRelationManager;
use App\Filament\Admin\Resources\Organizations\RelationManagers\ScheduleSlotsRelationManager;
use App\Models\Organization;
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
