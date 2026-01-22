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
use App\Models\School;
use App\Models\User;
use App\Services\TutoringService;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TutoringController extends Controller
{
    public function config()
    {
        $licenceName = 'Nachhilfetool';

        $schools = School::where('is_selectable', 1)->whereHas('licences', function ($query) use ($licenceName) {
            $query->where('name', $licenceName)
                ->where('school_licences.valid_until', '>=', now());
        })->with(['licences' => function ($query) use ($licenceName) {
            $query->where('name', $licenceName)
                ->where('school_licences.valid_until', '>=', now());
        }])->get();

        $data = [
            'schools' => SchoolWithLicenceRecource::collection($schools),
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

    public function checkEmail(TutoringCheckEmailRequest $request, TutoringService $service)
    {
        $validated = $request->validated();
        $data = $service->checkEmail($validated['data']);

        // Wenn ein Benutzer existiert, dann Rolle tutoring_user zuordnen
        if ($data['status'] == 'USER_FOUND') {

            // Tutoring-Rolle zuordnen
            $user = $service->assignTutoringRole($data['user_id']);
            $data = $service->checkLoginRequirement($data);
        }

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

        if ($service->confirmUser($validated['user_id'], $validated['token'])) {

            return redirect('/homepage/tutoring_response?title=Benutzer wurde erfolgreich bestätigt!&subtitle=' . $user->last_name . ' ' . $user->first_name . ' (' . $user->schoolclass . ')&text=Die Anfrage wurde genehmigt!&status=BESTÄTIGT');
        } else {
            return redirect('/homepage/tutoring_response?title=Benutzer wurde nicht bestätigt!&subtitle=' . $user->last_name . ' ' . $user->first_name . ' (' . $user->schoolclass . ')&text=Eventuell erfolgte schon früher die Genehmigung!&status=ZURÜCKGEWIESEN');
        }
    }

    public function refuseUser(TutoringConfirmUserRequest $request, TutoringService $service)
    {
        $validated = $request->validated();
        $user = User::findOrFail($validated['user_id']);

        if ($service->refuseUser($validated['user_id'], $validated['token'])) {

            return redirect('/homepage/tutoring_response?title=Benutzer wurde abgelehnt!&subtitle=' . $user->last_name . ' ' . $user->first_name . ' (' . $user->schoolclass . ')&text=Die Ablehnung wurde durchgeführt!&status=ABGELEHNT');
        } else {
            return redirect('/homepage/tutoring_response?title=Benutzer wurde nicht abgelehnt!&subtitle=' . $user->last_name . ' ' . $user->first_name . ' (' . $user->schoolclass . ')&text=Eventuell erfolgte schon früher die Ablehnung!&status=ZURÜCKGEWIESEN');
        }
    }

    public function unknownPassword(TutoringUnknownPasswordRequest $request, TutoringService $service)
    {
        $validated = $request->validated();
        $data = $validated['data'];

        $data = $service->checkLoginRequirement($data);
        if ($data['status'] != 'UNKNOWN_PASSWORD') return response()->json($data, 200);

        $data = $service->unknownPassword($data);
        return response()->json($data, 200);
    }

    public function loginWithToken(TutoringLoginWithTokenRequest $request, TutoringService $service)
    {
        $validated = $request->validated();
        $data = $validated['data'];

        $data = $service->checkLoginRequirement($data);
        if ($data['status'] != 'LOGIN_WITH_TOKEN') return response()->json($data, 200);

        $data = $service->loginWithToken($data);
        return response()->json($data, 200);
    }

    public function loginWithPassword(LoginWithPasswordRequest $request, TutoringService $service)
    {
        $validated = $request->validated();
        $data = $validated['data'];

        $data = $service->checkLoginRequirement($data);
        if (isset($data['status'])) return response()->json($data, 200);

        $data = $service->loginWithPassword($data);

        return response()->json($data, 200);
    }
}
