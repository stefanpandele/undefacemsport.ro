<?php

use App\Models\Club;
use App\Models\ClubApplication;
use App\Models\User;
use Database\Seeders\AdminAccessSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    // Seed the /admin RBAC (permissions + admin_staff role + limited admin).
    $this->seed(AdminAccessSeeder::class);

    // Mirror the admin-panel middleware: pin the platform team context.
    app(PermissionRegistrar::class)->setPermissionsTeamId(config('auth.platform_team_id'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function newApplication(): ClubApplication
{
    return ClubApplication::create([
        'club_name' => 'Aplicație Test',
        'fiscal_code' => 'RO'.fake()->unique()->numberBetween(1_000_000, 99_999_999),
        'contact_name' => 'Ion Test',
        'contact_email' => fake()->unique()->safeEmail(),
        'contact_phone' => '0700000000',
    ]);
}

test('the admin_staff role is scoped to the non-zero platform team', function () {
    $role = Role::where('name', 'admin_staff')->firstOrFail();

    expect(config('auth.platform_team_id'))->not->toBe(0)
        ->and((int) $role->team_id)->toBe(config('auth.platform_team_id'));
});

test('the limited admin has scoped admin access through Shield policies', function () {
    $adminl = User::where('email', 'adminl@email.com')->firstOrFail();

    expect($adminl->can('viewAny', Club::class))->toBeTrue()
        ->and($adminl->can('deleteAny', Club::class))->toBeFalse()
        ->and($adminl->can('delete', Club::factory()->create()))->toBeFalse()
        ->and($adminl->can('viewAny', ClubApplication::class))->toBeTrue()
        ->and($adminl->can('update', newApplication()))->toBeTrue()
        ->and($adminl->can('delete', newApplication()))->toBeFalse();
});

test('a super admin bypasses every admin policy', function () {
    config()->set('auth.super_admins', ['boss@undefacemsport.ro']);
    $boss = User::factory()->create(['email' => 'boss@undefacemsport.ro']);

    expect($boss->can('deleteAny', Club::class))->toBeTrue()
        ->and($boss->can('delete', Club::factory()->create()))->toBeTrue()
        ->and($boss->can('delete', newApplication()))->toBeTrue();
});

test('a user without an admin role cannot touch admin resources', function () {
    $nobody = User::factory()->create();

    expect($nobody->can('viewAny', Club::class))->toBeFalse()
        ->and($nobody->can('viewAny', ClubApplication::class))->toBeFalse()
        ->and($nobody->can('update', newApplication()))->toBeFalse();
});
