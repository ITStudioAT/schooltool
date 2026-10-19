<?php

namespace App\Services;

use App\Models\DropboxConnection;
use App\Models\McTest;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\User;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;

class DropboxWorkImport
{
    public const SCOPES = ['files.metadata.read', 'files.content.read'];

    private const MAX_BYTES = 6 * 1024 * 1024;

    /** @var array<int, string> */
    private array $accessTokens = [];

    public function configured(): bool
    {
        $url = rtrim((string) config('app.url'), '/');
        $secure = parse_url($url, PHP_URL_SCHEME) === 'https'
            || (parse_url($url, PHP_URL_SCHEME) === 'http' && in_array(parse_url($url, PHP_URL_HOST), ['localhost', '127.0.0.1', '[::1]'], true));

        return ! config('schooltool.preview.instance') && $secure
            && filled(config('services.dropbox.client_id')) && filled(config('services.dropbox.client_secret'))
            && config('services.dropbox.redirect_uri') === $url.route('teaching.dropbox.callback', [], false);
    }

    public function installed(): bool
    {
        return Schema::hasTable('dropbox_connections') && Schema::hasTable('teaching_work_dropbox_folders');
    }

    public function requireReady(): void
    {
        if (! $this->configured() || ! $this->installed()) {
            $this->fail('Dropbox ist noch nicht eingerichtet. Bitte den normalen Ordnerupload verwenden.');
        }
    }

    public function connection(User $user): DropboxConnection
    {
        $this->requireReady();
        $connection = DropboxConnection::query()->where('user_id', $user->id)->first();
        if (! $connection || $connection->revoked_at) {
            $this->fail('Bitte Dropbox erneut mit Schooltool verbinden.');
        }

        return $connection;
    }

