<?php

namespace App\Services;

use App\Models\Licence;
use App\Models\Register;
use App\Models\School;

use App\Models\SchoolLicence;
use Spatie\Permission\Models\Role;

class RegisterService
{

    public function setToUser($user, $register_id): Register
    {
        $register = Register::findOrFail($register_id);

        $user->register_id = $register->id;
        $user->save();

        return $register;
    }

    public function toggle($register_id): Register
    {
        $register = Register::findOrFail($register_id);

        $register->is_active = !$register->is_active;
        $register->save();

        return $register;
    }
}
