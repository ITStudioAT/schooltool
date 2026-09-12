<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\TeachingCourseStudentEntryService;
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Services\TeachingCourseWorkService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CourseWorkController extends Controller
{
    public function index(Request $request, TeachingCourseWorkEntrySyncService $entrySyncService)
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
            ->with('teachingCourseWorkGroupStudents')
            ->orderBy('date_for_all_groups', 'desc')
            ->get();

        return response()->json(['data' => $works->map(fn (TeachingCourseWork $work): array => $entrySyncService->serializeWork($work))->values()]);
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

        $allowedTypes = $entryService->allowedGradingTypesForCourse(
            $this->teachingCourseActor($auth_user, $course),
            $course
        );
        $typeRules = ['nullable', 'string', 'max:255'];
        if (! empty($allowedTypes)) {
            $typeRules[] = Rule::in($allowedTypes);
        }

        $gradeRules = $entryService->gradeRulesForCourse($this->teachingCourseActor($auth_user, $course), $course, $request->input('type'));
        $maximumPlus = $this->validatedMaximumPlus($request, $course, $this->teachingCourseActor($auth_user, $course), $entryService);
        $this->appendMaximumPlusGradeRule($gradeRules, $maximumPlus);
        $validated = $request->validate($this->workValidationRules($typeRules, true, $course, $gradeRules));
        $validated['maximum_plus'] = $maximumPlus;
        $validated['finish_until_date'] ??= $validated['date_for_all_groups'] ?? null;

        $validated = $workService->prepareWorkData($validated, $course, (int) $auth_user->school_id);

        $work = TeachingCourseWork::create($validated);
        $entrySyncService->syncWork($work);

        return response()->json(['data' => $entrySyncService->serializeWork($work->fresh('teachingCourseWorkGroupStudents'))], 201);
    }

    public function show(TeachingCourseWork $course_work, TeachingCourseWorkEntrySyncService $entrySyncService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_work->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        return response()->json(['data' => $entrySyncService->serializeWork($course_work->load('teachingCourseWorkGroupStudents'))]);
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

        $allowedTypes = $entryService->allowedGradingTypesForCourse(
            $this->teachingCourseActor($auth_user, $course),
            $course
        );
        $typeRules = ['nullable', 'string', 'max:255'];
        if (! empty($allowedTypes)) {
            $typeRules[] = Rule::in($allowedTypes);
        }

        $gradeRules = $entryService->gradeRulesForCourse($this->teachingCourseActor($auth_user, $course), $course, $request->input('type', $course_work->type));
        $maximumPlus = $this->validatedMaximumPlus($request, $course, $this->teachingCourseActor($auth_user, $course), $entryService, $course_work);
        $this->appendMaximumPlusGradeRule($gradeRules, $maximumPlus);
        $validated = $request->validate($this->workValidationRules($typeRules, false, $course, $gradeRules));
        $validated['maximum_plus'] = $maximumPlus;

        if (! array_key_exists('groups', $validated)) {
            Validator::make(['groups' => $course_work->groups], [
                'groups.*.grade' => $gradeRules,
                'groups.*.grades.*.grade' => $gradeRules,
            ])->validate();
            $validated['groups'] = $course_work->groups;
        }

        $validated = $workService->prepareWorkData($validated, $course, (int) $auth_user->school_id, $course_work->is_group_work);

        $course_work->update($validated);
        $entrySyncService->syncWork($course_work->fresh());

        return response()->json(['data' => $entrySyncService->serializeWork($course_work->fresh('teachingCourseWorkGroupStudents'))]);
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
    private function workValidationRules(array $typeRules, bool $isStore, TeachingCourse $course, array $gradeRules): array
    {
        $maximumGroupSize = $course->teachingCourseStudents()
            ->whereNull('canceled_at')
            ->count();

        $rules = [
            'type' => $typeRules,
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1024',
            'is_group_work' => 'sometimes|boolean',
            'group_size' => "nullable|integer|min:2|max:{$maximumGroupSize}",
            'is_random_groups' => 'sometimes|boolean',
            'date_for_all_groups' => 'nullable|date',
            'finish_until_date' => 'nullable|date',
            'groups' => 'nullable|array',
            'groups.*.student_ids' => 'nullable|array',
            'groups.*.student_ids.*' => 'integer',
            'groups.*.date' => 'nullable|date',
            'groups.*.comment' => 'nullable|string|max:1024',
            'groups.*.grade' => $gradeRules,
            'groups.*.grades' => 'nullable|array',
            'groups.*.grades.*.student_id' => 'required|integer',
            'groups.*.grades.*.grade' => $gradeRules,
            'groups.*.points' => 'nullable|array',
            'groups.*.points.*.student_id' => 'required|integer',
            'groups.*.points.*.points' => 'nullable|numeric',
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

    private function validatedMaximumPlus(Request $request, TeachingCourse $course, User $actor, TeachingCourseStudentEntryService $entryService, ?TeachingCourseWork $work = null): ?int
    {
        $definition = $entryService->entryDefinitionsForCourse($actor, $course)
            ->firstWhere('short_name', $request->input('type', $work?->type));
        if ($definition?->category !== 'Benotung' || ! $definition->has_properties
            || $definition->properties_mode !== 'plus' || ! $definition->allows_maximum_plus) {
            return null;
        }

        $validated = Validator::make([
            'maximum_plus' => $request->input('maximum_plus', $work?->maximum_plus),
        ], [
            'maximum_plus' => ['required', 'integer:strict', 'min:1', 'max:4294967295'],
        ], [
            'maximum_plus.required' => 'Bitte die maximal erreichbare Anzahl an Plus angeben.',
            'maximum_plus.integer' => 'Die maximale Anzahl an Plus muss eine ganze Zahl sein.',
            'maximum_plus.min' => 'Die maximale Anzahl an Plus muss mindestens 1 sein.',
        ])->validate();

        return $validated['maximum_plus'];
    }

    private function appendMaximumPlusGradeRule(array &$gradeRules, ?int $maximumPlus): void
    {
        if ($maximumPlus === null) {
            return;
        }

        $gradeRules[] = function (string $attribute, mixed $value, Closure $fail) use ($maximumPlus): void {
            if (is_string($value) && preg_match('/^\++$/', trim($value)) === 1 && strlen(trim($value)) > $maximumPlus) {
                $fail('Die Anzahl an Plus darf die maximal erreichbare Anzahl nicht überschreiten.');
            }
        };
    }
}
