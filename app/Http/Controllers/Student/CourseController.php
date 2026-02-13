<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SchoolTool;
use App\Models\TeachingCourse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    public function index()
    {
        if (! $auth_user = $this->userHasRole(['student'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        // Get active schoolyear from SchoolTool
        $schoolTool = SchoolTool::where('school_id', $auth_user->school_id)->first();
        $active_schoolyear_id = $schoolTool?->active_schoolyear_id ?? $auth_user->schoolyear_id;

        // Get all courses for this school/schoolyear and filter in PHP
        // This is more reliable than whereJsonContains for different JSON formats
        $courses = TeachingCourse::where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $active_schoolyear_id)
            ->with('user:id,first_name,last_name,short')
            ->get()
            ->filter(function ($course) use ($auth_user) {
                // Students is an array of objects with 'id' property
                // Extract the IDs and check if user's ID is in the list
                $students = $course->students ?? [];
                $studentIds = array_column($students, 'id');
                return in_array($auth_user->id, $studentIds) || in_array((string) $auth_user->id, $studentIds);
            })
            ->map(function ($course) {
                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'description' => $course->description,
                    'teacher' => $course->user ? ($course->user->short ?: ($course->user->first_name . ' ' . $course->user->last_name)) : '—',
                    'classes' => $course->classes,
                    'students_count' => count($course->students ?? []),
                ];
            })
            ->values();

        return response()->json([
            'courses' => $courses,
        ], 200);
    }
}
