<?php

namespace App\Policies;

use App\Models\TutoringOfferRequest;
use App\Models\User;

class TutoringOfferRequestPolicy
{
    public function markMailClicked(User $user, TutoringOfferRequest $tutoringOfferRequest): bool
    {
        return $user->hasRole('tutoring_user')
            && (int) $user->id === (int) $tutoringOfferRequest->to_user_id
            && (int) $user->school_id === (int) $tutoringOfferRequest->school_id;
    }
}
