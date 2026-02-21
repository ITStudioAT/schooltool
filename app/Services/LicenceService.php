<?php

namespace App\Services;

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use Carbon\Carbon;

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

        if ($schoolLicence->pivot->valid_until === null) {
            return true; // Unendlich gültig
        }


        // Schritt 4: Datum vergleichen
        return $schoolLicence->pivot->valid_until >= Carbon::today()->toDateString();
    }

    public function schoolAddLicence($school, $data): SchoolLicence
    {
        $licence = Licence::findOrFail((int) $data['licence_id']);
        $templateModel = $this->normalizeLicenceModel($licence->licence_model);

        $school_licence = SchoolLicence::where('school_id', $school['id'])
            ->where('licence_id', (int) $data['licence_id'])
            ->first();

        if ($school_licence) {
            $school_licence->valid_until = $data['valid_until'];
            if ($school_licence->licence_model === null) {
                $school_licence->licence_model = $templateModel;
            }
            $school_licence->save();

            return $school_licence;
        }

        $school_licence = SchoolLicence::create([
            'school_id' => $school['id'],
            'licence_id' => (int) $data['licence_id'],
            'valid_until' => $data['valid_until'],
            'licence_model' => $templateModel,
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

    public function saveLicenceModel(Licence $licence, array $licenceModel): Licence
    {
        $licence->licence_model = $this->normalizeLicenceModel($licenceModel);
        $licence->save();

        return $licence;
    }

    public function normalizeLicenceModel($licenceModel): array
    {
        $default = $this->defaultLicenceModel();

        if (!is_array($licenceModel)) {
            return $default;
        }

        $schoolLicenceRequired = $this->toBool($licenceModel['school_licence_required'] ?? $default['school_licence_required'], true);

        $affectedRolesRaw = $licenceModel['affected_roles'] ?? [];
        $affectedRoles = [];
        if (is_array($affectedRolesRaw)) {
            foreach ($affectedRolesRaw as $role) {
                if (!is_string($role)) {
                    continue;
                }
                $role = trim($role);
                if ($role === '' || in_array($role, $affectedRoles, true)) {
                    continue;
                }
                $affectedRoles[] = $role;
            }
        }

        $roleRequirementsRaw = $licenceModel['user_licence_required_by_role'] ?? [];
        $roleRequirementsRaw = is_array($roleRequirementsRaw) ? $roleRequirementsRaw : [];
        $roleRequirements = [];

        foreach ($affectedRoles as $roleName) {
            $roleRequirements[$roleName] = $this->toBool($roleRequirementsRaw[$roleName] ?? false, false);
        }

        return [
            'school_licence_required' => $schoolLicenceRequired,
            'affected_roles' => $affectedRoles,
            'user_licence_required_by_role' => $roleRequirements,
        ];
    }

    private function defaultLicenceModel(): array
    {
        return [
            'school_licence_required' => true,
            'affected_roles' => [],
            'user_licence_required_by_role' => [],
        ];
    }

    private function toBool($value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) ((int) $value);
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'ja'], true)) return true;
            if (in_array($normalized, ['0', 'false', 'no', 'nein'], true)) return false;
        }

        return $default;
    }
}
