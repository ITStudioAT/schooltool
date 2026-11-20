<?php

namespace App\Http\Controllers\Admin\Teaching;

use App\Http\Controllers\Controller;
use App\Models\Import116;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\TeachingCourseWorkEntrySyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkGradeVisibilityController extends Controller
{
    public function update(Request $request, TeachingCourseWork $course_work, TeachingCourseWorkEntrySyncService $entries): JsonResponse
    {
        $actor = $this->actor($course_work);
        $data = $request->validate([
            'visible' => ['required', 'boolean'],
            'expected_changed_at' => ['present', 'nullable', 'string', 'max:45'],
        ]);

        $work = DB::transaction(function () use ($course_work, $actor, $data): TeachingCourseWork {
            $work = TeachingCourseWork::query()->lockForUpdate()->findOrFail($course_work->id);
            $visible = (bool) $data['visible'];
            if ($visible === $work->gradesVisibleToStudents()) {
                return $work;
            }
            $status = $work->status ?? [];
            abort_unless(($status['grade_visibility']['changed_at'] ?? null) === $data['expected_changed_at'],
                409, 'Die Ergebnisfreigabe hat sich geändert. Bitte die Arbeit erneut laden.');
            if ($visible) {
                abort_if($this->studentsWithResults($work) === [], 409, 'Bitte zuerst mindestens eine persönliche Bewertung speichern.');
            }
            $changedAt = now()->toISOString();
            $visibility = $status['grade_visibility'] ?? [];
            if (! $visible && ! empty($visibility['student_notice']['student_ids'])) {
                $visibility['student_notice'] = ['student_ids' => [], 'changed_at' => $changedAt, 'changed_by' => $actor->id];
            }
            $status['grade_visibility'] = [
                ...$visibility,
                'visible' => $visible,
                'changed_at' => $changedAt,
                'changed_by' => $actor->id,
            ];
            $work->update(['status' => $status]);

            return $work;
        });

        return response()->json(['data' => $entries->serializeWork($work)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function notice(Request $request, TeachingCourseWork $course_work, TeachingCourseWorkEntrySyncService $entries): JsonResponse
    {
        $actor = $this->actor($course_work);
        $data = $request->validate([
            'publish' => ['required', 'boolean'],
            'expected_changed_at' => ['present', 'nullable', 'string', 'max:45'],
            'expected_notice_at' => ['present', 'nullable', 'string', 'max:45'],
        ]);

        $work = DB::transaction(function () use ($course_work, $actor, $data): TeachingCourseWork {
            $work = TeachingCourseWork::query()->lockForUpdate()->findOrFail($course_work->id);
            $status = $work->status ?? [];
            $visibility = $status['grade_visibility'] ?? ['visible' => true];
            abort_unless($work->gradesVisibleToStudents() && ($visibility['changed_at'] ?? null) === $data['expected_changed_at'],
                409, 'Bitte zuerst die aktuellen Bewertungen freigeben.');
            $recipients = $data['publish'] ? $this->studentsWithResults($work) : [];
            abort_if($data['publish'] && $recipients === [], 409, 'Es sind keine bewerteten Schüler:innen für die Verständigung vorhanden.');
            $notice = $visibility['student_notice'] ?? [];
            if (($notice['student_ids'] ?? []) === $recipients) {
                return $work;
            }
            abort_unless(($notice['changed_at'] ?? null) === $data['expected_notice_at'], 409,
                'Die Verständigung wurde inzwischen geändert. Bitte die Arbeit erneut laden.');
            $visibility['student_notice'] = ['student_ids' => $recipients, 'changed_at' => now()->toISOString(), 'changed_by' => $actor->id];
            $status['grade_visibility'] = $visibility;
            $work->update(['status' => $status]);

            return $work;
        });

        return response()->json(['data' => $entries->serializeWork($work)])
            ->header('Cache-Control', 'private, no-store');
    }

    /** @return list<int> */
    private function studentsWithResults(TeachingCourseWork $work): array
    {
        $course = $work->teachingCourse;
        $enrolled = $course->teachingCourseStudents()->whereNull('canceled_at');
        $direct = (clone $enrolled)->whereNotNull('user_id')->select('user_id');
        $imported = Import116::query()->where('school_id', $course->school_id)->where('schoolyear_id', $course->schoolyear_id)
            ->whereIn('id', (clone $enrolled)->whereNull('user_id')->select('import116_id'))->select('user_id');

        return $work->teachingCourseStudentEntries()
            ->where('teaching_course_id', $work->teaching_course_id)
            ->where(fn ($query) => $query->whereIn('user_id', $direct)->orWhereIn('user_id', $imported))
            ->orderBy('user_id')->get(['user_id', 'grade'])
            ->filter(fn (TeachingCourseStudentEntry $entry): bool => $work->hasResultForEntry($entry))
            ->pluck('user_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }

    private function actor(TeachingCourseWork $work): User
    {
        $actor = $this->userHasRole(['admin', 'teaching_admin', 'teacher']);
        abort_unless($actor && $work->teachingCourse, 403);
        $this->authorizeTeachingCourseAccess($work->teachingCourse, $actor);

        return $actor;
    }
}
