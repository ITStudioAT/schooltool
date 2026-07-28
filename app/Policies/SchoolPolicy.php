<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function view(User $user, School $school): bool
    {
        return $user->hasRole('super_admin')
            || (int) $user->school_id === (int) $school->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function update(User $user, School $school): bool
    {
        return $user->hasRole('super_admin')
            || ($user->hasRole('admin') && (int) $user->school_id === (int) $school->id);
    }

    public function delete(User $user, School $school): bool
    {
        return $user->hasRole('super_admin')
            && (int) $user->school_id !== (int) $school->id;
    }
}
