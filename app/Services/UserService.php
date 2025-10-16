<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use App\Enums\TwoFaResult;
use App\Notifications\StandardEmail;

class UserService
{
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
            info($role_id['name']);
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
                $user->is_2fa = false;
                $user->email_2fa = null;
                $user->email_2fa_verified_at = null;
                $user->save();

                break;

            case TwoFaResult::TWO_FA_OK:
                // 2-FA: yes, email exists and is verified
                $user->is_2fa = true;
                $user->save();

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
        $user->is_2fa = true;
        $user->email_2fa = $email_2fa;
        $user->email_2fa_verified_at = now();
        $user->save();

        return TwoFaResult::TWO_FA_SET;
    }

    private function send2FaCode($user, $email_2fa)
    {
        $token_2fa = $user->setToken2Fa(config('spa.token_expire_time'), 1);

        $data = [
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME'),
            'subject' => 'Code zum Bestätigen der E-Mail',
            'markdown' => 'spa::mails.admin.sendCode',
            'token_2fa' => $token_2fa,
            'token-expire-time' => config('spa.token_expire_time'),
        ];
        Notification::route('mail', $email_2fa)->notify(new StandardEmail($data));
    }
}
