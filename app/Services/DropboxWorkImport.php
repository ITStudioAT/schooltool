<?php

namespace App\Services;

use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\User;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
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

    public function authorizationUrl(string $state): string
    {
        $this->requireReady();

        return 'https://www.dropbox.com/oauth2/authorize?'.http_build_query([
            'client_id' => config('services.dropbox.client_id'),
            'redirect_uri' => config('services.dropbox.redirect_uri'),
            'response_type' => 'code', 'token_access_type' => 'offline',
            'scope' => implode(' ', self::SCOPES), 'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function connect(User $user, string $code): void
    {
        $this->requireReady();
        $data = $this->token(['grant_type' => 'authorization_code', 'code' => $code,
            'redirect_uri' => config('services.dropbox.redirect_uri')]);
        if (! is_string($data['refresh_token'] ?? null) || ! is_string($data['account_id'] ?? null)) {
            $this->fail('Dropbox hat keine dauerhafte Benutzerfreigabe geliefert. Bitte erneut verbinden.');
        }
        $scopes = preg_split('/\s+/', trim((string) ($data['scope'] ?? '')));
        if (array_diff(self::SCOPES, $scopes) !== [] || array_diff($scopes, self::SCOPES) !== []) {
            $this->fail('Die Dropbox-Freigabe muss ausschließlich die beiden benötigten Leserechte enthalten.');
        }
        DB::transaction(function () use ($user, $data): void {
            $connection = DropboxConnection::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if ($connection && $connection->account_id !== $data['account_id']) {
                TeachingWorkDropboxFolder::query()->where('dropbox_connection_id', $connection->id)->delete();
            }
            DropboxConnection::query()->updateOrCreate(['user_id' => $user->id], [
                'account_id' => $data['account_id'], 'credentials' => ['refresh_token' => $data['refresh_token']], 'revoked_at' => null,
            ]);
        });
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

    /** @return array{folders: list<array{id:string,name:string}>, cursor: ?string} */
    public function folders(DropboxConnection $connection, string $id = '', ?string $cursor = null): array
    {
        $data = $this->api($connection, $cursor ? 'files/list_folder/continue' : 'files/list_folder',
            $cursor ? ['cursor' => $cursor] : ['path' => $id, 'recursive' => false, 'limit' => 200])->json();

        return ['folders' => collect($data['entries'] ?? [])->filter(fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder')
            ->map(fn (array $entry): array => ['id' => $entry['id'], 'name' => $entry['name']])->values()->all(),
            'cursor' => ($data['has_more'] ?? false) ? ($data['cursor'] ?? null) : null];
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
        if ($response->status() === 401) {
            $connection->update(['revoked_at' => now()]);
            $this->fail('Dropbox hat die Freigabe abgelehnt. Bitte erneut verbinden.');
        }
        if (! $response->successful()) {
            $this->fail($response->status() === 409
                ? 'Der Dropbox-Ordner oder eine Importdatei ist nicht mehr verfügbar. Bitte den Ordner prüfen oder neu zuordnen.'
                : 'Dropbox-Anfrage fehlgeschlagen. Bitte später erneut versuchen oder einen Ordner hochladen.');
        }

        return $response;
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
        if (count($packages) !== 1) {
            $this->fail('Der Dropbox-Ordner muss genau eine Schooltool-Bewertungen.json direkt oder unter Beurteilungen enthalten.');
        }
        $temporaryPaths = [];
        $total = 0;
        try {
            $download = function (array $entry, string $name, int $limit) use ($connection, &$temporaryPaths, &$total): UploadedFile {
                if (($entry['size'] ?? -1) < 0 || $entry['size'] > $limit || $total + $entry['size'] > self::MAX_BYTES
                    || ! is_string($entry['rev'] ?? null)) {
                    $this->fail('Die Dropbox-Importdateien überschreiten die zulässige Größe.');
                }
                $contents = $this->api($connection, 'files/download', ['path' => 'rev:'.$entry['rev']], true)->body();
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
            $package = $download($packages[$packagePath], 'Schooltool-Bewertungen.json', 262144);
            try {
                $payload = json_decode($package->get(), true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $this->fail('Schooltool-Bewertungen.json kann nicht als UTF-8-JSON gelesen werden.');
            }
            if (! is_array($payload) || ! is_array($payload['records'] ?? null)) {
                $this->fail('Schooltool-Bewertungen.json enthält keine gültige Datensatzliste.');
            }
            $directory = str_starts_with($packagePath, 'beurteilungen/') ? 'beurteilungen/' : '';
            $references = array_filter([$payload['overview_pdf'] ?? null, ...array_column($payload['records'], 'pdf')]);
            $pdfs = [];
            foreach ($references as $reference) {
                $name = $reference['filename'] ?? null;
                if (! is_string($name) || ! preg_match('/\A[^\/\\\\\x00-\x1f]+\.pdf\z/ui', $name)) {
                    $this->fail('Ungültige PDF-Referenz im Dropbox-Paket.');
                }
                if (isset($pdfs[$name])) {
                    continue;
                }
                $entry = $files[$directory.mb_strtolower($name)] ?? null;
                if (! $entry || count($pdfs) >= 20) {
                    $this->fail('Referenziertes PDF fehlt oder das Paket enthält mehr als 20 PDFs.');
                }
                $pdfs[$name] = $download($entry, $name, self::MAX_BYTES);
            }
            $documents = [];
            foreach ($files as $relative => $entry) {
                if (! preg_match('#\Aversand/(aufgaben|ergebnisse)/versand_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}/versandprotokoll\.txt\z#', $relative, $matches)) {
                    continue;
                }
                if (count($documents) >= 30) {
                    $this->fail('Maximal 30 Versandprotokolle pro Ordnerimport.');
                }
                $file = $download($entry, 'Versandprotokoll.txt', 1048576);
                $path = 'Versand/'.($matches[1] === 'aufgaben' ? 'Aufgaben' : 'Ergebnisse').'/'.ucfirst(basename(dirname($relative))).'/Versandprotokoll.txt';
                $documents[] = ['path' => $root['name'].'/'.$path, 'text' => $file->get()];
            }

            return $consume(['folder' => $root['name'], 'documents' => json_encode($documents, JSON_THROW_ON_ERROR)],
                ['package' => $package, 'pdfs' => array_values($pdfs)]);
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
