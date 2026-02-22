<?php

namespace App\Services;

use App\Http\Resources\Admin\LicenceResource;
use App\Http\Resources\Admin\UserResource;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;

use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;


class SchoolService
{

    public function create($data)
    {
        return DB::transaction(function () use ($data) {
            // Merken falls upload_file gesetzt ist
            $path = $data['upload_file'] ?? null;
            unset($data['upload_file']);

            // Schule anlegen
            $school  = School::create($data);

            // Schuljahre aus Config anlegen (ab aktuellem Schuljahr)
            $schoolyear = $this->createSchoolyearsFromConfig($school);

            // Super-Admin anlegen
            $user = User::create([
                'school_id' => $school->id,
                'schoolyear_id' => $schoolyear->id,
                'last_name' => env('SA_LAST_NAME'),
                'first_name' => env('SA_FIRST_NAME'),
                'email' => env('SA_EMAIL'),
                'password' => env('SA_PW'),
            ]);

            $user->email_verified_at = now();
            $user->confirmed_at = now();
            $user->is_active = 1;
            $user->save();

            $user->assignRole('super_admin');

            // SchoolTool - Record erzeugen
            $schoolTool = SchoolTool::create([
                'school_id' =>  $school->id,
                'tutoring_student_must_be_confirmed' => false,
                'tutoring_confirmer_email' => '',
            ]);

            // Folder für Logo etc anlegen (immer lokal, da Teaching-Uploads direkt auf lokales Dateisystem schreiben)
            $hlp_path = $school->id . '/temp';
            if (!Storage::disk('local')->directoryExists($hlp_path)) {
                Storage::disk('local')->makeDirectory($hlp_path);
            }
            $hlp_path = $school->id . '/excel';
            if (!Storage::disk('local')->directoryExists($hlp_path)) {
                Storage::disk('local')->makeDirectory($hlp_path);
            }
            $hlp_path = $school->id . '/pdf';
            if (!Storage::disk('local')->directoryExists($hlp_path)) {
                Storage::disk('local')->makeDirectory($hlp_path);
            }

            // Logo verschieben
            if ($path) {
                $school = $this->moveLogo($school, $path);
            }

            return $school;
        });
    }

    private function createSchoolyearsFromConfig(School $school): Schoolyear
    {
        $schoolyears = collect(config('schooltool.schoolyears', []))
            ->filter(fn($entry) => !empty($entry['name']) && !empty($entry['from']) && !empty($entry['to']))
            ->values();

        if ($schoolyears->isEmpty()) {
            return Schoolyear::create([
                'school_id' => $school->id,
                'name' => 'Schuljahr',
                'is_active' => true,
            ]);
        }

        $today = Carbon::today();

        $startIndex = $schoolyears->search(function ($entry) use ($today) {
            $from = Carbon::parse($entry['from'])->startOfDay();
            $to = Carbon::parse($entry['to'])->endOfDay();
            return $today->betweenIncluded($from, $to);
        });

        if ($startIndex === false) {
            $startIndex = $schoolyears->search(function ($entry) use ($today) {
                $from = Carbon::parse($entry['from'])->startOfDay();
                return $from->greaterThanOrEqualTo($today);
            });
        }

        if ($startIndex === false) {
            $startIndex = $schoolyears->count() - 1;
        }

        $activeSchoolyear = null;
        foreach ($schoolyears->slice($startIndex)->values() as $index => $entry) {
            $created = Schoolyear::create([
                'school_id' => $school->id,
                'name' => $entry['name'],
                'concerns' => $entry['concerns'] ?? null,
                'from' => $entry['from'],
                'until' => $entry['to'],
                'sem_2_start' => $entry['sem_2_start'] ?? null,
                'is_active' => $index === 0,
            ]);

            if ($index === 0) {
                $activeSchoolyear = $created;
            }
        }

        return $activeSchoolyear;
    }

