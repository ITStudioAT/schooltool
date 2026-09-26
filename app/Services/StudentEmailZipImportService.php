<?php

namespace App\Services;

use App\Jobs\Teaching\Import116Job;
use App\Models\Import116;
use App\Models\User;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class StudentEmailZipImportService
{
    /** @return array<string, int|array<int, array{class: string, name: string}>> */
    public function import(string $path, int $schoolId, int $schoolyearId): array
    {
        $rows = $this->readRows($path);
        $students = Import116::query()
            ->where('school_id', $schoolId)
            ->where('schoolyear_id', $schoolyearId)
            ->whereNotNull('exists_date')
            ->get(['id', 'school_id', 'schoolyear_id', 'class', 'first_name', 'last_name', 'email', 'user_id', 'exists_date']);

        $matches = [];
        $unmatched = [];
        $ambiguous = 0;
        foreach ($rows as $row) {
            $candidates = $students->filter(fn (Import116 $student): bool => $this->normalize($student->class) === $this->normalize($row['class'])
                && $this->nameMatches($student, $row['name'])
            );

            if ($candidates->count() !== 1) {
                $ambiguous += $candidates->count() > 1 ? 1 : 0;
                if (count($unmatched) < 10) {
                    $unmatched[] = ['class' => $row['class'], 'name' => $row['name']];
                }

                continue;
            }

            $student = $candidates->first();
            if (isset($matches[$student->id]) && $matches[$student->id] !== $row['email']) {
                $this->invalid('Mehrere unterschiedliche E-Mail-Adressen passen zu derselben Person.');
            }
            $matches[$student->id] = $row['email'];
        }

        $counts = [
            'total' => count($rows),
            'matched' => count($matches),
            'updated' => 0,
            'skipped_existing' => 0,
            'skipped_conflict' => 0,
            'unmatched' => count($rows) - count($matches),
            'ambiguous' => $ambiguous,
            'unmatched_examples' => $unmatched,
        ];

        DB::transaction(function () use ($matches, $schoolId, $schoolyearId, &$counts): void {
            foreach ($matches as $id => $email) {
                $student = Import116::query()
                    ->whereKey($id)
                    ->where('school_id', $schoolId)
                    ->where('schoolyear_id', $schoolyearId)
                    ->lockForUpdate()
                    ->first();

                if (! $student || $student->exists_date === null) {
                    $counts['unmatched']++;
                    $counts['matched']--;

                    continue;
                }

                if (trim((string) $student->email) !== '') {
                    $counts['skipped_existing']++;

                    continue;
                }

                $emailAssignedToAnotherStudent = Import116::query()
                    ->where('school_id', $schoolId)
                    ->where('schoolyear_id', $schoolyearId)
                    ->where('email', $email)
                    ->whereKeyNot($student->id)
                    ->exists();
                if ($emailAssignedToAnotherStudent) {
                    $counts['skipped_conflict']++;

                    continue;
                }

                $linkedUser = $student->user_id
                    ? User::query()->whereKey($student->user_id)->lockForUpdate()->first()
                    : null;
                if ($linkedUser && (int) $linkedUser->school_id !== $schoolId) {
                    $counts['skipped_conflict']++;

                    continue;
                }

                if ($linkedUser && Import116Job::isPlaceholderEmail($linkedUser->email)) {
                    $emailTaken = User::query()
                        ->where('school_id', $schoolId)
                        ->where('email', $email)
                        ->whereKeyNot($linkedUser->id)
                        ->exists();
                    if ($emailTaken) {
                        $counts['skipped_conflict']++;

                        continue;
                    }

                    $linkedUser->email = $email;
                    $linkedUser->save();
                }

                $student->email = $email;
                $student->save();
                $counts['updated']++;
            }
        });

        return $counts;
    }

    /** @return list<array{class: string, name: string, email: string}> */
    private function readRows(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->invalid('Die Datei ist kein lesbares ZIP-Archiv.');
        }

        try {
            if ($zip->numFiles > 100) {
                $this->invalid('Das ZIP-Archiv enthält zu viele Dateien.');
            }

            $rows = [];
            $totalBytes = 0;
            $htmlFiles = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $name = (string) ($entry['name'] ?? '');
                if (str_ends_with($name, '/')) {
                    continue;
                }

                if (! preg_match('/\.html?$/i', $name)) {
                    $this->invalid('Das ZIP-Archiv darf nur HTML-Listen enthalten.');
                }

                $size = (int) ($entry['size'] ?? 0);
                $totalBytes += $size;
                if ($size > 1_000_000 || $totalBytes > 5_000_000) {
                    $this->invalid('Die HTML-Listen im ZIP-Archiv sind zu groß.');
                }

                $contents = $zip->getFromIndex($index);
                if (! is_string($contents) || strlen($contents) !== $size) {
                    $this->invalid('Eine HTML-Liste im ZIP-Archiv ist beschädigt.');
                }

                $htmlFiles++;
                foreach ($this->parseHtml($contents) as $row) {
                    $key = $row['email'];
                    if (isset($rows[$key]) && $rows[$key] !== $row) {
                        $this->invalid('Widersprüchliche Einträge für dieselbe E-Mail-Adresse im ZIP-Archiv.');
                    }
                    $rows[$key] = $row;
                    if (count($rows) > 2000) {
                        $this->invalid('Das ZIP-Archiv enthält zu viele Personen.');
                    }
                }
            }

            if ($htmlFiles === 0 || $rows === []) {
                $this->invalid('Das ZIP-Archiv enthält keine gültigen Login-Listen.');
            }

            return array_values($rows);
        } finally {
            $zip->close();
        }
    }

    /** @return list<array{class: string, name: string, email: string}> */
    private function parseHtml(string $contents): array
    {
        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$contents, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $table = $loaded ? $document->getElementsByTagName('table')->item(0) : null;
        if (! $table) {
            $this->invalid('Eine HTML-Liste enthält keine Tabelle.');
        }

        $rows = [];
        foreach ($table->getElementsByTagName('tr') as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $node) {
                if ($node instanceof DOMElement && in_array(strtolower($node->tagName), ['td', 'th'], true)) {
                    $cells[] = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
                }
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        if (array_shift($rows) !== ['Klasse', 'Benutzer', 'NT-Login', 'Passwort', 'E-Mail']) {
            $this->invalid('Eine HTML-Liste hat nicht die erwarteten Spalten.');
        }

        $parsed = [];
        foreach ($rows as $cells) {
            if (count($cells) !== 5) {
                $this->invalid('Eine HTML-Liste enthält eine unvollständige Tabellenzeile.');
            }
            [$class, $name, $login, , $email] = $cells;
            $email = mb_strtolower($email);
            if ($class === '' || $name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)
                || $email !== mb_strtolower($login).'@cdgym.at') {
                $this->invalid('Eine HTML-Liste enthält eine ungültige Klasse, Person oder E-Mail-Adresse.');
            }
            $parsed[] = compact('class', 'name', 'email');
        }

        return $parsed;
    }

    private function nameMatches(Import116 $student, string $name): bool
    {
        $last = $this->normalize($student->last_name);
        $first = $this->normalize($student->first_name);
        $source = $this->normalize($name);
        $firstShort = explode(' ', $first)[0];
        $lastShort = explode(' ', $last)[0];

        return in_array($source, ["{$last} {$first}", "{$last} {$firstShort}", "{$lastShort} {$firstShort}"], true);
    }

    private function normalize(?string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''));
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
