<?php

namespace App\Services;

use App\Models\TutoringOffer;
use App\Models\TutoringSubject;

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
            // NotIfication an Tutor
        } else {
            $data['is_active'] = true;
            $data['accepted_at'] = now();
        }




        $offer = TutoringOffer::create($data);
        return $offer;
    }
}
