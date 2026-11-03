<?php

namespace App\Services\MultipleChoice;

use App\Models\DropboxConnection;
use App\Services\DropboxWorkImport;
use Illuminate\Validation\ValidationException;
use JsonException;

class McExerciseFolderIdentity
{
    public function __construct(private DropboxWorkImport $dropbox) {}

    public function fromChildren(DropboxConnection $connection, array $folder, array $children): ?array
    {
        $matches = array_values(array_filter($children, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file'
            && strcasecmp($entry['name'] ?? '', 'handshake.json') === 0));
        if ($matches === []) {
            return null;
        }
        if (count($matches) !== 1 || ! is_string($matches[0]['id'] ?? null)) {
            $this->fail('Die Ordnerkennung in handshake.json ist nicht eindeutig.');
        }

        return $this->read($connection, $folder, $matches[0]['id']);
    }

    public function verify(DropboxConnection $connection, array $folder, array $expected, bool $sameRevision = false): array
    {
        $current = $this->read($connection, $folder, $expected['file_id']);
        abort_unless($current['exercise_folder_id'] === $expected['exercise_folder_id']
            && (! $sameRevision || $current['revision'] === $expected['revision']),
            409, 'Die zusätzliche Testordnerkennung wurde geändert. Bitte die Ordnerzuordnung erneut prüfen.');

        return $current;
    }

    private function read(DropboxConnection $connection, array $folder, string $fileId): array
    {
        $metadata = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $fileId])->json();
        $path = rtrim($folder['path_lower'], '/').'/handshake.json';
        if (($metadata['.tag'] ?? null) !== 'file' || ($metadata['id'] ?? null) !== $fileId
            || ($metadata['path_lower'] ?? null) !== $path || ! is_string($metadata['rev'] ?? null)
            || ! is_int($metadata['size'] ?? null) || $metadata['size'] < 1 || $metadata['size'] > 4096) {
            $this->fail('handshake.json muss direkt im Testordner liegen und eine kleine gültige Ordnerkennung enthalten.');
        }
        $contents = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$metadata['rev']])->body();
        if (strlen($contents) !== $metadata['size'] || ! mb_check_encoding($contents, 'UTF-8')) {
            $this->fail('Die zusätzliche Ordnerkennung konnte nicht vollständig gelesen werden.');
        }
        $after = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $fileId])->json();
        abort_unless(($after['id'] ?? null) === $fileId && ($after['rev'] ?? null) === $metadata['rev']
            && ($after['path_lower'] ?? null) === $path && ($after['size'] ?? null) === $metadata['size'],
            409, 'Die Ordnerkennung wurde während des Lesens geändert. Bitte erneut prüfen.');

        return ['exercise_folder_id' => $this->parse($contents)['exercise_folder_id'], 'file_id' => $fileId, 'revision' => $metadata['rev']];
    }

    /** The local file supplies only an identity to look up online. */
    public function parse(string $contents): array
    {
        if (strlen($contents) > 4096 || ! mb_check_encoding($contents, 'UTF-8')) {
            $this->fail('handshake.json muss eine UTF-8-Datei mit höchstens 4 KB sein.');
        }
        try {
            $data = json_decode(str_starts_with($contents, "\xEF\xBB\xBF") ? substr($contents, 3) : $contents,
                true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->fail('handshake.json enthält keine lesbare Testordnerkennung.');
        }
        if (! is_array($data) || count($data) !== 3 || ($data['schema_version'] ?? null) !== 1
            || ($data['kind'] ?? null) !== 'schooltool.exercise_folder' || ! is_string($data['exercise_folder_id'] ?? null)
            || preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $data['exercise_folder_id']) !== 1) {
            $this->fail('handshake.json enthält keine unterstützte Testordnerkennung. Eine Lernmodulkennung ist hier nicht ausreichend.');
        }
        preg_match_all('/"(?:[^"\\\\]|\\\\.)*"\s*:/s', $contents, $matches);
        $keys = array_map(fn (string $match): string => json_decode(rtrim(substr(rtrim($match), 0, -1)), flags: JSON_THROW_ON_ERROR), $matches[0]);
        if (count($keys) !== 3 || count(array_unique($keys)) !== 3) {
            $this->fail('handshake.json enthält doppelte oder zusätzliche Felder.');
        }

        return ['exercise_folder_id' => strtolower($data['exercise_folder_id'])];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['folder_id' => $message]);
    }
}
