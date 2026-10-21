<?php

namespace App\Services\MultipleChoice;

use App\Models\DropboxConnection;
use App\Models\McTest;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\DropboxWorkImport;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;

class McTestFolderImport
{
    private const FILENAME = 'Schooltool_Arbeitsdaten.json';

    private const CANONICAL_SOURCE = 'schülerlisten/quelle_schooltool_arbeitsdaten.json';

    private const MAX_BYTES = 1024 * 1024;

    public function __construct(private DropboxWorkImport $dropbox, private McImportService $imports) {}

    /** Resolve a local identity against a bounded online search, never local roster data. */
    public function resolve(User $actor, string $contents): array
    {
        $this->authorize($actor);
        $identities = app(McExerciseFolderIdentity::class);
        $local = $identities->parse($contents);
        $connection = $this->dropbox->connection($actor);
        $data = $this->dropbox->readModuleApi($connection, 'files/search_v2', ['query' => 'handshake.json',
            'options' => ['filename_only' => true, 'max_results' => 100, 'file_status' => 'active', 'file_extensions' => ['json']]])->json();
        $entries = [];
        for ($page = 0; ; $page++) {
            if (! is_array($data['matches'] ?? null) || ! is_bool($data['has_more'] ?? null)) {
                $this->fail('Die Dropbox-Suche wurde nicht vollständig bestätigt. Bitte erneut versuchen.');
            }
            foreach ($data['matches'] as $match) {
                $entry = $match['metadata']['metadata'] ?? [];
                if (($entry['.tag'] ?? null) === 'file' && strcasecmp($entry['name'] ?? '', 'handshake.json') === 0
                    && $this->isId($entry['id'] ?? null) && is_string($entry['path_lower'] ?? null)
                    && $this->isExerciseHandshakePath($entry['path_lower'])) {
                    $entries[$entry['id']] = $entry;
                }
            }
            if (count($entries) > 40) {
                $this->fail('Zu viele Testordnerkennungen für eine begrenzte sichere Suche. Es wurde nichts verknüpft.');
            }
            if (! $data['has_more']) {
                break;
            }
            if ($page >= 2 || ! is_string($data['cursor'] ?? null) || $data['cursor'] === '') {
                $this->fail('Die Dropbox-Suche ist noch unvollständig. Bitte später erneut versuchen.');
            }
            $data = $this->dropbox->readModuleApi($connection, 'files/search/continue_v2', ['cursor' => $data['cursor']])->json();
        }
        $matches = [];
        foreach ($entries as $entry) {
            if (! is_int($entry['size'] ?? null) || $entry['size'] < 1 || $entry['size'] > 4096) {
                continue;
            }
            $path = dirname($entry['path_lower']);
            $folder = $this->dropbox->metadata($connection, $path);
            abort_unless($folder['path_lower'] === $path && $this->isId($folder['id']), 409,
                'Ein gefundener Testordner wurde inzwischen verschoben. Bitte erneut versuchen.');
            try {
                $identity = $identities->fromChildren($connection, $folder, [$entry]);
            } catch (ValidationException $exception) {
                if (isset($exception->errors()['folder_id'])) {
                    continue;
                }
                throw $exception;
            }
            if ($identity['exercise_folder_id'] === $local['exercise_folder_id']) {
                $matches[$folder['id']] = $identity;
            }
        }
        abort_if(count($matches) > 1, 409,
            'Mehrere Dropbox-Testordner verwenden dieselbe Ordnerkennung. Bitte die kopierte Kennung klären; es wurde nichts verknüpft.');
        if ($matches === []) {
            $this->fail('Noch kein passender Online-Testordner bestätigt. Die Dropbox-Suche kann verzögert sein. Bitte später erneut versuchen.');
        }
        $preview = $this->preview($actor, array_key_first($matches));
        abort_unless(($preview['exercise_folder_id'] ?? null) === $local['exercise_folder_id'], 409,
            'Die Online-Ordnerkennung wurde geändert. Bitte die Datei erneut auswählen.');

        return $preview;
    }

