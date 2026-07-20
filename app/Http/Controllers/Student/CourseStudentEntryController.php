<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SchoolTool;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentCategoryEvaluation;
use App\Models\TeachingCourseStudentEntry;
use App\Models\User;
use App\Services\ParentStudentAccessService;
use App\Services\TeachingService;

class CourseStudentEntryController extends Controller
{
    public function index($courseId, ParentStudentAccessService $parentAccess)
    {
        if (! $auth_user = $parentAccess->currentStudent()) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        // Get active schoolyear from SchoolTool
        $schoolTool = SchoolTool::where('school_id', $auth_user->school_id)->first();
        $active_schoolyear_id = $schoolTool?->active_schoolyear_id ?? $auth_user->schoolyear_id;

        // Get the course
        $course = TeachingCourse::with('user:id,school_id,schoolyear_id')
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
            ->whereNull('canceled_at')
            ->exists();

        if (! $isEnrolled) {
            abort(403, 'Sie sind nicht in diesem Fach eingeschrieben');
        }

        // Get teaching schema for proper type labels
        $teachingService = new TeachingService;
        $schemaOwner = $course->user ?: $auth_user;
        $teachingSchemas = $teachingService->schemasForUser($schemaOwner, $course->schoolyear_id)->all();
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
        $defaultGradesByType = [];
        if ($schemaForCourse && isset($schemaForCourse['works'])) {
            foreach ($schemaForCourse['works'] as $work) {
                if (isset($work['short_name'])) {
                    $shortName = (string) $work['short_name'];
                    $name = trim((string) ($work['name'] ?? ''));
                    $typeLabels[$shortName] = $name !== '' ? $name : $shortName;

                    $defaultGrade = trim((string) ($work['default_grade'] ?? ''));
                    if ($defaultGrade === '') {
                        continue;
                    }
                    $grades = is_array($work['grades'] ?? null) ? $work['grades'] : [];
                    $hasGrade = collect($grades)->contains(function ($grade) use ($defaultGrade) {
                        if (! is_array($grade)) {
                            return false;
                        }

                        return strtoupper(trim((string) ($grade['grade'] ?? ''))) === strtoupper($defaultGrade);
                    });
                    if ($hasGrade) {
                        $defaultGradesByType[$shortName] = $defaultGrade;
                    }
                }
            }
        }

        $requiredTypeSet = $this->buildRequireAllCategoryTypeSet($schemaForCourse);
        $gradingCategories = collect(is_array($schemaForCourse['grading']['categories'] ?? null) ? $schemaForCourse['grading']['categories'] : [])
            ->map(function ($category): ?array {
                if (! is_array($category)) {
                    return null;
                }

                $name = trim((string) ($category['name'] ?? ''));
                if ($name === '') {
                    return null;
                }

                $works = collect(is_array($category['works'] ?? null) ? $category['works'] : [])
                    ->map(function ($workItem): ?string {
                        $shortName = is_string($workItem)
                            ? $workItem
                            : (is_array($workItem) ? ($workItem['short_name'] ?? null) : null);

                        $normalized = trim((string) $shortName);

                        return $normalized !== '' ? $normalized : null;
                    })
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'name' => $name,
                    'category_evaluation_enabled' => (bool) ($category['category_evaluation_enabled'] ?? false),
                    'works' => $works,
                ];
            })
            ->filter()
            ->values();

        $categoryEvaluationValues = collect(is_array($schemaForCourse['grading']['category_evaluation_values'] ?? null) ? $schemaForCourse['grading']['category_evaluation_values'] : [])
            ->map(function ($value): ?array {
                if (! is_array($value)) {
                    return null;
                }

                $label = trim((string) ($value['value'] ?? ''));
                if ($label === '') {
                    return null;
                }

                return [
                    'value' => $label,
                    'color' => trim((string) ($value['color'] ?? '')),
                ];
            })
            ->filter()
            ->values();

        $categoryEvaluationDefaultValue = trim((string) ($schemaForCourse['grading']['default_category_evaluation_value'] ?? ''));

        $formattedEntries = $formattedEntries->map(function (array $entry) use ($defaultGradesByType, $requiredTypeSet) {
            $raw = trim((string) ($entry['grade'] ?? ''));
            if ($raw !== '') {
                $entry['grade'] = $raw;
            } else {
                $type = trim((string) ($entry['type'] ?? ''));
                $entry['grade'] = $type !== '' ? ($defaultGradesByType[$type] ?? null) : null;
            }

            $typeKey = strtoupper(trim((string) ($entry['type'] ?? '')));
            $entry['is_required_entry'] = $typeKey !== '' && isset($requiredTypeSet[$typeKey]);

            return $entry;
        });

        $categoryEvaluations = TeachingCourseStudentCategoryEvaluation::query()
            ->where('teaching_course_id', $course->id)
            ->where('user_id', $auth_user->id)
            ->orderBy('semester')
            ->orderBy('category_name')
            ->get()
            ->map(function (TeachingCourseStudentCategoryEvaluation $evaluation): array {
                return [
                    'semester' => (int) $evaluation->semester,
                    'category_name' => $evaluation->category_name,
                    'value' => $evaluation->value,
                ];
            })
            ->values();

        return response()->json([
            'entries' => $formattedEntries,
            'category_evaluations' => $categoryEvaluations,
            'grading_categories' => $gradingCategories,
            'category_evaluation_values' => $categoryEvaluationValues,
            'category_evaluation_default_value' => $categoryEvaluationDefaultValue !== '' ? $categoryEvaluationDefaultValue : null,
            'type_labels' => $typeLabels,
        ], 200);
    }

    /**
     * @param  array<string, mixed>|null  $schema
     * @return array<string, bool>
     */
    private function buildRequireAllCategoryTypeSet(?array $schema): array
    {
        if (! is_array($schema)) {
            return [];
        }

        $grading = is_array($schema['grading'] ?? null) ? $schema['grading'] : [];
        $categories = is_array($grading['categories'] ?? null) ? $grading['categories'] : [];
        $requiredTypes = [];

        foreach ($categories as $category) {
            if (! is_array($category)) {
                continue;
            }

            $hasRequireAllKey = array_key_exists('require_all_entries', $category);
            $requireAllEntries = $hasRequireAllKey
                ? (bool) $category['require_all_entries']
                : (bool) ($category['all_entries_needed'] ?? $category['all_ewntries_needed'] ?? false);

            if (! $requireAllEntries) {
                continue;
            }

            $works = is_array($category['works'] ?? null) ? $category['works'] : [];
            foreach ($works as $workItem) {
                $shortName = is_string($workItem)
                    ? $workItem
                    : (is_array($workItem) ? ($workItem['short_name'] ?? null) : null);

                $typeKey = strtoupper(trim((string) $shortName));
                if ($typeKey === '') {
                    continue;
                }

                $requiredTypes[$typeKey] = true;
            }
        }

        return $requiredTypes;
    }
}
