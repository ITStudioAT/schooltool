<?php

namespace App\Services;

use App\Http\Resources\Tutoring\OfferRequestResource;
use App\Models\School;
use App\Models\TutoringOffer;
use App\Models\TutoringOfferRequest;
use App\Models\TutoringSubject;
use App\Models\User;
use App\Notifications\StandardEmail;
use Barryvdh\Debugbar\Facades\Debugbar;
use Carbon\Carbon;

use function Symfony\Component\Clock\now;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

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
        } else {
            if (!$offer->accepted_at) {
                $data['accepted_at'] = now();
            }
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

        $offer->token = Str::uuid();;
        $offer->token_expires_at = Carbon::now()->addMinutes((int) config('schooltool.token_expire_time'));
        $offer->save();

        $url_confirm = url('/homepage/tutoring/offer?action=confirm&offer_id=' . $offer->id . '&token=' . $offer->token . '&email_mentor=' . $offer->email_mentor);
        $url_refuse = url('/homepage/tutoring/offer/?action=refuse&offer_id=' . $offer->id . '&token=' . $offer->token . '&email_mentor=' . $offer->email_mentor);
        $url_login = url('/admin/login');

        $data['url_confirm'] = $url_confirm;
        $data['url_refuse'] = $url_refuse;
        $data['url_login'] = $url_login;


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

    public function offerConfirmRefuse($data)
    {
        /*
            'action' => 'required|string|in:confirm,refuse',
            'offer_id' => 'required|integer|exists:tutoring_offers,id',
            'token' => 'required|string',
            'email_mentor' => 'required|email',
        */

        $offer = TutoringOffer::findOrFail($data['offer_id']);
        $subject = $offer->subject;

        // Prüfen der Gültigkeit des E-Mail-Mentors
        if ($data['email_mentor'] != $offer->email_mentor) abort(403, 'E-Mail-Adresse des Tutors ist ungültig.');

        if ($data['token'] !== $offer->token || Carbon::parse($offer->token_expires_at)->lt(now())) {
            abort(403, 'Token ungültig oder abgelaufen.');
        }

        if ($data['action'] == 'confirm') {
            $offer->accepted_at = now();
            $offer->save();
        }

        return true;
    }

    public function sendConfirmRefuseEmail($action, $offer_id)
    {
        $offer = TutoringOffer::findOrFail($offer_id);
        $subject = $offer->subject;
        $school = $offer->school;

        if ($action == 'confirm') {
            $subject = 'Nachhilfe-Angebot wurde bestätigt';
        } else {
            $subject = 'Nachhilfe-Angebot wurde abgelehnt';
        }

        $data = [];
        $data['student'] = $offer->user->last_name . ' ' . $offer->user->first_name . ' ( ' . $offer->user->schoolclass . ' )';
        $data['student_email'] = $offer->user->email;
        $data['subject'] = $offer->subject->short_name . ' (' . $offer->subject->long_name . ')';
        $data['offer'] = $offer;

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/' . $school->logo),
            'subject' => $subject,
            'markdown' => 'mails.tutoring.offerConfirmedOrRefused',
            'data' => $data,
        ];

        Notification::route('mail', $offer->user->email)->notify(new StandardEmail($mail));
    }

    public function sendOfferRequest($user_id, $offer_id, $message)
    {

        $offer = TutoringOffer::findOrFail($offer_id);

        $offerRequest = TutoringOfferRequest::where('school_id', $offer->school_id)->where('offer_id', $offer->id)->where('from_user_id', $user_id)->where('to_user_id', $offer->user_id)->first();

        if (!$offerRequest) {
            // Anfrage wurde bisher nicht erstellt
            $offerRequest = TutoringOfferRequest::create([
                'school_id' => $offer->school_id,
                'offer_id' => $offer->id,
                'from_user_id' => $user_id,
                'to_user_id' => $offer->user_id,
                'message' => $message,
                'is_serious' => true,
                'sent_at' => now(),
            ]);
            $data = ['status' => 'NEW_REQUEST', 'offer_request' => new OfferRequestResource($offerRequest)];
        } else {
            // Anfrage wurde bereits erstellt
            $offerRequest->last_sent_at = now();
            $offerRequest->save();
            $data = ['status' => 'EXISTING_REQUEST', 'offer_request' => new OfferRequestResource($offerRequest)];
        }

        return $data;
    }

    public function sendOfferRequestEmail($offerRequest, $status)
    {

        $offerRequest = TutoringOfferRequest::findOrFail($offerRequest->id);

        $school = $offerRequest->school;
        $user = $offerRequest->to_user;

        $offerRequest->token = Str::uuid();;
        $offerRequest->token_expires_at = Carbon::now()->addMinutes((int) config('schooltool.token_expire_time'));
        $offerRequest->save();

        if ($status == 'NEW_REQUEST') {
            $subject = 'Neue Anfrage für Ihr Nachhilfe-Angebot';
        } else {
            $subject = 'Erinnerung: Anfrage für Ihr Nachhilfe-Angebot';
        }

        $data = [
            'url' => url('/homepage/tutoring/offer-request?id=' . $offerRequest->id . '&token=' . $offerRequest->token),
        ];

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/' . $school->logo),
            'subject' => $subject,
            'markdown' => 'mails.tutoring.offerRequest',
            'data' => $data,
        ];

        // Debugbar::info('Prepared email data:', $data);

        Notification::route('mail', $user->email)->notify(new StandardEmail($mail));
    }
}
