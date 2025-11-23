<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Homepage\TutoringCheckEmailRequest;
use App\Http\Requests\Homepage\TutoringConfirmEmailRequest;
use App\Http\Requests\Homepage\TutoringConfirmUserRequest;
use App\Http\Requests\Homepage\TutoringCreateUserRequest;
use App\Http\Requests\Homepage\TutoringLoginWithTokenRequest;
use App\Http\Requests\Homepage\TutoringUnknownPasswordRequest;
use App\Http\Resources\Homepage\SchoolWithLicenceRecource;
use App\Models\School;
use App\Models\User;
use App\Services\TutoringService;
use Illuminate\Http\Request;

class TutoringController extends Controller
{
    public function config()
    {
        $licenceName = 'Tutoring';

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
        \Debugbar::info('before TutoringService->confirmEmail', $data);
        $data = $service->confirmEmail($data);
        \Debugbar::info('after TutoringService->confirmEmail', $data);
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

        if ($service->confirmUser($validated['user_id'], $validated['token'])) {

            return response('<h1>Benutzer wurde erfolgreich bestätigt! ✓</h1>', 200)
                ->header('Content-Type', 'text/html; charset=utf-8');
        } else {
            return response('<h1>Benutzer wurde nicht bestätigt! ✗</h1>', 200)
                ->header('Content-Type', 'text/html; charset=utf-8');
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
}
