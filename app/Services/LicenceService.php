<?php

namespace App\Services;

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use Spatie\Permission\Models\Role;

class LicenceService
{

    public function selectableSchoolLicences($school)
    {
        return SchoolLicence::where('school_id', $school->id)->get();
    }

    public function isLicenceValid($school, $app): bool
    // $school: School-Objekt
    // $app: String mit dem App-Namen
    {
        // Checken, ob Schule existiert
        if (!$school) return false;

        // Checken, ob es die Lizenz für die App überhaupt gibt
        if (!$licence = Licence::where('name', $app)->first()) return false;

        // Checken, ob es die Lizenz für die Schule gibt gibt
        if (!$schoolLicence = $school->licences()->where('licence_id', $licence->id)->first()) return false;

        // Prüfen, ob die Lizenz gültig ist (kein Datum = unendlich gültig)
        if (!($schoolLicence->valid_until == null || $schoolLicence->valid_until->isFuture())) return false;

        return true;
    }
}
