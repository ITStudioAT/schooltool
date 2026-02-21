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
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\LicenceService;
use App\Services\SchoolService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Response;

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
            ->with(['licences' => function ($query) {
                $query->orderBy('name');
            }])
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
            'meta' => new PaginateResource($schools)
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
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }
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

        if (in_array($auth_user->school_id, $validated)) abort(409, "Eine zu löschende Schule ist aktuell ihnen zugeordnet. Das ist nicht zulässig.");

        $service->deleteSchools($validated);

        return response()->noContent();
    }

    public function uploadLogo(Request $request, FileUploadService $fileUploadService)
    {
        if (! $auth_user = $this->userHasRole(['admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $id = $fileUploadService->upload();

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
            ['width' => 200, 'height' => 100]
        );

        // Partial chunk → just forward the 200 "OK" response
        if ($result instanceof Response) {
            return $result; // "OK" or final name wrapped in Response
        }


        //$school->logo = $result;
        // $school->save();
        return response($result, 200)->header('Content-Type', 'text/plain');

        // return $result; // already a proper Response from the service
    }

    public function loadSwitchableSchools(Request $request, SchoolService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $schools = $service->loadSwitchableSchools($auth_user);

        return response()->json(SchoolResource::collection($schools), 200);
    }

    public function switchSchool(SchoolSwitchSchoolRequest $request, SchoolService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();

        $auth_user = $service->switchSchool($auth_user, $validated['school_id']);
        return response()->noContent();
    }

    public function loadSchoolInfos(SchoolLoadSchoolLicencesRequest $request, SchoolService $service)
    {

        if (! $auth_user = $this->userHasRole(['admin', 'register_admin', 'tutoring_admin', 'teacher'])) {
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

    public function deleteLicence(SchoolDeleteLicenceRequest $request, LicenceService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $school_licence = SchoolLicence::findOrFail($validated['school_licence_id']);
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

        $licenceModel = $service->normalizeLicenceModel($school_licence->licence_model);
        $schoolLicenceRequired = (bool) ($licenceModel['school_licence_required'] ?? true);

        $licenceModelRoles = collect($licenceModel['user_licence_required_by_role'] ?? [])
            ->filter(fn($isRequired) => (bool) $isRequired)
            ->keys()
            ->map(fn($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $selectedRoles = collect($validated['role_names'] ?? [])
            ->map(fn($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $activeRoleFilters = collect($selectedRoles)
            ->filter(fn($roleName) => in_array($roleName, $licenceModelRoles, true))
            ->values()
            ->all();

        $shouldApplyRoleFilter = ! empty($activeRoleFilters);
        $assignments = is_array($school_licence->user_licence_assignments) ? $school_licence->user_licence_assignments : [];

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
            ->when($shouldApplyRoleFilter, fn($query) => $query->whereHas('roles', fn($roleQuery) => $roleQuery->whereIn('name', $activeRoleFilters)));

        if ($expiredOnly) {
            if (empty($licenceModelRoles)) {
                $usersQuery->whereRaw('1=0');
            } elseif ($schoolLicenceRequired && ! $this->isDateActive($school_licence->valid_until)) {
                $usersQuery->whereHas('roles', fn($roleQuery) => $roleQuery->whereIn('name', $licenceModelRoles));
            } else {
                $outdatedUserIds = collect($assignments)
                    ->filter(fn($userAssignments) => is_array($userAssignments))
                    ->filter(function (array $userAssignments) use ($licenceModelRoles) {
                        foreach ($licenceModelRoles as $roleName) {
                            $rawValidUntil = $userAssignments[$roleName] ?? null;
                            if (! is_string($rawValidUntil) || trim($rawValidUntil) === '') {
                                continue;
                            }

                            $validUntil = trim($rawValidUntil);
                            if (! $this->isDateActive($validUntil)) {
                                return true;
                            }
                        }

                        return false;
                    })
                    ->keys()
                    ->map(fn($userId) => (int) $userId)
                    ->filter(fn($userId) => $userId > 0)
                    ->values()
                    ->all();

                if (empty($outdatedUserIds)) {
                    $usersQuery->whereRaw('1=0');
                } else {
                    $usersQuery
                        ->whereIn('id', $outdatedUserIds)
                        ->whereHas('roles', fn($roleQuery) => $roleQuery->whereIn('name', $licenceModelRoles));
                }
            }
        }

        $users = $usersQuery
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(config('schooltool.pagination'));

        $roleStatusesByUser = $users->getCollection()
            ->mapWithKeys(function (User $user) use ($licenceModelRoles, $assignments, $school_licence, $schoolLicenceRequired) {
                $userAssignments = isset($assignments[(string) $user->id]) && is_array($assignments[(string) $user->id])
                    ? $assignments[(string) $user->id]
                    : [];

                $userRoleNames = $user->roles
                    ->pluck('name')
                    ->map(fn($roleName) => is_string($roleName) ? trim($roleName) : '')
                    ->filter()
                    ->values()
                    ->all();
                $userRoleLookup = array_flip($userRoleNames);

                $statuses = [];
                foreach ($licenceModelRoles as $roleName) {
                    if (! isset($userRoleLookup[$roleName])) {
                        continue;
                    }

                    $rawRoleValidUntil = $userAssignments[$roleName] ?? null;
                    $roleValidUntil = is_string($rawRoleValidUntil) && trim($rawRoleValidUntil) !== ''
                        ? trim($rawRoleValidUntil)
                        : null;

                    $statuses[$roleName] = [
                        'valid_until' => $roleValidUntil,
                        'is_active' => $this->isUserRoleAssignmentActive($schoolLicenceRequired, $school_licence->valid_until, $roleValidUntil),
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

        $licenceModel = $service->normalizeLicenceModel($school_licence->licence_model);
        $roleNames = collect($licenceModel['user_licence_required_by_role'] ?? [])
            ->filter(fn($isRequired) => (bool) $isRequired)
            ->keys()
            ->map(fn($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $incomingByRole = collect($validated['roles'])
            ->filter(fn($entry) => is_array($entry) && isset($entry['name']))
            ->keyBy(fn($entry) => (string) $entry['name']);

        $assignments = is_array($school_licence->user_licence_assignments) ? $school_licence->user_licence_assignments : [];
        $userKey = (string) $user->id;
        $updatedUserAssignments = [];

        foreach ($roleNames as $roleName) {
            $entry = $incomingByRole->get($roleName);
            $assigned = (bool) ($entry['assigned'] ?? false);
            $validUntil = $assigned ? ($entry['valid_until'] ?? null) : null;

            if ($assigned) {
                if (! $user->hasRole($roleName)) {
                    $user->assignRole($roleName);
                }
                $updatedUserAssignments[$roleName] = $validUntil;
                continue;
            }

            if ($user->hasRole($roleName)) {
                $user->removeRole($roleName);
            }
        }

        if (empty($updatedUserAssignments)) {
            unset($assignments[$userKey]);
        } else {
            $assignments[$userKey] = $updatedUserAssignments;
        }

        $school_licence->user_licence_assignments = $assignments;
        $school_licence->save();

        return response()->json(
            $this->schoolLicenceUserRolesPayload($school_licence->fresh(), $user->fresh('roles'), $service),
            200
        );
    }

    private function schoolLicenceUserRolesPayload(SchoolLicence $school_licence, User $user, LicenceService $service): array
    {
        $licenceModel = $service->normalizeLicenceModel($school_licence->licence_model);
        $roleNames = collect($licenceModel['user_licence_required_by_role'] ?? [])
            ->filter(fn($isRequired) => (bool) $isRequired)
            ->keys()
            ->map(fn($roleName) => is_string($roleName) ? trim($roleName) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $assignments = is_array($school_licence->user_licence_assignments) ? $school_licence->user_licence_assignments : [];
        $userAssignments = isset($assignments[(string) $user->id]) && is_array($assignments[(string) $user->id])
            ? $assignments[(string) $user->id]
            : [];

        $roles = collect($roleNames)
            ->map(function ($roleName) use ($user, $userAssignments) {
                return [
                    'name' => $roleName,
                    'assigned' => $user->hasRole($roleName),
                    'valid_until' => isset($userAssignments[$roleName]) ? $userAssignments[$roleName] : null,
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

    private function isUserRoleAssignmentActive(bool $schoolLicenceRequired, ?string $schoolLicenceValidUntil, ?string $userRoleValidUntil): bool
    {
        if ($schoolLicenceRequired && ! $this->isDateActive($schoolLicenceValidUntil)) {
            return false;
        }

        return $this->isDateActive($userRoleValidUntil);
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

        if ($validated['admin_id'] == $auth_user->id) abort(409, "Man kann sich selbst nicht löschen.");

        $service->deleteAdmin($validated['admin_id'], $validated['is_delete_complete']);
        return response()->noContent();
    }
}
