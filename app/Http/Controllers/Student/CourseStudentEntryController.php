<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SchoolTool;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentEntry;
use App\Models\User;

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

        if (! $course) {
            abort(404, 'Fach nicht gefunden');
        }

        // Check if student is enrolled in this course
        $isEnrolled = $course->teachingCourseStudents()
            ->where('user_id', $auth_user->id)
            ->exists();

        if (! $isEnrolled) {
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
            ->with('teachingCourseWork:id,type,title,description,is_group_work,group_size,groups')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Format entries for response
        $formattedEntries = $entries->map(function ($entry) use ($auth_user) {
            // Use teachingCourseWork title and description, or entry description
            $title = null;
            $description = null;
            $comment = null;
            $work = null;
            $groupMembers = [];

            if ($entry->teachingCourseWork) {
                $workObj = $entry->teachingCourseWork;
                // If there's an associated work, use its title (or description as fallback) as the title
                $title = $workObj->title ?? $workObj->description ?? null;
                // Use work description (if different from title)
                $description = $workObj->description ?? null;
                if ($description === $title) {
                    $description = null;
                }

                // Find the student's comment and group members from the work groups
                $groups = $workObj->groups ?? [];
                foreach ($groups as $group) {
                    $studentIds = $group['student_ids'] ?? [];
                    if (in_array($auth_user->id, $studentIds) || in_array((string) $auth_user->id, $studentIds)) {
                        // Check individual comment first
                        $comments = $group['comments'] ?? [];
                        foreach ($comments as $commentObj) {
                            if (isset($commentObj['student_id']) && ($commentObj['student_id'] == $auth_user->id)) {
                                $comment = $commentObj['comment'] ?? null;
                                break;
                            }
                        }
                        // Fall back to group comment if no individual comment
                        if (! $comment) {
                            $comment = $group['comment'] ?? null;
                        }

                        // Get group member names from User model
                        // Filter out current user and get other student IDs
                        $otherStudentIds = array_filter($studentIds, function ($studentId) use ($auth_user) {
                            return (string) $studentId !== (string) $auth_user->id;
                        });

                        if (! empty($otherStudentIds)) {
                            // Fetch user names from User model (same school only)
                            $users = User::whereIn('id', $otherStudentIds)
                                ->where('school_id', $auth_user->school_id)
                                ->get(['id', 'first_name', 'last_name'])
                                ->keyBy('id'); // Key by ID for easier lookup

                            // Preserve order from student_ids array
                            foreach ($otherStudentIds as $studentId) {
                                $user = $users->get($studentId);
                                if ($user) {
                                    $firstName = trim($user->first_name ?? '');
                                    $lastName = trim($user->last_name ?? '');
                                    $fullName = trim("$firstName $lastName");
                                    if ($fullName) {
                                        $groupMembers[] = $fullName;
                                    }
                                }
                            }
                        }
                        break;
                    }
                }

                // Build work info object
                $work = [
                    'id' => $workObj->id,
                    'title' => $workObj->title,
                    'description' => $workObj->description,
                    'is_group_work' => $workObj->is_group_work ?? false,
                    'group_size' => $workObj->group_size ?? null,
                    'group_members' => $groupMembers,
                ];
            } elseif ($entry->description) {
                // If there's no work but the entry has a description, use that as comment
                $comment = $entry->description;
            }

            return [
                'id' => $entry->id,
                'title' => $title,
                'description' => $description,
                'comment' => $comment,
                'type' => $entry->type ?? 'info',
                'date' => $entry->date?->format('Y-m-d'),
                'grade' => $entry->grade,
                'status' => $entry->status,
                'created_at' => $entry->created_at,
                'work' => $work,
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
