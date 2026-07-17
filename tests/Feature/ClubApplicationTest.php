<?php

use App\Enums\ClubApplicationStatus;
use App\Models\ClubApplication;
use Illuminate\Support\Facades\Http;

function validApplicationPayload(array $overrides = []): array
{
    return array_merge([
        'club_name' => 'Clubul Sportiv Test',
        'fiscal_code' => 'RO12345678',
        'contact_name' => 'Ion Popescu',
        'contact_email' => 'ion@example.com',
        'contact_phone' => '0712345678',
    ], $overrides);
}

function fakeAnafResponse(array $overrides = []): array
{
    return [
        'found' => [
            array_replace_recursive([
                'date_generale' => [
                    'cui' => 12345678,
                    'denumire' => 'AQUA JUNIOR SRL',
                    'adresa' => 'MUNICIPIUL BRAȘOV, STR. LUNGĂ, NR.12',
                    'nrRegCom' => 'J08/1234/2020',
                ],
                'inregistrare_scop_Tva' => [
                    'scpTVA' => true,
                ],
                'adresa_sediu_social' => [
                    'sdenumire_Judet' => 'BRAȘOV',
                    'sdenumire_Localitate' => 'Mun. Brașov',
                ],
            ], $overrides),
        ],
        'notFound' => [],
    ];
}

function fakeAnafFound(array $overrides = []): void
{
    Http::fake([
        'webservicesp.anaf.ro/*' => Http::response(fakeAnafResponse($overrides)),
    ]);
}

function fakeAnafNotFound(): void
{
    Http::fake([
        'webservicesp.anaf.ro/*' => Http::response(['found' => [], 'notFound' => [12345678]]),
    ]);
}

beforeEach(function () {
    // Guarantees no test ever reaches the real ANAF registry, while leaving
    // Inertia's SSR round-trip untouched.
    Http::preventStrayRequests();
    Http::allowStrayRequests(['*__inertia_ssr*']);
});

it('shows the club application form', function () {
    $this->get(route('club-application.create'))
        ->assertOk();
});

it('stores a pending club application with company details resolved from anaf', function () {
    fakeAnafFound();

    $this->post(route('club-application.store'), validApplicationPayload())
        ->assertRedirect(route('club-application.create'));

    $application = ClubApplication::sole();

    expect($application->club_name)->toBe('Clubul Sportiv Test')
        ->and($application->fiscal_code)->toBe('RO12345678')
        ->and($application->company_name)->toBe('AQUA JUNIOR SRL')
        ->and($application->address)->toBe('MUNICIPIUL BRAȘOV, STR. LUNGĂ, NR.12')
        ->and($application->is_vat_payer)->toBeTrue()
        ->and($application->status)->toBe(ClubApplicationStatus::Pending)
        ->and($application->reviewed_at)->toBeNull();
});

it('strips the RO prefix before querying anaf', function () {
    fakeAnafFound();

    $this->post(route('club-application.store'), validApplicationPayload());

    Http::assertSent(fn ($request) => $request->data()[0]['cui'] === 12345678);
});

it('rejects an application whose fiscal code is unknown to anaf', function () {
    fakeAnafNotFound();

    $this->post(route('club-application.store'), validApplicationPayload())
        ->assertSessionHasErrors('fiscal_code');

    expect(ClubApplication::count())->toBe(0);
});

it('rejects an application when anaf is unreachable', function () {
    Http::fake([
        'webservicesp.anaf.ro/*' => Http::failedConnection(),
    ]);

    $this->post(route('club-application.store'), validApplicationPayload())
        ->assertSessionHasErrors('fiscal_code');

    expect(ClubApplication::count())->toBe(0);
});

it('requires the mandatory fields', function () {
    $this->post(route('club-application.store'), [])
        ->assertSessionHasErrors(['club_name', 'fiscal_code', 'contact_name', 'contact_email']);

    expect(ClubApplication::count())->toBe(0);
});

it('does not let the client dictate company details', function () {
    fakeAnafFound();

    $this->post(route('club-application.store'), validApplicationPayload([
        'status' => 'approved',
        'company_name' => 'Firma Inventată SRL',
        'is_vat_payer' => false,
    ]));

    $application = ClubApplication::sole();

    expect($application->status)->toBe(ClubApplicationStatus::Pending)
        ->and($application->company_name)->toBe('AQUA JUNIOR SRL')
        ->and($application->is_vat_payer)->toBeTrue();
});

it('looks up a company by fiscal code', function () {
    fakeAnafFound();

    $this->getJson(route('anaf.lookup', ['cui' => '12345678']))
        ->assertOk()
        ->assertJson([
            'company_name' => 'AQUA JUNIOR SRL',
            'address' => 'MUNICIPIUL BRAȘOV, STR. LUNGĂ, NR.12',
            'registration_number' => 'J08/1234/2020',
            'county' => 'BRAȘOV',
            'city' => 'Mun. Brașov',
            'is_vat_payer' => true,
        ]);
});

it('returns not found for an unknown fiscal code', function () {
    fakeAnafNotFound();

    $this->getJson(route('anaf.lookup', ['cui' => '12345678']))
        ->assertNotFound();
});

it('requires a cui when looking up a company', function () {
    $this->getJson(route('anaf.lookup'))
        ->assertJsonValidationErrors('cui');
});

it('caches a successful lookup so anaf is queried once per fiscal code', function () {
    fakeAnafFound();

    $this->getJson(route('anaf.lookup', ['cui' => '12345678']))->assertOk();
    $this->getJson(route('anaf.lookup', ['cui' => 'RO12345678']))->assertOk();

    Http::assertSentCount(1);
});
