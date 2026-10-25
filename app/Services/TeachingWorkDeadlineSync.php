<?php

namespace App\Services;

use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;
use DateTimeImmutable;
use DateTimeZone;

class TeachingWorkDeadlineSync
{
    public function __construct(private DropboxWorkImport $dropbox) {}

    /** @param array<string, mixed> $folder
     * @return array{changed: bool, message: string}
     */
    public function sync(DropboxConnection $connection, TeachingCourseWork $work, array $folder): array
    {
        $parent = $this->dropbox->metadata($connection, $folder['id']);
        $path = $parent['path_display'] ?? $parent['path_lower'];
        abort_unless($parent['id'] === $folder['id'] && $path === $folder['path'], 409, 'Der Leistungsfeststellungsordner wurde inzwischen verschoben.');
        $document = $this->read($connection, $path.'/Anlage_und_Quellen.json');
        abort_unless($document !== null, 409, 'Die Fristmetadaten fehlen.');
        $metadata = $document['value'];
        abort_unless(($metadata['Schooltool_Arbeit_ID'] ?? null) === $work->id
            && ($metadata['Schooltool_Kurs_ID'] ?? null) === $work->teaching_course_id
            && ($metadata['Zeitzone'] ?? null) === 'Europe/Vienna', 409, 'Die Fristmetadaten gehören nicht zu dieser Arbeit.');
        $updated = $this->metadata($work, $metadata);
        if ($updated === $metadata) {
            return ['changed' => false, 'message' => 'Interne Endfrist ist mit Dropbox abgeglichen.'];
        }
        abort_unless($this->dropbox->canWrite($connection), 422, 'Dropbox benötigt die Schreibfreigabe für die interne Endfrist.');
        $ready = $this->read($connection, $path.'/Schooltool-Versandvorbereitung.json');
        $order = $this->read($connection, $path.'/E-Mail-Vorbereitung/Auftragsvorbereitung.json');
        $oldHash = hash('sha256', $document['contents']);
        $updated['FristSynchronisierung'] = ['at' => now()->toIso8601String(), 'source' => 'Schooltool', 'scope' => 'internal'];
        $contents = $this->encode($updated);
        $newHash = hash('sha256', $contents);
        $writes = [[$document, $contents]];
        if ($order !== null) {
            $value = $order['value'];
            abort_unless(($value['Leistungsfeststellung'] ?? null) === ($metadata['Leistungsfeststellungsordner'] ?? null), 409,
                'Die E-Mail-Vorbereitung gehört zu einem anderen Ordner.');
            $value['Fristquelle'] = ['Datei' => 'Anlage_und_Quellen.json', 'SHA256' => $newHash, 'Geltung' => 'intern'];
            $writes[] = [$order, $this->encode($value)];
        }
        if ($ready !== null) {
            $value = $ready['value'];
            $sources = $value['Quellen'] ?? [];
            abort_unless(is_array($sources), 422, 'Ungültige Quellenliste der E-Mail-Vorbereitung.');
            $matches = array_keys(array_filter($sources, fn (mixed $source): bool => is_array($source) && ($source['Datei'] ?? null) === 'Anlage_und_Quellen.json'));
            if (($value['kind'] ?? null) === 'schooltool.preparation' && ($value['Versandbereit'] ?? null) === true
                && count($matches) === 1 && hash_equals($oldHash, $sources[$matches[0]]['SHA256'] ?? '')) {
                $value['Quellen'][$matches[0]]['SHA256'] = $newHash;
                $writes[] = [$ready, $this->encode($value)];
            }
        }
        foreach ($writes as [$file, $bytes]) {
            $this->write($connection, $file, $bytes);
        }

        return ['changed' => true, 'message' => 'Interne Endfrist in Schooltool und Dropbox übernommen. Schülertexte bleiben unverändert.'];
    }

