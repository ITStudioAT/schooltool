<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Teaching\UpdateCourseStudentSpecialInformationRequest;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CourseStudentSpecialInformationController extends Controller
{
    public function show(TeachingCourse $course, TeachingCourseStudent $courseStudent): JsonResponse
    {
        Gate::authorize('view', $course);
        abort_unless((int) $courseStudent->teaching_course_id === (int) $course->id, 404);

        return $this->response($courseStudent);
    }

    public function update(
        UpdateCourseStudentSpecialInformationRequest $request,
        TeachingCourse $course,
        TeachingCourseStudent $courseStudent,
    ): JsonResponse {
        abort_unless((int) $courseStudent->teaching_course_id === (int) $course->id, 404);

        $information = trim((string) $request->validated('special_information'));
        $courseStudent->special_information = $information !== '' ? $information : null;
        $courseStudent->save();

        return $this->response($courseStudent);
    }

    private function response(TeachingCourseStudent $courseStudent): JsonResponse
    {
        return response()->json([
            'data' => [
                'special_information' => $courseStudent->special_information ?? '',
                'has_special_information' => $courseStudent->hasSpecialInformation(),
            ],
        ])->header('Cache-Control', 'no-store, private');
    }
}
