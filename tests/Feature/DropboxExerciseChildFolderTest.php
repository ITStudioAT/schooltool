<?php

use App\Exceptions\DropboxExerciseRequestFailed;
use App\Models\DropboxConnection;
use App\Services\DropboxWorkImport;
use Carbon\CarbonInterval;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config(['services.dropbox.client_id' => 'test', 'services.dropbox.client_secret' => 'test']);
    $this->connection = new DropboxConnection(['credentials' => ['refresh_token' => 'test', 'scopes' => ['files.content.write']]]);
    $this->connection->id = 101;
    Http::preventStrayRequests();
});

function fakeExerciseFolderTransport(array $createResponse, int $status = 200, array $headers = []): void
{
    Http::fake(function (Request $request) use ($createResponse, $status, $headers) {
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'test'], 200);
        }
        if (str_ends_with($request->url(), '/files/get_metadata')) {
            $child = $request['path'] === 'id:child';

            return Http::response(['.tag' => 'folder', 'id' => $child ? 'id:child' : 'id:parent',
                'name' => $child ? 'Kandidaten' : 'Beispiel',
                'path_lower' => '/übungsbereich/beispiel'.($child ? '/kandidaten' : ''),
                'path_display' => '/Übungsbereich/Beispiel'.($child ? '/Kandidaten' : '')], 200);
        }
        if (str_ends_with($request->url(), '/files/create_folder_v2')) {
            return Http::response($createResponse, $status, $headers);
        }
        throw new RuntimeException('Unexpected fake endpoint');
    });
}

test('exercise folder confirmation accepts Dropbox lower case Unicode paths', function (): void {
    fakeExerciseFolderTransport(['metadata' => ['id' => 'id:child']]);
    $created = app(DropboxWorkImport::class)->createExerciseChildFolder($this->connection, 'id:parent', 'Kandidaten', '/übungsbereich/beispiel');
    expect($created['id'])->toBe('id:child');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/create_folder_v2')
        && $request['path'] === '/Übungsbereich/Beispiel/Kandidaten' && $request['autorename'] === false);
});

test('exercise folder creation reports safe Dropbox conflict type without leaking details', function (string $tag, string $expected): void {
    Sleep::fake();
    fakeExerciseFolderTransport(['error_summary' => 'secret/path/data', 'error' => ['.tag' => 'path',
        'path' => ['.tag' => $tag, 'malformed_path' => 'secret/path/data']]], 409);
    try {
        app(DropboxWorkImport::class)->createExerciseChildFolder($this->connection, 'id:parent', 'Kandidaten', '/übungsbereich/beispiel');
        $this->fail('Expected a validation error');
    } catch (ValidationException $exception) {
        $message = $exception->errors()['dropbox'][0] ?? '';
        expect($message)->toContain($expected)->not->toContain('secret')->not->toContain('/übungsbereich');
    }
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/create_folder_v2') && $request['autorename'] === false);
})->with([
    ['conflict', 'Konflikt'],
    ['malformed_path', 'ungültig'],
    ['no_write_permission', 'Schreibberechtigung'],
    ['insufficient_space', 'Speicherplatz'],
    ['disallowed_name', 'Ordnernamen'],
    ['too_many_write_operations', 'Schreibvorgänge'],
    ['unexpected', 'nicht näher bestimmten Konflikt'],
]);

test('exercise folder creation does not retry beyond the rate-limit wait budget', function (): void {
    Sleep::fake();
    fakeExerciseFolderTransport(['error_summary' => 'sensitive'], 429, ['Retry-After' => '300']);
    try {
        app(DropboxWorkImport::class)->createExerciseChildFolder($this->connection, 'id:parent', 'Kandidaten', '/übungsbereich/beispiel');
        $this->fail('Expected a validation error');
    } catch (DropboxExerciseRequestFailed $exception) {
        expect($exception->errors()['dropbox'][0])->toContain('begrenzt')->not->toContain('sensitive');
        expect($exception->diagnostic())->toMatchArray(['operation' => 'folder_create', 'category' => 'rate_limit', 'http_status' => 429]);
    }
    Http::assertSentCount(3); // Token, parent metadata and a single create attempt.
    Sleep::assertNeverSlept();
});

