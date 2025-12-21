<?php

namespace App\Services;

use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\StandardEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;



class TeacherListService
{

    public function create($school_id, $data)
    {

        $short = $data['short'];
        $email = $data['email'];

        // Prüfen, ob Lehrer-Kurzeichen bereits vergeben ist
        $teacher = Teacher::where('school_id', $school_id)->where('short', $short)->first();
        if ($teacher) abort(409, 'Das Kurzzeichen wird bereits verwendet');

        // Prüfen, ob E-Mail bereits vergeben ist
        $teacher = Teacher::where('school_id', $school_id)->where('email', $email)->first();
        if ($teacher) abort(409, 'Die E-Mail-Adresse wird bereits verwendet');

        $data['school_id'] = $school_id;

        $teacher = Teacher::create($data);


        return $teacher;
    }

    public function update($school_id, $data)
    {

        // Prüfen, ob User existiert
        $teacher = Teacher::findOrFail($data['id']);

        // Prüfen, ob die Update-Daten id + school_id vorhanden sind
        if ($teacher->school_id != $school_id) abort(401, 'Diese Änderung kann nicht durchgeführt werden.');

        // Prüfen, ob sich short verändert hat und wenn ja, ob short noch nicht vergeben ist
        if ($data['short'] != $teacher->short) {
            if (Teacher::where('school_id', $school_id)->whereNot('id', $data['id'])->where('short', $data['short'])->exists()) abort(409, 'Das Kurzzeichen des Lehrers existiert bereits.');
        }

        // Prüfen, ob sich E-Mail verändert hat und wenn ja, ob E-Mail noch nicht vergeben ist
        if ($data['email'] != $teacher->email) {
            if (Teacher::where('school_id', $school_id)->whereNot('id', $data['id'])->where('email', $data['email'])->exists()) abort(409, 'Die E-Mail des Lehrers existiert bereits.');
        }

        $teacher->update([
            'short' => $data['short'],
            'last_name' => $data['last_name'],
            'first_name' => $data['first_name'],
            'email' => $data['email'],
        ]);

        return $teacher;
    }

    public function deleteTeachers($school_id, $ids)
    {

        foreach ($ids as $id) {
            $this->deleteTeacher($school_id, $id);
        }
    }

    private function deleteTeacher($school_id, $id)
    {

        $teacher = Teacher::findOrFail($id);

        // school_id stimmt mit Benutzer nicht überein
        if ($teacher->school_id != $school_id) return false;

        // Teacher löschen
        $teacher->delete();
    }

    public function getAllTeachersNotInUsers($email)
    {
        // Alle Lehrer mit dieser E-Mail finden
        $teachers = Teacher::where('email', $email)->get();

        // Nur Lehrer zurückgeben, die noch nicht als User mit gleicher E-Mail und school_id existieren
        $teachers = $teachers->filter(function ($teacher) {
            return !User::where('email', $teacher->email)
                ->where('school_id', $teacher->school_id)
                ->exists();
        });

        if ($teachers->count() > 0) {
            // Lehrer vorhanden
            // Zugehörige Schulen laden
            $schoolIds = $teachers->pluck('school_id')->unique();
            $schools = \App\Models\School::whereIn('id', $schoolIds)->get();

            if ($schools->count() == 1) {
                $school = $schools->first();
                $schools = null;
                $step = 'NEW_TEACHER_INPUT_CODE';
            } else {
                $school = null;
                $step = 'NEW_TEACHER_SELECT_SCHOOL';
            }
        } else {
            // Kein Lehrer vorhanden
            $step = 'NEW_TEACHER_NO_TEACHER';
            $school = null;
            $schools = null;
        }

        $data = [
            'step' => $step,
            'email' => $email,
            'school' => $school,
            'schools' => $schools,
        ];

        return $data;
    }

    public function sendCode($school_id, $email)
    {
        $teacher = Teacher::where('school_id', $school_id)->where('email', $email)->first();

        $teacher->token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $teacher->token_expires_at = now()->addMinutes(config('schooltool.token_expire_time'));
        $teacher->save();

        $data = [
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME'),
            'logo' =>  asset('/storage/images/' . config('schooltool.logo')),
            'subject' => 'Code zum Bestätigen Ihrer Anmeldung',
            'markdown' => 'mails.admin.sendCode',
            'token_2fa' => $teacher->token,
            'token-expire-time' => config('spa.token_expire_time'),
        ];
        Notification::route('mail', $email)->notify(new StandardEmail($data));
    }

    public function checkToken($user, $should_token): string
    {
        // Check if the token matches and is still valid
        if ($user->token == $should_token && now()->isBefore($user->token_expires_at)) {
            return true; // Token is valid and not expired
        }

        return false; // Token is invalid or expired
    }

    public function createUserFromTeacher($data)
    {

        $teacher = Teacher::where('school_id', $data['school_id'])->where('email', $data['email'])->first();

        $user = User::create([
            'school_id' => $teacher->school_id,
            'short' => $teacher->short,
            'last_name' => $teacher->last_name,
            'first_name' => $teacher->first_name,
            'email' => $teacher->email,
            'email_verified_at' => now(),
            'password' => Hash::make(now()),
            'confirmed_at' => now(),
            'login_at' => now(),
            'login_ip' => request()->ip()
        ]);

        return $user;
    }
}