    public function authorizationUrl(string $state, bool $write = false): string
    {
        $this->requireReady();

        return 'https://www.dropbox.com/oauth2/authorize?'.http_build_query([
            'client_id' => config('services.dropbox.client_id'),
            'redirect_uri' => config('services.dropbox.redirect_uri'),
            'response_type' => 'code', 'token_access_type' => 'offline',
            'scope' => implode(' ', $write ? [...self::SCOPES, 'files.content.write'] : self::SCOPES), 'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function connect(User $user, string $code, bool $write = false): void
    {
        $this->requireReady();
        $data = $this->token(['grant_type' => 'authorization_code', 'code' => $code,
            'redirect_uri' => config('services.dropbox.redirect_uri')]);
        if (! is_string($data['refresh_token'] ?? null) || ! is_string($data['account_id'] ?? null)) {
            $this->fail('Dropbox hat keine dauerhafte Benutzerfreigabe geliefert. Bitte erneut verbinden.');
        }
        $scopes = preg_split('/\s+/', trim((string) ($data['scope'] ?? '')));
        $required = $write ? [...self::SCOPES, 'files.content.write'] : self::SCOPES;
        if (array_diff($required, $scopes) !== [] || array_diff($scopes, $required) !== []) {
            $this->fail('Die Dropbox-Freigabe enthält nicht die für diesen Vorgang benötigten Dateirechte.');
        }
        DB::transaction(function () use ($user, $data, $scopes): void {
            $connection = DropboxConnection::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if ($connection && $connection->account_id !== $data['account_id']) {
                TeachingWorkDropboxFolder::query()->where('dropbox_connection_id', $connection->id)->delete();
            }
            DropboxConnection::query()->updateOrCreate(['user_id' => $user->id], [
                'account_id' => $data['account_id'], 'credentials' => ['refresh_token' => $data['refresh_token'], 'scopes' => $scopes], 'revoked_at' => null,
            ]);
        });
    }

    public function canWrite(DropboxConnection $connection): bool
    {
        return in_array('files.content.write', $connection->credentials['scopes'] ?? [], true);
    }

    public function connectionStatus(User $user, bool $verify = false): array
    {
        $installed = $this->installed();
        $connection = $installed ? DropboxConnection::where('user_id', $user->id)->first() : null;
        $cacheKey = $connection ? $this->statusCacheKey($connection) : null;
        $checks = $cacheKey ? Cache::store('file')->get($cacheKey, []) : [];
        if ($verify && $connection && ! $connection->revoked_at && $this->configured()) {
            try {
                $this->api($connection, 'files/list_folder', ['path' => '', 'recursive' => false, 'limit' => 1]);
                $checks['read_checked_at'] = now()->toISOString();
                unset($checks['error']);
            } catch (ValidationException) {
                $checks['error'] = 'Die Dropbox-Verbindung konnte nicht bestätigt werden. Bitte erneut freigeben oder später prüfen.';
            }
            Cache::store('file')->put($cacheKey, $checks, now()->addDays(30));
        }

        return ['configured' => $this->configured(), 'installed' => $installed,
            'connected' => $connection !== null && $connection->revoked_at === null,
            'read_granted' => $connection && in_array('files.content.read', $connection->credentials['scopes'] ?? [], true),
            'write_granted' => $connection && $this->canWrite($connection),
            'read_checked_at' => $checks['read_checked_at'] ?? null, 'write_checked_at' => $checks['write_checked_at'] ?? null,
            'cleanup_complete' => $checks['cleanup_complete'] ?? null, 'error' => $checks['error'] ?? null];
    }

    public function verifyWrite(DropboxConnection $connection, string $folderId, bool $temporaryFolder = false): array
    {
        $filename = 'Schooltool-Schreibtest-'.bin2hex(random_bytes(16)).'.txt';
        $contents = "Schooltool Dropbox-Schreibtest\n".bin2hex(random_bytes(16))."\n";
        $revision = $this->writeDocument($connection, $folderId, $filename, $contents, null);
        try {
            $file = $this->readDocument($connection, $folderId, $filename);
            if ($file === null || $file['rev'] !== $revision || ! hash_equals($contents, $file['contents'])) {
                $this->fail('Die eigene Prüfdatei konnte nicht unverändert zurückgelesen werden.');
            }
        } finally {
            $this->api($connection, 'files/delete_v2', ['path' => $this->documentPath($folderId, $filename), 'parent_rev' => $revision]);
        }
        $key = $this->statusCacheKey($connection);
        $checks = Cache::store('file')->get($key, []);
        $checks['write_checked_at'] = now()->toISOString();
        $checks['read_checked_at'] = $checks['write_checked_at'];
        $checks['cleanup_complete'] = ! $temporaryFolder;
        Cache::store('file')->put($key, $checks, now()->addDays(30));

        return ['checked_at' => $checks['write_checked_at'], 'created_read_removed' => true];
    }

    public function verifyTemporaryWrite(User $user): array
    {
        $connection = $this->connection($user);
        if (! $this->canWrite($connection)) {
            $this->fail('Die zusätzliche Dropbox-Schreibfreigabe fehlt noch.');
        }
        $name = 'Schooltool-Schreibtest-'.bin2hex(random_bytes(16));
        $created = $this->api($connection, 'files/create_folder_v2', ['path' => '/'.$name, 'autorename' => false])->json('metadata');
        if (! is_array($created) || ($created['name'] ?? null) !== $name || ! is_string($created['id'] ?? null)) {
            $this->fail('Dropbox hat den eigenen temporären Testordner nicht eindeutig bestätigt.');
        }
        try {
            $result = $this->verifyWrite($connection, $created['id'], true);
        } finally {
            $listing = $this->api($connection, 'files/list_folder', ['path' => $created['id'], 'recursive' => false, 'limit' => 1])->json();
            if (($listing['entries'] ?? null) !== [] || ($listing['has_more'] ?? true)) {
                $this->fail('Der eigene Testordner ist nicht leer und wurde deshalb nicht entfernt: '.$name);
            }
            $this->api($connection, 'files/delete_v2', ['path' => $created['id']]);
        }

        $key = $this->statusCacheKey($connection);
        $checks = Cache::store('file')->get($key, []);
        $checks['cleanup_complete'] = true;
        Cache::store('file')->put($key, $checks, now()->addDays(30));

        return $result + ['temporary_folder_removed' => true];
    }

    private function statusCacheKey(DropboxConnection $connection): string
    {
        return 'dropbox-check:'.$connection->user_id.':'.hash('sha256', $connection->account_id.':'.$connection->credentials['refresh_token']);
    }

    /** @return array{contents: string, rev: string}|null */
    public function readDocument(DropboxConnection $connection, string $folderId, string $filename): ?array
    {
        $this->metadata($connection, $folderId);
        $path = $this->documentPath($folderId, $filename);
        try {
            $metadata = Http::withToken($this->accessToken($connection))->connectTimeout(5)->timeout(20)
                ->withOptions(['allow_redirects' => false])->post('https://api.dropboxapi.com/2/files/get_metadata', ['path' => $path]);
        } catch (ConnectionException) {
            $this->fail('Die Dropbox-Datei konnte nicht gelesen werden.');
        }
        if ($metadata->status() === 409 && str_starts_with((string) $metadata->json('error_summary'), 'path/not_found/')) {
            return null;
        }
        $entry = $this->checkResponse($connection, $metadata)->json();
        if (($entry['.tag'] ?? null) !== 'file') {
            $this->fail('Das Dropbox-Ziel ist keine Datei.');
        }
        $this->checkDownloadSize($entry, self::MAX_BYTES, 0);
        $response = $this->api($connection, 'files/download', ['path' => 'rev:'.$entry['rev']], true);
        $contents = $response->body();
        if (strlen($contents) !== $entry['size']) {
            $this->fail('Die Dropbox-Datei wurde nicht vollständig gelesen.');
        }

        return ['contents' => $contents, 'rev' => $entry['rev']];
    }

    public function writeDocument(DropboxConnection $connection, string $folderId, string $filename, string $contents, ?string $revision): string
    {
        if (! $this->canWrite($connection) || strlen($contents) > self::MAX_BYTES) {
            $this->fail('Bitte Dropbox für das Lesen und Schreiben dieses Testordners erneut verbinden.');
        }
        $this->metadata($connection, $folderId);
        $parameters = ['path' => $this->documentPath($folderId, $filename),
            'mode' => $revision === null ? 'add' : ['.tag' => 'update', 'update' => $revision],
            'autorename' => false, 'strict_conflict' => true];
        try {
            $response = Http::withToken($this->accessToken($connection))->connectTimeout(5)->timeout(20)
                ->withOptions(['allow_redirects' => false])->withHeaders(['Dropbox-API-Arg' => json_encode($parameters, JSON_THROW_ON_ERROR)])
                ->withBody($contents, 'application/octet-stream')->post('https://content.dropboxapi.com/2/files/upload');
        } catch (ConnectionException) {
            $this->fail('Dropbox hat das Speichern nicht bestätigt. Bitte den Ordner neu lesen, bevor Sie erneut speichern.');
        }
        $data = $this->checkResponse($connection, $response)->json();
        if (! is_string($data['rev'] ?? null) || ($data['size'] ?? null) !== strlen($contents)) {
            $this->fail('Dropbox hat die gespeicherte Datei nicht vollständig bestätigt.');
        }

        return $data['rev'];
    }

    private function documentPath(string $folderId, string $filename): string
    {
        if (! preg_match('/\Aid:[A-Za-z0-9_-]+\z/', $folderId)
            || ! preg_match('/\A(?:MC-Test_(?:[0-9]+|[a-f0-9]{24})\.json|Schooltool-Schreibtest-[a-f0-9]{32}\.txt)\z/', $filename)) {
            $this->fail('Der Testordner oder Dateiname ist ungültig.');
        }

        return $folderId.'/'.$filename;
    }

    /** @return array<string, mixed> */
    private function token(array $parameters, ?DropboxConnection $connection = null): array
    {
        try {
            $response = Http::asForm()->connectTimeout(5)->timeout(20)->withOptions(['allow_redirects' => false])
                ->post('https://api.dropboxapi.com/oauth2/token', $parameters + [
                    'client_id' => config('services.dropbox.client_id'), 'client_secret' => config('services.dropbox.client_secret'),
                ]);
        } catch (ConnectionException) {
            $this->fail('Dropbox ist momentan nicht erreichbar. Bitte später erneut versuchen oder einen Ordner hochladen.');
        }
        if ($connection && ($response->status() === 401 || $response->json('error') === 'invalid_grant')) {
            $connection->update(['revoked_at' => now()]);
        }
        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            $this->fail('Dropbox-Freigabe konnte nicht erneuert werden. Bitte die Verbindung prüfen oder erneut herstellen.');
        }

        return $response->json();
    }

    private function accessToken(DropboxConnection $connection): string
    {
        if (! isset($this->accessTokens[$connection->id])) {
            $data = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $connection->credentials['refresh_token']], $connection);
            $this->accessTokens[$connection->id] = $data['access_token'];
        }

        return $this->accessTokens[$connection->id];
    }

