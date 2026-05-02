<?php

namespace App\Http\Controllers\Admin\StudentsTimetables;

use App\Http\Controllers\Controller;
use App\Services\StudentsTimetables\StudentsTimetablesService;
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
}
