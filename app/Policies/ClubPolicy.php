<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\User;

class ClubPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_club');
    }

    public function view(User $user, Club $club): bool
    {
        return $user->can('view_club');
    }

    public function create(User $user): bool
    {
        return $user->can('create_club');
    }

    public function update(User $user, Club $club): bool
    {
        return $user->can('update_club');
    }

    public function delete(User $user, Club $club): bool
    {
        return $user->can('delete_club');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_club');
    }
}
