<?php

namespace App\Services;

use App\Models\TeachingCourseWork;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use JsonException;

class TeachingWorkFolderImport
{
    public function __construct(private TeachingWorkMarkdownImport $evaluations, private TeachingWorkDispatchImport $dispatches, private TeachingWorkProvisionImport $provisions) {}

    /** @param list<UploadedFile> $uploads
     * @return array<string, mixed>
     */
    public function parse(string $folder, string $documents, string $pdfPaths, array $uploads): array
    {
        try {
            $texts = json_decode($documents, true, 32, JSON_THROW_ON_ERROR);
            $paths = json_decode($pdfPaths, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->reject('Ungültige Ordnerdateiliste.');
        }
        if (! is_array($texts) || ! array_is_list($texts) || count($texts) > 130
            || ! is_array($paths) || ! array_is_list($paths) || count($paths) !== count($uploads)
            || strlen($documents) + array_sum(array_map(fn (UploadedFile $file): int => $file->getSize(), $uploads)) > 6 * 1024 * 1024) {
            $this->reject('Maximal 100 Auswertungen, 30 Versandprotokolle, 20 PDFs und insgesamt 6 MB pro Ordnerimport auswählen.');
        }
        $reports = [];
        $protocols = [];
        $provisions = [];
        $pdfs = [];
        $sources = [];
        $deadline = null;
        $workIdentity = null;
        foreach ($texts as $document) {
            if (! is_array($document) || ! is_string($document['path'] ?? null) || ! is_string($document['text'] ?? null)) {
                $this->reject('Ungültige Textdatei im Ordnerimport.');
            }
            $file = $this->classify($folder, $document['path']);
            if ($file['kind'] === 'deadline') {
                $metadata = $this->parseDeadline($document['text']);
                $workIdentity = ['work_id' => $metadata['work_id'], 'course_id' => $metadata['course_id']];
                $deadline = $metadata['at'] === null ? null : $metadata;
            } elseif ($file['kind'] === 'evaluation' && str_ends_with(mb_strtolower($file['name']), '.md')) {
                if (strlen($document['text']) > 262144) {
                    $this->reject($file['name'].': Auswertung überschreitet 256 KB.');
                }
                $reports[$file['name']] = $document['text'];
            } elseif ($file['kind'] === 'provision') {
                $provisions[] = $file + ['path' => $document['path'], 'report' => $this->provisions->parse($document['text']),
                    'text' => $document['text'], 'sha256' => hash('sha256', $document['text'])];
            } elseif (in_array($file['kind'], ['tasks', 'results'], true)) {
                try {
                    $report = $this->dispatches->parse($document['text']);
                } catch (ValidationException $exception) {
                    $this->reject(substr($document['path'], strlen($folder) + 1).': '.implode(' ', collect($exception->errors())->flatten()->all()));
                }
                if ($report['purpose'] !== $file['kind']) {
                    $this->reject($document['path'].': Ordnerbereich und Versandzweck widersprechen einander.');
                }
                if ($report['mode'] === 'test') {
                    continue;
                }
                $protocols[] = $file + ['path' => $document['path'], 'report' => $report, 'text' => $document['text'], 'sha256' => hash('sha256', $document['text'])];
            } else {
                $this->reject($document['path'].': Dateityp passt nicht zum Inhalt.');
            }
            $this->register($sources, $file['source'], hash('sha256', $document['text']));
        }
        foreach ($uploads as $index => $upload) {
            if (! is_string($paths[$index])) {
                $this->reject('Ungültiger PDF-Pfad.');
            }
            $file = $this->classify($folder, $paths[$index]);
            if ($file['kind'] !== 'evaluation' || ! str_ends_with(mb_strtolower($file['name']), '.pdf') || $file['name'] !== $upload->getClientOriginalName()) {
                $this->reject('PDF-Dateiname oder Ordnerbereich passt nicht zur Auswahl.');
            }
            $sha256 = hash_file('sha256', $upload->getRealPath());
            $this->register($sources, $file['source'], $sha256);
            $pdfs[] = ['name' => $file['name'], 'sha256' => $sha256];
        }
        if (count($reports) > 100 || count($protocols) > 30) {
            $this->reject('Maximal 100 Auswertungen und 30 Versandprotokolle pro Import auswählen.');
        }
        if ($pdfs !== [] && $reports === []) {
            $this->reject('Zu den Auswertungs-PDFs fehlen die zugehörigen Markdown-Auswertungen.');
        }
        usort($protocols, fn (array $first, array $second): int => strcmp($first['source'], $second['source']));

        return ['folder' => $folder, 'evaluation' => $reports !== [] ? $this->evaluations->parse($reports) : null,
            'protocols' => $protocols, 'provisions' => $provisions, 'deadline' => $deadline, 'work_identity' => $workIdentity, 'pdfs' => $pdfs, 'uploads' => $uploads, 'sources' => $sources];
    }

    /** @return array{work_id: int, course_id: int, at: ?string} */
    private function parseDeadline(string $text): array
    {
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        }
        try {
            $metadata = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->reject('Anlage_und_Quellen.json: Ungültige Fristmetadaten.');
        }
        if (! is_array($metadata) || ($metadata['Zeitzone'] ?? null) !== 'Europe/Vienna'
            || ! is_int($metadata['Schooltool_Arbeit_ID'] ?? null) || $metadata['Schooltool_Arbeit_ID'] < 1
            || ! is_int($metadata['Schooltool_Kurs_ID'] ?? null) || $metadata['Schooltool_Kurs_ID'] < 1) {
            $this->reject('Anlage_und_Quellen.json: Eindeutige Arbeits- und Kurskennung sowie Europe/Vienna erforderlich.');
        }
        if (($metadata['Abgabeschluss'] ?? null) === null) {
            if (isset($metadata['MitgeteilteAbgabefrist'])) {
                $this->reject('Anlage_und_Quellen.json: Zur mitgeteilten Frist fehlt der interne Prüfungsschluss.');
            }

            return ['work_id' => $metadata['Schooltool_Arbeit_ID'], 'course_id' => $metadata['Schooltool_Kurs_ID'], 'at' => null];
        }
        $deadline = $this->deadlineInstant($metadata['Abgabeschluss']);
        if (isset($metadata['MitgeteilteAbgabefrist']) && $this->deadlineInstant($metadata['MitgeteilteAbgabefrist']) > $deadline) {
            $this->reject('Anlage_und_Quellen.json: Die mitgeteilte Frist liegt nach dem Prüfungsschluss.');
        }

        return ['work_id' => $metadata['Schooltool_Arbeit_ID'], 'course_id' => $metadata['Schooltool_Kurs_ID'],
            'at' => $deadline->setTimezone(new DateTimeZone('Europe/Vienna'))->format('Y-m-d\TH:i:sP')];
    }

