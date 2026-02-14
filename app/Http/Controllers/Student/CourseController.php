<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SchoolTool;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseBehaviourEntry;
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
            ->with('user:id,first_name,last_name,short,email')
            ->get()
            ->filter(function ($course) use ($auth_user) {
                // Students is an array of objects with 'id' property
                // Extract the IDs and check if user's ID is in the list
                $students = $course->students ?? [];
                $studentIds = array_column($students, 'id');
                return in_array($auth_user->id, $studentIds) || in_array((string) $auth_user->id, $studentIds);
            })
            ->map(function ($course) use ($auth_user) {
                // Find the current student's data including stars
                $students = $course->students ?? [];
                $studentData = null;
                foreach ($students as $student) {
                    if (isset($student['id']) && ($student['id'] == $auth_user->id || (string) $student['id'] === (string) $auth_user->id)) {
                        $studentData = $student;
                        break;
                    }
                }

                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'description' => $course->description,
                    'teaching_schema_id' => $course->teaching_schema_id,
                    'teacher' => $course->user ? ($course->user->short ?: ($course->user->first_name . ' ' . $course->user->last_name)) : '—',
                    'teacher_email' => $course->user?->email ?? null,
                    'classes' => $course->classes,
                    'students_count' => count($course->students ?? []),
                    'stars' => $studentData['stars'] ?? [],
                    'sem_1_grade' => $studentData['sem_1_grade'] ?? null,
                    'sem_2_grade' => $studentData['sem_2_grade'] ?? null,
                    'sem_grade' => $studentData['sem_grade'] ?? null,
                    'behaviour_1_grade' => $studentData['behaviour_1_grade'] ?? null,
                    'behaviour_2_grade' => $studentData['behaviour_2_grade'] ?? null,
                    'behaviour_grade' => $studentData['behaviour_grade'] ?? null,
                ];
            })
            ->values();

        return response()->json([
            'courses' => $courses,
        ], 200);
    }

    public function show($courseId)
    {
        if (! $auth_user = $this->userHasRole(['student'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        // Get active schoolyear from SchoolTool
        $schoolTool = SchoolTool::where('school_id', $auth_user->school_id)->first();
        $active_schoolyear_id = $schoolTool?->active_schoolyear_id ?? $auth_user->schoolyear_id;

        // Get the course
        $course = TeachingCourse::with('user:id,first_name,last_name,short,email,teaching_notifications,teaching_behaviour')
            ->where('id', $courseId)
            ->where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $active_schoolyear_id)
            ->first();

        if (!$course) {
            abort(404, 'Fach nicht gefunden');
        }

        // Check if student is enrolled in this course
        $students = $course->students ?? [];
        $studentIds = array_column($students, 'id');
        if (!in_array($auth_user->id, $studentIds) && !in_array((string) $auth_user->id, $studentIds)) {
            abort(403, 'Sie sind nicht in diesem Fach eingeschrieben');
        }

        // Find the current student's data including stars and grades
        $studentData = null;
        foreach ($students as $student) {
            if (isset($student['id']) && ($student['id'] == $auth_user->id || (string) $student['id'] === (string) $auth_user->id)) {
                $studentData = $student;
                break;
            }
        }

        // Get notifications (TeachingCourseBehaviourEntry where kind == 'notification')
        $notifications = TeachingCourseBehaviourEntry::where('teaching_course_id', $course->id)
            ->where('user_id', $auth_user->id)
            ->where('kind', 'notification')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'date' => $notification->date?->format('Y-m-d'),
                    'due_date' => $notification->due_date?->format('Y-m-d'),
                    'done_date' => $notification->done_date?->format('Y-m-d'),
                    'description' => $notification->description,
                    'type' => $notification->type,
                    'is_open' => !$notification->done_date,
                ];
            });

        // Get behaviour entries (TeachingCourseBehaviourEntry where kind == 'behaviour')
        $behaviourEntries = TeachingCourseBehaviourEntry::where('teaching_course_id', $course->id)
            ->where('user_id', $auth_user->id)
            ->where('kind', 'behaviour')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'date' => $entry->date?->format('Y-m-d'),
                    'description' => $entry->description,
                    'type' => $entry->type,
                ];
            });

        // Get course dates (TeachingCourseDate)
        $courseDates = $course->teachingCourseDates()
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($courseDate) {
                return [
                    'id' => $courseDate->id,
                    'date' => $courseDate->date?->format('Y-m-d'),
                    'hours' => $courseDate->hours,
                    'content' => $courseDate->content,
                    'status' => $courseDate->status,
                ];
            });

        $courseData = [
            'id' => $course->id,
            'title' => $course->title,
            'description' => $course->description,
            'teaching_schema_id' => $course->teaching_schema_id,
            'teacher' => $course->user ? ($course->user->short ?: ($course->user->first_name . ' ' . $course->user->last_name)) : '—',
            'teacher_email' => $course->user?->email ?? null,
            'teacher_teaching_notifications' => $course->user?->teaching_notifications ?? [],
            'teacher_teaching_behaviour' => $course->user?->teaching_behaviour ?? [],
            'classes' => $course->classes,
            'students_count' => count($course->students ?? []),
            'stars' => $studentData['stars'] ?? [],
            'sem_1_grade' => $studentData['sem_1_grade'] ?? null,
            'sem_2_grade' => $studentData['sem_2_grade'] ?? null,
            'sem_grade' => $studentData['sem_grade'] ?? null,
            'behaviour_1_grade' => $studentData['behaviour_1_grade'] ?? null,
            'behaviour_2_grade' => $studentData['behaviour_2_grade'] ?? null,
            'behaviour_grade' => $studentData['behaviour_grade'] ?? null,
            'notifications' => $notifications,
            'behaviour_entries' => $behaviourEntries,
            'course_dates' => $courseDates,
        ];

        return response()->json([
            'course' => $courseData,
        ], 200);
    }
}
