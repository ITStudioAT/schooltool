<?php

namespace App\Services;

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\Schoolyear;
use Spatie\Permission\Models\Role;

class SchoolyearService
{

    public function setToUser($user, $schoolyear_id): Schoolyear
    {
        $schoolyear = Schoolyear::findOrFail($schoolyear_id);

        $user->schoolyear_id = $schoolyear->id;
        $user->save();

        return $schoolyear;
    }
}
