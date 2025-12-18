<?php

namespace App\Services;

use App\Models\TutoringOffer;
use App\Models\TutoringSubject;
use DebugBar\DebugBar;

class TutoringOfferService
{
    public function isCreatingPossible($school_id, $user_id, $data)
    {
        $answer = [
            'status' => true,
            'code' => 200,
            'message' => 'Speicherung möglich',
            'data' => $data
        ];
        return $answer;
    }

    public function create($school_id, $user_id, $data)
    {
        $data['school_id'] = $school_id;
        $data['user_id'] = $user_id;

        // Get Subject and check, if must_be_accepted is set
        $subject = TutoringSubject::findOrFail($data['subject_id']);
        $data['must_be_accepted'] = $subject->must_be_accepted;

        if ($data['must_be_accepted']) {
            $data['is_active'] = false;
            // TODO Notification an Tutor
        } else {
            $data['is_active'] = true;
            $data['accepted_at'] = now();
        }

        $offer = TutoringOffer::create($data);
        return $offer;
    }

    public function update($offer, $data)
    {
        if ($offer->must_be_accepted) {
            $data['accepted_at'] = null;
            $data['is_active'] = false;
        }

        $offer->update($data);



        return $offer;
    }
}
