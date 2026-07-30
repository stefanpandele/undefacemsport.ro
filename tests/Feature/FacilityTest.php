<?php

use App\Enums\FacilityStatus;
use App\Filament\Admin\Resources\Facilities\FacilityResource as AdminFacilityResource;
use App\Filament\Admin\Resources\Facilities\Pages\ManageFacilities;
use App\Filament\Organization\Resources\Locations\LocationResource;
use App\Filament\Organization\Resources\Locations\Pages\ManageLocations;
use App\Models\County;
use App\Models\Facility;
use App\Models\Locality;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Sport;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('a seeded facility is approved, a club suggestion is not', function () {
    $organization = Organization::factory()->create();

    expect(Facility::factory()->create()->status)->toBe(FacilityStatus::Approved)
        ->and(Facility::factory()->pending($organization)->create())
        ->status->toBe(FacilityStatus::Pending)
        ->suggested_by_organization_id->toBe($organization->id);
});

test('the approved scope leaves out anything still waiting', function () {
    Facility::factory()->create(['name' => 'Parcare']);
    Facility::factory()->pending()->create(['name' => 'Saună']);

    expect(Facility::query()->approved()->pluck('name')->all())->toBe(['Parcare']);
});

test('a club sees approved facilities plus only its own suggestions', function () {
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();

    Facility::factory()->create(['name' => 'Parcare']);
    Facility::factory()->pending($organization)->create(['name' => 'Saună']);
    Facility::factory()->pending($other)->create(['name' => 'Solar']);

    $usable = Facility::constrainUsable(Facility::query(), $organization);

    expect($usable->pluck('name')->sort()->values()->all())
        ->toBe(['Parcare', 'Saună'])
        // Without a club, only the shared vocabulary.
        ->and(Facility::constrainUsable(Facility::query(), null)->pluck('name')->all())
        ->toBe(['Parcare']);
});

test('a pending facility stays off the public pages until approved', function () {
    $sport = Sport::factory()->create(['slug' => 'inot', 'name' => 'Înot']);
    $organization = Organization::factory()->create();

    $organizationLocation = $organization->syncLocation([
        'county' => 'Cluj',
        'city' => 'Cluj-Napoca',
        'address' => 'Str. A 1',
        'name' => 'Bazinul A',
    ], [$sport->id]);

    $approved = Facility::factory()->create(['name' => 'Parcare']);
    $waiting = Facility::factory()->pending($organization)->create(['name' => 'Saună']);
    $organizationLocation->location->facilities()->attach([$approved->id, $waiting->id]);

    $slug = Location::query()->value('slug');

    // Neither a filter on the explore page…
    $this->get('/explorare?oras=Cluj-Napoca')
        ->assertInertia(fn ($page) => $page
            ->has('facilities', 1)
            ->where('facilities.0.name', 'Parcare')
        );

    // …nor an amenity shown on the location itself.
    $this->get("/locatii/{$slug}")
        ->assertInertia(fn ($page) => $page
            ->has('location.facilities', 1)
            ->where('location.facilities.0.label', 'Parcare')
        );

    $waiting->update(['status' => FacilityStatus::Approved]);

    $this->get("/locatii/{$slug}")
        ->assertInertia(fn ($page) => $page->has('location.facilities', 2));
});

test('a facility proposed from a location lands pending and attached to it', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member);

    $address = ['county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Sala A'];
    $organizationLocation = $organization->syncLocation($address);

    $this->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('organization'));
    Filament::setTenant($organization);

    // What the location form's "create option" does: a new entry in the shared
    // vocabulary, which the save then attaches to this location with its proof.
    $facilityId = LocationResource::createSuggestedFacility(['name' => 'Caffe-bar', 'icon' => '☕'], $organization);

    LocationResource::persist($address + [
        'location' => ['lat' => 46.77, 'lng' => 23.59],
        'sports' => [],
        'new_facilities' => [$facilityId],
        'new_facility_photos' => [$facilityId => 'facilities/proof/bar.webp'],
    ], $organizationLocation);

    $facility = Facility::query()->findOrFail($facilityId);
    $attached = $organizationLocation->location->facilities()->whereKey($facilityId)->first();

    // Once in the facilities table…
    expect($facility->status)->toBe(FacilityStatus::Pending)
        ->and($facility->suggested_by_organization_id)->toBe($organization->id)
        // …and once on the location, carrying the proof photo.
        ->and($attached)->not->toBeNull()
        ->and($attached->pivot->photo_path)->toBe('facilities/proof/bar.webp');

    // Attached, but still not shown to visitors.
    $this->get('/locatii/'.$organizationLocation->location->slug)
        ->assertInertia(fn ($page) => $page->has('location.facilities', 0));
});