    public function update($school, $data)
    {

        // Merken falls upload_file gesetzt ist
        $path = $data['upload_file'] ?? null;
        unset($data['upload_file']);

        // Schule updaten
        $school->update($data);

        // Logo verschieben
        if ($path) {
            $school = $this->moveLogo($school, $path);
        }

        return $school;
    }

    public function deleteSchools($ids)
    {
        foreach ($ids as $id) {
            $this->deleteSchool($id);
        }
    }

    private function deleteSchool($id): bool
    {

        // Schule 1 kann nicht gelöscht werden (Big Boss Schule)
        if ($id == 1) return false;

        // Schule mit Registers kann nicht gelöscht werden
        if (School::where('id', $id)->has('registers')->exists()) return false;

        // Die Schule hat mehr User als nur den Super-Admin und kann daher nicht gelöscht werden
        if (School::where('id', $id)->has('users', '>', 1)->exists())  return false;

        // Eine Schule darf nicht gelöscht werden, wenn eines ihrer Schuljahre noch Abhängigkeiten hat
        $hasSchoolyearDependencies = Schoolyear::where('school_id', $id)
            ->get()
            ->contains(fn(Schoolyear $schoolyear) => $schoolyear->hasDependencies());
        if ($hasSchoolyearDependencies) return false;

        $school = School::findOrFail($id);

        // Lizenzen löschen
        $school->licences()->detach();

        // Super-Admin löschen
        User::where('school_id', $id)->each(function ($user) {
            $user->syncRoles([]);   // removes all role assignments
            $user->delete();
        });


        // Schuljahr der Schule löschen
        Schoolyear::where('school_id', $id)->delete();

        // SchoolTool der Schule löschen
        SchoolTool::where('school_id', $id)->delete();

        // Schulen löschen
        $school = School::find($id);

        if ($school) {
            // Logo löschen, falls vorhanden
            if ($school->logo) {
                Storage::disk('public')->delete("images/{$school->logo}");
            }

            // Schule löschen
            $this->deleteSchoolPrivateStorageArtifacts((int) $school->id);
            $school->delete();
        }

        return true;
    }

    private function deleteSchoolPrivateStorageArtifacts(int $schoolId): void
    {
        // Keep storage cleanup idempotent and defensive to avoid deletion aborts on partial filesystem errors.
        $paths = [
            (string) $schoolId,
            "materials/schools/{$schoolId}",
            "materials/temp/{$schoolId}",
        ];

        foreach ($paths as $relativePath) {
            try {
                Storage::disk('local')->deleteDirectory($relativePath);
            } catch (\Throwable $e) {
                // Ignore disk-level cleanup errors and continue with direct filesystem fallback below.
            }
        }

        File::deleteDirectory(storage_path("app/private/{$schoolId}"));
        File::deleteDirectory(storage_path("app/private/materials/schools/{$schoolId}"));
        File::deleteDirectory(storage_path("app/private/materials/temp/{$schoolId}"));
    }

    public function schoolInfos($school_id)
    {
        $licences = School::find($school_id)->licences->sortBy('name')->values();
        $authUser = Auth::user();
        if ($authUser) {
            $authUser->load('roles');
        }

        $licenceService = app(LicenceService::class);
        $schoolLicencesById = SchoolLicence::query()
            ->where('school_id', $school_id)
            ->get()
            ->keyBy(fn(SchoolLicence $schoolLicence) => (string) $schoolLicence->id);

        $licenceRows = $licences
            ->map(function ($licence) use ($authUser, $licenceService, $schoolLicencesById) {
                $row = (new LicenceResource($licence))->resolve();
                $schoolLicenceId = (string) ($row['school_licence_id'] ?? '');
                $schoolLicence = $schoolLicencesById->get($schoolLicenceId);
                $row['licence_model'] = $this->mergeDashboardLicenceModel(
                    $schoolLicence?->licence_model ?? ($row['licence_model'] ?? null),
                    $licence->licence_model ?? null,
                    $licenceService
                );

                return $this->attachCurrentUserLicenceSummary($row, $authUser, $schoolLicence, $licenceService);
            })
            ->values()
            ->all();

        $data = [
            'licences' => $licenceRows,
        ];

        $roles = ['admin', 'register_admin', 'super_admin', 'tutoring_admin', 'teaching_admin', 'materials_admin'];
        $users = User::where('school_id', $school_id)
            ->role($roles)
            ->orderBy('last_name')
            ->get();

        $data['admins'] = UserResource::collection($users);

        $data['teachers']['count_active'] = User::where('school_id', $school_id)->role('teacher')->count();
        $data['teachers']['count'] = Teacher::where('school_id', $school_id)->count();

        return $data;
    }

