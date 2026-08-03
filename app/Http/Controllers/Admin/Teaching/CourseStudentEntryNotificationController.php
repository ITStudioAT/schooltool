<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Teaching\SendCourseStudentEntryNotificationsRequest;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseStudentEntryNotification;
use App\Models\User;
use App\Services\TeachingCourseStudentEntryNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseStudentEntryNotificationController extends Controller
{
    public function preview(
        Request $request,
        TeachingCourseStudentEntryNotificationService $notificationService,
    ): JsonResponse {
        $authUser = $this->authorizedTeachingUser();
        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:teaching_courses,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', 'string', 'max:255'],
        ]);

        $course = TeachingCourse::findOrFail($validated['course_id']);
        $this->authorizeTeachingCourseAccess($course, $authUser);

        $student = User::findOrFail($validated['user_id']);
        abort_unless((int) $student->school_id === (int) $authUser->school_id, 403, 'Sie haben keine Berechtigung');

        return response()->json([
            'data' => $notificationService->preview($course, $student, $validated['type']),
        ]);
    }

    public function index(
        TeachingCourseStudentEntry $courseStudentEntry,
        TeachingCourseStudentEntryNotificationService $notificationService,
    ): JsonResponse {
        $authUser = $this->authorizedTeachingUser();
        $this->authorizeEntryAccess($courseStudentEntry, $authUser);

        return response()->json([
            'data' => $notificationService->recipients($courseStudentEntry),
        ]);
    }

    public function store(
        SendCourseStudentEntryNotificationsRequest $request,
        TeachingCourseStudentEntry $courseStudentEntry,
        TeachingCourseStudentEntryNotificationService $notificationService,
    ): JsonResponse {
        $authUser = $this->authorizedTeachingUser();
        $this->authorizeEntryAccess($courseStudentEntry, $authUser);

        return response()->json([
            'data' => $notificationService->send($courseStudentEntry, $request->validated('recipients')),
            'message' => 'Die ausgewählten Personen wurden per E-Mail informiert.',
        ]);
    }

    public function update(
        TeachingCourseStudentEntry $courseStudentEntry,
        TeachingCourseStudentEntryNotification $notification,
        TeachingCourseStudentEntryNotificationService $notificationService,
    ): JsonResponse {
        $authUser = $this->authorizedTeachingUser();
        $this->authorizeEntryAccess($courseStudentEntry, $authUser);

        return response()->json([
            'data' => $notificationService->confirmManually($courseStudentEntry, $notification, $authUser),
            'message' => 'Die Bestätigung wurde manuell erfasst.',
        ]);
    }

    private function authorizedTeachingUser(): User
    {
        $authUser = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($authUser, 403, 'Sie haben keine Berechtigung');

        return $authUser;
    }

    private function authorizeEntryAccess(TeachingCourseStudentEntry $entry, User $authUser): void
    {
        $course = $entry->teachingCourse;
        abort_unless($course, 403, 'Sie haben keine Berechtigung');

        $this->authorizeTeachingCourseAccess($course, $authUser);

        $student = $entry->user;
        abort_unless($student && (int) $student->school_id === (int) $authUser->school_id, 403, 'Sie haben keine Berechtigung');
    }
}
