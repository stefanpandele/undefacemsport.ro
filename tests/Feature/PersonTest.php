<?php

use App\Filament\Organization\Resources\People\Pages\ManagePeople;
use App\Filament\Organization\Resources\People\PersonResource;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Sport;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('a person belongs to a club and teaches sports', function () {
    $organization = Organization::factory()->create();
    $sports = Sport::factory()->count(2)->create();

    $person = $organization->people()->create([
        'name' => 'Andrei Popescu',
        'role' => 'Antrenor principal',
        'is_primary' => true,
    ]);

    // One to one in the first sport, groups only in the second: the claim is
    // about this person at this sport, not about the person.
    $person->sports()->attach($sports->first(), ['offers_private_sessions' => true]);
    $person->sports()->attach($sports->last());

    expect($person->organization->is($organization))->toBeTrue()
        ->and($person->sports)->toHaveCount(2)
        ->and($person->offersPrivateSessionsIn($sports->first()->getKey()))->toBeTrue()
        ->and($person->offersPrivateSessionsIn($sports->last()->getKey()))->toBeFalse()
        ->and($person->is_primary)->toBeTrue();
});

test('a club exposes its people', function () {
    $organization = Organization::factory()->create();
    Person::factory()->count(3)->create(['organization_id' => $organization->id]);

    expect($organization->people)->toHaveCount(3);
});

test('a club member can open the people page without error', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member);

    $this->actingAs($member)
        ->get(PersonResource::getUrl(panel: 'organization', tenant: $organization))
        ->assertSuccessful();
});

test('the panel asks about 1:1 per sport and writes it to the pair', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->pro()->create();
    $organization->addMember($member);

    $swimming = Sport::factory()->create(['name' => 'Înot']);
    $basketball = Sport::factory()->create(['name' => 'Baschet']);
    $organization->declareSports([$swimming->getKey(), $basketball->getKey()]);

    $person = $organization->people()->create(['name' => 'Andrei Popescu']);
    $person->sports()->attach([$swimming->getKey(), $basketball->getKey()]);

    $this->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('organization'));
    Filament::setTenant($organization);

    Livewire::test(ManagePeople::class)
        ->mountTableAction('edit', $person)
        ->set('mountedActions.0.data.private_session_sports', [$swimming->getKey()])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($person->refresh()->sports)->toHaveCount(2)
        ->and($person->offersPrivateSessionsIn($swimming->getKey()))->toBeTrue()
        ->and($person->offersPrivateSessionsIn($basketball->getKey()))->toBeFalse();
});

test('unticking 1:1 for a sport takes the claim off that sport alone', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->pro()->create();
    $organization->addMember($member);

    $swimming = Sport::factory()->create(['name' => 'Înot']);
    $basketball = Sport::factory()->create(['name' => 'Baschet']);
    $organization->declareSports([$swimming->getKey(), $basketball->getKey()]);

    $person = $organization->people()->create(['name' => 'Andrei Popescu']);
    $person->sports()->attach($swimming, ['offers_private_sessions' => true]);
    $person->sports()->attach($basketball, ['offers_private_sessions' => true]);

    $this->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('organization'));
    Filament::setTenant($organization);

    Livewire::test(ManagePeople::class)
        ->mountTableAction('edit', $person)
        ->set('mountedActions.0.data.private_session_sports', [$basketball->getKey()])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($person->refresh()->offersPrivateSessionsIn($swimming->getKey()))->toBeFalse()
        ->and($person->offersPrivateSessionsIn($basketball->getKey()))->toBeTrue();
});

test('marking somebody as representing the organization takes it off the others', function () {
    $organization = Organization::factory()->create();

    $first = $organization->people()->create(['name' => 'Andrei Popescu', 'is_primary' => true]);
    $second = $organization->people()->create(['name' => 'Ana Ionescu', 'is_primary' => true]);

    expect($first->refresh()->is_primary)->toBeFalse()
        ->and($second->refresh()->is_primary)->toBeTrue();

    // And back again, from the panel or anywhere else.
    $first->update(['is_primary' => true]);

    expect($first->refresh()->is_primary)->toBeTrue()
        ->and($second->refresh()->is_primary)->toBeFalse();
});

test('the mark stops at the organization it was made in', function () {
    $ours = Organization::factory()->create();
    $theirs = Organization::factory()->create();

    $mine = $ours->people()->create(['name' => 'Andrei Popescu', 'is_primary' => true]);
    $theirs->people()->create(['name' => 'Ana Ionescu', 'is_primary' => true]);

    expect($mine->refresh()->is_primary)->toBeTrue();
});

test('the club page speaks through whoever represents it', function () {
    $organization = Organization::factory()->create(['slug' => 'cs-delfinul']);
    $sport = Sport::factory()->create();
    $organization->declareSports([$sport->getKey()]);

    $organization->people()->create(['name' => 'Andrei Popescu', 'role' => 'Antrenor principal']);
    $organization->people()->create(['name' => 'Ana Ionescu', 'role' => 'Președinte', 'is_primary' => true]);

    $this->get('/la/cs-delfinul')->assertInertia(
        fn ($page) => $page->where('organization.representative', 'Ana Ionescu, Președinte'),
    );
});

test('somebody who teaches nothing is never asked about 1:1', function () {
    // A club has a reception and an office as well as a poolside, and asking
    // the person at the desk which sports they give individual lessons in is
    // asking a question with no answer.
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member);
    $organization->declareSports([Sport::factory()->create()->getKey()]);

    $reception = $organization->people()->create(['name' => 'Maria Ene', 'role' => 'Recepție']);

    $this->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('organization'));
    Filament::setTenant($organization);

    Livewire::test(ManagePeople::class)
        ->mountTableAction('edit', $reception)
        ->assertFormFieldHidden('private_session_sports')
        // And it comes back the moment somebody says they teach something.
        ->set('mountedActions.0.data.sports', [$organization->organizationSports()->value('sport_id')])
        ->assertFormFieldVisible('private_session_sports');
});

test('a person with no sports is saved and shown without one', function () {
    $organization = Organization::factory()->create(['slug' => 'cs-delfinul']);
    $organization->declareSports([Sport::factory()->create()->getKey()]);

    $reception = $organization->people()->create(['name' => 'Maria Ene', 'role' => 'Recepție']);

    expect($reception->sports)->toBeEmpty()
        ->and($reception->privateSessionSportIds())->toBe([]);

    $this->get('/la/cs-delfinul')->assertInertia(function ($page) {
        $person = collect($page->toArray()['props']['organization']['people'])->firstWhere('name', 'Maria Ene');

        expect($person['role'])->toBe('Recepție')
            ->and($person['sportLabel'])->toBe('')
            ->and($person['solo'])->toBeFalse();

        return $page;
    });
});