    private function mergeDashboardLicenceModel(mixed $schoolLicenceModelRaw, mixed $baseLicenceModelRaw, LicenceService $licenceService): array
    {
        $schoolModel = $licenceService->normalizeLicenceModel($schoolLicenceModelRaw);
        $baseModel = $licenceService->normalizeLicenceModel($baseLicenceModelRaw);

        $affectedRoles = collect(array_merge(
            is_array($schoolModel['affected_roles'] ?? null) ? $schoolModel['affected_roles'] : [],
            is_array($baseModel['affected_roles'] ?? null) ? $baseModel['affected_roles'] : []
        ))
            ->map(fn($role) => is_string($role) ? trim($role) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $schoolRequiredByRole = is_array($schoolModel['user_licence_required_by_role'] ?? null)
            ? $schoolModel['user_licence_required_by_role']
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
            $requiredByRole[$roleName] =
                (bool) ($schoolRequiredByRole[$roleName] ?? false)
                || (bool) ($baseRequiredByRole[$roleName] ?? false);

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
                    : 'txt:' . $planText . '|price:' . $planPrice;

                if (isset($seenPlanKeys[$planKey])) {
                    continue;
                }

                $seenPlanKeys[$planKey] = true;
                $mergedPlans[] = $plan;
            }

            $plansByRole[$roleName] = $mergedPlans;
        }

