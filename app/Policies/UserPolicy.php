<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'user']);
    }

    public function view(User $user, User $targetUser): bool
    {
        if ((int) $user->id === (int) $targetUser->id) {
            return true;
        }

        return $user->hasAnyRole(['admin', 'register_admin', 'tutoring_admin', 'teacher'])
            && (int) $user->school_id === (int) $targetUser->school_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, User $targetUser): bool
    {
        return $this->canManage($user, $targetUser);
    }

    public function updateTutoringProfile(User $user, User $targetUser): bool
    {
        return $user->hasRole('tutoring_user')
            && (int) $user->id === (int) $targetUser->id
            && (int) $user->school_id === (int) $targetUser->school_id;
    }

    public function delete(User $user, User $targetUser): bool
    {
        return (int) $user->id !== (int) $targetUser->id
            && $this->canManage($user, $targetUser);
    }

    private function canManage(User $user, User $targetUser): bool
    {
        if (! $user->hasRole('admin')) {
            return false;
        }

        if ((int) $user->school_id !== (int) $targetUser->school_id) {
            return false;
        }

        return ! $targetUser->hasRole('super_admin')
            || $user->hasRole('super_admin');
    }
}
