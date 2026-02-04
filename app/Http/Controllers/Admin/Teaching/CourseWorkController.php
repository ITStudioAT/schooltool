<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Services\TeachingCourseService;
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
    public function store(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $allowedTypes = collect($auth_user->teaching_works ?? [])
            ->pluck('short_name')
            ->filter()
            ->values()
            ->all();

        $validated = $request->validate([
            'teaching_course_id' => 'required|integer|exists:teaching_courses,id',
            'type' => ['nullable', 'string', 'max:255', Rule::in($allowedTypes)],
            'description' => 'nullable|string|max:1024',
            'is_group_work' => 'sometimes|boolean',
            'group_size' => 'nullable|integer|min:2|max:50',
            'is_random_groups' => 'sometimes|boolean',
            'date_for_all_groups' => 'nullable|date',
            'groups' => 'nullable|array',
            'groups.*.student_ids' => 'nullable|array',
            'groups.*.student_ids.*' => 'integer|exists:users,id',
            'groups.*.comment' => 'nullable|string|max:1024',
            'groups.*.grade' => 'nullable|string|max:50',
            'groups.*.grades' => 'nullable|array',
            'groups.*.grades.*.student_id' => 'required|integer|exists:users,id',
            'groups.*.grades.*.grade' => 'nullable|string|max:50',
            'groups.*.comments' => 'nullable|array',
            'groups.*.comments.*.student_id' => 'required|integer|exists:users,id',
            'groups.*.comments.*.comment' => 'nullable|string|max:1024',
            'groups.*.name' => 'nullable|string|max:255',
            'status' => 'nullable|array',
        ]);

        $course = TeachingCourse::findOrFail($validated['teaching_course_id']);

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
            $validated['groups'] = $this->buildIndividualGroups($course);
        }

        $work = TeachingCourseWork::create($validated);

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
    public function update(Request $request, TeachingCourseWork $course_work)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_work->teachingCourse;

        if (! $course || $course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $allowedTypes = collect($auth_user->teaching_works ?? [])
            ->pluck('short_name')
            ->filter()
            ->values()
            ->all();

        $validated = $request->validate([
            'type' => ['nullable', 'string', 'max:255', Rule::in($allowedTypes)],
            'description' => 'nullable|string|max:1024',
            'is_group_work' => 'sometimes|boolean',
            'group_size' => 'nullable|integer|min:2|max:50',
            'is_random_groups' => 'sometimes|boolean',
            'date_for_all_groups' => 'nullable|date',
            'groups' => 'nullable|array',
            'groups.*.student_ids' => 'nullable|array',
            'groups.*.student_ids.*' => 'integer|exists:users,id',
            'groups.*.comment' => 'nullable|string|max:1024',
            'groups.*.grade' => 'nullable|string|max:50',
            'groups.*.grades' => 'nullable|array',
            'groups.*.grades.*.student_id' => 'required|integer|exists:users,id',
            'groups.*.grades.*.grade' => 'nullable|string|max:50',
            'groups.*.comments' => 'nullable|array',
            'groups.*.comments.*.student_id' => 'required|integer|exists:users,id',
            'groups.*.comments.*.comment' => 'nullable|string|max:1024',
            'groups.*.name' => 'nullable|string|max:255',
            'status' => 'nullable|array',
        ]);

        $isGroupWork = (bool) ($validated['is_group_work'] ?? $course_work->is_group_work);

        if (! $isGroupWork) {
            $validated['is_group_work'] = false;
            $validated['is_random_groups'] = false;
            $validated['group_size'] = null;
            $validated['groups'] = $this->buildIndividualGroups($course);
        }

        $course_work->update($validated);

        return response()->json(['data' => $course_work]);
    }

    private function buildIndividualGroups(TeachingCourse $course): array
    {
        $service = app(TeachingCourseService::class);
        $studentIds = $service->normalizeStudentIds($course->students);

        return array_values(array_map(function ($id) {
            return [
                'student_ids' => [(int) $id],
                'comment' => null,
                'grade' => null,
            ];
        }, $studentIds));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TeachingCourseWork $course_work)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_work->teachingCourse;

        if (! $course || $course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course_work->delete();

        return response()->json(null, 204);
    }
}
