<?php

namespace App\Http\Controllers\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutoring\UserUpdatePasswordRequest;
use App\Http\Requests\Tutoring\UserUpdateRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserUpdateRequest $request, User $user, UserService $service)
    {
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $data = $validated['data'];

        if (($data['status'] ?? null) == 'CONFIRM_EMAIL' || ($data['status'] ?? null) == 'RE_CONFIRM_EMAIL') {
            // E-Mail-Confirmation wird gerade durchgeführt
            unset($data['status']);

            // E-Mail-Adresse ist nicht verfügbar ==> abort
            if (!$service->isEmailInSchoolAvailable($user->school_id, $data['email'])) abort(409, "Die E-Mail-Adresse ist nicht verfügbar.");

            if ($service->checkEmailVerification($auth_user, $data['token_2fa'])) {
                //E-Mail_verifikation hat funktioniert
                // User updaten und E-Mail-Verifikationsdatum setzen
                $user->update($data);
                $user->email_verified_at = now();
                $user->save();
                $data['status'] = 'OK';
            } else {
                //E-Mail_verifikation fehlgeschlagen

                // Code für E-Mail-Verifikation schicken
                $service->sendEmailVerification($user, $data['email']);
                unset($data['token_2fa']);
                $data['status'] = 'RE_CONFIRM_EMAIL';
            }
        } else {
            // Normale Update-Anfrage
            // Benutzer hat eine neue E-Mail eingegeben
            if ($user->email != $data['email']) {
                // E-Mail-Adresse ist nicht verfügbar ==> abort
                if (!$service->isEmailInSchoolAvailable($user->school_id, $data['email'])) abort(409, "Die E-Mail-Adresse ist nicht verfügbar.");

                // Code für E-Mail-Verifikation schicken
                $service->sendEmailVerification($user, $data['email']);
                $data['status'] = 'CONFIRM_EMAIL';
            } else {
                // User-Profil updaten
                $user->update($data);
                $data['status'] = 'OK';
            }
        }

        return response()->json($data, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }

    public function updatePassword(UserUpdatePasswordRequest $request, UserService $service)
    {
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $data = $validated['data'];
        if ($auth_user->id != $data['id'])  abort(403, 'Sie haben keine Berechtigung');

        $data = $service->setPasswordOrSendCode($auth_user, $data);

        return response()->json($data, 200);
    }

    public function logout()
    {
        if (! $auth_user = $this->userHasRole(['tutoring_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        if (Auth::check()) {
            \Debugbar::info('logout');
            Auth::guard('web')->logout();
            session()->invalidate();
        }
    }
}