    /** @return array<string, mixed> */
    public function metadata(DropboxConnection $connection, string $id): array
    {
        $data = $this->api($connection, 'files/get_metadata', ['path' => $id])->json();
        if (($data['.tag'] ?? null) !== 'folder' || ! is_string($data['id'] ?? null)
            || ! is_string($data['name'] ?? null) || ! is_string($data['path_lower'] ?? null)
            || strlen($data['name']) > 255 || preg_match('/[\/\\\\\x00-\x1f]/u', $data['name'])) {
            $this->fail('Der zugeordnete Dropbox-Ordner ist nicht verfügbar. Bitte einen Ordner neu zuordnen.');
        }

        return $data;
    }

    /** @return array{status: string, message: string} */
    public function folderPlausibility(TeachingCourseWork $work, string $name): array
    {
        return $this->checkFolderContext($this->workFolderContext($work), $name);
    }

    public function testFolderPlausibility(McTest $test, string $name): array
    {
        return $this->checkFolderContext($this->testFolderContext($test), $name);
    }

    private function workFolderContext(TeachingCourseWork $work): array
    {
        return ['title' => (string) $work->title, 'classes' => $work->teachingCourse->classes ?? [],
            'course_title' => (string) $work->teachingCourse->title, 'course_ids' => [$work->teaching_course_id], 'work_id' => $work->id,
            'dates' => array_filter([$work->date_for_all_groups?->format('Y-m-d'), ...array_column($work->groups ?? [], 'date')])];
    }

    private function testFolderContext(McTest $test): array
    {
        $ids = $test->attempts()->pluck('student_id')->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
        $students = User::where('school_id', $test->school_id)->whereIn('id', $ids)->get(['id', 'schoolclass', 'schoolyear_id']);
        if ($students->count() !== count($ids)) {
            $this->fail('Die Testteilnahmen gehören nicht eindeutig zur aktuellen Schule.');
        }
        $courses = $ids === [] || $test->schoolyear_id === null ? collect() : TeachingCourse::where('school_id', $test->school_id)
            ->where('user_id', $test->owner_id)->where('schoolyear_id', $test->schoolyear_id)
            ->whereHas('teachingCourseStudents', fn ($query) => $query->whereIn('user_id', $ids)->whereNull('canceled_at'))
            ->with(['teachingCourseStudents' => fn ($query) => $query->whereNull('canceled_at')->select('id', 'teaching_course_id', 'user_id')])
            ->get(['id', 'title', 'classes'])->filter(fn (TeachingCourse $course): bool => $course->teachingCourseStudents
            ->pluck('user_id')->map(fn ($id): int => (int) $id)->sort()->values()->all() === $ids);
        $course = $courses->count() === 1 ? $courses->first() : null;
        $knownClasses = $students->every(fn (User $student): bool => $test->schoolyear_id !== null
            && (int) $student->schoolyear_id === (int) $test->schoolyear_id && trim((string) $student->schoolclass) !== '');
        $classes = $course?->classes ?? ($knownClasses ? $students->pluck('schoolclass')->unique()->values()->all() : []);
        $catalog = $test->catalog;
        $catalogTitle = $catalog && (int) $catalog->school_id === (int) $test->school_id && (int) $catalog->owner_id === (int) $test->owner_id
            ? $this->folderWords($catalog->title) : '';
        $excluded = ['test', 'tests', 'katalog', 'fragenkatalog', ...array_map($this->folderWords(...), $classes)];
        if ($course && preg_match('/\s+-\s+([1-9][0-9]*)\z/u', $course->title, $match)) {
            foreach ($classes as $class) {
                $excluded[] = $this->folderWords($class.$match[1]);
            }
        }
        $testWords = array_values(array_diff(explode(' ', $this->folderWords($test->title)), $excluded));
        $catalogWords = array_values(array_diff(explode(' ', $catalogTitle), $excluded));
        $common = array_values(array_intersect($testWords, $catalogWords));

        return ['title' => implode(' ', $common ?: ($testWords ?: $catalogWords)), 'classes' => $classes,
            'course_title' => $course?->title ?? '', 'course_ids' => $courses->pluck('id')->all(), 'work_id' => null,
            'dates' => array_filter([$test->starts_at?->format('Y-m-d')])];
    }

