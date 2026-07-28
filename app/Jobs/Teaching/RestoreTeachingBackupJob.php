<?php

namespace App\Jobs\Teaching;

use App\Models\TeachingBackupRestoreRun;
use App\Services\TeachingBackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use JsonException;
use Throwable;

class RestoreTeachingBackupJob implements ShouldQueue
{
    use Queueable;

    public const TIMEOUT_SECONDS = 600;

    public int $tries = 2;

    public int $timeout = self::TIMEOUT_SECONDS;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $restoreRunId,
        public int $schoolId,
        public int $schoolyearId,
    ) {
        $this->onQueue('maintenance');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('teaching-backup-restore:'.$this->schoolId.':'.$this->schoolyearId))
                ->shared()
                ->releaseAfter(60)
                ->expireAfter(3600),
        ];
    }

    public function handle(TeachingBackupService $service): void
    {
        $run = $this->restoreRun();

        if (! $run || ! in_array($run->status, ['pending', 'running'], true)) {
            return;
        }

        $run->update([
            'status' => 'running',
            'progress_current' => 1,
            'progress_total' => 3,
            'message' => 'Wiederherstellung wird geprüft.',
        ]);

        $backup = $run->backup;
        $user = $run->user;

        if (! $backup || ! $user) {
            $this->markFailed($run, 'Wiederherstellung fehlgeschlagen.', 'missing_restore_context');

            return;
        }

        try {
            $preRestoreBackup = $service->createForUser($user, 'pre_restore');
            $service->pruneBackupRetention((int) $user->school_id, (int) $user->schoolyear_id);
            $run->update([
                'pre_restore_backup_id' => $preRestoreBackup->id,
                'progress_current' => 2,
                'message' => 'Sicherheitskopie vor Wiederherstellung erstellt.',
            ]);

            $result = $service->restoreFull($backup, $user);
        } catch (JsonException) {
            $this->markFailed($run, 'Datensicherung kann nicht gelesen werden', 'invalid_backup');

            return;
        }

        $result['pre_restore_backup_id'] = $preRestoreBackup->id;

        $run->update([
            'status' => 'completed',
            'progress_current' => 3,
            'result' => $result,
            'finished_at' => now(),
            'message' => 'Wiederherstellung abgeschlossen.',
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $run = $this->restoreRun();

        if (! $run || ! in_array($run->status, ['pending', 'running'], true)) {
            return;
        }

        $this->markFailed($run, 'Wiederherstellung fehlgeschlagen.', 'unexpected_error');
    }

    private function restoreRun(): ?TeachingBackupRestoreRun
    {
        return TeachingBackupRestoreRun::query()
            ->with(['backup', 'user'])
            ->where('id', $this->restoreRunId)
            ->where('school_id', $this->schoolId)
            ->where('schoolyear_id', $this->schoolyearId)
            ->first();
    }

    private function markFailed(TeachingBackupRestoreRun $run, string $message, string $reason): void
    {
        $run->update([
            'status' => 'failed',
            'finished_at' => now(),
            'result' => [
                'failed' => true,
                'reason' => $reason,
                'message' => $message,
            ],
            'message' => $message,
        ]);
    }
}