test('a club proposes a facility from inside the location edit modal', function () {
    Storage::fake('s3');

    $county = County::create(['name' => 'Cluj']);
    Locality::create(['county_id' => $county->id, 'name' => 'Cluj-Napoca']);

    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member);

    $organizationLocation = $organization->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Sala A',
    ]);

    // An amenity that already exists, to prove the uniqueness check runs against
    // the facilities table. Regression: inside an edit form Filament defaults
    // `unique()` to ignoring the current record — the OrganizationLocation — which made
    // it query `organization_location.id` and blow up with an unknown column.
    Facility::factory()->create(['name' => 'Parcare']);

    $this->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('organization'));
    Filament::setTenant($organization);

    $component = Livewire::test(ManageLocations::class)
        ->mountTableAction('edit', $organizationLocation);

    $component->callFormComponentAction('new_facilities', 'createOption', data: [
        'name' => 'Parcare',
        'proof_photo' => [UploadedFile::fake()->image('bar.jpg', 400, 400)],
    ], formName: 'mountedActionSchema0');

    expect(Facility::query()->where('name', 'Parcare')->count())->toBe(1);

    $component->callFormComponentAction('new_facilities', 'createOption', data: [
        'name' => 'Caffe-bar',
        'icon' => '☕',
        'proof_photo' => [UploadedFile::fake()->image('bar.jpg', 400, 400)],
    ], formName: 'mountedActionSchema0');

    $created = Facility::query()->firstWhere('name', 'Caffe-bar');

    expect($created)->not->toBeNull()
        ->and($created->status)->toBe(FacilityStatus::Pending)
        ->and($created->suggested_by_organization_id)->toBe($organization->id);

    // Attached the moment it is proposed, without saving the location form.
    // Regression: it used to wait for the save, so closing the form left the
    // admin a suggestion with no location and no photo to judge it by.
    $attached = $organizationLocation->location->facilities()->whereKey($created->id)->first();

    expect($attached)->not->toBeNull()
        ->and($attached->pivot->photo_path)->toEndWith('.webp');
});

test('a just-proposed facility keeps its name in the select', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member);

    $organizationLocation = $organization->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Sala A',
    ]);

    $locationId = $organizationLocation->location_id;
    $attached = Facility::factory()->pending($organization)->create(['name' => 'Caffe-bar']);
    $free = Facility::factory()->create(['name' => 'Parcare']);

    // Proposing attaches it straight away, which used to drop it from the
    // options — leaving the select showing a bare id instead of the name.
    $organizationLocation->location->facilities()->attach($attached);

    $this->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('organization'));
    Filament::setTenant($organization);

    $withoutSelection = LocationResource::addableFacilities($locationId);
    $withSelection = LocationResource::addableFacilities($locationId, [$attached->id]);

    expect($withoutSelection)->toBe([$free->id => 'Parcare'])
        ->and($withSelection)->toHaveKey($attached->id)
        ->and($withSelection[$attached->id])->toBe('Caffe-bar');
});

