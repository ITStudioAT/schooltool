<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\SchoolHourResource;
use App\Services\SchoolHourService;
use App\Services\StudentsTimetables\StudentsTimetablesService;
use App\Services\StudentsTimetables\StudentTimetableOverviewService;
use Illuminate\Http\JsonResponse;

class StudentsTimetablesController extends Controller
{
    public function index(StudentsTimetablesService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        return response()->json([
            'data' => $service->dashboardForUser($authUser),
        ]);
    }

    public function schoolHours(SchoolHourService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        return response()->json([
            'data' => SchoolHourResource::collection($service->listForUser($authUser)),
        ]);
    }

    public function courseGroups(StudentTimetableOverviewService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'studentstimetables_admin'])) {
            abort(403, 'Sie haben keine Berechtigung.');
        }

        return response()->json([
            'data' => $service->courseGroupsForUser($authUser),
        ]);
    }
}