        return [
            'school_licence_required' => (bool) ($schoolModel['school_licence_required'] ?? true),
            'affected_roles' => $affectedRoles,
            'user_licence_required_by_role' => $requiredByRole,
            'user_licence_plans_by_role' => $plansByRole,
        ];
    }

    private function attachCurrentUserLicenceSummary(array $licence, ?User $authUser, ?SchoolLicence $schoolLicence, LicenceService $licenceService): array
    {
        $licenceModel = $licenceService->normalizeLicenceModel($licence['licence_model'] ?? null);
        $schoolLicenceRequired = (bool) ($licenceModel['school_licence_required'] ?? true);
        $schoolLicenceValidUntil = isset($licence['valid_until']) ? (string) $licence['valid_until'] : null;
        $isSchoolLicenceActive = ! $schoolLicenceRequired || $this->isDateActiveForSchoolInfo($schoolLicenceValidUntil);

        $requiredRoles = collect($licenceModel['user_licence_required_by_role'] ?? [])
            ->filter(fn($isRequired) => (bool) $isRequired)
            ->keys()
            ->map(fn($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->values()
            ->all();

        $userRoles = $authUser
            ? $authUser->roles()
                ->pluck('name')
                ->map(fn($roleName) => is_string($roleName) ? trim($roleName) : '')
                ->filter()
                ->values()
                ->all()
            : [];

        $matchedRoles = collect($userRoles)
            ->filter(fn(string $roleName) => in_array($roleName, $requiredRoles, true))
            ->values()
            ->all();

        $assignments = is_array($schoolLicence?->user_licence_assignments) ? $schoolLicence->user_licence_assignments : [];
        $userAssignments = [];
        if ($authUser && isset($assignments[(string) $authUser->id]) && is_array($assignments[(string) $authUser->id])) {
            $userAssignments = $assignments[(string) $authUser->id];
        }

        $roleSummaries = collect($matchedRoles)
            ->map(function (string $roleName) use ($licenceModel, $userAssignments, $schoolLicenceRequired, $schoolLicenceValidUntil) {
                $assigned = is_array($userAssignments) && array_key_exists($roleName, $userAssignments);
                $assignmentEntry = $assigned
                    ? $this->normalizeUserLicenceAssignmentEntryForSchoolInfo($userAssignments[$roleName] ?? null)
                    : ['valid_until' => null, 'is_activated' => false, 'plan_id' => null];
                $validUntil = $assignmentEntry['valid_until'];
                $isActivated = $assignmentEntry['is_activated'];
                $planId = $assignmentEntry['plan_id'];

                $plansByRole = is_array($licenceModel['user_licence_plans_by_role'] ?? null)
                    ? $licenceModel['user_licence_plans_by_role']
                    : [];
                $plans = isset($plansByRole[$roleName]) && is_array($plansByRole[$roleName])
                    ? $plansByRole[$roleName]
                    : [];
                $selectedPlan = collect($plans)
                    ->first(function ($plan) use ($planId) {
                        return is_array($plan)
                            && $planId !== null
                            && isset($plan['id'])
                            && (int) $plan['id'] === (int) $planId;
                    });
                $firstPlan = collect($plans)->first(fn($plan) => is_array($plan));
                $plan = is_array($selectedPlan) ? $selectedPlan : $firstPlan;

                $isActive = $assigned && $isActivated && (
                    (! $schoolLicenceRequired || $this->isDateActiveForSchoolInfo($schoolLicenceValidUntil))
                    && $this->isDateActiveForSchoolInfo($validUntil)
                );

                return [
                    'role_name' => $roleName,
                    'assigned' => $assigned,
                    'is_activated' => $isActivated,
                    'is_active' => $isActive,
                    'valid_until' => $validUntil,
                    'plan_id' => $planId,
                    'plan' => is_array($plan)
                        ? [
                            'id' => isset($plan['id']) ? (int) $plan['id'] : null,
                            'text' => isset($plan['text']) ? (string) $plan['text'] : null,
                            'price_per_year' => isset($plan['price_per_year']) ? (string) $plan['price_per_year'] : null,
                        ]
                        : null,
                ];
            })
            ->values()
            ->all();

        $hasUserLicence = collect($roleSummaries)->contains(fn(array $entry) => (bool) ($entry['is_active'] ?? false));
        $selectedRoleSummary = collect($roleSummaries)->first(fn(array $entry) => (bool) ($entry['is_active'] ?? false))
            ?? collect($roleSummaries)->first(fn(array $entry) => (bool) ($entry['is_activated'] ?? false))
            ?? collect($roleSummaries)->first(fn(array $entry) => (bool) ($entry['assigned'] ?? false))
            ?? collect($roleSummaries)->first();

        $toolHasUserLicence = count($requiredRoles) > 0;
        $requiresUserLicenceForCurrentUser = $isSchoolLicenceActive && count($matchedRoles) > 0;

        $licence['current_user_licence'] = [
            'enabled' => $toolHasUserLicence,
            'required' => $requiresUserLicenceForCurrentUser,
            'is_relevant_for_user' => count($matchedRoles) > 0,
            'has_licence' => $hasUserLicence,
            'is_activated' => is_array($selectedRoleSummary) ? (bool) ($selectedRoleSummary['is_activated'] ?? false) : false,
            'role_name' => is_array($selectedRoleSummary) ? ($selectedRoleSummary['role_name'] ?? null) : null,
            'plan_id' => is_array($selectedRoleSummary) ? ($selectedRoleSummary['plan_id'] ?? null) : null,
            'valid_until' => is_array($selectedRoleSummary) ? ($selectedRoleSummary['valid_until'] ?? null) : null,
            'plan' => is_array($selectedRoleSummary) ? ($selectedRoleSummary['plan'] ?? null) : null,
            'roles' => $roleSummaries,
        ];

        return $licence;
    }

    private function normalizeUserLicenceAssignmentEntryForSchoolInfo(mixed $entry): array
    {
        // Legacy shape: role => "YYYY-MM-DD" (or null)
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

    private function isDateActiveForSchoolInfo(?string $validUntil): bool
    {
        if (! is_string($validUntil) || trim($validUntil) === '') {
            return true;
        }

        try {
            return Carbon::parse($validUntil)->startOfDay()->greaterThanOrEqualTo(now()->startOfDay());
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function loadSwitchableSchools($user)
    {

        $users = User::where('email', $user->email)->get();
        $ids = $users->pluck('id');

        $schools = User::whereIn('id', $ids)
            ->with('selectedSchool')
            ->get()
            ->pluck('selectedSchool')
            ->filter()
            ->unique('id')
            ->sortBy('long_name')
            ->values();

        return $schools;
    }

    public function switchSchool($user, $school_id)
    {
        // Prüfen, ob es den User mit der Schule gibt
        $targetUser = User::where('email', $user->email)
            ->where('school_id', $school_id)
            ->first();

        if (! $targetUser) abort(403, 'Wechsel zu der Schule nicht möglich.');

        if (Auth::check()) {
            Auth::guard('web')->logout();
        }

        Auth::guard('web')->login($targetUser, true);
        session()->regenerate();

        return $targetUser;
    }

    private function moveLogo($school, $path)
    {
        $relPath = Str::before(ltrim($path, '/'), '?'); // strip leading slash + ?t=...

        // 1) Absolute paths
        $source = $relPath;      // /storage/app/private/temp/1/logo.jpg
        $destDir = storage_path('app/public/images/logos');           // /storage/app/public/images/logos

        // 2) Build new filename
        $baseName  = pathinfo($source, PATHINFO_FILENAME);      // "logo"
        $extension = pathinfo($source, PATHINFO_EXTENSION);     // "jpg"
        $newFilename = "logo_{$school->id}.{$extension}";  // "logo_12.jpg"
        $destPath = $destDir . DIRECTORY_SEPARATOR . $newFilename;




        // 3) copy the file
        // make sure the target directory exists
        File::ensureDirectoryExists(dirname($destDir));

        // copy the file
        File::copy($source, $destPath);

        // 4) Save only the pure filename in DB
        $school->logo = $newFilename;   // e.g. "logo_12.jpg"
        $school->save();

        return $school;
    }

    public function addAdmin($school_id, $schoolyear_id, $data, $roles): User
    {

        if ($user = User::where('school_id', $school_id)->where('email', $data['email'])->first()) abort(409, "Dieser Admin existiert bereits und kann daher nicht angelegt werden.");

        // Check if the user shoul get an super_admin role, but there exists an super_admin user ==> not allowed
        $role = "super_admin";
        if (in_array($role, $roles)) {
            if ($users = User::where('school_id', $school_id)
                ->role($role) // provided by Spatie\Permission\Traits\HasRoles
                ->count() > 0
            ) abort(409, "Für diese Schule existiert bereits ein Super-Admin.");
        }

        $data['school_id'] = $school_id;
        $data['schoolyear_id'] = $schoolyear_id;
        $data['password'] = Hash::make(now());

        $user = User::create($data);

        $user->email_verified_at = now();
        $user->confirmed_at = now();
        $user->is_active = 1;
        $user->save();

        $user->assignRole($roles);
        return $user;
    }

    public function deleteAdmin($user_id, $is_delete_complete)
    {

        if ($user_id == 1) abort(409, "Der Big-Boss-User kann nicht gelöscht werden.");
        $user = User::findOrFail($user_id);

        $roles = ['admin', 'register_admin'];
        foreach ($roles as $role) {
            $user->removeRole($role);
        }

        if ($is_delete_complete) {
            if (count($user->registerDateBookings) > 0) abort(409, 'Der Benutzer hat noch gebuchte Anmeldungen und kann nicht gelöscht werden. Seine Rollen wurden gelöscht.');

            $user->delete();
        }
    }
}