test('rate-limited folder creation backs off and retries the exact destination', function (): void {
    Sleep::fake();
    $writes = [];
    Http::fake(function (Request $request) use (&$writes) {
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'test']);
        }
        if (str_ends_with($request->url(), '/files/create_folder_v2')) {
            $writes[] = $request->data();

            return match (count($writes)) {
                1 => Http::response([], 429),
                2 => Http::response([], 429, ['Retry-After' => '0']),
                3 => Http::response([], 429, ['Retry-After' => '25']),
                default => Http::response(['metadata' => ['id' => 'id:child']]),
            };
        }
        $child = $request['path'] === 'id:child';

        return Http::response(['.tag' => 'folder', 'id' => $child ? 'id:child' : 'id:parent',
            'name' => $child ? 'Kandidaten' : 'Prüfung',
            'path_lower' => '/prüfung'.($child ? '/kandidaten' : ''),
            'path_display' => '/Prüfung'.($child ? '/Kandidaten' : '')]);
    });

    $folder = app(DropboxWorkImport::class)->createExerciseChildFolder($this->connection, 'id:parent', 'Kandidaten', '/prüfung');

    expect($folder['id'])->toBe('id:child')
        ->and($writes)->toBe(array_fill(0, 4, ['path' => '/Prüfung/Kandidaten', 'autorename' => false]));
    Sleep::assertSleptTimes(3);
    foreach ([1, 2, 25] as $expectedDelay) {
        Sleep::assertSlept(fn (CarbonInterval $delay): bool => (float) $delay->totalSeconds === (float) $expectedDelay);
    }
});

test('exercise folder creation hides malformed request details while retaining authentication errors', function (int $status, string $expected): void {
    fakeExerciseFolderTransport(['error_summary' => 'secret/path/token', 'error' => ['.tag' => 'secret/path/token']], $status);
    try {
        app(DropboxWorkImport::class)->createExerciseChildFolder($this->connection, 'id:parent', 'Kandidaten', '/übungsbereich/beispiel');
        $this->fail('Expected a validation error');
    } catch (ValidationException $exception) {
        expect($exception->errors()['dropbox'][0])->toContain($expected)->not->toContain('secret');
    }
})->with([[400, 'HTTP 400'], [401, 'Freigabe abgelehnt']]);

test('exercise folder transport passes an entire 223 character Dropbox path without alteration', function (): void {
    $parentPath = '/'.str_repeat('a', 211);
    $childPath = $parentPath.'/Kandidaten';
    expect(mb_strlen($childPath))->toBe(223);
    Http::fake(function (Request $request) use ($parentPath, $childPath) {
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'test'], 200);
        }
        if (str_ends_with($request->url(), '/files/get_metadata')) {
            $child = $request['path'] === 'id:child';

            return Http::response(['.tag' => 'folder', 'id' => $child ? 'id:child' : 'id:parent',
                'name' => $child ? 'Kandidaten' : basename($parentPath),
                'path_lower' => $child ? mb_strtolower($childPath) : $parentPath,
                'path_display' => $child ? $childPath : $parentPath], 200);
        }
        if (str_ends_with($request->url(), '/files/create_folder_v2')) {
            return Http::response(['metadata' => ['id' => 'id:child']], 200);
        }
        throw new RuntimeException('Unexpected fake endpoint');
    });
    expect(app(DropboxWorkImport::class)->createExerciseChildFolder($this->connection, 'id:parent', 'Kandidaten', $parentPath)['id'])->toBe('id:child');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/create_folder_v2')
        && $request['path'] === $childPath && $request['autorename'] === false);
});

