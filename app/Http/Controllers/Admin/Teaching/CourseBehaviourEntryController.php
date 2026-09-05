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
        $this->authorizeTeachingCourseAccess($course, $auth_user);

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

        $isReminder = $request->input('kind') === 'notification' && blank($request->input('type'));

        $validated = $request->validate([
            'teaching_course_id' => 'required|integer|exists:teaching_courses,id',
            'user_id' => 'required|integer|exists:users,id',
            'kind' => ['nullable', 'string', Rule::in(['behaviour', 'notification'])],
            'type' => [$isReminder ? 'nullable' : 'required', 'string', 'max:255'],
            'date' => 'nullable|date',
            'is_due' => 'nullable|boolean',
            'due_date' => $isReminder ? ['required', 'date_format:Y-m-d'] : ['nullable', 'date'],
            'due_time' => ['nullable', 'date_format:H:i'],
            'remind_student_by_email' => ['sometimes', 'boolean'],
            'remind_teacher_by_email' => ['sometimes', 'boolean'],
            'is_done' => 'nullable|boolean',
            'done_date' => 'nullable|date',
            'description' => [$isReminder ? 'required' : 'nullable', 'string', 'max:1024'],
        ]);

        $course = TeachingCourse::findOrFail($validated['teaching_course_id']);
        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $courseActor = $this->teachingCourseActor($auth_user, $course);

        $allowedBehaviourTypes = collect($this->teachingBehaviourForSchoolyear($courseActor, $course->schoolyear_id))
            ->pluck('short_name')
            ->filter()
            ->values()
            ->all();
        $allowedNotificationTypes = collect($this->teachingNotificationsForSchoolyear($courseActor, $course->schoolyear_id))
            ->pluck('short_name')
            ->filter()
            ->values()
            ->all();

        $kind = $validated['kind'] ?? 'behaviour';
        $allowedTypes = $kind === 'notification' ? $allowedNotificationTypes : $allowedBehaviourTypes;
        if (! $isReminder && ! in_array($validated['type'], $allowedTypes, true)) {
            abort(422, 'Ungültiger Typ für die gewählte Eintragsart.');
        }
        if (($validated['is_due'] ?? false) && empty($validated['due_date'])) {
            abort(422, 'Bitte ein Fälligkeitsdatum angeben.');
        }
        if (($isReminder || ($validated['is_due'] ?? false)) && ($validated['is_done'] ?? false) && empty($validated['done_date'])) {
            abort(422, 'Bitte ein Erledigt-Datum angeben.');
        }

        $student = User::findOrFail($validated['user_id']);
        if ($student->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $payload = [
            'teaching_course_id' => $validated['teaching_course_id'],
            'user_id' => $validated['user_id'],
            'type' => $validated['type'] ?? null,
            'date' => $validated['date'] ?? null,
            'description' => $validated['description'] ?? null,
            'kind' => $kind,
            'due_date' => $isReminder || ($validated['is_due'] ?? false) ? ($validated['due_date'] ?? null) : null,
            'due_time' => $isReminder ? ($validated['due_time'] ?? null) : null,
            'remind_student_by_email' => $isReminder && ($validated['remind_student_by_email'] ?? false),
            'remind_teacher_by_email' => $isReminder && ($validated['remind_teacher_by_email'] ?? true),
            'done_date' => ($isReminder || ($validated['is_due'] ?? false)) && ($validated['is_done'] ?? false) ? ($validated['done_date'] ?? null) : null,
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
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $courseActor = $this->teachingCourseActor($auth_user, $course);

        $allowedBehaviourTypes = collect($this->teachingBehaviourForSchoolyear($courseActor, $course->schoolyear_id))
            ->pluck('short_name')
            ->filter()
            ->values()
            ->all();
        $allowedNotificationTypes = collect($this->teachingNotificationsForSchoolyear($courseActor, $course->schoolyear_id))
            ->pluck('short_name')
            ->filter()
            ->values()
            ->all();

        $kind = $request->input('kind') ?? ($course_behaviour_entry->kind ?: 'behaviour');
        $isReminder = $kind === 'notification' && blank($request->input('type'));

        $validated = $request->validate([
            'kind' => ['nullable', 'string', Rule::in(['behaviour', 'notification'])],
            'type' => [$isReminder ? 'nullable' : 'required', 'string', 'max:255'],
            'date' => 'nullable|date',
            'is_due' => 'nullable|boolean',
            'due_date' => $isReminder ? ['required', 'date_format:Y-m-d'] : ['nullable', 'date'],
            'due_time' => ['nullable', 'date_format:H:i'],
            'remind_student_by_email' => ['sometimes', 'boolean'],
            'remind_teacher_by_email' => ['sometimes', 'boolean'],
            'is_done' => 'nullable|boolean',
            'done_date' => 'nullable|date',
            'description' => [$isReminder ? 'required' : 'nullable', 'string', 'max:1024'],
        ]);

        $kind = $validated['kind'] ?? ($course_behaviour_entry->kind ?: 'behaviour');
        $allowedTypes = $kind === 'notification' ? $allowedNotificationTypes : $allowedBehaviourTypes;
        if (! $isReminder && ! in_array($validated['type'], $allowedTypes, true)) {
            abort(422, 'Ungültiger Typ für die gewählte Eintragsart.');
        }
        if (($validated['is_due'] ?? false) && empty($validated['due_date'])) {
            abort(422, 'Bitte ein Fälligkeitsdatum angeben.');
        }
        if (($isReminder || ($validated['is_due'] ?? false)) && ($validated['is_done'] ?? false) && empty($validated['done_date'])) {
            abort(422, 'Bitte ein Erledigt-Datum angeben.');
        }

        $student = $course_behaviour_entry->user;
        if (! $student || $student->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $payload = [
            'kind' => $kind,
            'type' => $validated['type'] ?? null,
            'date' => $validated['date'] ?? null,
            'description' => $validated['description'] ?? null,
            'due_date' => $isReminder || ($validated['is_due'] ?? false) ? ($validated['due_date'] ?? null) : null,
            'due_time' => $isReminder ? ($validated['due_time'] ?? null) : null,
            'remind_student_by_email' => $isReminder && ($validated['remind_student_by_email'] ?? $course_behaviour_entry->remind_student_by_email),
            'remind_teacher_by_email' => $isReminder && ($validated['remind_teacher_by_email'] ?? $course_behaviour_entry->remind_teacher_by_email),
            'done_date' => ($isReminder || ($validated['is_due'] ?? false)) && ($validated['is_done'] ?? false) ? ($validated['done_date'] ?? null) : null,
        ];

        $course_behaviour_entry->fill($payload);

        if ($isReminder && $course_behaviour_entry->isDirty(['description', 'due_date', 'due_time'])) {
            $course_behaviour_entry->reminder_email_sent_at = null;
            $course_behaviour_entry->student_reminder_email_sent_at = null;
        }

        $course_behaviour_entry->save();

        return response()->json(['data' => $course_behaviour_entry]);
    }

    public function destroy(TeachingCourseBehaviourEntry $course_behaviour_entry)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'teaching_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course = $course_behaviour_entry->teachingCourse;
        if (! $course) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $this->authorizeTeachingCourseAccess($course, $auth_user);

        $student = $course_behaviour_entry->user;
        if (! $student || $student->school_id !== $auth_user->school_id) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $course_behaviour_entry->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function teachingBehaviourForSchoolyear(User $user, ?int $schoolyearId): array
    {
        $bySchoolyear = $user->teaching_behaviour_by_schoolyear;

        if ($schoolyearId !== null && is_array($bySchoolyear)) {
            $entries = $bySchoolyear[(string) $schoolyearId] ?? null;

            if (is_array($entries)) {
                return $entries;
            }
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function teachingNotificationsForSchoolyear(User $user, ?int $schoolyearId): array
    {
        $bySchoolyear = $user->teaching_notifications_by_schoolyear;

        if ($schoolyearId !== null && is_array($bySchoolyear)) {
            $entries = $bySchoolyear[(string) $schoolyearId] ?? null;

            if (is_array($entries)) {
                return $entries;
            }
        }

        return [];
    }
}
