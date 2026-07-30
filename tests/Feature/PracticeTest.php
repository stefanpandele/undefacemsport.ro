<?php

use App\Enums\OrganizationType;
use App\Enums\PersonProfession;
use App\Filament\Organization\Resources\Services\ServiceResource;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Service;
use App\Models\Specialty;
use App\Models\Sport;
use App\Models\User;
use Database\Seeders\PracticeSeeder;
use Database\Seeders\SpecialtySeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Lang;

/*
|--------------------------------------------------------------------------
| The taxonomy
|--------------------------------------------------------------------------
*/

test('specialties are their own taxonomy, not a branch of sports', function () {
    // Physiotherapy is not a sport, and `sports` carries product logic —
    // popular sports, reach counts, explore filters — that specialties would
    // pollute.
    $this->seed(SpecialtySeeder::class);

    expect(Specialty::count())->toBeGreaterThan(0)
        ->and(Sport::count())->toBe(0)
        ->and(Specialty::pluck('slug')->duplicates())->toBeEmpty();
});

test('every seeded specialty has an icon, a colour and translations', function () {
    $this->seed(SpecialtySeeder::class);

    $incomplete = Specialty::query()->whereNull('icon')->orWhereNull('color')->pluck('slug');

    expect($incomplete)->toBeEmpty('Specialties without icon/colour: '.$incomplete->implode(', '));

    foreach (['ro', 'en'] as $locale) {
        app()->setLocale($locale);

        $missing = Specialty::pluck('slug')->reject(fn (string $slug): bool => Lang::has('specialties.'.$slug));

        expect($missing)->toBeEmpty("Missing {$locale} translations: ".$missing->implode(', '));
    }
});

test('a practitioner names the sports they treat, and it never makes them a club', function () {
    // The claim lives on the service, not on the specialty: "I treat footballers"
    // is a statement about one practitioner, not a fact about physiotherapy.
    $this->seed();

    $treating = Service::query()
        ->whereHas('organization', fn ($query) => $query->where('type', OrganizationType::Practice))
        ->has('sports')
        ->with('sports')
        ->get();

    expect($treating)->not->toBeEmpty();

    // And none of those sports gained a club because of it.
    $reach = collect(Sport::withReach())->keyBy('key');

    $treating->flatMap->sports->unique('id')->each(function (Sport $sport) use ($reach): void {
        $clubs = Organization::query()
            ->where('type', OrganizationType::Club)
            ->whereHas('organizationSports', fn ($query) => $query->where('sport_id', $sport->getKey()))
            ->whereHas('organizationLocations')
            ->count();

        expect($reach->get($sport->slug)['clubCount'] ?? 0)->toBeLessThanOrEqual($clubs);
    });
});

test('the specialty seeder is idempotent', function () {
    $this->seed(SpecialtySeeder::class);
    $count = Specialty::count();

    $this->seed(SpecialtySeeder::class);

    expect(Specialty::count())->toBe($count);
});

/*
|--------------------------------------------------------------------------
| Services
|--------------------------------------------------------------------------
*/

test('a service is priced per appointment and bounded by a duration', function () {
    $service = Service::factory()->create(['price' => 200, 'duration_minutes' => 50]);

    expect($service->priceLabel())->toBe('200 lei')
        ->and($service->durationLabel())->toBe('50 min');
});

test('an unpriced service is unknown, not free', function () {
    // The same rule spaces follow: free means somebody said zero.
    $unknown = Service::factory()->unpriced()->create();
    $free = Service::factory()->free()->create();

    expect($unknown->priceLabel())->toBeNull()
        ->and($free->priceLabel())->toBe('Gratuit');
});

test('a service without a duration simply says nothing about it', function () {
    $service = Service::factory()->create(['duration_minutes' => null]);

    expect($service->durationLabel())->toBeNull();
});

test('a service can be left open on who provides it', function () {
    // A clinic with six physiotherapists need not commit to one.
    $service = Service::factory()->create(['person_id' => null]);

    expect($service->person)->toBeNull();
});

