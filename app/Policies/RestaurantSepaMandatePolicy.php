<?php

namespace App\Policies;

use App\Models\RestaurantSepaMandate;
use App\Models\User;

class RestaurantSepaMandatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'lunch_admin']);
    }

    public function viewSensitive(User $user, RestaurantSepaMandate $mandate): bool
    {
        return $this->viewAny($user)
            && (int) $mandate->school_id === (int) $user->school_id;
    }
}
