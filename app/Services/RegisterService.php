<?php

namespace App\Services;

use App\Http\Resources\Homepage\RegisterDateBookingResource;
use App\Http\Resources\Homepage\RegisterDateResource;
use App\Http\Resources\Homepage\RegisterResource;
use App\Http\Resources\Homepage\SchoolResource;
use App\Http\Resources\Homepage\UserResource;
use App\Models\Licence;
use App\Models\Register;

use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\User;
use App\Services\AdminService;

use App\Services\LicenceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RegisterService
{


    public function book($user, $data)
    {
        $register = Register::findOrFail($data['register_id']);
        if (!$register->is_active) abort(403, "Die Registrierung ist geschlossen. Die Buchung kontte nicht durchgeführt werden.");

        $registerDate = RegisterDate::findOrFail($data['register_date_id']);
        if ($registerDate->is_locked) abort(403, "Der Termin ist gesperrt. Die Buchung kontte nicht durchgeführt werden.");

        $bookings = $registerDate->bookings;

        if ($registerDate->max_registrations != 0 && count($bookings) >= $registerDate->max_registrations) abort(403, "Der Termin ist bereits ausgebucht. Die Buchung kontte nicht durchgeführt werden.");
        if ($register->max_registrations != 0 && count($bookings) >= $register->max_registrations) abort(403, "Die Registrierung ist bereits ausgebucht. Die Buchung kontte nicht durchgeführt werden.");


        if (RegisterDateBooking::where('register_date_id', $data['register_date_id'])->where('user_id', $user->id)->exists()) abort(403, "Sie haben bereits eine Buchung. Bitte stornieren Sie diese zuerst.");


        $service = new RegisterDateBookingService();
        $booking = $service->createBooking($registerDate->school_id, $registerDate->schoolyear_id, $register->id, $user->id, $data);

        /*
        $booking = RegisterDateBooking::create(
            [
                'school_id' => $registerDate->school_id,
                'schoolyear_id' =>  $registerDate->schoolyear_id,
                'register_id' =>  $register->id,
                'register_date_id' =>  $registerDate->id,
                'user_id' => $user->id,
                'student_last_name' => $data['student_last_name'] ?? '',
                'student_first_name' => $data['student_first_name'] ?? '',
                'student_birthdate' => $data['student_birthdate'] ?? ''
            ]
        );
        */

        $data['booking_id'] = $booking->id;

        return $data;
    }


    public function loadRegisterAndUser($user)
    {
        $school = School::findOrFail($user->school_id);
        $register = Register::findOrFail($user->register_id);
        $app = 'Anmeldetool';

        // Prüfen der Lizenz
        $register_data = $this->checkLicenceAndSchool($school->short_name, $app);

        if ($register_data['status'] == 'error') {
            // Probleme mit der Lizenz
            $data = ['status' => 'error', 'message' => $register_data['message']];
        } else {
            // Lizenz ok

            $config = [
                'logo' => $school ? $school->logo : null,
                'version' => config('schooltool.version', 'x.x.x'),
                'copyright' => config('schooltool.copyright', ''),
                'title' => 'Anmeldetool',
                'school' => new SchoolResource($school),
                'user' => new UserResource($user)
            ];

            $register_dates = RegisterDate::withCount('bookings')
                ->where('register_id', $register->id)
                ->orderBy('date')
                ->get();

            $dates = $register_dates
                ->sortBy('date') // oder ->sortBy(fn($d) => Carbon::parse($d->date))
                ->pluck('date')
                ->unique()
                ->map(function ($date) {
                    $c = \Carbon\Carbon::parse($date)->locale('de');
                    return [
                        'date'    => $c->toDateString(),
                        'weekday' => ucfirst($c->translatedFormat('l')),
                    ];
                })
                ->values();

            $bookings = RegisterDateBooking::with('registerDate')->where('register_id', $register->id)->where('user_id', $user->id)->orderBy('register_date_id')->get();

            $data = [
                'status' => 'ok',
                'config' => $config,
                'register' => new RegisterResource($register),
                'register_dates' => RegisterDateResource::collection($register_dates),
                'dates' => $dates,
                'bookings' => RegisterDateBookingResource::collection($bookings),
            ];
        }


        return $data;
    }

    public function checkLicenceAndSchool($school_short, $app): array
    {
        $licenceService = new LicenceService();

        // Prüfen, ob Schule existiert
        $isSchoolValid = ($school = School::where('short_name', $school_short)->first()) != null;

        // Prüfen, ob App-Lizenz existiert bzw. gültig ist
        $isLicenceValid = $licenceService->isLicenceValid($school, $app);

        if ($isLicenceValid) {
            $licence = Licence::where('name', $app)->first();
        } else {
            $data = ['status' => 'error', 'message' => 'Lizenz ist ungültig'];
        }

        $data = ['status' => 'ok', 'isSchoolValid' => $isSchoolValid, 'school' => $school, 'isLicenceValid' => $isLicenceValid, 'licence' => $licence];
        return $data;
    }

    public function setToUser($user, $register_id): Register
    {
        $register = Register::findOrFail($register_id);

        $user->register_id = $register->id;
        $user->save();

        return $register;
    }

    public function toggle($register_id): Register
    {
        $register = Register::findOrFail($register_id);

        $register->is_active = !$register->is_active;
        $register->save();

        return $register;
    }

    public function createUserAndSendToken($data)
    {
        // Schule einlesen
        $school = School::findOrFail($data['school_id']);

        // User erzeugen
        $user = User::create([
            'school_id' => $school->id,
            'register_id' => $data['register_id'],
            'last_name' => 'Nachname neuer Benutzer',
            'first_name' => 'Vorname neuer Benutzer',
            'email' => $data['email'],
            'password' => Hash::make(now()),
        ]);

        // 2FA-Code senden für E-Mail-Bestätigung
        $adminService = new AdminService();
        $data_load = ['school' => $school];
        $adminService->setToken2Fa($user, $data_load, 'Code zur Bestätigung der E-Mail-Adresse');

        $data['user_id'] = $user->id;
        $data['step'] = 'EMAIL_TOKEN';

        return $data;
    }

    public function sendTokenForLogin($user, $data)
    {
        // Schule einlesen
        $school = School::findOrFail($data['school_id']);


        // 2FA-Code senden für Login
        $adminService = new AdminService();
        $data_load = ['school' => $school];
        $adminService->setToken2Fa($user, $data_load, 'Code zur Anmeldung');

        $data['user_id'] = $user->id;
        $data['step'] = 'LOGIN_TOKEN';

        return $data;
    }

    public function checkToken($user, $data): bool
    {
        return $user->token_2fa === $data['token_2fa'] &&
            Carbon::parse($user->token_2fa_expires_at)->isFuture();
    }
}
