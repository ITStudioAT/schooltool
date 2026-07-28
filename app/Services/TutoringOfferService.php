<?php

namespace App\Services;

use App\Http\Resources\Tutoring\OfferRequestResource;
use App\Http\Resources\Tutoring\OfferResource;
use App\Models\School;
use App\Models\TutoringOffer;
use App\Models\TutoringOfferRequest;
use App\Models\TutoringSubject;
use App\Models\User;
use App\Notifications\StandardEmail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
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
        if (! $schoolTool->may_visible_for_other_schools) {
            $data['visible_for_other_schools'] = false;
        }

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
        if (! $schoolTool->may_visible_for_other_schools) {
            $data['visible_for_other_schools'] = false;
        }

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
        $offer->token = (string) Str::uuid();
        $offer->token_expires_at = Carbon::now()->addMinutes((int) config('schooltool.token_expire_time'));
        $offer->save();

        $school = $offer->school;
        $baseParams = [
            'offer_id' => $offer->id,
            'token' => $offer->token,
            'email_mentor' => $offer->email_mentor,
        ];

        $data = [
            'student' => "{$offer->user->last_name} {$offer->user->first_name} ( {$offer->user->schoolclass} )",
            'student_email' => $offer->user->email,
            'subject' => "{$offer->subject->short_name} ({$offer->subject->long_name})",
            'offer' => $offer,
            'url_confirm' => $this->offerDecisionUrl($offer, 'confirm', $baseParams),
            'url_refuse' => $this->offerDecisionUrl($offer, 'refuse', $baseParams),
            'url_login' => url('/admin/login'),
        ];

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/logos/'.$school->logo),
            'subject' => 'Nachhilfe-Angebot wurde erstellt/aktualisiert',
            'markdown' => 'mails.homepage.offerCreatedOrUpdated',
            'data' => $data,
        ];

        Notification::route('mail', EmailAliasResolver::resolveConfigured($offer->email_mentor))->notify(new StandardEmail($mail));
    }

    public function sendOfferDeletedToMentor(TutoringOffer $offer): void
    {
        $offer->token = (string) Str::uuid();
        $offer->token_expires_at = Carbon::now()->addMinutes((int) config('schooltool.token_expire_time'));
        $offer->save();

        $school = $offer->school;
        $baseParams = [
            'offer_id' => $offer->id,
            'token' => $offer->token,
            'email_mentor' => $offer->email_mentor,
        ];

        $data = [
            'student' => "{$offer->user->last_name} {$offer->user->first_name} ( {$offer->user->schoolclass} )",
            'student_email' => $offer->user->email,
            'subject' => "{$offer->subject->short_name} ({$offer->subject->long_name})",
            'offer' => $offer,
            'url_confirm' => $this->offerDecisionUrl($offer, 'confirm', $baseParams),
            'url_refuse' => $this->offerDecisionUrl($offer, 'refuse', $baseParams),
            'url_login' => url('/admin/login'),
            'email_mentor' => $offer->email_mentor,
        ];

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/logos/'.$school->logo),
            'subject' => 'Nachhilfe-Angebot wurde gelöscht',
            'markdown' => 'mails.tutoring.offerDeleted',
            'data' => $data,
        ];

        Notification::route('mail', EmailAliasResolver::resolveConfigured($offer->email_mentor))->notify(new StandardEmail($mail));
    }

    public function sendOfferDeletedToStudent(TutoringOffer $offer): void
    {
        $offer->token = (string) Str::uuid();
        $offer->token_expires_at = Carbon::now()->addMinutes((int) config('schooltool.token_expire_time'));
        $offer->save();

        $school = $offer->school;
        $user = $offer->user;

        $data = [
            'student' => "{$offer->user->last_name} {$offer->user->first_name} ( {$offer->user->schoolclass} )",
            'student_email' => $offer->user->email,
            'subject' => "{$offer->subject->short_name} ({$offer->subject->long_name})",
            'offer' => $offer,
            'email_mentor' => $offer->email_mentor,
        ];

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/logos/'.$school->logo),
            'subject' => 'Nachhilfe-Angebot wurde von Lehrkraft gelöscht',
            'markdown' => 'mails.tutoring.offerDeleted',
            'data' => $data,
        ];

        Notification::route('mail', EmailAliasResolver::resolveConfigured($user->email))->notify(new StandardEmail($mail));
    }

    public function offerConfirmRefuse(array $data): bool
    {
        return DB::transaction(function () use ($data): bool {
            $offer = TutoringOffer::query()
                ->whereKey((int) $data['offer_id'])
                ->where('token', (string) $data['token'])
                ->where('token_expires_at', '>=', now())
                ->lockForUpdate()
                ->first();

            if (
                ! $offer instanceof TutoringOffer
                || ! hash_equals(
                    mb_strtolower((string) $offer->email_mentor),
                    mb_strtolower((string) $data['email_mentor']),
                )
            ) {
                abort(403, 'Token ungültig oder abgelaufen.');
            }

            if ($data['action'] === 'confirm') {
                $offer->accepted_at = now();
            }

            $offer->token = null;
            $offer->token_expires_at = null;
            $offer->save();

            return true;
        });
    }

    /**
     * @param  array{offer_id: int, token: string, email_mentor: string}  $data
     */
    public function offerForDecision(array $data): ?TutoringOffer
    {
        $offer = TutoringOffer::query()
            ->whereKey((int) $data['offer_id'])
            ->where('token', (string) $data['token'])
            ->where('token_expires_at', '>=', now())
            ->first();

        if (! $offer instanceof TutoringOffer) {
            return null;
        }

        return hash_equals(
            mb_strtolower((string) $offer->email_mentor),
            mb_strtolower((string) $data['email_mentor']),
        ) ? $offer : null;
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
            'logo' => asset('/storage/images/logos/'.$school->logo),
            'subject' => $emailSubject,
            'markdown' => 'mails.tutoring.offerConfirmedOrRefused',
            'data' => $data,
        ];

        Notification::route('mail', EmailAliasResolver::resolveConfigured($offer->user->email))->notify(new StandardEmail($mail));
    }

    public function sendOfferRequest(User $user, TutoringOffer $offer, string $message): array
    {
        $offer->loadMissing(['school', 'subject', 'requests']);

        $offerRequest = TutoringOfferRequest::where('school_id', $offer->school_id)
            ->where('offer_id', $offer->id)
            ->where('from_user_id', $user->id)
            ->where('to_user_id', $offer->user_id)
            ->first();

        if (! $offerRequest) {
            $offerRequest = TutoringOfferRequest::create([
                'school_id' => $offer->school_id,
                'offer_id' => $offer->id,
                'from_user_id' => $user->id,
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

        $offerRequest->token = (string) Str::uuid();
        $offerRequest->token_expires_at = Carbon::now()->addMinutes((int) config('schooltool.token_expire_time'));
        $offerRequest->save();

        $emailSubject = $status === 'NEW_REQUEST'
            ? 'Neue Anfrage für Ihr Nachhilfe-Angebot'
            : 'Erinnerung: Anfrage für Ihr Nachhilfe-Angebot';

        $params = [
            'email' => $user->email,
            'id' => $offerRequest->id,
            'token' => $offerRequest->token,
        ];

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/logos/'.$school->logo),
            'subject' => $emailSubject,
            'markdown' => 'mails.tutoring.offerRequest',
            'data' => [
                'url' => URL::temporarySignedRoute(
                    'homepage.tutoring.offer-request',
                    $offerRequest->token_expires_at,
                    $params,
                ),
            ],
        ];

        Notification::route('mail', EmailAliasResolver::resolveConfigured($user->email))->notify(new StandardEmail($mail));
    }

    public function sendOfferRequestStornoEmail($offerRequest): void
    {
        if (! $offerRequest instanceof TutoringOfferRequest) {
            $offerRequest = TutoringOfferRequest::findOrFail($offerRequest->id ?? $offerRequest['id']);
        }
        $offerRequest = $offerRequest->fresh();
        $school = $offerRequest->school;
        $user = $offerRequest->to_user;

        $offerRequest->token = (string) Str::uuid();
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
            'logo' => asset('/storage/images/logos/'.$school->logo),
            'subject' => $emailSubject,
            'markdown' => 'mails.tutoring.offerRequestStorno',
            'data' => ['url' => url("/homepage/tutoring_overview/?{$params}")],
        ];

        Notification::route('mail', EmailAliasResolver::resolveConfigured($user->email))->notify(new StandardEmail($mail));
    }

    public function userFromOfferRequest(string $email, int $offerRequestId, string $token): ?User
    {
        $offerRequest = TutoringOfferRequest::query()
            ->with('to_user')
            ->whereKey($offerRequestId)
            ->where('token', $token)
            ->where('token_expires_at', '>=', now())
            ->first();

        if (
            ! $offerRequest instanceof TutoringOfferRequest
            || ! $offerRequest->to_user instanceof User
            || ! hash_equals(
                mb_strtolower($offerRequest->to_user->email),
                mb_strtolower($email),
            )
        ) {
            return null;
        }

        return $offerRequest->to_user;
    }

    public function consumeUserFromOfferRequest(string $email, int $offerRequestId, string $token): ?User
    {
        return DB::transaction(function () use ($email, $offerRequestId, $token): ?User {
            $offerRequest = TutoringOfferRequest::query()
                ->with('to_user')
                ->whereKey($offerRequestId)
                ->where('token', $token)
                ->where('token_expires_at', '>=', now())
                ->lockForUpdate()
                ->first();

            if (
                ! $offerRequest instanceof TutoringOfferRequest
                || ! $offerRequest->to_user instanceof User
                || ! hash_equals(
                    mb_strtolower($offerRequest->to_user->email),
                    mb_strtolower($email),
                )
            ) {
                return null;
            }

            $user = $offerRequest->to_user;
            $offerRequest->token = null;
            $offerRequest->token_expires_at = null;
            $offerRequest->save();

            return $user;
        });
    }

    /**
     * @param  array{offer_id: int, token: string, email_mentor: string}  $baseParams
     */
    private function offerDecisionUrl(TutoringOffer $offer, string $action, array $baseParams): string
    {
        return URL::temporarySignedRoute(
            'homepage.tutoring.offer',
            Carbon::parse($offer->token_expires_at),
            [...$baseParams, 'action' => $action],
        );
    }
}
