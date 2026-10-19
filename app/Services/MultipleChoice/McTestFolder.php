<?php

namespace App\Services\MultipleChoice;

use App\Models\DropboxConnection;
use App\Models\McTest;
use App\Models\User;
use App\Services\DropboxWorkImport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class McTestFolder
{
    public function __construct(private DropboxWorkImport $dropbox, private McTestExchange $exchange) {}

    public function status(McTest $test, User $actor): array
    {
        $ready = $this->dropbox->configured() && $this->dropbox->installed();
        $connection = $ready ? DropboxConnection::where('user_id', $actor->id)->whereNull('revoked_at')->first() : null;
        $folder = $this->binding($test, $actor);
        if (($folder['provider'] ?? null) === 'dropbox' && (! $connection || $folder['account_hash'] !== hash('sha256', $connection->account_id))) {
            $folder = null;
        }

        return ['configured' => $ready, 'connected' => $connection !== null,
            'writable' => $connection && $this->dropbox->canWrite($connection),
            'folder' => $folder ? array_intersect_key($folder, array_flip(['provider', 'folder_id', 'name'])) : null,
            'filename' => $this->filename($test), 'identity' => $this->exchange->identity($test)];
    }

    public function bind(McTest $test, User $actor, array $data): array
    {
        $binding = ['identity' => $this->exchange->identity($test), 'provider' => $data['provider']];
        if ($data['provider'] === 'dropbox') {
            $connection = $this->dropbox->connection($actor);
            $folder = $this->dropbox->metadata($connection, $data['folder_id']);
            $plausibility = $this->dropbox->testFolderPlausibility($test, $folder['name']);
            if ($plausibility['status'] === 'mismatch') {
                $this->fail($plausibility['message']);
            }
            $binding += ['folder_id' => $folder['id'], 'name' => $folder['name'], 'account_hash' => hash('sha256', $connection->account_id)];
        } else {
            $binding['name'] = $data['name'];
        }
        $this->store($test, $actor, $binding);

        $state = $this->status($test, $actor);
        if ($data['provider'] === 'dropbox') {
            $state['folder']['path'] = $folder['path_display'] ?? $folder['path_lower'];
        }

        return $state;
    }

    public function suggestions(McTest $test, User $actor): array
    {
        $state = $this->status($test, $actor);
        $folder = $state['folder'];
        if (($folder['provider'] ?? null) === 'local') {
            return ['folder' => $folder, 'reused' => true, 'suggestions' => [], 'suggested_folder' => null,
                'start_folder' => null, 'message' => 'Der gemerkte lokale Testordner wird wiederverwendet.'];
        }
        $connection = $this->dropbox->connection($actor);
        $warning = null;
        if ($folder) {
            try {
                $metadata = $this->dropbox->metadata($connection, $folder['folder_id']);
                $plausibility = $this->dropbox->testFolderPlausibility($test, $metadata['name']);
                if ($metadata['id'] === $folder['folder_id'] && $plausibility['status'] !== 'mismatch') {
                    return ['folder' => ['provider' => 'dropbox', 'folder_id' => $metadata['id'], 'name' => $metadata['name'],
                        'path' => $metadata['path_display'] ?? $metadata['path_lower']], 'reused' => true,
                        'suggestions' => [], 'suggested_folder' => null, 'start_folder' => null,
                        'message' => 'Der gemerkte Dropbox-Testordner ist erreichbar und wird wiederverwendet.'];
                }
                $warning = 'Der gemerkte Ordner passt nicht mehr zur aktuellen Teilnehmergruppe. Bitte neu zuordnen.';
            } catch (ValidationException) {
                $warning = 'Der gemerkte Dropbox-Testordner konnte nicht bestätigt werden. Bitte neu zuordnen.';
            }
        }
        $result = $this->dropbox->suggestTestFolders($connection, $test);

        return $result + ['folder' => null, 'reused' => false, 'warning' => $warning];
    }

    public function read(McTest $test, User $actor): array
    {
        $folder = $this->requireDropbox($test, $actor);
        $file = $this->dropbox->readDocument($this->dropbox->connection($actor), $folder['folder_id'], $this->filename($test));
        if ($file === null) {
            $this->fail('Im gemerkten Dropbox-Ordner fehlt der Testexport. Bitte zuerst den aktuellen Teststand exportieren.');
        }

        return $this->decode($file['contents']);
    }

    public function verifyWrite(McTest $test, User $actor): array
    {
        $folder = $this->requireDropbox($test, $actor);

        return $this->dropbox->verifyWrite($this->dropbox->connection($actor), $folder['folder_id']);
    }

    public function export(McTest $test, User $actor, array $links, ?array $previous, bool $toDropbox): array
    {
        return Cache::store('file')->lock('mc-test-folder:'.$actor->school_id.':'.$actor->id.':'.$test->id, 90)->block(3,
            function () use ($test, $actor, $links, $previous, $toDropbox): array {
                $folder = $this->binding($test, $actor);
                $revision = null;
                if ($toDropbox) {
                    $folder = $this->requireDropbox($test, $actor);
                    $file = $this->dropbox->readDocument($this->dropbox->connection($actor), $folder['folder_id'], $this->filename($test));
                    $revision = $file['rev'] ?? null;
                    $previous = $file === null ? null : $this->decode($file['contents']);
                }
                $oldDocument = $previous['exchange'] ?? $previous;
                if ($oldDocument !== null) {
                    if (! is_array($oldDocument) || ! is_array($oldDocument['results'] ?? null)) {
                        $this->fail('Die vorhandene Testdatei enthält keinen vollständigen Ergebnisstand. Bitte die Datei prüfen.');
                    }
                    $current = $this->exchange->export($test);
                    $oldResults = array_column($oldDocument['results'] ?? [], 'revision', 'participant');
                    $newResults = array_column($current['results'], 'revision', 'participant');
                    ksort($oldResults);
                    ksort($newResults);
                    if (($oldDocument['identity'] ?? null) !== $current['identity']
                        || (($folder['last_hash'] ?? null) !== $this->exchange->hash($previous) && $oldResults !== $newResults)) {
                        $this->fail('Die Ordnerdatei enthält einen anderen oder extern geänderten Stand. Bitte zuerst importieren und die Vorschau prüfen.');
                    }
                }
                $document = $this->exchange->export($test, $oldDocument);
                $package = $links ?: ['kind' => 'schooltool.multiple_choice_exchange', 'schema_version' => 1];
                $package['exchange'] = $document;
                if ($toDropbox) {
                    $contents = json_encode($package, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
                    $this->dropbox->writeDocument($this->dropbox->connection($actor), $folder['folder_id'], $this->filename($test), $contents, $revision);
                    $folder['last_hash'] = $this->exchange->hash($package);
                    $this->store($test, $actor, $folder);

                    return ['saved' => true, 'filename' => $this->filename($test)];
                }

                return ['package' => $package, 'filename' => $this->filename($test)];
            });
    }

    public function acknowledge(McTest $test, User $actor, array $package): void
    {
        if ((($package['exchange'] ?? $package)['identity'] ?? null) !== $this->exchange->identity($test)) {
            $this->fail('Dieser Teststand gehört nicht zur gemerkten Verbindung.');
        }
        $folder = $this->binding($test, $actor);
        if ($folder) {
            $folder['last_hash'] = $this->exchange->hash($package);
            $this->store($test, $actor, $folder);
        }
    }

    private function binding(McTest $test, User $actor): ?array
    {
        $contents = Storage::disk('local')->get($this->path($test, $actor));
        if ($contents === null) {
            return null;
        }
        $binding = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        return ($binding['identity'] ?? null) === $this->exchange->identity($test) ? $binding : null;
    }

    private function requireDropbox(McTest $test, User $actor): array
    {
        $folder = $this->binding($test, $actor);
        $connection = $this->dropbox->connection($actor);
        if (($folder['provider'] ?? null) !== 'dropbox' || ($folder['account_hash'] ?? null) !== hash('sha256', $connection->account_id)) {
            $this->fail('Bitte für diesen Test einen eigenen Dropbox-Ordner zuordnen.');
        }

        return $folder;
    }

    private function store(McTest $test, User $actor, array $binding): void
    {
        if (! Storage::disk('local')->put($this->path($test, $actor), json_encode($binding, JSON_THROW_ON_ERROR))) {
            $this->fail('Die Ordnerzuordnung konnte nicht dauerhaft gespeichert werden.');
        }
    }

    private function path(McTest $test, User $actor): string
    {
        return "multiple-choice/test-folders/{$actor->school_id}/{$actor->id}/{$test->id}.json";
    }

    private function filename(McTest $test): string
    {
        return 'MC-Test_'.substr($this->exchange->identity($test), 0, 24).'.json';
    }

    private function decode(string $contents): array
    {
        if (strlen($contents) > 6 * 1024 * 1024) {
            $this->fail('Die Testdatei ist größer als 6 MiB.');
        }
        try {
            $data = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->fail('Die Testdatei enthält kein lesbares JSON.');
        }
        if (! is_array($data)) {
            $this->fail('Die Testdatei enthält keinen Teststand.');
        }

        return $data;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['folder' => $message]);
    }
}
