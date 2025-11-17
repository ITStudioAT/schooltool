<?php

namespace App\Services;

use App\Enums\TwoFaResult;
use App\Models\RegisterDateBooking;
use App\Models\Role;
use App\Models\Schoolyear;
use App\Models\User;
use App\Notifications\StandardEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class UserService
{

    public function delete($me_id, $data)
    {

        foreach ($data as $id) {
            $user = User::findOrFail($id);
            if (!$user->hasDependencies() && $user->id != $me_id) {
                $user->syncRoles([]);
                $user->delete();
            }
        }
    }


    public function store($school_id, $data): User
    {
        if (User::where('school_id', $school_id)->where('email', $data['email'])->first()) abort(409, 'E-Mail existiert bereits');

        $user_roles = $data['roles'];
        unset($data['roles']);

        $data['school_id'] = $school_id;
        $data['email_verified_at'] = now();
        $data['is_active'] = true;
        $data['confirmed_at'] = now();
        $data['password'] = Hash::make(now());

        $user = User::create($data);

        foreach ($user_roles as $role) {

            // super_admin überspringen
            if ($role['name'] == 'super_admin') continue;

            if ($role['checked'] ?? false) {
                $user->assignRole($role['name']);
            }
        }
        return $user;
    }

    public function update($data): User
    {
        if (!$user = User::findOrFail($data['id'])) abort(404, 'Benutzer wurde nich gefunden');

        if (User::whereNot('id', $user->id)->where('school_id', $user->school_id)->where('email', $data['email'])->first()) abort(409, 'E-Mail existiert bereits');


        $user_roles = $data['roles'];
        unset($data['roles']);

        $user->update($data);

        foreach ($user_roles as $role) {

            // super_admin überspringen
            if ($role['name'] == 'super_admin') continue;

            if ($role['checked']) {
                $user->assignRole($role['name']);
            } else {
                // register_user prüfen, ob es eine Registrierung gibt.
                if ($role['name'] == 'register_user') {
                    if (RegisterDateBooking::where('user_id', $user->id)->count() > 0) continue;
                }
                $user->removeRole($role['name']);
            }
        }

        return $user;
    }


    // Die User sollen statt eines Schuljahres (und statt eines Registers) NULL zugewiesen bekommen
    public function setSchoolyearToNull($schoolyear): Schoolyear | bool
    {
        $school_id = $schoolyear->school_id;

        User::where('school_id', $school_id)->where('schoolyear_id', $schoolyear->id)->update([
            'schoolyear_id' => null,
            'register_id' => null,
        ]);

        return true;
    }


    // Neues Schuljahr bei User setzen (und Register auf NULL)
    public function setNewSchoolyear($user, $schoolyear)
    {
        $user->schoolyear_id = $schoolyear->id;
        $user->register_id = null;
        $user->save();
    }

    public function allUsersInfos(): array
    {
        $data = [];
        $users_count = User::query()->count();
        $users_is_active_count = User::where('is_active', 1)->count();
        $users_is_2fa_count = User::where('is_2fa', 1)->count();
        $users_is_confirmed_count = User::whereNotNull('confirmed_at')->count();
        $users_is_not_confirmed_count = User::whereNull('confirmed_at')->count();
        $users_is_email_verified_count = User::whereNotNull('email_verified_at')->count();

        $data = [
            ['title' => 'Gesamt', 'content' => $users_count],
            ['title' => 'Aktiv', 'content' => $users_is_active_count],
            ['title' => 'Mit 2-FA-Authentifizierung', 'content' => $users_is_2fa_count],
            ['title' => 'Mit bestätigter E-Mail', 'content' => $users_is_email_verified_count],
            ['title' => 'Bestätigte', 'content' => $users_is_confirmed_count],
            ['title' => 'Nicht bestätigte', 'content' => $users_is_not_confirmed_count],
        ];

        return $data;
    }

    public function sendVerificationEmail($par_ids)
    // $par_ids ist ein Array von User_IDs oder eine einzelne User-ID
    // an alle diese User wird eine E-Mail-Verifikation gesendet.
    {

        $ids = is_array($par_ids) ? $par_ids : [$par_ids];
        foreach ($ids as $id) {
            $user = User::findOrFail($id);
            $user->sendVerificationEmail();
        }
    }


    public function confirm($par_ids)
    // $par_ids ist ein Array von User_IDs oder eine einzelne User-ID
    // alle diese User sind auf confirmed zu setzen und darüber per E-Mail zu verständigen
    {
        //XXXXXXXXXXXXX
        $ids = is_array($par_ids) ? $par_ids : [$par_ids];


        // Check, how many users are not confirmed
        $count = User::whereIn('id', $ids)
            ->whereNull('confirmed_at')
            ->count();
        if ($count == 0) abort(422, "Alle Benutzer sind bereits bestätigt!");

        foreach ($ids as $id) {
            $user = User::findOrFail($id);
            $user->sendVerificationEmail();
        }
    }

    public function setNewUserRoles($user_ids, $role_ids)
    {
        // set the role_names of each role
        foreach ($role_ids as &$role_id) {
            $role = Role::findOrFail($role_id['id']);
            $role_id['name'] = $role->name;
        }
        unset($role_id);


        // Run all users
        foreach ($user_ids as $id) {
            $user = User::findOrFail($id);

            foreach ($role_ids as $role_id) {

                if ($role_id['role_check'] == 1) {
                    // role should be assigned
                    $user->assignRole($role_id['name']);
                }

                if ($role_id['role_check'] == 2) {
                    // role should be removed
                    $user->removeRole($role_id['name']);
                }
            }
        }
    }

    public function check2Fa($user, $is_2fa, $email_2fa)
    {
        if (! $is_2fa) {
            return TwoFaResult::TWO_FA_DELETE;
        } // No 2-FA wanted

        // ** 2-FA-WANTED ...

        // 2-FA-E-Mail is the same, as the user entered and is verified => everything ok
        if ($email_2fa == $user->email) {
            return TwoFaResult::TWO_FA_EMAIL_AND_2FA_EMAIL_MUST_NOT_BE_EQUAL;
        }

        // 2-FA-E-Mail is the same, as the user entered and is verified => everything ok
        if ($user->email_2fa == $email_2fa && $user->email_2fa_verified_at) {
            return TwoFaResult::TWO_FA_OK;
        }

        // 2-FA-E-Mail is the same, as the user entered, but is not verified => 2FA-EMAIL must be verified
        if ($user->email_2fa == $email_2fa && ! $user->email_2fa_verified_at) {
            return TwoFaResult::TWO_FA_EMAIL_MUST_BE_VERIFIED;
        }

        // 2FA-Email doesnt exists
        if (! $email_2fa) {
            return TwoFaResult::TWO_FA_ERROR;
        }

        // 2-FA-E-Mail is new and sure not verified, because it is new ;)
        return TwoFaResult::TWO_FA_EMAIL_IS_NEW;
    }

    public function check2FaStep2($result, $user, $email_2fa)
    {
        switch ($result) {
            case TwoFaResult::TWO_FA_DELETE:
                // Delete 2-FA-Authentication
                User::where('email', $user->email)->update([
                    'is_2fa' => false,
                    'email_2fa' => null,
                    'email_2fa_verified_at' => null
                ]);

                break;

            case TwoFaResult::TWO_FA_OK:
                // 2-FA: yes, email exists and is verified
                User::where('email', $user->email)->update([
                    'is_2fa' => true,
                    'email_2fa' => $user->email_2fa
                ]);

                break;

            case TwoFaResult::TWO_FA_EMAIL_MUST_BE_VERIFIED:
                // 2-FA: yes, email exists and is not verified
                $this->send2FaCode($user, $email_2fa);

                break;

            case TwoFaResult::TWO_FA_EMAIL_IS_NEW:
                // 2-FA: yes, email is new and must be verified

                $this->send2FaCode($user, $email_2fa);

                break;
        }
    }

    public function update2Fa($user, $email_2fa)
    {

        User::where('email', $user->email)->update(
            [
                'is_2fa' => true,
                'email_2fa' => $email_2fa,
                'email_2fa_verified_at' => now()
            ]
        );

        return TwoFaResult::TWO_FA_SET;
    }

    private function send2FaCode($user, $email_2fa)
    {
        $token_2fa = $user->setToken2Fa(config('spa.token_expire_time'), 1);

        $data = [
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME'),
            'logo' =>  asset('/storage/images/' . config('schooltool.logo')),
            'subject' => 'Code zum Bestätigen der E-Mail',
            'markdown' => 'mails.admin.sendCode',
            'token_2fa' => $token_2fa,
            'token-expire-time' => config('spa.token_expire_time'),
        ];
        Notification::route('mail', $email_2fa)->notify(new StandardEmail($data));
    }
}
