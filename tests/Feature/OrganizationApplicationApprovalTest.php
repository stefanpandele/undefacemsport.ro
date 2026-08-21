<?php

use App\Enums\OrganizationApplicationStatus;
use App\Enums\Plan;
use App\Filament\Admin\Resources\OrganizationApplications\Pages\ListOrganizationApplications;
use App\Models\Organization;
use App\Models\OrganizationApplication;
use App\Models\User;
use App\Notifications\OrganizationApplicationRejected;
use App\Notifications\OrganizationApproved;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function pendingApplication(array $overrides = []): OrganizationApplication
{
    return OrganizationApplication::factory()->create(array_merge([
        'name' => 'Baza Sportivă Olimpia',
        'company_name' => 'OLIMPIA SPORT SRL',
        'fiscal_code' => 'RO12345678',
        'is_vat_payer' => true,
        'address' => 'Str. Lungă 12',
        'county' => 'Brașov',
        'city' => 'Brașov',
        'status' => OrganizationApplicationStatus::Pending,
    ], $overrides));
}

it('creates the organization from the request when approved', function () {
    $application = pendingApplication();
    $reviewer = User::factory()->create();

    $organization = $application->approve($reviewer);

    expect($organization->name)->toBe('Baza Sportivă Olimpia')
        ->and($organization->company_name)->toBe('OLIMPIA SPORT SRL')
        ->and($organization->fiscal_code)->toBe('RO12345678')
        ->and($organization->is_vat_payer)->toBeTrue()
        ->and($organization->address)->toBe('Str. Lungă 12')
        ->and($organization->county)->toBe('Brașov')
        ->and($organization->city)->toBe('Brașov');
});

it('opens the organization on the free plan, with the contact holding it', function () {
    $organization = pendingApplication()->approve(User::factory()->create());

    expect($organization->plan)->toBe(Plan::Free)
        ->and($organization->owner_user_id)->not->toBeNull();
});

it('records who decided and what the decision produced', function () {
    $application = pendingApplication();
    $reviewer = User::factory()->create();

    $organization = $application->approve($reviewer);

    expect($application->status)->toBe(OrganizationApplicationStatus::Approved)
        ->and($application->organization_id)->toBe($organization->getKey())
        ->and($application->reviewed_by)->toBe($reviewer->getKey())
        ->and($application->reviewed_at)->not->toBeNull()
        ->and($application->rejection_reason)->toBeNull();
});

it('gives the organization a slug free to take', function () {
    Organization::factory()->create(['name' => 'Olimpia', 'slug' => 'olimpia']);

    $organization = pendingApplication(['name' => 'Olimpia', 'city' => 'Brașov'])
        ->approve(User::factory()->create());

    expect($organization->slug)->toBe('olimpia-brasov');
});

it('refuses to approve the same request twice', function () {
    $application = pendingApplication();
    $reviewer = User::factory()->create();

    $application->approve($reviewer);

    expect(fn () => $application->approve($reviewer))
        ->toThrow(DomainException::class);

    expect(Organization::count())->toBe(1);
});

it('refuses to approve a request whose fiscal code is already taken', function () {
    Organization::factory()->create(['fiscal_code' => 'RO12345678']);

    $application = pendingApplication(['fiscal_code' => 'RO12345678']);

    expect(fn () => $application->approve(User::factory()->create()))
        ->toThrow(DomainException::class);

    expect($application->fresh()->status)->toBe(OrganizationApplicationStatus::Pending)
        ->and(Organization::count())->toBe(1);
});

it('keeps the reason when a request is rejected', function () {
    $application = pendingApplication();
    $reviewer = User::factory()->create();

    $application->reject($reviewer, 'CUI-ul aparține altei firme decât cea din cerere.');

    expect($application->status)->toBe(OrganizationApplicationStatus::Rejected)
        ->and($application->rejection_reason)->toBe('CUI-ul aparține altei firme decât cea din cerere.')
        ->and($application->reviewed_by)->toBe($reviewer->getKey())
        ->and($application->reviewed_at)->not->toBeNull();
});

it('creates no organization when a request is rejected', function () {
    pendingApplication()->reject(User::factory()->create(), 'Date incomplete.');

    expect(Organization::count())->toBe(0);
});

it('reports whether a request is still waiting', function () {
    $application = pendingApplication();

    expect($application->isPending())->toBeTrue();

    $application->reject(User::factory()->create(), 'Date incomplete.');

    expect($application->isPending())->toBeFalse();
});

