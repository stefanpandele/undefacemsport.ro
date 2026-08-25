<?php

use App\Filament\Organization\Resources\OrganizationSports\OrganizationSportResource;
use App\Filament\Organization\Resources\OrganizationSports\Pages\ManageOrganizationSports;
use App\Models\Organization;
use App\Models\OrganizationSport;
use App\Models\Sport;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
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

test('a club sport carries a private-sessions flag, benefits and a gallery — and nothing about who it teaches', function () {
    $organization = Organization::factory()->create();
    $organizationSport = $organization->organizationSports()->create([
        'sport_id' => Sport::factory()->create()->id,
        'offers_private_sessions' => true,
    ]);

    $organizationSport->benefits()->create(['icon' => '🏅', 'label' => 'Licențiat FR Natație']);
    $organizationSport->images()->create(['path' => 'club-sports/gallery/1.jpg', 'collection' => 'gallery']);
    $organizationSport->images()->create(['path' => 'club-sports/gallery/2.jpg', 'collection' => 'cover']);

    $organizationSport->refresh();

    expect($organizationSport->offers_private_sessions)->toBeTrue()
        ->and($organizationSport->benefits->first()->label)->toBe('Licențiat FR Natație')
        ->and($organizationSport->images)->toHaveCount(2)
        ->and($organizationSport->galleryImages)->toHaveCount(1) // only the gallery-collection image
        // Groups and levels vary by address, so the club-wide row must not be
        // able to claim them at all.
        ->and(method_exists($organizationSport, 'ageGroups'))->toBeFalse()
        ->and(method_exists($organizationSport, 'levels'))->toBeFalse();
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
