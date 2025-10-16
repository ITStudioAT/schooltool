<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use App\Notifications\StandardEmail;

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

    public function checkUserLogin($data): User
    {

        if (! $user = User::where('email', $data['email'])->first()) {
            abort(401, 'Login funktioniert mit dieser E-Mail-Adresse nicht.');
        }


        if (! $user->confirmed_at) {
            abort(423, 'Benutzer ist noch nicht bestätigt.');
        }


        if (! $user->is_active) {
            abort(423, 'Benutzer ist gesperrt.');
        }


        if (! $user->hasAnyRole(['super_admin', 'admin', 'user'])) {
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
                abort(401, 'Login funktioniert mit diesem Kennwort nicht');
            }
            if (! $user->checkToken2Fa($data['token_2fa'])) {
                abort(401, 'Login funktioniert nicht. Code falsch oder Zeit abgelaufen.');
            }
        }

        return $user;
    }

    public function continueLoginFor2FaUser($user)
    {

        $token_2fa = $user->setToken2Fa(config('spa.token_expire_time'), 1);

        $data = [
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME'),
            'subject' => 'Code für Login',
            'markdown' => 'spa::mails.admin.sendCode',
            'token_2fa' => $token_2fa,
            'token-expire-time' => config('spa.token_expire_time'),
        ];

        Notification::route('mail', $user->email)->notify(new StandardEmail($data));
    }

    public function sendPasswordResetToken($select = 1, $user, $email)
    {
        $token_2fa = $user->setToken2Fa(config('spa.token_expire_time'), $select);

        $data = [
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME'),
            'subject' => 'Code zum Setzen des Kennwortes',
            'markdown' => 'spa::mails.admin.sendCode',
            'token_2fa' => $token_2fa,
            'token-expire-time' => config('spa.token_expire_time'),
        ];

        Notification::route('mail', $email)->notify(new StandardEmail($data));
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

    public function sendEmailValidationToken($select = 1, $user, $email)
    {
        $token_2fa = $user->setToken2Fa(config('spa.token_expire_time'), $select);

        $data = [
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME'),
            'subject' => 'Code zum Bestätigen der E-Mail-Adresse',
            'markdown' => 'spa::mails.admin.sendCode',
            'token_2fa' => $token_2fa,
            'token-expire-time' => config('spa.token_expire_time'),
        ];

        Notification::route('mail', $email)->notify(new StandardEmail($data));
    }
}