    private function deadlineInstant(mixed $value): DateTimeImmutable
    {
        if (! is_string($value) || ! preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00(?:Z|[+-]\d{2}:\d{2})\z/', $value)) {
            $this->reject('Anlage_und_Quellen.json: Prüfungsschluss benötigt Datum, Uhrzeit und explizite Zeitzone.');
        }
        $instant = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', str_replace('Z', '+00:00', $value));
        if ($instant === false || $instant->format('Y-m-d\TH:i:sP') !== str_replace('Z', '+00:00', $value)
            || (! str_ends_with($value, 'Z') && $instant->getOffset() !== $instant->setTimezone(new DateTimeZone('Europe/Vienna'))->getOffset())) {
            $this->reject('Anlage_und_Quellen.json: Ungültiger Zeitpunkt oder falscher Europe/Vienna-Offset.');
        }

        return $instant;
    }

    /** @param array<string, mixed> $bundle
     * @return array<string, string>
     */
    public function deadlineFields(TeachingCourseWork $work, array $bundle): array
    {
        $deadline = $bundle['deadline'] ?? null;
        $identity = $bundle['work_identity'] ?? $deadline;
        if ($identity !== null && ($identity['work_id'] !== $work->id || $identity['course_id'] !== $work->teaching_course_id)) {
            $this->reject('Anlage_und_Quellen.json: Prüfungsschluss gehört zu einer anderen Arbeit oder einem anderen Kurs.');
        }
        if ($deadline === null) {
            return [];
        }
        $instant = new DateTimeImmutable($deadline['at']);

        return ['finish_until_date' => $instant->format('Y-m-d'), 'finish_until_time' => $instant->format('H:i')];
    }