test('exercise folder retries only the read after Dropbox accepts the second candidate subsection', function (int $firstStatus): void {
    Sleep::fake();
    $parentPath = '/pruefung/kandidaten/testperson 02';
    $childReads = 0;
    $creates = 0;
    Http::fake(function (Request $request) use ($firstStatus, $parentPath, &$childReads, &$creates) {
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'test'], 200);
        }
        if (str_ends_with($request->url(), '/files/create_folder_v2')) {
            $creates++;
            expect($request['path'])->toBe('/Pruefung/Kandidaten/Testperson 02/Angaben');
            expect($request['autorename'])->toBeFalse();

            return Http::response(['metadata' => ['id' => 'id:section']], 200);
        }
        $child = $request['path'] === 'id:section';
        if ($child && ++$childReads === 1) {
            expect($creates)->toBe(1);

            return Http::response(['error' => ['.tag' => 'too_many_requests']], $firstStatus, ['Retry-After' => '2']);
        }

        return Http::response(['.tag' => 'folder', 'id' => $child ? 'id:section' : 'id:person',
            'name' => $child ? 'Angaben' : 'Testperson 02', 'path_lower' => $parentPath.($child ? '/angaben' : ''),
            'path_display' => '/Pruefung/Kandidaten/Testperson 02'.($child ? '/Angaben' : '')]);
    });

    $folder = app(DropboxWorkImport::class)->createExerciseChildFolder($this->connection, 'id:person', 'Angaben', $parentPath);
    expect($folder['id'])->toBe('id:section')->and($creates)->toBe(1)->and($childReads)->toBe(2);
    Sleep::assertSleptTimes(1);
    Sleep::assertSlept(fn (CarbonInterval $delay): bool => (float) $delay->totalSeconds === 2.0);
})->with([429, 503]);

test('exercise folder names its failing metadata stage and never replays an unconfirmed write', function (string $stage, ?int $status, int $expectedCreates): void {
    Sleep::fake();
    $attempts = 0;
    $creates = 0;
    Http::fake(function (Request $request) use ($stage, $status, &$attempts, &$creates) {
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'test'], 200);
        }
        if (str_ends_with($request->url(), '/files/create_folder_v2')) {
            $creates++;
            if ($stage === 'folder_create') {
                $attempts++;

                return $status === null ? Http::failedConnection('/private/Pfad') : Http::response(['error_summary' => '/private/Pfad'], $status);
            }

            return Http::response(['metadata' => ['id' => 'id:section']], 200);
        }
        $child = $request['path'] === 'id:section';
        if (($child ? 'folder_child_metadata' : 'folder_parent_metadata') === $stage) {
            $attempts++;

            return Http::response(['error_summary' => '/private/Pfad'], $status);
        }

        return Http::response(['.tag' => 'folder', 'id' => 'id:person', 'name' => 'Testperson 02', 'path_lower' => '/testperson 02']);
    });

    try {
        app(DropboxWorkImport::class)->createExerciseChildFolder($this->connection, 'id:person', 'Angaben', '/testperson 02');
        $this->fail('Expected a classified Dropbox error');
    } catch (DropboxExerciseRequestFailed $exception) {
        expect($exception->diagnostic())->toBe(['operation' => $stage, 'category' => $status === null ? 'network' : 'provider_unavailable', 'http_status' => $status]);
        expect($exception->endpoint())->toBe($stage === 'folder_create' ? 'files/create_folder_v2' : 'files/get_metadata');
        expect($exception->safePublicMessage)->not->toContain('/private/Pfad')->not->toContain('Testperson');
    }
    expect($creates)->toBe($expectedCreates)->and($attempts)->toBe($stage === 'folder_create' ? 1 : 3);
})->with([['folder_parent_metadata', 502, 0], ['folder_create', 503, 1], ['folder_create', null, 1], ['folder_child_metadata', 503, 1]]);

test('exercise status revision retry respects Dropbox wait and never permits overwrite', function (): void {
    Sleep::fake();
    $uploads = [];
    Http::fake(function (Request $request) use (&$uploads) {
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'test'], 200);
        }
        if (str_ends_with($request->url(), '/files/get_metadata')) {
            return Http::response(['.tag' => 'folder', 'id' => 'id:exam', 'name' => 'Prüfung', 'path_lower' => '/prüfung']);
        }
        $uploads[] = json_decode($request->header('Dropbox-API-Arg')[0], true, 8, JSON_THROW_ON_ERROR);
        if (count($uploads) === 1) {
            return Http::response(['error' => ['.tag' => 'too_many_write_operations']], 429, ['Retry-After' => '3']);
        }

        return Http::response(['rev' => 'new', 'size' => strlen($request->body())], 200);
    });

    expect(app(DropboxWorkImport::class)->writeExerciseDocument($this->connection, 'id:exam', 'status.json', '{}', 'known'))->toBe('new');
    expect($uploads)->toBe(array_fill(0, 2, ['path' => 'id:exam/status.json', 'mode' => ['.tag' => 'update', 'update' => 'known'], 'autorename' => false, 'strict_conflict' => true]));
    Sleep::assertSleptTimes(1);
});
