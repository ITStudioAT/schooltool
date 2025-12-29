<?php

namespace App\Services;

use App\Models\School;
use App\Models\TutoringOffer;
use App\Models\TutoringSubject;
use App\Notifications\StandardEmail;

use Illuminate\Support\Facades\Notification;

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


    public function sendOfferToMentor($offer)
    {
        $data = [];
        $data['student'] = $offer->user->last_name . ' ' . $offer->user->first_name . ' ( ' . $offer->user->schoolclass . ' )';
        $data['student_email'] = $offer->user->email;
        $data['subject'] = $offer->subject->short_name . ' (' . $offer->subject->long_name . ')';
        $data['offer'] = $offer;

        $school = $offer->school;

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/' . $school->logo),
            'subject' => 'Nachhilfe-Angebot wurde aktualisiert',
            'markdown' => 'mails.homepage.offerCreatedOrUpdated',
            'data' => $data,
        ];

        Notification::route('mail', $offer->email_mentor)->notify(new StandardEmail($mail));
    }
}