    /** @return array{source: string, name: string, kind: string, run: string} */
    private function classify(string $folder, string $path): array
    {
        if ($folder === '' || str_contains($folder, '/') || str_contains($folder, '\\') || str_contains($path, '\\')
            || preg_match('/[\x00-\x1f:]/u', $path) || strlen($path) > 1024 || ! str_starts_with($path, $folder.'/')) {
            $this->reject('Genau einen Leistungsfeststellungs- oder Übungsordner auswählen.');
        }
        $source = substr($path, strlen($folder) + 1);
        if (mb_strtolower($source) === 'anlage_und_quellen.json') {
            return ['source' => 'anlage_und_quellen.json', 'name' => 'Anlage_und_Quellen.json', 'kind' => 'deadline', 'run' => 'Frist'];
        }
        if (mb_strtolower($source) === 'schooltool-bereitstellung.json') {
            return ['source' => 'schooltool-bereitstellung.json', 'name' => 'Schooltool-Bereitstellung.json', 'kind' => 'provision', 'run' => 'Bereitstellung'];
        }
        if (in_array(mb_strtolower($source), ['schooltool-vorbereitung.json', 'schooltool-versandvorbereitung.json'], true)) {
            return ['source' => mb_strtolower($source), 'name' => $source, 'kind' => 'tasks', 'run' => 'Vorbereitung'];
        }
        if (preg_match('/\ABeurteilungen\/((?:(?:Beurteilung_|Gesamtuebersicht_Beurteilungen_).+|Gesamtübersicht|[^\/_]+_[^\/_]+)\.(?:md|pdf))\z/iu', $source, $matches)) {
            return ['source' => mb_strtolower($source), 'name' => $matches[1], 'kind' => 'evaluation', 'run' => ''];
        }
        if (preg_match('/\AVersand\/(Aufgaben|Ergebnisse)\/(Versand_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\/Versandprotokoll\.txt\z/iu', $source, $matches)) {
            return ['source' => mb_strtolower($source), 'name' => 'Versandprotokoll.txt', 'kind' => mb_strtolower($matches[1]) === 'aufgaben' ? 'tasks' : 'results', 'run' => $matches[2]];
        }
        $this->reject($path.': Keine unterstützte Importdatei im standardisierten Ordnerbereich.');
    }

    /** @param array<string, string> $sources */
    private function register(array &$sources, string $source, string $sha256): void
    {
        if (isset($sources[$source])) {
            $this->reject($source.': Datei mehrfach ausgewählt.');
        }
        $sources[$source] = $sha256;
    }

    /** @param array<string, mixed> $bundle
     * @return list<array<string, mixed>>
     */
    public function previewDispatches(TeachingCourseWork $work, array $bundle): array
    {
        $additions = $this->dispatches->participantAdditions($work, array_column($bundle['protocols'], 'report'));
        $previews = [];
        foreach ($bundle['protocols'] as $file) {
            $previews[] = ['source' => $file['source']] + $this->dispatches->preview($work, $file['report'], $file['sha256'],
                requireMatchingTitle: false, participantAdditions: $additions);
        }

        return $previews;
    }

    /** @param array<string, mixed> $bundle
     * @return list<array<string, mixed>>
     */
    public function previewProvisions(TeachingCourseWork $work, array $bundle): array
    {
        return array_map(fn (array $file): array => ['source' => $file['source']] + $this->provisions->preview($work, $file['report'], $file['sha256']), $bundle['provisions'] ?? []);
    }

