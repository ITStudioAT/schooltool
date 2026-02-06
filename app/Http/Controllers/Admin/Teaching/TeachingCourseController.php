<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Teaching\CourseResource;
use App\Http\Resources\Admin\Teaching\StudentResource;
use App\Models\Import116;
use App\Models\TeachingCourse;
use App\Models\User;
use App\Services\TeachingCourseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TeachingCourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, TeachingCourseService $service)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $courses = TeachingCourse::with(['teachingCourseDates' => fn ($q) => $q->orderBy('date')->orderByRaw('JSON_EXTRACT(hours, "$[0]")')])
            ->where('school_id', $auth_user->school_id)
            ->where('schoolyear_id', $auth_user->schoolyear_id)
            ->orderBy('title')
            ->get();

        $studentIds = $courses
            ->flatMap(function ($course) use ($service) {
                $entries = $service->normalizeStudentEntries($course->students);
                $ids = array_map(fn ($entry) => $entry['id'], $entries);
                return array_merge($ids, $service->normalizeStudentIds($course->students_deleted));
            })
            ->unique()
            ->values();

        $studentsById = $studentIds->isEmpty()
            ? collect()
            : User::whereIn('id', $studentIds)->get()->keyBy('id');

        $courses->each(function ($course) use ($studentsById, $service, $request) {
            $studentEntries = $service->normalizeStudentEntries($course->students);
            $studentIds = array_map(fn ($entry) => $entry['id'], $studentEntries);
            $deletedIds = $service->normalizeStudentIds($course->students_deleted);

            $students = [];
            foreach ($studentEntries as $entry) {
                $student = $studentsById->get($entry['id']);
                if (! $student) {
                    continue;
                }
                $data = (new StudentResource($student))->toArray($request);
                $data['comment'] = $entry['comment'] ?? null;
                $data['sem_1_grade'] = $entry['sem_1_grade'] ?? null;
                $data['sem_2_grade'] = $entry['sem_2_grade'] ?? null;
                $data['sem_grade'] = $entry['sem_grade'] ?? null;
                $students[] = $data;
            }

            $course->setAttribute('students', $students);

            $course->setAttribute(
                'students_deleted',
                StudentResource::collection($studentsById->only($deletedIds)->values())
            );
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
            'students_deleted' => 'nullable|array',
            'teaching_schema_id' => 'nullable|string|max:36',
        ]);

        $sortedClasses = $validated['classes'];
        sort($sortedClasses);

        $course = TeachingCourse::create([
            'school_id' => $auth_user->school_id,
            'schoolyear_id' => $auth_user->schoolyear_id,
            'user_id' => $auth_user->id,
            'title' => $validated['title'],
            'classes' => $sortedClasses,
            'students' => $service->resolveStudentEntries($validated['students'] ?? [], $auth_user->school_id),
            'students_deleted' => $service->resolveStudentIds($validated['students_deleted'] ?? [], $auth_user->school_id),
            'teaching_schema_id' => $validated['teaching_schema_id'] ?? null,
        ]);

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

        Log::info($request->all());


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
            'students_deleted' => 'nullable|array',
            'teaching_schema_id' => 'nullable|string|max:36',
        ]);

        $sortedClasses = $validated['classes'];
        sort($sortedClasses);

        $course->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'classes' => $sortedClasses,
            'students' => $service->resolveStudentEntries($validated['students'] ?? [], $auth_user->school_id),
            'students_deleted' => $service->resolveStudentIds($validated['students_deleted'] ?? [], $auth_user->school_id),
            'teaching_schema_id' => $validated['teaching_schema_id'] ?? $course->teaching_schema_id,
        ]);

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
}