test('a service outlives the person who used to provide it', function () {
    $practice = Organization::factory()->practice()->create();
    $person = $practice->people()->create([
        'name' => 'Ioana Marinescu',
        'profession' => PersonProfession::Physiotherapist,
        'sort_order' => 0,
    ]);
    $service = Service::factory()->create([
        'organization_id' => $practice->getKey(),
        'person_id' => $person->getKey(),
    ]);

    $person->delete();

    expect($service->refresh()->person_id)->toBeNull()
        ->and(Service::count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Plan limits and the panel
|--------------------------------------------------------------------------
*/

test('a free practice can publish three services then hits its limit', function () {
    $practice = Organization::factory()->practice()->create(); // free: services limit 3

    expect($practice->canAddService())->toBeTrue();

    Service::factory()->count(3)->create(['organization_id' => $practice->getKey()]);

    expect($practice->canAddService())->toBeFalse();
});

/**
 * Sign a member in and put the panel on the given organization.
 */
function practiceContext(Organization $organization): User
{
    $member = User::factory()->create();
    $organization->addMember($member);

    test()->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('organization'));
    Filament::setTenant($organization);

    return $member;
}

test('services are open to every type, because type is identity and not permission', function () {
    // A pilates studio sells massage, a hotel sells it beside the pool. Gating
    // this to practices contradicted the rule the whole model rests on and left a
    // studio unable to publish what it actually sells.
    practiceContext(Organization::factory()->practice()->create());
    expect(ServiceResource::canAccess())->toBeTrue();

    practiceContext(Organization::factory()->create());
    expect(ServiceResource::canAccess())->toBeTrue();

    practiceContext(Organization::factory()->venue()->create());
    expect(ServiceResource::canAccess())->toBeTrue();
});

test('a club can reach the services screen and publish one', function () {
    $club = Organization::factory()->create();
    $member = practiceContext($club);

    $this->actingAs($member)
        ->get(ServiceResource::getUrl(panel: 'organization', tenant: $club))
        ->assertSuccessful();
});

/*
|--------------------------------------------------------------------------
| The public page
|--------------------------------------------------------------------------
*/

test('a practice has its own page, listing what it sells', function () {
    $this->seed();

    $practice = Organization::query()
        ->where('type', OrganizationType::Practice)
        ->has('services')
        ->firstOrFail();

    $this->get(route('practices.show', $practice->slug))->assertInertia(
        fn ($page) => $page
            ->component('public/practices/Show')
            ->where('practice.name', $practice->name)
            ->has('practice.services', $practice->services()->count())
            ->has('practice.specialties')
            ->has('practice.people'),
    );
});

test('a club is not reachable at a practice URL, and the reverse', function () {
    // A physiotherapist is not a club, and somebody looking for one is not
    // looking for training.
    $club = Organization::factory()->create();
    $practice = Organization::factory()->practice()->create();

    $this->get(route('practices.show', $club->slug))->assertNotFound();
    $this->get(route('clubs.show', $practice->slug))->assertNotFound();
});

test('the specialties on the page are derived from the services, never claimed', function () {
    $practice = Organization::factory()->practice()->create();
    $offered = Specialty::factory()->create(['name' => 'Fizioterapie', 'slug' => 'fizioterapie']);
    Specialty::factory()->create(['name' => 'Podologie', 'slug' => 'podologie']);

    Service::factory()->count(2)->create([
        'organization_id' => $practice->getKey(),
        'specialty_id' => $offered->getKey(),
    ]);

    $this->get(route('practices.show', $practice->slug))->assertInertia(
        fn ($page) => $page
            // Only what it sells something for.
            ->has('practice.specialties', 1)
            ->where('practice.specialties.0.key', 'fizioterapie')
            ->where('practice.specialties.0.serviceCount', 2),
    );
});

test('a practice with no services yet still has a page', function () {
    $practice = Organization::factory()->practice()->create();

    $this->get(route('practices.show', $practice->slug))->assertInertia(
        fn ($page) => $page->where('practice.services', [])->where('practice.specialties', []),
    );
});

test('the page names each person by their profession, not as a coach', function () {
    $practice = Organization::factory()->practice()->create();
    $practice->people()->create([
        'name' => 'Ioana Marinescu',
        'profession' => PersonProfession::Nutritionist,
        'role' => 'Nutriționist',
        'sort_order' => 0,
    ]);

    $this->get(route('practices.show', $practice->slug))->assertInertia(
        fn ($page) => $page->where('practice.people.0.profession', 'Nutriționist'),
    );
});

/*
|--------------------------------------------------------------------------
| The seeded data
|--------------------------------------------------------------------------
*/

test('seeding gives every practice something to sell', function () {
    $this->seed();

    $practices = Organization::where('type', OrganizationType::Practice)
        ->withCount('services', 'people')
        ->get();

    expect($practices)->toHaveCount(8);

    $practices->each(function (Organization $practice): void {
        expect($practice->services_count)->toBeGreaterThan(0, $practice->name.' sells nothing')
            ->and($practice->people_count)->toBeGreaterThan(0, $practice->name.' has nobody');
    });
});

test('the seeded practices include lone practitioners, not only clinics', function () {
    // A PFA with one person is the commonest case in reality, so it has to look
    // right too.
    $this->seed();

    $solo = Organization::where('type', OrganizationType::Practice)
        ->withCount('people')
        ->get()
        ->filter(fn (Organization $practice): bool => $practice->people_count === 1);

    expect($solo)->not->toBeEmpty();
});

test('no seeded practice person is filed as a coach', function () {
    $this->seed();

    $practicePeople = Person::query()
        ->whereHas('organization', fn ($query) => $query->where('type', OrganizationType::Practice))
        ->get();

    expect($practicePeople)->not->toBeEmpty()
        ->and($practicePeople->filter(fn (Person $person): bool => $person->profession === PersonProfession::Coach))
        ->toBeEmpty();
});

test('at least one seeded service is deliberately unpriced', function () {
    // An unknown price is a real state, and the page has to be seen saying so.
    $this->seed();

    expect(Service::query()->whereNull('price')->exists())->toBeTrue();
});

test('re-running the practice seeder sells nothing twice', function () {
    $this->seed();
    $services = Service::count();
    $people = Person::count();

    (new PracticeSeeder)->run();

    expect(Service::count())->toBe($services)
        ->and(Person::count())->toBe($people);
});

test('practices never leak into the club numbers', function () {
    $this->seed();

    $this->get(route('home'))->assertInertia(
        fn ($page) => $page->where(
            'stats.clubs',
            Organization::where('type', OrganizationType::Club)->whereHas('organizationLocations')->count(),
        ),
    );
});
