<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Services\TeachingWorkAssignmentMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkAssignmentMailController extends Controller
{
    public function __construct(private TeachingWorkAssignmentMail $mail) {}

    public function show(Request $request, TeachingCourseWork $course_work): JsonResponse
    {
        $this->actor($course_work);
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $slot = $this->mail->slot($course_work, $data['date'] ?? null);

        return $this->result($course_work, $slot);
    }

    public function prepare(Request $request, TeachingCourseWork $course_work): JsonResponse
    {
        $actor = $this->actor($course_work);
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'previous' => ['nullable', 'uuid'],
            'transport' => ['required', 'in:postmark,mailpit']]);
        $slot = $this->mail->slot($course_work, $data['date'] ?? null);
        $this->mail->prepare($course_work, $actor, $slot, $data['transport'], $data['previous'] ?? null);

        return $this->result($course_work->fresh(), $slot);
    }

    public function attachment(Request $request, TeachingCourseWork $course_work, string $batch, int $index): StreamedResponse
    {
        $this->actor($course_work);
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $file = $this->mail->attachment($course_work, $this->mail->slot($course_work, $data['date'] ?? null), $batch, $index);

        return response()->streamDownload(static function () use ($file): void {
            echo $file['bytes'];
        }, $file['name'], ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function act(Request $request, TeachingCourseWork $course_work): JsonResponse
    {
        $actor = $this->actor($course_work);
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'batch_id' => ['required', 'uuid'],
            'action' => ['required', 'in:send,withdraw,restore,discard,reconcile'], 'confirmed' => ['required', 'accepted'],
            'include_previously_sent' => ['sometimes', 'boolean']]);
        $slot = $this->mail->slot($course_work, $data['date'] ?? null);
        match ($data['action']) {
            'send' => $this->mail->queue($course_work, $actor, $slot, $data['batch_id'], $data['include_previously_sent'] ?? false),
            'reconcile' => $this->mail->reconcile($course_work, $slot, $data['batch_id']),
            default => $this->mail->undo($course_work, $slot, $data['batch_id'], $data['action']),
        };

        return $this->result($course_work->fresh(), $slot);
    }

    private function result(TeachingCourseWork $work, string $slot): JsonResponse
    {
        return response()->json(['data' => app(TeachingCourseWorkEntrySyncService::class)->serializeWork($work),
            'preview' => $this->mail->preview($work, $slot), 'local' => app()->environment('local', 'testing')])
            ->header('Cache-Control', 'private, no-store');
    }

    private function actor(TeachingCourseWork $work): User
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $work->teachingCourse && (int) $work->teachingCourse->user_id === (int) $actor->id, 403);
        $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);

        return $actor;
    }
}
