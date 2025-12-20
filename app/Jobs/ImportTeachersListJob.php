<?php

namespace App\Jobs;

use App\Events\TeachersListImportFinishedEvent;
use App\Models\Teacher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Spatie\SimpleExcel\SimpleExcelReader;

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
        $fullPath = storage_path($this->path);
        $reader = SimpleExcelReader::create($fullPath);

        // Hole die erste Zeile (Header)
        $headers = $reader->getHeaders();

        // Validiere die Header und hole die Mapping-Informationen
        $headerMapping = $this->validateAndMapHeaders($headers);

        if ($headerMapping === false) {

            broadcast(new TeachersListImportFinishedEvent(
                500,
                $this->user->id,
                'Die Überschriften der Excel-Datei sind nicht korrekt! (Kurz, Nachname, Vorname, Email)',
                []
            ));

            return;
        }

        $school_id = $this->user->school_id;

        $created = 0;
        $updated = 0;
        $processedTeacherIds = [];

        $reader->getRows()->each(function (array $row) use ($headerMapping, $school_id, &$created, &$updated, &$processedTeacherIds) {
            // Mappe die Daten auf die Standard-Header
            $mappedRow = [];
            foreach ($headerMapping as $originalHeader => $standardHeader) {
                $mappedRow[$standardHeader] = $row[$originalHeader] ?? null;
            }

            $teacher = Teacher::updateOrCreate(
                [
                    'school_id' => $school_id,
                    'email' => $mappedRow['Email'],
                ],
                [
                    'short' => strtoupper($mappedRow['Kurz']),
                    'last_name' => $mappedRow['Nachname'],
                    'first_name' => $mappedRow['Vorname'],
                ]
            );

            // Speichere die ID des verarbeiteten Lehrers
            $processedTeacherIds[] = $teacher->id;

            if ($teacher->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        });

        // Lösche alle Lehrer dieser Schule, die nicht in der Excel-Datei waren
        /*
        $deleted = Teacher::where('school_id', $school_id)
            ->whereNotIn('id', $processedTeacherIds)
            ->delete();
            */
        $deleted = 0;

        broadcast(new TeachersListImportFinishedEvent(
            200,
            $this->user->id,
            'Die Lehrerliste (Excel) wurde erfolgreich importiert (' . $created . ' neu, ' . $updated . ' geprüft, ' . $deleted . ' gelöscht)',
            ['created' => $created, 'updated' => $updated, 'deleted' => $deleted]
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
