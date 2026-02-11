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

        $allowedBehaviourTypes = collect($auth_user->teaching_behaviour ?? [])
            ->pluck('short_name')
            ->filter()
            ->values()
            ->all();
        $allowedNotificationTypes = collect($auth_user->teaching_notifications ?? [])
            ->pluck('short_name')
            ->filter()
            ->values()
            ->all();

        $validated = $request->validate([
            'teaching_course_id' => 'required|integer|exists:teaching_courses,id',
            'user_id' => 'required|integer|exists:users,id',
            'kind' => ['nullable', 'string', Rule::in(['behaviour', 'notification'])],
            'type' => ['required', 'string', 'max:255'],
            'date' => 'nullable|date',
            'is_due' => 'nullable|boolean',
            'due_date' => 'nullable|date',
            'is_done' => 'nullable|boolean',
            'done_date' => 'nullable|date',
            'description' => 'nullable|string|max:1024',
        ]);

        $kind = $validated['kind'] ?? 'behaviour';
        $allowedTypes = $kind === 'notification' ? $allowedNotificationTypes : $allowedBehaviourTypes;
        if (! in_array($validated['type'], $allowedTypes, true)) {
            abort(422, 'Ungültiger Typ für die gewählte Eintragsart.');
        }
        if (($validated['is_due'] ?? false) && empty($validated['due_date'])) {
            abort(422, 'Bitte ein Fälligkeitsdatum angeben.');
        }
        if (($validated['is_due'] ?? false) && ($validated['is_done'] ?? false) && empty($validated['done_date'])) {
            abort(422, 'Bitte ein Erledigt-Datum angeben.');
        }

        $course = TeachingCourse::findOrFail($validated['teaching_course_id']);
        if ($course->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $student = User::findOrFail($validated['user_id']);
        if ($student->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $payload = [
            'teaching_course_id' => $validated['teaching_course_id'],
            'user_id' => $validated['user_id'],
            'type' => $validated['type'],
            'date' => $validated['date'] ?? null,
            'description' => $validated['description'] ?? null,
            'kind' => $kind,
            'due_date' => ($validated['is_due'] ?? false) ? ($validated['due_date'] ?? null) : null,
            'done_date' => ($validated['is_due'] ?? false) && ($validated['is_done'] ?? false) ? ($validated['done_date'] ?? null) : null,
        ];

        $entry = TeachingCourseBehaviourEntry::create($payload);

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

        $allowedBehaviourTypes = collect($auth_user->teaching_behaviour ?? [])
            ->pluck('short_name')
            ->filter()
            ->values()
            ->all();
        $allowedNotificationTypes = collect($auth_user->teaching_notifications ?? [])
            ->pluck('short_name')
            ->filter()
            ->values()
            ->all();

        $validated = $request->validate([
            'kind' => ['nullable', 'string', Rule::in(['behaviour', 'notification'])],
            'type' => ['required', 'string', 'max:255'],
            'date' => 'nullable|date',
            'is_due' => 'nullable|boolean',
            'due_date' => 'nullable|date',
            'is_done' => 'nullable|boolean',
            'done_date' => 'nullable|date',
            'description' => 'nullable|string|max:1024',
        ]);

        $kind = $validated['kind'] ?? ($course_behaviour_entry->kind ?: 'behaviour');
        $allowedTypes = $kind === 'notification' ? $allowedNotificationTypes : $allowedBehaviourTypes;
        if (! in_array($validated['type'], $allowedTypes, true)) {
            abort(422, 'Ungültiger Typ für die gewählte Eintragsart.');
        }
        if (($validated['is_due'] ?? false) && empty($validated['due_date'])) {
            abort(422, 'Bitte ein Fälligkeitsdatum angeben.');
        }
        if (($validated['is_due'] ?? false) && ($validated['is_done'] ?? false) && empty($validated['done_date'])) {
            abort(422, 'Bitte ein Erledigt-Datum angeben.');
        }

        $student = $course_behaviour_entry->user;
        if (! $student || $student->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $payload = [
            'kind' => $kind,
            'type' => $validated['type'],
            'date' => $validated['date'] ?? null,
            'description' => $validated['description'] ?? null,
            'due_date' => ($validated['is_due'] ?? false) ? ($validated['due_date'] ?? null) : null,
            'done_date' => ($validated['is_due'] ?? false) && ($validated['is_done'] ?? false) ? ($validated['done_date'] ?? null) : null,
        ];

        $course_behaviour_entry->update($payload);

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
