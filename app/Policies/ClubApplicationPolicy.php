<?php

namespace App\Policies;

use App\Models\ClubApplication;
use App\Models\User;

class ClubApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_club_application');
    }

    public function view(User $user, ClubApplication $application): bool
    {
        return $user->can('view_club_application');
    }

    public function create(User $user): bool
    {
        return $user->can('create_club_application');
    }

    public function update(User $user, ClubApplication $application): bool
    {
        return $user->can('update_club_application');
    }

    public function delete(User $user, ClubApplication $application): bool
    {
        return $user->can('delete_club_application');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_club_application');
    }
}
