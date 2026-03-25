<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Services\TeachingCourseStudentEntryService;
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Services\TeachingCourseWorkService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseWorkController extends Controller
{
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
        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $works = $course->teachingCourseWorks()
            ->orderBy('date_for_all_groups', 'desc')
            ->get();

        return response()->json(['data' => $works]);
    }

    public function store(
        Request $request,
        TeachingCourseWorkService $workService,
        TeachingCourseStudentEntryService $entryService,
        TeachingCourseWorkEntrySyncService $entrySyncService
    ) {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = TeachingCourse::findOrFail($request->input('teaching_course_id'));
        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $allowedTypes = $entryService->allowedTypesForSchema(
            $this->teachingCourseActor($auth_user, $course),
            $course->teaching_schema_id,
            $course->schoolyear_id
        );
        $typeRules = ['nullable', 'string', 'max:255'];
        if (! empty($allowedTypes)) {
            $typeRules[] = Rule::in($allowedTypes);
        }

        $validated = $request->validate($this->workValidationRules($typeRules, true));

        $validated = $workService->prepareWorkData($validated, $course, (int) $auth_user->school_id);

        $work = TeachingCourseWork::create($validated);
        $entrySyncService->syncWork($work);

        return response()->json(['data' => $work], 201);
    }

    public function show(TeachingCourseWork $course_work)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_work->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        return response()->json(['data' => $course_work]);
    }

    public function update(
        Request $request,
        TeachingCourseWork $course_work,
        TeachingCourseWorkService $workService,
        TeachingCourseStudentEntryService $entryService,
        TeachingCourseWorkEntrySyncService $entrySyncService
    ) {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_work->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $allowedTypes = $entryService->allowedTypesForSchema(
            $this->teachingCourseActor($auth_user, $course),
            $course->teaching_schema_id,
            $course->schoolyear_id
        );
        $typeRules = ['nullable', 'string', 'max:255'];
        if (! empty($allowedTypes)) {
            $typeRules[] = Rule::in($allowedTypes);
        }

        $validated = $request->validate($this->workValidationRules($typeRules, false));

        $validated = $workService->prepareWorkData($validated, $course, (int) $auth_user->school_id, $course_work->is_group_work);

        $course_work->update($validated);
        $entrySyncService->syncWork($course_work->fresh());

        return response()->json(['data' => $course_work]);
    }

    public function destroy(TeachingCourseWork $course_work, TeachingCourseWorkEntrySyncService $entrySyncService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_work->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $entrySyncService->deleteForWork($course_work);
        $course_work->delete();

        return response()->json(null, 204);
    }

    /**
     * Return the shared validation rules for store/update of a course work.
     */
    private function workValidationRules(array $typeRules, bool $isStore): array
    {
        $rules = [
            'type' => $typeRules,
            'title' => 'nullable|string|max:255',
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
        ];

        if ($isStore) {
            $rules['teaching_course_id'] = 'required|integer|exists:teaching_courses,id';
        }

        return $rules;
    }
}
