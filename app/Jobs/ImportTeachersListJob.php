<?php

namespace App\Jobs;

use App\Events\TeachersListImportFinishedEvent;
use App\Models\Teacher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use Spatie\SimpleExcel\SimpleExcelReader;
use Throwable;

class ImportTeachersListJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public $user, public string $path)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $fullPath = storage_path($this->path);
            [$headers, $rows] = $this->readRowsWithHeaders($fullPath);

            // Validiere die Header und hole die Mapping-Informationen
            $headerMapping = $this->validateAndMapHeaders($headers);

            if ($headerMapping === false) {
                $this->broadcastFailed('Die Überschriften der Excel-Datei sind nicht korrekt! (Kurz, Nachname, Vorname, Email)');
                return;
            }

            $school_id = $this->user->school_id;

            $created = 0;
            $updated = 0;
            $processedTeacherIds = [];

            foreach ($rows as $row) {
                $mappedRow = [];
                foreach ($headerMapping as $originalHeader => $standardHeader) {
                    $mappedRow[$standardHeader] = $row[$originalHeader] ?? null;
                }

                $email = trim((string) ($mappedRow['Email'] ?? ''));
                if ($email === '') {
                    continue;
                }

                $teacher = Teacher::updateOrCreate(
                    [
                        'school_id' => $school_id,
                        'email' => $email,
                    ],
                    [
                        'short' => strtoupper(trim((string) ($mappedRow['Kurz'] ?? ''))),
                        'last_name' => trim((string) ($mappedRow['Nachname'] ?? '')),
                        'first_name' => trim((string) ($mappedRow['Vorname'] ?? '')) ?: null,
                    ]
                );

                $processedTeacherIds[] = $teacher->id;

                if ($teacher->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }

            $deleted = 0;

            broadcast(new TeachersListImportFinishedEvent(
                200,
                $this->user->id,
                'Die Lehrerliste (Excel) wurde erfolgreich importiert (' . $created . ' neu, ' . $updated . ' geprüft, ' . $deleted . ' gelöscht)',
                ['created' => $created, 'updated' => $updated, 'deleted' => $deleted]
            ));
        } catch (Throwable $e) {
            report($e);
            $message = str_contains(strtolower($e->getMessage()), 'no readers supporting the given type: xls')
                ? 'Das Dateiformat .xls wird beim Lehrerlisten-Import nicht direkt unterstützt. Bitte als .xlsx speichern und erneut importieren.'
                : 'Die Lehrerliste konnte nicht importiert werden.';
            $this->broadcastFailed($message);
        }
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<string, mixed>>}
     */
    private function readRowsWithHeaders(string $fullPath): array
    {
        try {
            $reader = SimpleExcelReader::create($fullPath);
            $headers = $reader->getHeaders();
            $rows = $reader->getRows()->toArray();
            return [$headers, $rows];
        } catch (UnsupportedTypeException $e) {
            $ext = strtolower((string) pathinfo($fullPath, PATHINFO_EXTENSION));
            if ($ext !== 'xls') {
                throw $e;
            }

            return $this->readLegacyXls($fullPath);
        }
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<string, mixed>>}
     */
    private function readLegacyXls(string $fullPath): array
    {
        $spreadsheet = SpreadsheetIOFactory::load($fullPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, false);

        if (empty($rawRows)) {
            return [[], []];
        }

        $headers = array_map(fn ($value) => trim((string) $value), array_shift($rawRows) ?: []);

        $rows = [];
        foreach ($rawRows as $rowValues) {
            $assoc = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = $rowValues[$index] ?? null;
            }
            $rows[] = $assoc;
        }

        return [$headers, $rows];
    }

    private function broadcastFailed(string $message): void
    {
        broadcast(new TeachersListImportFinishedEvent(
            500,
            $this->user->id,
            $message,
            []
        ));
    }

    /**
     * Validiert die Excel-Header und gibt das Mapping zurück
     *
     * @return array|false Array mit Mapping von Original-Header zu Standard-Header, oder false bei Fehler
     */
    protected function validateAndMapHeaders(array $headers): array|false
    {
        $requiredColumns = [
            'Kurz' => ['kurz', 'short', 'kurzbezeichnung', 'kürzel', 'kuerzel'],
            'Nachname' => ['nachname', 'last_name', 'lastname', 'name', 'surname'],
            'Vorname' => ['vorname', 'first_name', 'firstname', 'givenname'],
            'Email' => ['email', 'e-mail', 'mail', 'e_mail'],
        ];

        $normalizedHeaders = array_map('strtolower', $headers);
        $normalizedHeaders = array_map('trim', $normalizedHeaders);

        $headerMapping = [];
        $missingColumns = [];

        foreach ($requiredColumns as $standardName => $variations) {
            $found = false;
            foreach ($normalizedHeaders as $index => $normalizedHeader) {
                if (in_array($normalizedHeader, $variations)) {
                    // Mappe den Original-Header auf den Standard-Namen
                    $headerMapping[$headers[$index]] = $standardName;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $missingColumns[] = $standardName;
            }
        }

        if (!empty($missingColumns)) {
            return false;
        }

        return $headerMapping;
    }
}
