<?php

namespace App\Support;

use Brick\Math\BigInteger;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;
use JsonException;
use stdClass;

class SchooltoolAssessmentJson
{
    public const MAX_INTEGER = 9007199254740991;

    public function parse(string $text): array
    {
        $this->check(strlen($text) <= 262144 && ! str_starts_with($text, "\xEF\xBB\xBF"), 'JSON höchstens 256 KiB, UTF-8 ohne BOM.');
        try {
            $object = json_decode($text, false, 32, JSON_THROW_ON_ERROR);
            $offset = 0;
            $this->scan($text, $offset);
        } catch (JsonException) {
            $this->check(false, 'Ungültiges UTF-8-JSON.');
        }
        $schema = json_decode(file_get_contents(__DIR__.'/schooltool-json-v1.schema.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->structure($object, $schema, $schema, '$');
        $package = json_decode(json_encode($object, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $download = $package['final_download'] ?? null;
        if ($download !== null) {
            $this->finalDownload($download, $package['exercise_id'], $package['records']);
        }
        $decision = $package['teacher_absence_decision'] ?? null;
        if ($decision !== null) {
            $this->teacherAbsenceDecision($decision, $package['exercise_id'], $package['records']);
        }
        $rubric = $package['rubric'];
        $this->check(count(array_unique(array_column($rubric, 'criterion'))) === count($rubric), 'Doppelte Kriterien.');
        $this->check($this->sum(array_column($rubric, 'maximum_minor')) === (string) $package['maximum_minor'], 'Falsches Gesamtmaximum.');
        $ids = $identities = $personIds = $pdfs = [];
        $this->pdf($package['overview_pdf'], $pdfs);
        foreach ($package['records'] as $record) {
            foreach (['email_collected_at', 'evaluation_completed_at'] as $field) {
                $time = $record[$field] ?? null;
                if ($time !== null) {
                    $this->check(preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.[0-9]{1,6})?Z\z/', $time) === 1
                        && checkdate((int) substr($time, 5, 2), (int) substr($time, 8, 2), (int) substr($time, 0, 4))
                        && (int) substr($time, 11, 2) < 24 && (int) substr($time, 14, 2) < 60 && (int) substr($time, 17, 2) < 60, $field.': ungültiger UTC-Zeitpunkt.');
                    $this->check($field === 'email_collected_at' ? $record['submission_state'] === 'received' : $record['evaluation_state'] === 'complete', $field.': Zeitpunkt widerspricht Status.');
                }
            }
            $this->check(! in_array($record['participant_id'], $ids, true), 'Doppelte Teilnehmerkennung.');
            $ids[] = $record['participant_id'];
            foreach ($record['identity'] as $value) {
                $this->check($value === null || ($value === trim($value) && $value !== '' && ! preg_match('/[\x00-\x1f]/u', $value)), 'Ungültige Identität.');
            }
            $identity = array_map(fn (string $field): string => self::normalize($record['identity'][$field]), ['first_name', 'last_name', 'class_name', 'group_name']);
            $this->check(! in_array($identity, $identities, true), 'Doppelte Personenidentität.');
            $identities[] = $identity;
            $personId = $record['identity']['schooltool_person_id'];
            if ($personId !== null) {
                $this->check(! in_array($personId, $personIds, true), 'Doppelte Schooltool-Personenkennung.');
                $personIds[] = $personId;
            }
            $criteria = $record['criteria'];
            $this->check(array_map(fn (array $c): array => [$c['criterion'], $c['maximum_minor']], $criteria) === array_map(fn (array $c): array => [$c['criterion'], $c['maximum_minor']], $rubric), 'Kriterien/Maxima widersprechen Raster.');
            foreach ($criteria as $criterion) {
                $value = $criterion['earned_minor'];
                $this->check($value === null || $value <= $criterion['maximum_minor'], 'Kriterium über Maximum.');
                $this->check($criterion['checkability'] !== 'unresolved' || $value === null, 'Ungeklärtes Kriterium mit Punkten.');
                $this->check($criterion['checkability'] !== 'uncheckable' || in_array($value, [null, 0], true), 'Nicht prüfbares Kriterium mit positiven Punkten.');
            }
            $values = array_column($criteria, 'earned_minor');
            $numeric = count(array_filter($values, fn (?int $value): bool => $value !== null));
            $absence = $record['submission_state'] === 'not_received' && $record['evaluation_state'] === 'complete';
            $this->check($record['submission_state'] === 'received' || $record['evaluation_state'] === 'open' || $absence, 'Abschluss/Teilpunkte ohne Eingang.');
            if ($absence) {
                $authorized = $decision !== null && in_array($record['participant_id'], $decision['participant_ids'], true);
                $check = $package['submission_check'] ?? null;
                $person = collect($check['participants'] ?? [])->firstWhere('participant_id', $record['participant_id']);
                $this->check($authorized || $download !== null || ($check !== null && $check['state'] === 'complete' && $person
                    && $person['email_result'] === 'not_found' && $person['other_result'] === 'not_received'), 'Nichtabgabe-Nullwertung ohne Abschlussdownload, Gesamtprüfnachweis oder ausdrückliche Lehrerentscheidung.');
                $reason = 'Innerhalb der Frist nicht abgegeben';
                $this->check($record['total_minor'] === 0 && $record['adjustments'] === []
                    && count(array_filter($criteria, fn (array $criterion): bool => $criterion['earned_minor'] === 0 && $criterion['checkability'] === 'uncheckable' && $criterion['reason'] === $reason)) === count($criteria), 'Ungültige Nichtabgabe-Nullwertung.');
                $this->check($record['comment'] === $reason && $record['evaluation_note'] === $reason && $record['submission_note'] === $reason, 'Nichtabgabe-Wortlaut muss exakt erhalten bleiben.');
            }
            if ($record['evaluation_state'] === 'complete') {
                $this->check($numeric === count($values) && $record['total_minor'] !== null, 'Abschluss mit offenen Punkten.');
                $this->check($this->sum([...$values, ...array_column($record['adjustments'], 'amount_minor')]) === (string) $record['total_minor'] && $record['total_minor'] <= $package['maximum_minor'], 'Falsche Gesamtsumme.');
            } else {
                $this->check($record['total_minor'] === null, 'Vorläufige Bewertung mit Gesamtsumme.');
                $this->check($record['evaluation_state'] === 'open' ? $numeric === 0 && $record['adjustments'] === [] : $numeric > 0 && $numeric < count($values), 'Offene/partielle Kriterien widersprechen Bewertungsstatus.');
            }
            $this->pdf($record['pdf'], $pdfs);
            $this->checksum($record, 'record_checksum');
        }
        $this->check(count($pdfs) <= 20 && count(array_unique($pdfs)) === count($pdfs), 'PDF-Limit oder mehrdeutige PDF-Zuordnung.');
        $this->checksum($package, 'package_checksum');
        if (isset($package['submission_check'])) {
            $absenceIds = $decision['participant_ids'] ?? [];
            if ($download !== null) {
                $absenceIds = array_merge($absenceIds, array_column(array_filter($download['participants'], fn (array $person): bool => $person['submission_sha256'] === []), 'participant_id'));
            }
            $this->submissionCheck($package['submission_check'], $package['exercise_id'], $package['records'], $absenceIds);
        }

        return $package;
    }

    public static function normalize(string $value): string
    {
        return mb_convert_case(preg_replace('/\s+/u', ' ', trim($value)), MB_CASE_FOLD, 'UTF-8');
    }

    /** @param list<array<string, mixed>> $records */
    public function finalDownload(array $download, string $exerciseId, array $records): void
    {
        $schema = json_decode(file_get_contents(__DIR__.'/schooltool-json-v1.schema.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->structure(json_decode(json_encode($download, JSON_THROW_ON_ERROR)), $schema['properties']['final_download'], $schema, '$.final_download');
        $this->check($download['exercise_id'] === $exerciseId, 'Abschlussdownload gehört zu einer anderen Arbeit.');
        $deadline = $this->instant($download['deadline_at']);
        $completed = $this->instant($download['download_completed_at']);
        $this->check($deadline < $completed && $completed <= new DateTimeImmutable(now()->toISOString()), 'Abschlussdownload muss nach Fristablauf und darf nicht in der Zukunft liegen.');
        $this->check(in_array($download['deadline_timezone'], DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true), 'Abschlussdownload: unbekannte Fristzeitzone.');
        $this->check(trim($download['scope']) !== '' && ! preg_match('/[\r\n]/', $download['scope']), 'Abschlussdownload: tatsächlicher Downloadbestand fehlt.');
        $ids = array_column($download['participants'], 'participant_id');
        $recordIds = array_column($records, 'participant_id');
        sort($ids, SORT_STRING);
        sort($recordIds, SORT_STRING);
        $this->check($ids === $recordIds && count(array_unique($ids)) === count($ids), 'Abschlussdownload: Teilnehmerbestand widerspricht dem Paket.');
        foreach ($download['participants'] as $person) {
            $hashes = $person['submission_sha256'];
            $this->check(count(array_unique($hashes)) === count($hashes), 'Abschlussdownload: doppelte Abgabeprüfsummen.');
            $record = collect($records)->firstWhere('participant_id', $person['participant_id']);
            $this->check($record['submission_state'] === ($hashes === [] ? 'not_received' : 'received'), 'Abgabestatus widerspricht Abschlussbestand.');
            $this->check($hashes !== [] || $record['evaluation_state'] === 'complete', 'Fehlende Abgabe nach Abschlussdownload muss nullbewertet sein.');
        }
        $this->checksum($download, 'download_checksum');
    }

    /** @param list<array<string, mixed>> $records */
    private function teacherAbsenceDecision(array $decision, string $exerciseId, array $records): void
    {
        $this->check($decision['exercise_id'] === $exerciseId, 'Lehrerentscheidung gehört zu einer anderen Arbeit.');
        $ids = $decision['participant_ids'];
        $this->check(count(array_unique($ids)) === count($ids) && array_diff($ids, array_column($records, 'participant_id')) === []
            && count(array_filter($ids, fn (string $id): bool => trim($id) === $id && $id !== '')) === count($ids), 'Lehrerentscheidung ohne eindeutige benannte Teilnehmer.');
        $this->check(trim($decision['instruction']) !== '', 'Ausdrücklicher Nutzerauftrag fehlt.');
        $deadline = $this->instant($decision['deadline_at']);
        $decided = $this->instant($decision['decided_at']);
        $this->check($deadline < $decided && $decided <= new DateTimeImmutable(now()->toISOString()), 'Lehrerentscheidung vor Fristablauf oder in Zukunft.');
        foreach ($records as $record) {
            if (in_array($record['participant_id'], $ids, true)) {
                $this->check($record['submission_state'] === 'not_received' && $record['evaluation_state'] === 'complete', 'Lehrerentscheidung widerspricht benannten Nullfällen.');
            }
        }
        $this->checksum($decision, 'decision_checksum');
    }

    /** @param list<array<string, mixed>> $records */
    public function submissionCheck(array $check, string $exerciseId, array $records, array $absenceIds = []): void
    {
        $schema = json_decode(file_get_contents(__DIR__.'/schooltool-json-v1.schema.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->structure(json_decode(json_encode($check, JSON_THROW_ON_ERROR)), $schema['$defs']['submission_check'], $schema, '$.submission_check');
        $this->check($check['exercise_id'] === $exerciseId, 'Abgabeprüfung gehört zu einer anderen Arbeit.');
        $deadline = $this->instant($check['deadline_at']);
        $checked = $this->instant($check['checked_at']);
        $this->check($checked <= new DateTimeImmutable(now()->toISOString()), 'Abgabeprüfung liegt in der Zukunft.');
        $ids = array_column($check['participants'], 'participant_id');
        $recordIds = array_column($records, 'participant_id');
        sort($ids, SORT_STRING);
        sort($recordIds, SORT_STRING);
        $this->check($ids === $recordIds && count(array_unique($ids)) === count($ids)
            && hash_equals($check['roster_fingerprint'], self::digest($ids)), 'Abgabeprüfung: Teilnehmerbasis widerspricht dem Paket.');
        $gaps = $check['gaps'];
        if ($checked <= $deadline) {
            $gaps[] = 'deadline_not_passed';
        }
        $dispatches = array_map(fn (array $person): DateTimeImmutable => $this->instant($person['dispatched_at']), $check['participants']);
        $earliest = min($dispatches);
        $mailboxes = array_column($check['coverage'], 'mailbox');
        sort($mailboxes, SORT_STRING);
        $expectedMailboxes = ['guenther.kron@bildung.gv.at', 'guenther.kron@cdgym.at'];
        $fullSearch = $mailboxes === $expectedMailboxes;
        foreach ($check['coverage'] as $coverage) {
            $start = $this->instant($coverage['start_at']);
            $end = $this->instant($coverage['end_at']);
            $this->check($start <= $end && ($coverage['evidence_sha256'] !== [] || ($coverage['scope'] === 'unavailable' && ! $coverage['verified'])), 'Abgabeprüfung: ungültige Postfachbelege.');
            $fullSearch = $fullSearch && $coverage['scope'] === 'server_all_folders' && $coverage['verified'] && $coverage['gaps'] === [] && $start <= $earliest && $end >= $checked;
        }
        if (! $fullSearch) {
            $gaps[] = 'mailbox_incomplete';
        }
        foreach ($check['participants'] as $index => $person) {
            if ($dispatches[$index] >= $checked || $person['gaps'] !== [] || $person['email_result'] === 'unresolved' || $person['other_result'] === 'unresolved') {
                $gaps[] = 'person_unresolved';
            }
            $this->check($fullSearch || $person['email_result'] !== 'not_found', 'Negative E-Mail-Feststellung ohne vollständige Serversuche.');
            $outcome = in_array('received', [$person['email_result'], $person['other_result']], true) ? 'received'
                : ($person['email_result'] === 'not_found' && $person['other_result'] === 'not_received' && $checked > $deadline ? 'not_received' : 'unresolved');
            $record = collect($records)->firstWhere('participant_id', $person['participant_id']);
            $overridden = $outcome === 'unresolved' && $check['state'] === 'open' && in_array($record['participant_id'], $absenceIds, true);
            $this->check($record['submission_state'] === $outcome || $overridden, 'Abgabestatus widerspricht Gesamtprüfnachweis.');
        }
        if ($check['unresolved_candidates'] !== []) {
            $gaps[] = 'unresolved_candidates';
        }
        $this->check($check['state'] === ($gaps === [] ? 'complete' : 'open')
            && $check['completed_at'] === ($gaps === [] ? $check['checked_at'] : null), 'Abgabeprüfung: unbelegter Gesamtabschluss.');
        $this->checksum($check, 'check_checksum');
    }

    public function instant(string $value): DateTimeImmutable
    {
        $this->check(preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.[0-9]{1,6})?Z\z/', $value) === 1
            && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))
            && (int) substr($value, 11, 2) < 24 && (int) substr($value, 14, 2) < 60 && (int) substr($value, 17, 2) < 60, 'Abgabeprüfung: ungültiger UTC-Zeitpunkt.');

        return new DateTimeImmutable($value);
    }

    public static function digest(array|stdClass $value): string
    {
        return hash('sha256', self::canonical($value));
    }

    public static function canonical(mixed $value): string
    {
        if ($value instanceof stdClass || (is_array($value) && ! array_is_list($value))) {
            $properties = (array) $value;
            uksort($properties, fn (string $left, string $right): int => strcmp($left, $right));
            $parts = [];
            foreach ($properties as $key => $item) {
                $parts[] = self::canonical((string) $key).':'.self::canonical($item);
            }

            return '{'.implode(',', $parts).'}';
        }
        if (is_array($value)) {
            return '['.implode(',', array_map(self::canonical(...), $value)).']';
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
    }

    /** Validate the exact subset of Draft 2020-12 used by the pinned v1 schema. */
    private function structure(mixed $value, array $schema, array $root, string $path): void
    {
        if (isset($schema['$ref'])) {
            $this->structure($value, $root['$defs'][basename($schema['$ref'])], $root, $path);

            return;
        }
        $types = (array) $schema['type'];
        $type = match (true) {
            $value instanceof stdClass => 'object', is_array($value) => 'array', is_int($value) => 'integer', is_string($value) => 'string', is_bool($value) => 'boolean', $value === null => 'null', default => 'invalid',
        };
        $this->check(in_array($type, $types, true), $path.': falscher Datentyp (Punkte nur als Integer-Hundertstel).');
        $this->check(! array_key_exists('const', $schema) || $value === $schema['const'], $path.': unbekannte Version/Art.');
        $this->check(! isset($schema['enum']) || in_array($value, $schema['enum'], true), $path.': ungültiger Zustand.');
        if ($type === 'object') {
            $actual = array_keys(get_object_vars($value));
            $this->check(array_diff($schema['required'], $actual) === [] && array_diff($actual, array_keys($schema['properties'])) === [], $path.': fehlende oder unbekannte Objektfelder.');
            foreach (get_object_vars($value) as $key => $item) {
                $this->structure($item, $schema['properties'][$key], $root, $path.'.'.$key);
            }
        } elseif ($type === 'array') {
            $this->check(count($value) >= ($schema['minItems'] ?? 0) && count($value) <= ($schema['maxItems'] ?? PHP_INT_MAX), $path.': Listenumfang.');
            foreach ($value as $index => $item) {
                $this->structure($item, $schema['items'], $root, $path.'['.$index.']');
            }
        } elseif ($type === 'string') {
            $this->check(mb_strlen($value) >= ($schema['minLength'] ?? 0), $path.': leerer Text.');
            $this->check(! isset($schema['pattern']) || preg_match('~\A'.substr($schema['pattern'], 1, -1).'\z~u', $value) === 1, $path.': ungültiges Muster.');
        } elseif ($type === 'integer') {
            $this->check($value >= ($schema['minimum'] ?? -self::MAX_INTEGER) && $value <= ($schema['maximum'] ?? self::MAX_INTEGER), $path.': Zahl außerhalb Wertebereich.');
        }
    }

    private function checksum(array $value, string $field): void
    {
        $expected = $value[$field];
        unset($value[$field]);
        $this->check(hash_equals($expected, self::digest($value)), $field.': Prüfsumme falsch.');
    }

    private function pdf(?array $pdf, array &$filenames): void
    {
        if ($pdf === null) {
            return;
        }
        $name = $pdf['filename'];
        $this->check(! preg_match('/[\x00-\x1f]/u', $name) && ! preg_match('/\A(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|\z)/i', $name) && trim($name) === $name, 'Unsicherer PDF-Basisname.');
        $filenames[] = mb_convert_case($name, MB_CASE_FOLD, 'UTF-8');
    }

    private function sum(array $values): string
    {
        $sum = BigInteger::of(0);
        foreach ($values as $value) {
            $sum = $sum->plus($value);
        }

        return (string) $sum;
    }

    /** Scan already valid JSON while retaining duplicate object keys, including escaped keys. */
    private function scan(string $text, int &$offset): void
    {
        $offset += strspn($text, " \t\r\n", $offset);
        $opening = $text[$offset];
        if ($opening === '{' || $opening === '[') {
            $offset++;
            $closing = $opening === '{' ? '}' : ']';
            $seen = [];
            $offset += strspn($text, " \t\r\n", $offset);
            while ($text[$offset] !== $closing) {
                if ($opening === '{') {
                    $key = $this->token($text, $offset);
                    $this->check(! in_array($key, $seen, true), 'Doppelter JSON-Schlüssel: '.$key);
                    $seen[] = $key;
                    $offset += strspn($text, " \t\r\n", $offset) + 1;
                }
                $this->scan($text, $offset);
                $offset += strspn($text, " \t\r\n", $offset);
                if ($text[$offset] === ',') {
                    $offset++;
                    $offset += strspn($text, " \t\r\n", $offset);
                }
            }
            $offset++;

            return;
        }
        $this->token($text, $offset);
    }

    private function token(string $text, int &$offset): mixed
    {
        preg_match('/\G(?:"(?:[^"\\\\]|\\\\.)*"|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?|true|false|null)/s', $text, $match, 0, $offset);
        $offset += strlen($match[0]);

        return json_decode($match[0], flags: JSON_THROW_ON_ERROR);
    }

    public function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['package' => $message]);
        }
    }
}
