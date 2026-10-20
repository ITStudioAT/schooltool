<?php

namespace App\Services\MultipleChoice;

use App\Models\DropboxConnection;
use App\Models\User;
use App\Services\DropboxModuleLinks;
use App\Services\DropboxWorkImport;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;

class McModuleTemplateSource
{
    public function __construct(private DropboxModuleLinks $modules, private DropboxWorkImport $dropbox, private McImportService $imports) {}

    /** @return array<string, mixed> */
    public function preview(User $user, ?int $catalogId, string $handshake, ?string $sourceId): array
    {
        $connection = $this->dropbox->connection($user);
        $located = $this->modules->locate($connection, $handshake);
        if ($located['conflict'] ?? false) {
            $this->fail($located['message'].' '.implode(' · ', array_column($located['candidates'], 'folder_path')));
        }
        $module = $located['candidate'];
        $folders = array_values(array_filter($this->children($connection, $module['folder_id']),
            fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder' && strcasecmp($entry['name'] ?? '', 'Multiple Choice Fragen') === 0));
        if (count($folders) !== 1) {
            $this->fail('Im bestätigten Lernmodul fehlt der eindeutige Unterordner „Multiple Choice Fragen“.');
        }
        $folder = $this->dropbox->metadata($connection, $folders[0]['id']);
        $files = array_values(array_filter($this->children($connection, $folder['id']), fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file'
            && in_array(strtolower(pathinfo($entry['name'] ?? '', PATHINFO_EXTENSION)), ['md', 'txt', 'json'], true)));
        if (count($files) > 20 || array_sum(array_column($files, 'size')) > 4 * 1024 * 1024) {
            $this->fail('Der Fragenordner enthält zu viele oder zu große Vorlagen für eine begrenzte Prüfung.');
        }
        $sources = [];
        $previews = [];
        foreach ($files as $file) {
            if (($file['size'] ?? PHP_INT_MAX) > 1024 * 1024) {
                continue;
            }
            $document = $this->document($connection, $file['id']);
            abort_unless(dirname($document['path_lower']) === $folder['path_lower'], 409, 'Eine Vorlage wurde aus dem bestätigten Fragenordner verschoben. Bitte erneut prüfen.');
            $format = strtolower(pathinfo($document['name'], PATHINFO_EXTENSION)) === 'json' ? 'json' : 'markdown';
            $preview = $this->imports->preview($document['contents'], $format);
            if (($preview['questions'] ?? []) === []) {
                continue;
            }
            $sources[] = ['id' => $document['id'], 'name' => $document['name'], 'path' => $document['path_display'] ?? $document['path_lower'],
                'revision' => $document['rev'], 'format' => $format, 'questions_count' => count($preview['questions'])];
            $previews[$document['id']] = ['preview' => $preview, 'text' => $document['contents'], 'format' => $format];
        }
        if ($sources === []) {
            $this->fail('Im Unterordner „Multiple Choice Fragen“ wurde keine kompatible Markdown-, Text- oder JSON-Fragenvorlage gefunden.');
        }
        $result = ['module' => $module, 'sources' => $sources];
        if ($sourceId === null && count($sources) > 1) {
            return $result + ['message' => 'Mehrere Fragenvorlagen gefunden. Bitte eine Datei auswählen.'];
        }
        $sourceId ??= $sources[0]['id'];
        $selected = collect($sources)->firstWhere('id', $sourceId);
        abort_unless($selected, 422, 'Die ausgewählte Vorlage gehört nicht zum bestätigten Fragenordner.');
        $source = ['module_id' => $module['module_id'], 'module_folder_id' => $module['folder_id'], 'module_path' => $module['folder_path'],
            'source_file_id' => $selected['id'], 'source_revision' => $selected['revision'], 'source_path' => $selected['path']];
        $proof = Crypt::encryptString(json_encode(['kind' => 'mc-module-source', 'user_id' => $user->id, 'catalog_id' => $catalogId,
            'connection_id' => $connection->id, 'account_id' => $connection->account_id, 'module' => $module, 'source' => $source,
            'expires' => now()->addHours(2)->timestamp], JSON_THROW_ON_ERROR));

        return $result + $previews[$sourceId] + ['module_source' => $source, 'source_proof' => $proof];
    }

    /** @return array<string, mixed> */
    public function verify(User $user, ?int $catalogId, string $proof): array
    {
        try {
            $pending = json_decode(Crypt::decryptString($proof), true, 16, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            abort(409, 'Die Modulquelle ist nicht mehr bestätigt. Bitte die Handshake-Datei erneut prüfen.');
        }
        $connection = $this->dropbox->connection($user);
        abort_unless(($pending['kind'] ?? null) === 'mc-module-source' && ($pending['user_id'] ?? null) === $user->id
            && ($pending['catalog_id'] ?? null) === $catalogId && ($pending['connection_id'] ?? null) === $connection->id
            && ($pending['account_id'] ?? null) === $connection->account_id && ($pending['expires'] ?? 0) >= now()->timestamp,
            409, 'Katalog, Konto oder Modulquelle hat sich geändert. Bitte die Quelle erneut prüfen.');
        $module = $this->modules->inspect($connection, $pending['module']['handshake_file_id']);
        abort_unless($module === $pending['module'], 409, 'Das Lernmodul hat sich geändert. Bitte die Quelle erneut prüfen.');
        $source = $pending['source'];
        $metadata = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $source['source_file_id']])->json();
        abort_unless(($metadata['.tag'] ?? null) === 'file' && ($metadata['id'] ?? null) === $source['source_file_id']
            && ($metadata['rev'] ?? null) === $source['source_revision']
            && ($metadata['path_display'] ?? $metadata['path_lower'] ?? null) === $source['source_path'],
            409, 'Die Fragenvorlage wurde geändert oder verschoben. Bitte erneut einlesen; der Katalog wurde nicht gespeichert.');

        return $source;
    }

    /** @return list<array<string, mixed>> */
    private function children(DropboxConnection $connection, string $folderId): array
    {
        $listing = $this->dropbox->readModuleApi($connection, 'files/list_folder', ['path' => $folderId, 'recursive' => false, 'limit' => 200])->json();
        $entries = [];
        for ($page = 0; ; $page++) {
            foreach ($listing['entries'] ?? [] as $entry) {
                if (is_string($entry['id'] ?? null)) {
                    $entries[$entry['id']] = $entry;
                }
            }
            if (! ($listing['has_more'] ?? false)) {
                return array_values($entries);
            }
            if ($page >= 2 || ! is_string($listing['cursor'] ?? null)) {
                $this->fail('Der Fragenordner konnte nicht vollständig gelesen werden. Bitte erneut versuchen.');
            }
            $listing = $this->dropbox->readModuleApi($connection, 'files/list_folder/continue', ['cursor' => $listing['cursor']])->json();
        }
    }

    /** @return array<string, mixed> */
    private function document(DropboxConnection $connection, string $id): array
    {
        $entry = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $id])->json();
        abort_unless(($entry['.tag'] ?? null) === 'file' && ($entry['id'] ?? null) === $id
            && is_int($entry['size'] ?? null) && $entry['size'] <= 1024 * 1024
            && is_string($entry['rev'] ?? null) && is_string($entry['path_lower'] ?? null), 422, 'Die Fragenvorlage ist ungültig oder zu groß.');
        $contents = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$entry['rev']])->body();
        abort_unless(strlen($contents) === $entry['size'] && mb_check_encoding($contents, 'UTF-8'), 422, 'Die Fragenvorlage wurde nicht vollständig als UTF-8 gelesen.');
        $after = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $id])->json();
        abort_unless(($after['rev'] ?? null) === $entry['rev'] && ($after['path_lower'] ?? null) === $entry['path_lower'],
            409, 'Die Fragenvorlage wurde während des Lesens geändert. Bitte erneut versuchen.');

        return $entry + ['contents' => $contents];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['handshake' => $message]);
    }
}
