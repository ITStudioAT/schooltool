<?php

namespace App\Services;

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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
        return $this->licenceStatus($school, $app) === 'active';
    }

    public function licenceStatus($school, $app): string
    {
        if (! $school) {
            return 'missing';
        }

        $licence = Licence::where('name', $app)->first();
        if (! $licence) {
            return 'missing';
        }

        $schoolLicence = SchoolLicence::where('school_id', $school->id)
            ->where('licence_id', $licence->id)
            ->first();

        if (! $schoolLicence) {
            return 'missing';
        }

        return $this->schoolLicenceStatus($schoolLicence, $licence);
    }

    public function schoolLicenceStatus(?SchoolLicence $schoolLicence, ?Licence $licence = null): string
    {
        if (! $schoolLicence) {
            return 'missing';
        }

        $licenceModel = $this->effectiveSchoolLicenceModel($schoolLicence, $licence);
        $schoolLicenceRequired = $this->toBool($licenceModel['school_licence_required'] ?? true, true);
        if (! $schoolLicenceRequired) {
            return 'active';
        }

        if ($schoolLicence->valid_until === null) {
            return 'active';
        }

        try {
            $validUntil = Carbon::parse($schoolLicence->valid_until)->toDateString();
        } catch (\Throwable $e) {
            // Fail closed on malformed dates for security-sensitive checks.
            return 'expired';
        }

        return $validUntil >= Carbon::today()->toDateString() ? 'active' : 'expired';
    }

    public function selectableSchoolLicenceOverview(string $app): array
    {
        $licence = Licence::where('name', $app)->first();
        $schools = School::selectables()->get();

        if (! $licence) {
            return [
                'licence' => null,
                'schools' => $schools,
                'active_schools' => collect(),
                'status_by_school_id' => collect(),
                'overall_status' => 'missing',
            ];
        }

        $schoolIds = $schools->pluck('id');

        $schoolLicences = $schoolIds->isEmpty()
            ? collect()
            : SchoolLicence::query()
                ->where('licence_id', $licence->id)
                ->whereIn('school_id', $schoolIds)
                ->get()
                ->keyBy('school_id');

        $statusBySchoolId = $schools->mapWithKeys(function (School $school) use ($schoolLicences, $licence) {
            /** @var SchoolLicence|null $schoolLicence */
            $schoolLicence = $schoolLicences->get($school->id);

            return [$school->id => $this->schoolLicenceStatus($schoolLicence, $licence)];
        });

        $activeSchools = $schools
            ->filter(fn(School $school) => $statusBySchoolId->get($school->id) === 'active')
            ->values();

        $hasAnySchoolWithLicence = $statusBySchoolId
            ->contains(fn(string $status) => $status !== 'missing');

        $overallStatus = 'missing';
        if ($activeSchools->isNotEmpty()) {
            $overallStatus = 'active';
        } elseif ($hasAnySchoolWithLicence) {
            $overallStatus = 'expired';
        }

        return [
            'licence' => $licence,
            'schools' => $schools,
            'active_schools' => $activeSchools,
            'status_by_school_id' => $statusBySchoolId,
            'overall_status' => $overallStatus,
        ];
    }

    public function selectableSchoolsForTool(string $app): array
    {
        $overview = $this->selectableSchoolLicenceOverview($app);
        $licence = $overview['licence'];

        $schools = collect();
        if ($licence && $overview['active_schools']->isNotEmpty()) {
            $activeSchoolIds = $overview['active_schools']->pluck('id');

            $schools = School::selectables()
                ->whereIn('id', $activeSchoolIds)
                ->with(['licences' => function ($query) use ($licence) {
                    $query->where('licences.id', $licence->id);
                }])
                ->get();
        }

        return [
            'licence' => $licence,
            'schools' => $schools,
            'status' => $overview['overall_status'],
        ];
    }

    public function selectableActiveLicencesForSchool(School $school): Collection
    {
        $schoolLicences = SchoolLicence::query()
            ->where('school_id', $school->id)
            ->with('licence')
            ->get();

        return $schoolLicences
            ->filter(function (SchoolLicence $schoolLicence) {
                $licence = $schoolLicence->licence;
                if (! $licence || ! ((bool) $licence->is_selectable)) {
                    return false;
                }

                return $this->schoolLicenceStatus($schoolLicence, $licence) === 'active';
            })
            ->map(function (SchoolLicence $schoolLicence) {
                $licence = $schoolLicence->licence;
                $licence->setRelation('pivot', $schoolLicence);

                return $licence;
            })
            ->sortBy('long_name')
            ->values();
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

        if ($this->licenceStatus($school, $licence_load) !== 'active') {
            return ['status' => 'error', 'msg' => 'Die Lizenz für die App ist abgelaufen.'];
        }

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

    private function effectiveSchoolLicenceModel(SchoolLicence $schoolLicence, ?Licence $licence = null): array
    {
        if (is_array($schoolLicence->licence_model)) {
            return $this->normalizeLicenceModel($schoolLicence->licence_model);
        }

        if ($licence && is_array($licence->licence_model)) {
            return $this->normalizeLicenceModel($licence->licence_model);
        }

        return $this->defaultLicenceModel();
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
