<?php

namespace App\Services;

use App\Http\Resources\Tutoring\OfferRequestResource;
use App\Http\Resources\Tutoring\OfferResource;
use App\Models\School;
use App\Models\TutoringOffer;
use App\Models\TutoringOfferRequest;
use App\Models\TutoringSubject;
use App\Notifications\StandardEmail;
use Barryvdh\Debugbar\Facades\Debugbar;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class TutoringOfferService
{
    public function isCreatingPossible(int $schoolId, int $userId, array $data): array
    {
        return [
            'status' => true,
            'code' => 200,
            'message' => 'Speicherung möglich',
            'data' => $data,
        ];
    }

    public function create(int $schoolId, int $userId, array $data): TutoringOffer
    {

        $school = School::findOrFail($schoolId);
        $schoolTool = $school->schoolTool;

        // Wenn eine Sichtbarkeit für andere Schulen generell ausgeschlossen wird => visible_for_other_schools = false
        if (!$schoolTool->may_visible_for_other_schools) $data['visible_for_other_schools'] = false;


        $data['school_id'] = $schoolId;
        $data['user_id'] = $userId;

        $subject = TutoringSubject::findOrFail($data['subject_id']);
        $data['must_be_accepted'] = $subject->must_be_accepted;
        $data['is_active'] = ! $subject->must_be_accepted;

        $offer = TutoringOffer::create($data);

        if (! $data['must_be_accepted']) {
            $offer->accepted_at = now();
            $offer->save();
        }

        return $offer;
    }

    public function update(TutoringOffer $offer, array $data): TutoringOffer
    {

        $school = School::findOrFail($offer['school_id']);
        $schoolTool = $school->schoolTool;
        $subject = TutoringSubject::findOrFail($offer['subject_id']);

        // Wenn eine Sichtbarkeit für andere Schulen generell ausgeschlossen wird => visible_for_other_schools = false
        if (!$schoolTool->may_visible_for_other_schools) $data['visible_for_other_schools'] = false;

        // Ob die Offer akzeptiert werden muss, wird vom Subject aktuell festgelegt
        $offer->must_be_accepted = $subject->must_be_accepted;


        if ($offer->must_be_accepted) {
            $data['is_active'] = false;
        }

        $offer->update($data);

        if ($offer->must_be_accepted) {
            $offer->accepted_at = null;
            $offer->save();
        } elseif (! $offer->accepted_at) {
            $offer->accepted_at = now();
            $offer->save();
        }

        return $offer;
    }


    public function sendOfferToMentor(TutoringOffer $offer): void
    {
        $offer->token = Str::uuid();
        $offer->token_expires_at = Carbon::now()->addMinutes((int) config('schooltool.token_expire_time'));
        $offer->save();

        $school = $offer->school;
        $baseParams = http_build_query([
            'offer_id' => $offer->id,
            'token' => $offer->token,
            'email_mentor' => $offer->email_mentor,
        ]);

        $data = [
            'student' => "{$offer->user->last_name} {$offer->user->first_name} ( {$offer->user->schoolclass} )",
            'student_email' => $offer->user->email,
            'subject' => "{$offer->subject->short_name} ({$offer->subject->long_name})",
            'offer' => $offer,
            'url_confirm' => url("/homepage/tutoring/offer?action=confirm&{$baseParams}"),
            'url_refuse' => url("/homepage/tutoring/offer?action=refuse&{$baseParams}"),
            'url_login' => url('/admin/login'),
        ];

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

    public function offerConfirmRefuse(array $data): bool
    {
        $offer = TutoringOffer::findOrFail($data['offer_id']);

        if ($data['email_mentor'] !== $offer->email_mentor) {
            abort(403, 'E-Mail-Adresse des Tutors ist ungültig.');
        }

        $tokenExpired = Carbon::parse($offer->token_expires_at)->lt(now());
        if ($data['token'] !== $offer->token || $tokenExpired) {
            abort(403, 'Token ungültig oder abgelaufen.');
        }

        if ($data['action'] === 'confirm') {
            $offer->accepted_at = now();
            $offer->save();
        }

        return true;
    }

    public function sendConfirmRefuseEmail(string $action, int $offerId): void
    {
        $offer = TutoringOffer::with(['user', 'subject', 'school'])->findOrFail($offerId);
        $school = $offer->school;

        $emailSubject = $action === 'confirm'
            ? 'Nachhilfe-Angebot wurde bestätigt'
            : 'Nachhilfe-Angebot wurde abgelehnt';

        $offerArray = $offer->toArray();
        $offerArray['classes'] = collect(is_array($offerArray['classes']) ? $offerArray['classes'] : []);

        $data = [
            'student' => "{$offer->user->last_name} {$offer->user->first_name} ( {$offer->user->schoolclass} )",
            'student_email' => $offer->user->email,
            'subject' => "{$offer->subject->short_name} ({$offer->subject->long_name})",
            'offer' => $offerArray,
        ];

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/' . $school->logo),
            'subject' => $emailSubject,
            'markdown' => 'mails.tutoring.offerConfirmedOrRefused',
            'data' => $data,
        ];

        Notification::route('mail', $offer->user->email)->notify(new StandardEmail($mail));
    }

    public function sendOfferRequest(int $userId, int $offerId, string $message): array
    {
        $offer = TutoringOffer::with(['school', 'subject', 'requests'])->findOrFail($offerId);

        $offerRequest = TutoringOfferRequest::where('school_id', $offer->school_id)
            ->where('offer_id', $offer->id)
            ->where('from_user_id', $userId)
            ->where('to_user_id', $offer->user_id)
            ->first();

        if (! $offerRequest) {
            $offerRequest = TutoringOfferRequest::create([
                'school_id' => $offer->school_id,
                'offer_id' => $offer->id,
                'from_user_id' => $userId,
                'to_user_id' => $offer->user_id,
                'message' => $message,
                'is_serious' => true,
                'sent_at' => now(),
                'sent_count' => 1,
            ]);
            $status = 'NEW_REQUEST';
        } else {
            $offerRequest->sent_count++;
            $offerRequest->last_sent_at = now();
            $offerRequest->save();
            $status = 'EXISTING_REQUEST';
        }

        $offer->refresh();

        return [
            'status' => $status,
            'offer_request' => new OfferRequestResource($offerRequest),
            'offer' => new OfferResource($offer),
        ];
    }

    public function sendOfferRequestEmail($offerRequest, string $status): void
    {
        if (! $offerRequest instanceof TutoringOfferRequest) {
            $offerRequest = TutoringOfferRequest::findOrFail($offerRequest->id ?? $offerRequest['id']);
        }
        $offerRequest = $offerRequest->fresh();
        $school = $offerRequest->school;
        $user = $offerRequest->to_user;

        $offerRequest->token = Str::uuid();
        $offerRequest->token_expires_at = Carbon::now()->addMinutes((int) config('schooltool.token_expire_time'));
        $offerRequest->save();

        $emailSubject = $status === 'NEW_REQUEST'
            ? 'Neue Anfrage für Ihr Nachhilfe-Angebot'
            : 'Erinnerung: Anfrage für Ihr Nachhilfe-Angebot';

        $params = http_build_query([
            'email' => $user->email,
            'id' => $offerRequest->id,
            'token' => $offerRequest->token,
        ]);

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/' . $school->logo),
            'subject' => $emailSubject,
            'markdown' => 'mails.tutoring.offerRequest',
            'data' => ['url' => url("/homepage/tutoring/offer_request?{$params}")],
        ];

        Notification::route('mail', $user->email)->notify(new StandardEmail($mail));
    }


    public function sendOfferRequestStornoEmail($offerRequest): void
    {
        if (! $offerRequest instanceof TutoringOfferRequest) {
            $offerRequest = TutoringOfferRequest::findOrFail($offerRequest->id ?? $offerRequest['id']);
        }
        $offerRequest = $offerRequest->fresh();
        $school = $offerRequest->school;
        $user = $offerRequest->to_user;

        $offerRequest->token = Str::uuid();
        $offerRequest->token_expires_at = Carbon::now()->addMinutes((int) config('schooltool.token_expire_time'));
        $offerRequest->save();

        $emailSubject = 'Storno einer Anfrage';


        $params = http_build_query([
            'school' => $school->short_name,
        ]);

        // http://localhost:8000/homepage/tutoring_overview/?school=ABG-SB

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/' . $school->logo),
            'subject' => $emailSubject,
            'markdown' => 'mails.tutoring.offerRequestStorno',
            'data' => ['url' => url("/homepage/tutoring_overview/?{$params}")],
        ];

        Notification::route('mail', $user->email)->notify(new StandardEmail($mail));
    }

    public function getUserFromOfferRequest(string $email, int $offerRequestId, string $token)
    {
        $offerRequest = TutoringOfferRequest::find($offerRequestId);

        if (! $offerRequest) {
            return null;
        }
        if ($offerRequest->token !== $token) {
            return null;
        }
        if ($offerRequest->token_expires_at < now()) {
            return null;
        }
        if ($offerRequest->to_user->email !== $email) {
            return null;
        }

        return $offerRequest->to_user;
    }
}
