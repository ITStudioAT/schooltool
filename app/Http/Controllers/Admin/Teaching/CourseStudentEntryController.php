<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentEntry;
use App\Models\User;
use App\Services\TeachingCourseStudentEntryService;
use App\Services\TeachingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseStudentEntryController extends Controller
{
    public function index(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'course_id' => 'required|integer|exists:teaching_courses,id',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $course = TeachingCourse::findOrFail($validated['course_id']);
        if ($course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $query = TeachingCourseStudentEntry::where('teaching_course_id', $course->id);

        if (! empty($validated['user_id'])) {
            $student = User::findOrFail($validated['user_id']);
            if ($student->school_id !== $auth_user->school_id) {
                abort(403, 'Sie haben keine Berechtigung');
            }
            $query->where('user_id', $student->id);
        }

        $entries = $query
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $defaultsByType = $this->defaultGradesByType($course, $auth_user);
        $entries->each(function (TeachingCourseStudentEntry $entry) use ($defaultsByType) {
            $this->attachEffectiveGrade($entry, $defaultsByType);
        });

        return response()->json(['data' => $entries]);
    }

    public function store(Request $request, TeachingCourseStudentEntryService $entryService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = TeachingCourse::findOrFail($request->input('teaching_course_id'));
        $allowedTypes = $entryService->allowedTypesForSchema($auth_user, $course->teaching_schema_id, $course->schoolyear_id);

        $validated = $request->validate([
            'teaching_course_id' => 'required|integer|exists:teaching_courses,id',
            'user_id' => 'required|integer|exists:users,id',
            'type' => ['required', 'string', 'max:255', Rule::in($allowedTypes)],
            'grade' => 'nullable|string|max:50',
            'date' => 'nullable|date',
            'description' => 'nullable|string|max:1024',
            'teaching_course_work_id' => 'nullable|integer|exists:teaching_course_works,id',
            'status' => 'nullable|array',
        ]);

        $course = TeachingCourse::findOrFail($validated['teaching_course_id']);
        if ($course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $student = User::findOrFail($validated['user_id']);
        if ($student->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $entry = TeachingCourseStudentEntry::create($validated);
        $this->attachEffectiveGrade($entry, $this->defaultGradesByType($course, $auth_user));

        return response()->json(['data' => $entry], 201);
    }

    public function update(Request $request, TeachingCourseStudentEntry $course_student_entry, TeachingCourseStudentEntryService $entryService)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (($course_student_entry->source ?? 'manual') === 'course_work') {
            abort(409, 'Dieser Eintrag wird aus einer Arbeit abgeleitet und kann hier nicht direkt geändert werden.');
        }

        $course = $course_student_entry->teachingCourse;
        if (! $course || $course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $allowedTypes = $entryService->allowedTypesForSchema($auth_user, $course->teaching_schema_id, $course->schoolyear_id);

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:255', Rule::in($allowedTypes)],
            'grade' => 'nullable|string|max:50',
            'date' => 'nullable|date',
            'description' => 'nullable|string|max:1024',
            'teaching_course_work_id' => 'nullable|integer|exists:teaching_course_works,id',
            'status' => 'nullable|array',
        ]);

        $student = $course_student_entry->user;
        if (! $student || $student->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course_student_entry->update($validated);
        $this->attachEffectiveGrade($course_student_entry, $this->defaultGradesByType($course, $auth_user));

        return response()->json(['data' => $course_student_entry]);
    }

    public function destroy(TeachingCourseStudentEntry $course_student_entry)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if (($course_student_entry->source ?? 'manual') === 'course_work') {
            abort(409, 'Dieser Eintrag wird aus einer Arbeit abgeleitet und kann hier nicht direkt gelöscht werden.');
        }

        $course = $course_student_entry->teachingCourse;
        if (! $course || $course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $student = $course_student_entry->user;
        if (! $student || $student->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course_student_entry->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, string>
     */
    private function defaultGradesByType(TeachingCourse $course, User $authUser): array
    {
        if (! $course->teaching_schema_id) {
            return [];
        }

        $schemaOwner = $course->user ?: $authUser;
        $schema = (new TeachingService)->schemaById($schemaOwner, (string) $course->teaching_schema_id, $course->schoolyear_id);
        if (! is_array($schema)) {
            return [];
        }

        $defaults = [];
        foreach ((array) ($schema['works'] ?? []) as $work) {
            if (! is_array($work)) {
                continue;
            }

            $type = trim((string) ($work['short_name'] ?? ''));
            if ($type === '') {
                continue;
            }

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
            if (! $hasGrade) {
                continue;
            }

            $defaults[$type] = $defaultGrade;
        }

        return $defaults;
    }

    private function attachEffectiveGrade(TeachingCourseStudentEntry $entry, array $defaultsByType): void
    {
        $rawGrade = trim((string) ($entry->grade ?? ''));
        if ($rawGrade !== '') {
            $entry->setAttribute('effective_grade', $rawGrade);

            return;
        }

        $type = trim((string) ($entry->type ?? ''));
        $entry->setAttribute('effective_grade', $type !== '' ? ($defaultsByType[$type] ?? null) : null);
    }
}
