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
    public function index(TeachingCourseService $service)
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
                return array_merge(
                    $service->normalizeStudentIds($course->students),
                    $service->normalizeStudentIds($course->students_deleted)
                );
            })
            ->unique()
            ->values();

        $studentsById = $studentIds->isEmpty()
            ? collect()
            : User::whereIn('id', $studentIds)->get()->keyBy('id');

        $courses->each(function ($course) use ($studentsById, $service) {
            $studentIds = $service->normalizeStudentIds($course->students);
            $deletedIds = $service->normalizeStudentIds($course->students_deleted);

            $course->setAttribute(
                'students',
                StudentResource::collection($studentsById->only($studentIds)->values())
            );

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
        ]);

        $sortedClasses = $validated['classes'];
        sort($sortedClasses);

        $course = TeachingCourse::create([
            'school_id' => $auth_user->school_id,
            'schoolyear_id' => $auth_user->schoolyear_id,
            'user_id' => $auth_user->id,
            'title' => $validated['title'],
            'classes' => $sortedClasses,
            'students' => $service->resolveStudentIds($validated['students'] ?? [], $auth_user->school_id),
            'students_deleted' => $service->resolveStudentIds($validated['students_deleted'] ?? [], $auth_user->school_id),
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
        ]);

        $sortedClasses = $validated['classes'];
        sort($sortedClasses);

        $course->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'classes' => $sortedClasses,
            'students' => $service->resolveStudentIds($validated['students'] ?? [], $auth_user->school_id),
            'students_deleted' => $service->resolveStudentIds($validated['students_deleted'] ?? [], $auth_user->school_id),
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
