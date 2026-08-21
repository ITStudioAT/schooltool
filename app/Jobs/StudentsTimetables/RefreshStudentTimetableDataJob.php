<?php

namespace App\Jobs\StudentsTimetables;

use App\Models\StudentTimetableDataRefresh;
use App\Models\User;
use App\Services\StudentsTimetables\StudentTimetableStudySelectionRefreshService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class RefreshStudentTimetableDataJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $dataRefreshId,
    ) {
        $this->onQueue('imports');
    }

    public function handle(StudentTimetableStudySelectionRefreshService $refreshService): void
    {
        $dataRefresh = StudentTimetableDataRefresh::query()->find($this->dataRefreshId);

        if (! $dataRefresh || ! in_array($dataRefresh->status, ['queued', 'running'], true)) {
            return;
        }

        $user = User::query()
            ->whereKey($dataRefresh->user_id)
            ->where('school_id', $dataRefresh->school_id)
            ->first();

        if (! $user) {
            throw new RuntimeException('Der Benutzer für die Datenaktualisierung wurde nicht gefunden.');
        }

        $dataRefresh->update([
            'status' => 'running',
            'processed_students' => 0,
            'study_selections_updated' => 0,
            'course_results_updated' => 0,
            'error_message' => null,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        $summary = $refreshService->refreshForUserWithProgress(
            $user,
            (int) $dataRefresh->schoolyear_id,
            function (array $progress) use ($dataRefresh): void {
                StudentTimetableDataRefresh::query()
                    ->whereKey($dataRefresh->id)
                    ->where('school_id', $dataRefresh->school_id)
                    ->where('schoolyear_id', $dataRefresh->schoolyear_id)
                    ->where('status', 'running')
                    ->update([
                        'total_students' => $progress['total_students'],
                        'processed_students' => $progress['processed_students'],
                        'study_selections_updated' => $progress['study_selections_updated'],
                        'course_results_updated' => $progress['course_results_updated'],
                        'updated_at' => now(),
                    ]);
            },
        );

        $dataRefresh->update([
            'status' => 'completed',
            'total_students' => $summary['total_students'],
            'processed_students' => $summary['processed_students'],
            'study_selections_updated' => $summary['study_selections_updated'],
            'course_results_updated' => $summary['course_results_updated'],
            'finished_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        StudentTimetableDataRefresh::query()
            ->whereKey($this->dataRefreshId)
            ->whereIn('status', ['queued', 'running'])
            ->update([
                'status' => 'failed',
                'error_message' => 'Die Datenaktualisierung konnte nicht abgeschlossen werden.',
                'finished_at' => now(),
            ]);
    }
}
