<?php

namespace App\Services\MultipleChoice;

use App\Models\DropboxConnection;
use App\Models\TeachingWorkModuleLink;
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

    /** @return array<string, mixed>|null */
    public function workCatalog(User $user, TeachingWorkModuleLink $link): ?array
    {
        $connection = $this->dropbox->connection($user);
        abort_unless($connection->id === $link->dropbox_connection_id && $connection->account_id === $link->account_id, 409);
        $module = $this->modules->inspect($connection, $link->handshake_file_id);
        abort_unless($module['folder_id'] === $link->folder_id && strcasecmp($module['module_id'], $link->module_id) === 0
            && strcasecmp($module['folder_path'], $link->folder_path) === 0 && $module['handshake_revision'] === $link->handshake_revision, 409);
        $folders = array_values(array_filter($this->children($connection, $module['folder_id']),
            fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
                && in_array(mb_strtolower($entry['name']), ['multiple choice fragen', 'fragenkatalog'], true)));
        abort_if(count($folders) > 1, 409, 'Mehrere Fragenordner gefunden.');
        if ($folders === []) {
            return null;
        }
        $folder = $this->dropbox->metadata($connection, $folders[0]['id']);
        abort_unless(dirname($folder['path_lower']) === mb_strtolower($module['folder_path']), 409);
        $files = array_values(array_filter($this->children($connection, $folder['id']),
            fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file' && strtolower(pathinfo($entry['name'], PATHINFO_EXTENSION)) === 'md'));
        abort_if(count($files) > 20 || array_sum(array_column($files, 'size')) > 4 * 1024 * 1024, 422);
        $pairs = [];
        foreach ($files as $answer) {
            if (! preg_match('/^(.*)_Antworten\.md$/iu', $answer['name'], $matches)) {
                continue;
            }
            $questionFiles = array_values(array_filter($files, fn (array $file): bool => mb_strtolower($file['name']) === mb_strtolower($matches[1].'.md')));
            abort_if(count($questionFiles) > 1, 409, 'Der Fragenkatalog ist nicht eindeutig.');
            if ($questionFiles === []) {
                continue;
            }
            $questions = $this->document($connection, $questionFiles[0]['id']);
            $solutions = $this->document($connection, $answer['id']);
            abort_unless(dirname($questions['path_lower']) === $folder['path_lower'] && dirname($solutions['path_lower']) === $folder['path_lower'], 409);
            $questionPreview = $this->imports->preview($questions['contents']);
            $answerPreview = $this->imports->preview($solutions['contents']);
            if (! $answerPreview['valid'] || $answerPreview['questions'] === []
                || count($questionPreview['questions']) !== count($answerPreview['questions'])) {
                continue;
            }
            $same = true;
            foreach ($answerPreview['questions'] as $index => $item) {
                $other = $questionPreview['questions'][$index];
                if (($item['id'] ?? null) !== ($other['id'] ?? null) || ($item['prompt'] ?? null) !== ($other['prompt'] ?? null)
                    || ($item['type'] ?? null) !== ($other['type'] ?? null) || ($item['options'] ?? null) !== ($other['options'] ?? null)
                    || ($other['correct_option_ids'] ?? []) !== []) {
                    $same = false;
                    break;
                }
            }
            if (! $same) {
                continue;
            }
            $source = ['module_id' => $module['module_id'], 'module_folder_id' => $module['folder_id'],
                'module_path' => $module['folder_path'], 'source_file_id' => $solutions['id'], 'source_revision' => $solutions['rev'],
                'source_path' => $solutions['path_display'] ?? $solutions['path_lower']];
            $fingerprint = hash('sha256', json_encode([$module, $questions['id'], $questions['rev'], $solutions['id'], $solutions['rev']], JSON_THROW_ON_ERROR));
            $pairs[] = ['questions' => $answerPreview['questions'], 'source' => $source, 'fingerprint' => $fingerprint];
        }
        abort_if(count($pairs) > 1, 409, 'Mehrere passende Fragenkataloge gefunden.');

        return $pairs[0] ?? null;
    }

    public function hasUsableQuestions(User $user, TeachingWorkModuleLink $link): bool
    {
        $connection = $this->dropbox->connection($user);
        abort_unless($connection->id === $link->dropbox_connection_id && $connection->account_id === $link->account_id,
            409, 'Die Modulverbindung gehört nicht zum aktuellen Dropbox-Konto.');
        $module = $this->modules->inspect($connection, $link->handshake_file_id);
        abort_unless($module['folder_id'] === $link->folder_id && strcasecmp($module['module_id'], $link->module_id) === 0
            && strcasecmp($module['folder_path'], $link->folder_path) === 0 && $module['handshake_revision'] === $link->handshake_revision,
            409, 'Die bestätigte Modulverbindung hat sich geändert. Bitte erneut prüfen.');
        $folders = array_values(array_filter($this->children($connection, $module['folder_id']),
            fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
                && in_array(mb_strtolower($entry['name'] ?? ''), ['multiple choice fragen', 'fragenkatalog'], true)));
        abort_if(count($folders) > 1, 409, 'Mehrere Fragenordner gefunden. Die Quelle ist nicht eindeutig.');
        if ($folders === []) {
            return false;
        }
        $folder = $this->dropbox->metadata($connection, $folders[0]['id']);
        abort_unless(dirname($folder['path_lower']) === mb_strtolower($module['folder_path']), 409, 'Der Fragenordner wurde verschoben.');
        $files = array_values(array_filter($this->children($connection, $folder['id']), fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file'
            && in_array(strtolower(pathinfo($entry['name'] ?? '', PATHINFO_EXTENSION)), ['md', 'txt', 'json'], true)));
        abort_if(count($files) > 20 || array_sum(array_column($files, 'size')) > 4 * 1024 * 1024, 422, 'Der Fragenordner ist für eine vollständige Prüfung zu groß.');
        foreach ($files as $file) {
            $document = $this->document($connection, $file['id']);
            abort_unless(dirname($document['path_lower']) === $folder['path_lower'], 409, 'Eine Fragenvorlage wurde verschoben.');
            $format = strtolower(pathinfo($document['name'], PATHINFO_EXTENSION)) === 'json' ? 'json' : 'markdown';
            $preview = $this->imports->preview($document['contents'], $format);
            if ($preview['valid'] && collect($preview['questions'])->contains(fn (mixed $question): bool => is_array($question) && ($question['active'] ?? false) === true)) {
                return true;
            }
        }

        return false;
    }

    public function assessmentPoints(User $user, TeachingWorkModuleLink $link): ?float
    {
        $connection = $this->dropbox->connection($user);
        abort_unless($connection->id === $link->dropbox_connection_id && $connection->account_id === $link->account_id, 409);
        $module = $this->modules->inspect($connection, $link->handshake_file_id);
        abort_unless($module['folder_id'] === $link->folder_id && strcasecmp($module['module_id'], $link->module_id) === 0
            && strcasecmp($module['folder_path'], $link->folder_path) === 0 && $module['handshake_revision'] === $link->handshake_revision, 409);
        $folders = array_values(array_filter($this->children($connection, $module['folder_id']),
            fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
                && in_array(mb_strtolower($entry['name']), ['prüfungsvorlage', 'leistungsfeststellung', 'assessment template', 'assessment_template'], true)));
        if (count($folders) !== 1) {
            return null;
        }
        $folder = $this->dropbox->metadata($connection, $folders[0]['id']);
        abort_unless(dirname($folder['path_lower']) === mb_strtolower($module['folder_path']), 409);
        $entries = $this->children($connection, $folder['id']);
        if (mb_strtolower($folder['name']) !== 'leistungsfeststellung'
            && collect($entries)->contains(fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder')) {
            return null;
        }
        $files = array_values(array_filter($entries, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file'
            && (in_array(strtolower(pathinfo($entry['name'], PATHINFO_EXTENSION)), ['md', 'txt'], true)
                || strcasecmp($entry['name'], 'Pruefungspunkte.json') === 0)));
        if ($files === [] || count($files) > 10 || array_sum(array_column($files, 'size')) > 4 * 1024 * 1024) {
            return null;
        }
        $points = [];
        $metadata = null;
        $taskPoints = [];
        foreach ($files as $file) {
            $document = $this->document($connection, $file['id']);
            abort_unless(dirname($document['path_lower']) === $folder['path_lower'], 409);
            if (strcasecmp($document['name'], 'Pruefungspunkte.json') === 0) {
                try {
                    $metadata = json_decode($document['contents'], true, 32, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    return null;
                }
                if (! is_array($metadata) || ($metadata['schema_version'] ?? null) !== 1
                    || ! is_numeric($metadata['gesamtpunkte'] ?? null) || ! is_array($metadata['aufgaben'] ?? null)
                    || ! array_is_list($metadata['aufgaben']) || $metadata['aufgaben'] === []) {
                    return null;
                }

                continue;
            }
            $contents = preg_replace('/[*_#]/u', '', $document['contents']);
            if (preg_match('/multiple[ -]choice|\\bmc[ -](?:test|teil|fragen)\\b/iu', $contents)) {
                return null;
            }
            preg_match_all('/^\h*(?:
                (?:gesamtpunkte|gesamtpunktzahl|punkte\h+gesamt|maximal(?:e|\h+erreichbare)?\h+punkte)
                (?:\h*:\h*|\h+)(\d+(?:[,.]\d{1,2})?)\h*(?:punkte?|p\.)?
                (?:\h+\(je\h+Aufgabe\h+\d+(?:[,.]\d{1,2})?\h+punkte?\))?
                | gesamt\h*:\h*(\d+(?:[,.]\d{1,2})?)\h*(?:punkte?|p\.)
            )\h*\r?$/imux', $contents, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $value = $match[1] !== '' ? $match[1] : $match[2];
                $points[] = (float) str_replace(',', '.', $value);
            }
            preg_match_all('/^\s*facts:\s*["\x27]?(\d+(?:[,.]\d{1,2})?)\s+Punkte["\x27]?\s*$/imu', $contents, $facts);
            foreach ($facts[1] as $value) {
                $points[] = (float) str_replace(',', '.', $value);
            }
            preg_match_all('/^\s*\|\s*\|\s*Gesamt\s*\|\s*(\d+(?:[,.]\d{1,2})?)\s*\|/imu', $contents, $totals);
            foreach ($totals[1] as $value) {
                $points[] = (float) str_replace(',', '.', $value);
            }
            preg_match_all('/^\s*\|\s*\|\s*Teilsumme Aufgabe\s+(\d+)\s*\|\s*(\d+(?:[,.]\d{1,2})?)\s*\|/imu', $contents, $subtotals, PREG_SET_ORDER);
            preg_match_all('/^\s*(\d+)\.\s+[^\r\n]+?<small>\s*\((\d+(?:[,.]\d{1,2})?)\s+Punkte?\)<\/small>/imu', $contents, $headings, PREG_SET_ORDER);
            foreach (array_merge($subtotals, $headings) as $task) {
                $taskPoints[(int) $task[1]][] = (float) str_replace(',', '.', $task[2]);
            }
        }
        $points = array_values(array_unique($points, SORT_REGULAR));
        if (count($points) !== 1) {
            return null;
        }
        if ($metadata !== null) {
            $tasks = [];
            foreach ($metadata['aufgaben'] as $task) {
                if (! is_array($task) || ! is_int($task['nummer'] ?? null) || $task['nummer'] < 1
                    || ! is_numeric($task['punkte'] ?? null) || (float) $task['punkte'] < 0 || isset($tasks[$task['nummer']])) {
                    return null;
                }
                $tasks[$task['nummer']] = (float) $task['punkte'];
            }
            if (round(array_sum($tasks), 2) !== round((float) $metadata['gesamtpunkte'], 2)
                || round((float) $metadata['gesamtpunkte'], 2) !== round($points[0], 2) || count($taskPoints) !== count($tasks)) {
                return null;
            }
            foreach ($taskPoints as $number => $values) {
                if (! isset($tasks[$number]) || count(array_unique($values, SORT_REGULAR)) !== 1
                    || round($values[0], 2) !== round($tasks[$number], 2)) {
                    return null;
                }
            }
        }

        return $points[0];
    }

    /** @return array<string, mixed> */
    public function preview(User $user, ?int $catalogId, string $handshake, ?string $sourceId): array
    {
        $connection = $this->dropbox->connection($user);
        $located = $this->modules->locate($connection, $handshake);
        if ($located['conflict'] ?? false) {
            $this->fail($located['message'].' '.implode(' · ', array_column($located['candidates'], 'folder_path')));
        }
        $module = $located['candidate'];
        $moduleChildren = $this->children($connection, $module['folder_id']);
        [$roots, $manifest] = $this->templateRoots($connection, $module, $moduleChildren);
        $sources = [];
        $previews = [];
        $files = [];
        foreach ($roots as $root) {
            $children = $root['id'] === $module['folder_id'] ? $moduleChildren : $this->children($connection, $root['id']);
            $folders = array_values(array_filter($children, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
                && in_array(mb_strtolower($entry['name'] ?? ''), ['multiple choice fragen', 'fragenkatalog'], true)));
            if (count($folders) > 1) {
                $this->fail('Im bestätigten Lernmodul sind mehrere mögliche Fragenordner vorhanden. Die Quelle ist nicht eindeutig.');
            }
            if ($folders === []) {
                continue;
            }
            $folder = $this->dropbox->metadata($connection, $folders[0]['id']);
            abort_unless(dirname($folder['path_lower']) === mb_strtolower($root['path']), 409, 'Ein Fragenordner wurde verschoben. Bitte erneut prüfen.');
            foreach ($this->children($connection, $folder['id']) as $entry) {
                if (($entry['.tag'] ?? null) === 'file' && in_array(strtolower(pathinfo($entry['name'] ?? '', PATHINFO_EXTENSION)), ['md', 'txt', 'json'], true)) {
                    $files[] = ['entry' => $entry, 'folder' => $folder, 'root' => $root];
                }
            }
        }
        if (count($files) > 20 || array_sum(array_map(fn (array $file): int => $file['entry']['size'] ?? PHP_INT_MAX, $files)) > 4 * 1024 * 1024) {
            $this->fail('Die Fragenordner enthalten zu viele oder zu große Vorlagen für eine begrenzte Prüfung.');
        }
        foreach ($files as $candidate) {
            $file = $candidate['entry'];
            $folder = $candidate['folder'];
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
            $previews[$document['id']] = ['preview' => $preview, 'text' => $document['contents'], 'format' => $format,
                'root' => $candidate['root']];
        }
        if ($sources === []) {
            $this->fail('Im bestätigten Lernmodul und seinen belegten Quellen wurde keine kompatible Fragenvorlage gefunden.');
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
        $root = $previews[$sourceId]['root'];
        unset($previews[$sourceId]['root']);
        if ($root['id'] !== $module['folder_id']) {
            $source['merge_manifest'] = $manifest;
            $source['source_module'] = $root;
        }
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
        if (isset($source['merge_manifest'])) {
            [$roots, $manifest] = $this->templateRoots($connection, $module, $this->children($connection, $module['folder_id']));
            abort_unless($manifest === $source['merge_manifest'] && in_array($source['source_module'] ?? null, $roots, true),
                409, 'Die Quellen des zusammengeführten Moduls haben sich geändert. Bitte erneut einlesen.');
        }
        $metadata = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $source['source_file_id']])->json();
        abort_unless(($metadata['.tag'] ?? null) === 'file' && ($metadata['id'] ?? null) === $source['source_file_id']
            && ($metadata['rev'] ?? null) === $source['source_revision']
            && ($metadata['path_display'] ?? $metadata['path_lower'] ?? null) === $source['source_path'],
            409, 'Die Fragenvorlage wurde geändert oder verschoben. Bitte erneut einlesen; der Katalog wurde nicht gespeichert.');

        return $source;
    }

    /** @return array{0: list<array{id: string, path: string}>, 1: array{id: string, rev: string}|null} */
    private function templateRoots(DropboxConnection $connection, array $module, array $children): array
    {
        $roots = [['id' => $module['folder_id'], 'path' => $module['folder_path']]];
        $manifests = array_values(array_filter($children, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file'
            && mb_strtolower($entry['name'] ?? '') === 'lernmodule-zusammenfuehrung.json'));
        abort_if(count($manifests) > 1, 409, 'Das Modulmanifest ist mehrdeutig.');
        if ($manifests === []) {
            return [$roots, null];
        }
        $document = $this->document($connection, $manifests[0]['id']);
        abort_unless(dirname($document['path_lower']) === mb_strtolower($module['folder_path']), 409, 'Das Modulmanifest wurde verschoben.');
        try {
            $data = json_decode($document['contents'], true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->fail('Das Modulmanifest ist ungültig.');
        }
        abort_unless(is_array($data) && ($data['schema_version'] ?? null) === 1 && ($data['type'] ?? null) === 'lernmodule-zusammenfuehrung'
            && ($data['target']['id'] ?? null) === $module['folder_id'] && is_string($data['target']['path'] ?? null)
            && mb_strtolower($data['target']['path']) === mb_strtolower($module['folder_path'])
            && is_array($data['sources'] ?? null) && array_is_list($data['sources']) && count($data['sources']) >= 1 && count($data['sources']) <= 20,
            422, 'Das Modulmanifest ist ungültig oder passt nicht zum bestätigten Lernmodul.');
        $materialRoot = null;
        foreach (config('services.dropbox.module_material_roots', []) as $configured) {
            if (is_string($configured) && str_starts_with(mb_strtolower($module['folder_path']), rtrim(mb_strtolower($configured), '/').'/')) {
                $materialRoot = rtrim(mb_strtolower($configured), '/');
                break;
            }
        }
        if ($materialRoot === null && config('services.dropbox.module_material_roots', []) === []
            && preg_match('#\A(.*/materialien/lerninhalte)/#u', mb_strtolower($module['folder_path']), $matches)) {
            $materialRoot = $matches[1];
        }
        abort_unless($materialRoot !== null, 409, 'Die Lerninhalte-Wurzel konnte nicht bestätigt werden.');
        foreach ($data['sources'] as $source) {
            abort_unless(is_array($source) && is_string($source['id'] ?? null) && is_string($source['path'] ?? null)
                && str_starts_with(mb_strtolower($source['path']), $materialRoot.'/')
                && ! preg_match('#(?:\A|/)(?:\.|\.\.)(?:/|\z)|[\x00-\x1f]#u', $source['path']),
                422, 'Eine Modulquelle liegt außerhalb des bestätigten Lerninhalte-Bestands.');
            $folder = $this->dropbox->metadata($connection, $source['id']);
            abort_unless($folder['id'] === $source['id'] && $folder['path_lower'] === mb_strtolower($source['path'])
                && ! collect($roots)->contains('id', $folder['id']), 409, 'Eine Modulquelle ist nicht eindeutig oder wurde verschoben.');
            $roots[] = ['id' => $folder['id'], 'path' => $folder['path_display'] ?? $folder['path_lower']];
        }

        return [$roots, ['id' => $document['id'], 'rev' => $document['rev']]];
    }

    /** @return list<array<string, mixed>> */
    private function children(DropboxConnection $connection, string $folderId): array
    {
        $listing = $this->dropbox->readModuleApi($connection, 'files/list_folder', ['path' => $folderId, 'recursive' => false, 'limit' => 200])->json();
        $entries = [];
        for ($page = 0; ; $page++) {
            abort_unless(is_array($listing['entries'] ?? null) && is_bool($listing['has_more'] ?? null), 422,
                'Der Fragenordner konnte nicht vollständig gelesen werden. Bitte erneut versuchen.');
            foreach ($listing['entries'] ?? [] as $entry) {
                abort_unless(is_array($entry) && is_string($entry['id'] ?? null) && is_string($entry['name'] ?? null)
                    && in_array($entry['.tag'] ?? null, ['file', 'folder'], true), 422,
                    'Der Fragenordner konnte nicht vollständig gelesen werden. Bitte erneut versuchen.');
                $entries[$entry['id']] = $entry;
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
