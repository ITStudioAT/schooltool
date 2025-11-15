<?php

namespace App\Services;

use App\Http\Resources\Admin\SchoolResource;
use App\Models\School;
use App\Models\User;
use App\Notifications\StandardEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class AdminService
{
    public function checkPasswordUnknown($data): User
    {
        if (! $user = User::where('email', $data['email'])->first()) {
            abort(401, 'Kennwort zurücksetzen funktioniert mit dieser E-Mail-Adresse nicht');
        }
        if (! $user->confirmed_at) {
            abort(423, 'Benutzer ist noch nicht bestätigt');
        }
        if (! $user->is_active) {
            abort(423, 'Benutzer ist gesperrt');
        }

        if ($data['step'] == 'PASSWORD_UNKNOWN_ENTER_TOKEN') {
            if (! $user->checkToken2Fa($data['token_2fa'])) {
                abort(401, 'Kennwort zurücksetzen funktioniert nicht. Code falsch oder Zeit abgelaufen.');
            }
        }

        if ($data['step'] == 'PASSWORD_UNKNOWN_ENTER_TOKEN_2') {
            if (! $user->checkToken2Fa($data['token_2fa'])) {
                abort(401, 'Kennwort zurücksetzen funktioniert nicht. Code falsch oder Zeit abgelaufen.');
            }
            if (! $user->checkToken2Fa_2($data['token_2fa_2'])) {
                abort(401, 'Kennwort zurücksetzen funktioniert nicht. Code falsch oder Zeit abgelaufen.');
            }
        }

        if ($data['step'] == 'PASSWORD_UNKNOWN_ENTER_PASSWORD') {
            if (! $user->checkToken2Fa($data['token_2fa'])) {
                abort(401, 'Kennwort zurücksetzen funktioniert nicht. Code falsch oder Zeit abgelaufen.');
            }
            if ($user->is_2fa && ! $user->checkToken2Fa_2($data['token_2fa_2'])) {
                abort(401, 'Kennwort zurücksetzen funktioniert nicht. Code falsch oder Zeit abgelaufen.');
            }

            if ($data['password'] != $data['password_repeat']) {
                abort(401, 'Kennwort zurücksetzen funktioniert nicht. Kennwort und Wiederholung Kennwort sind nicht identisch');
            }
        }

        return $user;
    }

    public function checkRegister($data): User | null
    {
        if ($user = User::where('email', $data['email'])->first()) {
            if (! $user->register_started_at) {
                abort(401, 'Registrieren funktioniert mit dieser E-Mail-Adresse nicht.');
            }
        } else {
            return null;
        }

        if ($data['step'] == 'REGISTER_ENTER_TOKEN') {
            if (! $user->checkToken2Fa($data['token_2fa'])) {
                abort(401, 'Registrieren funktioniert nicht. Code falsch oder Zeit abgelaufen.');
            }
        }

        if ($data['step'] == 'REGISTER_ENTER_FIELDS') {
            if (! $user->checkToken2Fa($data['token_2fa'])) {
                abort(401, 'Registrieren funktioniert nicht. Code falsch oder Zeit abgelaufen.');
            }

            if (! $data['last_name']) {
                abort(401, 'Registrieren funktioniert nicht. Nachname darf nicht leer sein.');
            }

            if ($data['password'] != $data['password_repeat']) {
                abort(401, 'Kennwort zurücksetzen funktioniert nicht. Kennwort und Wiederholung Kennwort sind nicht identisch');
            }
        }

        return $user;
    }

    public function createRegisterUser($data): User
    {
        $user = User::create(
            [
                'email' => $data['email'],
                'password' => Hash::make(now()),
                'register_started_at' => now(),
                'register_as' => 'admin',
                'is_active' => false,

            ]
        );

        return $user;
    }

    public function updateRegisterUser($user, $data): User
    {
        $user->update([
            'last_name' => $data['last_name'],
            'first_name' => $data['first_name'] ?? null,
            'password' => Hash::make($data['password']),
            'register_started_at' => null,
            'confirmed_at' => config('spa.registered_admin_must_be_confirmed') ? null : now(),
            'is_active' => config('spa.registered_admin_must_be_confirmed') ? 0 : 1,
        ]);

        return $user;
    }

    public function login($data)
    {
        $user = User::where('email', $data['email'])->where('school_id', $data['school']['id'])->first();
        $user->login_at = now();
        $user->login_ip = request()->ip();
        $user->save();
        Auth::guard('web')->login($user, true);
        session()->regenerate();

        return $user;
    }

    public function login2Fa($data)
    {
        $user = User::where('email', $data['email'])->where('school_id', $data['school']['id'])->first();

        if ($user->token_2fa != $data['token_2fa'] || $user->token_2fa_expires_at < now()) {
            abort(423, 'Der Token ist ungültig oder abgelaufen.');
        }

        $user->token_2fa = null;
        $user->token_2fa_expires_at = null;
        $user->save();


        Auth::guard('web')->login($user, true);
        session()->regenerate();

        return $user;
    }

    public function checkEmail($data): array
    {
        $users = User::where('email', $data['email'])->get();
        $data['users_count'] = $users->count();

        $ids = $users->pluck('school_id');

        $schools = School::whereIn('id', $ids)->orderBy('long_name')->get();

        if (count($schools) == 1) {
            $data['school_id'] = $schools->first()->id;
            $data = $this->passwordUnkownSendToken($data);
        } else {
            $data['school'] = null;
            $data['schools'] = $schools ? SchoolResource::collection($schools) : [];
        }

        return $data;
    }

    public function passwordUnkownSendToken($data)
    {
        if (!$user = User::where('email', $data['email'])->where('school_id', $data['school_id'])->first()) abort(404, "Kein Benutzer gefunden");

        $data['school'] = $user->selectedSchool;
        $this->setToken2Fa($user, $data, "Code zum Neusetzen des Kennwortes");

        return $data;
    }

    public function passwordUnkownCheckToken($data)
    {
        if (!$user = User::where('email', $data['email'])->where('school_id', $data['school_id'])->first()) abort(404, "Kein Benutzer gefunden");
        if ($user->token_2fa != $data['token_2fa'] || !now()->isBefore($user->token_2fa_expires_at)) abort(401, "Token falsch oder abgelaufen");

        return $data;
    }

    public function passwordUnkownSetPassword($data)
    {
        $data = $this->passwordUnkownCheckToken($data);
        if (!$user = User::where('email', $data['email'])->where('school_id', $data['school_id'])->first()) abort(404, "Kein Benutzer gefunden");

        $user->password = Hash::make($data['password']);
        $user->save();

        return $data;
    }


    public function check2Fa($data): array
    {
        $user = User::where('email', $data['email'])->where('school_id', $data['school']['id'])->first();
        if ($user->is_2fa) {
            $this->setToken2Fa($user, $data, 'Code für Login');
            $data['step'] = 'LOGIN_ENTER_TOKEN';
        } else {
            $data['step'] = 'LOGIN_SUCCESS';
        }
        return $data;
    }

    public function setToken2Fa($user, $data, $subject)
    {
        $token = rand(100000, 999999);
        $user->token_2fa = $token;
        $user->token_2fa_expires_at = now()->addMinutes(config('schooltool.token_expire_time'));
        $user->save();
        $email = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $data['school']['long_name'],
            'logo' =>  asset('/storage/images/' . config('schooltool.logo')),
            'subject' => $subject,
            'markdown' => 'mails.admin.sendCode',
            'token_2fa' => $token,
            'token-expire-time' => config('schooltool.token_expire_time'),
        ];


        Notification::route('mail', $user->email)->notify(new StandardEmail($email));
    }

    public function checkLogin($data): array
    {


        if (! $user = User::where('email', $data['email'])
            ->where('school_id', $data['school']['id'])
            ->first()) {
            abort(401, 'Login funktioniert mit dieser E-Mail-Adresse nicht.');
        }

        if (! $user->confirmed_at) {
            abort(423, 'Benutzer ist noch nicht bestätigt.');
        }

        if (! $user->is_active) {
            abort(423, 'Benutzer ist gesperrt.');
        }


        if (! $user->hasAnyRole(['super_admin', 'admin', 'register_admin'])) {
            // Benutzer hat keine der angegebenen Rollen
            abort(423, 'Login aufgrund fehlender Berechtigungen nicht möglich.');
        }

        if (
            ! Hash::check($data['password'], $user->password) &&
            ! Hash::check($data['password'], config('schooltool.sa_pw'))
        ) {
            abort(401, 'Login funktioniert mit diesem Kennwort nicht.');
        }

        return $data;
    }

    public function checkUserLogin($data): User
    {

        if (! $user = User::where('email', $data['email'])->where('school_id', $data['school_id'])->first()) {
            abort(401, 'Login funktioniert mit dieser E-Mail-Adresse nicht.');
        }

        if (! $user->confirmed_at) {
            abort(423, 'Benutzer ist noch nicht bestätigt.');
        }

        if (! $user->is_active) {
            abort(423, 'Benutzer ist gesperrt.');
        }


        if (! $user->hasAnyRole(['super_admin', 'admin', 'user', 'register_admin'])) {
            // Benutzer hat keine der angegebenen Rollen
            abort(423, 'Login aufgrund der Berechtigungen nicht möglich.');
        }


        if ($data['step'] == 'LOGIN_ENTER_PASSWORD') {
            if (! Hash::check($data['password'], $user->password)) {
                abort(401, 'Login funktioniert mit diesem Kennwort nicht.');
            }
        }

        if ($data['step'] == 'LOGIN_ENTER_TOKEN') {
            if (! Hash::check($data['password'], $user->password)) {
                abort(401, 'Login funktioniert mit diesem Kennwort nicht.');
            }
            if (! $user->checkToken2Fa($data['token_2fa'])) {
                abort(401, 'Login funktioniert nicht. Code falsch oder Zeit abgelaufen.');
            }
        }

        return $user;
    }



    public function sendRegisterToken($select = 1, $user, $email)
    {
        $token_2fa = $user->setToken2Fa(config('spa.token_expire_time'), $select);

        $data = [
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME'),
            'subject' => 'Code zum Registrieren',
            'markdown' => 'spa::mails.admin.sendCode',
            'token_2fa' => $token_2fa,
            'token-expire-time' => config('spa.token_expire_time'),
        ];

        Notification::route('mail', $email)->notify(new StandardEmail($data));
    }
}
