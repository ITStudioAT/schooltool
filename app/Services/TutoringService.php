<?php

namespace App\Services;

use App\Http\Resources\Homepage\SchoolResource;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

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

    public function createUser($data)
    {
        $data = $this->checkEmail($data);
        if ($data['status'] != 'NEW_USER') abort(409, "Der Benutzer existiert bereits.");

        unset($data['status']);
        $data['password'] = Hash::make(now());
    }
}
