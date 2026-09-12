<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourse;
use App\Services\TeachingCourseEvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseEvaluationController extends Controller
{
    public function show(Request $request, TeachingCourse $course, TeachingCourseEvaluationService $service): JsonResponse
    {
        $user = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($user, 403, 'Sie haben keine Berechtigung');
        $this->authorizeTeachingCourseAccess($course, $user);
        $validated = $request->validate(['semester' => ['sometimes', 'integer', 'in:1,2,3']]);

        return response()->json(['data' => $service->report($course, (int) ($validated['semester'] ?? 3))]);
    }
}