    /** @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function metadata(TeachingCourseWork $work, array $metadata): array
    {
        $date = $work->finish_until_date?->format('Y-m-d');
        $time = $work->finish_until_time ?: null;
        abort_if($time !== null && $date === null, 422, 'Zur Uhrzeit fehlt das Datum der internen Endfrist.');
        $instant = $date && $time ? DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date.' '.$time, new DateTimeZone('Europe/Vienna')) : null;
        abort_if($date && $time && (! $instant || $instant->format('Y-m-d H:i') !== $date.' '.$time), 422,
            'Die interne Endfrist ist keine gültige Wiener Ortszeit.');
        $communicated = $work->communicatedDeadline();
        abort_unless($this->matchesStudentDeadline($work, $metadata), 409,
            'Die mitgeteilte Schülerfrist weicht ab. Diese Prüfung ändert nur die interne Endfrist.');
        $candidate = clone $work;
        $candidate->setStudentDeadline($communicated['date'] ?? null, $communicated['time'] ?? null);
        $deadline = $instant?->format('Y-m-d\TH:i:sP');
        $fields = ['date' => $date, 'time' => $time];
        if (($metadata['Abgabeschluss'] ?? null) === $deadline && ($metadata['Schooltool_Fristdaten']['main_deadline'] ?? null) === $fields) {
            return $metadata;
        }
        $metadata['Abgabeschluss'] = $deadline;
        $metadata['Schooltool_Fristdaten']['main_deadline'] = $fields;
        $metadata['Fristhinweis'] = $instant ? 'Interne endgültige Frist; keine Veröffentlichung an Schüler:innen.'
            : 'Interne endgültige Frist noch unvollständig; keine Uhrzeit ergänzt und keine Veröffentlichung an Schüler:innen.';

        return $metadata;
    }

    /** @param array<string, mixed> $metadata */
    public function matchesStudentDeadline(TeachingCourseWork $work, array $metadata): bool
    {
        $expected = $work->communicatedDeadline();
        $source = $metadata['Schooltool_Fristdaten']['communicated_deadline'] ?? null;
        $at = $metadata['MitgeteilteAbgabefrist'] ?? null;
        if ($at !== null) {
            if (! is_string($at) || ! preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00(?:Z|[+-]\d{2}:\d{2})\z/', $at)) {
                return false;
            }
            $instant = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', str_replace('Z', '+00:00', $at));
            if (! $instant || $instant->format('Y-m-d\TH:i:sP') !== str_replace('Z', '+00:00', $at)) {
                return false;
            }
            $instant = $instant->setTimezone(new DateTimeZone('Europe/Vienna'));
            $source = ['date' => $instant->format('Y-m-d'), 'time' => $instant->format('H:i')];
        }
        if ($source !== null && ! is_array($source)) {
            return false;
        }

        return [$expected['date'] ?? null, $expected['time'] ?? null] === [$source['date'] ?? null, $source['time'] ?? null];
    }

    /** @return array<string, mixed>|null */
    private function read(DropboxConnection $connection, string $path): ?array
    {
        $entry = $this->dropbox->findModuleEntry($connection, $path);
        if ($entry === null) {
            return null;
        }
        abort_unless(($entry['.tag'] ?? null) === 'file' && ($entry['size'] ?? PHP_INT_MAX) <= 1024 * 1024
            && ($entry['path_display'] ?? null) === $path && is_string($entry['rev'] ?? null), 422, 'Ungültige Fristdatei.');
        $contents = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$entry['rev']])->body();
        abort_unless(strlen($contents) === $entry['size'], 422, 'Die Fristdatei wurde nicht vollständig gelesen.');
        $value = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        abort_unless(is_array($value) && ! array_is_list($value), 422, 'Ungültige Fristmetadaten.');

        return ['entry' => $entry, 'contents' => $contents, 'value' => $value];
    }

    /** @param array<string, mixed> $document */
    private function write(DropboxConnection $connection, array $document, string $contents): void
    {
        $entry = $document['entry'];
        $this->dropbox->replaceModuleTemplate($connection, $entry['id'], $entry['rev'], $contents, $entry['path_display']);
    }

    /** @param array<string, mixed> $value */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
    }
}