    /** @param list<array<string, mixed>> $provisions
     * @param  list<array<string, mixed>>  $dispatches
     * @return list<array<string, mixed>>
     */
    public function availabilitySources(TeachingCourseWork $work, array $provisions, array $dispatches): array
    {
        $logs = $work->status['dispatch_logs'] ?? [];
        $sources = array_values(array_filter($work->status['provision_events'] ?? [], fn (array $event): bool => collect($logs)
            ->contains(fn (array $log): bool => ($log['origin'] ?? null) === 'provision_import' && $log['sha256'] === $event['log_sha256'])));
        foreach ($work->status['dispatch_notifications'] ?? [] as $notice) {
            if (($notice['purpose'] ?? 'results') === 'tasks' && ($notice['origin'] ?? null) === 'dispatch_import'
                && collect($logs)->contains(fn (array $log): bool => ($log['origin'] ?? null) === 'dispatch_import' && ($log['purpose'] ?? 'results') === 'tasks' && $log['sha256'] === $notice['log_sha256'])) {
                $sources[] = ['student_id' => $notice['student_id'], 'method' => 'email_dispatch', 'available_at' => $notice['sent_at'], 'log_sha256' => $notice['log_sha256']];
            }
        }
        foreach ($provisions as $preview) {
            if ($preview['can_import']) {
                $sources = array_merge($sources, $preview['records']);
            }
        }
        foreach ($dispatches as $preview) {
            if ($preview['purpose'] !== 'tasks' || ! $preview['can_import']) {
                continue;
            }
            foreach ($preview['rows'] as $row) {
                if ($row['accepted']) {
                    $sources[] = ['student_id' => $row['student_id'], 'method' => 'email_dispatch', 'available_at' => $row['sent_at'], 'log_sha256' => $preview['source_sha256']];
                }
            }
        }

        return $sources;
    }

