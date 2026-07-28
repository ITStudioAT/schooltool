<?php

namespace App\Jobs\StudentsTimetables;

use App\Models\TimetableImport;
use App\Services\StudentsTimetables\TimetableImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessTimetableImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public int $timetableImportId,
    ) {
        $this->onQueue('imports');
    }

    public function handle(TimetableImportService $service): void
    {
        $import = TimetableImport::find($this->timetableImportId);

        if (! $import || ! in_array($import->import_status, ['pending', 'running'], true)) {
            return;
        }

        $service->processImport($import);
    }

    public function failed(Throwable $exception): void
    {
        TimetableImport::whereKey($this->timetableImportId)->update([
            'import_status' => 'failed',
            'import_error' => mb_substr($exception->getMessage(), 0, 1000),
            'import_message' => 'Import fehlgeschlagen.',
            'finished_at' => now(),
        ]);
    }
}
