<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourse;
use App\Services\TeachingCourseDateService;
use Illuminate\Http\JsonResponse;

class TeachingCourseCurriculumController extends Controller
{
    public function destroy(TeachingCourse $course, TeachingCourseDateService $service): JsonResponse
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $authUser);

        $result = $service->removeCurriculumAssignment($course);

        return response()->json($result + ['teaching_curriculum_id' => null]);
    }
}
