<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Teaching\StoreSchoolHourRequest;
use App\Http\Requests\Admin\Teaching\UpdateSchoolHourRequest;
use App\Http\Resources\Admin\Teaching\SchoolHourResource;
use App\Models\TeachingSchoolHour;
use App\Services\SchoolHourService;

class SchoolHourController extends Controller
{
    public function index(SchoolHourService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $schoolHours = $service->listForUser($auth_user);

        return response()->json([
            'data' => SchoolHourResource::collection($schoolHours),
        ]);
    }

    public function store(StoreSchoolHourRequest $request, SchoolHourService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validated();
        $schoolHours = $service->createManyForUser($auth_user, $validated['entries']);

        return response()->json([
            'data' => SchoolHourResource::collection($schoolHours),
            'created' => $schoolHours->count(),
        ], 201);
    }

    public function update(UpdateSchoolHourRequest $request, TeachingSchoolHour $school_hour, SchoolHourService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $schoolHour = $service->updateForUser($auth_user, $school_hour, $request->validated());

        return response()->json([
            'data' => new SchoolHourResource($schoolHour),
        ]);
    }

    public function destroy(TeachingSchoolHour $school_hour, SchoolHourService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $service->deleteForUser($auth_user, $school_hour);

        return response()->json(null, 204);
    }
}