    /** @return array{folders: array, cursor: ?string, parent: ?array, current: ?array} */
    public function folders(User $actor, string $folderId = '', ?string $cursor = null): array
    {
        $this->authorize($actor);
        $this->validateFolderId($folderId, true);
        $connection = $this->dropbox->connection($actor);
        $rawCursor = $cursor === null ? null : $this->decodeCursor($actor, $connection, $folderId, $cursor);
        $listing = $this->dropbox->folders($connection, $folderId, $rawCursor);
        abort_if($folderId !== '' && ($listing['current']['id'] ?? null) !== $folderId,
            409, 'Der ausgewählte Dropbox-Ordner wurde nicht eindeutig bestätigt. Bitte erneut auswählen.');
        if ($listing['cursor'] !== null) {
            $listing['cursor'] = Crypt::encryptString(json_encode(['kind' => 'mc-test-folder-cursor',
                'user_id' => $actor->id, 'school_id' => $actor->school_id, 'connection_id' => $connection->id,
                'account_id' => $connection->account_id, 'folder_id' => $folderId,
                'cursor' => $listing['cursor'], 'expires' => now()->addMinutes(30)->timestamp], JSON_THROW_ON_ERROR));
        }

        return $listing;
    }

    /** @return array<string, mixed> */
    public function preview(User $actor, string $folderId): array
    {
        $this->authorize($actor);
        $this->validateFolderId($folderId);
        $connection = $this->dropbox->connection($actor);
        $folder = $this->dropbox->metadata($connection, $folderId);
        abort_unless($folder['id'] === $folderId, 409, 'Der ausgewählte Dropbox-Ordner wurde nicht bestätigt.');
        $children = $this->children($connection, $folderId);
        $identity = app(McExerciseFolderIdentity::class)->fromChildren($connection, $folder, $children);
        [$source, $relativePath] = $this->sourceFile($connection, $folder, $children);
        $fileId = $source['id'];
        $file = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $fileId])->json();
        $expectedPath = rtrim($folder['path_lower'], '/').'/'.$relativePath;
        if (($file['.tag'] ?? null) !== 'file' || ($file['id'] ?? null) !== $fileId
            || ($file['path_lower'] ?? null) !== $expectedPath
            || mb_strtolower($file['name'] ?? '') !== basename($relativePath)
            || ! is_string($file['rev'] ?? null) || $file['rev'] === ''
            || ! is_int($file['size'] ?? null) || $file['size'] < 1 || $file['size'] > self::MAX_BYTES) {
            $this->fail('Die Arbeitsdaten-Datei muss im Testordner oder an der vorgesehenen Stelle in Schülerlisten liegen und darf höchstens 1 MB groß sein.');
        }
        $contents = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$file['rev']])->body();
        if (strlen($contents) !== $file['size'] || strlen($contents) > self::MAX_BYTES || ! mb_check_encoding($contents, 'UTF-8')) {
            $this->fail('Die Arbeitsdaten-Datei wurde nicht vollständig als UTF-8 gelesen. Bitte erneut versuchen.');
        }
        $after = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $fileId])->json();
        $folderAfter = $this->dropbox->metadata($connection, $folderId);
        abort_unless(($after['.tag'] ?? null) === 'file' && ($after['id'] ?? null) === $fileId
            && ($after['rev'] ?? null) === $file['rev'] && ($after['size'] ?? null) === $file['size']
            && ($after['path_lower'] ?? null) === $expectedPath && $folderAfter['id'] === $folderId
            && $folderAfter['path_lower'] === $folder['path_lower'],
            409, 'Testordner oder Arbeitsdaten wurden während des Lesens geändert. Bitte den Ordner erneut übernehmen.');
        try {
            $export = json_decode(str_starts_with($contents, "\xEF\xBB\xBF") ? substr($contents, 3) : $contents,
                true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->fail(self::FILENAME.' enthält keine lesbaren JSON-Arbeitsdaten.');
        }
        if (! is_array($export) || ($export['kind'] ?? null) !== 'schooltool.ai-work-export'
            || ($export['schema_version'] ?? null) !== 1 || ! is_array($export['course_roster']['participants'] ?? null)
            || ! array_is_list($export['course_roster']['participants'])) {
            $this->fail('Bitte einen Schooltool-Arbeitsdatenexport mit vollständiger Kursliste verwenden.');
        }
        $this->validateExport($actor, $export);
        $owned = TeachingCourseWork::query()->whereKey($export['work']['id'])
            ->where('teaching_course_id', $export['course']['id'])
            ->whereHas('teachingCourse', fn ($query) => $query->where('user_id', $actor->id)
                ->where('school_id', $actor->school_id)->where('schoolyear_id', $actor->schoolyear_id))->exists();
        abort_unless($owned, 404, 'Die Arbeit gehört nicht zu Ihren Kursen in der ausgewählten Schule und im ausgewählten Schuljahr.');
        $exerciseMetadata = $this->exerciseMetadata($connection, $folder, $children, $export);

        $roster = $this->imports->workRosterPreview($export, (int) $actor->school_id);
        $proof = ['kind' => 'mc-test-folder', 'user_id' => $actor->id, 'school_id' => $actor->school_id,
            'schoolyear_id' => $actor->schoolyear_id, 'connection_id' => $connection->id, 'account_id' => $connection->account_id,
            'folder_id' => $folderId, 'folder_path' => $folderAfter['path_lower'], 'file_id' => $fileId,
            'revision' => $file['rev'], 'size' => $file['size'], 'csv_hash' => hash('sha256', Str::trim($roster['csv'])),
            'exercise_folder_identity' => $identity, 'source_relative_path' => $relativePath,
            'exercise_metadata' => $exerciseMetadata,
            'expires' => now()->addMinutes(30)->timestamp];

        return $roster + [
            'directory' => $folderAfter['name'], 'path' => $folderAfter['path_display'] ?? $folderAfter['path_lower'],
            'folder_id' => $folderId, 'file_id' => $fileId, 'revision' => $file['rev'],
            'exercise_folder_id' => $identity['exercise_folder_id'] ?? null,
            'folder_proof' => Crypt::encryptString(json_encode($proof, JSON_THROW_ON_ERROR)),
        ];
    }

    /** @return array<string, mixed> */
    public function verify(User $actor, string $token, string $rosterCsv): array
    {
        $this->authorize($actor);
        $proof = $this->decodeProof($actor, $token);
        abort_unless(hash_equals($proof['csv_hash'], hash('sha256', Str::trim($rosterCsv))),
            409, 'Die Schülerliste wurde seit der Ordnerauswahl geändert. Bitte den Dropbox-Ordner erneut prüfen oder die Auswahl aufheben.');
        $this->verifySource($actor, $proof);
        $fresh = $this->preview($actor, $proof['folder_id']);
        $current = $this->decodeProof($actor, $fresh['folder_proof']);
        foreach (['connection_id', 'account_id', 'folder_id', 'folder_path', 'file_id', 'revision', 'size', 'csv_hash', 'exercise_folder_identity', 'source_relative_path', 'exercise_metadata'] as $key) {
            abort_unless(($current[$key] ?? null) === ($proof[$key] ?? null), 409,
                'Testordner, Arbeitsdaten oder Schülerliste haben sich geändert. Bitte den Ordner erneut prüfen.');
        }
        abort_unless($fresh['valid'] ?? false, 422, 'Die Schülerliste im Testordner ist nicht mehr vollständig zuordenbar.');

        return $proof;
    }

    /** Persist only while the caller's new test transaction is still open. */
    public function bind(McTest $test, User $actor, array $proof): array
    {
        abort_unless((int) $test->owner_id === (int) $actor->id && (int) $test->school_id === (int) $actor->school_id
            && (int) $test->schoolyear_id === (int) $actor->schoolyear_id, 404);
        $this->verifySource($actor, $proof);
        $folders = app(McTestFolder::class);
        $identity = $folders->status($test, $actor)['identity'];
        try {
            $binding = $folders->bind($test, $actor, ['provider' => 'dropbox', 'folder_id' => $proof['folder_id'],
                'exercise_folder_identity' => $proof['exercise_folder_identity'] ?? null]);
            $this->verifySource($actor, $proof);

            return $binding;
        } catch (\Throwable $exception) {
            $folders->forgetProvisionalBinding($test, $actor, $identity);

            throw $exception;
        }
    }

    private function decodeProof(User $actor, string $token): array
    {
        try {
            $proof = json_decode(Crypt::decryptString($token), true, 16, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            abort(409, 'Die Testordner-Auswahl ist nicht mehr gültig. Bitte erneut auswählen.');
        }
        abort_unless(($proof['kind'] ?? null) === 'mc-test-folder' && ($proof['user_id'] ?? null) === $actor->id
            && ($proof['school_id'] ?? null) === $actor->school_id && ($proof['schoolyear_id'] ?? null) === $actor->schoolyear_id
            && ($proof['expires'] ?? 0) >= now()->timestamp && is_string($proof['csv_hash'] ?? null),
            409, 'Konto, Schule, Schuljahr oder Testordner-Auswahl haben sich geändert. Bitte erneut auswählen.');

        return $proof;
    }

    private function verifySource(User $actor, array $proof): void
    {
        $connection = $this->dropbox->connection($actor);
        abort_unless($connection->id === $proof['connection_id'] && $connection->account_id === $proof['account_id'],
            409, 'Das Dropbox-Konto wurde seit der Ordnerauswahl geändert. Bitte erneut auswählen.');
        $folder = $this->dropbox->metadata($connection, $proof['folder_id']);
        $file = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $proof['file_id']])->json();
        $relativePath = $proof['source_relative_path'] ?? mb_strtolower(self::FILENAME);
        abort_unless(in_array($relativePath, [mb_strtolower(self::FILENAME), self::CANONICAL_SOURCE], true), 409);
        abort_unless($folder['id'] === $proof['folder_id'] && $folder['path_lower'] === $proof['folder_path']
            && ($file['.tag'] ?? null) === 'file' && ($file['id'] ?? null) === $proof['file_id']
            && ($file['rev'] ?? null) === $proof['revision'] && ($file['size'] ?? null) === $proof['size']
            && ($file['path_lower'] ?? null) === rtrim($proof['folder_path'], '/').'/'.$relativePath,
            409, 'Testordner oder Arbeitsdaten wurden seit der Auswahl geändert. Bitte den Ordner erneut prüfen.');
        if (isset($proof['exercise_folder_identity'])) {
            app(McExerciseFolderIdentity::class)->verify($connection, $folder, $proof['exercise_folder_identity'], true);
        }
        if (isset($proof['exercise_metadata'])) {
            $expected = $proof['exercise_metadata'];
            $metadata = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $expected['file_id']])->json();
            abort_unless(($metadata['.tag'] ?? null) === 'file' && ($metadata['id'] ?? null) === $expected['file_id']
                && ($metadata['rev'] ?? null) === $expected['revision'] && ($metadata['size'] ?? null) === $expected['size']
                && ($metadata['path_lower'] ?? null) === rtrim($folder['path_lower'], '/').'/anlage_und_quellen.json',
                409, 'Die Angaben zur Leistungsfeststellung wurden seit der Auswahl geändert. Bitte den Ordner erneut prüfen.');
        }
    }

    private function isExerciseHandshakePath(string $path): bool
    {
        if (preg_match('#/leistungsfeststellung/[^/\x00-\x1f]+/handshake\.json\z#u', $path) !== 1) {
            return false;
        }
        $roots = config('services.dropbox.module_material_roots', []);
        if ($roots !== []) {
            foreach ($roots as $root) {
                if (is_string($root) && str_starts_with($root, '/')
                    && str_starts_with($path, rtrim(mb_strtolower($root), '/').'/')) {
                    return true;
                }
            }

            return false;
        }

        return preg_match('#\A/(?:[^\x00-\x1f]*/)?materialien/lerninhalte/#u', $path) === 1;
    }

    /** Only the legacy root export and the canonical create-exercise source are eligible. */
    private function sourceFile(DropboxConnection $connection, array $folder, array $children): array
    {
        $sources = [];
        foreach ($children as $entry) {
            if (($entry['.tag'] ?? null) === 'file' && strcasecmp($entry['name'] ?? '', self::FILENAME) === 0) {
                $sources[] = [$entry, mb_strtolower(self::FILENAME)];
            }
            if (($entry['.tag'] ?? null) !== 'folder' || mb_strtolower($entry['name'] ?? '') !== 'schülerlisten') {
                continue;
            }
            $studentFolder = $this->dropbox->metadata($connection, $entry['id']);
            abort_unless($studentFolder['id'] === $entry['id']
                && $studentFolder['path_lower'] === rtrim($folder['path_lower'], '/').'/schülerlisten',
                409, 'Der Schülerlisten-Ordner wurde verschoben. Bitte erneut auswählen.');
            foreach ($this->children($connection, $entry['id']) as $child) {
                if (($child['.tag'] ?? null) === 'file'
                    && strcasecmp($child['name'] ?? '', 'Quelle_Schooltool_Arbeitsdaten.json') === 0) {
                    $sources[] = [$child, self::CANONICAL_SOURCE];
                }
            }
        }
        if (count($sources) !== 1) {
            $this->fail(count($sources) > 1
                ? 'Der Testordner enthält mehrere Arbeitsdatenquellen. Bitte die eindeutige Quelle klären; es wurde nichts verknüpft.'
                : 'Im Testordner fehlt Schooltool_Arbeitsdaten.json oder Schülerlisten/Quelle_Schooltool_Arbeitsdaten.json.');
        }

        return $sources[0];
    }

    /** @return array{file_id: string, revision: string, size: int}|null */
    private function exerciseMetadata(DropboxConnection $connection, array $folder, array $children, array $export): ?array
    {
        $matches = array_values(array_filter($children, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file'
            && strcasecmp($entry['name'] ?? '', 'Anlage_und_Quellen.json') === 0));
        if ($matches === []) {
            return null;
        }
        if (count($matches) !== 1) {
            $this->fail('Die Angaben zur Leistungsfeststellung sind nicht eindeutig.');
        }
        $id = $matches[0]['id'];
        $file = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $id])->json();
        $path = rtrim($folder['path_lower'], '/').'/anlage_und_quellen.json';
        if (($file['.tag'] ?? null) !== 'file' || ($file['id'] ?? null) !== $id || ($file['path_lower'] ?? null) !== $path
            || ! is_string($file['rev'] ?? null) || $file['rev'] === '' || ! is_int($file['size'] ?? null)
            || $file['size'] < 1 || $file['size'] > 256 * 1024) {
            $this->fail('Anlage_und_Quellen.json ist keine gültige kleine Metadatendatei im Testordner.');
        }
        $contents = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$file['rev']])->body();
        if (strlen($contents) !== $file['size'] || ! mb_check_encoding($contents, 'UTF-8')) {
            $this->fail('Die Angaben zur Leistungsfeststellung wurden nicht vollständig gelesen.');
        }
        $after = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $id])->json();
        abort_unless(($after['id'] ?? null) === $id && ($after['rev'] ?? null) === $file['rev']
            && ($after['size'] ?? null) === $file['size'] && ($after['path_lower'] ?? null) === $path,
            409, 'Die Angaben zur Leistungsfeststellung wurden während des Lesens geändert.');
        try {
            $data = json_decode(str_starts_with($contents, "\xEF\xBB\xBF") ? substr($contents, 3) : $contents,
                true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->fail('Anlage_und_Quellen.json enthält keine lesbaren Angaben zur Leistungsfeststellung.');
        }
        if (! is_array($data) || array_is_list($data)) {
            $this->fail('Anlage_und_Quellen.json enthält kein gültiges Metadatenobjekt.');
        }
        foreach (['Schooltool_Arbeit_ID' => $export['work']['id'], 'Schooltool_Kurs_ID' => $export['course']['id'],
            'Schooltool_Schule_ID' => $export['school']['id']] as $key => $expected) {
            if (array_key_exists($key, $data) && (! is_int($data[$key]) || $data[$key] !== (int) $expected)) {
                $this->fail('Die Angaben zur Leistungsfeststellung und die Arbeitsdaten gehören nicht zur selben Arbeit, zum selben Kurs oder zur selben Schule.');
            }
        }
        if (array_key_exists('Durchfuehrungsdatum', $data)
            && ($data['Durchfuehrungsdatum'] !== ($export['work']['scheduled_date'] ?? null) || ! is_string($data['Durchfuehrungsdatum']))) {
            $this->fail('Das Durchführungsdatum in Anlage_und_Quellen.json stimmt nicht mit den Arbeitsdaten überein.');
        }

        return ['file_id' => $id, 'revision' => $file['rev'], 'size' => $file['size']];
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->exists && $actor->school_id && $actor->hasAnyRole(['admin', 'teaching_admin', 'teacher']), 403);
    }

    private function validateFolderId(string $folderId, bool $allowRoot = false): void
    {
        if (($allowRoot && $folderId === '') || $this->isId($folderId)) {
            return;
        }
        $this->fail('Bitte einen Dropbox-Ordner aus der Ordnerauswahl wählen.');
    }

    private function isId(mixed $id): bool
    {
        return is_string($id) && strlen($id) <= 255 && preg_match('/\Aid:[A-Za-z0-9_-]+\z/', $id) === 1;
    }

    private function decodeCursor(User $actor, DropboxConnection $connection, string $folderId, string $cursor): string
    {
        try {
            $data = json_decode(Crypt::decryptString($cursor), true, 16, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            abort(409, 'Die Ordneransicht ist nicht mehr gültig. Bitte erneut öffnen.');
        }
        abort_unless(($data['kind'] ?? null) === 'mc-test-folder-cursor' && ($data['user_id'] ?? null) === $actor->id
            && ($data['school_id'] ?? null) === $actor->school_id && ($data['connection_id'] ?? null) === $connection->id
            && ($data['account_id'] ?? null) === $connection->account_id && ($data['folder_id'] ?? null) === $folderId
            && is_string($data['cursor'] ?? null) && ($data['expires'] ?? 0) >= now()->timestamp,
            409, 'Konto oder Ordneransicht hat sich geändert. Bitte erneut öffnen.');

        return $data['cursor'];
    }

    /** @return list<array<string, mixed>> */
    private function children(DropboxConnection $connection, string $folderId): array
    {
        $response = $this->dropbox->readModuleApi($connection, 'files/list_folder',
            ['path' => $folderId, 'recursive' => false, 'limit' => 200])->json();
        $entries = [];
        for ($page = 0; ; $page++) {
            if (! is_array($response['entries'] ?? null) || ! is_bool($response['has_more'] ?? null)) {
                $this->fail('Dropbox hat den Inhalt des Testordners nicht vollständig bestätigt.');
            }
            foreach ($response['entries'] as $entry) {
                if (! is_array($entry) || ! $this->isId($entry['id'] ?? null)) {
                    $this->fail('Dropbox hat einen ungültigen Ordnereintrag geliefert. Bitte erneut auswählen.');
                }
                $entries[$entry['id']] = $entry;
            }
            if (! $response['has_more']) {
                return array_values($entries);
            }
            if ($page >= 2 || ! is_string($response['cursor'] ?? null) || $response['cursor'] === '') {
                $this->fail('Der Testordner konnte nicht vollständig gelesen werden. Bitte einen kleineren Testordner auswählen.');
            }
            $response = $this->dropbox->readModuleApi($connection, 'files/list_folder/continue', ['cursor' => $response['cursor']])->json();
        }
    }

    private function validateExport(User $actor, array $export): void
    {
        Validator::make(['work_export' => $export], [
            'work_export.school.id' => ['required', 'integer', Rule::in([$actor->school_id])],
            'work_export.schoolyear.id' => ['required', 'integer', Rule::in([$actor->schoolyear_id])],
            'work_export.work.id' => ['required', 'integer', 'min:1'],
            'work_export.work.title' => ['required', 'string', 'max:255'],
            'work_export.work.scheduled_date' => ['nullable', 'date_format:Y-m-d'],
            'work_export.course.id' => ['required', 'integer', 'min:1'],
            'work_export.course.title' => ['required', 'string', 'max:255'],
            'work_export.course_roster.complete' => ['required', 'accepted'],
            'work_export.course_roster.participants' => ['required', 'array', 'min:1', 'max:1000'],
            'work_export.course_roster.participants.*' => ['required', 'array'],
            'work_export.course_roster.participants.*.schooltool_person_id' => ['nullable', 'integer', 'min:1'],
            'work_export.course_roster.participants.*.first_name' => ['required', 'string', 'max:255'],
            'work_export.course_roster.participants.*.last_name' => ['required', 'string', 'max:255'],
            'work_export.course_roster.participants.*.class_name' => ['nullable', 'string', 'max:255'],
            'work_export.course_roster.participants.*.active' => ['required', 'boolean'],
        ])->validate();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['folder_id' => $message]);
    }
}
