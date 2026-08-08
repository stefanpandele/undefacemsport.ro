<?php

use App\Enums\LocationClaimStatus;
use App\Filament\Admin\Resources\LocationClaims\LocationClaimResource;
use App\Filament\Admin\Resources\LocationClaims\Pages\ManageLocationClaims;
use App\Filament\Organization\Resources\Locations\Pages\ManageLocations;
use App\Models\Location;
use App\Models\LocationClaim;
use App\Models\LocationCorrection;
use App\Models\LocationRedirect;
use App\Models\Organization;
use App\Models\OrganizationLocation;
use App\Models\Sport;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * A venue present at the location, inside the tenant panel.
 *
 * @return array{0: Organization, 1: OrganizationLocation}
 */
function venueAt(Location $location): array
{
    $venue = Organization::factory()->create();
    $member = User::factory()->create();
    $venue->addMember($member);

    $presence = OrganizationLocation::create([
        'organization_id' => $venue->getKey(),
        'location_id' => $location->getKey(),
    ]);

    test()->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('organization'));
    Filament::setTenant($venue);

    return [$venue, $presence];
}

test('a location starts owned by nobody', function () {
    // They are created as a side effect of a club saying it trains somewhere, and
    // that club has no claim to the record itself.
    $location = Location::factory()->create();

    expect($location->isClaimed())->toBeFalse()
        ->and($location->claimedByOrganization)->toBeNull();
});

test('an organization present at a place can ask to hold its pen', function () {
    $location = Location::factory()->create();
    [$venue, $presence] = venueAt($location);

    Livewire::test(ManageLocations::class)
        ->callAction(TestAction::make('claim')->table($presence), data: [
            'evidence' => 'Deținem baza din 2019.',
        ]);

    $claim = LocationClaim::query()->first();

    expect($claim)->not->toBeNull()
        ->and($claim->location_id)->toBe($location->getKey())
        ->and($claim->organization_id)->toBe($venue->getKey())
        ->and($claim->isPending())->toBeTrue()
        // Asking is not getting.
        ->and($location->refresh()->isClaimed())->toBeFalse();
});

test('asking twice is not more evidence', function () {
    $location = Location::factory()->create();
    [, $presence] = venueAt($location);

    LocationClaim::create([
        'location_id' => $location->getKey(),
        'organization_id' => $presence->organization_id,
        'evidence' => 'Prima cerere.',
    ]);

    Livewire::test(ManageLocations::class)
        ->assertActionHidden(TestAction::make('claim')->table($presence));
});

test('a place that already has an editor cannot be claimed again', function () {
    // A settled place is settled. A rival claim is a dispute, and a dispute is
    // not a form.
    $location = Location::factory()->create();
    [, $presence] = venueAt($location);

    $location->forceFill([
        'claimed_by_organization_id' => Organization::factory()->create()->getKey(),
        'claimed_at' => now(),
    ])->save();

    Livewire::test(ManageLocations::class)
        ->assertActionHidden(TestAction::make('claim')->table($presence));
});

test('approving hands over the place and refuses the rivals', function () {
    $location = Location::factory()->create();
    $winner = Organization::factory()->create();
    $rival = Organization::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);

    $claim = LocationClaim::create([
        'location_id' => $location->getKey(),
        'organization_id' => $winner->getKey(),
        'evidence' => 'Contract de administrare.',
    ]);
    $rivalClaim = LocationClaim::create([
        'location_id' => $location->getKey(),
        'organization_id' => $rival->getKey(),
        'evidence' => 'Și noi.',
    ]);

    $claim->approve($admin);

    expect($location->refresh()->isClaimedBy($winner))->toBeTrue()
        ->and($location->claimed_at)->not->toBeNull()
        ->and($claim->refresh()->status)->toBe(LocationClaimStatus::Approved)
        ->and($claim->reviewed_by)->toBe($admin->getKey())
        // Left open, a second approval would silently overwrite the first.
        ->and($rivalClaim->refresh()->status)->toBe(LocationClaimStatus::Rejected)
        ->and($rivalClaim->reviewed_by)->toBe($admin->getKey());
});

test('rejecting changes nothing about the place', function () {
    $location = Location::factory()->create();
    $claim = LocationClaim::factory()->create(['location_id' => $location->getKey()]);
    $admin = User::factory()->create(['is_admin' => true]);

    $claim->reject($admin);

    expect($location->refresh()->isClaimed())->toBeFalse()
        ->and($claim->refresh()->status)->toBe(LocationClaimStatus::Rejected);
});

