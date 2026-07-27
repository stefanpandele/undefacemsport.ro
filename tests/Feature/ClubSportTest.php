<?php

use App\Filament\Club\Resources\ClubSports\ClubSportResource;
use App\Filament\Club\Resources\ClubSports\Pages\ManageClubSports;
use App\Models\AgeGroup;
use App\Models\Club;
use App\Models\ClubSport;
use App\Models\Sport;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('a club sport belongs to a club and a global sport', function () {
    $club = Club::factory()->create();
    $sport = Sport::factory()->create();

    $clubSport = $club->clubSports()->create(['sport_id' => $sport->id]);

    expect($clubSport)->toBeInstanceOf(ClubSport::class)
        ->and($clubSport->club->is($club))->toBeTrue()
        ->and($clubSport->sport->is($sport))->toBeTrue();
});

test('a free club can add one sport then hits the plan limit', function () {
    $club = Club::factory()->create(); // free plan: sports limit 1

    expect($club->canAddSport())->toBeTrue();

    $club->clubSports()->create(['sport_id' => Sport::factory()->create()->id]);

    expect($club->canAddSport())->toBeFalse();
});

test('a pro club can add sports up to its higher limit', function () {
    $club = Club::factory()->pro()->create(); // sports limit 5

    Sport::factory()->count(4)->create()->each(
        fn (Sport $sport) => $club->clubSports()->create(['sport_id' => $sport->id]),
    );

    expect($club->canAddSport())->toBeTrue(); // 4 < 5

    $club->clubSports()->create(['sport_id' => Sport::factory()->create()->id]);

    expect($club->canAddSport())->toBeFalse(); // 5 == 5
});

test('a premium club has no sport limit', function () {
    $club = Club::factory()->premium()->create();

    Sport::factory()->count(10)->create()->each(
        fn (Sport $sport) => $club->clubSports()->create(['sport_id' => $sport->id]),
    );

    expect($club->canAddSport())->toBeTrue();
});

test('a club sport carries a private-sessions flag, age groups, benefits and a gallery', function () {
    $club = Club::factory()->create();
    $clubSport = $club->clubSports()->create([
        'sport_id' => Sport::factory()->create()->id,
        'offers_private_sessions' => true,
    ]);

    $clubSport->ageGroups()->attach(AgeGroup::factory()->count(2)->create());
    $clubSport->benefits()->create(['icon' => '🏅', 'label' => 'Licențiat FR Natație']);
    $clubSport->images()->create(['path' => 'club-sports/gallery/1.jpg', 'collection' => 'gallery']);
    $clubSport->images()->create(['path' => 'club-sports/gallery/2.jpg', 'collection' => 'cover']);

    $clubSport->refresh();

    expect($clubSport->offers_private_sessions)->toBeTrue()
        ->and($clubSport->ageGroups)->toHaveCount(2)
        ->and($clubSport->benefits->first()->label)->toBe('Licențiat FR Natație')
        ->and($clubSport->images)->toHaveCount(2)
        ->and($clubSport->galleryImages)->toHaveCount(1); // only the gallery-collection image
});

test('a club member can open the sports page without error', function () {
    $member = User::factory()->create();
    $club = Club::factory()->create();
    $club->addMember($member);

    $this->actingAs($member)
        ->get(ClubSportResource::getUrl(panel: 'club', tenant: $club))
        ->assertSuccessful();
});

test('the sport form modals are not closed by clicking away', function () {
    $member = User::factory()->create();
    $club = Club::factory()->create();
    $club->addMember($member);

    $this->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('club'));
    Filament::setTenant($club);

    $page = Livewire::test(ManageClubSports::class)->instance();

    expect($page->getAction('create')->isModalClosedByClickingAway())->toBeFalse()
        ->and(EditAction::make()->isModalClosedByClickingAway())->toBeFalse()
        // Confirmation modals hold no input, so they keep the default.
        ->and(DeleteAction::make()->isModalClosedByClickingAway())->toBeTrue();
});
