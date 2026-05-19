<?php

namespace App\Jobs\StudentsTimetables;

use App\Models\StudentTimetableRecognitionImport;
use App\Services\StudentsTimetables\RecognitionImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessRecognitionCsvImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public int $recognitionImportId,
    ) {}

    public function handle(RecognitionImportService $service): void
    {
        $import = StudentTimetableRecognitionImport::find($this->recognitionImportId);

        if (! $import || ! in_array($import->import_status, ['pending', 'running'], true)) {
            return;
        }

        $service->processImport($import);
    }

    public function failed(Throwable $exception): void
    {
        StudentTimetableRecognitionImport::whereKey($this->recognitionImportId)->update([
            'import_status' => 'failed',
            'import_message' => mb_substr($exception->getMessage(), 0, 1000),
        ]);
    }
}
