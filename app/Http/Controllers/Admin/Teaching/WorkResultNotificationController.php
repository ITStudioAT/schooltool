<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Services\TeachingWorkResultNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkResultNotificationController extends Controller
{
    public function preview(Request $request, TeachingCourseWork $course_work, TeachingWorkResultNotification $notifications): JsonResponse
    {
        $actor = $this->actor($course_work);

        return response()->json(['data' => $notifications->preview($course_work, $actor, $this->input($request))])->header('Cache-Control', 'private, no-store');
    }

    public function send(Request $request, TeachingCourseWork $course_work, TeachingWorkResultNotification $notifications): JsonResponse
    {
        $actor = $this->actor($course_work);
        $status = $notifications->sendSimulation($course_work, $actor, $this->input($request, true));

        return response()->json(['notification' => $status, 'data' => app(TeachingCourseWorkEntrySyncService::class)->serializeWork($course_work->fresh())]);
    }

    public function officeOrder(Request $request, TeachingCourseWork $course_work, TeachingWorkResultNotification $notifications): JsonResponse
    {
        $actor = $this->actor($course_work);

        return response()->json(['text' => $notifications->officeOrder($course_work, $actor, $this->input($request, true))])->header('Cache-Control', 'private, no-store');
    }

    private function input(Request $request, bool $confirmed = false): array
    {
        return $request->validate(['student_ids' => [$confirmed ? 'present' : 'sometimes', 'array', 'max:100'],
            'student_ids.*' => ['required', 'integer', 'min:1', 'distinct'], 'subject' => ['sometimes', 'nullable', 'string', 'max:200'],
            'body' => ['sometimes', 'nullable', 'string', 'max:12000'], 'date' => ['nullable', 'date_format:Y-m-d'],
            'teacher_preview' => ['sometimes', 'boolean'], 'preview_id' => ['nullable', 'uuid'],
            'address_form' => ['sometimes', 'string', 'in:du,sie'],
            'hash' => [$confirmed ? 'required' : 'sometimes', 'string', 'regex:/\A[a-f0-9]{64}\z/']]);
    }

    private function actor(TeachingCourseWork $work): User
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);

        return $actor;
    }
}
