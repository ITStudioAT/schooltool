<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Hash;



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
}