    private function checkFolderContext(array $context, string $name): array
    {
        $classes = array_map(fn (string $class): string => mb_strtoupper(trim($class)), $context['classes']);
        $group = null;
        if (count($classes) === 1 && preg_match('/\A.+?\s+-\s+(.+?)\s+-\s+([1-9][0-9]*)\z/u', trim($context['course_title']), $parts) === 1
            && mb_strtoupper(trim($parts[1])) === $classes[0]) {
            $group = (int) $parts[2];
        }
        $recognized = preg_match('/(?:\A|_)([0-9]+[A-Z][A-Z0-9]*(?:[-_][0-9]+[A-Z][A-Z0-9]*)*)(?:_Gruppe-([1-9][0-9]*))?(?:_(?:Tag|Abend))?(?:_[A-Z][A-Z0-9]*)?\z/ui', $name, $parts) === 1;
        if (! $recognized) {
            $recognized = preg_match('/(?:\A|_)[A-Z][A-Z0-9]*-([0-9]+[A-Z][A-Z0-9]*)-([1-9][0-9]*)(?:_(?:Tag|Abend))?\z/ui', $name, $parts) === 1;
        }
        if (! $recognized || $classes === []) {
            return ['status' => 'unknown', 'message' => 'Der Ordnername enthält keine eindeutige Kursangabe. Die Zuordnung wird anhand der Importdateien geprüft.'];
        }
        $folderClasses = preg_split('/[-_]/u', mb_strtoupper($parts[1]));
        $folderGroup = isset($parts[2]) && $parts[2] !== '' ? (int) $parts[2] : null;
        if (array_diff($classes, $folderClasses) === [] && array_diff($folderClasses, $classes) !== []
            && ! ($group !== null && $folderGroup !== null && $folderGroup !== $group)) {
            return ['status' => 'unknown', 'message' => 'Der Ordner nennt zusätzliche Klassen. Die genaue Arbeits- und Personenzuordnung wird anhand der Importdateien geprüft.'];
        }
        if (array_diff($folderClasses, $classes) !== [] || ($group !== null && $folderGroup !== null && $folderGroup !== $group)) {
            $target = $context['work_id'] !== null ? 'zum Kurs '.$context['course_title'] : 'zur aktuellen Kurs-/Teilnehmergruppe';

            return ['status' => 'mismatch', 'message' => "Ordner passt nicht {$target}: {$name}. Bitte den passenden Dropbox-Ordner wählen!"];
        }
        if ($group !== null && $folderGroup === null) {
            return ['status' => 'unknown', 'message' => "Die Kursgruppe {$group} fehlt im Ordnernamen. Bitte den Inhalt beim Import prüfen."];
        }

        return ['status' => 'match', 'message' => $group === null
            ? 'Die Klassenangaben passen zum aktuellen Kurs.'
            : 'Klasse und Kursgruppe passen zum aktuellen Kurs.'];
    }

    /** @return array{suggested_folder: ?array, suggestions: list<array>, start_folder: ?array, message: string} */
    public function suggestFolders(DropboxConnection $connection, TeachingCourseWork $work): array
    {
        return $this->suggestFolderContext($connection, $this->workFolderContext($work));
    }

    public function suggestTestFolders(DropboxConnection $connection, McTest $test): array
    {
        return $this->suggestFolderContext($connection, $this->testFolderContext($test));
    }

