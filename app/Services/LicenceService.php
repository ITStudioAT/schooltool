<?php

namespace App\Services;

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use Carbon\Carbon;
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

    public function schoolAddLicence($school, $data): SchoolLicence
    {
        $school_licence = SchoolLicence::updateOrCreate([
            'school_id' => $school['id'],
            'licence_id' => $data['licence_id'],
        ], [
            'valid_until' => $data['valid_until'],
        ]);

        return $school_licence;
    }

    public function deleteLicences($ids)
    {

        // Wenn die Schule eine Lizenz zugeordnet hat, kann nicht gelöscht werden
        if (SchoolLicence::whereIn('licence_id', $ids)->exists()) abort(409, "Mindest eine Lizenz ist noch einer Schule zugeordnet");

        Licence::whereIn('id', $ids)->delete();
    }

    public function checkLicence($school, $licence_load)
    {
        $licence = Licence::where('name', $licence_load)->first();
        if (!$licence) return ['status' => 'error', 'msg' => 'Die Lizenz konnte nicht gefunden werden.'];

        $school_licence = SchoolLicence::where('school_id', $school->id)->where('licence_id', $licence->id)->first();
        if (!$school_licence) return ['status' => 'error', 'msg' => 'Die Schule hat für die App keine Lizenz.'];

        if (Carbon::parse($school_licence->valid_until)->isPast()) return ['status' => 'error', 'msg' => 'Die Lizenz für die App ist abgelaufen.'];

        return ['status' => 'ok', 'redirect' => '&licence=' . $licence_load];
    }
}
