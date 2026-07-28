<?php

namespace App\Policies;

use App\Models\Register;
use App\Models\User;

class RegisterPolicy
{
    public function view(User $user, Register $register): bool
    {
        return $this->canManage($user, $register);
    }

    public function update(User $user, Register $register): bool
    {
        return $this->canManage($user, $register);
    }

    public function delete(User $user, Register $register): bool
    {
        return $this->canManage($user, $register);
    }

    private function canManage(User $user, Register $register): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->hasAnyRole(['admin', 'register_admin'])
            && (int) $user->school_id === (int) $register->school_id;
    }
}
