<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\SchoolHourResource;
use App\Services\SchoolHourService;
use Illuminate\Http\JsonResponse;

class SchoolHourImportsController extends Controller
{
    public function store(SchoolHourService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $schoolHours = $service->importPreviousYearForUser($authUser);

        return response()->json([
            'data' => SchoolHourResource::collection($schoolHours),
            'imported' => $schoolHours->count(),
        ], 201);
    }
}
