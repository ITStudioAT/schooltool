<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SchoolAddAdminRequest;
use App\Http\Requests\Admin\SchoolAddLicenceRequest;
use App\Http\Requests\Admin\SchoolDeleteAdminRequest;
use App\Http\Requests\Admin\SchoolDeleteLicenceRequest;
use App\Http\Requests\Admin\SchoolDeleteSchoolsRequest;
use App\Http\Requests\Admin\SchoolIndexRequest;
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
use App\Services\FileUploadService;
use App\Services\LicenceService;
use App\Services\SchoolService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
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


        $schools = School::query()
            ->when($search_string, function ($query, $search_string) {
                $query->where(function ($q) use ($search_string) {
                    $q->where('long_name', 'like', "%{$search_string}%")
                        ->orWhere('short_name', 'like', "%{$search_string}%")
                        ->orWhere('email', 'like', "%{$search_string}%");
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

        if (in_array($auth_user->school_id, $validated)) abort(409, "Eine zu löschende Schule ist ihnen zugeordnet. Das ist nicht zulässig.");

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

        $school = School::findOrFail($auth_user->school_id);
        $logo   = 'logo';

        $result = $fileUploadService->uploadNext(
            $request,
            'app/public/temp/' . $auth_user->id,
            $logo,
            ['width' => 200, 'height' => 100]
        );

        // Partial chunk → just forward the 200 "OK" response
        if ($result instanceof Response) {
            return $result; // "OK" or final name wrapped in Response
        }


        $school->logo = $result;
        $school->save();
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

        if (! $auth_user = $this->userHasRole(['admin'])) {
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


        $school_licence = $service->schoolAddLicence($auth_user->selectedSchool, $validated['data']);
        $licences = School::find($school_licence->school_id)->licences;
        return response()->json(LicenceResource::collection($licences), 200);
    }

    public function deleteLicence(SchoolDeleteLicenceRequest $request, LicenceService $service)
    {
        if (! $auth_user = $this->userHasRole(['super_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $school_licence = SchoolLicence::findOrFail($validated['school_licence_id']);
        $school_id = $school_licence->id;
        $school_licence->delete();

        $licences = School::find($school_id)->licences;
        return response()->json(LicenceResource::collection($licences), 200);
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
