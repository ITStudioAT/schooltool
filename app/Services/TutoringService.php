<?php

namespace App\Services;

use App\Http\Resources\Homepage\SchoolResource;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\User;
use App\Notifications\StandardEmail;
use DragonCode\Support\Facades\Helpers\Arr as HelpersArr;
use DragonCode\Support\Helpers\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class TutoringService
{

    public function checkEmail($data)
    {
        // Prüft, ob die Email existiert
        // $data['status']
        // NEW_USER => neuen User anlegen
        // USER_FOUND => es existiert genau ein User in einer Schule
        $email = $data['email'];
        $user = User::where('school_id', $data['school_id'])->where('email', $email)->first();


        if (!$user) {
            $data['status'] = 'NEW_USER';
        } else {
            $data['status'] = 'USER_FOUND';
            $data['user_id'] = $user->id;
        }

        return $data;
    }

    public function createUser($data): User
    {
        // Neuen User anlegen
        $data = $this->checkEmail($data);
        if ($data['status'] != 'NEW_USER') abort(409, "Der Benutzer existiert bereits.");

        unset($data['status']);
        $data['password'] = Hash::make(now());

        $user = User::create($data);


        return $user;
    }

    public function assignTutoringRole($user_id): User
    {
        $user = User::findOrFail($user_id);
        $user->assignRole('tutoring_user');
        return $user;
    }

    public function sendCodeToUser($user)
    {
        // User Code senden für E-Mail-Verifikation
        $token_2fa = random_int(100000, 999999);
        $token_2fa_expires_at = now()->addMinutes(config('schooltool.token_expire_time'));
        $user->token_2fa = $token_2fa;
        $user->token_2fa_expires_at = $token_2fa_expires_at;
        $user->save();

        $school = School::findOrFail($user->school_id);

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/' . $school->logo),
            'subject' => 'E-Mail bestätigen',
            'markdown' => 'mails.homepage.sendCode',
            'token_2fa' =>  $token_2fa,
            'token-expire-time' => config('schooltool.token_expire_time')
        ];

        Notification::route('mail', $user->email)->notify(new StandardEmail($mail));
    }

    public function confirmEmail($data)
    {
        // E-Mail-Verifikation
        $user_id = $data['user_id'];
        $user = User::findOrFail($user_id);

        if ($user->token_2fa == $data['token_2fa'] && $user->token_2fa_expires_at && $user->token_2fa_expires_at->isFuture()) {
            // Token ist noch gültig
            $user->email_verified_at = now();
            $user->save();
            $data['status'] = 'EMAIL_VERIFIED';
        } else {
            $this->sendCodeToUser($user);
            $data['status'] = 'CONFIRM_EMAIL_AGAIN';
        }
        \Debugbar::info('TutoringService->confirmEmail', $data);
        return $data;
    }

    public function checkUserConfirmation($data)
    {
        $user = User::findOrFail($data['user_id']);
        if (!$user->confirmed_at) {
            $schoolTool = SchoolTool::findOrFail(1);
            if ($schoolTool->tutoring_student_must_be_confirmed) {
                // User muss gemäß Konfiguration confirmed werden
                $data['status'] = 'USER_MUST_BE_CONFIRMED';


                if ($schoolTool->tutoring_confirmer_email) {
                    // Information an Bestätiger schicken
                    $token_2fa_2 = Str::uuid()->toString();
                    $user->token_2fa_2 = $token_2fa_2;
                    $user->token_2fa_2_expires_at = null;
                    $user->save();

                    $school = School::findOrFail($user->school_id);

                    $mail = [
                        'from_address' => config('schooltool.noreply_email'),
                        'from_name' => $school->long_name,
                        'logo' => asset('/storage/images/' . $school->logo),
                        'subject' => 'Tutoring-User bestätigen',
                        'markdown' => 'mails.admin.confirmTutoringUser',
                        'full_name' => $user->last_name . ' ' . $user->first_name,
                        'email' => $user->email,
                        'confirmation_url' => url('/homepage/tutoring/confirm-user?' . http_build_query([
                            'user_id' => $user->id,
                            'token' => $token_2fa_2
                        ])),
                    ];

                    Notification::route('mail', $schoolTool->tutoring_confirmer_email)->notify(new StandardEmail($mail));
                }
            }
        } else {
            // Keine Bestätigung notwendig ==> confirmed_at auf now() setzen
            $user->confirmed_at = now();
            $user->save();
        }

        return $data;
    }

    public function confirmUser($user_id, $uuid): bool
    {
        $user = User::findOrFail($user_id);

        if (!$user->confirmed_at && $user->token_2fa_2 == $uuid) {
            // Benutzer bestätigen
            $user->confirmed_at = now();
            $user->token_2fa_2 = null;
            $user->save();


            // E-Mail zur Info schicken 
            $school = School::findOrFail($user->school_id);
            $mail = [
                'from_address' => config('schooltool.noreply_email'),
                'from_name' => $school->long_name,
                'logo' => asset('/storage/images/' . $school->logo),
                'subject' => 'Nachhilfe freigeschatet',
                'markdown' => 'mails.admin.informTutoringUserIsConfirmed',
                'full_name' => $user->last_name . ' ' . $user->first_name,
                'email' => $user->email,
                'login_url' => url('/homepage/tutoring?school=' . $school->short_name),
            ];

            Notification::route('mail', $user->email)->notify(new StandardEmail($mail));


            return true;
        }

        return false;
    }

    public function checkLoginRequirement($data)
    {

        $user = User::findOrFail($data['user_id']);

        if (!$user->is_active) {
            // User nicht aktiv
            $data['status'] = 'USER_INACTIVE';
            return $data;
        }

        if (!$user->email_verified_at) {
            // User E-Mail noch nicht bestätigt
            $this->sendCodeToUser($user);
            $data['status'] = 'CONFIRM_EMAIL';
            return $data;
        }

        $data = $this->checkUserConfirmation($data);
        return $data;
    }

    public function unknownPassword($data)
    {
        $user = User::findOrFail($data['user_id']);
        $school = School::findOrFail($user->school_id);

        // User Code senden für Login ohne Password
        $token_2fa = random_int(100000, 999999);
        $token_2fa_expires_at = now()->addMinutes(config('schooltool.token_expire_time'));
        $user->token_2fa = $token_2fa;
        $user->token_2fa_expires_at = $token_2fa_expires_at;
        $user->save();

        $mail = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school->long_name,
            'logo' => asset('/storage/images/' . $school->logo),
            'subject' => 'Login mit Code',
            'markdown' => 'mails.homepage.sendCode',
            'token_2fa' =>  $token_2fa,
            'token-expire-time' => config('schooltool.token_expire_time')
        ];

        Notification::route('mail', $user->email)->notify(new StandardEmail($mail));

        $data['status'] = 'LOGIN_WITH_TOKEN';
        return $data;
    }

    public function loginWithToken($data)
    {

        $user_id = $data['user_id'];
        $user = User::findOrFail($user_id);

        if ($user->token_2fa == $data['token_2fa'] && $user->token_2fa_expires_at && $user->token_2fa_expires_at->isFuture()) {

            $user->login_at = now();
            $user->login_ip = request()->ip();
            $user->save();
            Auth::guard('web')->login($user, true);
            session()->regenerate();
            $data['status'] = 'LOGGED_IN';
        } else {
            $data = $this->unknownPassword($data);
            $data['status'] = 'RETRY_LOGIN_WITH_TOKEN';
        }

        return $data;
    }
}
