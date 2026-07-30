<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminAccessSeeder extends Seeder
{
    /**
     * Seed the /admin RBAC: Shield-style permissions, a limited "admin_staff"
     * role scoped to the reserved platform team, and a limited admin user
     * (adminl@email.com) assigned to it. The platform super admin is handled by
     * the email allowlist (config/auth.php) and bypasses all of this.
     */
    public function run(): void
    {
        $teamId = config('auth.platform_team_id');

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($teamId);

        $permissions = [
            'view_any_organization', 'view_organization', 'create_organization', 'update_organization', 'delete_organization', 'delete_any_organization',
            'view_any_organization_application', 'view_organization_application', 'create_organization_application',
            'update_organization_application', 'delete_organization_application', 'delete_any_organization_application',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => 'admin_staff', 'guard_name' => 'web']);

        // Spatie does not set roles.team_id from the active team on create, so
        // pin it explicitly. Role, assignment and the admin middleware must all
        // share the same platform team, or Shield's role UI shows a mismatch.
        if ((int) $role->getAttribute('team_id') !== (int) $teamId) {
            DB::table(config('permission.table_names.roles'))
                ->where('id', $role->getKey())
                ->update(['team_id' => $teamId]);

            $registrar->forgetCachedPermissions();
            $role = Role::query()->whereKey($role->getKey())->firstOrFail();
        }

        // Limited scope: review club applications, read-only on clubs.
        $role->syncPermissions([
            'view_any_organization',
            'view_organization',
            'view_any_organization_application',
            'view_organization_application',
            'update_organization_application',
        ]);

        $admin = User::updateOrCreate(
            ['email' => 'adminl@email.com'],
            [
                'name' => 'Admin Limitat',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        $admin->syncRoles([$role]);

        $registrar->forgetCachedPermissions();
    }
}
