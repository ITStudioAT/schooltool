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
        $choice = $request->validate([
            'channel' => ['present', 'nullable', 'in:portal,email'],
            'expected' => ['present', 'nullable', 'in:portal,email'],
        ]);

        $work = DB::transaction(function () use ($course_work, $choice, $actor): TeachingCourseWork {
            $work = TeachingCourseWork::query()->with('teachingCourse')->lockForUpdate()->findOrFail($course_work->id);
            $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);
            $status = $work->status ?? [];
            $current = $status['provision_method']['channel'] ?? null;
            if ($current === $choice['channel']) {
                return $work;
            }
            if ($current !== $choice['expected']) {
                throw new HttpResponseException(response()->json(['current' => $current,
                    'message' => 'Der Bereitstellungsweg wurde inzwischen geändert. Bitte prüfe die aktuelle Auswahl.'], 409));
            }
            if ($choice['channel'] === null) {
                unset($status['provision_method']);
            } else {
                $status['provision_method'] = ['channel' => $choice['channel']];
            }
            $work->update(['status' => $status]);

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
            'channel' => ['present', 'nullable', 'in:portal,email'],
            'email' => ['required_if:channel,email', 'nullable', 'email:filter', 'max:254'],
            'expected' => ['present', 'nullable', 'array:channel,email'],
        ]);

        $work = DB::transaction(function () use ($course_work, $data, $actor): TeachingCourseWork {
            $work = TeachingCourseWork::query()->with('teachingCourse')->lockForUpdate()->findOrFail($course_work->id);
            $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);
            $current = TeachingWorkSubmissions::method($work);
            $method = $data['channel'] === null ? null : ['channel' => $data['channel'], 'email' => $data['channel'] === 'email' ? $data['email'] : null];
            if ($current === $method) {
                return $work;
            }
            abort_unless($current === null ? $data['expected'] === null : $current == $data['expected'], 409,
                'Der Abgabeweg wurde inzwischen geändert. Bitte öffne die Arbeit erneut.');
            $status = $work->status ?? [];
            if ($method === null) {
                unset($status['submission_method']);
            } else {
                $status['submission_method'] = $method;
            }
            $work->update(['status' => $status]);
            app(TeachingWorkStudentDistribution::class)->signalDeadlineChangeAfterCommit($work);

            return $work;
        });

        return response()->json(['data' => $entries->serializeWork($work)])->header('Cache-Control', 'private, no-store');
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
