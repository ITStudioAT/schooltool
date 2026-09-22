<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SchoolAddAdminRequest;
use App\Http\Requests\Admin\SchoolAddLicenceRequest;
use App\Http\Requests\Admin\SchoolDeleteAdminRequest;
use App\Http\Requests\Admin\SchoolDeleteLicenceRequest;
use App\Http\Requests\Admin\SchoolDeleteSchoolsRequest;
use App\Http\Requests\Admin\SchoolIndexRequest;
use App\Http\Requests\Admin\SchoolLicenceSaveModelRequest;
use App\Http\Requests\Admin\SchoolLicenceSaveSchoolRequest;
use App\Http\Requests\Admin\SchoolLicenceUserRolesSaveRequest;
use App\Http\Requests\Admin\SchoolLicenceUsersRequest;
use App\Http\Requests\Admin\SchoolLoadSchoolLicencesRequest;
use App\Http\Requests\Admin\SchoolStoreRequest;
use App\Http\Requests\Admin\SchoolSwitchSchoolRequest;
use App\Http\Requests\Admin\SchoolUpdateRequest;
use App\Http\Resources\Admin\LicenceResource;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Admin\SchoolResource;
use App\Http\Resources\Admin\UserResource;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolUserLicence;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\LicenceService;
use App\Services\SchoolService;
use App\Services\SchoolUserLicenceAssignmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SchoolController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(SchoolIndexRequest $request)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $search_string = $validated['search_string'] ?? null;
        $expiredOnly = (bool) ($validated['expired_only'] ?? false);

        $schools = School::query()
            ->with([
                'licences' => fn ($query) => $query->orderBy('name'),
                'schoolLicences',
                'schoolUserLicences',
            ])
            ->when($expiredOnly, function ($query) {
                $query->whereHas('licences', function ($licenceQuery) {
                    $licenceQuery->where(function ($requiredQuery) {
                        $requiredQuery
                            ->where('school_licences.licence_model->school_licence_required', true)
                            ->orWhere(function ($fallbackTemplateQuery) {
                                $fallbackTemplateQuery
                                    ->whereNull('school_licences.licence_model->school_licence_required')
                                    ->where(function ($templateRequiredQuery) {
                                        $templateRequiredQuery
                                            ->whereNull('licences.licence_model->school_licence_required')
                                            ->orWhere('licences.licence_model->school_licence_required', true);
                                    });
                            });
                    })
                        ->whereNotNull('school_licences.valid_until')
                        ->whereDate('school_licences.valid_until', '<', now()->toDateString());
                });
            })
            ->when($search_string, function ($query, $search_string) {
                $query->where(function ($q) use ($search_string) {
                    $q->where('long_name', 'like', "%{$search_string}%")
                        ->orWhere('short_name', 'like', "%{$search_string}%")
                        ->orWhere('email', 'like', "%{$search_string}%")
                        ->orWhereHas('licences', function ($licenceQuery) use ($search_string) {
                            $licenceQuery->where('name', 'like', "%{$search_string}%")
                                ->orWhere('long_name', 'like', "%{$search_string}%");
                        });
                });
            })
            ->orderBy('long_name')
            ->paginate(config('schooltool.pagination'));

        return response()->json([
            'data' => SchoolResource::collection($schools),
            'meta' => new PaginateResource($schools),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SchoolStoreRequest $request, SchoolService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
        $validated = $request->validated();

        $school = $service->create($validated);

        return response()->json(new SchoolResource($school), 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(School $school)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SchoolUpdateRequest $request, School $school, SchoolService $service)
    {
        Gate::authorize('update', $school);
        $validated = $request->validated();

        $school = $service->update($school, $validated);

        return response()->json(new SchoolResource($school), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(School $school)
    {
        //
    }

    public function deleteSchools(SchoolDeleteSchoolsRequest $request, SchoolService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        if (in_array($auth_user->school_id, $validated)) {
            abort(409, 'Eine zu löschende Schule ist aktuell ihnen zugeordnet. Das ist nicht zulässig.');
        }

        $service->deleteSchools($validated);

        return response()->noContent();
    }

    public function uploadLogo(Request $request, FileUploadService $fileUploadService)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $id = $fileUploadService->upload($request, 'school-logo');

        return response($id, 200)->header('Content-Type', 'text/plain');
    }

    public function uploadLogoNext(Request $request, FileUploadService $fileUploadService)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        // $school = School::findOrFail($auth_user->school_id);

        $result = $fileUploadService->uploadNext(
            $request,
            'app/public/temp',              // final target directory
            "logo_{$auth_user->id}",                   // required filename base
            ['width' => 200, 'height' => 100],
            profile: 'school-logo',
        );

        // Partial chunk → just forward the 200 "OK" response
        if ($result instanceof Response) {
            return $result; // "OK" or final name wrapped in Response
        }

        // $school->logo = $result;
        // $school->save();
        return response($result, 200)->header('Content-Type', 'text/plain');

        // return $result; // already a proper Response from the service
    }

    public function loadSwitchableSchools(Request $request, SchoolService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        $schools = $service->loadSwitchableSchools($auth_user, $validated['email'] ?? null);

        return response()->json(SchoolResource::collection($schools), 200);
    }

    public function searchSwitchUsers(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'last_name' => ['nullable', 'string', 'max:255'],
        ]);

        $lastName = trim((string) ($validated['last_name'] ?? ''));
        if ($lastName === '') {
            return response()->json([], 200);
        }

        $users = User::query()
            ->with('selectedSchool:id,long_name,short_name')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where('school_id', '!=', $auth_user->school_id)
            ->where('last_name', 'like', "{$lastName}%")
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('email')
            ->limit(100)
            ->get();

        $matches = $users
            ->groupBy(fn (User $user) => mb_strtolower(trim((string) $user->email)))
            ->map(function ($group) {
                /** @var User $first */
                $first = $group->first();
                $schools = $group
                    ->map(function (User $user) {
                        $label = trim((string) ($user->selectedSchool?->long_name ?: $user->selectedSchool?->short_name));

                        return [
                            'id' => (int) $user->school_id,
                            'label' => $label !== '' ? $label : ('Schule #'.(int) $user->school_id),
                        ];
                    })
                    ->unique('id')
                    ->sortBy('label')
                    ->values()
                    ->all();

                $fullName = trim(((string) ($first->last_name ?? '')).' '.((string) ($first->first_name ?? '')));
                $schoolsText = collect($schools)->pluck('label')->implode(', ');

                return [
                    'email' => (string) $first->email,
                    'first_name' => $first->first_name,
                    'last_name' => $first->last_name,
                    'school_count' => count($schools),
                    'schools' => $schools,
                    'label' => trim(($fullName !== '' ? $fullName : (string) $first->email).' • '.(string) $first->email),
                    'subtitle' => $schoolsText,
                ];
            })
            ->sortBy([
                ['last_name', 'asc'],
                ['first_name', 'asc'],
                ['email', 'asc'],
            ])
            ->values()
            ->take(20)
            ->all();

        return response()->json($matches, 200);
    }

    public function switchSchool(SchoolSwitchSchoolRequest $request, SchoolService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        $auth_user = $service->switchSchool(
            $auth_user,
            $validated['school_id'],
            $validated['email'] ?? null
        );

        return response()->noContent();
    }

    public function loadSchoolInfos(SchoolLoadSchoolLicencesRequest $request, SchoolService $service)
    {

        if (! $auth_user = $this->userHasRole(['admin', 'register_admin', 'teaching_admin', 'materials_admin', 'materials_moderator', 'teacher', 'lunch_admin', 'studentstimetables_admin', 'studentstimetables_moderator'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        $data = $service->schoolInfos($validated['school_id']);

        return response()->json($data, 200);
    }

    public function addLicence(SchoolAddLicenceRequest $request, LicenceService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $schoolId = (int) ($validated['data']['school_id'] ?? ($auth_user->selectedSchool?->id ?? 0));
        if ($schoolId <= 0) {
            abort(422, 'Keine Schule ausgewählt.');
        }

        $school = School::findOrFail($schoolId);

        $school_licence = $service->schoolAddLicence($school, $validated['data']);
        $licences = School::findOrFail($school_licence->school_id)->licences;

        return response()->json(LicenceResource::collection($licences), 200);
    }

    public function saveSchoolLicenceSchool(SchoolLicenceSaveSchoolRequest $request, SchoolLicence $school_licence)
    {
        if (! $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        $school_licence->update([
            'charged_school_price' => $validated['charged_school_price'] ?? null,
            'extra_storage_units' => $validated['extra_storage_units'] ?? null,
            'extra_storage_unit_price' => $validated['extra_storage_unit_price'] ?? null,
            'charged_admin_price' => $validated['charged_admin_price'] ?? null,
            'admin_extra_storage_units' => $validated['admin_extra_storage_units'] ?? null,
            'admin_extra_storage_unit_price' => $validated['admin_extra_storage_unit_price'] ?? null,
            'charged_user_price' => $validated['charged_user_price'] ?? null,
            'user_extra_storage_units' => $validated['user_extra_storage_units'] ?? null,
            'user_extra_storage_unit_price' => $validated['user_extra_storage_unit_price'] ?? null,
        ]);

        return response()->noContent();
    }

    public function deleteLicence(SchoolDeleteLicenceRequest $request, LicenceService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $school_licence = SchoolLicence::findOrFail($validated['school_licence_id']);

        if ($this->schoolLicenceHasRemainingUserLicenceAssignments($school_licence)) {
            return response()->json([
                'message' => 'Lizenz kann nicht entfernt werden, solange noch Benutzerlizenzen vorhanden sind.',
            ], 422);
        }

        $school_id = $school_licence->school_id;
        $school_licence->delete();

        $licences = School::find($school_id)->licences;

        return response()->json(LicenceResource::collection($licences), 200);
    }

    public function saveSchoolLicenceModel(SchoolLicenceSaveModelRequest $request, SchoolLicence $school_licence, LicenceService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        $school_licence->licence_model = $service->normalizeLicenceModel($validated['licence_model']);
        $school_licence->save();

        $licences = School::findOrFail($school_licence->school_id)->licences;

        return response()->json(LicenceResource::collection($licences), 200);
    }

    public function loadSchoolLicenceUsers(SchoolLicenceUsersRequest $request, SchoolLicence $school_licence, LicenceService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $search_string = $validated['search_string'] ?? null;
        $expiredOnly = (bool) ($validated['expired_only'] ?? false);
        $assignedOnly = array_key_exists('assigned_only', $validated) ? (bool) $validated['assigned_only'] : true;

        $licenceModel = $this->mergedSchoolLicenceUserLicenceModel($school_licence->loadMissing('licence'), $service);
        $schoolLicenceRequired = (bool) ($licenceModel['school_licence_required'] ?? true);

        $requiredByRole = is_array($licenceModel['user_licence_required_by_role'] ?? null)
            ? $licenceModel['user_licence_required_by_role']
            : [];

        $licenceFilterRoles = collect(array_merge(
            is_array($licenceModel['affected_roles'] ?? null) ? $licenceModel['affected_roles'] : [],
            array_keys($requiredByRole)
        ))
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $licenceModelRoles = collect($requiredByRole)
            ->filter(fn ($isRequired) => (bool) $isRequired)
            ->keys()
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $selectedRoles = collect($validated['role_names'] ?? [])
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $activeRoleFilters = collect($selectedRoles)
            ->filter(fn ($roleName) => in_array($roleName, $licenceFilterRoles, true))
            ->values()
            ->all();

        $shouldApplyRoleFilter = ! empty($activeRoleFilters);
        $assignmentService = app(SchoolUserLicenceAssignmentService::class);
        $assignments = $assignmentService->assignmentsForSchoolLicence($school_licence, $licenceFilterRoles);
        $assignmentRoleNames = $shouldApplyRoleFilter
            ? array_values(array_intersect($licenceModelRoles, $activeRoleFilters))
            : $licenceModelRoles;
        $assignedUserIds = collect($assignments)
            ->filter(fn ($userAssignments) => is_array($userAssignments))
            ->filter(function (array $userAssignments) use ($assignmentRoleNames) {
                foreach ($assignmentRoleNames as $roleName) {
                    if (array_key_exists($roleName, $userAssignments)) {
                        return true;
                    }
                }

                return false;
            })
            ->keys()
            ->map(fn ($userId) => (int) $userId)
            ->filter(fn ($userId) => $userId > 0)
            ->values()
            ->all();

        $usersQuery = User::query()
            ->with('roles')
            ->where('school_id', $school_licence->school_id)
            ->when($search_string, function ($query, $search_string) {
                $query->where(function ($q) use ($search_string) {
                    $q->where('last_name', 'like', "%{$search_string}%")
                        ->orWhere('first_name', 'like', "%{$search_string}%")
                        ->orWhere('email', 'like', "%{$search_string}%");
                });
            })
            ->when(
                empty($licenceModelRoles) || ($assignedOnly && empty($assignedUserIds) && ! $shouldApplyRoleFilter),
                fn ($query) => $query->whereRaw('1=0'),
                function ($query) use ($assignedOnly, $assignedUserIds, $shouldApplyRoleFilter, $activeRoleFilters, $licenceModelRoles) {
                    if ($assignedOnly && ! empty($assignedUserIds)) {
                        $query->whereIn('id', $assignedUserIds);
                    }

                    $roleNames = $shouldApplyRoleFilter ? $activeRoleFilters : $licenceModelRoles;
                    $query->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', $roleNames));
                }
            );

        if ($expiredOnly) {
            if (empty($licenceModelRoles)) {
                $usersQuery->whereRaw('1=0');
            } elseif ($schoolLicenceRequired && ! $this->isDateActive($school_licence->valid_until)) {
                $usersQuery->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', $licenceModelRoles));
            } else {
                $outdatedUserIds = collect($assignments)
                    ->filter(fn ($userAssignments) => is_array($userAssignments))
                    ->filter(function (array $userAssignments) use ($licenceModelRoles) {
                        foreach ($licenceModelRoles as $roleName) {
                            $entry = $this->normalizeUserLicenceAssignmentEntry($userAssignments[$roleName] ?? null);
                            $validUntil = $entry['valid_until'] ?? null;
                            if (! is_string($validUntil) || trim($validUntil) === '') {
                                continue;
                            }

                            if (! $this->isDateActive(trim($validUntil))) {
                                return true;
                            }
                        }

                        return false;
                    })
                    ->keys()
                    ->map(fn ($userId) => (int) $userId)
                    ->filter(fn ($userId) => $userId > 0)
                    ->values()
                    ->all();

                if (empty($outdatedUserIds)) {
                    $usersQuery->whereRaw('1=0');
                } else {
                    $usersQuery
                        ->whereIn('id', $outdatedUserIds)
                        ->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', $licenceModelRoles));
                }
            }
        }

        $users = $usersQuery
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(config('schooltool.pagination'));

        $roleStatusesByUser = $users->getCollection()
            ->mapWithKeys(function (User $user) use ($licenceFilterRoles, $requiredByRole, $assignments, $school_licence, $schoolLicenceRequired) {
                $userAssignments = isset($assignments[(string) $user->id]) && is_array($assignments[(string) $user->id])
                    ? $assignments[(string) $user->id]
                    : [];

                $userRoleNames = $user->roles
                    ->pluck('name')
                    ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
                    ->filter()
                    ->values()
                    ->all();
                $userRoleLookup = array_flip($userRoleNames);

                $statuses = [];
                foreach ($licenceFilterRoles as $roleName) {
                    if (! isset($userRoleLookup[$roleName])) {
                        continue;
                    }

                    $userLicenceRequired = (bool) ($requiredByRole[$roleName] ?? false);
                    $hasLicenceAssignment = array_key_exists($roleName, $userAssignments);
                    $entry = $this->normalizeUserLicenceAssignmentEntry($userAssignments[$roleName] ?? null);
                    $roleValidUntil = $userLicenceRequired ? $entry['valid_until'] : null;
                    $isActivated = $userLicenceRequired ? $entry['is_activated'] : true;

                    $statuses[$roleName] = [
                        'assigned' => $userLicenceRequired ? $hasLicenceAssignment : true,
                        'valid_until' => $roleValidUntil,
                        'is_activated' => $isActivated,
                        'is_active' => $userLicenceRequired
                            ? ($hasLicenceAssignment && $this->isUserRoleAssignmentActive($schoolLicenceRequired, $school_licence->valid_until, $roleValidUntil, $isActivated))
                            : true,
                        'plan_id' => $userLicenceRequired ? ($entry['plan_id'] ?? null) : null,
                        'charged_price' => $userLicenceRequired ? ($entry['charged_price'] ?? null) : null,
                        'extra_storage_units' => $userLicenceRequired ? ($entry['extra_storage_units'] ?? null) : null,
                        'extra_storage_unit_price' => $userLicenceRequired ? ($entry['extra_storage_unit_price'] ?? null) : null,
                    ];
                }

                return [
                    (string) $user->id => $statuses,
                ];
            })
            ->all();

        return response()->json([
            'data' => UserResource::collection($users),
            'meta' => new PaginateResource($users),
            'roles' => $licenceModelRoles,
            'active_role_filters' => $activeRoleFilters,
            'role_statuses_by_user' => $roleStatusesByUser,
        ], 200);
    }

    public function loadSchoolLicenceUserRoles(SchoolLicence $school_licence, User $user, LicenceService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ((int) $user->school_id !== (int) $school_licence->school_id) {
            abort(422, 'Benutzer gehört nicht zur Schule der Lizenz.');
        }

        return response()->json(
            $this->schoolLicenceUserRolesPayload($school_licence, $user, $service),
            200
        );
    }

    public function saveSchoolLicenceUserRoles(
        SchoolLicenceUserRolesSaveRequest $request,
        SchoolLicence $school_licence,
        User $user,
        LicenceService $service
    ) {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ((int) $user->school_id !== (int) $school_licence->school_id) {
            abort(422, 'Benutzer gehört nicht zur Schule der Lizenz.');
        }

        $validated = $request->validated();

        $licenceModel = $this->mergedSchoolLicenceUserLicenceModel($school_licence->loadMissing('licence'), $service);
        $roleNames = collect($licenceModel['user_licence_required_by_role'] ?? [])
            ->filter(fn ($isRequired) => (bool) $isRequired)
            ->keys()
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $incomingByRole = collect($validated['roles'])
            ->filter(fn ($entry) => is_array($entry) && isset($entry['name']))
            ->keyBy(fn ($entry) => (string) $entry['name']);

        $assignments = app(SchoolUserLicenceAssignmentService::class)
            ->assignmentsForSchoolLicence($school_licence, $roleNames, [$user->id]);
        $userKey = (string) $user->id;
        $updatedUserAssignments = [];

        foreach ($roleNames as $roleName) {
            $entry = $incomingByRole->get($roleName);
            $userHasRole = $user->hasRole($roleName);
            $assigned = $userHasRole && (bool) ($entry['assigned'] ?? false);
            $validUntil = $assigned ? ($entry['valid_until'] ?? null) : null;
            $existingRoleEntry = $this->normalizeUserLicenceAssignmentEntry($assignments[$userKey][$roleName] ?? null);
            $isActivated = $assigned
                ? (bool) ($entry['is_activated'] ?? $existingRoleEntry['is_activated'] ?? false)
                : false;
            $planId = $assigned
                ? (isset($entry['plan_id']) ? (int) $entry['plan_id'] : ($existingRoleEntry['plan_id'] ?? null))
                : null;

            if ($assigned) {
                $existingChargedPrice = $existingRoleEntry['charged_price'] ?? null;
                $chargedPrice = array_key_exists('charged_price', $entry)
                    ? (is_numeric($entry['charged_price']) ? round((float) $entry['charged_price'], 2) : null)
                    : $existingChargedPrice;
                $existingExtraStorageUnits = $existingRoleEntry['extra_storage_units'] ?? null;
                $extraStorageUnits = array_key_exists('extra_storage_units', $entry)
                    ? (is_numeric($entry['extra_storage_units']) ? (int) $entry['extra_storage_units'] : null)
                    : $existingExtraStorageUnits;
                $existingExtraStorageUnitPrice = $existingRoleEntry['extra_storage_unit_price'] ?? null;
                $extraStorageUnitPrice = array_key_exists('extra_storage_unit_price', $entry)
                    ? (is_numeric($entry['extra_storage_unit_price']) ? round((float) $entry['extra_storage_unit_price'], 2) : null)
                    : $existingExtraStorageUnitPrice;
                $updatedUserAssignments[$roleName] = [
                    'valid_until' => $validUntil,
                    'is_activated' => $isActivated,
                    'plan_id' => $planId,
                    'charged_price' => $chargedPrice,
                    'extra_storage_units' => $extraStorageUnits,
                    'extra_storage_unit_price' => $extraStorageUnitPrice,
                ];

                continue;
            }
        }

        if (empty($updatedUserAssignments)) {
            unset($assignments[$userKey]);
        } else {
            $assignments[$userKey] = $updatedUserAssignments;
        }

        return DB::transaction(function () use ($school_licence, $assignments, $user, $service) {
            $school_licence->user_licence_assignments = $assignments;
            $school_licence->save();
            app(SchoolUserLicenceAssignmentService::class)->persistUserAssignments(
                $school_licence->fresh('licence'),
                $user->fresh('roles'),
                $assignments[(string) $user->id] ?? [],
                $this->mergedSchoolLicenceUserLicenceModel($school_licence->fresh('licence'), $service),
                $school_licence->fresh('licence')->licence
            );
            $this->syncStructuredAccessAssignmentsForUser($school_licence->fresh('licence'), $user->fresh('roles'), $service);

            return response()->json(
                $this->schoolLicenceUserRolesPayload($school_licence->fresh(), $user->fresh('roles'), $service),
                200
            );
        });
    }

    public function saveSchoolLicenceUserSpatieRoles(Request $request, SchoolLicence $school_licence, User $user, LicenceService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ((int) $user->school_id !== (int) $school_licence->school_id) {
            abort(422, 'Benutzer gehört nicht zur Schule der Lizenz.');
        }

        $validated = $request->validate([
            'role_names' => ['nullable', 'array'],
            'role_names.*' => ['nullable', 'string', 'max:255'],
        ]);

        $licenceModel = $this->mergedSchoolLicenceUserLicenceModel($school_licence->loadMissing('licence'), $service);
        $requiredByRole = is_array($licenceModel['user_licence_required_by_role'] ?? null)
            ? $licenceModel['user_licence_required_by_role']
            : [];
        $licenceRoleNames = collect(array_merge(
            is_array($licenceModel['affected_roles'] ?? null) ? $licenceModel['affected_roles'] : [],
            array_keys($requiredByRole)
        ))
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $selectedLicenceRoleNames = collect($validated['role_names'] ?? [])
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->filter(fn ($roleName) => in_array($roleName, $licenceRoleNames, true))
            ->unique()
            ->values()
            ->all();

        $currentRoleNames = $user->roles()
            ->pluck('name')
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $nonLicenceRoleNames = array_values(array_diff($currentRoleNames, $licenceRoleNames));
        $rolesToSync = array_values(array_unique(array_merge($nonLicenceRoleNames, $selectedLicenceRoleNames)));

        if ($user->hasRole('super_admin') && ! in_array('super_admin', $rolesToSync, true)) {
            $rolesToSync[] = 'super_admin';
        }

        sort($rolesToSync);

        return DB::transaction(function () use ($user, $rolesToSync, $school_licence, $licenceRoleNames, $selectedLicenceRoleNames, $service) {
            $user->syncRoles($rolesToSync);

            // Remove stale user-licence assignments for licence-model roles that are no longer assigned as Spatie roles.
            $assignments = is_array($school_licence->user_licence_assignments) ? $school_licence->user_licence_assignments : [];
            $userKey = (string) $user->id;
            if (isset($assignments[$userKey]) && is_array($assignments[$userKey])) {
                foreach (array_keys($assignments[$userKey]) as $roleName) {
                    if (! is_string($roleName)) {
                        continue;
                    }

                    $roleName = trim($roleName);
                    if ($roleName === '') {
                        continue;
                    }

                    if (in_array($roleName, $licenceRoleNames, true) && ! in_array($roleName, $selectedLicenceRoleNames, true)) {
                        unset($assignments[$userKey][$roleName]);
                    }
                }

                if (empty($assignments[$userKey])) {
                    unset($assignments[$userKey]);
                }

                $school_licence->user_licence_assignments = $assignments;
                $school_licence->save();
            }

            app(SchoolUserLicenceAssignmentService::class)->persistUserAssignments(
                $school_licence->fresh('licence'),
                $user->fresh('roles'),
                $assignments[(string) $user->id] ?? [],
                $this->mergedSchoolLicenceUserLicenceModel($school_licence->fresh('licence'), $service),
                $school_licence->fresh('licence')->licence
            );
            $this->syncStructuredAccessAssignmentsForUser($school_licence->fresh('licence'), $user->fresh('roles'), $service);

            return response()->json(
                $this->schoolLicenceUserRolesPayload($school_licence->fresh(), $user->fresh('roles'), $service),
                200
            );
        });
    }

    public function activateCurrentUserLicence(Request $request, SchoolLicence $school_licence, LicenceService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin', 'teaching_admin', 'materials_admin', 'materials_moderator', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ((int) $auth_user->school_id !== (int) $school_licence->school_id) {
            abort(422, 'Lizenz gehört nicht zur ausgewählten Schule.');
        }

        $validated = $request->validate([
            'role_name' => ['required', 'string', 'max:255'],
            'plan_id' => ['required', 'integer', 'min:1'],
        ]);

        $roleName = trim((string) $validated['role_name']);
        $planId = (int) $validated['plan_id'];
        if ($roleName === '') {
            abort(422, 'Rolle ist erforderlich.');
        }

        if (! $auth_user->hasRole($roleName)) {
            abort(422, 'Die Benutzerlizenz ist für Ihre Rolle nicht verfügbar.');
        }

        $licenceModel = $this->mergedSchoolLicenceUserLicenceModel($school_licence->loadMissing('licence'), $service);
        $requiredRoleNames = collect($licenceModel['user_licence_required_by_role'] ?? [])
            ->filter(fn ($isRequired) => (bool) $isRequired)
            ->keys()
            ->map(fn ($name) => is_string($name) ? trim($name) : '')
            ->filter()
            ->values()
            ->all();

        if (! in_array($roleName, $requiredRoleNames, true)) {
            abort(422, 'Für diese Rolle ist keine Benutzerlizenz erforderlich.');
        }

        $plansByRole = is_array($licenceModel['user_licence_plans_by_role'] ?? null)
            ? $licenceModel['user_licence_plans_by_role']
            : [];
        $rolePlans = isset($plansByRole[$roleName]) && is_array($plansByRole[$roleName])
            ? collect($plansByRole[$roleName])->filter(fn ($plan) => is_array($plan))
            : collect();

        $selectedPlan = $rolePlans->first(function (array $plan) use ($planId) {
            return isset($plan['id']) && (int) $plan['id'] === $planId;
        });

        if (! is_array($selectedPlan)) {
            abort(422, 'Ungültige Plan-Auswahl.');
        }

        if (config('schooltool.payment_active', false)) {
            return response()->json([
                'message' => 'Payment',
            ], 200);
        }

        if (! $this->isUserLicencePlanFree($selectedPlan['price_per_year'] ?? null)) {
            abort(422, 'Aktuell können nur kostenlose Optionen aktiviert werden.');
        }

        $assignments = is_array($school_licence->user_licence_assignments) ? $school_licence->user_licence_assignments : [];
        $userKey = (string) $auth_user->id;
        $userAssignments = isset($assignments[$userKey]) && is_array($assignments[$userKey])
            ? $assignments[$userKey]
            : [];

        $existingRoleEntry = $this->normalizeUserLicenceAssignmentEntry($userAssignments[$roleName] ?? null);
        $newValidUntil = $this->resolveFreeUserLicenceActivationValidUntil();

        $userAssignments[$roleName] = [
            'valid_until' => $newValidUntil,
            'is_activated' => true,
            'plan_id' => $planId,
        ];

        $assignments[$userKey] = $userAssignments;
        $school_licence->user_licence_assignments = $assignments;
        $school_licence->save();
        app(SchoolUserLicenceAssignmentService::class)->persistUserAssignments(
            $school_licence->fresh('licence'),
            $auth_user->fresh('roles'),
            $userAssignments,
            $licenceModel,
            $school_licence->fresh('licence')->licence
        );
        $this->syncStructuredAccessAssignmentsForUser($school_licence->fresh('licence'), $auth_user->fresh('roles'), $service);

        return response()->json([
            'message' => 'Benutzerlizenz aktiviert.',
            'valid_until' => $newValidUntil,
        ], 200);
    }

    public function renewCurrentUserLicence(Request $request, SchoolLicence $school_licence, LicenceService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin', 'teaching_admin', 'materials_admin', 'materials_moderator', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ((int) $auth_user->school_id !== (int) $school_licence->school_id) {
            abort(422, 'Lizenz gehört nicht zur ausgewählten Schule.');
        }

        $validated = $request->validate([
            'role_name' => ['required', 'string', 'max:255'],
            'plan_id' => ['required', 'integer', 'min:1'],
        ]);

        $roleName = trim((string) $validated['role_name']);
        $planId = (int) $validated['plan_id'];
        if ($roleName === '') {
            abort(422, 'Rolle ist erforderlich.');
        }

        if (! $auth_user->hasRole($roleName)) {
            abort(422, 'Die Benutzerlizenz ist für Ihre Rolle nicht verfügbar.');
        }

        $licenceModel = $this->mergedSchoolLicenceUserLicenceModel($school_licence->loadMissing('licence'), $service);
        $requiredRoleNames = collect($licenceModel['user_licence_required_by_role'] ?? [])
            ->filter(fn ($isRequired) => (bool) $isRequired)
            ->keys()
            ->map(fn ($name) => is_string($name) ? trim($name) : '')
            ->filter()
            ->values()
            ->all();

        if (! in_array($roleName, $requiredRoleNames, true)) {
            abort(422, 'Für diese Rolle ist keine Benutzerlizenz erforderlich.');
        }

        $plansByRole = is_array($licenceModel['user_licence_plans_by_role'] ?? null)
            ? $licenceModel['user_licence_plans_by_role']
            : [];
        $rolePlans = isset($plansByRole[$roleName]) && is_array($plansByRole[$roleName])
            ? collect($plansByRole[$roleName])->filter(fn ($plan) => is_array($plan))
            : collect();

        $selectedPlan = $rolePlans->first(function (array $plan) use ($planId) {
            return isset($plan['id']) && (int) $plan['id'] === $planId;
        });

        if (! is_array($selectedPlan)) {
            abort(422, 'Ungültige Plan-Auswahl.');
        }

        if (config('schooltool.payment_active', false)) {
            return response()->json([
                'message' => 'Payment',
            ], 200);
        }

        if (! $this->isUserLicencePlanFree($selectedPlan['price_per_year'] ?? null)) {
            abort(422, 'Aktuell können nur kostenlose Optionen verlängert werden.');
        }

        $assignments = is_array($school_licence->user_licence_assignments) ? $school_licence->user_licence_assignments : [];
        $userKey = (string) $auth_user->id;
        $userAssignments = isset($assignments[$userKey]) && is_array($assignments[$userKey])
            ? $assignments[$userKey]
            : [];

        $existingRoleEntry = $this->normalizeUserLicenceAssignmentEntry($userAssignments[$roleName] ?? null);
        if (! $existingRoleEntry['is_activated']) {
            abort(409, 'Die Benutzerlizenz ist nicht aktiv.');
        }

        $baseDate = null;
        if (is_string($existingRoleEntry['valid_until'] ?? null) && trim((string) $existingRoleEntry['valid_until']) !== '') {
            try {
                $baseDate = Carbon::parse((string) $existingRoleEntry['valid_until'])->startOfDay();
            } catch (\Throwable $exception) {
                $baseDate = null;
            }
        }
        if (! $baseDate) {
            $baseDate = now()->startOfDay();
        }

        $newValidUntil = $baseDate->copy()->addYear()->toDateString();

        $userAssignments[$roleName] = [
            'valid_until' => $newValidUntil,
            'is_activated' => true,
            'plan_id' => $planId,
        ];

        $assignments[$userKey] = $userAssignments;
        $school_licence->user_licence_assignments = $assignments;
        $school_licence->save();
        app(SchoolUserLicenceAssignmentService::class)->persistUserAssignments(
            $school_licence->fresh('licence'),
            $auth_user->fresh('roles'),
            $userAssignments,
            $licenceModel,
            $school_licence->fresh('licence')->licence
        );
        $this->syncStructuredAccessAssignmentsForUser($school_licence->fresh('licence'), $auth_user->fresh('roles'), $service);

        return response()->json([
            'message' => 'Benutzerlizenz verlängert.',
            'valid_until' => $newValidUntil,
        ], 200);
    }

    public function deactivateCurrentUserLicence(Request $request, SchoolLicence $school_licence, LicenceService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'register_admin', 'teaching_admin', 'materials_admin', 'materials_moderator', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ((int) $auth_user->school_id !== (int) $school_licence->school_id) {
            abort(422, 'Lizenz gehört nicht zur ausgewählten Schule.');
        }

        $validated = $request->validate([
            'role_name' => ['required', 'string', 'max:255'],
        ]);

        $roleName = trim((string) $validated['role_name']);
        if ($roleName === '') {
            abort(422, 'Rolle ist erforderlich.');
        }

        if (! $auth_user->hasRole($roleName)) {
            abort(422, 'Die Benutzerlizenz ist für Ihre Rolle nicht verfügbar.');
        }

        $licenceModel = $this->mergedSchoolLicenceUserLicenceModel($school_licence->loadMissing('licence'), $service);
        $requiredRoleNames = collect($licenceModel['user_licence_required_by_role'] ?? [])
            ->filter(fn ($isRequired) => (bool) $isRequired)
            ->keys()
            ->map(fn ($name) => is_string($name) ? trim($name) : '')
            ->filter()
            ->values()
            ->all();

        if (! in_array($roleName, $requiredRoleNames, true)) {
            abort(422, 'Für diese Rolle ist keine Benutzerlizenz erforderlich.');
        }

        $assignments = is_array($school_licence->user_licence_assignments) ? $school_licence->user_licence_assignments : [];
        $userKey = (string) $auth_user->id;
        $userAssignments = isset($assignments[$userKey]) && is_array($assignments[$userKey])
            ? $assignments[$userKey]
            : [];

        $existingRoleEntry = $this->normalizeUserLicenceAssignmentEntry($userAssignments[$roleName] ?? null);
        if ($existingRoleEntry['valid_until'] === null && $existingRoleEntry['plan_id'] === null && $existingRoleEntry['is_activated'] === false) {
            abort(409, 'Für diese Rolle ist aktuell keine Benutzerlizenz gespeichert.');
        }

        $userAssignments[$roleName] = [
            'valid_until' => $existingRoleEntry['valid_until'],
            'is_activated' => false,
            'plan_id' => $existingRoleEntry['plan_id'],
        ];

        $assignments[$userKey] = $userAssignments;
        $school_licence->user_licence_assignments = $assignments;
        $school_licence->save();
        app(SchoolUserLicenceAssignmentService::class)->persistUserAssignments(
            $school_licence->fresh('licence'),
            $auth_user->fresh('roles'),
            $userAssignments,
            $licenceModel,
            $school_licence->fresh('licence')->licence
        );
        $this->syncStructuredAccessAssignmentsForUser($school_licence->fresh('licence'), $auth_user->fresh('roles'), $service);

        return response()->json([
            'message' => 'Benutzerlizenz deaktiviert.',
        ], 200);
    }

    private function mergedSchoolLicenceUserLicenceModel(SchoolLicence $school_licence, LicenceService $service): array
    {
        $schoolModel = $service->normalizeLicenceModel($school_licence->licence_model);
        $baseModel = $service->normalizeLicenceModel($school_licence->licence?->licence_model);
        $baseStructuredConfiguration = $school_licence->licence
            ? $service->editableLicenceConfiguration($school_licence->licence)
            : [];
        $baseStructuredRoleNames = collect(array_merge(
            (bool) ($baseStructuredConfiguration['admin_licence_enabled'] ?? false)
                ? (is_array($baseStructuredConfiguration['admin_role_names'] ?? null) ? $baseStructuredConfiguration['admin_role_names'] : [])
                : [],
            (bool) ($baseStructuredConfiguration['user_licence_enabled'] ?? false)
                ? (is_array($baseStructuredConfiguration['user_role_names'] ?? null) ? $baseStructuredConfiguration['user_role_names'] : [])
                : []
        ))
            ->map(fn ($role) => is_string($role) ? trim($role) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $affectedRoles = collect(array_merge(
            is_array($schoolModel['affected_roles'] ?? null) ? $schoolModel['affected_roles'] : [],
            is_array($baseModel['affected_roles'] ?? null) ? $baseModel['affected_roles'] : [],
            $baseStructuredRoleNames
        ))
            ->map(fn ($role) => is_string($role) ? trim($role) : '')
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
        foreach ($baseStructuredRoleNames as $roleName) {
            if (! array_key_exists($roleName, $baseRequiredByRole)) {
                $baseRequiredByRole[$roleName] = true;
            }
        }

        $schoolPlansByRole = is_array($schoolModel['user_licence_plans_by_role'] ?? null)
            ? $schoolModel['user_licence_plans_by_role']
            : [];
        $basePlansByRole = is_array($baseModel['user_licence_plans_by_role'] ?? null)
            ? $baseModel['user_licence_plans_by_role']
            : [];

        $requiredByRole = [];
        $plansByRole = [];

        foreach ($affectedRoles as $roleName) {
            if (array_key_exists($roleName, $schoolRequiredByRole)) {
                $requiredByRole[$roleName] = (bool) $schoolRequiredByRole[$roleName];
            } else {
                $requiredByRole[$roleName] = (bool) ($baseRequiredByRole[$roleName] ?? false);
            }

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

        return [
            'school_licence_required' => (bool) ($schoolModel['school_licence_required'] ?? true),
            'affected_roles' => $affectedRoles,
            'user_licence_required_by_role' => $requiredByRole,
            'user_licence_plans_by_role' => $plansByRole,
        ];
    }

    private function syncStructuredAccessAssignmentsForUser(SchoolLicence $school_licence, User $user, LicenceService $service): void
    {
        $licence = $school_licence->licence;
        if (! $licence || (int) ($licence->licence_schema_version ?? 0) < 2) {
            return;
        }

        $configuration = $service->editableLicenceConfiguration($licence);
        $assignments = is_array($school_licence->user_licence_assignments) ? $school_licence->user_licence_assignments : [];
        $userAssignments = isset($assignments[(string) $user->id]) && is_array($assignments[(string) $user->id])
            ? $assignments[(string) $user->id]
            : [];
        $userRoleNames = $user->roles()
            ->pluck('name')
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->syncStructuredAccessAssignmentTypeForUser(
            $school_licence,
            $user,
            $userRoleNames,
            $userAssignments,
            'admin',
            is_array($configuration['admin_role_names'] ?? null) ? $configuration['admin_role_names'] : [],
            $configuration['admin_price_per_year'] ?? null
        );

        $this->syncStructuredAccessAssignmentTypeForUser(
            $school_licence,
            $user,
            $userRoleNames,
            $userAssignments,
            'user',
            is_array($configuration['user_role_names'] ?? null) ? $configuration['user_role_names'] : [],
            $configuration['user_price_per_year'] ?? null
        );
    }

    private function syncStructuredAccessAssignmentTypeForUser(
        SchoolLicence $school_licence,
        User $user,
        array $userRoleNames,
        array $userAssignments,
        string $assignmentType,
        array $configuredRoleNames,
        mixed $basePrice
    ): void {
        $matchingRoleNames = $this->matchingStructuredRoleNamesForUser($userRoleNames, $configuredRoleNames);
        $assignmentQuery = SchoolUserLicence::query()
            ->where('school_id', $school_licence->school_id)
            ->where('licence_id', $school_licence->licence_id)
            ->where('user_id', $user->id)
            ->where('assignment_type', $assignmentType);

        if (app(SchoolUserLicenceAssignmentService::class)->supportsRoleAssignments()) {
            $assignmentQuery->where('role_name', '');
        }

        if (empty($matchingRoleNames)) {
            $assignmentQuery->delete();

            return;
        }

        $entries = collect($matchingRoleNames)
            ->filter(fn (string $roleName) => array_key_exists($roleName, $userAssignments))
            ->map(fn (string $roleName) => $this->normalizeUserLicenceAssignmentEntry($userAssignments[$roleName] ?? null))
            ->values();

        if ($entries->isEmpty()) {
            $assignmentQuery->delete();

            return;
        }

        $chargedPrice = $entries
            ->map(fn (array $entry) => $entry['charged_price'] ?? null)
            ->first(fn ($value) => $value !== null);
        $basePricePerYear = is_numeric($basePrice) ? round((float) $basePrice, 2) : null;
        $payload = [
            'valid_until' => $this->aggregateStructuredAssignmentValidUntil($entries->all()),
            'base_price_per_year' => $basePricePerYear,
            'charged_price' => is_numeric($chargedPrice) ? round((float) $chargedPrice, 2) : null,
            'is_active' => $entries->contains(fn (array $entry) => (bool) ($entry['is_activated'] ?? false)),
        ];

        $existingAssignment = $assignmentQuery->first();
        if ($existingAssignment) {
            $existingAssignment->fill($payload);
            $existingAssignment->save();

            return;
        }

        SchoolUserLicence::create([
            'school_id' => $school_licence->school_id,
            'licence_id' => $school_licence->licence_id,
            'user_id' => $user->id,
            'assignment_type' => $assignmentType,
            'valid_from' => null,
            ...$payload,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function matchingStructuredRoleNamesForUser(array $userRoleNames, array $configuredRoleNames): array
    {
        $normalizedUserRoleNames = collect($userRoleNames)
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $normalizedConfiguredRoleNames = collect($configuredRoleNames)
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($normalizedUserRoleNames) || empty($normalizedConfiguredRoleNames)) {
            return [];
        }

        if (in_array('*', $normalizedConfiguredRoleNames, true)) {
            return $normalizedUserRoleNames;
        }

        return array_values(array_intersect($normalizedUserRoleNames, $normalizedConfiguredRoleNames));
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function aggregateStructuredAssignmentValidUntil(array $entries): ?string
    {
        $validUntilValues = collect($entries)
            ->map(fn (array $entry) => isset($entry['valid_until']) && is_string($entry['valid_until']) ? trim($entry['valid_until']) : null)
            ->values();

        if ($validUntilValues->contains(fn ($value) => $value === null || $value === '')) {
            return null;
        }

        return $validUntilValues
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->max();
    }

    private function schoolLicenceHasRemainingUserLicenceAssignments(SchoolLicence $school_licence): bool
    {
        return SchoolUserLicence::query()
            ->where('school_id', $school_licence->school_id)
            ->where('licence_id', $school_licence->licence_id)
            ->exists();
    }

    private function schoolLicenceUserRolesPayload(SchoolLicence $school_licence, User $user, LicenceService $service): array
    {
        $licenceModel = $this->mergedSchoolLicenceUserLicenceModel($school_licence->loadMissing('licence'), $service);
        $requiredByRole = is_array($licenceModel['user_licence_required_by_role'] ?? null)
            ? $licenceModel['user_licence_required_by_role']
            : [];

        $roleNames = collect($licenceModel['affected_roles'] ?? [])
            ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($roleNames)) {
            $roleNames = collect(array_keys($requiredByRole))
                ->map(fn ($roleName) => is_string($roleName) ? trim($roleName) : '')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $assignments = is_array($school_licence->user_licence_assignments) ? $school_licence->user_licence_assignments : [];
        $userAssignments = isset($assignments[(string) $user->id]) && is_array($assignments[(string) $user->id])
            ? $assignments[(string) $user->id]
            : [];

        $plansByRole = is_array($licenceModel['user_licence_plans_by_role'] ?? null)
            ? $licenceModel['user_licence_plans_by_role']
            : [];

        $roles = collect($roleNames)
            ->map(function ($roleName) use ($user, $userAssignments, $plansByRole, $requiredByRole) {
                $userLicenceRequired = (bool) ($requiredByRole[$roleName] ?? false);
                $hasUserRole = $user->hasRole($roleName);
                $hasLicenceAssignment = is_array($userAssignments) && array_key_exists($roleName, $userAssignments);
                $entry = ($hasUserRole && $hasLicenceAssignment)
                    ? $this->normalizeUserLicenceAssignmentEntry($userAssignments[$roleName] ?? null)
                    : ['valid_until' => null, 'is_activated' => false, 'plan_id' => null];
                $assigned = $userLicenceRequired ? ($hasUserRole && $hasLicenceAssignment) : $hasUserRole;
                $isActivated = $userLicenceRequired ? $entry['is_activated'] : true;
                $validUntil = $userLicenceRequired ? $entry['valid_until'] : null;
                $planId = $userLicenceRequired ? $entry['plan_id'] : null;

                return [
                    'name' => $roleName,
                    'assigned' => $assigned,
                    'user_licence_required' => $userLicenceRequired,
                    'is_user_role_assigned' => $hasUserRole,
                    'valid_until' => $validUntil,
                    'is_activated' => $isActivated,
                    'plan_id' => $planId,
                    'charged_price' => $entry['charged_price'] ?? null,
                    'extra_storage_units' => $entry['extra_storage_units'] ?? null,
                    'extra_storage_unit_price' => $entry['extra_storage_unit_price'] ?? null,
                    'plans' => isset($plansByRole[$roleName]) && is_array($plansByRole[$roleName])
                        ? array_values($plansByRole[$roleName])
                        : [],
                ];
            })
            ->values()
            ->all();

        return [
            'user' => new UserResource($user->loadMissing('roles')),
            'school_licence_valid_until' => $school_licence->valid_until,
            'roles' => $roles,
        ];
    }

    private function isUserRoleAssignmentActive(bool $schoolLicenceRequired, ?string $schoolLicenceValidUntil, ?string $userRoleValidUntil, bool $isActivated = false): bool
    {
        if ($schoolLicenceRequired && ! $this->isDateActive($schoolLicenceValidUntil)) {
            return false;
        }

        return $this->isDateActive($userRoleValidUntil);
    }

    private function normalizeUserLicenceAssignmentEntry(mixed $entry): array
    {
        // Legacy shape: role => "YYYY-MM-DD" (or null)
        if (is_string($entry)) {
            $validUntil = trim($entry);

            return [
                'valid_until' => $validUntil !== '' ? $validUntil : null,
                'is_activated' => false,
                'plan_id' => null,
                'charged_price' => null,
                'extra_storage_units' => null,
                'extra_storage_unit_price' => null,
            ];
        }

        if (! is_array($entry)) {
            return [
                'valid_until' => null,
                'is_activated' => false,
                'plan_id' => null,
                'charged_price' => null,
                'extra_storage_units' => null,
                'extra_storage_unit_price' => null,
            ];
        }

        $rawValidUntil = $entry['valid_until'] ?? null;
        $validUntil = is_string($rawValidUntil) && trim($rawValidUntil) !== '' ? trim($rawValidUntil) : null;
        $planId = isset($entry['plan_id']) && is_numeric($entry['plan_id']) && (int) $entry['plan_id'] > 0
            ? (int) $entry['plan_id']
            : null;
        $chargedPrice = isset($entry['charged_price']) && is_numeric($entry['charged_price'])
            ? round((float) $entry['charged_price'], 2)
            : null;
        $extraStorageUnits = isset($entry['extra_storage_units']) && is_numeric($entry['extra_storage_units']) && (int) $entry['extra_storage_units'] >= 0
            ? (int) $entry['extra_storage_units']
            : null;
        $extraStorageUnitPrice = isset($entry['extra_storage_unit_price']) && is_numeric($entry['extra_storage_unit_price'])
            ? round((float) $entry['extra_storage_unit_price'], 2)
            : null;

        return [
            'valid_until' => $validUntil,
            'is_activated' => (bool) ($entry['is_activated'] ?? false),
            'plan_id' => $planId,
            'charged_price' => $chargedPrice,
            'extra_storage_units' => $extraStorageUnits,
            'extra_storage_unit_price' => $extraStorageUnitPrice,
        ];
    }

    private function isUserLicencePlanFree(mixed $rawPrice): bool
    {
        if ($rawPrice === null) {
            return true;
        }

        $price = trim((string) $rawPrice);
        if ($price === '') {
            return true;
        }

        $lower = mb_strtolower($price);
        foreach (['kostenlos', 'gratis', 'free'] as $token) {
            if (str_contains($lower, $token)) {
                return true;
            }
        }

        // Accept "0,-", "EUR 0 / Jahr", "1.200,00", etc.
        $normalized = preg_replace('/\s+/', '', $price);
        if (! is_string($normalized)) {
            return false;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '.', $normalized);
        }

        $normalized = preg_replace('/[^0-9.\-]/', '', $normalized);
        if (! is_string($normalized)) {
            return false;
        }

        $normalized = preg_replace('/([0-9])\.(?=-|$)/', '$1', $normalized) ?? $normalized; // "0.-" -> "0"
        $normalized = preg_replace('/([0-9])-(?=$)/', '$1', $normalized) ?? $normalized; // "0-" -> "0"

        if (! preg_match('/-?\d+(?:\.\d+)?/', $normalized, $matches)) {
            return false;
        }

        return ((float) $matches[0]) <= 0.0;
    }

    private function resolveFreeUserLicenceActivationValidUntil(): string
    {
        $configuredDate = config('schooltool.licence_free_activation_date');

        if (is_string($configuredDate) && trim($configuredDate) !== '') {
            try {
                return Carbon::parse(trim($configuredDate))->toDateString();
            } catch (\Throwable $exception) {
                // Fallback keeps activation usable if config is malformed.
            }
        }

        return now()->startOfDay()->addYear()->toDateString();
    }

    private function isDateActive(?string $validUntil): bool
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

    public function addAdmin(SchoolAddAdminRequest $request, SchoolService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $user = $service->addAdmin($auth_user->selectedSchool->id, $auth_user->selectedSchoolyear->id, $validated['data'], $validated['roles']);

        return response()->json(new UserResource($user), 200);
    }

    public function deleteAdmin(SchoolDeleteAdminRequest $request, SchoolService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        if ($validated['admin_id'] == $auth_user->id) {
            abort(409, 'Man kann sich selbst nicht löschen.');
        }

        $service->deleteAdmin($validated['admin_id'], $validated['is_delete_complete']);

        return response()->noContent();
    }
}
