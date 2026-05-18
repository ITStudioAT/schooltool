<?php

namespace App\Http\Controllers\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Homepage\TutoringCheckEmailRequest;
use App\Http\Requests\Homepage\TutoringConfirmEmailRequest;
use App\Http\Requests\Homepage\TutoringConfirmUserRequest;
use App\Http\Requests\Homepage\TutoringCreateUserRequest;
use App\Http\Requests\Homepage\TutoringLoginWithTokenRequest;
use App\Http\Requests\Homepage\TutoringUnknownPasswordRequest;
use App\Http\Requests\Tutoring\LoginWithPasswordRequest;
use App\Http\Resources\Homepage\SchoolWithLicenceRecource;
use App\Http\Resources\Homepage\UserResource;
use App\Http\Resources\Tutoring\SchoolToolResource;
use App\Models\SchoolTool;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Services\Import116Service;
use App\Services\LicenceService;
use App\Services\TutoringService;
use Carbon\Carbon;

class TutoringController extends Controller
{
    public function config(LicenceService $licenceService)
    {
        $schools = $licenceService->selectableSchoolsForTool('Nachhilfetool')['schools'];

        $data = [
            'schools' => SchoolWithLicenceRecource::collection($schools),
            'health' => [
                'queue_working' => $this->isQueueWorking(),
            ],
        ];

        return response()->json($data, 200);
    }

    public function loadAuth()
    {
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $school = $auth_user->selectedSchool;
        $schoolTool = $school->schoolTool;

        $data = [
            'auth_check' => true,
            'auth_user' => new UserResource($auth_user),
            'version' => config('schooltool.version'),
            'school_long_name' => $school->long_name,
            'school_short_name' => $school->short_name,
            'school_logo' => $school->logo,
            'school_tool' => new SchoolToolResource($schoolTool),
        ];

        return response()->json($data, 200);
    }

    public function checkEmail(TutoringCheckEmailRequest $request, TutoringService $service, Import116Service $import116Service)
    {
        $validated = $request->validated();

        // Prüfen, ob Benutzer in User mit der E-Mail-Adresse und mit der Schule existiert
        // $data['status] = 'NEW_USER' : User existiert nicht
        // $data['status] = 'USER_FOUND' : User existiert
        $data = $service->checkEmail($validated['data']);

        // Wenn kein User existiert, check, ob dieser aus Import116 angelegt werden kann
        if ($data['status'] == 'NEW_USER') {
            $import116User = $import116Service->getImport116User($data['school_id'], $data['email']);
            if ($import116User) {
                // Import 116 User existiert => erzeuge einen neuen User mit den Daten aus Import116
                $data = $import116Service->createUserFromImport116($import116User);
                $data['status'] = 'USER_FOUND';
            }
        }

        // Wenn ein Benutzer existiert, dann Rolle tutoring_user zuordnen
        if ($data['status'] == 'USER_FOUND') {

            // Tutoring-Rolle zuordnen
            $user = $service->assignTutoringRole($data['user_id']);

            // Prüfen, ob der Schüler in Import116 existiert und ggf. Daten aktualisieren
            $import116User = $import116Service->getImport116User($data['school_id'], $data['email']);
            $import116Service->syncUser($user, $import116User);

            // Prüfen, ob User  !is_active ==> $data['status'] = 'USER_INACTIVE'
            // Prüfen, ob User  !email_verified_at ==> sendCodeToUSer ==> $data['status'] = 'CONFIRM_EMAIL'
            // Prüfen, ob User  !confirmed_at
            // .. Wenn $schoolTool->tutoring_student_must_be_confirmed && $schoolTool->tutoring_confirmer_email ==> sendConfirmerNotification ==> $data['status'] = 'USER_NOT_CONFIRMED'
            // .. sonst $user->confirmed_at = now();
            $data = $service->checkLoginRequirement($data);
        }

        // $data['status']
        // .. NEW_USER => Neuer Benutzer
        // .. USER_FOUND => User existiert und bereits bestätigt
        // .. USER_INACTIVE => User existiert, aber ist inaktiv
        // .. CONFIRM_EMAIL => User existiert, aber E-Mail ist nicht bestätigt
        // .. USER_NOT_CONFIRMED => User existiert, aber User ist nicht bestätigt

        return response()->json($data, 200);
    }

