<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentCategoryEvaluation;
use App\Models\User;
use App\Services\TeachingCourseStudentCategoryEvaluationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseStudentCategoryEvaluationController extends Controller
{
    public function index(Request $request)
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'course_id' => 'required|integer|exists:teaching_courses,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'semester' => 'nullable|integer|in:1,2,3',
        ]);

        $course = TeachingCourse::findOrFail($validated['course_id']);
        $this->authorizeTeachingCourseAccess($course, $authUser);

        $query = TeachingCourseStudentCategoryEvaluation::query()
            ->where('teaching_course_id', $course->id);

        if (array_key_exists('semester', $validated)) {
            $query->where('semester', $validated['semester']);
        }

        if (! empty($validated['user_id'])) {
            $student = User::findOrFail($validated['user_id']);
            if ($student->school_id !== $authUser->school_id) {
                abort(403, 'Sie haben keine Berechtigung');
            }

            $query->where('user_id', $student->id);
        }

        return response()->json([
            'data' => $query->orderBy('user_id')->orderBy('semester')->orderBy('category_name')->get(),
        ]);
    }

    public function store(Request $request, TeachingCourseStudentCategoryEvaluationService $service)
    {
        if (! $authUser = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $baseValidated = $request->validate([
            'teaching_course_id' => 'required|integer|exists:teaching_courses,id',
            'user_id' => 'required|integer|exists:users,id',
            'semester' => 'required|integer|in:1,2,3',
        ]);

        $course = TeachingCourse::findOrFail($baseValidated['teaching_course_id']);
        $this->authorizeTeachingCourseAccess($course, $authUser);

        $student = User::findOrFail($baseValidated['user_id']);
        if ($student->school_id !== $authUser->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $config = $service->configForCourse($course, $authUser);

        $validated = $request->validate([
            'teaching_course_id' => 'required|integer|exists:teaching_courses,id',
            'user_id' => 'required|integer|exists:users,id',
            'semester' => 'required|integer|in:1,2,3',
            'category_name' => ['required', 'string', 'max:100', Rule::in($config['category_names'])],
            'value' => ['required', 'string', 'max:50', Rule::in($config['allowed_values'])],
        ]);

        $evaluation = TeachingCourseStudentCategoryEvaluation::query()->updateOrCreate(
            [
                'teaching_course_id' => $validated['teaching_course_id'],
                'user_id' => $validated['user_id'],
                'semester' => $validated['semester'],
                'category_name' => $validated['category_name'],
            ],
            [
                'value' => $validated['value'],
            ]
        );

        return response()->json(['data' => $evaluation]);
    }
}
