<?php

namespace App\Services;

use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Support\PrivateImportSourceFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeachingWorkMarkdownImport
{
    public function __construct(private TeachingCourseStudentEntryService $entries) {}

    public static function identity(string $name, string $class): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name.' / '.$class)));
    }

    /** @return array<string, list<array{student_id: int, email: string}>> */
    public function courseIdentities(TeachingCourse $course): array
    {
        $identities = [];
        $students = $course->teachingCourseStudents()->whereNull('canceled_at')->with(['user', 'import116'])->get();
        foreach ($students as $student) {
            $user = $student->user;
            if (! $user || (int) $user->school_id !== (int) $course->school_id) {
                continue;
            }
            $import = $student->import116;
            $usesCurrentImport = $import
                && (int) $import->school_id === (int) $course->school_id
                && (int) $import->schoolyear_id === (int) $course->schoolyear_id
                && ((int) $import->user_id === (int) $user->id || (int) $user->import116_id === (int) $import->id);
            $name = $usesCurrentImport ? $import->first_name.' '.$import->last_name : $user->first_name.' '.$user->last_name;
            $class = $usesCurrentImport ? $import->class : $user->schoolclass;
            $identities[self::identity($name, (string) $class)][] = [
                'student_id' => (int) $user->id,
                'email' => mb_strtolower(trim((string) ($usesCurrentImport && $import->email ? $import->email : $user->email))),
            ];
        }

        return $identities;
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['files' => $message]);
    }

    private function number(string $value): float
    {
        if (! preg_match('/\A\d+(?:[,.]\d{1,2})?\z/', trim($value))) {
            $this->reject('Ungültige Punktzahl in der Auswertung.');
        }

        return (float) str_replace(',', '.', trim($value));
    }

    /** @param array<string, string> $files */
    public function parse(array $files): array
    {
        foreach ($files as $text) {
            if (str_contains($text, '| Person / Klasse | Abgabestatus | Erreichte Punkte | Maximale Punkte | Bewertungsstatus |')) {
                return $this->parseCompactReports($files);
            }
        }
        $overview = null;
        $details = [];
        foreach ($files as $filename => $text) {
            $text = str_replace(["\r\n", "\r"], "\n", ltrim($text, "\xEF\xBB\xBF"));
            if (! mb_check_encoding($text, 'UTF-8') || str_contains($text, "\0") ||
                ! preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $text, $document) ||
                ! preg_match('/^title: "([^"\n]+)"$/m', $document[1], $title) ||
                ! preg_match('/^fach: "([^"\n]+)"$/m', $document[1], $subject) ||
                ! preg_match('/Maximal sind \*\*([\d,.]+) Punkte\*\*|Maximal ([\d,.]+) Punkte:/', $document[2], $maximum)) {
                $this->reject("{$filename}: Unbekanntes Markdown-Auswertungsformat.");
            }
            $max = $this->number($maximum[1] !== '' ? $maximum[1] : $maximum[2]);
            if ($max <= 0) {
                $this->reject("{$filename}: Maximale Punktzahl fehlt.");
            }
            $metadata = explode(' | ', $subject[1]);
            $date = end($metadata);
            if (! preg_match('/\A\d{2}\.\d{2}\.\d{4}\z/', $date)) {
                $this->reject("{$filename}: Datum fehlt.");
            }
            $assignment = trim(explode(':', $title[1], 2)[1] ?? '');
            if (str_starts_with($title[1], 'Gesamtübersicht:')) {
                if ($overview !== null || ! preg_match('/\| Person \/ Klasse \| Abgabe \| Bewertung \| MC \/ ([\d,.]+) \| E-Mail \/ ([\d,.]+) \| Gesamt \/ ([\d,.]+) \|/', $document[2], $header)) {
                    $this->reject('Genau eine Gesamtübersicht mit der bekannten Punktetabelle auswählen.');
                }
                if (abs($this->number($header[3]) - $max) > 0.001 || abs($this->number($header[1]) + $this->number($header[2]) - $max) > 0.001) {
                    $this->reject('Die maximalen Punkte der Übersicht widersprechen einander.');
                }
                $rows = [];
                foreach (explode("\n", $document[2]) as $line) {
                    if (! preg_match('/^\| (.+?) \/ ([^|]+) \| (Vorhanden|Fehlt) \| (Beurteilt|Offen) \| ([^|]+) \| ([^|]+) \| ([^|]+) \|$/', $line, $row)) {
                        continue;
                    }
                    $points = in_array($row[7], ['—', 'offen'], true) ? null : $this->number($row[7]);
                    if ($points === null) {
                        if ($row[3] !== 'Fehlt' || $row[4] !== 'Offen' || $row[5] !== $row[7] || $row[6] !== $row[7]) {
                            $this->reject('Offene Abgabe mit widersprüchlichen Punkten.');
                        }
                    } elseif ($row[3] !== 'Vorhanden' || $row[4] !== 'Beurteilt' || $points > $max ||
                        $this->number($row[5]) > $this->number($header[1]) || $this->number($row[6]) > $this->number($header[2]) ||
                        abs($this->number($row[5]) + $this->number($row[6]) - $points) > 0.001) {
                        $this->reject('Gesamtpunkte und Teilpunkte stimmen nicht überein.');
                    }
                    $key = self::identity($row[1], $row[2]);
                    if (isset($rows[$key])) {
                        $this->reject('Eine Person/Klasse steht mehrfach in der Übersicht.');
                    }
                    $rows[$key] = ['person' => $row[1], 'class' => $row[2], 'points' => $points];
                }
                $overview = ['title' => $assignment, 'date' => $date, 'maximum' => $max, 'source' => $filename, 'rows' => $rows];
            } elseif (str_starts_with($title[1], 'Beurteilung:')) {
                $surnameFirst = count($metadata) === 3 && preg_match('/\A(.+) \/ ([^\/]+)\z/u', $metadata[0], $personAndClass);
                if ($surnameFirst) {
                    $person = $personAndClass[1];
                    $class = $personAndClass[2];
                } elseif (count($metadata) === 4 && str_starts_with($metadata[1], 'Klasse ')) {
                    $person = $metadata[0];
                    $class = substr($metadata[1], 7);
                } else {
                    $this->reject("{$filename}: Kein unterstützter Beurteilungsbericht.");
                }
                $filenameParts = explode('_', substr(pathinfo($filename, PATHINFO_FILENAME), strlen('Beurteilung_')));
                if (! str_starts_with($filename, 'Beurteilung_') || count($filenameParts) !== 2 || trim($filenameParts[0]) === '' || trim($filenameParts[1]) === '' ||
                    self::identity($surnameFirst ? $filenameParts[0].' '.$filenameParts[1] : $filenameParts[1].' '.$filenameParts[0], '') !== self::identity($person, '')) {
                    $this->reject("{$filename}: Dateiname und Person im Bericht passen nicht zusammen.");
                }
                $key = self::identity($person, $class);
                if (isset($details[$key])) {
                    $this->reject('Mehrere Einzelbeurteilungen für dieselbe Person/Klasse.');
                }
                $points = null;
                if (preg_match('/\*\*Gesamt: ([\d,.]+) von ([\d,.]+) Punkten\.\*\* MC: ([\d,.]+) von ([\d,.]+); E-Mail: ([\d,.]+) von ([\d,.]+)\./', $document[2], $result)) {
                    $points = $this->number($result[1]);
                    if (abs($this->number($result[2]) - $max) > 0.001 || abs($this->number($result[3]) + $this->number($result[5]) - $points) > 0.001 ||
                        abs($this->number($result[4]) + $this->number($result[6]) - $max) > 0.001 || $points > $max ||
                        $this->number($result[3]) > $this->number($result[4]) || $this->number($result[5]) > $this->number($result[6])) {
                        $this->reject("{$filename}: Widersprüchliches Gesamtergebnis.");
                    }
                } elseif (! str_contains($document[2], '## Abgabe offen') ||
                    (! str_contains($document[2], '**Keine Gesamtsumme:**') && ! str_contains($document[2], '**Keine abschließende Gesamtsumme.**'))) {
                    $this->reject("{$filename}: Bekanntes Ergebnis oder offene Abgabe fehlt.");
                }
                $comment = $points !== null ? trim($result[0]) : '';
                if ($points !== null && (! str_contains($document[2], '## Ergebnis') || mb_strlen($comment) > 1024)) {
                    $this->reject("{$filename}: Ergebnis fehlt oder Kommentar überschreitet 1024 Zeichen.");
                }
                $details[$key] = ['title' => $assignment, 'date' => $date, 'maximum' => $max, 'points' => $points, 'comment' => $comment, 'source' => $filename,
                    'course_identity' => self::identity($filenameParts[1].' '.$filenameParts[0], $class)];
            } else {
                $this->reject("{$filename}: Kein unterstützter Beurteilungsbericht.");
            }
        }
        if (! $overview || ! $overview['rows'] || count($overview['rows']) !== count($details)) {
            $this->reject('Gesamtübersicht und zugehörige Einzelbeurteilungen vollständig auswählen.');
        }
        $courseRows = [];
        foreach ($overview['rows'] as $key => $row) {
            $detail = $details[$key] ?? null;
            if (! $detail || $detail['title'] !== $overview['title'] || $detail['date'] !== $overview['date'] ||
                $detail['maximum'] !== $overview['maximum'] || $detail['points'] !== $row['points']) {
                $this->reject('Übersicht und Einzelbeurteilung widersprechen einander: '.$row['person'].' / '.$row['class']);
            }
            $row['comment'] = $detail['comment'];
            $row['source'] = $detail['source'];
            if (isset($courseRows[$detail['course_identity']])) {
                $this->reject('Mehrere Einzelbeurteilungen für dieselbe Person/Klasse.');
            }
            $courseRows[$detail['course_identity']] = $row;
        }
        $overview['rows'] = $courseRows;

        return $overview;
    }

    /** @param array<string, string> $files */
    private function parseCompactReports(array $files): array
    {
        $documents = [];
        $overview = null;
        foreach ($files as $filename => $text) {
            $text = str_replace(["\r\n", "\r"], "\n", preg_replace('/\A\xEF\xBB\xBF/', '', $text));
            if (! mb_check_encoding($text, 'UTF-8') || str_contains($text, "\0")
                || ! preg_match('/\A---\n(.*?)\n---\n(.*)\z/su', $text, $document)
                || ! preg_match('/^title: "([^"\n]+)"$/m', $document[1], $title)
                || ! preg_match('/^fach: "([^"\n]+)"$/m', $document[1], $subject)) {
                $this->reject("{$filename}: Unbekanntes Markdown-Auswertungsformat.");
            }
            $documents[$filename] = ['title' => $title[1], 'subject' => $subject[1], 'body' => $document[2]];
            if (str_starts_with($title[1], 'Gesamtübersicht:')) {
                if ($overview !== null || ! preg_match('/ · Leistungsfeststellung vom (\d{2}\.\d{2}\.\d{4})\z/u', $subject[1], $date)) {
                    $this->reject('Genau eine Gesamtübersicht mit eindeutigem Arbeitsdatum auswählen.');
                }
                $parsedDate = \DateTimeImmutable::createFromFormat('!d.m.Y', $date[1]);
                $assignment = trim(explode(':', $title[1], 2)[1]);
                if (! $parsedDate || $parsedDate->format('d.m.Y') !== $date[1] || $assignment === '') {
                    $this->reject('Das Datum oder der Titel der Auswertung ist ungültig.');
                }
                $overview = ['title' => $assignment, 'date' => $date[1], 'source' => $filename];
            }
        }
        if ($overview === null) {
            $this->reject('Gesamtübersicht und zugehörige Einzelbeurteilungen vollständig auswählen.');
        }
        $overviewRows = $this->compactTableRows($documents[$overview['source']]['body'], '| Person / Klasse | Abgabestatus | Erreichte Punkte | Maximale Punkte | Bewertungsstatus |');
        $rows = [];
        $maximum = null;
        foreach ($overviewRows as $cells) {
            if (count($cells) !== 5 || ! preg_match('/\A(.+) \/ ([^\/]+)\z/u', $cells[0], $identity)) {
                $this->reject('Ungültige Person oder Zeile in der Gesamtübersicht.');
            }
            $rowMaximum = $this->number($cells[3]);
            $points = $cells[2] === 'offen' ? null : $this->number($cells[2]);
            if ($rowMaximum <= 0 || ($maximum !== null && $maximum !== $rowMaximum)
                || ($points === null && ($cells[1] !== 'im Prüfstand offen' || $cells[4] !== 'Bewertung offen'))
                || ($points !== null && ($points > $rowMaximum || $cells[1] !== 'E-Mail und MC-PDF vorhanden' || $cells[4] !== 'vorliegende Abgabe beurteilt'))) {
                $this->reject('Punkte, Abgabestatus oder Bewertungsstatus der Übersicht widersprechen einander.');
            }
            $maximum = $rowMaximum;
            $key = self::identity($identity[1], $identity[2]);
            if (isset($rows[$key])) {
                $this->reject('Eine Person/Klasse steht mehrfach in der Übersicht.');
            }
            $rows[$key] = ['person' => $identity[1], 'class' => $identity[2], 'points' => $points];
        }
        $courseRows = [];
        $seen = [];
        foreach ($documents as $filename => $document) {
            if ($filename === $overview['source']) {
                continue;
            }
            $filenameParts = explode('_', pathinfo($filename, PATHINFO_FILENAME));
            if ($document['title'] !== 'Auswertung: '.$overview['title'] || count($filenameParts) !== 2
                || trim($filenameParts[0]) === '' || trim($filenameParts[1]) === ''
                || ! preg_match('/\A(.+) \/ ([^\/]+)\z/u', $document['subject'], $identity)
                || self::identity($filenameParts[0].' '.$filenameParts[1], '') !== self::identity($identity[1], '')) {
                $this->reject("{$filename}: Dateiname, Person oder Titel im Bericht passen nicht zusammen.");
            }
            $key = self::identity($identity[1], $identity[2]);
            $row = $rows[$key] ?? null;
            if (! $row || isset($seen[$key])) {
                $this->reject("{$filename}: Keine eindeutige Person/Klasse in der Übersicht.");
            }
            $criteria = $this->compactTableRows($document['body'], '| Kriterium / Aufgabenteil | Maximale Punkte laut Kriterien | Erreichte Punkte | Konkrete Begründung anhand der Abgabe |');
            $totals = null;
            $mc = null;
            $sumMaximum = 0.0;
            $sumPoints = 0.0;
            $labels = [];
            foreach ($criteria as $cells) {
                if (count($cells) !== 4 || isset($labels[$cells[0]])) {
                    $this->reject("{$filename}: Ungültige oder doppelte Kriterienzeile.");
                }
                $labels[$cells[0]] = true;
                $criterionMaximum = $this->number(trim($cells[1], '* '));
                $criterionPoints = trim($cells[2], '* ') === 'offen' ? null : $this->number(trim($cells[2], '* '));
                if ($criterionMaximum <= 0 || ($criterionPoints !== null && $criterionPoints > $criterionMaximum)
                    || ($row['points'] === null) !== ($criterionPoints === null)) {
                    $this->reject("{$filename}: Kriterienpunkte widersprechen dem Bewertungsstatus.");
                }
                if ($cells[0] === '**Gesamt**') {
                    $totals = ['maximum' => $criterionMaximum, 'points' => $criterionPoints];

                    continue;
                }
                $sumMaximum += $criterionMaximum;
                $sumPoints += $criterionPoints ?? 0;
                if ($cells[0] === 'MC-PDF') {
                    $mc = ['maximum' => $criterionMaximum, 'points' => $criterionPoints];
                }
            }
            if (! $totals || ! $mc || abs($sumMaximum - $maximum) > 0.001 || $totals['maximum'] !== $maximum
                || $totals['points'] !== $row['points'] || ($row['points'] !== null && abs($sumPoints - $row['points']) > 0.001)) {
                $this->reject("{$filename}: Übersicht, Gesamtergebnis und Kriterienpunkte widersprechen einander.");
            }
            $comment = '';
            if ($row['points'] !== null) {
                if (! preg_match('/^\*\*Ergebnis der vorliegenden Abgabe: ([\d,.]+) von ([\d,.]+) Punkten\.\*\* E-Mail: ([\d,.]+)\/([\d,.]+); MC-PDF: ([\d,.]+)\/([\d,.]+)\.$/m', $document['body'], $result)
                    || $this->number($result[1]) !== $row['points'] || $this->number($result[2]) !== $maximum
                    || abs($this->number($result[3]) - ($sumPoints - $mc['points'])) > 0.001
                    || abs($this->number($result[4]) - ($maximum - $mc['maximum'])) > 0.001
                    || $this->number($result[5]) !== $mc['points'] || $this->number($result[6]) !== $mc['maximum']) {
                    $this->reject("{$filename}: Ergebniszeile und Kriterienpunkte widersprechen einander.");
                }
                $comment = trim($result[0]);
            } elseif (! str_contains($document['body'], '**Bewertung offen.**') || ! str_contains($document['body'], '**Keine abschließende Summe.**')) {
                $this->reject("{$filename}: Eindeutiger Hinweis auf offene Bewertung fehlt.");
            }
            $courseKey = self::identity($filenameParts[1].' '.$filenameParts[0], $identity[2]);
            if (isset($courseRows[$courseKey])) {
                $this->reject('Mehrere Einzelbeurteilungen für dieselbe Person/Klasse.');
            }
            $courseRows[$courseKey] = $row + ['comment' => $comment, 'source' => $filename];
            $seen[$key] = true;
        }
        if ($rows === [] || count($seen) !== count($rows)) {
            $this->reject('Gesamtübersicht und zugehörige Einzelbeurteilungen vollständig auswählen.');
        }

        return $overview + ['maximum' => $maximum, 'rows' => $courseRows];
    }

    /** @return list<list<string>> */
    private function compactTableRows(string $body, string $header): array
    {
        if (preg_match_all('/^'.preg_quote($header, '/').'\n\|[ :|\-]+\|\n((?:\|[^\n]+\|(?:\n|\z))+)/mu', $body, $tables) !== 1) {
            $this->reject('Genau eine bekannte Punktetabelle im Auswertungsbericht erwartet.');
        }

        return array_map(fn (string $line): array => array_map('trim', explode('|', trim($line, '| '))), explode("\n", rtrim($tables[1][0], "\n")));
    }

    public function preview(TeachingCourseWork $work, User $actor, array $report, array $pdfs = []): array
    {
        $course = $work->teachingCourse;
        $definition = $this->entries->entryDefinitionsForCourse($actor, $course)->firstWhere('short_name', $work->type);
        if (! $definition?->has_properties || $definition->properties_mode !== 'points' ||
            $definition->category !== 'Benotung' || abs((float) $definition->maximum_points - $report['maximum']) > 0.001) {
            $this->reject('Für diese Arbeit einen Eintragstyp mit Punkte-Bewertung und '.$report['maximum'].' maximalen Punkten wählen. Es erfolgt keine Umrechnung in Schulnoten.');
        }
        $date = \DateTimeImmutable::createFromFormat('!d.m.Y', $report['date']);
        if (! $date || $date->format('d.m.Y') !== $report['date']) {
            $this->reject('Das Datum der Auswertung ist ungültig.');
        }
        $identities = array_map(fn (array $matches): array => array_column($matches, 'student_id'), $this->courseIdentities($course));
        $groups = app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work);
        $rows = [];
        $blocked = false;
        foreach ($report['rows'] as $key => $row) {
            $ids = array_unique($identities[$key] ?? []);
            $row['student_id'] = count($ids) === 1 ? reset($ids) : null;
            $row['status'] = count($ids) > 1 ? 'Mehrdeutig – Import gesperrt' : ($row['student_id'] ? ($row['points'] === null ? 'Offen – unverändert' : 'Übernehmen') : 'Nicht im Kurs – übersprungen');
            $blocked = $blocked || count($ids) > 1;
            $matchingGroups = array_keys(array_filter($groups, fn (array $group): bool => in_array($row['student_id'], $group['student_ids'] ?? [])));
            if ($row['student_id'] && count($matchingGroups) !== 1) {
                $row['status'] = 'Keine eindeutige Arbeitsgruppe – Import gesperrt';
                $blocked = true;
            }
            $row['group_index'] = count($matchingGroups) === 1 ? reset($matchingGroups) : null;
            $group = $groups[$row['group_index']] ?? [];
            $previous = [];
            foreach (['points', 'grades', 'comments'] as $field) {
                $previous[$field] = collect($group[$field] ?? [])->firstWhere('student_id', $row['student_id'])[$field === 'grades' ? 'grade' : ($field === 'comments' ? 'comment' : 'points')] ?? null;
            }
            $previous['grades'] ??= $group['grade'] ?? null;
            $row['previous'] = $previous;
            if ($work->is_group_work && empty($group['use_individual_grades']) && $row['student_id'] && $row['points'] !== null) {
                $row['status'] = 'Gruppenarbeit: Einzelbewertungen aktivieren – Import gesperrt';
                $blocked = true;
            }
            $row['pdf'] = $this->pairPdf($row['source'], $pdfs);
            $row['previous_pdf'] = collect($work->status['evaluation_pdfs'] ?? [])->first(fn (array $pdf): bool => ($pdf['origin'] ?? null) === 'evaluation_import' && $pdf['student_id'] === $row['student_id']);
            $rows[] = $row;
        }
        $knownSources = array_merge([$report['source']], array_column($rows, 'source'));
        foreach ($pdfs as $pdf) {
            if (! collect($knownSources)->contains(fn (string $source): bool => mb_strtolower(pathinfo($source, PATHINFO_FILENAME)) === mb_strtolower(pathinfo($pdf['name'], PATHINFO_FILENAME)))) {
                $this->reject('PDF ohne exakt gleichnamigen Markdown-Bericht: '.$pdf['name']);
            }
        }
        $hash = hash('sha256', json_encode([$work->getAttributes(), $groups, $definition->toArray(), $identities, $report, $pdfs], JSON_THROW_ON_ERROR));

        return ['title' => $report['title'], 'date' => $report['date'], 'maximum' => $report['maximum'], 'rows' => $rows, 'hash' => $hash,
            'pdf' => $this->pairPdf($report['source'], $pdfs),
            'previous_pdf' => collect($work->status['evaluation_pdfs'] ?? [])->first(fn (array $pdf): bool => ($pdf['origin'] ?? null) === 'evaluation_import' && $pdf['student_id'] === null),
            'can_import' => ! $blocked && (collect($rows)->contains('status', 'Übernehmen') || $pdfs !== [])];
    }

    private function pairPdf(string $source, array $pdfs): ?array
    {
        $matches = array_values(array_filter($pdfs, fn (array $pdf): bool => mb_strtolower(pathinfo($source, PATHINFO_FILENAME)) === mb_strtolower(pathinfo($pdf['name'], PATHINFO_FILENAME))));
        if (count($matches) > 1) {
            $this->reject('Mehrdeutiges PDF-Paar für '.$source);
        }

        return $matches[0] ?? null;
    }

    public function apply(TeachingCourseWork $work, array $preview): void
    {
        $groups = app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work);
        foreach ($preview['rows'] as $row) {
            if ($row['status'] !== 'Übernehmen') {
                continue;
            }
            $group = &$groups[$row['group_index']];
            if ($work->is_group_work && empty($group['use_individual_grades'])) {
                $this->reject('Gruppenarbeit zuerst auf Einzelbewertungen umstellen; gemeinsame Bewertungen werden nicht überschrieben.');
            }
            foreach (['points' => $row['points'], 'grades' => (string) $row['points'], 'comments' => $row['comment']] as $field => $value) {
                $key = $field === 'grades' ? 'grade' : ($field === 'comments' ? 'comment' : 'points');
                $group[$field] = array_values(array_filter($group[$field] ?? [], fn (array $item): bool => (int) $item['student_id'] !== $row['student_id']));
                $group[$field][] = ['student_id' => $row['student_id'], $key => $value];
            }
            unset($group);
        }
        $work->groups = $groups;
        $work->save();
        app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);
    }

    /** @param array<string, mixed> $preview
     * @param  list<UploadedFile>  $uploads
     * @param  list<array{name: string, sha256: string}>  $pdfs
     * @param  list<string>  $createdPaths
     */
    public function storePdfs(TeachingCourseWork $work, array $preview, array $uploads, array $pdfs, array &$createdPaths): void
    {
        $status = $work->status ?? [];
        $attachments = $status['evaluation_pdfs'] ?? [];
        $targets = [['student_id' => null, 'pdf' => $preview['pdf']]];
        foreach ($preview['rows'] as $row) {
            if ($row['student_id']) {
                $targets[] = ['student_id' => $row['student_id'], 'pdf' => $row['pdf']];
            }
        }
        foreach ($targets as $target) {
            $pdf = $target['pdf'];
            if (! $pdf || collect($attachments)->contains(fn (array $attachment): bool => $attachment['sha256'] === $pdf['sha256'] && $attachment['student_id'] === $target['student_id'])) {
                continue;
            }
            $directory = "teaching/work_evaluations/{$work->teachingCourse->school_id}/{$work->id}";
            $path = $directory.'/'.$pdf['sha256'].'.pdf';
            if (! Storage::disk('local')->exists($path)) {
                $createdPaths[] = $path;
                $index = array_search($pdf, $pdfs, true);
                if ($uploads[$index]->storeAs($directory, $pdf['sha256'].'.pdf', 'local') !== $path) {
                    $this->reject('Auswertungs-PDF konnte nicht gespeichert werden.');
                }
            }
            $attachments = array_values(array_filter($attachments, fn (array $attachment): bool => ($attachment['origin'] ?? null) !== 'evaluation_import' || $attachment['student_id'] !== $target['student_id']));
            $attachments[] = $pdf + ['student_id' => $target['student_id'], 'file_path' => $path, 'storage_disk' => 'local', 'origin' => 'evaluation_import'];
        }
        $status['evaluation_pdfs'] = $attachments;
        $work->status = $status;
        $work->save();
    }

    /** Call only after authorizing the owning course; studentId additionally restricts the personal report. */
    public function streamPdf(TeachingCourseWork $work, string $sha256, ?int $studentId = null, bool $inline = false): StreamedResponse
    {
        abort_unless(preg_match('/\A[a-f0-9]{64}\z/', $sha256), 404);
        $attachment = collect($work->status['evaluation_pdfs'] ?? [])->first(fn (array $pdf): bool => $pdf['sha256'] === $sha256 && ($studentId === null || ($pdf['student_id'] !== null && (int) $pdf['student_id'] === $studentId)));
        abort_unless($attachment, 404);
        $path = $attachment['file_path'] ?? '';
        $schoolId = $work->teachingCourse->school_id;
        abort_unless(($attachment['storage_disk'] ?? null) === 'local' && ! str_contains($path, '..') && ! str_contains($path, '\\') &&
            (str_starts_with($path, "teaching/work_evaluations/{$schoolId}/") || str_starts_with($path, "teaching/synchronisation/{$schoolId}/") || str_starts_with($path, 'teaching/personal_restores/')), 404);
        $stream = Storage::disk('local')->readStream($path);
        abort_unless(is_resource($stream), 404);
        $name = PrivateImportSourceFile::downloadName($attachment['name'], 'beurteilung', 'pdf');

        return response()->stream(static function () use ($stream): void {
            try {
                while (! feof($stream)) {
                    $chunk = fread($stream, 8192);
                    if ($chunk === false) {
                        throw new \RuntimeException('Die PDF konnte nicht gelesen werden.');
                    }
                    echo $chunk;
                }
            } finally {
                fclose($stream);
            }
        }, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $name), 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
