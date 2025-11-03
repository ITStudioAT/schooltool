<?php

namespace App\Http\Controllers\Homepage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Homepage\RegisterBookRequest;
use App\Http\Requests\Homepage\RegisterCheckEmailRequest;
use App\Http\Requests\Homepage\RegisterConfirmEmailRequest;
use App\Http\Requests\Homepage\RegisterDeleteBookingRequest;
use App\Http\Requests\Homepage\RegisterLoginTokenRequest;
use App\Http\Requests\Homepage\RegisterSaveUserDataRequest;
use App\Http\Resources\Homepage\LicenceResource;
use App\Http\Resources\Homepage\RegisterResource;
use App\Http\Resources\Homepage\SchoolResource;
use App\Models\Licence;
use App\Models\Register;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\User;
use App\Services\AdminService;
use App\Services\LicenceService;
use App\Services\RegisterDateBookingService;
use App\Services\RegisterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function config(Request $request, RegisterService $service)
    {

        if (Auth::check()) {
            $user = Auth::user();
            $school = School::findOrFail($user->school_id);
            $school_short = $school->short_name;
        } else {
            $school_short = $request->query('school');
        }

        $app = 'Anmeldetool';

        $register_data = $service->checkLicenceAndSchool($school_short, $app);

        if ($register_data['status'] == 'error') abort(403, $register_data['message']);

        $isSchoolValid = $register_data['isSchoolValid'];
        $isLicenceValid = $register_data['isLicenceValid'];
        $school = $register_data['school'];
        $licence = $register_data['licence'];

        // Laden der Registers
        $registers = Register::where('school_id', $school->id)->where('is_active', 1)->orderBy('name')->get();

        $data = [
            'logo' => $school ? $school->logo : null,
            'version' => config('schooltool.version', 'x.x.x'),
            'copyright' => config('schooltool.copyright', ''),
            'title' => 'Anmeldetool',
            'isSchoolValid' => $isSchoolValid,
            'school' => $isSchoolValid ? new SchoolResource($school) : null,
            'isLicenceValid' =>  $isLicenceValid,
            'licence' => $isLicenceValid ? new LicenceResource($licence) : null,
            'registers' => $registers ? RegisterResource::collection($registers) : [],
        ];

        return response()->json($data, 200);
    }

    public function checkEmail(RegisterCheckEmailRequest $request, AdminService $adminService, RegisterService $registerService)
    {
        $validated = $request->validated();
        $data = $validated['data'];

        // Prüfen, ob es den User bereits gibt
        $user = User::where('school_id', $data['school_id'])->where('email', $data['email'])->first();

        // User existiert noch nicht, 
        if (!$user) {
            $data = $registerService->createUserAndSendToken($data);
        } else {
            $data = $registerService->sendTokenForLogin($user, $data);
        }

        return response()->json($data, 200);
    }

    public function confirmEmail(RegisterConfirmEmailRequest $request, RegisterService $registerService)
    {

        $validated = $request->validated();
        $data = $validated['data'];

        // Prüfen, ob es den User wirklich gibt, wenn nein, kann etwas nicht stimmen
        if (!$user = User::where('id', $data['user_id'])->where('school_id', $data['school_id'])->where('email', $data['email'])->first()) abort(422, 'Ungültige Anmeldedaten');

        // Prüfen, des Tokens
        if (!$registerService->checkToken($user, $data)) abort(401, 'Das Token ist falsch oder abgelaufen');

        // E-Mail verified_at  und confirmed_at setzen
        $user->email_verified_at = now();
        $user->confirmed_at = now();
        $user->save();

        $data['step'] = 'ENTER_USER_DATA';

        return response()->json($data, 200);
    }

    public function saveUserData(RegisterSaveUserDataRequest $request, RegisterService $registerService)
    {

        $validated = $request->validated();
        $data = $validated['data'];

        // Prüfen, ob es den User wirklich gibt, wenn nein, kann etwas nicht stimmen
        if (!$user = User::where('id', $data['user_id'])->where('school_id', $data['school_id'])->where('email', $data['email'])->first()) abort(422, 'Ungültige Anmeldedaten');

        if (!$registerService->checkToken($user, $data)) abort(401, 'Das Token ist falsch oder abgelaufen');


        // User-Daten aktualisieren
        $user->update([
            'last_name' => $data['last_name'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        // User einloggen
        $user->assignRole('register_user');
        Auth::guard('web')->login($user, true);
        session()->regenerate();

        $data['step'] = 'OK';

        return response()->json($data, 200);
    }

    public function loginToken(RegisterLoginTokenRequest $request, RegisterService $registerService)
    {

        $validated = $request->validated();
        $data = $validated['data'];

        // Prüfen, ob es den User wirklich gibt, wenn nein, kann etwas nicht stimmen
        if (!$user = User::where('id', $data['user_id'])->where('school_id', $data['school_id'])->where('email', $data['email'])->first()) abort(422, 'Ungültige Anmeldedaten');

        if (!$registerService->checkToken($user, $data)) abort(401, 'Das Token ist falsch oder abgelaufen');


        // User einloggen
        $user->assignRole('register_user');
        Auth::guard('web')->login($user, true);
        session()->regenerate();

        $data['step'] = 'OK';

        return response()->json($data, 200);
    }

    public function loadRegisterAndUser(RegisterService $service)
    {
        if (! $auth_user = $this->userHasRole(['register_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $data = $service->loadRegisterAndUser($auth_user);
        return response()->json($data, 200);
    }

    public function book(RegisterBookRequest $request, RegisterService $service)
    {
        if (! $auth_user = $this->userHasRole(['register_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $data = $request->validated()['data'];
        $data['is_notify'] = true;

        $data = $service->book($auth_user, $data);
        return response()->json($data, 200);
    }

    public function deleteBooking(RegisterDeleteBookingRequest $request, RegisterDateBookingService $service)
    {
        if (! $auth_user = $this->userHasRole(['register_user'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        $service->deleteBookings($auth_user, [$validated['booking_id']], true);
    }
}