    private function suggestFolderContext(DropboxConnection $connection, array $context): array
    {
        $budget = 16;
        $partial = false;
        $read = function (string $endpoint, array $parameters, bool $download = false) use ($connection, &$budget, &$partial): mixed {
            if ($budget-- <= 0) {
                $partial = true;

                return $download ? '' : [];
            }
            $response = $this->api($connection, $endpoint, $parameters, $download);
            if ($download) {
                return $response->body();
            }

            return $response->json();
        };
        $search = function (string $query, string $path = '') use ($read, &$partial, &$budget): array {
            $data = $read('files/search_v2', ['query' => $query, 'options' => [
                'filename_only' => true, 'max_results' => 50, 'file_status' => 'active', 'file_categories' => ['folder'],
            ] + ($path !== '' ? ['path' => $path] : [])]);
            $matches = $data['matches'] ?? [];
            for ($page = 1; ($data['has_more'] ?? false) && is_string($data['cursor'] ?? null) && $page < 3 && $budget > 5; $page++) {
                $data = $read('files/search/continue_v2', ['cursor' => $data['cursor']]);
                $matches = array_merge($matches, $data['matches'] ?? []);
            }
            $partial = $partial || ($data['has_more'] ?? false);

            return array_values(array_filter(array_map(fn (array $match): array => $match['metadata']['metadata'] ?? [], $matches),
                fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder' && is_string($entry['id'] ?? null)
                    && is_string($entry['name'] ?? null) && is_string($entry['path_lower'] ?? null)));
        };
        $list = function (string $id) use ($read, &$partial, &$budget): array {
            $data = $read('files/list_folder', ['path' => $id, 'recursive' => false, 'limit' => 200]);
            $entries = $data['entries'] ?? [];
            if (($data['has_more'] ?? false) && is_string($data['cursor'] ?? null) && $budget > 5) {
                $data = $read('files/list_folder/continue', ['cursor' => $data['cursor']]);
                $entries = array_merge($entries, $data['entries'] ?? []);
            }
            $partial = $partial || ($data['has_more'] ?? false);

            return $entries;
        };
        $roots = array_values(array_filter($search('Lerninhalte'), fn (array $entry): bool => preg_match('#/materialien/lerninhalte\z#u', $entry['path_lower']) === 1
                && ! preg_match('#/(?:archiv|archive|papierkorb)/#u', $entry['path_lower'])));
        usort($roots, fn (array $first, array $second): int => strcmp($first['path_lower'], $second['path_lower']));
        $start = isset($roots[0]) ? $this->suggestionFolder($roots[0]) : null;
        $tokens = array_values(array_filter(explode(' ', $this->folderWords($context['title'])), fn (string $word): bool => strlen($word) >= 3
            && ! in_array($word, ['leistungsfeststellung', 'uebung', 'ubung', 'arbeit', 'teil', 'praktische', 'pruefung', 'prufung'], true)));
        $candidates = [];
        if ($tokens !== []) {
            usort($tokens, fn (string $first, string $second): int => strlen($second) <=> strlen($first));
            $partial = $partial || count($roots) > 2;
            foreach (array_slice($roots, 0, 2) as $root) {
                $topics = array_values(array_filter($search($tokens[0], $root['path_lower']), fn (array $entry): bool => str_starts_with($entry['path_lower'], $root['path_lower'].'/')
                        && $this->matchesFolderTopic($entry['name'], $tokens)));
                if ($topics === []) {
                    $queue = [[$root, 0]];
                    $visited = [];
                    while ($queue !== [] && count($visited) < 8 && $budget > 5) {
                        [$parent, $depth] = array_shift($queue);
                        if (isset($visited[$parent['id']])) {
                            continue;
                        }
                        $visited[$parent['id']] = true;
                        foreach ($list($parent['id']) as $entry) {
                            if (($entry['.tag'] ?? null) !== 'folder' || ! is_string($entry['id'] ?? null)
                                || ! is_string($entry['name'] ?? null) || ! is_string($entry['path_lower'] ?? null)
                                || ! str_starts_with($entry['path_lower'], $root['path_lower'].'/')
                                || preg_match('/\A(?:\.|pdfs?(?:_|\z)|assets\z|images\z|archiv\z|archive\z|papierkorb\z)/iu', $entry['name'])) {
                                continue;
                            }
                            if ($this->matchesFolderTopic($entry['name'], $tokens)) {
                                $topics[$entry['id']] = $entry;
                            } elseif ($depth < 2) {
                                $queue[] = [$entry, $depth + 1];
                            } else {
                                $partial = true;
                            }
                        }
                    }
                    $partial = $partial || $queue !== [];
                    $topics = array_values($topics);
                }
                usort($topics, fn (array $first, array $second): int => substr_count($first['path_lower'], '/') <=> substr_count($second['path_lower'], '/'));
                $partial = $partial || count($topics) > 3;
                foreach (array_slice($topics, 0, 3) as $topic) {
                    if (preg_match('#/(?:leistungsfeststellung|uebungen|übungen)/[^/]+\z#u', $topic['path_lower'])) {
                        $candidates[$topic['id']] = $topic;

                        continue;
                    }
                    foreach ($list($topic['id']) as $collection) {
                        if (($collection['.tag'] ?? null) !== 'folder'
                            || ! in_array($this->folderWords($collection['name']), ['leistungsfeststellung', 'uebungen', 'ubungen', 'uebung', 'ubung'], true)) {
                            continue;
                        }
                        foreach ($list($collection['id']) as $candidate) {
                            if (($candidate['.tag'] ?? null) === 'folder' && is_string($candidate['id'] ?? null)
                                && is_string($candidate['name'] ?? null) && is_string($candidate['path_lower'] ?? null)
                                && str_starts_with($candidate['path_lower'], $root['path_lower'].'/')) {
                                $candidates[$candidate['id']] = $candidate;
                            }
                        }
                    }
                }
            }
        }
        $ranked = [];
        foreach ($candidates as $candidate) {
            $score = $this->scoreFolderSuggestion($context, $candidate);
            if ($score !== null) {
                $ranked[] = $candidate + $score;
            }
        }
        usort($ranked, fn (array $first, array $second): int => $second['score'] <=> $first['score'] ?: strcmp($first['path_lower'], $second['path_lower']));
        $partial = $partial || count($ranked) > 5;
        $checked = [];
        foreach (array_slice($ranked, 0, 5) as $candidate) {
            try {
                $metadataFile = collect($list($candidate['id']))->first(fn (array $entry): bool => ($entry['.tag'] ?? null) === 'file'
                    && mb_strtolower($entry['name'] ?? '') === 'anlage_und_quellen.json');
                if ($metadataFile !== null) {
                    $candidate['strong'] = false;
                    if (is_int($metadataFile['size'] ?? null) && $metadataFile['size'] <= 262144 && is_string($metadataFile['rev'] ?? null)) {
                        $text = $read('files/download', ['path' => 'rev:'.$metadataFile['rev']], true);
                        $metadata = json_decode(str_starts_with($text, "\xEF\xBB\xBF") ? substr($text, 3) : $text, true, 32, JSON_THROW_ON_ERROR);
                        $workId = $metadata['Schooltool_Arbeit_ID'] ?? null;
                        $courseId = $metadata['Schooltool_Kurs_ID'] ?? null;
                        if (($context['work_id'] !== null && $workId !== null && $workId !== $context['work_id'])
                            || ($courseId !== null && ! in_array($courseId, $context['course_ids'], true))) {
                            continue;
                        }
                        if ($context['work_id'] !== null && $workId === $context['work_id'] && in_array($courseId, $context['course_ids'], true)) {
                            $candidate['score'] += 200;
                            $candidate['strong'] = true;
                            $candidate['reason'] = 'Arbeits- und Kurskennung stimmen mit dieser Arbeit überein.';
                        }
                    }
                }
            } catch (ValidationException|JsonException) {
                $candidate['strong'] = false;
                $candidate['reason'] .= ' Importmetadaten konnten nicht geprüft werden.';
            }
            $checked[] = $candidate;
        }
        usort($checked, fn (array $first, array $second): int => $second['score'] <=> $first['score'] ?: strcmp($first['path_lower'], $second['path_lower']));
        $best = $checked[0] ?? null;
        $uniqueBest = $best && $best['strong'] && (! isset($checked[1]) || $best['score'] - $checked[1]['score'] >= 25);
        $clear = ! $partial && $uniqueBest;

        return ['suggested_folder' => $clear ? $this->suggestionFolder($best) : null,
            'suggestions' => array_map($this->suggestionFolder(...), $checked), 'start_folder' => $partial && $uniqueBest ? $this->suggestionFolder($best) : $start,
            'message' => $partial ? 'Begrenzte Suche: Bitte die Vorschläge prüfen oder den Ordner manuell wählen.'
                : ($checked === [] ? ($start ? 'Kein passender Arbeitsordner gefunden. Bitte im Lerninhalte-Bereich auswählen.'
                    : 'Lerninhalte-Bereich nicht gefunden. Bitte den Ordner manuell auswählen.')
                    : 'Ordner vorgeschlagen – erst mit „Diesen Ordner zuordnen“ wird die Zuordnung gespeichert.')];
    }

