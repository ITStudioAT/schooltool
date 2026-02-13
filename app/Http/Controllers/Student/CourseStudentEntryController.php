<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SchoolTool;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentEntry;
use Illuminate\Http\Request;

class CourseStudentEntryController extends Controller
{
    public function index($courseId)
    {
        if (! $auth_user = $this->userHasRole(['student'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        // Get active schoolyear from SchoolTool
        $schoolTool = SchoolTool::where('school_id', $auth_user->school_id)->first();
        $active_schoolyear_id = $schoolTool?->active_schoolyear_id ?? $auth_user->schoolyear_id;

        // Get the course
        $course = TeachingCourse::with('user:id,teaching_schemas')
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

        // Get teaching schema for proper type labels
        $teachingSchemas = $course->user?->teaching_schemas ?? $auth_user->teaching_schemas ?? [];
        $schemaForCourse = null;
        if ($course->teaching_schema_id) {
            foreach ($teachingSchemas as $schema) {
                if (isset($schema['id']) && (string) $schema['id'] === (string) $course->teaching_schema_id) {
                    $schemaForCourse = $schema;
                    break;
                }
            }
        }

        // Get entries from TeachingCourseStudentEntry model
        $entries = TeachingCourseStudentEntry::where('teaching_course_id', $course->id)
            ->where('user_id', $auth_user->id)
            ->with('teachingCourseWork:id,type,description')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Format entries for response
        $formattedEntries = $entries->map(function ($entry) {
            // Use teachingCourseWork description as title, or entry description, or generate from type
            $title = 'Eintrag';
            $description = null;

            if ($entry->teachingCourseWork) {
                // If there's an associated work, use its description as the title
                $title = $entry->teachingCourseWork->description ?? ($entry->teachingCourseWork->type ?? 'Arbeit');
                // And use the entry's own description as additional details
                $description = $entry->description;
            } elseif ($entry->description) {
                // If there's no work but the entry has a description, use that as title
                $title = $entry->description;
            } elseif ($entry->type) {
                // Otherwise, generate title from type
                $title = ucfirst($entry->type);
            }

            return [
                'id' => $entry->id,
                'title' => $title,
                'description' => $description,
                'type' => $entry->type ?? 'info',
                'date' => $entry->date?->format('Y-m-d'),
                'grade' => $entry->grade,
                'status' => $entry->status,
                'created_at' => $entry->created_at,
            ];
        });

        // Build type labels map from teaching schema
        $typeLabels = [];
        if ($schemaForCourse && isset($schemaForCourse['works'])) {
            foreach ($schemaForCourse['works'] as $work) {
                if (isset($work['short_name'])) {
                    $shortName = (string) $work['short_name'];
                    $name = trim((string) ($work['name'] ?? ''));
                    $typeLabels[$shortName] = $name !== '' ? $name : $shortName;
                }
            }
        }

        return response()->json([
            'entries' => $formattedEntries,
            'type_labels' => $typeLabels,
        ], 200);
    }
}
