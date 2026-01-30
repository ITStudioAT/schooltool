<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use Illuminate\Http\Request;

class CourseDateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
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

        return response()->json(['data' => $dates]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, TeachingCourse $course)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'hours' => 'nullable|array',
            'content' => 'nullable|string|max:4096',
            'status' => 'nullable|array',
        ]);

        $courseDate = $course->teachingCourseDates()->create($validated);

        return response()->json($courseDate, 201);
    }

    /**
     * Display the specified resource.
     */
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

        return response()->json($date);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TeachingCourse $course, TeachingCourseDate $date)
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

        $validated = $request->validate([
            'date' => 'required|date',
            'hours' => 'nullable|array',
            'content' => 'nullable|string|max:4096',
            'status' => 'nullable|array',
        ]);

        $date->update($validated);

        return response()->json($date);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TeachingCourse $course, TeachingCourseDate $date)
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

        $date->delete();

        return response()->json(null, 204);
    }
}
