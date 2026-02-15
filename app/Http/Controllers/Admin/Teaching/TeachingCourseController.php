<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\CourseResource;
use App\Http\Resources\Admin\Teaching\StudentResource;
use App\Models\Import116;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\User;
use App\Services\TeachingCourseService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class TeachingCourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $coursesQuery = TeachingCourse::with([
            'teachingCourseDates' => fn ($q) => $q->orderBy('date')->orderByRaw('JSON_EXTRACT(hours, "$[0]")'),
            'teachingCourseStudents',
            'teachingCourseStudentsWithTrashed',
        ])
            ->where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id);

        if ($auth_user->hasRole('teacher') && ! $auth_user->hasAnyRole(['admin', 'teaching_admin'])) {
            $coursesQuery->where('user_id', $auth_user->id);
        }

        $courses = $coursesQuery
            ->orderBy('title')
            ->get();

        $allCourseStudents = $courses->flatMap(
            fn (TeachingCourse $course) => $course->teachingCourseStudentsWithTrashed
        );

        $userIds = $allCourseStudents
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();
        $importIds = $allCourseStudents
            ->pluck('import116_id')
            ->filter()
            ->unique()
            ->values();

        $studentsById = $userIds->isEmpty()
            ? collect()
            : User::whereIn('id', $userIds)->get()->keyBy('id');
        $importsById = $importIds->isEmpty()
            ? collect()
            : Import116::where('school_id', $auth_user->school_id)->whereIn('id', $importIds)->get()->keyBy('id');

        $courses->each(function (TeachingCourse $course) use ($studentsById, $importsById, $request) {
            $activeStudents = [];
            foreach ($course->teachingCourseStudents as $courseStudent) {
                $payload = $this->serializeCourseStudent($courseStudent, $studentsById, $importsById, $request);
                if ($payload) {
                    $activeStudents[] = $payload;
                }
            }

            $deletedStudents = [];
            foreach ($course->teachingCourseStudentsWithTrashed as $courseStudent) {
                if (! $courseStudent->trashed()) {
                    continue;
                }

                $payload = $this->serializeCourseStudent($courseStudent, $studentsById, $importsById, $request);
                if ($payload) {
                    $deletedStudents[] = $payload;
                }
            }

            $course->setAttribute('students', $activeStudents);
            $course->setAttribute('students_deleted', $deletedStudents);
        });

        $classes = Import116::where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id)
            ->distinct()
            ->orderBy('class')
            ->pluck('class');

        return response()->json([
            'data' => CourseResource::collection($courses),
            'classes' => $classes,
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, TeachingCourseService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $classes = Import116::where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id)
            ->distinct()
            ->orderBy('class')
            ->pluck('class');

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'classes' => 'required|array|min:1',
            'classes.*' => ['required', 'string', Rule::in($classes)],
            'students' => 'nullable|array',
            'students.*.stars' => 'nullable|array',
            'students.*.stars.*.id' => 'nullable|string|max:64',
            'students.*.stars.*.value' => 'nullable|integer|in:1',
            'students.*.stars.*.comment' => 'nullable|string|max:1024',
            'students.*.stars.*.date' => 'nullable|date',
            'students_deleted' => 'nullable|array',
            'teaching_schema_id' => 'nullable|string|max:36',
        ]);

        $sortedClasses = $validated['classes'];
        sort($sortedClasses);

        // Prefer students_info (has user_id/import116_id) over students (just IDs)
        $studentsInfoPayload = $request->input('students_info', []);
        if (is_array($studentsInfoPayload) && ! empty($studentsInfoPayload)) {
            $studentsPayload = $studentsInfoPayload;
        } else {
            $studentsPayload = $validated['students'] ?? [];
        }

        $studentsDeletedInfoPayload = $request->input('students_deleted_info', []);
        if (is_array($studentsDeletedInfoPayload) && ! empty($studentsDeletedInfoPayload)) {
            $studentsDeletedPayload = $studentsDeletedInfoPayload;
        } else {
            $studentsDeletedPayload = $validated['students_deleted'] ?? [];
        }

        $course = TeachingCourse::create([
            'school_id' => $auth_user->school_id,
            'schoolyear_id' => $auth_user->schoolyear_id,
            'user_id' => $auth_user->id,
            'title' => $validated['title'],
            'classes' => $sortedClasses,
            'teaching_schema_id' => $validated['teaching_schema_id'] ?? null,
        ]);

        $service->syncCourseStudents($course, $studentsPayload, $studentsDeletedPayload);

        return response()->json(new CourseResource($course), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TeachingCourse $course)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TeachingCourse $course, TeachingCourseService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $classes = Import116::where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id)
            ->distinct()
            ->orderBy('class')
            ->pluck('class');

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:4096',
            'classes' => 'required|array|min:1',
            'classes.*' => ['required', 'string', Rule::in($classes)],
            'students' => 'nullable|array',
            'students.*.stars' => 'nullable|array',
            'students.*.stars.*.id' => 'nullable|string|max:64',
            'students.*.stars.*.value' => 'nullable|integer|in:1',
            'students.*.stars.*.comment' => 'nullable|string|max:1024',
            'students.*.stars.*.date' => 'nullable|date',
            'students_deleted' => 'nullable|array',
            'teaching_schema_id' => 'nullable|string|max:36',
        ]);

        $sortedClasses = $validated['classes'];
        sort($sortedClasses);

        // Prefer students_info (has user_id/import116_id) over students (just IDs)
        $studentsInfoPayload = $request->input('students_info', []);
        if (is_array($studentsInfoPayload) && ! empty($studentsInfoPayload)) {
            $studentsPayload = $studentsInfoPayload;
        } else {
            $studentsPayload = $validated['students'] ?? [];
        }

        $studentsDeletedInfoPayload = $request->input('students_deleted_info', []);
        if (is_array($studentsDeletedInfoPayload) && ! empty($studentsDeletedInfoPayload)) {
            $studentsDeletedPayload = $studentsDeletedInfoPayload;
        } else {
            $studentsDeletedPayload = $validated['students_deleted'] ?? [];
        }

        $course->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'classes' => $sortedClasses,
            'teaching_schema_id' => $validated['teaching_schema_id'] ?? $course->teaching_schema_id,
        ]);

        $service->syncCourseStudents($course, $studentsPayload, $studentsDeletedPayload);

        return response()->json(new CourseResource($course));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TeachingCourse $course)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        if ($course->hasDependencies()) {
            abort(409, 'Der Kurs hat noch Abhängigkeiten und kann nicht gelöscht werden');
        }

        $course->delete();

        return response()->json(null, 204);
    }

    private function serializeCourseStudent(
        TeachingCourseStudent $courseStudent,
        Collection $studentsById,
        Collection $importsById,
        Request $request
    ): ?array {
        if ($courseStudent->user_id) {
            $student = $studentsById->get((int) $courseStudent->user_id);
            if (! $student) {
                return null;
            }

            $payload = (new StudentResource($student))->toArray($request);
        } else {
            $import = $courseStudent->import116_id ? $importsById->get((int) $courseStudent->import116_id) : null;
            if (! $import) {
                return null;
            }

            $payload = [
                'id' => $import->id,
                'first_name' => $import->first_name,
                'last_name' => $import->last_name,
                'email' => $import->email,
                'schoolclass' => $import->class,
                'class' => $import->class,
            ];
        }

        $resolvedId = $courseStudent->studentId();
        if (! $resolvedId) {
            return null;
        }

        $payload['id'] = $resolvedId;
        $payload['user_id'] = $courseStudent->user_id;
        $payload['import116_id'] = $courseStudent->import116_id;
        $payload['comment'] = $courseStudent->comment;
        $payload['sem_1_grade'] = $courseStudent->sem_1_grade;
        $payload['sem_2_grade'] = $courseStudent->sem_2_grade;
        $payload['sem_grade'] = $courseStudent->sem_grade;
        $payload['behaviour_1_grade'] = $courseStudent->behaviour_1_grade;
        $payload['behaviour_2_grade'] = $courseStudent->behaviour_2_grade;
        $payload['behaviour_grade'] = $courseStudent->behaviour_grade;
        $payload['stars'] = $courseStudent->stars ?? [];

        return $payload;
    }
}
