<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Services\TeachingCourseService;
use App\Services\TeachingCourseWorkEntrySyncService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseWorkController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $courseId = $request->query('course_id');

        if (! $courseId) {
            return response()->json(['data' => []]);
        }

        $course = TeachingCourse::findOrFail($courseId);

        if ($course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $works = $course->teachingCourseWorks()
            ->orderBy('date_for_all_groups', 'desc')
            ->get();

        return response()->json(['data' => $works]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, TeachingCourseWorkEntrySyncService $entrySyncService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = TeachingCourse::findOrFail($request->input('teaching_course_id'));
        $schema = collect($auth_user->teaching_schemas ?? [])->firstWhere('id', $course->teaching_schema_id);
        $allowedTypes = collect($schema['works'] ?? [])
            ->pluck('short_name')
            ->filter()
            ->values()
            ->all();
        $typeRules = ['nullable', 'string', 'max:255'];
        if (!empty($allowedTypes)) {
            $typeRules[] = Rule::in($allowedTypes);
        }

        $validated = $request->validate([
            'teaching_course_id' => 'required|integer|exists:teaching_courses,id',
            'type' => $typeRules,
            'description' => 'nullable|string|max:1024',
            'is_group_work' => 'sometimes|boolean',
            'group_size' => 'nullable|integer|min:2|max:50',
            'is_random_groups' => 'sometimes|boolean',
            'date_for_all_groups' => 'nullable|date',
            'groups' => 'nullable|array',
            'groups.*.student_ids' => 'nullable|array',
            'groups.*.student_ids.*' => 'integer',
            'groups.*.date' => 'nullable|date',
            'groups.*.comment' => 'nullable|string|max:1024',
            'groups.*.grade' => 'nullable|string|max:50',
            'groups.*.grades' => 'nullable|array',
            'groups.*.grades.*.student_id' => 'required|integer',
            'groups.*.grades.*.grade' => 'nullable|string|max:50',
            'groups.*.comments' => 'nullable|array',
            'groups.*.comments.*.student_id' => 'required|integer',
            'groups.*.comments.*.comment' => 'nullable|string|max:1024',
            'groups.*.name' => 'nullable|string|max:255',
            'status' => 'nullable|array',
        ]);

        if ($auth_user->school_id != $course->school_id) {
            abort(409, 'Kein Zugriff auf diese Schule');
        }

        if ($auth_user->schoolyear_id != $course->schoolyear_id) {
            abort(409, 'Kein Zugriff auf dieses Schuljahr');
        }

        $isGroupWork = (bool) ($validated['is_group_work'] ?? false);

        if (! $isGroupWork) {
            $validated['is_group_work'] = false;
            $validated['is_random_groups'] = false;
            $validated['group_size'] = null;
            if (empty($validated['groups'])) {
                $validated['groups'] = $this->buildIndividualGroups($course);
            }
        }

        $validated['groups'] = $this->normalizeWorkGroupsStudentIds(
            $validated['groups'] ?? [],
            (int) $auth_user->school_id
        );

        $work = TeachingCourseWork::create($validated);
        $entrySyncService->syncWork($work);

        return response()->json(['data' => $work], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TeachingCourseWork $course_work)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_work->teachingCourse;

        if (! $course || $course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        return response()->json(['data' => $course_work]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TeachingCourseWork $course_work, TeachingCourseWorkEntrySyncService $entrySyncService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_work->teachingCourse;

        if (! $course || $course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $schema = collect($auth_user->teaching_schemas ?? [])->firstWhere('id', $course->teaching_schema_id);
        $allowedTypes = collect($schema['works'] ?? [])
            ->pluck('short_name')
            ->filter()
            ->values()
            ->all();
        $typeRules = ['nullable', 'string', 'max:255'];
        if (!empty($allowedTypes)) {
            $typeRules[] = Rule::in($allowedTypes);
        }

        $validated = $request->validate([
            'type' => $typeRules,
            'description' => 'nullable|string|max:1024',
            'is_group_work' => 'sometimes|boolean',
            'group_size' => 'nullable|integer|min:2|max:50',
            'is_random_groups' => 'sometimes|boolean',
            'date_for_all_groups' => 'nullable|date',
            'groups' => 'nullable|array',
            'groups.*.student_ids' => 'nullable|array',
            'groups.*.student_ids.*' => 'integer',
            'groups.*.date' => 'nullable|date',
            'groups.*.comment' => 'nullable|string|max:1024',
            'groups.*.grade' => 'nullable|string|max:50',
            'groups.*.grades' => 'nullable|array',
            'groups.*.grades.*.student_id' => 'required|integer',
            'groups.*.grades.*.grade' => 'nullable|string|max:50',
            'groups.*.comments' => 'nullable|array',
            'groups.*.comments.*.student_id' => 'required|integer',
            'groups.*.comments.*.comment' => 'nullable|string|max:1024',
            'groups.*.name' => 'nullable|string|max:255',
            'status' => 'nullable|array',
        ]);

        $isGroupWork = (bool) ($validated['is_group_work'] ?? $course_work->is_group_work);

        if (! $isGroupWork) {
            $validated['is_group_work'] = false;
            $validated['is_random_groups'] = false;
            $validated['group_size'] = null;
            if (empty($validated['groups'])) {
                $validated['groups'] = $this->buildIndividualGroups($course);
            }
        }

        $validated['groups'] = $this->normalizeWorkGroupsStudentIds(
            $validated['groups'] ?? [],
            (int) $auth_user->school_id
        );

        $course_work->update($validated);
        $entrySyncService->syncWork($course_work->fresh());

        return response()->json(['data' => $course_work]);
    }

    private function buildIndividualGroups(TeachingCourse $course): array
    {
        $service = app(TeachingCourseService::class);
        $entries = $service->normalizeStudentEntries($course->students);
        $studentIds = [];
        foreach ($entries as $entry) {
            $resolvedId = $service->resolveStudentIdFromNumeric((int) ($entry['id'] ?? 0), (int) $course->school_id);
            if ($resolvedId) {
                $studentIds[] = $resolvedId;
            }
        }
        $studentIds = array_values(array_unique($studentIds));

        return array_values(array_map(function ($id) {
            return [
                'student_ids' => [(int) $id],
                'comment' => null,
                'grade' => null,
            ];
        }, $studentIds));
    }

    private function normalizeWorkGroupsStudentIds(array $groups, int $schoolId): array
    {
        $service = app(TeachingCourseService::class);
        $normalized = [];

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $studentIds = [];
            foreach (($group['student_ids'] ?? []) as $studentId) {
                $resolvedId = $service->resolveStudentIdFromNumeric((int) $studentId, $schoolId);
                if ($resolvedId) {
                    $studentIds[] = $resolvedId;
                }
            }
            $studentIds = array_values(array_unique($studentIds));

            $grades = [];
            foreach (($group['grades'] ?? []) as $gradeItem) {
                if (!is_array($gradeItem)) {
                    continue;
                }
                $resolvedId = $service->resolveStudentIdFromNumeric((int) ($gradeItem['student_id'] ?? 0), $schoolId);
                if (! $resolvedId) {
                    continue;
                }
                $grades[] = [
                    'student_id' => $resolvedId,
                    'grade' => $gradeItem['grade'] ?? '',
                ];
            }

            $comments = [];
            foreach (($group['comments'] ?? []) as $commentItem) {
                if (!is_array($commentItem)) {
                    continue;
                }
                $resolvedId = $service->resolveStudentIdFromNumeric((int) ($commentItem['student_id'] ?? 0), $schoolId);
                if (! $resolvedId) {
                    continue;
                }
                $comments[] = [
                    'student_id' => $resolvedId,
                    'comment' => $commentItem['comment'] ?? '',
                ];
            }

            $normalized[] = array_merge($group, [
                'student_ids' => $studentIds,
                'grades' => $grades,
                'comments' => $comments,
            ]);
        }

        return $normalized;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TeachingCourseWork $course_work, TeachingCourseWorkEntrySyncService $entrySyncService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_work->teachingCourse;

        if (! $course || $course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $entrySyncService->deleteForWork($course_work);
        $course_work->delete();

        return response()->json(null, 204);
    }
}
