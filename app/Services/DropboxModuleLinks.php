<?php

namespace App\Services;

use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\TeachingWorkModuleLink;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class DropboxModuleLinks
{
    private const MAX_HANDSHAKE_FILES = 200;

    public function __construct(private DropboxWorkImport $dropbox, private ModuleHandshake $handshake) {}

    public function installed(): bool
    {
        return Schema::hasTable('teaching_work_module_links');
    }

    public function current(TeachingCourseWork $work): ?TeachingWorkModuleLink
    {
        return $this->installed() ? TeachingWorkModuleLink::query()->where('teaching_course_work_id', $work->id)->first() : null;
    }

    /** @return array{status: string, folder: ?array<string, string>, message?: string, checked_at?: ?string} */
    public function exercise(DropboxConnection $connection, TeachingCourseWork $work, TeachingWorkModuleLink $link, ?string $date, bool $refresh = false, bool $initial = true): array
    {
        if ($link->dropbox_connection_id !== $connection->id || $link->account_id !== $connection->account_id) {
            return ['status' => 'missing', 'folder' => null];
        }
        $key = 'teaching:exercise-check:v2:'.hash('sha256', serialize([$connection->id, $connection->account_id,
            $work->id, $work->teaching_course_id, $link->getAttributes()]));
        $schedule = hash('sha256', serialize([$work->date_for_all_groups?->format('Y-m-d'),
            app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work), $work->title,
            $work->finish_until_date?->format('Y-m-d'), $work->finish_until_time, $work->communicatedDeadline(),
            $work->teachingCourse->teachingCourseStudents()->with(['user:id,email,updated_at', 'import116:id,email,updated_at'])
                ->get(['id', 'user_id', 'import116_id', 'canceled_at', 'updated_at'])->toArray()]));
        $record = Cache::get($key);
        if ($refresh || ($record === null && $initial)) {
            $record = Cache::lock($key.':lock', 120)->block(55, function () use ($key, $schedule, $refresh, $connection, $work, $link): array {
                $existing = Cache::get($key);
                if (! $refresh && is_array($existing)) {
                    return $existing;
                }
                $record = ['schedule' => $schedule, 'checked_at' => now()->toIso8601String(),
                    'result' => $this->inspectExercises($connection, $work, $link)];
                Cache::forever($key, $record);

                return $record;
            });
        }
        if (! is_array($record) || ($record['schedule'] ?? null) !== $schedule) {
            return ['status' => 'unknown', 'folder' => null, 'checked_at' => $record['checked_at'] ?? null,
                'message' => 'Bitte den LF-Status einmal mit „Erneut prüfen“ bestätigen.'];
        }

        return $this->selectExercise($record['result'], $date, ['checked_at' => $record['checked_at']]);
    }

    /** @return array<string, mixed> */
    public function exerciseFolder(DropboxConnection $connection, TeachingCourseWork $work, TeachingWorkModuleLink $link, ?string $date): array
    {
        return $this->selectExercise($this->inspectExercises($connection, $work, $link, includeEmailStatus: false), $date);
    }

    /** @param array<string, mixed> $result
     * @param  array<string, mixed>  $checked
     * @return array<string, mixed>
     */
    private function selectExercise(array $result, ?string $date, array $checked = []): array
    {
        if ($result['status'] !== 'found') {
            return $result + $checked;
        }
        $matches = array_values(array_filter($result['matches'], fn (array $match): bool => $date === null || $match['date'] === $date));
        if (count($matches) > 1) {
            return ['status' => 'unknown', 'folder' => null, 'message' => 'Mehrere Leistungsfeststellungsordner passen. Bitte den konkreten Termin auswählen.'] + $checked;
        }

        return ($matches === [] ? ['status' => 'missing', 'folder' => null]
            : ['status' => 'found', 'folder' => $matches[0]['folder'],
                'assignment_status' => $matches[0]['assignment_status'] ?? 'unknown',
                'email_preparation' => $matches[0]['email_preparation'] ?? ['status' => 'unknown'],
                'email_dispatch' => $matches[0]['email_dispatch'] ?? ['status' => 'unknown']]) + $checked;
    }

    /** @return array<string, mixed> */
    private function inspectExercises(DropboxConnection $connection, TeachingCourseWork $work, TeachingWorkModuleLink $link, bool $includeEmailStatus = true): array
    {
        $missing = ['status' => 'missing', 'folder' => null];
        if ($link->dropbox_connection_id !== $connection->id || $link->account_id !== $connection->account_id) {
            return $missing;
        }
        try {
            $module = $this->inspect($connection, $link->handshake_file_id);
            abort_unless(strcasecmp($module['module_id'], $link->module_id) === 0 && $module['folder_id'] === $link->folder_id,
                409, 'Die Modulbindung konnte online nicht bestätigt werden.');
            $roots = array_values(array_filter($this->exerciseChildren($connection, $module['folder_id']),
                fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder' && strcasecmp($entry['name'] ?? '', 'Leistungsfeststellung') === 0));
            if ($roots === []) {
                return $missing;
            }
            abort_unless(count($roots) === 1, 409, 'Der Leistungsfeststellungsordner ist nicht eindeutig.');
            $root = $this->dropbox->metadata($connection, $roots[0]['id']);
            abort_unless(strcasecmp(dirname($root['path_lower']), $module['folder_path']) === 0, 409, 'Der Ordner gehört nicht mehr zum verbundenen Modul.');
            $folders = array_values(array_filter($this->exerciseChildren($connection, $root['id']),
                fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'));
            abort_if(count($folders) > 20, 422, 'Zu viele Leistungsfeststellungsordner für eine vollständige begrenzte Prüfung.');
            $dates = array_filter(array_merge([$work->date_for_all_groups?->format('Y-m-d')], array_column($work->groups ?? [], 'date')));
            $matches = [];
            $candidateChildren = $this->exerciseCandidateChildren($connection, $folders);
            $candidateRecords = $this->exerciseCandidateRecords($connection, $candidateChildren);
            foreach ($folders as $folder) {
                $children = $candidateChildren[$folder['id']];
                $record = $candidateRecords[$folder['id']] ?? null;
                if (! $record || ($record['Schooltool_Arbeit_ID'] ?? null) !== $work->id
                    || ($record['Schooltool_Kurs_ID'] ?? null) !== $work->teaching_course_id
                    || ($record['Schooltool_Schule_ID'] ?? null) !== $work->teachingCourse->school_id) {
                    continue;
                }
                $scheduled = $record['Durchfuehrungsdatum'] ?? null;
                if (! in_array($scheduled, $dates, true)) {
                    continue;
                }
                $binding = $record['Modulbindung'] ?? [];
                if (! is_array($binding) || ($binding['status'] ?? null) !== 'linked' || ! is_string($binding['module_id'] ?? null)
                    || ! is_string($binding['dropbox_path'] ?? null) || strcasecmp($binding['module_id'], $module['module_id']) !== 0
                    || ($binding['dropbox_folder_id'] ?? null) !== $module['folder_id']
                    || strcasecmp($binding['dropbox_path'] ?? '', $module['folder_path']) !== 0) {
                    continue;
                }
                $subfolders = array_column(array_filter($children, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'), 'id', 'name');
                foreach (['Schülerlisten', 'Angaben', 'Abgaben', 'Auswertungen', 'Transaktionen'] as $name) {
                    abort_unless(isset($subfolders[$name]), 409, 'Die passende Leistungsfeststellung ist noch nicht vollständig angelegt.');
                }
                $history = $this->exerciseJson($connection, $this->exerciseChildren($connection, $subfolders['Transaktionen']), 'History.json');
                abort_unless(($history['kind'] ?? null) === 'create-exercise.history' && ($history['schema_version'] ?? null) === 1
                    && is_string($record['Leistungsfeststellungsordner'] ?? null)
                    && basename(str_replace('\\', '/', $record['Leistungsfeststellungsordner'])) === $folder['name']
                    && is_string($history['binding']['folder'] ?? null)
                    && basename(str_replace('\\', '/', $history['binding']['folder'])) === $folder['name']
                    && ($history['binding']['work_id'] ?? null) === $work->id
                    && ($history['binding']['course_id'] ?? null) === $work->teaching_course_id
                    && ($history['binding']['school_id'] ?? null) === $work->teachingCourse->school_id
                    && is_array($history['binding'] ?? null) && is_array($history['entries'] ?? null)
                    && collect($history['entries'])->contains(fn (mixed $entry): bool => is_array($entry) && ($entry['action'] ?? null) === 'exercise_folder_created' && ($entry['result'] ?? null) === 'created'),
                    409, 'Die tatsächliche Anlage dieser Leistungsfeststellung konnte nicht bestätigt werden.');
                $confirmed = $this->dropbox->metadata($connection, $folder['id']);
                abort_unless(dirname($confirmed['path_lower']) === $root['path_lower'], 409, 'Die Leistungsfeststellung wurde inzwischen verschoben.');
                $match = ['date' => $scheduled, 'folder' => ['id' => $confirmed['id'], 'name' => $confirmed['name'],
                    'path' => $confirmed['path_display'] ?? $confirmed['path_lower']]];
                if ($includeEmailStatus) {
                    $match['assignment_status'] = $this->assignmentStatus($connection, $work, $children, $subfolders['Angaben'], $scheduled);
                    $match['email_preparation'] = $this->emailPreparation($connection, $work, $confirmed, $children, $scheduled);
                    $match['email_dispatch'] = $this->emailDispatch($connection, $work, $children);
                }
                $matches[] = $match;
            }
            abort_unless($this->current($work)?->getAttributes() === $link->getAttributes()
                && $connection->fresh()?->account_id === $link->account_id && ! $connection->fresh()?->revoked_at,
                409, 'Die Modulverbindung wurde während der Prüfung geändert.');

            return $matches === [] ? $missing : ['status' => 'found', 'matches' => $matches];
        } catch (ValidationException|HttpExceptionInterface|JsonException $exception) {
            return ['status' => 'unknown', 'folder' => null, 'message' => 'Anlagezustand nicht bestätigt. '.($exception instanceof ValidationException
                ? collect($exception->errors())->flatten()->first() : ($exception instanceof JsonException ? 'Ungültige LF-Metadaten.' : $exception->getMessage()))];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $folders
     * @return array<string, list<array<string, mixed>>>
     */
    private function exerciseCandidateChildren(DropboxConnection $connection, array $folders): array
    {
        $requests = [];
        $pages = [];
        $entries = [];
        foreach ($folders as $folder) {
            $id = $folder['id'];
            $requests[$id] = ['endpoint' => 'files/list_folder', 'parameters' => ['path' => $id, 'recursive' => false, 'limit' => 200]];
            $pages[$id] = 0;
            $entries[$id] = [];
        }

        while ($requests !== []) {
            $next = [];
            foreach ($this->dropbox->readModuleBatch($connection, $requests) as $id => $response) {
                $listing = $response->json();
                abort_unless(is_array($listing['entries'] ?? null) && is_bool($listing['has_more'] ?? null), 422, 'Dropbox hat keine vollständige Ordnerliste geliefert.');
                foreach ($listing['entries'] as $entry) {
                    abort_unless(is_string($entry['id'] ?? null), 422, 'Dropbox hat einen ungültigen Ordnereintrag geliefert.');
                    $entries[$id][$entry['id']] = $entry;
                }
                if ($listing['has_more']) {
                    abort_if($pages[$id] >= 2 || ! is_string($listing['cursor'] ?? null), 422, 'Die Leistungsfeststellungsordner konnten nicht vollständig geprüft werden.');
                    $pages[$id]++;
                    $next[$id] = ['endpoint' => 'files/list_folder/continue', 'parameters' => ['cursor' => $listing['cursor']]];
                }
            }
            $requests = $next;
        }

        return array_map(array_values(...), $entries);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $childrenByFolder
     * @return array<string, array<string, mixed>>
     */
    private function exerciseCandidateRecords(DropboxConnection $connection, array $childrenByFolder): array
    {
        $files = [];
        $downloads = [];
        foreach ($childrenByFolder as $folderId => $children) {
            $found = array_values(array_filter($children, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file'
                && strcasecmp($entry['name'] ?? '', 'Anlage_und_Quellen.json') === 0));
            if ($found === []) {
                continue;
            }
            abort_unless(count($found) === 1, 409, 'Mehrdeutige LF-Metadaten.');
            $file = $found[0];
            abort_unless(is_int($file['size'] ?? null) && $file['size'] <= 128 * 1024 && is_string($file['rev'] ?? null), 422, 'LF-Metadaten sind ungültig oder zu groß.');
            $files[$folderId] = $file;
            $downloads[$folderId] = ['endpoint' => 'files/download', 'parameters' => ['path' => 'rev:'.$file['rev']]];
        }

        $contents = [];
        foreach ($this->dropbox->readModuleBatch($connection, $downloads) as $folderId => $response) {
            $contents[$folderId] = $response->body();
            abort_unless(strlen($contents[$folderId]) === $files[$folderId]['size'], 422, 'LF-Metadaten wurden nicht vollständig gelesen.');
        }
        $checks = [];
        foreach ($files as $folderId => $file) {
            $checks[$folderId] = ['endpoint' => 'files/get_metadata', 'parameters' => ['path' => $file['id']]];
        }
        $records = [];
        foreach ($this->dropbox->readModuleBatch($connection, $checks) as $folderId => $response) {
            $file = $files[$folderId];
            $after = $response->json();
            abort_unless(($after['rev'] ?? null) === $file['rev'] && ($after['path_lower'] ?? null) === ($file['path_lower'] ?? null), 409, 'LF-Metadaten wurden während des Lesens geändert.');
            $record = json_decode($contents[$folderId], true, 64, JSON_THROW_ON_ERROR);
            abort_unless(is_array($record), 422, 'Ungültige LF-Metadaten.');
            $records[$folderId] = $record;
        }

        return $records;
    }

    /**
     * @param  array<string, array{entries: list<array<string, mixed>>, name: string, max_size: int}>  $requests
     * @return array<string, ?string>
     */
    private function exerciseFilesBatch(DropboxConnection $connection, array $requests, bool $hashesOnly = false): array
    {
        $files = [];
        $selected = [];
        $results = [];
        foreach ($requests as $key => $request) {
            $found = array_values(array_filter($request['entries'], fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file'
                && strcasecmp($entry['name'] ?? '', $request['name']) === 0));
            $results[$key] = null;
            if ($found === []) {
                continue;
            }
            abort_unless(count($found) === 1, 409, 'Mehrdeutige LF-Metadaten.');
            $file = $found[0];
            abort_unless(is_int($file['size'] ?? null) && $file['size'] <= $request['max_size'] && is_string($file['rev'] ?? null),
                422, 'LF-Metadaten sind ungültig oder zu groß.');
            $id = $file['id'];
            if (isset($files[$id])) {
                abort_unless($files[$id]['rev'] === $file['rev'] && $files[$id]['size'] === $file['size']
                    && ($files[$id]['path_lower'] ?? null) === ($file['path_lower'] ?? null), 409, 'LF-Metadaten wurden während des Lesens geändert.');
            }
            $files[$id] = $file;
            $selected[$key] = $id;
        }

        $values = [];
        foreach (array_chunk($files, 4, preserve_keys: true) as $batch) {
            $downloads = [];
            $checks = [];
            foreach ($batch as $id => $file) {
                $downloads[$id] = ['endpoint' => 'files/download', 'parameters' => ['path' => 'rev:'.$file['rev']]];
                $checks[$id] = ['endpoint' => 'files/get_metadata', 'parameters' => ['path' => $id]];
            }
            $responses = $this->dropbox->readModuleBatch($connection, $downloads);
            $bodies = [];
            foreach ($responses as $id => $response) {
                $bodies[$id] = $response->body();
                abort_unless(strlen($bodies[$id]) === $batch[$id]['size'], 422, 'LF-Metadaten wurden nicht vollständig gelesen.');
            }
            unset($responses);
            foreach ($this->dropbox->readModuleBatch($connection, $checks) as $id => $response) {
                $after = $response->json();
                abort_unless(($after['rev'] ?? null) === $batch[$id]['rev'] && ($after['path_lower'] ?? null) === ($batch[$id]['path_lower'] ?? null),
                    409, 'LF-Metadaten wurden während des Lesens geändert.');
                $values[$id] = $hashesOnly ? hash('sha256', $bodies[$id]) : $bodies[$id];
            }
        }
        foreach ($selected as $key => $id) {
            $results[$key] = $values[$id];
        }

        return $results;
    }

    /** @param list<array<string, mixed>> $children */
    private function assignmentStatus(DropboxConnection $connection, TeachingCourseWork $work, array $children, string $documentsFolderId, string $date): string
    {
        try {
            $package = $this->exerciseJson($connection, $children, 'Schooltool-Vorbereitung.json');
            if ($package === null) {
                return 'missing';
            }
            abort_unless(($package['kind'] ?? null) === 'schooltool.preparation' && ($package['schema_version'] ?? null) === 2
                && ($package['work_id'] ?? null) === $work->id && ($package['course_id'] ?? null) === $work->teaching_course_id
                && ($package['exercise']['scheduled_date'] ?? null) === $date, 409, 'Der Aufgabenbeleg gehört nicht zu dieser Arbeit.');
            app(TeachingWorkDispatchImport::class)->parse(json_encode($package, JSON_THROW_ON_ERROR));
            $files = $this->exerciseChildren($connection, $documentsFolderId);
            $hash = $this->exerciseFilesBatch($connection, ['assignment' => ['entries' => $files,
                'name' => $package['assignment']['filename'], 'max_size' => 20 * 1024 * 1024]], hashesOnly: true)['assignment'];
            abort_unless(is_string($hash) && hash_equals($package['assignment']['sha256'], $hash), 409, 'Die Aufgaben-PDF stimmt nicht mit dem Aufgabenbeleg überein.');

            return 'ready';
        } catch (ValidationException|HttpExceptionInterface|JsonException $exception) {
            return 'unknown';
        }
    }

    /**
     * @param  array<string, mixed>  $folder
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function emailPreparation(DropboxConnection $connection, TeachingCourseWork $work, array $folder, array $children, string $date): array
    {
        try {
            $package = $this->exerciseJson($connection, $children, 'Schooltool-Versandvorbereitung.json');
            if ($package === null) {
                return ['status' => 'missing'];
            }
            abort_unless(($package['kind'] ?? null) === 'schooltool.preparation' && ($package['schema_version'] ?? null) === 1
                && ($package['Versandbereit'] ?? null) === true, 422, 'Kein vollständiger Versandbereitschaftsbeleg.');
            $importer = app(TeachingWorkDispatchImport::class);
            $json = json_encode($package, JSON_THROW_ON_ERROR);
            $report = $importer->parse($json);
            abort_unless(is_string($package['Leistungsfeststellung'] ?? null)
                && basename(str_replace('\\', '/', $package['Leistungsfeststellung'])) === $folder['name'],
                409, 'Die Vorbereitung gehört zu einem anderen Leistungsfeststellungsordner.');
            $sources = $package['Quellen'] ?? null;
            abort_unless(is_array($sources) && count($sources) >= 2 && count($sources) <= 10, 422, 'Die Quellen der Vorbereitung sind nicht vollständig belegt.');
            $verifiedSources = [];
            $metadata = null;
            $sourceFiles = [];
            $sourceDirectories = [];
            foreach ($sources as $source) {
                abort_unless(is_array($source), 422, 'Ungültige Vorbereitungsquelle.');
                $relative = $source['Datei'] ?? null;
                abort_unless(is_string($relative) && preg_match('#\\A[^/\\\\]+(?:/[^/\\\\]+)?\\z#u', $relative)
                    && ! in_array('..', explode('/', $relative), true) && ! str_contains($relative, ':'), 422, 'Ungültige Vorbereitungsquelle.');
                $directoryId = null;
                if (str_contains($relative, '/')) {
                    [$directory] = explode('/', $relative);
                    $directories = array_values(array_filter($children, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder' && $entry['name'] === $directory));
                    abort_unless(count($directories) === 1, 409, 'Die Vorbereitungsquelle ist nicht eindeutig.');
                    $directoryId = $directories[0]['id'];
                    $sourceDirectories[$directoryId] = $directories[0];
                }
                $sourceFiles[] = ['source' => $source, 'relative' => $relative, 'directory_id' => $directoryId];
            }
            $directoryChildren = $this->exerciseCandidateChildren($connection, array_values($sourceDirectories));
            $sourceRequests = ['draft' => ['entries' => $children, 'name' => 'nachricht_entwurf.md', 'max_size' => 128 * 1024]];
            foreach ($sourceFiles as $index => $file) {
                $sourceRequests['source:'.$index] = ['entries' => $file['directory_id'] === null ? $children : $directoryChildren[$file['directory_id']],
                    'name' => basename($file['relative']), 'max_size' => 128 * 1024];
            }
            $sourceContents = $this->exerciseFilesBatch($connection, $sourceRequests);
            $draft = $sourceContents['draft'];
            abort_unless($draft !== null && is_string($package['Textquelle_SHA256'] ?? null)
                && hash_equals($package['Textquelle_SHA256'], hash('sha256', $draft)),
                409, 'Der Nachrichtentext wurde seit der Vorbereitung geändert.');
            foreach ($sourceFiles as $index => $file) {
                $source = $file['source'];
                $relative = $file['relative'];
                $contents = $sourceContents['source:'.$index];
                abort_unless($contents !== null && is_string($source['SHA256'] ?? null)
                    && hash_equals($source['SHA256'], hash('sha256', $contents)), 409, 'Eine Quelle wurde seit der E-Mail-Vorbereitung geändert.');
                $verifiedSources[] = $relative;
                if ($relative === 'Anlage_und_Quellen.json') {
                    $metadata = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
                }
            }
            abort_unless(in_array('Anlage_und_Quellen.json', $verifiedSources, true)
                && collect($verifiedSources)->contains(fn (string $path): bool => str_starts_with($path, 'Schülerlisten/')),
                422, 'Quellenmetadaten und Schülerliste müssen belegt sein.');
            abort_unless(app(TeachingWorkDeadlineSync::class)->matchesStudentDeadline($work, $metadata), 409,
                'Die Schülerfrist wurde seit der E-Mail-Vorbereitung geändert. Bitte Schülerunterlagen und E-Mails neu vorbereiten.');
            foreach (['Abgabeschluss' => $work->finish_until_date && $work->finish_until_time
                ? $work->finish_until_date->format('Y-m-d').' '.$work->finish_until_time : null,
                'MitgeteilteAbgabefrist' => ($work->communicatedDeadline()['at'] ?? null)] as $field => $expected) {
                $source = $metadata[$field] ?? null;
                if ($source === null && $expected === null) {
                    continue;
                }
                abort_unless(is_string($source) && preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00(?:Z|[+-]\d{2}:\d{2})\z/', $source)
                    && $expected !== null, 409, 'Die vorbereiteten Fristen passen nicht zum aktuellen Arbeitsstand.');
                $instant = new DateTimeImmutable($source);
                $current = new DateTimeImmutable($expected, new DateTimeZone('Europe/Vienna'));
                abort_unless($instant->getTimestamp() === $current->getTimestamp(), 409, 'Eine Frist wurde seit der E-Mail-Vorbereitung geändert.');
            }
            $attachmentNames = [];
            $attachmentClaims = [];
            foreach ($package['Empfaenger'] ?? [] as $recipient) {
                foreach ($recipient['VorbereiteteAnhaenge'] ?? [] as $attachment) {
                    abort_unless(is_string($attachment['Dateiname'] ?? null) && is_string($attachment['SHA256'] ?? null),
                        422, 'Ungültiger Anhangbeleg.');
                    $name = $attachment['Dateiname'];
                    abort_unless(basename(str_replace('\\', '/', $name)) === $name, 422, 'Ungültiger Anhangname.');
                    if (! isset($attachmentNames[$name])) {
                        abort_if(count($attachmentNames) >= 10, 422, 'Zu viele verschiedene Anhänge für die vollständige Prüfung.');
                        $attachmentNames[$name] = 'attachment:'.count($attachmentNames);
                    }
                    $attachmentClaims[] = ['name' => $name, 'hash' => $attachment['SHA256']];
                }
            }
            if ($attachmentNames !== []) {
                $sections = array_values(array_filter($children, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder' && $entry['name'] === 'Angaben'));
                abort_unless(count($sections) === 1, 409, 'Der Angabenordner ist nicht eindeutig.');
                $attachmentChildren = $this->exerciseChildren($connection, $sections[0]['id']);
                $attachmentRequests = [];
                foreach ($attachmentNames as $name => $key) {
                    $attachmentRequests[$key] = ['entries' => $attachmentChildren, 'name' => $name, 'max_size' => 8 * 1024 * 1024];
                }
                $attachmentHashes = $this->exerciseFilesBatch($connection, $attachmentRequests, hashesOnly: true);
                foreach ($attachmentClaims as $claim) {
                    $hash = $attachmentHashes[$attachmentNames[$claim['name']]];
                    abort_unless($hash !== null, 409, 'Ein vorbereiteter Aufgabenanhang fehlt im Angabenordner.');
                    abort_unless(hash_equals($claim['hash'], $hash), 409, 'Ein Aufgabenanhang wurde seit der E-Mail-Vorbereitung geändert.');
                }
            }
            $datedWork = clone $work;
            $groups = app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work);
            $datedWork->groups = $groups === [] && ! $work->is_group_work
                ? [['student_ids' => $work->teachingCourse->teachingCourseStudents()->whereNull('canceled_at')->whereNotNull('user_id')->pluck('user_id')->all()]]
                : array_values(array_filter($groups, fn (array $group): bool => ($group['date'] ?? $work->date_for_all_groups?->format('Y-m-d')) === $date));
            $datedWork->setRelation('teachingCourseWorkGroupStudents', $work->newCollection());
            $preview = $importer->preview($datedWork, $report, hash('sha256', $json), requireMatchingTitle: false);
            abort_unless($preview['log_info']['dispatch_ready'] === true, 409, 'Die Vorbereitung passt nicht vollständig zu den aktuellen Prüfungskandidaten.');

            return ['status' => 'ready', 'prepared_at' => $preview['log_info']['prepared_at'],
                'recipients_count' => count($report['recipients']), 'package_sha256' => hash('sha256', $json)];
        } catch (ValidationException|HttpExceptionInterface|JsonException $exception) {
            return ['status' => 'unknown', 'message' => 'E-Mail-Vorbereitung nicht bestätigt. '.($exception instanceof ValidationException
                ? collect($exception->errors())->flatten()->first() : ($exception instanceof JsonException ? 'Ungültige Vorbereitungsdaten.' : $exception->getMessage()))];
        }
    }

    /** @return array{work_id: int, course_id: int, folder_id: string, from: string, to: string, subject: string, html: string, attachments: list<string>} */
    public function preparedEmailPreview(DropboxConnection $connection, TeachingCourseWork $work, ?string $date): array
    {
        $link = $this->current($work);
        abort_unless($link && $link->dropbox_connection_id === $connection->id && $link->account_id === $connection->account_id,
            409, 'Die Modulverbindung muss zuerst bestätigt werden.');
        $exercise = $this->exercise($connection, $work, $link, $date, initial: false);
        if (($exercise['status'] ?? null) !== 'found') {
            $exercise = $this->exerciseFolder($connection, $work, $link, $date);
        }
        abort_unless(($exercise['status'] ?? null) === 'found', 409,
            $exercise['message'] ?? 'Die E-Mail-Vorbereitung ist nicht eindeutig bestätigt.');

        $folder = $exercise['folder'];
        $metadata = $this->dropbox->metadata($connection, $folder['id']);
        abort_unless($metadata['id'] === $folder['id'] && ($metadata['path_display'] ?? $metadata['path_lower']) === $folder['path'],
            409, 'Der Leistungsfeststellungsordner wurde inzwischen verschoben.');
        $children = $this->exerciseChildren($connection, $folder['id']);
        $record = $this->exerciseJson($connection, $children, 'Anlage_und_Quellen.json');
        $binding = $record['Modulbindung'] ?? [];
        $dates = array_filter(array_merge([$work->date_for_all_groups?->format('Y-m-d')], array_column($work->groups ?? [], 'date')));
        abort_unless(is_array($record) && ($record['Schooltool_Arbeit_ID'] ?? null) === $work->id
            && ($record['Schooltool_Kurs_ID'] ?? null) === $work->teaching_course_id
            && ($record['Schooltool_Schule_ID'] ?? null) === $work->teachingCourse->school_id
            && in_array($record['Durchfuehrungsdatum'] ?? null, $dates, true)
            && ($date === null || ($record['Durchfuehrungsdatum'] ?? null) === $date)
            && is_array($binding) && ($binding['status'] ?? null) === 'linked'
            && is_string($binding['module_id'] ?? null) && strcasecmp($binding['module_id'], $link->module_id) === 0
            && ($binding['dropbox_folder_id'] ?? null) === $link->folder_id
            && is_string($binding['dropbox_path'] ?? null) && strcasecmp($binding['dropbox_path'], $link->folder_path) === 0
            && strcasecmp(dirname($metadata['path_lower']), mb_strtolower(rtrim($link->folder_path, '/').'/Leistungsfeststellung')) === 0,
            409, 'Die Zuordnung des Arbeitsordners konnte nicht bestätigt werden.');

        $order = $this->preparedEmailFolder($connection, $children, 'E-Mail-Vorbereitung', $metadata['path_lower']);
        $orderChildren = $this->exerciseChildren($connection, $order['id']);
        $previews = $this->preparedEmailFolder($connection, $orderChildren, 'Nachrichtenvorschauen', $order['path_lower']);
        $files = array_values(array_filter($this->exerciseChildren($connection, $previews['id']),
            fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file' && is_string($entry['name'] ?? null)
                && preg_match('/\.json\z/i', $entry['name']) === 1));
        usort($files, fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));
        $file = $files[0] ?? null;
        abort_unless(is_array($file) && is_string($file['id'] ?? null) && is_string($file['rev'] ?? null)
            && is_string($file['path_lower'] ?? null) && dirname($file['path_lower']) === $previews['path_lower']
            && is_int($file['size'] ?? null) && $file['size'] > 0 && $file['size'] <= 128 * 1024,
            409, 'Eine vorbereitete Nachricht ist nicht verfügbar.');
        $contents = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$file['rev']])->body();
        abort_unless(strlen($contents) === $file['size'], 409, 'Die Nachricht wurde nicht vollständig gelesen.');
        try {
            $payload = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(409, 'Die vorbereitete Nachricht ist ungültig.');
        }
        abort_unless(is_array($payload) && is_string($payload['From'] ?? null) && is_string($payload['To'] ?? null)
            && is_string($payload['Subject'] ?? null) && is_string($payload['HtmlBody'] ?? null)
            && is_array($payload['Attachments'] ?? null), 409, 'Die vorbereitete Nachricht ist unvollständig.');
        $names = array_values(array_filter(array_map(fn (mixed $attachment): mixed => is_array($attachment) ? ($attachment['Name'] ?? null) : null,
            $payload['Attachments']), fn (mixed $name): bool => is_string($name)));

        $freshWork = $work->fresh();
        abort_unless($freshWork && $freshWork->teaching_course_id === $work->teaching_course_id && $freshWork->automationEnabled(),
            409, 'Die Arbeitszuordnung wurde während des Ladens geändert.');
        $after = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $file['id']])->json();
        abort_unless(($after['rev'] ?? null) === $file['rev'] && ($after['path_lower'] ?? null) === $file['path_lower']
            && $this->current($freshWork)?->getAttributes() === $link->getAttributes()
            && $connection->fresh()?->account_id === $link->account_id && ! $connection->fresh()?->revoked_at,
            409, 'Die Nachricht oder Zuordnung wurde während des Ladens geändert. Bitte erneut öffnen.');

        return ['work_id' => (int) $work->id, 'course_id' => (int) $work->teaching_course_id, 'folder_id' => $folder['id'],
            'from' => $payload['From'], 'to' => $payload['To'],
            'subject' => $payload['Subject'], 'html' => $payload['HtmlBody'], 'attachments' => $names];
    }

    /** @param list<array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    private function preparedEmailFolder(DropboxConnection $connection, array $entries, string $name, string $parentPath): array
    {
        $folders = array_values(array_filter($entries, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
            && is_string($entry['name'] ?? null) && strcasecmp($entry['name'], $name) === 0));
        abort_unless(count($folders) === 1 && is_string($folders[0]['id'] ?? null), 409, 'Der Vorbereitungsordner ist nicht eindeutig verfügbar.');
        $folder = $this->dropbox->metadata($connection, $folders[0]['id']);
        abort_unless($folder['id'] === $folders[0]['id'] && dirname($folder['path_lower']) === $parentPath, 409,
            'Der Vorbereitungsordner wurde inzwischen verschoben.');

        return $folder;
    }

    /** @return list<array<string, mixed>> */
    private function exerciseChildren(DropboxConnection $connection, string $id): array
    {
        $listing = $this->dropbox->readModuleApi($connection, 'files/list_folder', ['path' => $id, 'recursive' => false, 'limit' => 200])->json();
        $entries = [];
        for ($page = 0; ; $page++) {
            abort_unless(is_array($listing['entries'] ?? null) && is_bool($listing['has_more'] ?? null), 422, 'Dropbox hat keine vollständige Ordnerliste geliefert.');
            foreach ($listing['entries'] as $entry) {
                abort_unless(is_string($entry['id'] ?? null), 422, 'Dropbox hat einen ungültigen Ordnereintrag geliefert.');
                $entries[$entry['id']] = $entry;
            }
            if (! $listing['has_more']) {
                return array_values($entries);
            }
            abort_if($page >= 2 || ! is_string($listing['cursor'] ?? null), 422, 'Die Leistungsfeststellungsordner konnten nicht vollständig geprüft werden.');
            $listing = $this->dropbox->readModuleApi($connection, 'files/list_folder/continue', ['cursor' => $listing['cursor']])->json();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>|null
     */
    private function exerciseJson(DropboxConnection $connection, array $entries, string $name): ?array
    {
        $contents = $this->exerciseFile($connection, $entries, $name);
        if ($contents === null) {
            return null;
        }
        $data = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        abort_unless(is_array($data), 422, 'Ungültige LF-Metadaten.');

        return $data;
    }

    /** @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    private function emailDispatch(DropboxConnection $connection, TeachingCourseWork $work, array $children): array
    {
        try {
            foreach (['Versand', 'Aufgaben'] as $name) {
                $folder = collect($children)->first(fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder' && ($entry['name'] ?? null) === $name);
                if (! $folder) {
                    return ['status' => 'missing', 'confirmed_count' => 0];
                }
                $children = $this->exerciseChildren($connection, $folder['id']);
            }
            $runs = collect($children)->filter(fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
                && str_starts_with($entry['name'] ?? '', 'Versand_'))->sortByDesc('name')->take(30);
            $confirmed = [];
            $last = null;
            $mode = null;
            $expected = array_unique(array_merge([], ...array_column(app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work), 'student_ids')));
            foreach ($runs as $run) {
                $text = $this->exerciseFile($connection, $this->exerciseChildren($connection, $run['id']), 'Versandprotokoll.txt');
                if ($text === null) {
                    continue;
                }
                $import = app(TeachingWorkDispatchImport::class);
                $preview = $import->preview($work, $import->parse($text), hash('sha256', $text), requireMatchingTitle: false);
                if (! $preview['can_import'] || $preview['purpose'] !== 'tasks') {
                    continue;
                }
                foreach ($preview['rows'] as $row) {
                    if (! $row['student_id'] || (! $row['accepted'] && $row['mode'] !== 'mailpit')) {
                        continue;
                    }
                    $confirmed[$row['student_id']] ??= ['at' => $row['sent_at'] ?? $row['observed_at'], 'mode' => $row['mode']];
                }
                if ($confirmed !== [] && $last === null) {
                    $last = ['run' => $run['name'], 'protocol_sha256' => hash('sha256', $text)];
                    $mode = collect($confirmed)->contains('mode', 'mailpit') ? 'mailpit' : 'office_outlook';
                }
                if ($expected !== [] && count($confirmed) === count($expected)) {
                    break;
                }
            }

            return ['status' => $expected !== [] && count($confirmed) === count($expected) ? 'sent' : ($confirmed !== [] ? 'partial' : 'missing'),
                'confirmed_count' => count($confirmed), 'expected_count' => count($expected), 'transport' => $mode,
                'sent_at' => collect($confirmed)->pluck('at')->filter()->sort()->last(), 'last_protocol' => $last];
        } catch (\Throwable $exception) {
            report($exception);

            return ['status' => 'unknown', 'confirmed_count' => 0];
        }
    }

    /** @param list<array<string, mixed>> $entries */
    private function exerciseFile(DropboxConnection $connection, array $entries, string $name, int $maxSize = 128 * 1024): ?string
    {
        $files = array_values(array_filter($entries, fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file' && strcasecmp($entry['name'] ?? '', $name) === 0));
        if ($files === []) {
            return null;
        }
        abort_unless(count($files) === 1, 409, 'Mehrdeutige LF-Metadaten.');
        $file = $files[0];
        abort_unless(is_int($file['size'] ?? null) && $file['size'] <= $maxSize && is_string($file['rev'] ?? null), 422, 'LF-Metadaten sind ungültig oder zu groß.');
        $contents = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$file['rev']])->body();
        abort_unless(strlen($contents) === $file['size'], 422, 'LF-Metadaten wurden nicht vollständig gelesen.');
        $after = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $file['id']])->json();
        abort_unless(($after['rev'] ?? null) === $file['rev'] && ($after['path_lower'] ?? null) === ($file['path_lower'] ?? null), 409, 'LF-Metadaten wurden während des Lesens geändert.');

        return $contents;
    }

    public function assignmentVersion(DropboxConnection $connection, TeachingCourseWork $work,
        ?TeachingWorkModuleLink $module, ?TeachingWorkDropboxFolder $folder): string
    {
        return hash('sha256', json_encode(['connection_id' => $connection->id, 'account_id' => $connection->account_id,
            'user_id' => $connection->user_id, 'work_id' => $work->id, 'module' => $module?->getAttributes(),
            'folder' => $folder?->getAttributes()], JSON_THROW_ON_ERROR));
    }

    public function unlink(DropboxConnection $connection, TeachingCourseWork $work, string $expectedVersion): void
    {
        DB::transaction(function () use ($connection, $work, $expectedVersion): void {
            $locked = DropboxConnection::query()->lockForUpdate()->find($connection->id);
            abort_unless($locked && ! $locked->revoked_at && $locked->user_id === $connection->user_id
                && $locked->account_id === $connection->account_id, 409, 'Die Dropbox-Verbindung hat sich geändert.');
            $lockedWork = TeachingCourseWork::query()->lockForUpdate()->findOrFail($work->id);
            abort_unless($lockedWork->teaching_course_id === $work->teaching_course_id && $lockedWork->automationEnabled(),
                409, 'Kurs oder Beurteilungsmodus der Arbeit hat sich geändert.');
            $module = $this->installed() ? TeachingWorkModuleLink::query()->where('teaching_course_work_id', $work->id)->lockForUpdate()->first() : null;
            $folder = TeachingWorkDropboxFolder::query()->where('dropbox_connection_id', $connection->id)
                ->where('teaching_course_work_id', $work->id)->lockForUpdate()->first();
            abort_unless(hash_equals($this->assignmentVersion($connection, $work, $module, $folder), $expectedVersion),
                409, 'Die Zuordnung wurde inzwischen geändert. Bitte den Dialog erneut öffnen.');
            $ownModule = $module && $module->dropbox_connection_id === $connection->id && $module->account_id === $connection->account_id;
            abort_unless($ownModule || $folder, 409, 'Für diese Arbeit ist keine eigene Verbindung zum Trennen vorhanden.');
            if ($ownModule) {
                $module->delete();
            }
            $folder?->delete();
        });
    }

    /** @return array<string, mixed> */
    public function locate(DropboxConnection $connection, string $contents): array
    {
        $local = $this->handshake->parse($contents);
        if ($local['schema_version'] === 2) {
            $folder = $this->dropbox->metadata($connection, $local['dropbox_folder_id']);
            abort_unless($folder['id'] === $local['dropbox_folder_id'], 409, 'Der angegebene Dropbox-Ordner konnte nicht bestätigt werden.');
            $file = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $folder['path_lower'].'/handshake.json'])->json();
            abort_unless(is_string($file['id'] ?? null), 422, 'Die Online-Handshake-Datei konnte nicht bestätigt werden.');
            $candidate = $this->inspect($connection, $file['id']);
            abort_unless($candidate['schema_version'] === 2 && $candidate['folder_id'] === $local['dropbox_folder_id']
                && strcasecmp($candidate['module_id'], $local['module_id']) === 0,
                409, 'Die Kennung im Dropbox-Ordner stimmt nicht mit der ausgewählten Datei überein.');

            return ['module_id' => $candidate['module_id'], 'candidate' => $candidate];
        }
        $data = $this->dropbox->readModuleApi($connection, 'files/search_v2', ['query' => 'handshake.json',
            'options' => ['filename_only' => true, 'max_results' => 100, 'file_status' => 'active', 'file_extensions' => ['json']]])->json();
        $entries = [];
        for ($page = 0; ; $page++) {
            if (! is_array($data['matches'] ?? null) || ! is_bool($data['has_more'] ?? null)) {
                $this->fail('Die Dropbox-Suche ist noch unvollständig. Bitte erneut versuchen; es wurde nichts verknüpft.');
            }
            foreach ($data['matches'] ?? [] as $match) {
                $entry = $match['metadata']['metadata'] ?? [];
                if (($entry['.tag'] ?? null) === 'file' && strtolower($entry['name'] ?? '') === 'handshake.json'
                    && is_string($entry['id'] ?? null) && is_string($entry['rev'] ?? null)
                    && $this->materialRoot($entry['path_lower'] ?? '') !== null) {
                    $entries[$entry['id']] = $entry;
                }
            }
            if (count($entries) > self::MAX_HANDSHAKE_FILES) {
                $this->fail('Zu viele Handshake-Dateien für eine begrenzte sichere Suche. Es wurde nichts verknüpft.');
            }
            if (! $data['has_more']) {
                break;
            }
            if ($page >= 2 || ! is_string($data['cursor'] ?? null) || $data['cursor'] === '') {
                $this->fail('Die Dropbox-Suche ist noch unvollständig. Bitte erneut versuchen; es wurde nichts verknüpft.');
            }
            $data = $this->dropbox->readModuleApi($connection, 'files/search/continue_v2', ['cursor' => $data['cursor']])->json();
        }
        $roots = [];
        foreach ($entries as $entry) {
            $root = $this->materialRoot($entry['path_lower']);
            if (! isset($roots[$root])) {
                $metadata = $this->dropbox->metadata($connection, $root);
                abort_unless($metadata['path_lower'] === $root, 422, 'Die Lerninhalte-Wurzel konnte nicht bestätigt werden.');
                $roots[$root] = $metadata['id'];
            }
        }
        $matches = [];
        $contentsById = $this->handshakeContents($connection, $entries);
        foreach ($entries as $entry) {
            if (! array_key_exists($entry['id'], $contentsById)) {
                continue;
            }
            try {
                $candidate = $this->handshake->parse($contentsById[$entry['id']]);
            } catch (ValidationException $exception) {
                if (isset($exception->errors()['handshake'])) {
                    continue;
                }
                throw $exception;
            }
            if (strcasecmp($candidate['module_id'], $local['module_id']) === 0) {
                $matches[] = $this->inspect($connection, $entry['id']);
            }
        }
        if ($matches === []) {
            $this->fail('Noch keine passende Online-Datei bestätigt. Die Dropbox-Suche kann verzögert oder unvollständig sein. Bitte später erneut versuchen.');
        }
        if (count($matches) > 1) {
            return ['module_id' => $local['module_id'], 'candidates' => $matches, 'conflict' => true,
                'message' => 'Mehrere Online-Ordner enthalten dieselbe Modul-ID. Bitte die kopierte Modulidentität klären; es wurde nichts verknüpft.'];
        }

        return ['module_id' => $matches[0]['module_id'], 'candidate' => $matches[0]];
    }

    /**
     * @param  array<string, array<string, mixed>>  $entries
     * @return array<string, string>
     */
    private function handshakeContents(DropboxConnection $connection, array $entries): array
    {
        $contents = [];
        $requests = [];
        $keys = [];
        foreach ($entries as $id => $entry) {
            if (! is_int($entry['size'] ?? null) || $entry['size'] < 0 || $entry['size'] > ModuleHandshake::MAX_BYTES) {
                continue;
            }
            $keys[$id] = 'teaching:module-handshake:v1:'.hash('sha256', serialize([
                $connection->id, $connection->user_id, $connection->account_id, $id, $entry['rev'], $entry['size'],
            ]));
            $cached = Cache::get($keys[$id]);
            if (is_string($cached) && strlen($cached) === $entry['size']) {
                $contents[$id] = $cached;
            } else {
                $requests[$id] = ['endpoint' => 'files/download', 'parameters' => ['path' => 'rev:'.$entry['rev']]];
            }
        }
        foreach ($this->dropbox->readModuleBatch($connection, $requests) as $id => $response) {
            $download = $response->body();
            abort_unless(strlen($download) === $entries[$id]['size'], 422, 'Die Online-Datei wurde nicht vollständig gelesen.');
            $contents[$id] = $download;
            Cache::put($keys[$id], $download, now()->addHour());
        }

        return $contents;
    }

    /** @return array<string, mixed> */
    public function resolve(DropboxConnection $connection, TeachingCourseWork $work, string $contents): array
    {
        $current = $this->current($work);
        abort_if($current && ($current->dropbox_connection_id !== $connection->id || $current->account_id !== $connection->account_id),
            409, 'Für diese Arbeit besteht bereits eine Verknüpfung mit einem anderen Dropbox-Konto.');
        $located = $this->locate($connection, $contents);
        if ($located['conflict'] ?? false) {
            return $located;
        }
        $candidate = $located['candidate'];
        $proof = Crypt::encryptString(json_encode(['user_id' => $connection->user_id, 'connection_id' => $connection->id,
            'account_id' => $connection->account_id, 'work_id' => $work->id, 'candidate' => $candidate,
            'expected' => $this->fingerprint($current), 'expires' => now()->addMinutes(10)->timestamp], JSON_THROW_ON_ERROR));

        return ['module_id' => $candidate['module_id'], 'candidate' => $candidate, 'proof' => $proof,
            'can_link' => $this->installed(), 'message' => $this->installed()
                ? 'Online-Datei geprüft. Bitte den vollständigen Ordnerpfad bestätigen.'
                : 'Online-Datei geprüft. Vor dem Speichern muss die neue Datenbankmigration freigegeben und ausgeführt werden.'];
    }

    public function store(DropboxConnection $connection, TeachingCourseWork $work, string $contents, string $proof): void
    {
        abort_unless($this->installed(), 409, 'Die Datenbankmigration für Modulverknüpfungen wurde noch nicht ausgeführt.');
        $local = $this->handshake->parse($contents);
        try {
            $pending = json_decode(Crypt::decryptString($proof), true, 16, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            abort(409, 'Die Bestätigung ist ungültig. Bitte die Datei erneut auswählen.');
        }
        abort_unless(($pending['user_id'] ?? null) === $connection->user_id && ($pending['connection_id'] ?? null) === $connection->id
            && ($pending['account_id'] ?? null) === $connection->account_id && ($pending['work_id'] ?? null) === $work->id
            && ($pending['expires'] ?? 0) >= now()->timestamp && strcasecmp($pending['candidate']['module_id'] ?? '', $local['module_id']) === 0
            && ($pending['candidate']['schema_version'] ?? null) === $local['schema_version']
            && ($local['schema_version'] !== 2 || ($pending['candidate']['folder_id'] ?? null) === $local['dropbox_folder_id']),
            409, 'Konto, Arbeit oder Bestätigung hat sich geändert. Bitte erneut auswählen.');
        $candidate = $this->inspect($connection, $pending['candidate']['handshake_file_id']);
        abort_unless($candidate === $pending['candidate'], 409, 'Die Online-Datei oder ihr Ordner hat sich geändert. Bitte erneut auswählen.');
        DB::transaction(function () use ($connection, $work, $candidate, $pending): void {
            $locked = DropboxConnection::query()->lockForUpdate()->find($connection->id);
            abort_unless($locked && ! $locked->revoked_at && $locked->user_id === $connection->user_id
                && $locked->account_id === $connection->account_id, 409, 'Die Dropbox-Verbindung hat sich geändert.');
            $lockedWork = TeachingCourseWork::query()->lockForUpdate()->findOrFail($work->id);
            abort_unless($lockedWork->teaching_course_id === $work->teaching_course_id && $lockedWork->automationEnabled(),
                409, 'Kurs oder Beurteilungsmodus der Arbeit hat sich geändert.');
            $current = TeachingWorkModuleLink::query()->where('teaching_course_work_id', $work->id)->lockForUpdate()->first();
            abort_unless($this->fingerprint($current) === $pending['expected'], 409, 'Die Verknüpfung wurde inzwischen geändert. Bitte erneut öffnen.');
            $other = TeachingWorkModuleLink::query()->where('dropbox_connection_id', $connection->id)
                ->where('module_id', $candidate['module_id'])->where('folder_id', '!=', $candidate['folder_id'])->exists();
            abort_if($other, 409, 'Diese Modul-ID ist bereits mit einem anderen Online-Ordner verknüpft.');
            TeachingWorkModuleLink::query()->updateOrCreate(['teaching_course_work_id' => $work->id], $candidate + [
                'dropbox_connection_id' => $connection->id, 'account_id' => $connection->account_id]);
        });
    }

    /** @return array<string, mixed> */
    public function inspect(DropboxConnection $connection, string $fileId): array
    {
        $entry = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $fileId])->json();
        $path = $entry['path_lower'] ?? null;
        abort_unless(($entry['.tag'] ?? null) === 'file' && ($entry['id'] ?? null) === $fileId
            && strtolower($entry['name'] ?? '') === 'handshake.json' && is_string($entry['rev'] ?? null)
            && is_int($entry['size'] ?? null) && $entry['size'] <= ModuleHandshake::MAX_BYTES
            && is_string($path) && str_starts_with($path, '/') && ! preg_match('/[\x00-\x1f]/u', $path),
            422, 'Die Online-Handshake-Datei konnte nicht bestätigt werden.');
        $root = $this->materialRoot($path);
        abort_unless($root !== null, 409, 'Die Online-Datei liegt außerhalb des Lerninhalte-Bestands. Bitte erneut auswählen.');
        $rootMetadata = $this->dropbox->metadata($connection, $root);
        abort_unless($rootMetadata['path_lower'] === $root, 409, 'Die Lerninhalte-Wurzel hat sich geändert.');
        $contents = $this->dropbox->readModuleApi($connection, 'files/download', ['path' => 'rev:'.$entry['rev']])->body();
        abort_unless(strlen($contents) === $entry['size'], 422, 'Die Online-Datei wurde nicht vollständig gelesen.');
        $parsed = $this->handshake->parse($contents);
        $parent = substr($path, 0, strrpos($path, '/'));
        abort_if($parent === '', 422, 'Die Handshake-Datei muss in einem Modulordner liegen.');
        $folder = $this->dropbox->metadata($connection, $parent);
        abort_unless(! isset($parsed['dropbox_folder_id']) || $parsed['dropbox_folder_id'] === $folder['id'],
            409, 'Die Handshake-Datei verweist auf einen anderen Dropbox-Ordner.');
        $listing = $this->dropbox->readModuleApi($connection, 'files/list_folder', ['path' => $folder['id'], 'recursive' => false, 'limit' => 200])->json();
        $handshakes = [];
        for ($page = 0; ; $page++) {
            foreach ($listing['entries'] ?? [] as $child) {
                if (strtolower($child['name'] ?? '') === 'handshake.json') {
                    $handshakes[] = $child;
                }
            }
            if (! ($listing['has_more'] ?? false)) {
                break;
            }
            abort_if($page >= 2 || ! is_string($listing['cursor'] ?? null), 422, 'Der Modulordner konnte nicht vollständig geprüft werden.');
            $listing = $this->dropbox->readModuleApi($connection, 'files/list_folder/continue', ['cursor' => $listing['cursor']])->json();
        }
        abort_unless(count($handshakes) === 1 && ($handshakes[0]['id'] ?? null) === $fileId
            && ($handshakes[0]['rev'] ?? null) === $entry['rev'], 409, 'Die Online-Handshake-Datei ist mehrdeutig oder wurde verändert.');
        $after = $this->dropbox->readModuleApi($connection, 'files/get_metadata', ['path' => $fileId])->json();
        abort_unless(($after['rev'] ?? null) === $entry['rev'] && ($after['path_lower'] ?? null) === $path, 409, 'Die Online-Datei wurde während der Prüfung verschoben oder verändert.');

        return $parsed + ['folder_id' => $folder['id'], 'folder_name' => $folder['name'],
            'folder_path' => $folder['path_display'] ?? $folder['path_lower'],
            'namespace_id' => $folder['sharing_info']['parent_shared_folder_id'] ?? $folder['sharing_info']['shared_folder_id'] ?? null,
            'handshake_file_id' => $fileId, 'handshake_revision' => $entry['rev']];
    }

    private function fingerprint(?TeachingWorkModuleLink $link): string
    {
        return hash('sha256', json_encode($link?->getAttributes(), JSON_THROW_ON_ERROR));
    }

    private function materialRoot(string $path): ?string
    {
        if (str_starts_with(mb_strtolower($path), '/ai/tmp/')) {
            return null;
        }
        $configured = config('services.dropbox.module_material_roots', []);
        if ($configured !== []) {
            foreach ($configured as $root) {
                if (! is_string($root) || ! str_starts_with($root, '/')) {
                    continue;
                }
                $root = rtrim(mb_strtolower($root), '/');
                if (str_starts_with($path, $root.'/') && str_ends_with($path, '/handshake.json')) {
                    return $root;
                }
            }

            return null;
        }
        if (preg_match('#\A(.*/materialien/lerninhalte)/[^\x00-\x1f]+/handshake\.json\z#u', $path, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['dropbox' => $message]);
    }
}
