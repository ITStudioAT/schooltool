<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\CourseDateResource;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Services\TeachingCourseDateService;
use Illuminate\Http\Request;

class CourseDateController extends Controller
{
    public function index(TeachingCourse $course)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $dates = $course->teachingCourseDates()
            ->orderBy('date')
            ->get();

        return response()->json(['data' => CourseDateResource::collection($dates)]);
    }

    public function store(Request $request, TeachingCourseDateService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'course_id' => 'required|integer|exists:teaching_courses,id',
            'from' => 'required|date',
            'until' => 'nullable|date',
            'hours' => 'required|array',
            'hours.*' => 'integer|min:1|max:20',
            'interval' => 'required|integer|in:1,2,3,4',
            'status' => 'nullable|array',
        ]);

        $course = TeachingCourse::findOrFail($validated['course_id']);

        if ($auth_user->school_id != $course->school_id) {
            abort(409, 'Kein Zugriff auf diese Schule');
        }

        if ($auth_user->schoolyear_id != $course->schoolyear_id) {
            abort(409, 'Kein Zugriff auf dieses Schuljahr');
        }

        $createdDates = $service->createDates(
            $course->id,
            $validated['from'],
            $validated['until'] ?? null,
            $validated['hours'],
            $validated['interval']
        );

        return response()->json(['data' => CourseDateResource::collection($createdDates), 'count' => count($createdDates)], 201);
    }

    public function show(TeachingCourse $course, TeachingCourseDate $date)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($date->teaching_course_id !== $course->id) {
            abort(404);
        }

        return response()->json(new CourseDateResource($date));
    }

    public function update(Request $request, TeachingCourseDate $course_date, TeachingCourseDateService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_date->teachingCourse;

        if (! $course || $course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'hours' => 'nullable|array',
            'content' => 'nullable|string|max:4096',
            'status' => 'nullable|array',
            'status.*' => 'string|in:pruefung',
            'attendance' => 'nullable|array',
            'attendance.*' => 'boolean',
            'attendance_checked' => 'nullable|boolean',
        ]);

        $service->updateCourseDate($course_date, $validated, $course);

        return response()->json(new CourseDateResource($course_date));
    }

    public function destroy(TeachingCourseDate $course_date)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_date->teachingCourse;

        if (! $course || $course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course_date->delete();

        return response()->json(null, 204);
    }

    public function updateStatus(Request $request, TeachingCourseDate $course_date, TeachingCourseDateService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_date->teachingCourse;

        if (! $course || $course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'status' => 'nullable|array',
            'status.*' => 'string|in:pruefung',
            'attendance' => 'nullable|array',
            'attendance.*' => 'boolean',
            'attendance_checked' => 'nullable|boolean',
            'toggle_student_id' => 'nullable|integer',
            'client_toggle_version' => 'nullable|string|max:32',
        ]);

        $service->updateCourseDateStatus($course_date, $validated, $course);

        $course_date->refresh();

        return response()->json(new CourseDateResource($course_date));
    }
}
