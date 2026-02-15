<?php

namespace App\Services;

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\Schoolyear;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SchoolyearService
{

    public function setToUser($user, $schoolyear_id): Schoolyear
    {
        $schoolyear = Schoolyear::findOrFail($schoolyear_id);

        if ((int) $schoolyear->school_id !== (int) $user->school_id) {
            throw new HttpException(403, 'Sie haben keine Berechtigung');
        }

        $user->schoolyear_id = $schoolyear->id;
        $user->save();

        return $schoolyear;
    }
}