    /** Call within the authorized work transaction and clean created files on rollback.
     * @param  array<string, mixed>  $bundle
     * @param  list<string>  $createdPaths
     * @return array{messages: list<string>, missing: list<string>}
     */
    public function apply(TeachingCourseWork $work, User $actor, array $bundle, array &$createdPaths): array
    {
        $messages = [];
        $deadlineFields = $this->deadlineFields($work, $bundle);
        if ($deadlineFields !== []) {
            $work->forceFill($deadlineFields);
            if ($work->isDirty(['finish_until_date', 'finish_until_time'])) {
                $work->save();
            }
            $messages[] = 'Prüfungsschluss aus Arbeitsordner übernommen: '.(new DateTimeImmutable($bundle['deadline']['at']))->format('d.m.Y H:i').' Uhr (Europe/Vienna).';
        }
        $missing = [];
        $dispatchPreviews = $this->previewDispatches($work, $bundle);
        foreach ($dispatchPreviews as $preview) {
            if (! $preview['can_import']) {
                $bad = collect($preview['rows'])->first(fn (array $row): bool => str_contains($row['status'], 'gesperrt'));
                $this->reject($preview['source'].': '.($bad['person'] ?? '').' '.($bad['status'] ?? 'Zuordnung ungeklärt').'. Keine Dateien wurden übernommen.');
            }
        }
        $this->dispatches->applyParticipantAdditions($work, $dispatchPreviews[0]['participant_additions'] ?? []);
        foreach ($bundle['provisions'] ?? [] as $file) {
            $preview = $this->provisions->preview($work, $file['report'], $file['sha256']);
            $this->provisions->apply($work, $preview, $file['text'], $createdPaths);
            $messages[] = 'Bereitstellung: '.count($preview['records']).' personenbezogene Verfügbarkeitsbelege geprüft; kein individueller Download bestätigt.';
        }
        $previous = $work->status['folder_import_sources'] ?? [];
        if ($bundle['evaluation']) {
            $report = $bundle['evaluation'];
            $preview = $this->evaluations->preview($work, $actor, $report, $bundle['pdfs']);
            foreach ($preview['rows'] as &$row) {
                if (! $row['student_id'] || str_contains($row['status'], 'gesperrt')) {
                    $this->reject($row['person'].' / '.$row['class'].': '.$row['status'].'. Keine Dateien wurden übernommen.');
                }
                $source = 'beurteilungen/'.mb_strtolower($row['source']);
                if ($row['status'] === 'Übernehmen' && ($previous[$source] ?? null) === $bundle['sources'][$source]) {
                    $row['status'] = 'Unverändert';
                }
            }
            unset($row);
            $changed = collect($preview['rows'])->where('status', 'Übernehmen')->count();
            if ($changed > 0) {
                $this->evaluations->apply($work, $preview);
            }
            $this->evaluations->storePdfs($work, $preview, $bundle['uploads'], $bundle['pdfs'], $createdPaths);
            $messages[] = 'Auswertungen: '.$changed.' Bewertungen übernommen; '.(count($preview['rows']) - $changed).' offen oder unverändert. '.count($bundle['pdfs']).' PDFs geprüft.';
            if (! $preview['pdf']) {
                $missing[] = 'Gesamtauswertung (PDF)';
            }
            $missingPersonal = collect($preview['rows'])->whereNull('pdf')->count();
            if ($missingPersonal > 0) {
                $missing[] = $missingPersonal.' persönliche Auswertungs-PDFs';
            }
        } else {
            $missing[] = 'Auswertungen';
        }
        foreach (['tasks' => 'Aufgabenversand', 'results' => 'Ergebnisbenachrichtigung'] as $purpose => $label) {
            $protocols = array_values(array_filter($bundle['protocols'], fn (array $file): bool => $file['kind'] === $purpose));
            if ($protocols === []) {
                $missing[] = $label;

                continue;
            }
            $newLogs = 0;
            $live = [];
            $tests = [];
            foreach ($protocols as $file) {
                try {
                    $preview = $this->dispatches->preview($work, $file['report'], $file['sha256'], requireMatchingTitle: false);
                } catch (ValidationException $exception) {
                    $this->reject($file['path'].': '.implode(' ', collect($exception->errors())->flatten()->all()));
                }
                if (! $preview['can_import']) {
                    $bad = collect($preview['rows'])->first(fn (array $row): bool => str_contains($row['status'], 'gesperrt'));
                    $this->reject($file['path'].': '.($bad['person'] ?? '').' '.($bad['status'] ?? 'Zuordnung ungeklärt').'. Keine Dateien wurden übernommen.');
                }
                $newLogs += (int) ! $preview['already_imported'];
                $directory = "teaching/work_dispatches/{$work->teachingCourse->school_id}/{$work->id}";
                $path = $directory.'/'.$file['sha256'].'.txt';
                if (! Storage::disk('local')->exists($path)) {
                    $createdPaths[] = $path;
                    if (! Storage::disk('local')->put($path, $file['text'])) {
                        $this->reject('Versandprotokoll konnte nicht gespeichert werden.');
                    }
                }
                $this->dispatches->apply($work, $preview, $file['sha256'], $file['run'].'_'.$file['name'], $path);
                foreach ($preview['rows'] as $row) {
                    if ($row['accepted']) {
                        $live[$row['student_id']] = true;
                    } elseif ($row['student_id'] && $row['mode'] === 'test') {
                        $tests[$row['student_id']] = true;
                    }
                }
            }
            $messages[] = $label.': '.count($protocols).' Protokolle geprüft, '.$newLogs.' neu gespeichert; '.count($live).' Personen mit bestätigtem Live-Versand, '.count($tests).' mit lokalem Test.';
        }
        if ($bundle['sources'] !== []) {
            $status = $work->status ?? [];
            $status['folder_import_sources'] = array_replace($previous, $bundle['sources']);
            $status['folder_imported_at'] = now()->toISOString();
            $work->status = $status;
            $work->save();
        } else {
            $messages[] = 'Keine unterstützten Importdateien gefunden.';
        }

        return ['messages' => $messages, 'missing' => $missing];
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['folder' => $message]);
    }
}
