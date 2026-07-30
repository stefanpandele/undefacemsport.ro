<?php

namespace App\Policies;

use App\Models\OrganizationApplication;
use App\Models\User;

class OrganizationApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_organization_application');
    }

    public function view(User $user, OrganizationApplication $application): bool
    {
        return $user->can('view_organization_application');
    }

    public function create(User $user): bool
    {
        return $user->can('create_organization_application');
    }

    public function update(User $user, OrganizationApplication $application): bool
    {
        return $user->can('update_organization_application');
    }

    public function delete(User $user, OrganizationApplication $application): bool
    {
        return $user->can('delete_organization_application');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_organization_application');
    }
}
