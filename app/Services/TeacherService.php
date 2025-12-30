<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;



class TeacherService
{

    public function create($school_id, $data)
    {

        $short = $data['short'];
        $email = $data['email'];

        // Prüfen, ob Lehrer-Kurzeichen bereits vergeben ist
        $user = User::where('school_id', $school_id)->where('short', $short)->first();
        if ($user) abort(409, 'Das Kurzzeichen wird bereits verwendet');

        // Prüfen, ob E-Mail bereits vergeben ist
        $user = User::where('school_id', $school_id)->where('email', $email)->first();
        if ($user) abort(409, 'Die E-Mail-Adresse wird bereits verwendet');

        $data['school_id'] = $school_id;
        $data['email_verified_at'] = now();
        $data['confirmed_at'] = now();
        $data['password'] = Hash::make(now());

        $user = User::create($data);
        $user->assignRole('teacher');

        return $user;
    }

    public function update($auth_user, $data)
    {
        $school_id = $auth_user->school_id;

        // Prüfen, ob User existiert
        $user = User::findOrFail($data['id']);

        if ($user->hasRole('super_admin') && $user->id != $data['id']) abort(401, "Ein anderer Lehrer kann nicht gespeichrt werden, wenn dieser Super-Admin ist.");

        // Prüfen, ob die Update-Daten id + school_id vorhanden sind
        if ($user->school_id != $school_id) abort(401, 'Diese Änderung kann nicht durchgeführt werden.');

        // Prüfen, ob sich short verändert hat und wenn ja, ob short noch nicht vergeben ist
        if ($data['short'] != $user->short) {
            if (User::where('school_id', $school_id)->whereNot('id', $data['id'])->where('short', $data['short'])->exists()) abort(409, 'Das Kurzzeichen des Lehrers existiert bereits.');
        }

        // Prüfen, ob sich E-Mail verändert hat und wenn ja, ob E-Mail noch nicht vergeben ist
        if ($data['email'] != $user->email) {
            if (User::where('school_id', $school_id)->whereNot('id', $data['id'])->where('email', $data['email'])->exists()) abort(409, 'Die E-Mail des Lehrers existiert bereits.');
        }

        $user->update([
            'short' => $data['short'],
            'last_name' => $data['last_name'],
            'first_name' => $data['first_name'],
            'email' => $data['email'],
        ]);

        return $user;
    }

    public function deleteTeachers($school_id, $ids)
    {

        foreach ($ids as $id) {
            $this->deleteTeacher($school_id, $id);
        }
    }

    private function deleteTeacher($school_id, $id)
    {

        $user = User::findOrFail($id);



        // school_id stimmt mit Benutzer nicht überein
        if ($user->school_id != $school_id) return false;

        // Der Benutzer hat Abhängigkeiten
        if ($user->hasDependencies()) return false;

        // Prüfen, ob der Benutzer genau nur die Rolle teacher hat
        if ($user->roles()->count() != 1 || !$user->hasRole('teacher')) return false;

        // Rollen löschen
        $user->roles()->detach();

        // User löschen
        $user->delete();
    }
}
