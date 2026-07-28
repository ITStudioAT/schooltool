<?php

namespace App\Policies;

use App\Models\TutoringOffer;
use App\Models\User;
use Illuminate\Support\Carbon;

class TutoringOfferPolicy
{
    public function update(User $user, TutoringOffer $tutoringOffer): bool
    {
        return $user->hasRole('tutoring_user')
            && (int) $user->id === (int) $tutoringOffer->user_id
            && (int) $user->school_id === (int) $tutoringOffer->school_id;
    }

    public function request(User $user, TutoringOffer $tutoringOffer): bool
    {
        $isVisibleToUser = (int) $user->school_id === (int) $tutoringOffer->school_id
            || (bool) $tutoringOffer->visible_for_other_schools;

        return $user->hasRole('tutoring_user')
            && (int) $user->id !== (int) $tutoringOffer->user_id
            && $isVisibleToUser
            && (bool) $tutoringOffer->is_active
            && $tutoringOffer->accepted_at !== null
            && (
                $tutoringOffer->active_until === null
                || Carbon::parse($tutoringOffer->active_until)->endOfDay()->isFuture()
            );
    }
}