describe('the admin queue', function () {
    beforeEach(function () {
        config()->set('auth.super_admins', ['boss@undefacemsport.ro']);
        $this->admin = User::factory()->create(['email' => 'boss@undefacemsport.ro', 'is_admin' => true]);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    });

    it('opens on the pending tab and separates the three states', function () {
        $pending = pendingApplication();
        $approved = pendingApplication(['fiscal_code' => 'RO22222222']);
        $rejected = pendingApplication(['fiscal_code' => 'RO33333333']);

        $approved->approve($this->admin);
        $rejected->reject($this->admin, 'Date incomplete.');

        $page = Livewire::test(ListOrganizationApplications::class);

        expect(array_keys($page->instance()->getTabs()))->toBe(['pending', 'approved', 'rejected']);

        $page->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$approved, $rejected]);

        $page->set('activeTab', 'approved')
            ->assertCanSeeTableRecords([$approved])
            ->assertCanNotSeeTableRecords([$pending, $rejected]);

        $page->set('activeTab', 'rejected')
            ->assertCanSeeTableRecords([$rejected])
            ->assertCanNotSeeTableRecords([$pending, $approved]);
    });

    it('creates the organization from the approve action', function () {
        $application = pendingApplication();

        Livewire::test(ListOrganizationApplications::class)
            ->callTableAction('approve', $application)
            ->assertHasNoTableActionErrors();

        expect(Organization::query()->where('fiscal_code', 'RO12345678')->exists())->toBeTrue()
            ->and($application->fresh()->status)->toBe(OrganizationApplicationStatus::Approved);
    });

    it('will not reject without a reason', function () {
        $application = pendingApplication();

        Livewire::test(ListOrganizationApplications::class)
            ->callTableAction('reject', $application, ['rejection_reason' => ''])
            ->assertHasTableActionErrors(['rejection_reason']);

        expect($application->fresh()->status)->toBe(OrganizationApplicationStatus::Pending);
    });

    it('stores the reason given to the reject action', function () {
        $application = pendingApplication();

        Livewire::test(ListOrganizationApplications::class)
            ->callTableAction('reject', $application, ['rejection_reason' => 'CUI inexistent.'])
            ->assertHasNoTableActionErrors();

        expect($application->fresh())
            ->status->toBe(OrganizationApplicationStatus::Rejected)
            ->rejection_reason->toBe('CUI inexistent.');
    });

    it('offers neither decision on a request already reviewed', function () {
        $application = pendingApplication();
        $application->reject($this->admin, 'Date incomplete.');

        Livewire::test(ListOrganizationApplications::class)
            ->set('activeTab', 'rejected')
            ->assertTableActionHidden('approve', $application)
            ->assertTableActionHidden('reject', $application);
    });
});

describe('the owner account', function () {
    beforeEach(function () {
        Notification::fake();
    });

    it('creates the contact as owner, so somebody can actually get in', function () {
        $application = pendingApplication([
            'contact_name' => 'Andrei Popescu',
            'contact_email' => 'andrei@exemplu.ro',
        ]);

        $organization = $application->approve(User::factory()->create());

        $owner = User::query()->firstWhere('email', 'andrei@exemplu.ro');

        expect($owner)->not->toBeNull()
            ->and($owner->name)->toBe('Andrei Popescu')
            ->and($organization->fresh()->owner_user_id)->toBe($owner->getKey())
            ->and($owner->isMasterOf($organization))->toBeTrue();
    });

    it('sends the new owner a way to set a password', function () {
        $application = pendingApplication(['contact_email' => 'andrei@exemplu.ro']);

        $application->approve(User::factory()->create());

        $owner = User::query()->firstWhere('email', 'andrei@exemplu.ro');

        Notification::assertSentTo(
            $owner,
            OrganizationApproved::class,
            fn (OrganizationApproved $notification): bool => $notification->passwordToken !== null,
        );
    });

    it('reuses an account that already exists on that address', function () {
        // Months can pass between the request and the review, and the contact may
        // have signed up as a visitor meanwhile. A second row on the same address
        // would be a login nobody can use.
        $existing = User::factory()->create(['email' => 'andrei@exemplu.ro']);
        $application = pendingApplication(['contact_email' => 'andrei@exemplu.ro']);

        $organization = $application->approve(User::factory()->create());

        expect(User::query()->where('email', 'andrei@exemplu.ro')->count())->toBe(1)
            ->and($organization->fresh()->owner_user_id)->toBe($existing->getKey());

        // And they are not told to make a password they already have.
        Notification::assertSentTo(
            $existing,
            OrganizationApproved::class,
            fn (OrganizationApproved $notification): bool => $notification->passwordToken === null,
        );
    });

    it('tells the applicant why a request was refused', function () {
        $application = pendingApplication(['contact_email' => 'andrei@exemplu.ro']);

        $application->reject(User::factory()->create(), 'CUI-ul aparține altei firme.');

        Notification::assertSentOnDemand(
            OrganizationApplicationRejected::class,
            fn (OrganizationApplicationRejected $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'andrei@exemplu.ro'
                && $notification->reason === 'CUI-ul aparține altei firme.',
        );
    });

    it('creates no account when the request is refused', function () {
        pendingApplication(['contact_email' => 'andrei@exemplu.ro'])
            ->reject(User::factory()->create(), 'Date incomplete.');

        expect(User::query()->where('email', 'andrei@exemplu.ro')->exists())->toBeFalse();
    });

    it('creates no account when approval fails on the fiscal code', function () {
        Organization::factory()->create(['fiscal_code' => 'RO12345678']);
        $application = pendingApplication(['fiscal_code' => 'RO12345678', 'contact_email' => 'andrei@exemplu.ro']);

        expect(fn () => $application->approve(User::factory()->create()))->toThrow(DomainException::class);

        expect(User::query()->where('email', 'andrei@exemplu.ro')->exists())->toBeFalse();
        Notification::assertNothingSent();
    });
});
