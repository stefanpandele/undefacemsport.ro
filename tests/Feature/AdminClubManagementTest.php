<?php

use App\Filament\Admin\Resources\Clubs\ClubResource;
use App\Filament\Admin\Resources\Clubs\Pages\EditClub;
use App\Filament\Admin\Resources\Clubs\RelationManagers\ClubSportsRelationManager;
use App\Filament\Admin\Resources\Clubs\RelationManagers\CoachesRelationManager;
use App\Filament\Admin\Resources\Clubs\RelationManagers\LocationsRelationManager;
use App\Filament\Admin\Resources\Clubs\RelationManagers\ScheduleSlotsRelationManager;
use App\Models\Club;
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
    $club = Club::factory()->create();

    $this->get(ClubResource::getUrl('edit', ['record' => $club]))
        ->assertSuccessful();
});

test('every club sub-entity relation manager renders for an admin', function () {
    $club = Club::factory()->create();

    $managers = [
        ClubSportsRelationManager::class,
        LocationsRelationManager::class,
        CoachesRelationManager::class,
        ScheduleSlotsRelationManager::class,
    ];

    foreach ($managers as $manager) {
        Livewire::test($manager, [
            'ownerRecord' => $club,
            'pageClass' => EditClub::class,
        ])->assertSuccessful();
    }
});
