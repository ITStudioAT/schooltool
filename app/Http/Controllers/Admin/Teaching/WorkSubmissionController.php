<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourseWork;
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Services\TeachingWorkStudentDistribution;
use App\Services\TeachingWorkSubmissions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkSubmissionController extends Controller
{
    public function updateProvisionMethod(Request $request, TeachingCourseWork $course_work, TeachingCourseWorkEntrySyncService $entries): JsonResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);
        $combined = $request->hasAny(['submission', 'expected_submission']);
        $choice = $request->validate([
            'channel' => [$combined ? 'required' : 'present', 'nullable', 'in:portal,email'],
            'expected' => ['present', 'nullable', 'in:portal,email'],
            'submission' => [$combined ? 'required' : 'sometimes', 'array:channel,email', 'required_array_keys:channel'],
            'submission.channel' => ['required_with:submission', 'in:portal,email'],
            'submission.email' => ['required_if:submission.channel,email', 'nullable', 'email:filter', 'max:254'],
            'expected_submission' => [$combined ? 'present' : 'sometimes', 'nullable', 'array:channel,email', 'required_array_keys:channel'],
            'expected_submission.channel' => ['required_with:expected_submission', 'in:portal,email'],
            'expected_submission.email' => ['required_if:expected_submission.channel,email', 'nullable', 'email:filter', 'max:254'],
        ], [
            'submission.email.required_if' => 'Bitte gib die E-Mail-Adresse für die Abgabe ein.',
            'submission.email.email' => 'Bitte gib eine gültige E-Mail-Adresse für die Abgabe ein.',
            'submission.email.max' => 'Die E-Mail-Adresse für die Abgabe darf höchstens 254 Zeichen lang sein.',
        ]);

        $work = DB::transaction(function () use ($course_work, $choice, $actor, $combined): TeachingCourseWork {
            $work = TeachingCourseWork::query()->with('teachingCourse')->lockForUpdate()->findOrFail($course_work->id);
            $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);
            $work->assertStudentReleaseOpen();
            $status = $work->status ?? [];
            $current = $status['provision_method']['channel'] ?? null;
            $currentSubmission = $combined ? TeachingWorkSubmissions::method($work) : null;
            $submission = $this->submissionChoice($choice['submission'] ?? null);
            $provisionChanged = $current !== $choice['channel'];
            $submissionChanged = $combined && $currentSubmission !== $submission;
            $provisionConflict = $provisionChanged && $current !== $choice['expected'];
            $submissionConflict = $submissionChanged && $currentSubmission !== $this->submissionChoice($choice['expected_submission'] ?? null);
            if ($provisionConflict || $submissionConflict) {
                $response = ['current' => $current, 'message' => $provisionConflict
                    ? 'Der Bereitstellungsweg wurde inzwischen geändert. Bitte prüfe die aktuelle Auswahl.'
                    : 'Der Abgabeweg wurde inzwischen geändert. Bitte prüfe die aktuelle Auswahl.'];
                if ($combined) {
                    $response['current_submission'] = $currentSubmission;
                }
                throw new HttpResponseException(response()->json($response, 409));
            }
            if (! $provisionChanged && ! $submissionChanged) {
                return $work;
            }
            if ($submissionChanged) {
                $this->assertSubmissionChangeAllowed($work, $currentSubmission);
            }
            if ($choice['channel'] === null) {
                unset($status['provision_method']);
            } else {
                $status['provision_method'] = ['channel' => $choice['channel']];
            }
            if ($submissionChanged) {
                $status['submission_method'] = $submission;
            }
            $work->update(['status' => $status]);
            if ($submissionChanged) {
                app(TeachingWorkStudentDistribution::class)->signalDeadlineChangeAfterCommit($work);
            }

            return $work;
        });

        return response()->json(['data' => $entries->serializeWork($work)])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, TeachingCourseWork $course_work, TeachingCourseWorkEntrySyncService $entries): JsonResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);
        $data = $request->validate([
            'channel' => ['required', 'in:portal,email'],
            'email' => ['required_if:channel,email', 'nullable', 'email:filter', 'max:254'],
            'expected' => ['present', 'nullable', 'array:channel,email'],
        ]);

        $work = DB::transaction(function () use ($course_work, $data, $actor): TeachingCourseWork {
            $work = TeachingCourseWork::query()->with('teachingCourse')->lockForUpdate()->findOrFail($course_work->id);
            $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);
            $work->assertStudentReleaseOpen();
            $current = TeachingWorkSubmissions::method($work);
            $method = $this->submissionChoice($data);
            if ($current === $method) {
                return $work;
            }
            abort_unless($current === null ? $data['expected'] === null : $current == $data['expected'], 409,
                'Der Abgabeweg wurde inzwischen geändert. Bitte öffne die Arbeit erneut.');
            $this->assertSubmissionChangeAllowed($work, $current);
            $work->update(['status' => [...($work->status ?? []), 'submission_method' => $method]]);
            app(TeachingWorkStudentDistribution::class)->signalDeadlineChangeAfterCommit($work);

            return $work;
        });

        return response()->json(['data' => $entries->serializeWork($work)])->header('Cache-Control', 'private, no-store');
    }

    private function assertSubmissionChangeAllowed(TeachingCourseWork $work, ?array $current): void
    {
        $reason = TeachingWorkSubmissions::lockReason($work, lock: true);
        if ($reason === 'email_pending' || ($current !== null && $reason !== null)) {
            $message = match ($reason) {
                'email_sent' => 'Der Abgabeweg ist nach bestätigtem Aufgabenversand gesperrt. Ein Bearbeitungsbeginn außerhalb von Schooltool ist nicht überprüfbar.',
                'email_pending' => 'Der Aufgabenversand läuft oder muss noch abgeglichen werden. Der Abgabeweg kann derzeit nicht geändert werden.',
                default => 'Abgabeweg nicht mehr änderbar. Die Bearbeitung hat bereits begonnen.',
            };
            throw new HttpResponseException(response()->json(['message' => $message, 'submission_lock_reason' => $reason,
                'current_submission' => $current], 409));
        }
    }

    /** @return array{channel: string, email: ?string}|null */
    private function submissionChoice(?array $choice): ?array
    {
        return isset($choice['channel'])
            ? ['channel' => $choice['channel'], 'email' => $choice['channel'] === 'email' ? $choice['email'] : null]
            : null;
    }

    public function download(TeachingCourseWork $course_work, string $fileId): StreamedResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);
        $file = collect($course_work->status['portal_submissions'] ?? [])->firstWhere('id', $fileId);
        $path = TeachingWorkSubmissions::storagePath($course_work, $file ?? []);
        abort_unless($path !== null && empty($file['withdrawn_at']) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $file['name'], [
            'Content-Type' => 'application/octet-stream', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