    public function confirmEMail(TutoringConfirmEmailRequest $request, TutoringService $service)
    {
        $validated = $request->validated();
        $data = $validated['data'];
        $data = $service->confirmEmail($data);

        return response()->json($data, 200);
    }

    public function createUser(TutoringCreateUserRequest $request, TutoringService $service)
    {
        $validated = $request->validated();
        $data = $validated['data'];
        $user = $service->createUser($data);
        $user = $service->assignTutoringRole($user->id);
        $data['user_id'] = $user->id;
        $service->sendCodeToUser($user);
        $data['status'] = 'CONFIRM_EMAIL';

        return response()->json($data, 200);
    }

    public function confirmUser(TutoringConfirmUserRequest $request, TutoringService $service)
    {
        $validated = $request->validated();
        $user = User::findOrFail($validated['user_id']);
        $this->ensureTutoringLicenceForSchool($user->selectedSchool);

        if ($service->confirmUser($validated['user_id'], $validated['token'])) {

            return redirect('/homepage/tutoring_response?title=Benutzer wurde erfolgreich bestätigt!&subtitle='.$user->last_name.' '.$user->first_name.' ('.$user->schoolclass.')&text=Die Anfrage wurde genehmigt!&status=BESTÄTIGT');
        } else {
            return redirect('/homepage/tutoring_response?title=Benutzer wurde nicht bestätigt!&subtitle='.$user->last_name.' '.$user->first_name.' ('.$user->schoolclass.')&text=Eventuell erfolgte schon früher die Genehmigung!&status=ZURÜCKGEWIESEN');
        }
    }

    public function refuseUser(TutoringConfirmUserRequest $request, TutoringService $service)
    {
        $validated = $request->validated();
        $user = User::findOrFail($validated['user_id']);
        $this->ensureTutoringLicenceForSchool($user->selectedSchool);

        if ($service->refuseUser($validated['user_id'], $validated['token'])) {

            return redirect('/homepage/tutoring_response?title=Benutzer wurde abgelehnt!&subtitle='.$user->last_name.' '.$user->first_name.' ('.$user->schoolclass.')&text=Die Ablehnung wurde durchgeführt!&status=ABGELEHNT');
        } else {
            return redirect('/homepage/tutoring_response?title=Benutzer wurde nicht abgelehnt!&subtitle='.$user->last_name.' '.$user->first_name.' ('.$user->schoolclass.')&text=Eventuell erfolgte schon früher die Ablehnung!&status=ZURÜCKGEWIESEN');
        }
    }

    public function unknownPassword(TutoringUnknownPasswordRequest $request, TutoringService $service)
    {
        $validated = $request->validated();
        $data = $validated['data'];

        $data = $service->checkLoginRequirement($data);
        if ($data['status'] != 'UNKNOWN_PASSWORD') {
            return response()->json($data, 200);
        }

        $data = $service->unknownPassword($data);

        return response()->json($data, 200);
    }

    public function loginWithToken(TutoringLoginWithTokenRequest $request, TutoringService $service)
    {
        $validated = $request->validated();
        $data = $validated['data'];

        $data = $service->checkLoginRequirement($data);
        if ($data['status'] != 'LOGIN_WITH_TOKEN') {
            return response()->json($data, 200);
        }

        $data = $service->loginWithToken($data);

        return response()->json($data, 200);
    }

    public function loginWithPassword(LoginWithPasswordRequest $request, TutoringService $service)
    {
        $validated = $request->validated();
        $data = $validated['data'];

        $data = $service->checkLoginRequirement($data);
        if (isset($data['status'])) {
            return response()->json($data, 200);
        }

        $data = $service->loginWithPassword($data);

        return response()->json($data, 200);
    }

    private function ensureTutoringLicenceForSchool($school): void
    {
        $status = app(LicenceService::class)->licenceStatus($school, 'Nachhilfetool');
        if ($status === 'active') {
            return;
        }

        abort(403, $status === 'expired' ? 'Lizenz abgelaufen.' : 'Lizenz nicht vorhanden.');
    }

    private function isQueueWorking(): bool
    {
        $workerAt = Cache::get('health:worker');
        if (! $workerAt) {
            return true;
        }

        return Carbon::parse($workerAt)->greaterThan(now()->subMinutes(2));
    }
}