    /** @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function suggestionFolder(array $entry): array
    {
        return ['id' => $entry['id'], 'name' => $entry['name'], 'path' => $entry['path_display'] ?? $entry['path_lower']]
            + (isset($entry['reason']) ? ['reason' => $entry['reason']] : []);
    }

    private function folderWords(string $value): string
    {
        $value = preg_replace('/(?<![\p{L}\p{N}])e[\s_-]*mails?(?![\p{L}\p{N}])/iu', 'emails', $value);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($value))));
    }

    /** @param list<string> $tokens */
    private function matchesFolderTopic(string $name, array $tokens): bool
    {
        $words = explode(' ', $this->folderWords($name));

        return array_diff($tokens, $words) === []
            || str_replace(' ', '', $this->folderWords($name)) === implode('', $tokens);
    }

    /** @param array<string, mixed> $folder
     * @return ?array{score: int, strong: bool, reason: string}
     */
    private function scoreFolderSuggestion(array $context, array $folder): ?array
    {
        $plausibility = $this->checkFolderContext($context, $folder['name']);
        if ($plausibility['status'] === 'mismatch') {
            return null;
        }
        $words = explode(' ', $this->folderWords($folder['name']));
        $classes = array_map($this->folderWords(...), $context['classes']);
        $folderClasses = array_filter($words, fn (string $word): bool => preg_match('/\A[0-9]+[a-z][a-z0-9]*\z/', $word) === 1);
        $classMatch = $classes !== [] && array_diff($classes, $folderClasses) === [] && array_diff($folderClasses, $classes) === [];
        $dates = $context['dates'];
        $dateMatch = count(array_filter($dates, fn (string $date): bool => str_starts_with($folder['name'], $date.'_'))) > 0;
        $subject = explode(' ', $this->folderWords($context['course_title']))[0];
        $subject = ['inf' => 'informatik', 'dgb' => 'digitale', 'winf' => 'wirtschaftsinformatik', 'medi' => 'medieninformatik'][$subject] ?? $subject;
        $courseMatch = in_array($subject, $words, true);
        if (preg_match('/\A(?:\.|pdfs?(?:_|\z)|assets\z|images\z|archiv\z|archive\z|papierkorb\z)/iu', $folder['name'])) {
            return null;
        }

        return ['score' => 40 + ($classMatch ? 40 : 0) + ($dateMatch ? 60 : 0) + ($courseMatch ? 15 : 0)
                + ($plausibility['status'] === 'match' ? 20 : 0),
            'strong' => $classMatch && $dateMatch && $plausibility['status'] === 'match'
                && ($context['work_id'] !== null || count($context['course_ids']) === 1),
            'reason' => 'Thema passt'.($classMatch ? ', Klassen passen' : '').($courseMatch ? ', Kurs passt' : '').($dateMatch ? ', Termin passt' : '').'. Bitte bestätigen.'
                .($plausibility['status'] === 'unknown' ? ' '.$plausibility['message'] : '')];
    }

