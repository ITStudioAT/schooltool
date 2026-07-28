<?php

namespace App\Policies;

use App\Models\MaterialCard;
use App\Models\User;

class MaterialCardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'materials_admin', 'materials_moderator']);
    }

    public function view(User $user, MaterialCard $card): bool
    {
        return $this->ownsCard($user, $card);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, MaterialCard $card): bool
    {
        return $this->ownsCard($user, $card);
    }

    public function delete(User $user, MaterialCard $card): bool
    {
        return $this->ownsCard($user, $card);
    }

    private function ownsCard(User $user, MaterialCard $card): bool
    {
        return $this->viewAny($user)
            && (int) $card->user_id === (int) $user->id
            && (int) $card->school_id === (int) $user->school_id;
    }
}
