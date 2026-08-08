<?php

use App\Enums\FacilityStatus;
use App\Filament\Organization\Widgets\OfferChecklistWidget;
use App\Models\Organization;
use App\Models\OrganizationSport;
use App\Models\Service;
use App\Models\Space;
use App\Models\Sport;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * Sign a member in and put the panel on this organization.
 */
function panelFor(Organization $organization): User
{
    $member = User::factory()->create();
    $organization->addMember($member);

    test()->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('organization'));
    Filament::setTenant($organization);

    return $member;
}

test('a freshly approved account is told it has no public page yet', function () {
    // Approval creates the organization and nothing else. Until this widget
    // existed, the first screen said nothing about that.
    panelFor(Organization::factory()->create());

    Livewire::test(OfferChecklistWidget::class)
        ->assertSuccessful()
        ->assertSee('Nu ai publicat nimic încă');
});

test('the checklist offers all three kinds, whatever the organization already has', function () {
    panelFor(Organization::factory()->create());

    Livewire::test(OfferChecklistWidget::class)
        ->assertSee('Țin cursuri')
        ->assertSee('Am spații în care se poate intra')
        ->assertSee('Ofer servicii pe programare');
});

test('each row counts what is published rather than what was ticked', function () {
    $organization = Organization::factory()->create();
    OrganizationSport::factory()->for($organization)->create([
        'sport_id' => Sport::factory()->create()->getKey(),
    ]);

    panelFor($organization);

    Livewire::test(OfferChecklistWidget::class)
        ->assertSee('1 sport')
        // The other two are still open doors, not failures.
        ->assertSee('adaugă →');
});

test('publishing anything changes what the panel says about the account', function () {
    $organization = Organization::factory()->create();
    Service::factory()->for($organization)->create();

    panelFor($organization);

    Livewire::test(OfferChecklistWidget::class)
        ->assertDontSee('Nu ai publicat nimic încă')
        ->assertSee('Ce ești se citește din ce ai publicat');
});

test('the address comes first, because a space needs a place to be', function () {
    panelFor(Organization::factory()->create());

    Livewire::test(OfferChecklistWidget::class)
        ->assertSee('Spune unde ești');
});

test('a space counts towards the venue row even before moderation clears it', function () {
    // Deliberate: the panel reports what the operator did, and a space waiting
    // for review is done work. What moderation gates is the public listing.
    $organization = Organization::factory()->create();
    $presence = $organization->syncLocation([
        'county' => 'Brașov', 'city' => 'Brașov', 'address' => 'Str. Test 1', 'name' => 'Baza Test',
    ]);
    Space::factory()->for($presence, 'organizationLocation')->create([
        'location_id' => $presence->location_id,
        'status' => FacilityStatus::Pending,
    ]);

    panelFor($organization);

    Livewire::test(OfferChecklistWidget::class)->assertSee('1 spațiu');
});
