<?php

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\OrganizationApplication;
use App\Models\User;
use Database\Seeders\AdminAccessSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    // Seed the /admin RBAC (permissions + admin_staff role + limited admin).
    $this->seed(AdminAccessSeeder::class);

    // Mirror the admin-panel middleware: pin the platform team context.
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('auth.platform_team_id'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function newApplication(): OrganizationApplication
{
    return OrganizationApplication::create([
        'name' => 'Aplicație Test',
        'type' => OrganizationType::Club,
        'fiscal_code' => 'RO'.fake()->unique()->numberBetween(1_000_000, 99_999_999),
        'contact_name' => 'Ion Test',
        'contact_email' => fake()->unique()->safeEmail(),
        'contact_phone' => '0700000000',
    ]);
}

test('the Shield permissions follow the model name, not the old club one', function () {
    // Renamed by migration as well as by the seeder: without the data migration a
    // production database would keep the old names, the seeder would create a
    // second parallel set, and a limited admin would silently lose the abilities
    // they had been granted.
    $role = Role::where('name', 'admin_staff')->firstOrFail();
    $granted = $role->permissions->pluck('name');

    expect($granted)->toContain('view_any_organization', 'view_organization', 'update_organization_application')
        ->and($granted)->not->toContain('view_any_club', 'view_club', 'update_club_application')
        ->and(Permission::query()->where('name', 'like', '%_club%')->exists())->toBeFalse();
});

test('the admin_staff role is scoped to the non-zero platform team', function () {
    $role = Role::where('name', 'admin_staff')->firstOrFail();

    expect(config('auth.platform_team_id'))->not->toBe(0)
        ->and((int) $role->team_id)->toBe(config('auth.platform_team_id'));
});

test('the limited admin has scoped admin access through Shield policies', function () {
    $adminl = User::where('email', 'adminl@email.com')->firstOrFail();

    expect($adminl->can('viewAny', Organization::class))->toBeTrue()
        ->and($adminl->can('deleteAny', Organization::class))->toBeFalse()
        ->and($adminl->can('delete', Organization::factory()->create()))->toBeFalse()
        ->and($adminl->can('viewAny', OrganizationApplication::class))->toBeTrue()
        ->and($adminl->can('update', newApplication()))->toBeTrue()
        ->and($adminl->can('delete', newApplication()))->toBeFalse();
});

test('a super admin bypasses every admin policy', function () {
    config()->set('auth.super_admins', ['boss@undefacemsport.ro']);
    $boss = User::factory()->create(['email' => 'boss@undefacemsport.ro']);

    expect($boss->can('deleteAny', Organization::class))->toBeTrue()
        ->and($boss->can('delete', Organization::factory()->create()))->toBeTrue()
        ->and($boss->can('delete', newApplication()))->toBeTrue();
});

test('a user without an admin role cannot touch admin resources', function () {
    $nobody = User::factory()->create();

    expect($nobody->can('viewAny', Organization::class))->toBeFalse()
        ->and($nobody->can('viewAny', OrganizationApplication::class))->toBeFalse()
        ->and($nobody->can('update', newApplication()))->toBeFalse();
});