test('an organization cannot approve its own claim by mass assignment', function () {
    $location = Location::factory()->create();
    $venue = Organization::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);

    $claim = LocationClaim::create([
        'location_id' => $location->getKey(),
        'organization_id' => $venue->getKey(),
        'evidence' => 'Noi.',
        'status' => LocationClaimStatus::Approved,
        'reviewed_by' => $admin->getKey(),
    ]);

    expect($claim->status)->toBe(LocationClaimStatus::Pending)
        ->and($claim->reviewed_by)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| What holding the pen actually means
|--------------------------------------------------------------------------
*/

test('the pen holder can rename the place, and the old URL keeps working', function () {
    $location = Location::factory()->create([
        'name' => 'Bazin fara nume bun',
        'county' => 'Cluj',
        'city' => 'Cluj-Napoca',
        'address' => 'Str. A 1',
    ]);
    $oldSlug = $location->slug;

    $venue = Organization::factory()->create();
    $venue->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Bazin fara nume bun',
    ]);

    $location->forceFill([
        'claimed_by_organization_id' => $venue->getKey(),
        'claimed_at' => now(),
    ])->save();

    $venue->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Bazinul Olimpic',
    ]);

    expect($location->refresh()->name)->toBe('Bazinul Olimpic')
        ->and($location->slug)->not->toBe($oldSlug)
        // An indexed URL is not the editor's to break.
        ->and(LocationRedirect::query()->where('slug', $oldSlug)->value('location_id'))->toBe($location->getKey());

    $this->get('/locatii/'.$oldSlug)->assertStatus(301);
});

test('everybody else still cannot rename a place a dozen clubs rely on', function () {
    $location = Location::factory()->create([
        'name' => 'Bazinul Olimpic',
        'county' => 'Cluj',
        'city' => 'Cluj-Napoca',
        'address' => 'Str. A 1',
    ]);

    $club = Organization::factory()->create();
    $club->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Cu totul alt nume',
    ]);

    expect($location->refresh()->name)->toBe('Bazinul Olimpic');
});

test('the correction flow disappears for the organization that can simply edit', function () {
    $location = Location::factory()->create();
    [$venue, $presence] = venueAt($location);

    Livewire::test(ManageLocations::class)
        ->assertActionVisible(TestAction::make('proposeCorrection')->table($presence));

    $location->forceFill([
        'claimed_by_organization_id' => $venue->getKey(),
        'claimed_at' => now(),
    ])->save();

    Livewire::test(ManageLocations::class)
        ->assertActionHidden(TestAction::make('proposeCorrection')->table($presence))
        ->assertActionHidden(TestAction::make('claim')->table($presence));
});

test('a club at the place keeps proposing corrections, and its own data', function () {
    // Handing the place over must not touch anybody's offer.
    $location = Location::factory()->create();
    $sport = Sport::factory()->create();
    $club = clubAt($location, $sport, 'CS Test');

    $venue = Organization::factory()->create();
    $location->forceFill([
        'claimed_by_organization_id' => $venue->getKey(),
        'claimed_at' => now(),
    ])->save();

    expect($club->organizationLocations()->count())->toBe(1)
        ->and($club->organizationLocations()->first()->sports)->toHaveCount(1);

    LocationCorrection::create([
        'location_id' => $location->getKey(),
        'organization_id' => $club->getKey(),
        'field' => 'name',
        'suggested_value' => 'Alt nume',
    ]);

    expect(LocationCorrection::count())->toBe(1);
});

test('a place outlives the company that held its pen', function () {
    $location = Location::factory()->create();
    $venue = Organization::factory()->create();

    $location->forceFill([
        'claimed_by_organization_id' => $venue->getKey(),
        'claimed_at' => now(),
    ])->save();

    $venue->delete();

    expect(Location::query()->whereKey($location->getKey())->exists())->toBeTrue()
        ->and($location->refresh()->isClaimed())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The review screen
|--------------------------------------------------------------------------
*/

test('an admin can review a claim from the panel', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $location = Location::factory()->create();
    $claim = LocationClaim::factory()->create(['location_id' => $location->getKey()]);

    $this->actingAs($admin)
        ->get(LocationClaimResource::getUrl('index', panel: 'admin'))
        ->assertSuccessful();

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ManageLocationClaims::class)
        ->callAction(TestAction::make('approve')->table($claim));

    expect($location->refresh()->claimed_by_organization_id)->toBe($claim->organization_id);
});

test('a club member cannot reach the claims screen', function () {
    $member = User::factory()->create();
    Organization::factory()->create()->addMember($member);

    $this->actingAs($member)
        ->get(LocationClaimResource::getUrl('index', panel: 'admin'))
        ->assertRedirect();
});
