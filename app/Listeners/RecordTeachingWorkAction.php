<?php

namespace App\Listeners;

use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\TeachingWorkActionLog;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordTeachingWorkAction
{
    public function __construct(private TeachingWorkActionLog $log) {}

    public function handle(RequestHandled $event): void
    {
        // The streaming helper records the final response after the operation runs.
        if ($event->response->headers->get('Content-Type') !== 'application/x-ndjson') {
            $this->record($event->request, $event->response);
        }
    }

    public function record(Request $request, Response $response): void
    {
        $route = $request->route()?->getName();
        $httpStatus = $response->getStatusCode();
        if ($route === null || $request->isMethod('HEAD') || in_array($httpStatus, [401, 403, 419, 429], true)) {
            return;
        }
        $action = $this->action($route, $request);
        if ($action === null) {
            return;
        }

        try {
            $body = $response instanceof JsonResponse ? $response->getData(true) : [];
            $work = $request->route('course_work');
            if ($route === 'course_works.store' && is_int($body['data']['id'] ?? null)) {
                $work = TeachingCourseWork::query()->find($body['data']['id']);
            }
            if (! $work instanceof TeachingCourseWork) {
                return;
            }

            $studentPortal = str_starts_with($route, 'student.course-work.');
            if ($studentPortal) {
                // Only confirmed portal responses establish access to the selected work.
                if (! $response->isSuccessful() || (int) $request->route('courseId') !== $work->teaching_course_id) {
                    return;
                }
            } elseif (! $this->canRecordStaffAction($request->user(), $work)) {
                return;
            }

            $result = match (true) {
                $httpStatus >= 400, ($body['progress']['state'] ?? null) === 'failed' => 'failed',
                ($body['data']['status'] ?? null) === 'open' => 'open',
                ($body['data']['status'] ?? null) === 'existing' && $route === 'teaching.work-submissions.copy.file' => 'already_completed',
                ($body['preview']['can_import'] ?? null) === false => 'blocked',
                $httpStatus === 202 => 'queued',
                default => 'completed',
            };
            $date = (in_array($route, ['teaching.work-submissions.copy.prepare', 'teaching.work-submissions.copy.file'], true)
                ? ($body['data']['date'] ?? null) : null)
                ?? $request->input('portal_date') ?? $request->input('date');
            $date = is_string($date) && preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $parts)
                && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $date : null;
            $this->log->record($work, $action, $result, $studentPortal ? 'student_portal' : 'staff', $date);
        } catch (Throwable $exception) {
            // An audit failure must not hide an operation that has already committed.
            logger()->error('Teaching work action could not be recorded.', [
                'action' => $action, 'exception_class' => $exception::class,
            ]);
        }
    }

    private function canRecordStaffAction(?User $user, TeachingCourseWork $work): bool
    {
        $course = $work->teachingCourse;

        return $user && $course && (int) $user->school_id === (int) $course->school_id
            && (int) $user->schoolyear_id === (int) $course->schoolyear_id
            && ($user->hasAnyRole(['admin', 'teaching_admin'])
                || ($user->hasRole('teacher') && (int) $course->user_id === (int) $user->id));
    }

    private function action(string $route, Request $request): ?string
    {
        return match ($route) {
            'course_works.store' => 'work.create',
            'course_works.update' => match (true) {
                $request->hasAny(['expected_type', 'expected_is_group_work']) => 'work.change_type_or_form',
                $request->has('completion_override') && count($request->keys()) === 1 => $request->boolean('completion_override')
                    ? 'work.mark_complete' : 'work.reopen',
                $request->has('expected_final_deadline') => 'work.change_deadline',
                default => 'work.save',
            },
            'course_works.destroy' => 'work.delete',
            'teaching.course-works.ai-export' => match ($request->input('purpose')) {
                'evaluation' => 'grading.prepare_context',
                'email_preparation' => 'assignment_mail.prepare_context',
                'email_dispatch' => 'assignment_mail.prepare_dispatch_context',
                'submission_collection' => 'submission.prepare_collection_context',
                default => 'assessment.prepare_context',
            },
            'teaching.course-works.email-preparation-preview' => 'assignment_mail.preview',
            'teaching.course-works.postmark-test.preview' => 'mail_test.preview',
            'teaching.course-works.postmark-test.send' => 'mail_test.send',
            'teaching.course-works.prepare-exercise' => 'assessment.prepare',
            'teaching.course-works.store-exercise', 'teaching.course-works.exercise-run.store' => 'assessment.start',
            'teaching.course-works.exercise.destroy' => 'assessment.reset',
            'teaching.course-works.import-evaluations' => $request->boolean('apply') ? 'grading.import' : 'grading.preview_import',
            'teaching.course-works.import-folder' => 'work.import_folder',
            'teaching.course-works.import-json' => $request->boolean('apply') ? 'work.import_package' : 'work.preview_package',
            'teaching.course-works.import-dispatch' => $request->boolean('apply') ? 'mail.import_dispatch' : 'mail.preview_dispatch_import',
            'teaching.course-works.download-evaluation' => 'grading.download',
            'teaching.course-works.download-dispatch' => 'mail.download_dispatch',
            'teaching.work-provision-method.update' => 'release.change_method',
            'teaching.work-submission-method.update' => 'submission.change_method',
            'teaching.work-submissions.reopen' => 'submission.reopen',
            'teaching.work-submissions.copy.prepare' => 'submission.prepare_dropbox_copy',
            'teaching.work-submissions.copy.file' => 'submission.copy_file_to_dropbox',
            'teaching.work-submissions.download' => 'submission.download',
            'teaching.work-student-distribution' => match (true) {
                $request->input('action') === 'end' => 'release.end',
                $request->boolean('publish') => 'release.publish',
                default => 'release.pause',
            },
            'teaching.work-student-distribution.test-action' => match ($request->input('action')) {
                'countdown' => 'test.schedule_countdown',
                'pause' => 'test.pause',
                'resume' => 'test.resume',
                'reset' => 'test.reset',
                'stop' => 'test.stop',
                'delete' => 'test.delete',
                default => 'test.manage',
            },
            'teaching.work-student-distribution-preparation.delete' => 'release.delete_preparation',
            'teaching.work-student-distribution.files.download' => 'release.download_material',
            'teaching.work-results.visibility' => 'grading.change_visibility',
            'teaching.work-results.notice' => 'grading.save_notice',
            'teaching.work-results.preview' => 'results.preview_notice',
            'teaching.work-results.send' => 'results.send_notice',
            'teaching.work-results.office-order' => 'results.prepare_office_order',
            'teaching.work-assignment-mail.prepare' => 'assignment_mail.prepare',
            'teaching.work-assignment-mail.attachment' => 'assignment_mail.download_attachment',
            'teaching.work-assignment-mail.act' => match ($request->input('action')) {
                'send' => 'assignment_mail.send',
                'withdraw' => 'assignment_mail.withdraw',
                'restore' => 'assignment_mail.restore',
                'discard' => 'assignment_mail.discard',
                'reconcile' => 'assignment_mail.reconcile',
                default => 'assignment_mail.manage',
            },
            'teaching.work-dropbox.refresh' => match (true) {
                $request->boolean('overall') => 'work.check_overall_status',
                $request->boolean('status_only') => 'work.check_preparation_status',
                default => 'work.sync_deadline',
            },
            'teaching.work-dropbox.store-folder' => match (true) {
                $request->has('unlink') => 'dropbox.unlink_folder',
                $request->has('retry_preparation') => 'dropbox.retry_preparation',
                $request->boolean('verify'), $request->has('handshake') && ! $request->filled('proof') => 'dropbox.verify_folder',
                default => 'dropbox.link_folder',
            },
            'teaching.work-dropbox.folders' => $request->boolean('pdf') ? 'assessment.download_document' : null,
            'teaching.work-dropbox.connect' => 'dropbox.request_connection',
            'teaching.work-dropbox.disconnect' => 'dropbox.disconnect',
            'teaching.work-dropbox.import' => 'dropbox.import',
            'student.course-work.start' => 'portal.start_work',
            'student.course-work.submissions.store' => 'portal.submit',
            'student.course-work.submissions.withdraw' => 'portal.withdraw_submission',
            'student.course-work.task-file' => 'portal.download_material',
            'student.course-work.evaluation-pdf' => 'portal.download_evaluation',
            default => null,
        };
    }
}
