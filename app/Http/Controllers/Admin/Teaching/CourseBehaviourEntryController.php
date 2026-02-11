<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseBehaviourEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseBehaviourEntryController extends Controller
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

        $query = TeachingCourseBehaviourEntry::where('teaching_course_id', $course->id);

        if (! empty($validated['user_id'])) {
            $student = User::findOrFail($validated['user_id']);
            if ($student->school_id !== $auth_user->school_id) {
                abort(403, 'Sie haben keine Berechtigung');
            }
            $query->where('user_id', $student->id);
        }

        $entries = $query->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['data' => $entries]);
    }

    public function store(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $allowedTypes = collect($auth_user->teaching_behaviour ?? [])
            ->pluck('short_name')
            ->filter()
            ->values()
            ->all();

        $validated = $request->validate([
            'teaching_course_id' => 'required|integer|exists:teaching_courses,id',
            'user_id' => 'required|integer|exists:users,id',
            'type' => ['required', 'string', 'max:255', Rule::in($allowedTypes)],
            'date' => 'nullable|date',
            'description' => 'nullable|string|max:1024',
        ]);

        $course = TeachingCourse::findOrFail($validated['teaching_course_id']);
        if ($course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $student = User::findOrFail($validated['user_id']);
        if ($student->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $entry = TeachingCourseBehaviourEntry::create($validated);

        return response()->json(['data' => $entry], 201);
    }

    public function update(Request $request, TeachingCourseBehaviourEntry $course_behaviour_entry)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_behaviour_entry->teachingCourse;
        if (! $course || $course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $allowedTypes = collect($auth_user->teaching_behaviour ?? [])
            ->pluck('short_name')
            ->filter()
            ->values()
            ->all();

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:255', Rule::in($allowedTypes)],
            'date' => 'nullable|date',
            'description' => 'nullable|string|max:1024',
        ]);

        $student = $course_behaviour_entry->user;
        if (! $student || $student->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course_behaviour_entry->update($validated);

        return response()->json(['data' => $course_behaviour_entry]);
    }

    public function destroy(TeachingCourseBehaviourEntry $course_behaviour_entry)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_behaviour_entry->teachingCourse;
        if (! $course || $course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $student = $course_behaviour_entry->user;
        if (! $student || $student->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course_behaviour_entry->delete();

        return response()->json(null, 204);
    }
}
