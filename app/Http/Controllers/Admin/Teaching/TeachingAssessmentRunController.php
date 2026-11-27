<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\TeachingCourseWork;
use App\Services\TeachingAssessmentRuns;
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Services\TeachingWorkSubmissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachingAssessmentRunController extends Controller
{
    public function show(Request $request, TeachingCourseWork $course_work, TeachingAssessmentRuns $runs, TeachingCourseWorkEntrySyncService $entries): JsonResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'progress_only' => ['sometimes', 'boolean'],
            'include_portal_completion' => ['sometimes', 'boolean']]);
        if ($request->boolean('progress_only')) {
            $mail = [];
            foreach ($course_work->status['assignment_mail']['batches'] ?? [] as $slot => $batch) {
                if (isset($data['date']) && $slot !== $data['date'] && $slot !== 'all') {
                    continue;
                }
                $mail[] = [
                    'date' => $slot === 'all' ? null : $slot,
                    'state' => $batch['state'], 'transport' => $batch['transport'],
                    'withdrawn' => filled($batch['withdrawn_at'] ?? null),
                    'counts' => array_count_values(array_column($batch['recipients'], 'state')),
                ];
            }

            $progress = $runs->progressForWork($course_work, $actor, $data['date'] ?? null) + ['mail' => $mail];
            if ($request->boolean('include_portal_completion') && $course_work->studentReleaseEnded()
                && (TeachingWorkSubmissions::method($course_work)['channel'] ?? null) === 'portal') {
                $progress['completed_portal_work'] = $entries->serializeWork($course_work);
            }

            return response()->json($progress)
                ->header('Cache-Control', 'private, no-store');
        }

        return response()->json(['run' => $runs->show($course_work, $actor, $data['date'] ?? null)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, TeachingCourseWork $course_work, TeachingAssessmentRuns $runs): JsonResponse
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $course_work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($course_work->teachingCourse, $actor);
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'approved' => ['required', 'accepted'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'template_points_snapshot' => ['required', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'],
            'expected_test_id' => ['present', 'nullable', 'integer', 'min:1'],
        ]);

        return response()->json(['run' => $runs->start($course_work, $actor, $data['request_id'], [
            'date' => $data['date'] ?? null,
            'points' => (float) $data['template_points_snapshot'],
            'mc_id' => isset($data['expected_test_id']) ? (int) $data['expected_test_id'] : null,
        ])], 202)->header('Cache-Control', 'private, no-store');
    }
}