test('proposing is only offered once the location exists', function () {
    $county = County::create(['name' => 'Cluj']);
    Locality::create(['county_id' => $county->id, 'name' => 'Cluj-Napoca']);

    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member);

    $organizationLocation = $organization->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Sala A',
    ]);

    $this->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('organization'));
    Filament::setTenant($organization);

    // Creating a location: there is no place yet to attach a proposal to, so a
    // suggestion made here could only end up orphaned.
    Livewire::test(ManageLocations::class)
        ->mountAction('create')
        ->assertFormComponentActionHidden('new_facilities', 'createOption', formName: 'mountedActionSchema0');

    // Editing one that exists: proposing is available.
    Livewire::test(ManageLocations::class)
        ->mountTableAction('edit', $organizationLocation)
        ->assertFormComponentActionVisible('new_facilities', 'createOption', formName: 'mountedActionSchema0');
});

test('every location keeps its own proof, and the admin sees them all', function () {
    $organization = Organization::factory()->create();
    $facility = Facility::factory()->pending($organization)->create(['name' => 'Caffe-bar']);

    // The same amenity proven at three different halls: three photos, three
    // places. Regression: only the first one used to reach the admin.
    foreach (['Sala A', 'Sala B', 'Sala C'] as $index => $name) {
        $organizationLocation = $organization->syncLocation([
            'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. '.$name, 'name' => $name,
        ]);

        $organizationLocation->location->facilities()->attach($facility, [
            'photo_path' => "facilities/proof/{$index}.webp",
        ]);
    }

    $proofs = $facility->fresh()->proofPhotos();

    expect($proofs)->toHaveCount(3)
        ->and(array_column($proofs, 'location'))->toBe(['Sala A', 'Sala B', 'Sala C'])
        ->and($proofs[1]['url'])->toContain('1.webp');
});

test('the proof photo reaches the admin reviewing the suggestion', function () {
    $organization = Organization::factory()->create();
    $organizationLocation = $organization->syncLocation([
        'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'address' => 'Str. A 1', 'name' => 'Sala A',
    ]);

    $facility = Facility::factory()->pending($organization)->create(['name' => 'Caffe-bar']);
    $organizationLocation->location->facilities()->attach($facility, ['photo_path' => 'facilities/proof/bar.webp']);

    $url = $facility->fresh()->proofPhotoUrl();

    expect($url)->toContain('facilities/proof/bar.webp');
});

test('a facility with no proof photo does not pretend to have one', function () {
    $facility = Facility::factory()->create(['name' => 'Parcare']);

    // The seeded vocabulary predates the rule, so it simply has nothing to show.
    expect($facility->proofPhotoUrl())->toBeNull();
});

test('an admin approves a suggestion by editing it', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $facility = Facility::factory()->pending()->create(['name' => 'Saună', 'icon' => '🧖']);

    $this->actingAs($admin)
        ->get(AdminFacilityResource::getUrl(panel: 'admin'))
        ->assertSuccessful();

    Livewire::test(ManageFacilities::class)
        ->callTableAction('edit', $facility, data: [
            'name' => 'Saună',
            'icon' => '🧖',
            'status' => FacilityStatus::Approved->value,
            'sort_order' => 0,
        ])
        ->assertHasNoTableActionErrors();

    expect($facility->refresh()->status)->toBe(FacilityStatus::Approved);
});

test('the admin list offers status tabs that count what is waiting', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Facility::factory()->count(3)->create();
    Facility::factory()->pending()->create(['name' => 'Saună']);

    $this->actingAs($admin);

    $tabs = Livewire::test(ManageFacilities::class)->instance()->getTabs();

    expect(array_keys($tabs))->toBe(['all', 'pending', 'approved']);

    expect((int) $tabs['all']->getBadge())->toBe(4)
        ->and((int) $tabs['pending']->getBadge())->toBe(1)
        ->and((int) $tabs['approved']->getBadge())->toBe(3);
});

test('an admin creating a facility needs no proof photo', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin);

    Livewire::test(ManageFacilities::class)
        ->callAction('create', data: [
            'name' => 'Tribună',
            'icon' => '🪑',
            'status' => FacilityStatus::Approved->value,
            'sort_order' => 5,
        ])
        ->assertHasNoActionErrors();

    expect(Facility::query()->firstWhere('name', 'Tribună'))
        ->not->toBeNull()
        ->status->toBe(FacilityStatus::Approved);
});