    /** @return array{folders: list<array>, cursor: ?string, parent: ?array, current: ?array} */
    public function folders(DropboxConnection $connection, string $id = '', ?string $cursor = null): array
    {
        $data = $this->api($connection, $cursor ? 'files/list_folder/continue' : 'files/list_folder',
            $cursor ? ['cursor' => $cursor] : ['path' => $id, 'recursive' => false, 'limit' => 200])->json();
        $parent = null;
        $currentFolder = null;
        if ($id !== '') {
            $current = $this->metadata($connection, $id);
            $currentFolder = $this->suggestionFolder($current);
            $parentPath = Str::beforeLast($current['path_lower'], '/');
            if ($parentPath !== '') {
                $parentFolder = $this->metadata($connection, $parentPath);
                $parent = $this->suggestionFolder($parentFolder);
            }
        }

        return ['folders' => collect($data['entries'] ?? [])->filter(fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder')
            ->map(fn (array $entry): array => ['id' => $entry['id'], 'name' => $entry['name']]
                + (isset($entry['path_lower']) ? ['path' => $entry['path_display'] ?? $entry['path_lower']] : []))->values()->all(),
            'cursor' => ($data['has_more'] ?? false) ? ($data['cursor'] ?? null) : null, 'parent' => $parent, 'current' => $currentFolder];
    }

    private function api(DropboxConnection $connection, string $endpoint, array $parameters, bool $download = false): Response
    {
        try {
            $client = Http::withToken($this->accessToken($connection))->connectTimeout(5)->timeout(20)
                ->withOptions(['allow_redirects' => false]);
            $response = $download
                ? $client->withHeaders(['Dropbox-API-Arg' => json_encode($parameters, JSON_THROW_ON_ERROR), 'Content-Type' => ''])
                    ->withBody('', '')->post('https://content.dropboxapi.com/2/'.$endpoint)
                : $client->post('https://api.dropboxapi.com/2/'.$endpoint, $parameters);
        } catch (ConnectionException) {
            $this->fail('Dropbox ist momentan nicht erreichbar. Bitte später erneut versuchen oder einen Ordner hochladen.');
        }

        return $this->checkResponse($connection, $response);
    }

    private function checkResponse(DropboxConnection $connection, Response $response): Response
    {
        if ($response->status() === 401) {
            $connection->update(['revoked_at' => now()]);
            $this->fail('Dropbox hat die Freigabe abgelehnt. Bitte erneut verbinden.');
        }
        if (! $response->successful()) {
            if ($response->status() === 429) {
                $this->fail('Dropbox begrenzt momentan die Anfragen. Bitte kurz warten und die Vorschau erneut laden.');
            }
            $this->fail($response->status() === 409
                ? 'Der Dropbox-Ordner oder eine Importdatei ist nicht mehr verfügbar. Bitte den Ordner prüfen oder neu zuordnen.'
                : 'Dropbox-Anfrage fehlgeschlagen. Bitte später erneut versuchen oder einen Ordner hochladen.');
        }

        return $response;
    }

    /**
     * @param  array<string, array{entry: array<string, mixed>, name: string, limit: int, path?: string}>  $files
     * @return array<string, Response>
     */
    private function downloadFiles(DropboxConnection $connection, array $files, int $total): array
    {
        if ($files === []) {
            return [];
        }
        foreach ($files as $file) {
            $total = $this->checkDownloadSize($file['entry'], $file['limit'], $total);
        }
        $token = $this->accessToken($connection);
        $responses = Http::pool(function (Pool $pool) use ($files, $token): void {
            foreach ($files as $key => $file) {
                $pool->as($key)->withToken($token)->connectTimeout(5)->timeout(20)
                    ->withOptions(['allow_redirects' => false])
                    ->withHeaders(['Dropbox-API-Arg' => json_encode(['path' => 'rev:'.$file['entry']['rev']], JSON_THROW_ON_ERROR), 'Content-Type' => ''])
                    ->withBody('', '')->post('https://content.dropboxapi.com/2/files/download');
            }
        }, concurrency: 4);
        foreach ($files as $key => $file) {
            $response = $responses[$key] ?? null;
            if (! $response instanceof Response) {
                $this->fail('Dropbox ist momentan nicht erreichbar. Bitte später erneut versuchen oder einen Ordner hochladen.');
            }
            $this->checkResponse($connection, $response);
        }

        return $responses;
    }

    /** @param array<string, mixed> $entry */
    private function checkDownloadSize(array $entry, int $limit, int $total): int
    {
        if (! is_int($entry['size'] ?? null) || $entry['size'] < 0 || $entry['size'] > $limit
            || $total + $entry['size'] > self::MAX_BYTES || ! is_string($entry['rev'] ?? null)) {
            $this->fail('Die Dropbox-Importdateien überschreiten die zulässige Größe.');
        }

        return $total + $entry['size'];
    }

    /** @param Closure(array<string, mixed>, array<string, mixed>): mixed $consume */
    public function withImportFiles(User $user, TeachingCourseWork $work, Closure $consume): mixed
    {
        $connection = $this->connection($user);
        $mapping = TeachingWorkDropboxFolder::query()->where('dropbox_connection_id', $connection->id)
            ->where('teaching_course_work_id', $work->id)->first();
        if (! $mapping) {
            $this->fail('Für diese Arbeit ist noch kein Dropbox-Ordner zugeordnet.');
        }
        $root = $this->metadata($connection, $mapping->folder_id);
        $plausibility = $this->folderPlausibility($work, $root['name']);
        if ($plausibility['status'] === 'mismatch') {
            $this->fail($plausibility['message']);
        }
        $entries = [];
        $cursor = null;
        do {
            $data = $this->api($connection, $cursor ? 'files/list_folder/continue' : 'files/list_folder',
                $cursor ? ['cursor' => $cursor] : ['path' => $root['id'], 'recursive' => true, 'limit' => 200])->json();
            foreach ($data['entries'] ?? [] as $entry) {
                if (count($entries) >= 2000) {
                    $this->fail('Dieser Dropbox-Ordner ist zu groß. Bitte den einzelnen Leistungsarbeitsordner zuordnen.');
                }
                $entries[] = $entry;
            }
            $cursor = ($data['has_more'] ?? false) ? ($data['cursor'] ?? null) : null;
        } while ($cursor);
        $files = [];
        foreach ($entries as $entry) {
            if (($entry['.tag'] ?? null) !== 'file') {
                continue;
            }
            $prefix = $root['path_lower'].'/';
            if (! str_starts_with($entry['path_lower'] ?? '', $prefix)) {
                $this->fail('Dropbox lieferte eine Datei außerhalb des zugeordneten Ordners.');
            }
            $relative = substr($entry['path_lower'], strlen($prefix));
            if (isset($files[$relative])) {
                $this->fail('Mehrdeutige Importdatei im Dropbox-Ordner.');
            }
            $files[$relative] = $entry;
        }
        $packages = array_intersect_key($files, array_flip(['schooltool-bewertungen.json', 'beurteilungen/schooltool-bewertungen.json']));
        if (count($packages) > 1) {
            $this->fail('Der Dropbox-Ordner darf nur eine Schooltool-Bewertungen.json direkt oder unter Beurteilungen enthalten.');
        }
        $temporaryPaths = [];
        $total = 0;
        try {
            $saveDownload = function (array $entry, string $name, int $limit, string $contents) use (&$temporaryPaths, &$total): UploadedFile {
                $this->checkDownloadSize($entry, $limit, $total);
                $total += strlen($contents);
                if (strlen($contents) !== $entry['size'] || strlen($contents) > $limit || $total > self::MAX_BYTES) {
                    $this->fail('Dropbox-Dateigröße hat sich geändert oder überschreitet die Importgrenze. Bitte Vorschau erneut laden.');
                }
                $path = tempnam(sys_get_temp_dir(), 'schooltool-dropbox-');
                if ($path === false) {
                    $this->fail('Die Importdateien konnten nicht vorübergehend gespeichert werden.');
                }
                $temporaryPaths[] = $path;
                if (file_put_contents($path, $contents) !== strlen($contents)) {
                    $this->fail('Die Importdateien konnten nicht vorübergehend gespeichert werden.');
                }

                return new UploadedFile($path, $name, null, null, true);
            };
            $packagePath = array_key_first($packages);
            $package = null;
            $references = [];
            if ($packagePath !== null) {
                $this->checkDownloadSize($packages[$packagePath], 262144, $total);
                $package = $saveDownload($packages[$packagePath], 'Schooltool-Bewertungen.json', 262144,
                    $this->api($connection, 'files/download', ['path' => 'rev:'.$packages[$packagePath]['rev']], true)->body());
                try {
                    $payload = json_decode($package->get(), true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    $this->fail('Schooltool-Bewertungen.json kann nicht als UTF-8-JSON gelesen werden.');
                }
                if (! is_array($payload) || ! is_array($payload['records'] ?? null)) {
                    $this->fail('Schooltool-Bewertungen.json enthält keine gültige Datensatzliste.');
                }
                $references = array_filter([$payload['overview_pdf'] ?? null, ...array_column($payload['records'], 'pdf')]);
            }
            $directory = $packagePath !== null && str_starts_with($packagePath, 'beurteilungen/') ? 'beurteilungen/' : '';
            $downloads = [];
            if ($package !== null && isset($files['schooltool_arbeitsdaten.json'])) {
                $downloads['identity_export'] = ['entry' => $files['schooltool_arbeitsdaten.json'], 'name' => 'Schooltool_Arbeitsdaten.json', 'limit' => 1048576];
            }
            $pdfNames = [];
            foreach ($references as $reference) {
                $name = $reference['filename'] ?? null;
                if (! is_string($name) || ! preg_match('/\A[^\/\\\\\x00-\x1f]+\.pdf\z/ui', $name)) {
                    $this->fail('Ungültige PDF-Referenz im Dropbox-Paket.');
                }
                if (isset($pdfNames[$name])) {
                    continue;
                }
                $entry = $files[$directory.mb_strtolower($name)] ?? null;
                if (! $entry || count($pdfNames) >= 20) {
                    $this->fail('Referenziertes PDF fehlt oder das Paket enthält mehr als 20 PDFs.');
                }
                $pdfNames[$name] = true;
                $downloads['pdf:'.$name] = ['entry' => $entry, 'name' => $name, 'limit' => self::MAX_BYTES];
            }
            $protocolCount = 0;
            foreach ($files as $relative => $entry) {
                if (in_array($relative, ['anlage_und_quellen.json', 'schooltool-vorbereitung.json', 'schooltool-versandvorbereitung.json', 'schooltool-bereitstellung.json'], true)) {
                    $name = match ($relative) {
                        'anlage_und_quellen.json' => 'Anlage_und_Quellen.json',
                        'schooltool-vorbereitung.json' => 'Schooltool-Vorbereitung.json',
                        'schooltool-versandvorbereitung.json' => 'Schooltool-Versandvorbereitung.json',
                        default => 'Schooltool-Bereitstellung.json',
                    };
                    $downloads[$relative] = ['entry' => $entry, 'name' => $name, 'limit' => 1048576,
                        'path' => $root['name'].'/'.$name];

                    continue;
                }
                if (! preg_match('#\Aversand/(aufgaben|ergebnisse)/versand_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}/versandprotokoll\.txt\z#', $relative, $matches)) {
                    continue;
                }
                if ($protocolCount >= 30) {
                    $this->fail('Maximal 30 Versandprotokolle pro Ordnerimport.');
                }
                $path = 'Versand/'.($matches[1] === 'aufgaben' ? 'Aufgaben' : 'Ergebnisse').'/'.ucfirst(basename(dirname($relative))).'/Versandprotokoll.txt';
                $downloads['protocol:'.$protocolCount++] = ['entry' => $entry, 'name' => 'Versandprotokoll.txt', 'limit' => 1048576, 'path' => $root['name'].'/'.$path];
            }
            $responses = $this->downloadFiles($connection, $downloads, $total);
            $pdfs = [];
            $documents = [];
            $identityExport = null;
            foreach ($downloads as $key => $file) {
                $upload = $saveDownload($file['entry'], $file['name'], $file['limit'], $responses[$key]->body());
                if ($key === 'identity_export') {
                    $identityExport = $upload;
                } elseif (isset($file['path'])) {
                    $documents[] = ['path' => $file['path'], 'text' => $upload->get()];
                } else {
                    $pdfs[] = $upload;
                }
            }

            if ($package === null && $documents === []) {
                $this->fail('Der Dropbox-Ordner enthält weder Bewertungen noch Bereitstellungsbelege, eine Vorbereitung oder Versandprotokolle.');
            }

            return $consume(['folder' => $root['name'], 'documents' => json_encode($documents, JSON_THROW_ON_ERROR)],
                array_filter(['package' => $package, 'pdfs' => $pdfs, 'identity_export' => $identityExport]));
        } finally {
            foreach ($temporaryPaths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['dropbox' => $message]);
    }
}
