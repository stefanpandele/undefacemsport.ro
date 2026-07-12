<?php

use App\Enums\ClubApplicationStatus;
use App\Models\ClubApplication;

it('shows the club application form', function () {
    $this->get(route('club-application.create'))
        ->assertOk();
});

it('stores a pending club application', function () {
    $this->post(route('club-application.store'), [
        'club_name' => 'Clubul Sportiv Test',
        'fiscal_code' => 'RO12345678',
        'contact_name' => 'Ion Popescu',
        'contact_role' => 'Președinte',
        'contact_email' => 'ion@example.com',
        'contact_phone' => '0712345678',
        'county' => 'Cluj',
        'city' => 'Cluj-Napoca',
        'message' => 'Dorim să ne înscriem clubul.',
    ])->assertRedirect(route('club-application.create'));

    $application = ClubApplication::sole();

    expect($application->club_name)->toBe('Clubul Sportiv Test')
        ->and($application->fiscal_code)->toBe('RO12345678')
        ->and($application->status)->toBe(ClubApplicationStatus::Pending)
        ->and($application->reviewed_at)->toBeNull()
        ->and($application->company_name)->toBeNull()
        ->and($application->is_vat_payer)->toBeNull();
});

it('requires the mandatory fields', function () {
    $this->post(route('club-application.store'), [])
        ->assertSessionHasErrors(['club_name', 'fiscal_code', 'contact_name', 'contact_email']);

    expect(ClubApplication::count())->toBe(0);
});

it('does not allow mass-assigning protected fields', function () {
    $this->post(route('club-application.store'), [
        'club_name' => 'Clubul Sportiv Test',
        'fiscal_code' => 'RO12345678',
        'contact_name' => 'Ion Popescu',
        'contact_email' => 'ion@example.com',
        'status' => 'approved',
        'company_name' => 'Falsă SRL',
        'is_vat_payer' => true,
    ]);

    $application = ClubApplication::sole();

    expect($application->status)->toBe(ClubApplicationStatus::Pending)
        ->and($application->company_name)->toBeNull()
        ->and($application->is_vat_payer)->toBeNull();
});
