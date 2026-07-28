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
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterService
{
    public function book(User $user, array $data): array
    {
        return DB::transaction(function () use ($data, $user): array {
            $register = Register::query()
                ->whereKey((int) $data['register_id'])
                ->where('school_id', $user->school_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $user->register_id !== (int) $register->id) {
                abort(403, 'Diese Registrierung ist Ihrem Benutzerkonto nicht zugeordnet.');
            }

            if (! $register->is_active) {
                abort(403, 'Die Registrierung ist geschlossen. Die Buchung konnte nicht durchgeführt werden.');
            }

            $registerDate = RegisterDate::query()
                ->whereKey((int) $data['register_date_id'])
                ->where('register_id', $register->id)
                ->where('school_id', $register->school_id)
                ->where('schoolyear_id', $register->schoolyear_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($registerDate->is_locked) {
                abort(403, 'Der Termin ist gesperrt. Die Buchung konnte nicht durchgeführt werden.');
            }

            $dateBookingsCount = RegisterDateBooking::query()
                ->where('register_date_id', $registerDate->id)
                ->count();

            if ($registerDate->max_registrations !== 0 && $dateBookingsCount >= $registerDate->max_registrations) {
                abort(403, 'Der Termin ist bereits ausgebucht. Die Buchung konnte nicht durchgeführt werden.');
            }

            $registerBookingsCount = RegisterDateBooking::query()
                ->where('register_id', $register->id)
                ->count();

            if ($register->max_registrations !== 0 && $registerBookingsCount >= $register->max_registrations) {
                abort(403, 'Die Registrierung ist bereits ausgebucht. Die Buchung konnte nicht durchgeführt werden.');
            }

            $existingBooking = RegisterDateBooking::query()
                ->where('register_date_id', $registerDate->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($existingBooking) {
                abort(403, 'Sie haben bereits eine Buchung. Bitte stornieren Sie diese zuerst.');
            }

            $booking = app(RegisterDateBookingService::class)->createBooking(
                $registerDate->school_id,
                $registerDate->schoolyear_id,
                $register->id,
                $user->id,
                $data,
            );

            $responseData = $data;
            $responseData['booking_id'] = $booking->id;

            return $responseData;
        }, attempts: 3);
    }

    public function loadRegisterAndUser($user): array
    {
        $school = School::findOrFail($user->school_id);
        $register = Register::findOrFail($user->register_id);

        $licenceCheck = $this->checkLicenceAndSchool($school->short_name, 'Anmeldetool');

        if ($licenceCheck['status'] === 'error') {
            return ['status' => 'error', 'message' => $licenceCheck['message']];
        }

        $config = [
            'logo' => $school->logo,
            'version' => config('schooltool.version', 'x.x.x'),
            'copyright' => config('schooltool.copyright', ''),
            'title' => 'Anmeldetool',
            'school' => new SchoolResource($school),
            'user' => new UserResource($user),
        ];

        $registerDates = RegisterDate::withCount('bookings')
            ->where('register_id', $register->id)
            ->orderBy('date')
            ->orderBy('from')
            ->get();

        $dates = $registerDates
            ->pluck('date')
            ->unique()
            ->map(function ($date) {
                $carbon = Carbon::parse($date)->locale('de');

                return [
                    'date' => $carbon->toDateString(),
                    'weekday' => ucfirst($carbon->translatedFormat('l')),
                ];
            })
            ->values();

        $bookings = RegisterDateBooking::with('registerDate')
            ->where('register_id', $register->id)
            ->where('user_id', $user->id)
            ->orderBy('register_date_id')
            ->get();

        return [
            'status' => 'ok',
            'config' => $config,
            'register' => new RegisterResource($register),
            'register_dates' => RegisterDateResource::collection($registerDates),
            'dates' => $dates,
            'bookings' => RegisterDateBookingResource::collection($bookings),
        ];
    }

    public function checkLicenceAndSchool(string $schoolShort, string $app): array
    {
        $licenceService = new LicenceService;

        $school = School::where('short_name', $schoolShort)->first();
        $isSchoolValid = $school !== null;
        $isLicenceValid = $licenceService->isLicenceValid($school, $app);
        $licence = $isLicenceValid ? Licence::where('name', $app)->first() : null;

        return [
            'status' => 'ok',
            'isSchoolValid' => $isSchoolValid,
            'school' => $school,
            'isLicenceValid' => $isLicenceValid,
            'licence' => $licence,
        ];
    }

    public function setToUser(User $user, int $registerId): Register
    {
        $register = Register::query()
            ->whereKey($registerId)
            ->where('school_id', $user->school_id)
            ->firstOrFail();

        $user->register_id = $register->id;
        $user->save();

        return $register;
    }

    public function toggle(int $registerId): Register
    {
        $register = Register::findOrFail($registerId);
        $register->is_active = ! $register->is_active;
        $register->save();

        return $register;
    }

    public function createUserAndSendToken(array $data): array
    {
        $school = School::findOrFail($data['school_id']);

        $user = User::create([
            'school_id' => $school->id,
            'register_id' => $data['register_id'],
            'last_name' => 'Nachname neuer Benutzer',
            'first_name' => 'Vorname neuer Benutzer',
            'email' => $data['email'],
            'password' => Hash::make(now()),
        ]);

        $adminService = new AdminService;
        $adminService->setToken2Fa($user, ['school' => $school], 'Code zur Bestätigung der E-Mail-Adresse');

        $data['user_id'] = $user->id;
        $data['step'] = 'EMAIL_TOKEN';

        return $data;
    }

    public function sendTokenForLogin($user, array $data): array
    {
        $school = School::findOrFail($data['school_id']);

        $adminService = new AdminService;
        $adminService->setToken2Fa($user, ['school' => $school], 'Code zur Anmeldung');

        $data['user_id'] = $user->id;
        $data['step'] = 'LOGIN_TOKEN';

        return $data;
    }

    public function checkToken($user, array $data): bool
    {
        $user = $user->fresh();

        return $user
            && $user->token_2fa === $data['token_2fa']
            && $user->token_2fa_expires_at !== null
            && ! $user->token_2fa_expires_at->isPast();
    }
}
