<?php

namespace App\Services;

use App\Models\TeachingCourseWork;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use JsonException;

class TeachingWorkFolderImport
{
    public function __construct(private TeachingWorkMarkdownImport $evaluations, private TeachingWorkDispatchImport $dispatches) {}

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
        $pdfs = [];
        $sources = [];
        foreach ($texts as $document) {
            if (! is_array($document) || ! is_string($document['path'] ?? null) || ! is_string($document['text'] ?? null)) {
                $this->reject('Ungültige Textdatei im Ordnerimport.');
            }
            $file = $this->classify($folder, $document['path']);
            $this->register($sources, $file['source'], hash('sha256', $document['text']));
            if ($file['kind'] === 'evaluation' && str_ends_with(mb_strtolower($file['name']), '.md')) {
                if (strlen($document['text']) > 262144) {
                    $this->reject($file['name'].': Auswertung überschreitet 256 KB.');
                }
                $reports[$file['name']] = $document['text'];
            } elseif (in_array($file['kind'], ['tasks', 'results'], true)) {
                try {
                    $report = $this->dispatches->parse($document['text']);
                } catch (ValidationException $exception) {
                    $this->reject(substr($document['path'], strlen($folder) + 1).': '.implode(' ', collect($exception->errors())->flatten()->all()));
                }
                if ($report['purpose'] !== $file['kind']) {
                    $this->reject($document['path'].': Ordnerbereich und Versandzweck widersprechen einander.');
                }
                $protocols[] = $file + ['path' => $document['path'], 'report' => $report, 'text' => $document['text'], 'sha256' => hash('sha256', $document['text'])];
            } else {
                $this->reject($document['path'].': Dateityp passt nicht zum Inhalt.');
            }
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
            'protocols' => $protocols, 'pdfs' => $pdfs, 'uploads' => $uploads, 'sources' => $sources];
    }

    /** @return array{source: string, name: string, kind: string, run: string} */
    private function classify(string $folder, string $path): array
    {
        if ($folder === '' || str_contains($folder, '/') || str_contains($folder, '\\') || str_contains($path, '\\')
            || preg_match('/[\x00-\x1f:]/u', $path) || strlen($path) > 1024 || ! str_starts_with($path, $folder.'/')) {
            $this->reject('Genau einen Leistungsfeststellungs- oder Übungsordner auswählen.');
        }
        $source = substr($path, strlen($folder) + 1);
        if (preg_match('/\ABeurteilungen\/((?:(?:Beurteilung_|Gesamtuebersicht_Beurteilungen_).+|Gesamtübersicht)\.(?:md|pdf))\z/iu', $source, $matches)) {
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

    /** Call within the authorized work transaction and clean created files on rollback.
     * @param  array<string, mixed>  $bundle
     * @param  list<string>  $createdPaths
     * @return array{messages: list<string>, missing: list<string>}
     */
    public function apply(TeachingCourseWork $work, User $actor, array $bundle, array &$createdPaths): array
    {
        $messages = [];
        $missing = [];
        $previous = $work->status['folder_import_sources'] ?? [];
        if ($bundle['evaluation']) {
            $report = $bundle['evaluation'];
            $date = \DateTimeImmutable::createFromFormat('!d.m.Y', $report['date']);
            $this->assertIdentity($work, $report['title'], $date && $date->format('d.m.Y') === $report['date'] ? $date->format('Y-m-d') : '');
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
                    $preview = $this->dispatches->preview($work, $file['report'], $file['sha256']);
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
            $work->status = $status;
            $work->save();
        } else {
            $messages[] = 'Keine unterstützten Importdateien gefunden.';
        }

        return ['messages' => $messages, 'missing' => $missing];
    }

    private function assertIdentity(TeachingCourseWork $work, string $title, string $date): void
    {
        $normalize = fn (string $value): string => mb_strtolower(preg_replace('/\s+/u', ' ', trim(preg_replace('/\A(?:Übung|Leistungsfeststellung|Arbeit):\s*/iu', '', trim($value)))));
        if ($date === '' || $work->date_for_all_groups?->format('Y-m-d') !== $date || $normalize((string) $work->title) !== $normalize($title)) {
            $this->reject('Titel oder Datum der Auswertungen passt nicht zur gespeicherten Arbeit.');
        }
        if ($work->teachingCourse->teachingCourseWorks()->whereDate('date_for_all_groups', $date)->get(['id', 'title'])
            ->filter(fn (TeachingCourseWork $candidate): bool => $normalize((string) $candidate->title) === $normalize($title))->count() !== 1) {
            $this->reject('Mehrere Arbeiten mit diesem Titel und Datum – Zuordnung nicht eindeutig.');
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['folder' => $message]);
    }
}
