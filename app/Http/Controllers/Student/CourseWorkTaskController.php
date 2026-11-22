<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SchoolTool;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\ParentStudentAccessService;
use App\Services\TeachingWorkStudentDistribution;
use App\Services\TeachingWorkSubmissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CourseWorkTaskController extends Controller
{
    public function index(Request $request, int $courseId, ParentStudentAccessService $access, TeachingWorkStudentDistribution $distribution): JsonResponse
    {
        $student = $access->currentStudent();
        abort_unless($student, 403);
        $course = $this->authorizedCourse($courseId, $student);
        if ($request->query('view') === 'deadlines') {
            return $this->deadlineSnapshot($request, $course, $student, $distribution);
        }

        $tasks = $course->teachingCourseWorks()->orderByDesc('date_for_all_groups')->get()
            ->map(fn (TeachingCourseWork $work): ?array => $distribution->taskForStudent($work->setRelation('teachingCourse', $course), $student))
            ->filter()->values();

        $response = response()->json(['tasks' => $tasks])->header('Cache-Control', 'private, no-store');
        if ($access->authenticatedStudent()?->is($student)) {
            $response->header('X-Schooltool-Deadline-Channel', 'user.'.$student->id);
        }

        return $response;
    }

    private function deadlineSnapshot(Request $request, TeachingCourse $course, User $student, TeachingWorkStudentDistribution $distribution): JsonResponse
    {
        $works = $course->teachingCourseWorks()
            ->where(fn (Builder $query) => $query->whereJsonContains('status->student_distribution->student_ids', (int) $student->id)
                ->orWhereJsonContains('status->student_distribution->ended_email_student_ids', (int) $student->id)
                ->orWhereRaw("JSON_CONTAINS(JSON_EXTRACT(status, '$.assignment_mail.batches.*.recipients[*].student_id'), ?)", [(string) (int) $student->id]))
            ->orderBy('id')
            ->select(['id', 'teaching_course_id', 'finish_until_date', 'finish_until_time', 'groups'])
            ->selectRaw(<<<'SQL'
                JSON_OBJECT(
                    'submission_method', JSON_EXTRACT(status, '$.submission_method'),
                    'student_distribution', JSON_OBJECT(
                        'student_ids', JSON_EXTRACT(status, '$.student_distribution.student_ids'),
                        'ended_email_student_ids', JSON_EXTRACT(status, '$.student_distribution.ended_email_student_ids'),
                        'prepared_at', JSON_EXTRACT(status, '$.student_distribution.prepared_at'),
                        'published_at', JSON_EXTRACT(status, '$.student_distribution.published_at'),
                        'withdrawn_at', JSON_EXTRACT(status, '$.student_distribution.withdrawn_at'),
                        'deleted_at', JSON_EXTRACT(status, '$.student_distribution.deleted_at'),
                        'ended_at', JSON_EXTRACT(status, '$.student_distribution.ended_at')
                    )
                ) AS status
                SQL)
            ->selectRaw(<<<'SQL'
                (SELECT JSON_ARRAYAGG(recipient.accepted_at)
                 FROM JSON_TABLE(teaching_course_works.status, '$.assignment_mail.batches.*' COLUMNS (
                     transport VARCHAR(16) PATH '$.transport',
                     withdrawn_at VARCHAR(40) PATH '$.withdrawn_at',
                     NESTED PATH '$.recipients[*]' COLUMNS (
                         student_id BIGINT PATH '$.student_id', state VARCHAR(16) PATH '$.state', accepted_at VARCHAR(40) PATH '$.accepted_at'
                     )
                 )) AS recipient
                 WHERE recipient.transport = 'postmark' AND recipient.withdrawn_at IS NULL
                     AND recipient.student_id = ? AND recipient.state = 'accepted' AND recipient.accepted_at IS NOT NULL
                ) AS accepted_submission_times
                SQL, [$student->id])
            ->get();

        // Use the saved values, including changes made within the same timestamp second.
        $version = hash('sha256', serialize([$course->id, $student->id, $works->map->getAttributes()->all()]));
        $data = ['version' => $version];
        if ($request->query('version') !== $version) {
            $data['tasks'] = $works->map(fn (TeachingCourseWork $work): ?array => $distribution->deadlineSummaryForStudent(
                $work->setRelation('teachingCourse', $course), $student
            ))->filter()->values();
        }

        return response()->json($data)->header('Cache-Control', 'private, no-store');
    }

    public function start(int $courseId, TeachingCourseWork $course_work, ParentStudentAccessService $access, TeachingWorkStudentDistribution $distribution): JsonResponse
    {
        $student = $access->authenticatedStudent();
        abort_unless($student && $access->currentStudent()?->is($student), 403);
        $course = $this->authorizedCourse($courseId, $student);
        DB::transaction(function () use ($course, $course_work, $student, $distribution): void {
            $work = $course->teachingCourseWorks()->lockForUpdate()->findOrFail($course_work->id);
            $work->assertStudentReleaseOpen();
            abort_unless($distribution->studentTaskStatus($work, $student) === 'released', 404);
            abort_if(TeachingWorkSubmissions::expired($work), 409, 'Die Abgabefrist ist abgelaufen.');
            $started = $work->status['portal_started_student_ids'] ?? [];
            if (! in_array((int) $student->id, $started, true)) {
                $work->update(['status' => [...$work->status, 'portal_started_student_ids' => [...$started, (int) $student->id]]]);
            }
        }, 3);

        return response()->json(['started' => true])->header('Cache-Control', 'private, no-store');
    }

    public function submit(Request $request, int $courseId, TeachingCourseWork $course_work, ParentStudentAccessService $access,
        TeachingWorkStudentDistribution $distribution, TeachingWorkSubmissions $submissions, ?string $fileId = null): JsonResponse
    {
        $student = $access->authenticatedStudent();
        abort_unless($student, 403);
        $course = $this->authorizedCourse($courseId, $student);
        abort_unless((int) $course_work->teaching_course_id === $courseId, 404);
        $upload = $fileId === null ? $request->validate(['file' => ['required', 'file', 'max:'.TeachingWorkSubmissions::MAX_FILE_KIB]])['file'] : null;
        $createdPath = null;
        try {
            $receipts = DB::transaction(function () use ($course, $course_work, $student, $distribution, $submissions, $fileId, $upload, &$createdPath): array {
                $work = $course->teachingCourseWorks()->lockForUpdate()->findOrFail($course_work->id);
                $work->setRelation('teachingCourse', $course);
                $status = $distribution->studentTaskStatus($work, $student);
                abort_unless($status !== null, 404);
                $work->assertStudentReleaseOpen();
                abort_unless(in_array($status, ['released', 'email_delivery'], true), 409, 'Die Aufgabe ist derzeit nicht freigegeben.');
                abort_unless((TeachingWorkSubmissions::method($work)['channel'] ?? null) === 'portal', 409, 'Für diese Aufgabe ist keine Abgabe im Schülerbereich vorgesehen.');
                abort_if(TeachingWorkSubmissions::expired($work), 409, 'Die Abgabefrist ist abgelaufen.');
                if ($fileId === null) {
                    $submissions->upload($work, $student, $upload, $createdPath);
                } else {
                    $submissions->withdraw($work, $student, $fileId);
                }

                return TeachingWorkSubmissions::receipts($work, $student);
            });
        } catch (Throwable $exception) {
            if ($createdPath !== null) {
                Storage::disk('local')->delete($createdPath);
            }
            throw $exception;
        }

        return response()->json(['files' => $receipts])->header('Cache-Control', 'private, no-store');
    }

    public function download(int $courseId, TeachingCourseWork $course_work, string $fileId, ParentStudentAccessService $access, TeachingWorkStudentDistribution $distribution): StreamedResponse
    {
        $student = $access->currentStudent();
        abort_unless($student, 403);
        $this->authorizedCourse($courseId, $student);
        abort_unless((int) $course_work->teaching_course_id === $courseId && $distribution->visibleTo($course_work, $student), 404);
        if ($fileId === 'deadline') {
            abort_if(empty($course_work->status['student_distribution']['file_ids']), 404);
            $pdf = base64_decode(Pdf::view('pdfs.teachingExercise', [
                'title' => $course_work->title,
                'label' => 'Fristenblatt',
                'course' => $course_work->teachingCourse->title,
                'html' => '',
                'deadlineLabel' => $course_work->finalDeadlineLabel() ?? 'Derzeit ist keine Abgabefrist festgelegt.',
            ])->format('a4')->base64(), true);
            abort_unless(is_string($pdf) && str_starts_with($pdf, '%PDF-'), 500);

            return response()->streamDownload(static function () use ($pdf): void {
                echo $pdf;
            }, 'Fristenblatt.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }
        $file = collect($course_work->status['student_task_files'] ?? [])->firstWhere('id', $fileId);
        abort_unless($file && in_array($fileId, $course_work->status['student_distribution']['file_ids'] ?? [], true)
            && ($file['storage_disk'] ?? null) === 'local' && Storage::disk('local')->exists($file['file_path']), 404);

        $stream = Storage::disk('local')->readStream($file['file_path']);
        abort_unless(is_resource($stream), 404);

        return response()->streamDownload(function () use ($stream): void {
            try {
                while (! feof($stream)) {
                    $chunk = fread($stream, 8192);
                    if ($chunk === false) {
                        throw new RuntimeException('Das Aufgaben-PDF konnte nicht gelesen werden.');
                    }
                    echo $chunk;
                }
            } finally {
                fclose($stream);
            }
        }, $file['name'], ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function authorizedCourse(int $courseId, User $student): TeachingCourse
    {
        $yearId = SchoolTool::where('school_id', $student->school_id)->value('active_schoolyear_id') ?? $student->schoolyear_id;
        $course = TeachingCourse::query()->where('school_id', $student->school_id)->where('schoolyear_id', $yearId)->findOrFail($courseId);
        abort_unless($course->teachingCourseStudents()->whereNull('canceled_at')->forStudentAccount($student, $course->schoolyear_id)->exists(), 403);

        return $course;
    }
}
