<?php

namespace App\Services;

use App\Models\Licence;
use App\Models\LicenceUserPlan;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolUserLicence;
use App\Models\User;
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

        if ($this->usesStructuredLicenceModel($licence)) {
            return $this->structuredSchoolAccessStatus($school, $licence);
        }

        $schoolLicence = SchoolLicence::where('school_id', $school->id)
            ->where('licence_id', $licence->id)
            ->first();

        if (! $schoolLicence) {
            return 'missing';
        }

        return $this->schoolLicenceStatus($schoolLicence, $licence);
    }

    public function toolAccessStatusForUser(?User $user, ?School $school, string $app, array $candidateRoleNames = []): string
    {
        if (! $school) {
            return 'missing';
        }

        $licence = Licence::where('name', $app)->first();
        if (! $licence) {
            return 'missing';
        }

        if ($this->usesStructuredLicenceModel($licence)) {
            return $this->structuredToolAccessStatusForUser($user, $school, $licence, $candidateRoleNames);
        }

        $schoolLicence = SchoolLicence::where('school_id', $school->id)
            ->where('licence_id', $licence->id)
            ->first();

        $schoolStatus = $this->schoolLicenceStatus($schoolLicence, $licence);
        if ($schoolStatus !== 'active') {
            return $schoolStatus;
        }

        if (! $user || ! $schoolLicence) {
            return 'active';
        }

        return $this->userLicenceStatusForTool($user, $schoolLicence, $licence, $candidateRoleNames);
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
            if ($this->usesStructuredLicenceModel($licence)) {
                return [$school->id => $this->structuredSchoolAccessStatus($school, $licence)];
            }

            /** @var SchoolLicence|null $schoolLicence */
            $schoolLicence = $schoolLicences->get($school->id);

            return [$school->id => $this->schoolLicenceStatus($schoolLicence, $licence)];
        });

        $activeSchools = $schools
            ->filter(fn (School $school) => $statusBySchoolId->get($school->id) === 'active')
            ->values();

        $hasAnySchoolWithLicence = $statusBySchoolId
            ->contains(fn (string $status) => $status !== 'missing');

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
            ->get()
            ->keyBy('licence_id');

        return Licence::query()
            ->where('is_selectable', true)
            ->get()
            ->filter(function (Licence $licence) use ($school, $schoolLicences) {
                if ($this->usesStructuredLicenceModel($licence)) {
                    return $this->structuredSchoolAccessStatus($school, $licence) === 'active';
                }

                /** @var SchoolLicence|null $schoolLicence */
                $schoolLicence = $schoolLicences->get($licence->id);

                return $schoolLicence !== null
                    && $this->schoolLicenceStatus($schoolLicence, $licence) === 'active';
            })
            ->map(function (Licence $licence) use ($schoolLicences) {
                /** @var SchoolLicence|null $schoolLicence */
                $schoolLicence = $schoolLicences->get($licence->id);
                if ($schoolLicence) {
                    $licence->setRelation('pivot', $schoolLicence);
                }

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
        if (SchoolLicence::whereIn('licence_id', $ids)->exists()) {
            abort(409, 'Mindestens eine Lizenz ist noch einer Schule zugeordnet und kann nicht gelöscht werden.');
        }

        Licence::whereIn('id', $ids)->delete();
    }

    public function checkLicence($school, $licence_load)
    {
        $licence = Licence::where('name', $licence_load)->first();
        if (! $licence) {
            return ['status' => 'error', 'msg' => 'Die Lizenz konnte nicht gefunden werden.'];
        }

        $school_licence = SchoolLicence::where('school_id', $school->id)->where('licence_id', $licence->id)->first();
        if (! $school_licence) {
            return ['status' => 'error', 'msg' => 'Die Schule hat für die App keine Lizenz.'];
        }

        if ($this->licenceStatus($school, $licence_load) !== 'active') {
            return ['status' => 'error', 'msg' => 'Die Lizenz für die App ist abgelaufen.'];
        }

        return ['status' => 'ok', 'redirect' => '&licence='.$licence_load];
    }

    public function saveLicenceModel(Licence $licence, array $licenceModel): Licence
    {
        if ($this->isStructuredLicenceConfiguration($licenceModel)) {
            $normalizedStructuredConfiguration = $this->normalizeStructuredLicenceConfiguration($licenceModel);

            $licence->licence_schema_version = 2;
            $licence->school_licence_enabled = $normalizedStructuredConfiguration['school_licence_enabled'];
            $licence->school_price_per_year = $normalizedStructuredConfiguration['school_price_per_year'];
            $licence->school_included_storage_gb = $normalizedStructuredConfiguration['school_included_storage_gb'];
            $licence->school_extra_storage_step_gb = $normalizedStructuredConfiguration['school_extra_storage_step_gb'];
            $licence->school_extra_storage_step_price = $normalizedStructuredConfiguration['school_extra_storage_step_price'];
            $licence->admin_licence_enabled = $normalizedStructuredConfiguration['admin_licence_enabled'];
            $licence->admin_price_per_year = $normalizedStructuredConfiguration['admin_price_per_year'];
            $licence->admin_role_names = $normalizedStructuredConfiguration['admin_role_names'];
            $licence->admin_included_storage_gb = $normalizedStructuredConfiguration['admin_included_storage_gb'];
            $licence->admin_extra_storage_step_gb = $normalizedStructuredConfiguration['admin_extra_storage_step_gb'];
            $licence->admin_extra_storage_step_price = $normalizedStructuredConfiguration['admin_extra_storage_step_price'];
            $licence->user_licence_enabled = $normalizedStructuredConfiguration['user_licence_enabled'];
            $licence->user_price_per_year = $normalizedStructuredConfiguration['user_price_per_year'];
            $licence->user_role_names = $normalizedStructuredConfiguration['user_role_names'];
            $licence->user_included_storage_gb = $normalizedStructuredConfiguration['user_included_storage_gb'];
            $licence->user_extra_storage_step_gb = $normalizedStructuredConfiguration['user_extra_storage_step_gb'];
            $licence->user_extra_storage_step_price = $normalizedStructuredConfiguration['user_extra_storage_step_price'];
            $licence->save();
            $licence->refresh();

            return $licence;
        }

        $normalizedModel = $this->normalizeLicenceModel($licenceModel);
        $syncedPlansByRole = $this->syncLicenceUserPlans($licence, $normalizedModel['user_licence_plans_by_role'] ?? []);
        $normalizedModel['user_licence_plans_by_role'] = $syncedPlansByRole;
        $licence->licence_model = $normalizedModel;
        $licence->save();
        $licence->refresh();

        return $licence;
    }

    public function editableLicenceConfiguration(Licence $licence): array
    {
        if ($this->usesStructuredLicenceModel($licence) || $this->licenceHasStructuredConfiguration($licence)) {
            return $this->normalizeStructuredLicenceConfiguration([
                'school_licence_enabled' => $licence->school_licence_enabled,
                'school_price_per_year' => $licence->school_price_per_year,
                'school_included_storage_gb' => $licence->school_included_storage_gb,
                'school_extra_storage_step_gb' => $licence->school_extra_storage_step_gb,
                'school_extra_storage_step_price' => $licence->school_extra_storage_step_price,
                'admin_licence_enabled' => $licence->admin_licence_enabled,
                'admin_price_per_year' => $licence->admin_price_per_year,
                'admin_role_names' => $licence->admin_role_names,
                'admin_included_storage_gb' => $licence->admin_included_storage_gb,
                'admin_extra_storage_step_gb' => $licence->admin_extra_storage_step_gb,
                'admin_extra_storage_step_price' => $licence->admin_extra_storage_step_price,
                'user_licence_enabled' => $licence->user_licence_enabled,
                'user_price_per_year' => $licence->user_price_per_year,
                'user_role_names' => $licence->user_role_names,
                'user_included_storage_gb' => $licence->user_included_storage_gb,
                'user_extra_storage_step_gb' => $licence->user_extra_storage_step_gb,
                'user_extra_storage_step_price' => $licence->user_extra_storage_step_price,
            ]);
        }

        $legacyLicenceModel = $this->normalizeLicenceModel($licence->licence_model);
        $requiredLegacyRoles = collect($legacyLicenceModel['user_licence_required_by_role'] ?? [])
            ->filter(fn ($isRequired) => (bool) $isRequired)
            ->keys()
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->values()
            ->all();

        $adminRoleNames = collect($requiredLegacyRoles)
            ->filter(fn (string $roleName) => $this->looksLikeAdminRoleName($roleName))
            ->values()
            ->all();

        $userRoleNames = collect($requiredLegacyRoles)
            ->filter(fn (string $roleName) => ! $this->looksLikeAdminRoleName($roleName))
            ->values()
            ->all();

        return $this->normalizeStructuredLicenceConfiguration([
            'school_licence_enabled' => $this->toBool($legacyLicenceModel['school_licence_required'] ?? true, true),
            'school_price_per_year' => $licence->price_per_year,
            'school_included_storage_gb' => null,
            'school_extra_storage_step_gb' => null,
            'school_extra_storage_step_price' => null,
            'admin_licence_enabled' => count($adminRoleNames) > 0,
            'admin_price_per_year' => null,
            'admin_role_names' => $adminRoleNames,
            'admin_included_storage_gb' => null,
            'admin_extra_storage_step_gb' => null,
            'admin_extra_storage_step_price' => null,
            'user_licence_enabled' => count($userRoleNames) > 0,
            'user_price_per_year' => null,
            'user_role_names' => $userRoleNames,
            'user_included_storage_gb' => null,
            'user_extra_storage_step_gb' => null,
            'user_extra_storage_step_price' => null,
        ]);
    }

    public function normalizeStructuredLicenceConfiguration(mixed $licenceConfiguration): array
    {
        if (is_string($licenceConfiguration)) {
            $decoded = json_decode($licenceConfiguration, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $licenceConfiguration = $decoded;
            }
        }

        $licenceConfiguration = is_array($licenceConfiguration) ? $licenceConfiguration : [];

        return [
            'school_licence_enabled' => $this->toBool($licenceConfiguration['school_licence_enabled'] ?? true, true),
            'school_price_per_year' => $this->normalizePriceString($licenceConfiguration['school_price_per_year'] ?? null),
            'school_included_storage_gb' => $this->normalizePositiveIntegerString($licenceConfiguration['school_included_storage_gb'] ?? null),
            'school_extra_storage_step_gb' => $this->normalizePositiveIntegerString($licenceConfiguration['school_extra_storage_step_gb'] ?? null),
            'school_extra_storage_step_price' => $this->normalizePriceString($licenceConfiguration['school_extra_storage_step_price'] ?? null),
            'admin_licence_enabled' => $this->toBool($licenceConfiguration['admin_licence_enabled'] ?? false, false),
            'admin_price_per_year' => $this->normalizePriceString($licenceConfiguration['admin_price_per_year'] ?? null),
            'admin_role_names' => $this->normalizeRoleNames($licenceConfiguration['admin_role_names'] ?? []),
            'admin_included_storage_gb' => $this->normalizePositiveIntegerString($licenceConfiguration['admin_included_storage_gb'] ?? null),
            'admin_extra_storage_step_gb' => $this->normalizePositiveIntegerString($licenceConfiguration['admin_extra_storage_step_gb'] ?? null),
            'admin_extra_storage_step_price' => $this->normalizePriceString($licenceConfiguration['admin_extra_storage_step_price'] ?? null),
            'user_licence_enabled' => $this->toBool($licenceConfiguration['user_licence_enabled'] ?? false, false),
            'user_price_per_year' => $this->normalizePriceString($licenceConfiguration['user_price_per_year'] ?? null),
            'user_role_names' => $this->normalizeRoleNames($licenceConfiguration['user_role_names'] ?? []),
            'user_included_storage_gb' => $this->normalizePositiveIntegerString($licenceConfiguration['user_included_storage_gb'] ?? null),
            'user_extra_storage_step_gb' => $this->normalizePositiveIntegerString($licenceConfiguration['user_extra_storage_step_gb'] ?? null),
            'user_extra_storage_step_price' => $this->normalizePriceString($licenceConfiguration['user_extra_storage_step_price'] ?? null),
        ];
    }

    public function normalizeLicenceModel($licenceModel): array
    {
        $default = $this->defaultLicenceModel();

        $licenceModel = $this->parseLicenceModelInput($licenceModel);

        if (! is_array($licenceModel)) {
            return $default;
        }

        $schoolLicenceRequired = $this->toBool($licenceModel['school_licence_required'] ?? $default['school_licence_required'], true);

        $affectedRolesRaw = $licenceModel['affected_roles'] ?? [];
        $affectedRoles = [];
        if (is_array($affectedRolesRaw)) {
            foreach ($affectedRolesRaw as $role) {
                if (! is_string($role)) {
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

        $rolePlansRaw = $licenceModel['user_licence_plans_by_role'] ?? [];
        $rolePlansRaw = is_array($rolePlansRaw) ? $rolePlansRaw : [];
        $rolePlans = [];

        foreach ($affectedRoles as $roleName) {
            $rawPlans = $rolePlansRaw[$roleName] ?? [];
            $rawPlans = is_array($rawPlans) ? $rawPlans : [];

            $plans = [];
            foreach ($rawPlans as $plan) {
                if (! is_array($plan)) {
                    continue;
                }

                $planId = null;
                if (array_key_exists('id', $plan) && is_numeric($plan['id'])) {
                    $planId = (int) $plan['id'];
                }

                $text = trim((string) ($plan['text'] ?? ''));
                $pricePerYear = trim((string) ($plan['price_per_year'] ?? ''));

                // Leere UI-Zeilen nicht persistieren.
                if ($text === '' && $pricePerYear === '') {
                    continue;
                }

                if ($text === '') {
                    continue;
                }

                $normalizedPlan = [
                    'text' => mb_substr($text, 0, 255),
                    'price_per_year' => mb_substr($pricePerYear, 0, 255),
                ];
                if ($planId) {
                    $normalizedPlan['id'] = $planId;
                }

                $plans[] = $normalizedPlan;
            }

            $rolePlans[$roleName] = $plans;

            if (($roleRequirements[$roleName] ?? false) === true && count($rolePlans[$roleName]) === 0) {
                $rolePlans[$roleName][] = $this->defaultUserLicencePlan();
            }
        }

        return [
            'school_licence_required' => $schoolLicenceRequired,
            'affected_roles' => $affectedRoles,
            'user_licence_required_by_role' => $roleRequirements,
            'user_licence_plans_by_role' => $rolePlans,
        ];
    }

    public function mergeLicenceModels(mixed $schoolLicenceModelRaw, mixed $baseLicenceModelRaw): array
    {
        $schoolSourceModel = $this->parseLicenceModelInput($schoolLicenceModelRaw);
        $baseSourceModel = $this->parseLicenceModelInput($baseLicenceModelRaw);

        $schoolModel = $this->normalizeLicenceModel($schoolSourceModel);
        $baseModel = $this->normalizeLicenceModel($baseSourceModel);

        $affectedRoles = collect(array_merge(
            is_array($schoolModel['affected_roles'] ?? null) ? $schoolModel['affected_roles'] : [],
            is_array($baseModel['affected_roles'] ?? null) ? $baseModel['affected_roles'] : []
        ))
            ->map(fn ($role) => is_string($role) ? trim($role) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $schoolRequiredByRoleSource = is_array($schoolSourceModel['user_licence_required_by_role'] ?? null)
            ? $schoolSourceModel['user_licence_required_by_role']
            : [];
        $baseRequiredByRole = is_array($baseModel['user_licence_required_by_role'] ?? null)
            ? $baseModel['user_licence_required_by_role']
            : [];

        $schoolPlansByRole = is_array($schoolModel['user_licence_plans_by_role'] ?? null)
            ? $schoolModel['user_licence_plans_by_role']
            : [];
        $basePlansByRole = is_array($baseModel['user_licence_plans_by_role'] ?? null)
            ? $baseModel['user_licence_plans_by_role']
            : [];

        $requiredByRole = [];
        $plansByRole = [];

        foreach ($affectedRoles as $roleName) {
            $requiredByRole[$roleName] = array_key_exists($roleName, $schoolRequiredByRoleSource)
                ? $this->toBool($schoolRequiredByRoleSource[$roleName], false)
                : (bool) ($baseRequiredByRole[$roleName] ?? false);

            $schoolRolePlans = (array_key_exists($roleName, $schoolPlansByRole) && is_array($schoolPlansByRole[$roleName]))
                ? $schoolPlansByRole[$roleName]
                : [];
            $baseRolePlans = (array_key_exists($roleName, $basePlansByRole) && is_array($basePlansByRole[$roleName]))
                ? $basePlansByRole[$roleName]
                : [];

            $mergedPlans = [];
            $seenPlanKeys = [];
            foreach (array_merge($schoolRolePlans, $baseRolePlans) as $plan) {
                if (! is_array($plan)) {
                    continue;
                }

                $planId = (isset($plan['id']) && is_numeric($plan['id'])) ? (int) $plan['id'] : null;
                $planText = isset($plan['text']) ? trim((string) $plan['text']) : '';
                $planPrice = isset($plan['price_per_year']) ? trim((string) $plan['price_per_year']) : '';
                $planKey = $planId !== null && $planId > 0
                    ? "id:{$planId}"
                    : 'txt:'.$planText.'|price:'.$planPrice;

                if (isset($seenPlanKeys[$planKey])) {
                    continue;
                }

                $seenPlanKeys[$planKey] = true;
                $mergedPlans[] = $plan;
            }

            $plansByRole[$roleName] = $mergedPlans;
        }

        $schoolLicenceRequired = $schoolSourceModel !== null && array_key_exists('school_licence_required', $schoolSourceModel)
            ? $this->toBool($schoolSourceModel['school_licence_required'], true)
            : $this->toBool($baseModel['school_licence_required'] ?? true, true);

        return [
            'school_licence_required' => $schoolLicenceRequired,
            'affected_roles' => $affectedRoles,
            'user_licence_required_by_role' => $requiredByRole,
            'user_licence_plans_by_role' => $plansByRole,
        ];
    }

    private function defaultLicenceModel(): array
    {
        return [
            'school_licence_required' => true,
            'affected_roles' => [],
            'user_licence_required_by_role' => [],
            'user_licence_plans_by_role' => [],
        ];
    }

    private function defaultUserLicencePlan(): array
    {
        return [
            'text' => 'Standard',
            'price_per_year' => '0',
        ];
    }

    private function effectiveSchoolLicenceModel(SchoolLicence $schoolLicence, ?Licence $licence = null): array
    {
        if ($this->parseLicenceModelInput($schoolLicence->licence_model) !== null) {
            return $this->normalizeLicenceModel($schoolLicence->licence_model);
        }

        if ($licence && $this->parseLicenceModelInput($licence->licence_model) !== null) {
            return $this->normalizeLicenceModel($licence->licence_model);
        }

        return $this->defaultLicenceModel();
    }

    private function mergedSchoolLicenceModel(SchoolLicence $schoolLicence, ?Licence $licence = null): array
    {
        return $this->mergeLicenceModels($schoolLicence->licence_model, $licence?->licence_model);
    }

    private function userLicenceStatusForTool(User $user, SchoolLicence $schoolLicence, ?Licence $licence = null, array $candidateRoleNames = []): string
    {
        $licenceModel = $this->mergedSchoolLicenceModel($schoolLicence, $licence);

        $requiredRoleNames = collect($licenceModel['user_licence_required_by_role'] ?? [])
            ->filter(fn ($isRequired) => (bool) $isRequired)
            ->keys()
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($requiredRoleNames)) {
            return 'active';
        }

        $userRoleNames = $user->roles()
            ->pluck('name')
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $candidateRoleNames = collect($candidateRoleNames)
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($candidateRoleNames)) {
            $userRoleNames = array_values(array_intersect($userRoleNames, $candidateRoleNames));
        }

        $relevantRequiredRoles = array_values(array_intersect($userRoleNames, $requiredRoleNames));
        if (empty($relevantRequiredRoles)) {
            // None of the user's relevant roles require a per-user licence assignment.
            return 'active';
        }

        $assignments = is_array($schoolLicence->user_licence_assignments) ? $schoolLicence->user_licence_assignments : [];
        $userAssignments = isset($assignments[(string) $user->id]) && is_array($assignments[(string) $user->id])
            ? $assignments[(string) $user->id]
            : [];

        $hasExpiredAssignment = false;

        foreach ($relevantRequiredRoles as $roleName) {
            $entry = $this->normalizeUserLicenceAssignmentEntry($userAssignments[$roleName] ?? null);

            if (! $entry['is_activated']) {
                continue;
            }

            if ($this->isDateActive($entry['valid_until'])) {
                return 'active';
            }

            $hasExpiredAssignment = true;
        }

        return $hasExpiredAssignment ? 'expired' : 'missing';
    }

    private function structuredToolAccessStatusForUser(?User $user, School $school, Licence $licence, array $candidateRoleNames = []): string
    {
        $schoolStatus = $this->structuredSchoolAccessStatus($school, $licence);
        if ($this->structuredSchoolLicenceEnabled($licence) && $schoolStatus !== 'active') {
            return $schoolStatus;
        }

        if (! $user) {
            return 'active';
        }

        $relevantAssignmentTypes = $this->structuredRelevantAssignmentTypes($user, $licence, $candidateRoleNames);
        if (empty($relevantAssignmentTypes)) {
            return 'active';
        }

        $assignmentsByType = SchoolUserLicence::query()
            ->where('school_id', $school->id)
            ->where('licence_id', $licence->id)
            ->where('user_id', $user->id)
            ->whereIn('assignment_type', $relevantAssignmentTypes)
            ->orderBy('valid_until')
            ->get()
            ->groupBy('assignment_type');

        $hasExpiredAssignment = false;

        foreach ($relevantAssignmentTypes as $assignmentType) {
            $status = $this->structuredUserAssignmentCollectionStatus($assignmentsByType->get($assignmentType, collect()));

            if ($status === 'active') {
                return 'active';
            }

            if ($status === 'expired') {
                $hasExpiredAssignment = true;
            }
        }

        return $hasExpiredAssignment ? 'expired' : 'missing';
    }

    private function structuredSchoolAccessStatus(School $school, Licence $licence): string
    {
        if (! $this->structuredSchoolLicenceEnabled($licence)) {
            return 'active';
        }

        $schoolLicence = SchoolLicence::query()
            ->where('school_id', $school->id)
            ->where('licence_id', $licence->id)
            ->first();

        if (! $schoolLicence) {
            return 'missing';
        }

        return $this->datedAccessStatus($schoolLicence->valid_until);
    }

    private function structuredRelevantAssignmentTypes(User $user, Licence $licence, array $candidateRoleNames = []): array
    {
        $userRoleNames = $user->roles()
            ->pluck('name')
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $candidateRoleNames = collect($candidateRoleNames)
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($candidateRoleNames)) {
            $userRoleNames = array_values(array_intersect($userRoleNames, $candidateRoleNames));
        }

        $assignmentTypes = [];

        if ($this->toBool($licence->admin_licence_enabled ?? false, false)) {
            $adminRoleNames = $this->normalizeRoleNames($licence->admin_role_names ?? []);
            if ($this->configuredRolesMatchUserRoles($userRoleNames, $adminRoleNames)) {
                $assignmentTypes[] = 'admin';
            }
        }

        if ($this->toBool($licence->user_licence_enabled ?? false, false)) {
            $userRoleNamesForLicence = $this->normalizeRoleNames($licence->user_role_names ?? []);
            if ($this->configuredRolesMatchUserRoles($userRoleNames, $userRoleNamesForLicence)) {
                $assignmentTypes[] = 'user';
            }
        }

        return array_values(array_unique($assignmentTypes));
    }

    private function structuredUserAssignmentCollectionStatus(Collection $assignments): string
    {
        if ($assignments->isEmpty()) {
            return 'missing';
        }

        $hasExpiredAssignment = false;

        foreach ($assignments as $assignment) {
            if (! $assignment instanceof SchoolUserLicence) {
                continue;
            }

            if (! $assignment->is_active) {
                continue;
            }

            $status = $this->datedAccessStatus(
                $assignment->valid_until?->format('Y-m-d') ?? null,
                $assignment->valid_from?->format('Y-m-d') ?? null
            );

            if ($status === 'active') {
                return 'active';
            }

            if ($status === 'expired') {
                $hasExpiredAssignment = true;
            }
        }

        return $hasExpiredAssignment ? 'expired' : 'missing';
    }

    private function structuredSchoolLicenceEnabled(Licence $licence): bool
    {
        return $this->toBool($licence->school_licence_enabled ?? true, true);
    }

    private function usesStructuredLicenceModel(?Licence $licence): bool
    {
        return $licence !== null
            && isset($licence->licence_schema_version)
            && (int) $licence->licence_schema_version >= 2;
    }

    private function licenceHasStructuredConfiguration(Licence $licence): bool
    {
        return $licence->school_price_per_year !== null
            || $licence->school_included_storage_gb !== null
            || $licence->school_extra_storage_step_gb !== null
            || $licence->school_extra_storage_step_price !== null
            || $licence->admin_price_per_year !== null
            || $licence->admin_included_storage_gb !== null
            || $licence->admin_extra_storage_step_gb !== null
            || $licence->admin_extra_storage_step_price !== null
            || $licence->user_price_per_year !== null
            || $licence->user_included_storage_gb !== null
            || $licence->user_extra_storage_step_gb !== null
            || $licence->user_extra_storage_step_price !== null
            || ! empty($this->normalizeRoleNames($licence->admin_role_names ?? []))
            || ! empty($this->normalizeRoleNames($licence->user_role_names ?? []))
            || $this->toBool($licence->admin_licence_enabled ?? false, false)
            || $this->toBool($licence->user_licence_enabled ?? false, false);
    }

    private function normalizeUserLicenceAssignmentEntry($entry): array
    {
        if (is_string($entry)) {
            $validUntil = trim($entry);

            return [
                'valid_until' => $validUntil !== '' ? $validUntil : null,
                'is_activated' => false,
                'plan_id' => null,
            ];
        }

        if (! is_array($entry)) {
            return [
                'valid_until' => null,
                'is_activated' => false,
                'plan_id' => null,
            ];
        }

        $rawValidUntil = $entry['valid_until'] ?? null;
        $validUntil = is_string($rawValidUntil) && trim($rawValidUntil) !== '' ? trim($rawValidUntil) : null;
        $planId = isset($entry['plan_id']) && is_numeric($entry['plan_id']) && (int) $entry['plan_id'] > 0
            ? (int) $entry['plan_id']
            : null;

        return [
            'valid_until' => $validUntil,
            'is_activated' => (bool) ($entry['is_activated'] ?? false),
            'plan_id' => $planId,
        ];
    }

    private function isDateActive(?string $validUntil): bool
    {
        if (! is_string($validUntil) || trim($validUntil) === '') {
            return true;
        }

        try {
            return Carbon::parse(trim($validUntil))->toDateString() >= Carbon::today()->toDateString();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function datedAccessStatus(?string $validUntil, ?string $validFrom = null): string
    {
        if ($validFrom !== null && trim($validFrom) !== '') {
            try {
                if (Carbon::parse(trim($validFrom))->toDateString() > Carbon::today()->toDateString()) {
                    return 'missing';
                }
            } catch (\Throwable $e) {
                return 'expired';
            }
        }

        if ($validUntil === null || trim($validUntil) === '') {
            return 'active';
        }

        try {
            $normalizedValidUntil = Carbon::parse(trim($validUntil))->toDateString();
        } catch (\Throwable $e) {
            return 'expired';
        }

        return $normalizedValidUntil >= Carbon::today()->toDateString() ? 'active' : 'expired';
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
            if (in_array($normalized, ['1', 'true', 'yes', 'ja'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'nein'], true)) {
                return false;
            }
        }

        return $default;
    }

    private function parseLicenceModelInput(mixed $licenceModel): ?array
    {
        if (is_string($licenceModel)) {
            $decoded = json_decode($licenceModel, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            $licenceModel = $decoded;
        }

        return is_array($licenceModel) ? $licenceModel : null;
    }

    private function normalizeRoleNames(mixed $roleNames): array
    {
        if (is_string($roleNames)) {
            $decoded = json_decode($roleNames, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $roleNames = $decoded;
            }
        }

        if (! is_array($roleNames)) {
            return [];
        }

        return collect($roleNames)
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->pipe(function (Collection $roleNames) {
                if ($roleNames->contains('*')) {
                    return collect(['*']);
                }

                return $roleNames;
            })
            ->unique()
            ->values()
            ->all();
    }

    private function configuredRolesMatchUserRoles(array $userRoleNames, array $configuredRoleNames): bool
    {
        if (empty($userRoleNames) || empty($configuredRoleNames)) {
            return false;
        }

        if (in_array('*', $configuredRoleNames, true)) {
            return true;
        }

        return ! empty(array_intersect($userRoleNames, $configuredRoleNames));
    }

    private function normalizePriceString(mixed $price): ?string
    {
        if ($price === null) {
            return null;
        }

        $normalized = trim((string) $price);
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^[1-9][0-9]*$/', $normalized) === 1) {
            return $normalized;
        }

        if (preg_match('/^[1-9][0-9]*[.,]0+$/', $normalized) === 1) {
            return strtok(str_replace(',', '.', $normalized), '.');
        }

        return $normalized;
    }

    private function normalizePositiveIntegerString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        return preg_match('/^[1-9][0-9]*$/', $normalized) === 1 ? $normalized : $normalized;
    }

    private function isStructuredLicenceConfiguration(array $licenceModel): bool
    {
        return array_key_exists('school_licence_enabled', $licenceModel)
            || array_key_exists('school_price_per_year', $licenceModel)
            || array_key_exists('school_included_storage_gb', $licenceModel)
            || array_key_exists('school_extra_storage_step_gb', $licenceModel)
            || array_key_exists('school_extra_storage_step_price', $licenceModel)
            || array_key_exists('admin_licence_enabled', $licenceModel)
            || array_key_exists('admin_price_per_year', $licenceModel)
            || array_key_exists('admin_role_names', $licenceModel)
            || array_key_exists('admin_included_storage_gb', $licenceModel)
            || array_key_exists('admin_extra_storage_step_gb', $licenceModel)
            || array_key_exists('admin_extra_storage_step_price', $licenceModel)
            || array_key_exists('user_licence_enabled', $licenceModel)
            || array_key_exists('user_price_per_year', $licenceModel)
            || array_key_exists('user_role_names', $licenceModel)
            || array_key_exists('user_included_storage_gb', $licenceModel)
            || array_key_exists('user_extra_storage_step_gb', $licenceModel)
            || array_key_exists('user_extra_storage_step_price', $licenceModel);
    }

    private function looksLikeAdminRoleName(string $roleName): bool
    {
        $normalizedRoleName = strtolower(trim($roleName));

        return str_contains($normalizedRoleName, 'admin')
            || in_array($normalizedRoleName, ['super_admin'], true);
    }

    private function syncLicenceUserPlans(Licence $licence, array $plansByRole): array
    {
        $existingPlans = $licence->userPlans()->get()->keyBy('id');
        $keptPlanIds = [];
        $syncedPlansByRole = [];

        foreach ($plansByRole as $roleName => $plans) {
            if (! is_string($roleName) || ! is_array($plans)) {
                continue;
            }

            $roleName = trim($roleName);
            if ($roleName === '') {
                continue;
            }

            $syncedPlansByRole[$roleName] = [];
            $sortOrder = 0;

            foreach ($plans as $plan) {
                if (! is_array($plan)) {
                    continue;
                }

                $text = trim((string) ($plan['text'] ?? ''));
                $pricePerYear = trim((string) ($plan['price_per_year'] ?? ''));
                if ($text === '' || $pricePerYear === '') {
                    continue;
                }

                $planId = (isset($plan['id']) && is_numeric($plan['id'])) ? (int) $plan['id'] : null;
                /** @var LicenceUserPlan $planModel */
                $planModel = ($planId && $existingPlans->has($planId))
                    ? $existingPlans->get($planId)
                    : new LicenceUserPlan(['licence_id' => $licence->id]);

                $planModel->licence_id = $licence->id;
                $planModel->role_name = $roleName;
                $planModel->text = mb_substr($text, 0, 255);
                $planModel->price_per_year = mb_substr($pricePerYear, 0, 255);
                $planModel->sort_order = $sortOrder++;
                $planModel->save();

                $keptPlanIds[] = (int) $planModel->id;

                $syncedPlansByRole[$roleName][] = [
                    'id' => (int) $planModel->id,
                    'text' => $planModel->text,
                    'price_per_year' => $planModel->price_per_year,
                ];
            }
        }

        if (! empty($keptPlanIds)) {
            $licence->userPlans()->whereNotIn('id', $keptPlanIds)->delete();
        } else {
            $licence->userPlans()->delete();
        }

        return $syncedPlansByRole;
    }
}
