<?php

use App\Models\DropboxConnection;
use App\Models\McAttempt;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\TeachingWorkModuleLink;
use App\Models\User;
use App\Services\DropboxModuleLinks;
use App\Services\DropboxWorkImport;
use App\Services\ModuleHandshake;
use App\Services\TeachingAssessmentTargetGuard;
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Services\TeachingDirectAssessment;
use App\Services\TeachingWorkDeadlineSync;
use App\Support\SchooltoolAssessmentJson;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TeachingWorkDispatchFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['app.url' => 'http://localhost:8000', 'services.dropbox.client_id' => 'test-client', 'services.dropbox.client_secret' => 'test-secret',
        'services.dropbox.redirect_uri' => 'http://localhost:8000/admin/teaching/dropbox/callback']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    enableSchoolToolModuleForTests($school, 'teaching');
    grantSchoolToolLicenceForTests($school, 'Lehrertool');
    $this->user = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id])->assignRole('admin');
    $this->course = TeachingCourse::factory()->forSchool($school)->forSchoolyear($year)->forTeacher($this->user)->create();
    $this->work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $this->connection = DropboxConnection::factory()->create(['user_id' => $this->user->id]);
    $this->url = '/api/admin/teaching/course_works/'.$this->work->id.'/dropbox';
    $this->handshake = '{"schema_version":1,"module_id":"A263B873-4f27-4167-90f0-7d94ea0f67fa"}';
    $this->online = [['.tag' => 'file', 'id' => 'id:handshake', 'name' => 'handshake.json', 'rev' => 'revision1',
        'path_lower' => '/schule2020_2027/materialien/lerninhalte/it_grundlagen/emails - kopie/handshake.json',
        'path_display' => '/schule2020_2027/Materialien/Lerninhalte/IT_Grundlagen/emails - Kopie/handshake.json',
        'size' => strlen($this->handshake)]];
    $this->remoteContents = $this->handshake;
    $this->actingAs($this->user, 'sanctum');
});

function fakeModuleDropbox(object $context): void
{
    Http::fake(function (Request $request) use ($context) {
        if ($context->exerciseOffline ?? false) {
            return Http::response([], 503);
        }
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'module-test-access']);
        }
        if (str_ends_with($request->url(), '/files/search_v2')) {
            expect($request['options']['filename_only'])->toBeTrue();

            if (isset($context->searchResponse)) {
                return Http::response($context->searchResponse);
            }
            if ($context->searchPaginated ?? false) {
                return Http::response(['matches' => array_map(fn (array $entry): array => ['metadata' => ['metadata' => $entry]], array_slice($context->online, 0, 30)),
                    'has_more' => true, 'cursor' => 'handshake-page-2']);
            }

            return Http::response(['matches' => array_map(fn (array $entry): array => ['metadata' => ['metadata' => $entry]], $context->online), 'has_more' => false]);
        }
        if (str_ends_with($request->url(), '/files/search/continue_v2')) {
            expect($request['cursor'])->toBe('handshake-page-2');

            return Http::response(['matches' => array_map(fn (array $entry): array => ['metadata' => ['metadata' => $entry]], array_slice($context->online, 30)), 'has_more' => false]);
        }
        if (str_ends_with($request->url(), '/files/download')) {
            $argument = json_decode($request->header('Dropbox-API-Arg')[0], true);
            if (($context->failDownloadPath ?? null) === ($argument['path'] ?? null)) {
                return Http::response(['error_summary' => 'fixture_error/'], 400);
            }
            if (isset($context->onPreparedPreviewDownload) && $argument['path'] === $context->preparedPreviewRevision) {
                ($context->onPreparedPreviewDownload)();
                unset($context->onPreparedPreviewDownload);
            }
            if (isset($context->exerciseDocuments[$argument['path']])) {
                return Http::response($context->exerciseDocuments[$argument['path']]);
            }

            return Http::response($context->remoteContents);
        }
        if (str_ends_with($request->url(), '/files/move_v2')) {
            $context->moveCount = ($context->moveCount ?? 0) + 1;
            $source = collect($context->online)->firstWhere('id', $request['from_path']);
            if (! $source || ($context->moveFails ?? false) || ($context->failMoveAt ?? 0) === $context->moveCount) {
                return Http::response(['error_summary' => 'from_lookup/not_found/'], 409);
            }
            $target = $request['to_path'];
            if (collect($context->online)->contains(fn (array $entry): bool => ($entry['path_lower'] ?? null) === mb_strtolower($target))) {
                return Http::response(['error_summary' => 'to/conflict/'], 409);
            }
            foreach ($context->online as &$entry) {
                if (($entry['path_display'] ?? '') === $source['path_display'] || str_starts_with($entry['path_display'] ?? '', $source['path_display'].'/')) {
                    $entry['path_display'] = $target.substr($entry['path_display'], strlen($source['path_display']));
                    $entry['path_lower'] = mb_strtolower($entry['path_display']);
                    if ($entry['id'] === $source['id']) {
                        $entry['name'] = basename($target);
                    }
                }
            }
            unset($entry);

            if ($context->moveLosesResponse ?? false) {
                return Http::response([], 503);
            }

            return Http::response(['metadata' => collect($context->online)->firstWhere('id', $source['id'])]);
        }
        if (str_ends_with($request->url(), '/files/delete_v2')) {
            $context->deleteCount = ($context->deleteCount ?? 0) + 1;
            $source = collect($context->online)->firstWhere('id', $request['path']);
            if (! $source || ($context->failDeleteAt ?? 0) === $context->deleteCount) {
                return Http::response([], 503);
            }
            $context->deletedIds[] = $source['id'];
            $context->online = array_values(array_filter($context->online,
                fn (array $entry): bool => ($entry['path_lower'] ?? '') !== $source['path_lower']
                    && ! str_starts_with($entry['path_lower'] ?? '', $source['path_lower'].'/')));

            if (($context->loseDeleteResponseAt ?? 0) === $context->deleteCount) {
                return Http::response([], 503);
            }

            return Http::response(['metadata' => $source]);
        }
        if (str_ends_with($request->url(), '/files/get_metadata')) {
            if ($request['path'] === '/schule2020_2027/materialien/lerninhalte') {
                return Http::response(['.tag' => 'folder', 'id' => 'id:materialsroot', 'name' => 'Lerninhalte',
                    'path_lower' => $request['path'], 'path_display' => '/schule2020_2027/Materialien/Lerninhalte']);
            }
            $path = $request['path'];
            if (($context->createExercise ?? false) && str_starts_with($path, 'id:') && str_contains($path, '/')) {
                $parent = collect($context->online)->firstWhere('id', dirname($path));
                $path = $parent['path_lower'].'/'.mb_strtolower(basename($path));
            }
            if ((($context->resetExerciseStatus ?? false) || ($context->createExercise ?? false))
                && str_starts_with($path, 'id:') && str_ends_with($path, '/status.json')) {
                $folder = collect($context->online)->firstWhere('id', dirname($path));
                $path = $folder['path_lower'].'/status.json';
            }
            foreach ($context->online as $entry) {
                if ($entry['id'] === $path || $entry['path_lower'] === mb_strtolower($path)) {
                    if (($context->shareAfterPreflightId ?? null) === $path && ($context->recursivePreflights ?? 0) >= 2) {
                        expect($request['include_has_explicit_shared_members'])->toBeTrue();
                        $entry['has_explicit_shared_members'] = true;
                    }
                    if (($context->varyParentCaseForId ?? null) === $request['path'] && isset($entry['path_display'])) {
                        $entry['path_display'] = mb_strtolower(dirname($entry['path_display'])).'/'.$entry['name'];
                    }
                    if (($context->changedExerciseRevision ?? false) && $entry['name'] === 'Anlage_und_Quellen.json') {
                        $entry['rev'] = 'changed-revision';
                    }
                    if (($context->changedExercisePath ?? false) && $entry['name'] === 'Anlage_und_Quellen.json') {
                        $entry['path_lower'] .= '-moved';
                    }
                    if (($context->changedPreparationFile ?? null) === $entry['name']) {
                        $entry['rev'] = 'changed-revision';
                    }

                    return Http::response($entry);
                }
                $parent = substr($entry['path_lower'], 0, strrpos($entry['path_lower'], '/'));
                if (in_array($request['path'], [$parent, 'id:folder'.basename($parent)], true)) {
                    return Http::response(['.tag' => 'folder', 'id' => 'id:folder'.basename($parent), 'name' => basename($parent),
                        'path_lower' => $parent, 'path_display' => substr($entry['path_display'], 0, strrpos($entry['path_display'], '/')),
                        'sharing_info' => ['parent_shared_folder_id' => '186736344']]);
                }
            }

            return Http::response(['error_summary' => 'path/not_found/'], 409);
        }
        if (($context->createExercise ?? false) && str_ends_with($request->url(), '/files/create_folder_v2')) {
            $path = $request['path'];
            expect($request['autorename'])->toBeFalse();
            if (collect($context->online)->contains('path_lower', mb_strtolower($path))) {
                return Http::response(['error_summary' => 'path/conflict/folder/',
                    'error' => ['.tag' => 'path', 'path' => ['.tag' => 'conflict', 'conflict' => ['.tag' => 'folder']]]], 409);
            }
            $folder = ['.tag' => 'folder', 'id' => 'id:created'.hash('sha256', $path), 'name' => basename($path),
                'path_lower' => mb_strtolower($path), 'path_display' => $path];
            $context->online[] = $folder;

            unset($folder['.tag']);
            if (($context->initialFolderFault ?? null) === 'missing-path' && $folder['name'] !== 'Leistungsfeststellungen') {
                unset($folder['path_lower']);
            }
            if (($context->initialFolderFault ?? null) === 'root-response' && $folder['name'] === 'Leistungsfeststellungen') {
                $folder['name'] = 'Falscher Antwortname';
            }

            return Http::response(['metadata' => $folder]);
        }
        if (($context->createExercise ?? false) && str_ends_with($request->url(), '/files/upload')) {
            $argument = json_decode($request->header('Dropbox-API-Arg')[0], true, flags: JSON_THROW_ON_ERROR);
            $folder = collect($context->online)->firstWhere('id', dirname($argument['path']));
            expect($argument['mode'])->toBe('add')->and(basename($argument['path']))->toBe('status.json');
            $fault = $context->initialUploadFault ?? null;
            unset($context->initialUploadFault);
            if ($fault === 'before-write') {
                return Http::response([], 503);
            }
            addEmailPreparationFile($context, $folder['path_display'].'/status.json', $request->body());
            if ($fault === 'after-write') {
                return Http::response([], 503);
            }

            $uploaded = end($context->online);
            unset($uploaded['.tag']);

            return Http::response($uploaded);
        }
        if (($context->resetExerciseStatus ?? false) && str_ends_with($request->url(), '/files/upload')) {
            $argument = json_decode($request->header('Dropbox-API-Arg')[0], true, flags: JSON_THROW_ON_ERROR);
            $folder = collect($context->online)->firstWhere('id', dirname($argument['path']));
            expect(basename($argument['path']))->toBe('status.json');
            $file = collect($context->online)->firstWhere('path_lower', $folder['path_lower'].'/status.json');
            expect($argument['mode'])->toBe(['.tag' => 'update', 'update' => $file['rev']]);
            $revision = hash('sha256', $request->body());
            $context->exerciseDocuments['rev:'.$revision] = $request->body();
            if ($context->failVerificationAfterReset ?? false) {
                $context->failDownloadPath = 'rev:'.$revision;
            }
            foreach ($context->online as &$entry) {
                if ($entry['id'] === $file['id']) {
                    $entry['rev'] = $revision;
                    $entry['size'] = strlen($request->body());

                    return Http::response($entry);
                }
            }
        }
        if (str_ends_with($request->url(), '/files/list_folder')) {
            $parentFolder = collect($context->online)->firstWhere('id', $request['path']);
            if (($context->shareAfterPreflightId ?? null) === $request['path'] && ($request['recursive'] ?? false)) {
                $context->recursivePreflights = ($context->recursivePreflights ?? 0) + 1;
            }
            if (($context->failedCandidateFolderId ?? null) === $request['path']) {
                return Http::response([], 503);
            }
            $entries = array_values(array_filter($context->online,
                fn (array $entry): bool => ($parentFolder['.tag'] ?? null) === 'folder'
                    ? (($request['recursive'] ?? false) ? str_starts_with($entry['path_lower'], $parentFolder['path_lower'].'/')
                        : dirname($entry['path_lower']) === $parentFolder['path_lower'])
                    : $request['path'] === 'id:folder'.basename(dirname($entry['path_lower']))));
            if (($request['recursive'] ?? false) && ($context->changeStatusOnRecheckId ?? null) === $request['path']) {
                $context->initialFolderInventoryCount = ($context->initialFolderInventoryCount ?? 0) + 1;
                if ($context->initialFolderInventoryCount === 2) {
                    $entries[0]['rev'] = 'changed-after-receipt';
                }
            }
            $listing = ['entries' => $entries, 'has_more' => false];
            if (($request['recursive'] ?? false) && ($context->recursiveRootFolderId ?? null) === $request['path']) {
                $rootEntry = $parentFolder;
                $rootEntry['property_groups'] = [];
                $entries = [$rootEntry, ...$entries, ...($context->recursiveExtraEntries ?? [])];
                $context->recursiveContinuedEntries = array_slice($entries, 32);
                $listing = ['entries' => array_slice($entries, 0, 32), 'has_more' => true, 'cursor' => 'recursive-exam-page-2'];
            }
            if (($context->pagedCandidateFolderId ?? null) === $request['path']) {
                $context->continuedCandidateEntries = array_slice($entries, 1);
                $listing = ['entries' => array_slice($entries, 0, 1), 'has_more' => true];
                if (! ($context->missingCandidateCursor ?? false)) {
                    $listing['cursor'] = 'candidate-page-2';
                }
            }
            if (in_array($request['path'], $context->deferredCandidateIds ?? [], true)) {
                $promise = new Promise(fn () => Utils::queue()->run());
                $context->candidatePending[] = [$promise, $listing];
                $context->candidateMaximum = max($context->candidateMaximum, count($context->candidatePending));
                if (! $context->candidateDrainScheduled) {
                    $context->candidateDrainScheduled = true;
                    Utils::queue()->add(function () use ($context): void {
                        $batch = $context->candidatePending;
                        $context->candidatePending = [];
                        $context->candidateDrainScheduled = false;
                        $context->candidateWaves[] = count($batch);
                        foreach (array_reverse($batch) as [$promise, $data]) {
                            $promise->resolve(new PsrResponse(200, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR)));
                        }
                    });
                }

                return $promise;
            }

            return Http::response($listing);
        }
        if (str_ends_with($request->url(), '/files/list_folder/continue')) {
            if ($request['cursor'] === 'recursive-exam-page-2') {
                return Http::response(['entries' => $context->recursiveContinuedEntries, 'has_more' => false]);
            }
            expect($request['cursor'])->toBe('candidate-page-2');

            return Http::response(['entries' => $context->continuedCandidateEntries, 'has_more' => false]);
        }

        throw new RuntimeException('Unexpected module Dropbox request: '.$request->url());
    });
}

test('dropping a handshake resolves an online path without linking or importing', function (): void {
    fakeModuleDropbox($this);
    $before = $this->work->fresh()->getAttributes();

    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])
        ->assertOk()->assertJsonPath('candidate.folder_path', '/schule2020_2027/Materialien/Lerninhalte/IT_Grundlagen/emails - Kopie')
        ->assertJsonPath('module_id', 'A263B873-4f27-4167-90f0-7d94ea0f67fa')->assertJsonPath('can_link', true);

    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('a direct folder handshake resolves without searching and rejects a mismatched identity', function (): void {
    $path = '/schule2020_2027/materialien/lerninhalte/netzwerke/handshake.json';
    $this->online[0]['path_lower'] = $path;
    $this->online[0]['path_display'] = $path;
    $this->handshake = json_encode(['schema_version' => 2, 'module_id' => 'A263B873-4f27-4167-90f0-7d94ea0f67fa',
        'dropbox_folder_id' => 'id:foldernetzwerke'], JSON_THROW_ON_ERROR);
    $this->remoteContents = $this->handshake;
    $this->online[0]['size'] = strlen($this->handshake);
    fakeModuleDropbox($this);
    $payload = ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true];

    $this->postJson($this->url.'/folder', $payload)->assertOk()
        ->assertJsonPath('candidate.folder_id', 'id:foldernetzwerke')->assertJsonPath('module_id', 'A263B873-4f27-4167-90f0-7d94ea0f67fa');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/files/search'));

    $payload['handshake'] = str_replace('A263B873', 'B263B873', $this->handshake);
    $this->postJson($this->url.'/folder', $payload)->assertConflict();
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('module lookup checks more than forty handshakes and still detects copied identities', function (): void {
    $this->searchPaginated = true;
    for ($index = 1; $index <= 45; $index++) {
        $contents = json_encode(['schema_version' => 1,
            'module_id' => sprintf('00000000-0000-4000-8000-%012d', $index)], JSON_THROW_ON_ERROR);
        $this->online[] = ['.tag' => 'file', 'id' => 'id:other-'.$index, 'name' => 'handshake.json',
            'rev' => 'other-revision-'.$index,
            'path_lower' => '/schule2020_2027/materialien/lerninhalte/other-'.$index.'/handshake.json',
            'path_display' => '/schule2020_2027/Materialien/Lerninhalte/other-'.$index.'/handshake.json',
            'size' => strlen($contents)];
        $this->exerciseDocuments['rev:other-revision-'.$index] = $contents;
    }
    fakeModuleDropbox($this);
    $payload = ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true];

    $this->postJson($this->url.'/folder', $payload)->assertOk()
        ->assertJsonPath('candidate.folder_path', '/schule2020_2027/Materialien/Lerninhalte/IT_Grundlagen/emails - Kopie');

    $copy = $this->online[0];
    $copy['id'] = 'id:copy';
    $copy['rev'] = 'copy-revision';
    $copy['path_lower'] = '/schule2020_2027/materialien/lerninhalte/copied/handshake.json';
    $copy['path_display'] = '/schule2020_2027/Materialien/Lerninhalte/copied/handshake.json';
    $this->online[] = $copy;

    $this->postJson($this->url.'/folder', $payload)->assertOk()
        ->assertJsonPath('conflict', true)->assertJsonCount(2, 'candidates');
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('module lookup rejects an incomplete search without linking', function (array $searchResponse): void {
    $this->searchResponse = $searchResponse;
    fakeModuleDropbox($this);

    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])
        ->assertUnprocessable();

    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
})->with([
    [['matches' => []]],
    [['matches' => [], 'has_more' => true]],
]);

test('module lookup reuses unchanged handshake revisions but still detects newly copied identities', function (): void {
    fakeModuleDropbox($this);
    $payload = ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true];
    $this->postJson($this->url.'/folder', $payload)->assertOk()->assertJsonPath('can_link', true);
    $downloads = Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/files/download'))->count();

    $this->postJson($this->url.'/folder', $payload)->assertOk()->assertJsonPath('can_link', true);
    expect(Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/files/download'))->count())->toBe($downloads);

    $copy = $this->online[0];
    $copy['id'] = 'id:copy';
    $copy['rev'] = 'revision-copy';
    $copy['path_lower'] = str_replace('emails - kopie', 'copied-module', $copy['path_lower']);
    $copy['path_display'] = str_replace('emails - Kopie', 'copied-module', $copy['path_display']);
    $this->online[] = $copy;
    $this->postJson($this->url.'/folder', $payload)->assertOk()->assertJsonPath('conflict', true)->assertJsonCount(2, 'candidates');
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('a new handshake revision never reuses the previous module identity', function (): void {
    fakeModuleDropbox($this);
    $payload = ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true];
    $this->postJson($this->url.'/folder', $payload)->assertOk();
    $this->online[0]['rev'] = 'revision2';
    $this->remoteContents = str_replace('A263B873', 'B263B873', $this->handshake);
    $this->postJson($this->url.'/folder', $payload)->assertUnprocessable();
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

function addExerciseFixture(object $context, string $name = '2026-10-06_E-Mail_Fach_3B_Gruppe-2', array $override = []): void
{
    $module = dirname($context->online[0]['path_display']);
    $root = $module.'/Leistungsfeststellung';
    $path = $root.'/'.$name;
    $record = array_replace_recursive(['Schooltool_Arbeit_ID' => $context->work->id, 'Schooltool_Kurs_ID' => $context->course->id,
        'Schooltool_Schule_ID' => $context->course->school_id, 'Durchfuehrungsdatum' => '2026-10-06', 'Leistungsfeststellungsordner' => $path,
        'Modulbindung' => ['status' => 'linked', 'module_id' => json_decode($context->handshake, true)['module_id'],
            'dropbox_folder_id' => 'id:folder'.basename(strtolower($module)), 'dropbox_path' => $module]], $override);
    $history = ['kind' => 'create-exercise.history', 'schema_version' => 1,
        'binding' => ['work_id' => $context->work->id, 'course_id' => $context->course->id, 'school_id' => $context->course->school_id, 'folder' => $path],
        'entries' => [['action' => 'exercise_folder_created', 'result' => 'created']]];
    foreach ([$root, $path, ...array_map(fn (string $child): string => $path.'/'.$child, ['Schülerlisten', 'Angaben', 'Abgaben', 'Auswertungen', 'Transaktionen'])] as $folder) {
        $id = 'id:folder'.hash('sha256', strtolower($folder));
        $context->online = array_values(array_filter($context->online, fn (array $entry): bool => $entry['id'] !== $id));
        $context->online[] = ['.tag' => 'folder', 'id' => $id, 'name' => basename($folder), 'path_lower' => strtolower($folder), 'path_display' => $folder];
    }
    foreach ([$path.'/Anlage_und_Quellen.json' => $record, $path.'/Transaktionen/History.json' => $history] as $file => $data) {
        $revision = hash('sha256', $file);
        $contents = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $context->exerciseDocuments['rev:'.$revision] = $contents;
        $context->online[] = ['.tag' => 'file', 'id' => 'id:'.$revision, 'name' => basename($file), 'path_lower' => strtolower($file),
            'path_display' => $file, 'rev' => $revision, 'size' => strlen($contents)];
    }
}

function linkExerciseFixture(object $context): void
{
    fakeModuleDropbox($context);
    $proof = test()->postJson($context->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $context->handshake, 'resolve' => true])->assertOk()->json('proof');
    test()->postJson($context->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $context->handshake, 'proof' => $proof])->assertOk();
    $context->work->update(['date_for_all_groups' => '2026-10-06']);
}

test('confirmed module linking initializes the exam folder, but resolving alone does not', function (): void {
    fakeModuleDropbox($this);
    $direct = Mockery::mock(TeachingDirectAssessment::class);
    app()->instance(TeachingDirectAssessment::class, $direct);
    $direct->shouldReceive('initializeLinkedFolder')->once()->withArgs(fn ($work, $actor): bool => $work->id === $this->work->id && $actor->id === $this->user->id)
        ->andReturn(['status' => 'initialized', 'folder' => ['path_display' => '/Modul/Leistungsfeststellungen/Prüfung']]);
    $proof = $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])
        ->assertOk()->json('proof');
    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])
        ->assertOk()->assertJsonPath('link_preparation.status', 'initialized')
        ->assertJsonPath('link_preparation.folder', '/Modul/Leistungsfeststellungen/Prüfung');
    $this->assertDatabaseHas('teaching_work_module_links', ['teaching_course_work_id' => $this->work->id]);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('new work linking creates only its exact target without a module lock or a Dropbox readback', function (int $version, int $siblings, string $scenario = 'normal'): void {
    $this->course->update(['title' => 'INF - 3B - 2', 'classes' => ['3B']]);
    $this->work->update(['type' => 'TE', 'title' => 'Word', 'date_for_all_groups' => '2026-10-06',
        'status' => ['creation_receipt' => ['id' => (string) Str::uuid(), 'user_id' => $this->user->id, 'hash' => str_repeat('a', 64)]]]);
    $student = User::factory()->create(['school_id' => $this->course->school_id, 'schoolyear_id' => $this->course->schoolyear_id,
        'first_name' => 'Anna', 'last_name' => 'Beispiel', 'schoolclass' => '3B', 'is_active' => true]);
    $this->course->teachingCourseStudents()->create(['user_id' => $student->id]);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $path = dirname($this->online[0]['path_display']);
    if ($version === 2) {
        $this->handshake = json_encode(['schema_version' => 2, 'module_id' => 'A263B873-4f27-4167-90f0-7d94ea0f67fa',
            'dropbox_folder_id' => 'id:module'], JSON_THROW_ON_ERROR);
        $this->remoteContents = $this->handshake;
        $this->online[0]['size'] = strlen($this->handshake);
    }
    array_unshift($this->online, ['.tag' => 'folder', 'id' => 'id:module', 'name' => basename($path),
        'path_lower' => mb_strtolower($path), 'path_display' => $path]);
    $root = $path.'/Leistungsfeststellungen';
    $target = $root.'/2026-10-06 – 3B_Gruppe-2 – Word';
    if ($siblings > 0 || $scenario === 'conflict') {
        $this->online[] = ['.tag' => 'folder', 'id' => 'id:examroot', 'name' => basename($root),
            'path_lower' => mb_strtolower($root), 'path_display' => $root];
    }
    for ($index = 0; $index < $siblings; $index++) {
        $sibling = $root.'/2026-10-06 – 3B_Gruppe-2 – Andere '.$index;
        $this->online[] = ['.tag' => 'folder', 'id' => 'id:sibling'.$index, 'name' => basename($sibling),
            'path_lower' => mb_strtolower($sibling), 'path_display' => $sibling];
        addEmailPreparationFile($this, $sibling.'/status.json', 'Dieser fremde Inhalt darf nicht gelesen werden.');
    }
    if ($scenario === 'conflict') {
        $this->online[] = ['.tag' => 'folder', 'id' => 'id:conflict', 'name' => basename($target),
            'path_lower' => mb_strtolower($target), 'path_display' => $target];
        addEmailPreparationFile($this, $target.'/status.json', 'Bestehende Datei.');
    }
    $this->createExercise = true;
    $this->initialFolderFault = $scenario;
    $this->initialUploadFault = $scenario;
    fakeModuleDropbox($this);
    $payload = ['filename' => 'handshake.json', 'handshake' => $this->handshake];
    $proof = $this->postJson($this->url.'/folder', $payload + ['resolve' => true])->assertOk()->json('proof');

    $offset = Http::recorded()->count();
    $lock = Cache::lock('teaching:direct-assessment:'.hash('sha256', 'id:module'), 60);
    expect($lock->get())->toBeTrue();
    try {
        $response = $this->postJson($this->url.'/folder', $payload + ['proof' => $proof,
            'creation_request_id' => $this->work->status['creation_receipt']['id']])->assertOk();
    } finally {
        $lock->release();
    }
    $calls = Http::recorded()->slice($offset);
    expect($calls->filter(fn (array $call): bool => str_contains($call[0]->url(), '/files/list_folder')
        || str_ends_with($call[0]->url(), '/files/download')
        || (str_ends_with($call[0]->url(), '/files/get_metadata') && str_contains($call[0]['path'], 'sibling'))))->toBeEmpty();
    if ($scenario === 'conflict') {
        $response->assertJsonPath('link_preparation.status', 'partial');
        expect($response->json('link_preparation.message'))->toContain('Konflikt');
        expect($calls->filter(fn (array $call): bool => str_ends_with($call[0]->url(), '/files/upload')))->toBeEmpty();

    } elseif (in_array($scenario, ['before-write', 'after-write', 'root-response'], true)) {
        $response->assertJsonPath('link_preparation.status', 'partial');
    } else {
        $response->assertJsonPath('link_preparation.status', 'initialized')->assertJsonPath('link_preparation.folder', $target);
        expect($calls)->toHaveCount(($siblings === 0 ? 11 : 9) + ($scenario === 'missing-path' ? 1 : 0));
        expect($calls->last()[0]->url())->toEndWith('/files/upload');
    }
    if (in_array($scenario, ['before-write', 'after-write', 'root-response', 'conflict'], true)) {
        $offset = Http::recorded()->count();
        $targetId = collect($this->online)->firstWhere('path_lower', mb_strtolower($target))['id'] ?? null;
        expect($lock->get())->toBeTrue();
        try {
            $retry = $this->postJson($this->url.'/folder', ['retry_preparation' => true])->assertOk();
        } finally {
            $lock->release();
        }
        $retryCalls = Http::recorded()->slice($offset);
        expect($retryCalls->filter(fn (array $call): bool => (str_ends_with($call[0]->url(), '/files/list_folder') && $call[0]['path'] !== $targetId)
            || (str_ends_with($call[0]->url(), '/files/get_metadata') && str_contains($call[0]['path'], 'sibling'))))->toBeEmpty();
        expect($retryCalls->filter(fn (array $call): bool => str_ends_with($call[0]->url(), '/files/create_folder_v2')))
            ->toHaveCount($scenario === 'root-response' ? 1 : 0);
        if ($scenario === 'conflict') {
            $retry->assertJsonPath('link_preparation.status', 'partial');
            expect($retryCalls->filter(fn (array $call): bool => str_ends_with($call[0]->url(), '/files/upload')))->toBeEmpty();
            $existing = collect($this->online)->firstWhere('path_lower', mb_strtolower($target.'/status.json'));
            expect($this->exerciseDocuments['rev:'.$existing['rev']])->toBe('Bestehende Datei.');

            return;
        }
        $retry->assertJsonPath('link_preparation.status', 'initialized')->assertJsonPath('link_preparation.folder', $target);
        if ($targetId !== null) {
            expect(collect($this->online)->firstWhere('path_lower', mb_strtolower($target))['id'])->toBe($targetId);
        }
        if ($scenario === 'after-write') {
            expect($retryCalls->filter(fn (array $call): bool => str_ends_with($call[0]->url(), '/files/upload')))->toBeEmpty();
        } else {
            expect($retryCalls->last()[0]->url())->toEndWith('/files/upload');
        }
    }
    $handshakeDownloads = Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/files/download')
        && json_decode($request->header('Dropbox-API-Arg')[0], true)['path'] === 'rev:revision1');
    expect($handshakeDownloads)->toHaveCount(1);
    $status = collect($this->online)->firstWhere('path_lower', mb_strtolower($target.'/status.json'));
    expect(json_decode($this->exerciseDocuments['rev:'.$status['rev']], true)['schooltool'])
        ->toBe(['work_id' => $this->work->id, 'course_id' => $this->course->id]);
})->with([
    'native response with a new parent' => [1, 0],
    'native response with existing siblings' => [2, 8],
    'missing path in the creation response' => [2, 8, 'missing-path'],
    'retry after creating only the parent' => [2, 0, 'root-response'],
    'retry after creating only the target' => [2, 8, 'before-write'],
    'retry after losing a successful upload response' => [2, 8, 'after-write'],
    'retry preserves conflicting existing data' => [2, 8, 'conflict'],
]);

test('a failed initial exam folder reports the saved link and a partial outcome', function (): void {
    fakeModuleDropbox($this);
    $direct = Mockery::mock(TeachingDirectAssessment::class);
    app()->instance(TeachingDirectAssessment::class, $direct);
    $direct->shouldReceive('initializeLinkedFolder')->once()->andThrow(ValidationException::withMessages(['date' => 'Durchführungstermin fehlt.']));
    $proof = $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()->json('proof');
    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])
        ->assertOk()->assertJsonPath('link_preparation.status', 'partial')
        ->assertJsonPath('link_preparation.message', 'Lernmodul verknüpft; der Prüfungsordner ist noch nicht bestätigt. Durchführungstermin fehlt.');
    $this->assertDatabaseHas('teaching_work_module_links', ['teaching_course_work_id' => $this->work->id]);
});

test('a canonical folder cannot be claimed when another work has the same target', function (bool $linked): void {
    linkExerciseFixture($this);
    $this->course->update(['title' => 'INF - 3B - 2', 'classes' => ['3B']]);
    $this->work->update(['title' => 'Word', 'date_for_all_groups' => '2026-10-06']);
    $link = TeachingWorkModuleLink::query()->where('teaching_course_work_id', $this->work->id)->firstOrFail();
    $other = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id, 'title' => 'Word', 'date_for_all_groups' => '2026-10-06']);
    if ($linked) {
        TeachingWorkModuleLink::query()->create(array_merge($link->only(['dropbox_connection_id', 'account_id', 'module_id', 'schema_version', 'folder_id',
            'folder_name', 'folder_path', 'handshake_file_id', 'handshake_revision']), ['teaching_course_work_id' => $other->id]));
    }
    try {
        app(TeachingAssessmentTargetGuard::class)->assertUnique($this->work, $link, '2026-10-06', '3B_Gruppe-2');
        test()->fail('An ambiguous target must be blocked.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
})->with([true, false]);

function addModernExerciseFixture(object $context, bool $withMc = false, string $date = '2026-10-06', bool $withAssignment = true): string
{
    $context->course->update(['title' => 'INF - 3B - 2', 'classes' => ['3B']]);
    $context->work->update(['title' => 'Word', 'groups' => [['date' => '2026-10-06', 'student_ids' => [123]]]]);
    $module = dirname($context->online[0]['path_display']);
    $path = $module.'/Leistungsfeststellungen/'.$date.' – 3B_Gruppe-2 – Word';
    foreach ([$module.'/Leistungsfeststellungen', $path, $path.'/Kandidaten', $path.'/Kandidaten/Person',
        $path.'/Kandidaten/Person/Angaben', $path.'/Kandidaten/Person/Abgaben', $path.'/Kandidaten/Person/Auswertung'] as $folder) {
        $context->online[] = ['.tag' => 'folder', 'id' => 'id:folder'.hash('sha256', $folder), 'name' => basename($folder),
            'path_lower' => mb_strtolower($folder), 'path_display' => $folder];
    }
    $documents = ['Beurteilung.md', 'Beurteilung.pdf'];
    if ($withAssignment) {
        $documents[] = 'Kandidaten/Person/Angaben/Aufgaben.pdf';
    }
    foreach ($documents as $file) {
        addEmailPreparationFile($context, $path.'/'.$file, '%PDF-1.4 test content');
    }
    if ($withMc) {
        addEmailPreparationFile($context, $path.'/Kandidaten/Person/Angaben/MC.pdf', 'test MC content');
    }
    foreach ($context->online as &$entry) {
        if (($entry['name'] ?? null) === 'Aufgaben.pdf') {
            $entry['content_hash'] = hash('sha256', 'test content identity');
        }
    }
    unset($entry);
    $record = ['schema_version' => 1, 'kind' => 'leistungsfeststellung.status',
        'schooltool' => ['work_id' => $context->work->id, 'course_id' => $context->course->id],
        'folder_group' => '3B_Gruppe-2', 'scheduled_date' => $date, 'prepared' => true,
        'components' => ['aufgabenblatt' => ['included' => $withAssignment, 'points' => $withAssignment ? 35 : null],
            'multiple_choice' => ['included' => $withMc, 'points' => $withMc ? 2 : null]],
        'total_points' => ($withAssignment ? 35 : 0) + ($withMc ? 2 : 0)];
    addEmailPreparationFile($context, $path.'/status.json', json_encode($record, JSON_THROW_ON_ERROR));

    return $path;
}

test('the verified assessment status accepts an explicitly MC-only package and preserves its total', function (): void {
    linkExerciseFixture($this);
    addModernExerciseFixture($this, withMc: true, withAssignment: false);
    $this->getJson($this->url.'?include_exercise=1&refresh=1&date=2026-10-06')->assertOk()
        ->assertJsonPath('exercise.preparation.status', 'ready')->assertJsonPath('exercise.preparation.components.aufgabenblatt.included', false)
        ->assertJsonPath('exercise.preparation.components.multiple_choice.included', true)->assertJsonPath('exercise.preparation.total_points', 2);
});

function addLinkedExerciseStatusFixture(object $context, string $path): void
{
    $record = ['schema_version' => 1, 'kind' => 'leistungsfeststellung.status',
        'schooltool' => ['work_id' => $context->work->id, 'course_id' => $context->course->id],
        'folder_group' => '3B_Gruppe-2', 'scheduled_date' => '2026-10-06', 'prepared' => false,
        'components' => ['aufgabenblatt' => ['included' => false, 'points' => null], 'multiple_choice' => ['included' => false, 'points' => null]],
        'total_points' => null];
    addEmailPreparationFile($context, $path.'/status.json', json_encode($record, JSON_THROW_ON_ERROR));
}

test('the workflow reads the stored folder check on a cold and a previously checked work without remote requests', function (): void {
    config(['cache.stores.file' => ['driver' => 'array']]);
    Cache::forgetDriver('file');
    linkExerciseFixture($this);
    addModernExerciseFixture($this);
    $url = $this->url.'?include_exercise=1&stored_only=1&date=2026-10-06';
    $before = Http::recorded()->count();
    $this->getJson($url.'&refresh=1&include_unlink_status=1&include_folder_path=1')
        ->assertOk()->assertJsonPath('exercise.status', 'unknown')->assertJsonPath('exercise.checked_at', null);
    expect(Http::recorded())->toHaveCount($before);

    $checked = $this->postJson($this->url.'/refresh', ['status_only' => true, 'date' => '2026-10-06',
        'finish_until_date' => null, 'finish_until_time' => null, 'expected' => ['date' => null, 'time' => null]])
        ->assertOk()->assertJsonPath('exercise.status', 'found')->assertJsonPath('progress.state', 'done')
        ->assertJsonPath('progress.completed', ['connection', 'folder', 'verification'])->json('exercise.checked_at');
    $requests = Http::recorded()->count();
    expect($requests)->toBeGreaterThan($before);
    $this->getJson($url)->assertOk()->assertJsonPath('exercise.checked_at', $checked)
        ->assertJsonPath('exercise.preparation.total_points', 35);
    expect(Http::recorded())->toHaveCount($requests);

    $modules = app(DropboxModuleLinks::class);
    $module = $modules->current($this->work);
    $key = 'teaching:exercise-check:v2:'.hash('sha256', serialize([$this->connection->id, $this->connection->account_id,
        $this->work->id, $this->course->id, $module->getAttributes()]));
    $legacy = Cache::store('database')->pull($key);
    expect($legacy['checked_at'])->toBe($checked);
    Cache::store('file')->forever($key, $legacy);
    $this->getJson($url)->assertOk()->assertJsonPath('exercise.checked_at', $checked)->assertJsonPath('exercise.status', 'found');
    expect(Cache::store('database')->get($key))->toEqual($legacy)
        ->and(Cache::store('file')->has($key))->toBeFalse()->and(Http::recorded())->toHaveCount($requests);
    $modules->forgetExerciseCache($this->work, $this->user);
    $this->getJson($url)->assertOk()->assertJsonPath('exercise.status', 'unknown')->assertJsonPath('exercise.checked_at', null);
});

test('a status check skips unavailable steps instead of confirming a missing association', function (): void {
    $this->postJson($this->url.'/refresh', ['status_only' => true, 'refresh_collection' => true,
        'finish_until_date' => null, 'finish_until_time' => null, 'expected' => ['date' => null, 'time' => null]])
        ->assertOk()->assertJsonPath('progress.state', 'done')
        ->assertJsonPath('progress.completed', ['connection'])->assertJsonPath('progress.skipped', ['folder', 'verification', 'collection']);
    Http::assertNothingSent();
});

test('the assessment is protected during real assignment dispatch and available again after a confirmed stop', function (): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this);
    $folder = 'id:folder'.hash('sha256', $path);
    $service = app(TeachingDirectAssessment::class);
    $check = fn () => $service->exerciseDeletionCandidate($this->work, $this->user, '2026-10-06', $folder);
    $batch = ['transport' => 'postmark', 'withdrawn_at' => null, 'recipients' => [['state' => 'queued']]];
    $save = fn (array $value) => $this->work->update(['status' => ['assignment_mail' => ['batches' => ['all' => $value]]]]);
    $save($batch);
    expect($check)->toThrow(HttpException::class, 'Bitte zuerst den Aufgabenversand');
    $batch['recipients'][0]['state'] = 'accepted';
    $save($batch);
    expect($check)->toThrow(HttpException::class, 'Bitte zuerst den Aufgabenversand');
    $save([...$batch, 'transport' => 'mailpit']);
    expect($check()['id'])->toBe($folder);
    $batch['withdrawn_at'] = now()->toIso8601String();
    $save($batch);
    expect($check()['id'])->toBe($folder);
    $batch['recipients'][0]['state'] = 'uncertain';
    $save($batch);
    expect($check)->toThrow(HttpException::class, 'Bitte zuerst die Versandbelege prüfen');
});

test('modern preparation status is confirmed read only for the matching work', function (bool $withMc): void {
    linkExerciseFixture($this);
    addModernExerciseFixture($this, $withMc);
    $before = $this->work->fresh()->getAttributes();
    $this->postJson($this->url.'/refresh', ['status_only' => true, 'date' => '2026-10-06',
        'finish_until_date' => '2026-10-20', 'finish_until_time' => null, 'expected' => ['date' => null, 'time' => null]])
        ->assertOk()->assertJsonPath('exercise.status', 'found')->assertJsonPath('exercise.preparation.status', 'ready')
        ->assertJsonPath('exercise.preparation.components.multiple_choice.included', $withMc)
        ->assertJsonPath('exercise.preparation.total_points', $withMc ? 37 : 35);
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    $candidateIds = collect($this->online)->filter(fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
        && str_contains($entry['path_lower'] ?? '', '/kandidaten'))->pluck('id')->all();
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/list_folder')
        && in_array($request['path'] ?? null, $candidateIds, true));
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
})->with([false, true]);

test('only an empty canonical or verifiably linked initial folder can be offered for preparation', function (string $case): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this);
    $folderId = 'id:folder'.hash('sha256', $path);
    $this->online = array_values(array_filter($this->online, fn (array $entry): bool => ! str_starts_with($entry['path_lower'] ?? '', mb_strtolower($path).'/')));
    if (in_array($case, ['linked', 'read-only'], true)) {
        addLinkedExerciseStatusFixture($this, $path);
    }
    if ($case === 'linked') {
        $this->connection->update(['credentials' => $this->connection->credentials + ['scopes' => ['files.content.write']]]);
    }
    if ($case === 'occupied') {
        addEmailPreparationFile($this, $path.'/fremd.txt', 'fremd');
    }
    if ($case === 'shared') {
        foreach ($this->online as &$entry) {
            if ($entry['id'] === $folderId) {
                $entry['sharing_info'] = ['shared_folder_id' => 'shared'];
            }
        }
        unset($entry);
    }
    $result = $this->getJson($this->url.'?include_exercise=1&refresh=1&date=2026-10-06')->assertOk();
    $result->assertJsonPath('exercise.status', in_array($case, ['empty', 'linked', 'read-only'], true) ? 'missing' : 'unknown');
    if (in_array($case, ['empty', 'linked', 'read-only'], true)) {
        $result->assertJsonPath('exercise.reusable', $case === 'empty' ? 'empty' : 'linked');
    }
    $canUnlink = in_array($case, ['empty', 'linked'], true);
    $version = $this->getJson($this->url.'?include_unlink_status=1')->assertOk()
        ->assertJsonPath('unlink_status.status', $canUnlink ? 'available' : ($case === 'read-only' ? 'blocked' : 'unknown'))->json('link_version');
    $this->postJson($this->url.'/folder', ['unlink' => true, 'link_version' => $version])->assertStatus($canUnlink ? 200 : 409);
    expect(collect($this->online)->contains('id', $folderId))->toBe($case !== 'linked')
        ->and($this->deletedIds ?? [])->toBe($case === 'linked' ? [$folderId] : []);
})->with(['empty', 'linked', 'read-only', 'occupied', 'shared']);

test('unlink retries a verified exam removal after a Dropbox failure and keeps unrelated folders', function (bool $responseLost): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this);
    $folderId = 'id:folder'.hash('sha256', $path);
    $this->online = array_values(array_filter($this->online, fn (array $entry): bool => ! str_starts_with($entry['path_lower'], mb_strtolower($path).'/')));
    addLinkedExerciseStatusFixture($this, $path);
    $foreignPath = dirname($path).'/anderer Ordner';
    $this->online[] = ['.tag' => 'folder', 'id' => 'id:foreign', 'name' => basename($foreignPath),
        'path_lower' => mb_strtolower($foreignPath), 'path_display' => $foreignPath];
    $this->connection->update(['credentials' => $this->connection->credentials + ['scopes' => ['files.content.write']]]);
    $link = TeachingWorkModuleLink::query()->where('teaching_course_work_id', $this->work->id)->firstOrFail();
    $legacy = TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $this->work->id, 'folder_id' => $folderId]);
    $this->failDeleteAt = $responseLost ? 0 : 1;
    $this->loseDeleteResponseAt = $responseLost ? 1 : 0;
    $request = ['unlink' => true, 'link_version' => $this->getJson($this->url)->assertOk()->json('link_version')];

    $this->postJson($this->url.'/folder', $request)->assertUnprocessable();
    expect($link->fresh())->not->toBeNull()->and($legacy->fresh())->not->toBeNull();

    $this->postJson($this->url.'/folder', $request)->assertOk()->assertJsonPath('module_link', null);
    expect($legacy->fresh())->toBeNull()
        ->and($this->deletedIds)->toBe([$folderId])
        ->and(collect($this->online)->pluck('id'))->toContain('id:handshake', 'id:foreign');
})->with([false, true]);

test('the new preparation exposes only the verified common PDF for read-only preview', function (): void {
    linkExerciseFixture($this);
    addModernExerciseFixture($this);
    $params = '?contents=1&exercise=1&verify_exercise=1&section=Angaben&date=2026-10-06';
    $response = $this->getJson($this->url.'/folders'.$params)->assertOk()
        ->assertJsonPath('shared_assignment', true)->assertJsonPath('root.name', 'Angaben')
        ->assertJsonPath('root.path', null)->assertJsonCount(1, 'entries')
        ->assertJsonPath('entries.0.name', 'Aufgaben.pdf');
    expect($response->getContent())->not->toContain('Person');
    $id = $response->json('entries.0.id');
    $this->get($this->url.'/folders'.$params.'&pdf=1&file_id='.$id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($this->get($this->url.'/folders'.$params.'&pdf=1&file_id='.$id)->getContent())->toStartWith('%PDF-');
    $attachment = $this->get($this->url.'/folders'.str_replace('verify_exercise=1', 'download_prepared=1', $params))->assertOk();
    expect($attachment->headers->get('Content-Disposition'))->toStartWith('attachment')->toContain('Aufgaben.pdf');
    expect($attachment->getContent())->toStartWith('%PDF-');
    $before = Http::recorded()->count();
    $work = $this->work->fresh();
    $work->finish_until_time = '18:00';
    $links = app(DropboxModuleLinks::class);
    $preparation = $links->cachedAssessmentPreparation($work, '2026-10-06');
    expect($preparation['total_points'] ?? null)->toBe(35);
    expect($links->cachedAssessmentPreparation($work, '2026-10-07'))->toBeNull();
    $work->title = 'Anderes Aufgabenblatt';
    expect($links->cachedAssessmentPreparation($work, '2026-10-06'))->toBeNull();
    expect(Http::recorded())->toHaveCount($before);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('personal MC PDFs are never selected as a shared assignment', function (): void {
    linkExerciseFixture($this);
    addModernExerciseFixture($this, true);
    $this->getJson($this->url.'/folders?contents=1&exercise=1&verify_exercise=1&section=Angaben&date=2026-10-06')
        ->assertStatus(409)->assertJsonPath('message', 'Das gemeinsame Aufgabenblatt konnte nicht eindeutig aus den persönlichen Angaben bestätigt werden. Bitte ein Aufgaben-PDF auswählen.');
});

test('prepared exercise links expose only the available general PDF documents', function (): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this, true);
    addEmailPreparationFile($this, $path.'/LF_Word.pdf', '%PDF-1.4 general assignment');
    $solutions = $path.'/Musterlösungen';
    $this->online[] = ['.tag' => 'folder', 'id' => 'id:solutions', 'name' => 'Musterlösungen',
        'path_lower' => mb_strtolower($solutions), 'path_display' => $solutions];
    addEmailPreparationFile($this, $solutions.'/Lösung.pdf', '%PDF-1.4 solution');

    $check = $this->getJson($this->url.'?include_exercise=1&date=2026-10-06')->assertOk();
    expect($check->json('exercise.preparation.document_sections'))->toEqualCanonicalizing(['Angaben', 'Bewertungskriterien', 'Musterlösungen']);
    foreach (['Angaben' => 'LF_Word.pdf', 'Bewertungskriterien' => 'Beurteilung.pdf', 'Musterlösungen' => 'Lösung.pdf'] as $section => $filename) {
        $url = $this->url.'/folders?contents=1&exercise=1&verify_exercise=1&date=2026-10-06&section='.rawurlencode($section);
        $id = $this->getJson($url)->assertOk()->assertJsonCount(1, 'entries')->assertJsonPath('entries.0.name', $filename)->json('entries.0.id');
        $this->get($url.'&pdf=1&file_id='.$id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('prepared PDF downloads reuse the verified documents without listing or checking the exercise again', function (): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this, true);
    $assignment = '%PDF-1.4 general assignment';
    addEmailPreparationFile($this, $path.'/LF_Word.pdf', $assignment);
    $this->getJson($this->url.'?include_exercise=1&date=2026-10-06')->assertOk()->assertJsonPath('exercise.preparation.status', 'ready');
    $before = Http::recorded()->count();
    $download = $this->url.'/folders?contents=1&exercise=1&download_prepared=1&date=2026-10-06&section=';

    $this->get($download.'Angaben')->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertContent($assignment);
    $criteria = $this->get($download.'Bewertungskriterien')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($criteria->headers->get('Content-Disposition'))->toStartWith('attachment')->toContain('Beurteilung.pdf');
    expect($criteria->getContent())->toStartWith('%PDF-');
    $calls = Http::recorded()->slice($before);
    expect($calls->filter(fn (array $call): bool => str_ends_with($call[0]->url(), '/files/download')))->toHaveCount(2);
    expect($calls->filter(fn (array $call): bool => str_contains($call[0]->url(), '/files/list_folder')))->toBeEmpty();

    $this->online = array_values(array_filter($this->online, fn (array $entry): bool => $entry['name'] !== 'LF_Word.pdf'));
    $this->getJson($download.'Angaben')->assertStatus(422);
});

test('modern preparation refuses missing conflicting incomplete or invalid status', function (string $case): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this);
    $file = collect($this->online)->firstWhere('name', 'status.json');
    $record = json_decode($this->exerciseDocuments['rev:'.$file['rev']], true, flags: JSON_THROW_ON_ERROR);
    match ($case) {
        'work' => $record['schooltool']['work_id']++,
        'course' => $record['schooltool']['course_id']++,
        'date' => $record['scheduled_date'] = '2026-10-07',
        'group' => $record['folder_group'] = '3B_Gruppe-3',
        'unfinished' => $record['prepared'] = false,
        'points' => $record['total_points'] = 100,
        default => null,
    };
    if ($case === 'missing') {
        $this->online = array_values(array_filter($this->online, fn (array $entry): bool => ($entry['name'] ?? null) !== 'status.json'));
    } else {
        $json = json_encode($record, JSON_THROW_ON_ERROR);
        addEmailPreparationFile($this, $path.'/status.json', $case === 'duplicate' ? str_replace('"prepared":true', '"prepared":true,"prepared":true', $json) : $json);
    }
    $response = $this->getJson($this->url.'?include_exercise=1&refresh=1&date=2026-10-06')
        ->assertOk()->assertJsonPath('exercise.status', $case === 'unfinished' ? 'missing' : 'unknown')->assertJsonMissingPath('exercise.preparation.status');
    if ($case === 'unfinished') {
        $response->assertJsonPath('exercise.reusable', 'partial');
    }
})->with(['missing', 'work', 'course', 'date', 'group', 'unfinished', 'points', 'duplicate']);

test('the status check trusts the preparation receipt without scanning personal documents', function (): void {
    linkExerciseFixture($this);
    addModernExerciseFixture($this);
    $this->online = array_values(array_filter($this->online, fn (array $entry): bool => ($entry['name'] ?? null) !== 'Beurteilung.pdf'));
    $this->getJson($this->url.'?include_exercise=1&refresh=1&date=2026-10-06')
        ->assertOk()->assertJsonPath('exercise.preparation.status', 'ready');
    $candidateIds = collect($this->online)->filter(fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
        && str_contains($entry['path_lower'] ?? '', '/kandidaten'))->pluck('id')->all();
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/list_folder')
        && in_array($request['path'] ?? null, $candidateIds, true));
});

test('modern MC dispatch check distinguishes no dispatch from an incomplete or unavailable dispatch', function (string $case): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this);
    if ($case === 'incomplete') {
        foreach ([$path.'/Versand', $path.'/Versand/Aufgaben', $path.'/Versand/Aufgaben/Versand_01'] as $folder) {
            $this->online[] = ['.tag' => 'folder', 'id' => 'id:folder'.hash('sha256', $folder), 'name' => basename($folder),
                'path_lower' => mb_strtolower($folder), 'path_display' => $folder];
        }
    }
    if ($case === 'case') {
        $folder = $path.'/VERSAND';
        $this->online[] = ['.tag' => 'folder', 'id' => 'id:folder'.hash('sha256', $folder), 'name' => basename($folder),
            'path_lower' => mb_strtolower($folder), 'path_display' => $folder];
    }
    if ($case === 'offline') {
        $this->exerciseOffline = true;
    }
    $service = app(DropboxModuleLinks::class);
    $work = $this->work->fresh();
    $result = $service->preparedExerciseDispatch($this->connection, $work, $service->current($work), '2026-10-06');
    expect($result['status'])->toBe($case === 'missing' ? 'missing' : 'unknown');
    $candidateIds = collect($this->online)->filter(fn (array $entry): bool => ($entry['.tag'] ?? null) === 'folder'
        && str_contains($entry['path_lower'] ?? '', '/kandidaten'))->pluck('id')->all();
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/list_folder')
        && in_array($request['path'] ?? null, $candidateIds, true));
})->with(['missing', 'incomplete', 'offline', 'case']);

test('a prepared exam cannot be renamed without Dropbox write permission', function (): void {
    linkExerciseFixture($this);
    addModernExerciseFixture($this);
    $before = $this->work->fresh()->getAttributes();
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['title' => 'Anderer Titel'])
        ->assertStatus(409)->assertJsonPath('message', 'Für die Umbenennung des Prüfungsordners fehlt die Dropbox-Schreibfreigabe.');
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    $this->getJson($this->url.'?include_exercise=1&refresh=1&date=2026-10-06')->assertOk()
        ->assertJsonPath('exercise.preparation.status', 'ready');
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('a confirmed modern exam folder is renamed with the work and remains discoverable', function (): void {
    linkExerciseFixture($this);
    $oldPath = addModernExerciseFixture($this);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $folderId = collect($this->online)->firstWhere('path_display', $oldPath)['id'];
    TeachingWorkDropboxFolder::query()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $this->work->id, 'folder_id' => $folderId, 'folder_name' => basename($oldPath)]);
    $this->partialMock(TeachingCourseWorkEntrySyncService::class)->shouldNotReceive('syncWork');

    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['title' => 'Word-lokal'])->assertOk()->assertJsonPath('data.title', 'Word-lokal');
    expect($this->work->fresh()->title)->toBe('Word-lokal')
        ->and(TeachingWorkDropboxFolder::query()->where('teaching_course_work_id', $this->work->id)->value('folder_name'))
        ->toBe('2026-10-06 – 3B_Gruppe-2 – Word-lokal');
    $this->getJson($this->url.'?include_exercise=1&refresh=1&date=2026-10-06')->assertOk()
        ->assertJsonPath('exercise.folder.id', $folderId)->assertJsonPath('exercise.preparation.status', 'ready');
    expect(Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/files/move_v2'))->count())->toBe(1);
});

test('a lost Dropbox move response is resolved using the original folder id', function (): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $id = collect($this->online)->firstWhere('path_display', $path)['id'];
    $this->moveLosesResponse = true;
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['title' => 'Word-lokal'])->assertOk();
    $this->getJson($this->url.'?include_exercise=1&refresh=1&date=2026-10-06')->assertOk()
        ->assertJsonPath('exercise.folder.id', $id)->assertJsonPath('exercise.preparation.status', 'ready');
});

test('folder rename accepts differing parent path case from Dropbox while checking the original id and final name', function (): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $id = collect($this->online)->firstWhere('path_display', $path)['id'];
    $this->varyParentCaseForId = $id;
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['title' => 'Word-local'])->assertOk();
    $this->getJson($this->url.'?include_exercise=1&refresh=1&date=2026-10-06')->assertOk()
        ->assertJsonPath('exercise.folder.id', $id)->assertJsonPath('exercise.preparation.status', 'ready');
});

test('a rejected Dropbox move reports the safe error code and keeps the title', function (): void {
    linkExerciseFixture($this);
    addModernExerciseFixture($this);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $this->moveFails = true;
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['title' => 'Word-local'])->assertStatus(409)
        ->assertJsonPath('message', 'Dropbox hat die Ordnerumbenennung abgelehnt (from_lookup/not_found). Einzelne Ordner können bereits umbenannt sein. Bitte denselben Titel erneut speichern und den Stand prüfen.');
    expect($this->work->fresh()->title)->toBe('Word');
});

test('a database failure after the folder move can be safely resumed with the same title', function (): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $id = collect($this->online)->firstWhere('path_display', $path)['id'];
    $this->partialMock(TeachingCourseWorkEntrySyncService::class)->shouldReceive('syncWork')->once()->andThrow(new RuntimeException('simulated failure'));
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['title' => 'Word-lokal', 'groups' => $this->work->groups])->assertStatus(409)
        ->assertJsonPath('message', 'Der Titel konnte nicht vollständig gespeichert werden. Ein Prüfungsordner wurde möglicherweise bereits umbenannt. Bitte denselben Titel erneut speichern und den Stand prüfen.');
    expect($this->work->fresh()->title)->toBe('Word');
    $this->app->forgetInstance(TeachingCourseWorkEntrySyncService::class);
    $this->getJson($this->url.'?include_exercise=1&date=2026-10-06')->assertOk()->assertJsonPath('exercise.status', 'unknown');
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['title' => 'Word-anders'])->assertStatus(409);
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['title' => 'Word-lokal'])->assertOk();
    expect(Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/files/move_v2'))->count())->toBe(1);
    $this->getJson($this->url.'?include_exercise=1&refresh=1&date=2026-10-06')->assertOk()->assertJsonPath('exercise.folder.id', $id);
});

test('a conflicting target is not overwritten or renamed automatically', function (): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $this->online[] = ['.tag' => 'folder', 'id' => 'id:collision', 'name' => '2026-10-06 – 3B_Gruppe-2 – Word-lokal',
        'path_display' => $path.'-lokal', 'path_lower' => mb_strtolower($path.'-lokal')];
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['title' => 'Word-lokal'])->assertStatus(409);
    expect($this->work->fresh()->title)->toBe('Word');
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/move_v2'));
});

test('changing a saved exam date together with its title stops before moving any folder', function (): void {
    linkExerciseFixture($this);
    addModernExerciseFixture($this);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id,
        ['title' => 'Word-lokal', 'date_for_all_groups' => '2026-10-09', 'groups' => []])->assertStatus(409)
        ->assertJsonPath('message', 'Bitte Durchführungstermine und Titel getrennt ändern, damit die vorhandenen Prüfungsordner zugeordnet bleiben.');
    expect($this->work->fresh()->title)->toBe('Word');
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/move_v2'));
});

test('a case-only title change is stopped before touching the Dropbox folder', function (): void {
    linkExerciseFixture($this);
    addModernExerciseFixture($this);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['title' => 'WORD'])->assertStatus(409)
        ->assertJsonPath('message', 'Eine Änderung nur der Groß- und Kleinschreibung wird von Dropbox nicht unterstützt.');
    expect($this->work->fresh()->title)->toBe('Word');
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/move_v2'));
});

test('each dated modern exam folder is renamed and remains discoverable', function (): void {
    linkExerciseFixture($this);
    addModernExerciseFixture($this);
    addModernExerciseFixture($this, date: '2026-10-07');
    $students = User::factory()->count(2)->create(['school_id' => $this->course->school_id, 'schoolyear_id' => $this->course->schoolyear_id]);
    foreach ($students as $student) {
        $this->course->teachingCourseStudents()->create(['user_id' => $student->id]);
    }
    $this->work->update(['groups' => [['date' => '2026-10-06', 'student_ids' => [$students[0]->id]], ['date' => '2026-10-07', 'student_ids' => [$students[1]->id]]]]);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['title' => 'Word-lokal'])->assertOk();
    foreach (['2026-10-06', '2026-10-07'] as $date) {
        $this->getJson($this->url.'?include_exercise=1&refresh=1&date='.$date)->assertOk()
            ->assertJsonPath('exercise.folder.name', $date.' – 3B_Gruppe-2 – Word-lokal')->assertJsonPath('exercise.preparation.status', 'ready');
    }
    expect(Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/files/move_v2'))->count())->toBe(2);
});

test('a partially completed multi-date rename resumes without another move of the completed folder', function (): void {
    linkExerciseFixture($this);
    addModernExerciseFixture($this);
    addModernExerciseFixture($this, date: '2026-10-07');
    $students = User::factory()->count(2)->create(['school_id' => $this->course->school_id, 'schoolyear_id' => $this->course->schoolyear_id]);
    foreach ($students as $student) {
        $this->course->teachingCourseStudents()->create(['user_id' => $student->id]);
    }
    $this->work->update(['groups' => [['date' => '2026-10-06', 'student_ids' => [$students[0]->id]], ['date' => '2026-10-07', 'student_ids' => [$students[1]->id]]]]);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $this->failMoveAt = 2;
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['title' => 'Word-lokal'])->assertStatus(409)
        ->assertJsonPath('message', 'Dropbox hat die Ordnerumbenennung abgelehnt (from_lookup/not_found). Einzelne Ordner können bereits umbenannt sein. Bitte denselben Titel erneut speichern und den Stand prüfen.');
    expect($this->work->fresh()->title)->toBe('Word');
    $this->getJson($this->url.'?include_exercise=1&date=2026-10-06')->assertOk()->assertJsonPath('exercise.status', 'unknown');
    $this->failMoveAt = 0;
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['title' => 'Word-lokal'])->assertOk();
    foreach (['2026-10-06', '2026-10-07'] as $date) {
        $this->getJson($this->url.'?include_exercise=1&refresh=1&date='.$date)->assertOk()
            ->assertJsonPath('exercise.folder.name', $date.' – 3B_Gruppe-2 – Word-lokal');
    }
    expect($this->moveCount)->toBe(3);
});

test('a title remains editable when the linked module has no exam folder', function (): void {
    linkExerciseFixture($this);
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['title' => 'Neuer Titel'])->assertOk()
        ->assertJsonPath('data.title', 'Neuer Titel');
    expect($this->work->fresh()->title)->toBe('Neuer Titel');
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('description changes do not inspect or alter the exam folder', function (): void {
    linkExerciseFixture($this);
    addModernExerciseFixture($this);
    $requests = Http::recorded()->count();
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['description' => 'Ergänzte Beschreibung'])->assertOk();
    expect($this->work->fresh()->description)->toBe('Ergänzte Beschreibung')
        ->and(Http::recorded()->count())->toBe($requests);
});

test('a title change fails closed when Dropbox cannot be checked', function (): void {
    linkExerciseFixture($this);
    addModernExerciseFixture($this);
    $before = $this->work->fresh()->getAttributes();
    $this->exerciseOffline = true;
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['title' => 'Anderer Titel'])
        ->assertStatus(409);
    expect($this->work->fresh()->getAttributes())->toEqual($before);
});

test('a title change is blocked when an older possible exam has incomplete identifiers', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this, override: ['Schooltool_Arbeit_ID' => null]);
    $before = $this->work->fresh()->getAttributes();
    $this->putJson('/api/admin/teaching/course_works/'.$this->work->id, ['title' => 'Anderer Titel'])
        ->assertStatus(409)->assertJsonPath('message', 'Der Titel kann nicht geändert werden: Ein älterer Prüfungsordner ist nicht eindeutig zugeordnet.');
    expect($this->work->fresh()->getAttributes())->toEqual($before);
});

test('a failed modern status refresh does not reuse a prior prepared result', function (): void {
    linkExerciseFixture($this);
    addModernExerciseFixture($this);
    $url = $this->url.'?include_exercise=1&refresh=1&date=2026-10-06';
    $this->getJson($url)->assertOk()->assertJsonPath('exercise.preparation.status', 'ready');
    $this->exerciseOffline = true;
    $this->getJson($url)->assertOk()->assertJsonPath('exercise.status', 'unknown')->assertJsonMissingPath('exercise.preparation.status');
});

test('the actual externally created exercise folder is read only and confirmed for this work and module', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $before = $this->work->fresh()->getAttributes();
    $this->getJson($this->url.'?include_exercise=1&date=2026-10-06')->assertOk()
        ->assertJsonPath('exercise.status', 'found')->assertJsonPath('exercise.assignment_status', 'missing')
        ->assertJsonPath('exercise.folder.name', '2026-10-06_E-Mail_Fach_3B_Gruppe-2');
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

function addEmailPreparationFile(object $context, string $path, string $contents): void
{
    $revision = hash('sha256', $path.$contents);
    $context->exerciseDocuments['rev:'.$revision] = $contents;
    $context->online = array_values(array_filter($context->online, fn (array $entry): bool => $entry['path_lower'] !== strtolower($path)));
    $context->online[] = ['.tag' => 'file', 'id' => 'id:'.$revision, 'name' => basename($path), 'path_lower' => strtolower($path),
        'path_display' => $path, 'rev' => $revision, 'size' => strlen($contents)];
}

test('the assignment is ready only with a matching preparation proof and PDF', function (bool $pdfMatches): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $metadata = collect($this->online)->firstWhere('name', 'Anlage_und_Quellen.json');
    $path = dirname($metadata['path_display']);
    $pdf = '%PDF-1.4 test assignment';
    $package = TeachingWorkDispatchFixture::documentPreparation($this->work->id, $this->course->id);
    $package['exercise']['scheduled_date'] = '2026-10-06';
    $package['assignment']['sha256'] = hash('sha256', $pdf);
    unset($package['preparation_checksum']);
    $package['preparation_checksum'] = SchooltoolAssessmentJson::digest($package);
    addEmailPreparationFile($this, $path.'/Schooltool-Vorbereitung.json', json_encode($package, JSON_THROW_ON_ERROR));
    addEmailPreparationFile($this, $path.'/Angaben/'.$package['assignment']['filename'], $pdfMatches ? $pdf : '%PDF-changed');

    $this->getJson($this->url.'?include_exercise=1&date=2026-10-06')->assertOk()
        ->assertJsonPath('exercise.status', 'found')->assertJsonPath('exercise.assignment_status', $pdfMatches ? 'ready' : 'unknown');
})->with([true, false]);

function addEmailPreparationFixture(object $context): string
{
    $context->course->update(['title' => 'INF 1']);
    $student = User::factory()->create(['school_id' => $context->course->school_id, 'schoolyear_id' => $context->course->schoolyear_id,
        'first_name' => 'Ada', 'last_name' => 'Van Alpha', 'schoolclass' => '1A', 'email' => 'ada@example.test']);
    $context->course->teachingCourseStudents()->create(['user_id' => $student->id]);
    $context->work->update(['groups' => [['student_ids' => [$student->id]]], 'finish_until_date' => null, 'finish_until_time' => null]);
    $metadataFile = collect($context->online)->firstWhere('name', 'Anlage_und_Quellen.json');
    $root = dirname($metadataFile['path_display']);
    $roster = json_encode([['id' => $student->id, 'email' => $student->email]], JSON_THROW_ON_ERROR);
    addEmailPreparationFile($context, $root.'/Schülerlisten/Teilnehmer.json', $roster);
    addEmailPreparationFile($context, $root.'/nachricht_entwurf.md', 'Persönlicher E-Mail-Entwurf');
    addEmailPreparationFile($context, $root.'/Angaben/Aufgaben.pdf', '%PDF-Aufgaben');
    $package = json_decode(TeachingWorkDispatchFixture::readyTasksText(), true, flags: JSON_THROW_ON_ERROR);
    $package['Leistungsfeststellung'] = $root;
    $package['Textquelle_SHA256'] = hash('sha256', 'Persönlicher E-Mail-Entwurf');
    $package['Empfaenger'][0]['VorbereiteteAnhaenge'][0]['SHA256'] = hash('sha256', '%PDF-Aufgaben');
    $package['Quellen'] = [['Datei' => 'Anlage_und_Quellen.json', 'SHA256' => hash('sha256', $context->exerciseDocuments['rev:'.$metadataFile['rev']])],
        ['Datei' => 'Schülerlisten/Teilnehmer.json', 'SHA256' => hash('sha256', $roster)]];
    addEmailPreparationFile($context, $root.'/Schooltool-Versandvorbereitung.json', json_encode($package, JSON_THROW_ON_ERROR));

    return $root;
}

test('a cold deadline refresh reads email readiness only in the final full check', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    addEmailPreparationFixture($this);
    $package = collect($this->online)->firstWhere('name', 'Schooltool-Versandvorbereitung.json');
    $this->partialMock(TeachingWorkDeadlineSync::class)
        ->shouldReceive('sync')->once()->andReturn(['changed' => false, 'message' => 'Frist abgeglichen.']);

    $this->postJson($this->url.'/refresh', ['date' => '2026-10-06', 'finish_until_date' => null, 'finish_until_time' => null,
        'expected' => ['date' => null, 'time' => null]])->assertOk()
        ->assertJsonPath('exercise.status', 'found')->assertJsonPath('exercise.email_preparation.status', 'ready');

    $reads = Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/files/download')
        && json_decode($request->header('Dropbox-API-Arg')[0], true)['path'] === 'rev:'.$package['rev']);
    expect($reads)->toHaveCount(1);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('folder-only discovery preserves group ambiguity and validates other matching groups', function (): void {
    linkExerciseFixture($this);
    $this->work->update(['groups' => [['date' => '2026-10-07']]]);
    addExerciseFixture($this);
    addExerciseFixture($this, '2026-10-07_Andere-Gruppe', ['Durchfuehrungsdatum' => '2026-10-07']);
    $service = app(DropboxModuleLinks::class);
    $work = $this->work->fresh();
    $link = $service->current($work);

    expect($service->exerciseFolder($this->connection, $work, $link, null)['status'])->toBe('unknown')
        ->and($service->exerciseFolder($this->connection, $work, $link, '2026-10-07')['folder']['name'])->toBe('2026-10-07_Andere-Gruppe')
        ->and($service->exercise($this->connection, $work, $link, '2026-10-07', initial: false)['status'])->toBe('unknown');

    $this->online = array_values(array_filter($this->online, fn (array $entry): bool => ! (($entry['name'] ?? null) === 'History.json'
        && str_contains($entry['path_display'], '2026-10-06_E-Mail_Fach_3B_Gruppe-2'))));
    expect($service->exerciseFolder($this->connection, $work, $link, '2026-10-07')['status'])->toBe('unknown');
});

/** @return array{root: string, payload: array<string, mixed>, package: array<string, mixed>} */
function addPreparedEmailPreviewFixture(object $context): array
{
    $root = addEmailPreparationFixture($context);
    $entry = collect($context->online)->firstWhere('name', 'Schooltool-Versandvorbereitung.json');
    $package = json_decode($context->exerciseDocuments['rev:'.$entry['rev']], true, flags: JSON_THROW_ON_ERROR);
    $recipient = $package['Empfaenger'][0];
    $payload = ['From' => $recipient['From'], 'To' => $recipient['To'], 'Subject' => $recipient['Betreff'],
        'TextBody' => 'Hallo Ada, deine Aufgabe ist bereit.',
        'HtmlBody' => '<div><p>Hallo Ada, deine Aufgabe ist bereit.</p><a href="https://example.test/private-token">Test öffnen</a></div>',
        'SourceTextSHA256' => $package['Textquelle_SHA256'],
        'SourceTextPath' => 'C:\\historisch\\nachricht_entwurf.md',
        'Attachments' => [['Name' => 'Aufgaben.pdf', 'Path' => 'C:\\historisch\\Aufgaben.pdf']]];
    foreach ([$root.'/E-Mail-Vorbereitung', $root.'/E-Mail-Vorbereitung/Nachrichtenvorschauen'] as $folder) {
        $context->online[] = ['.tag' => 'folder', 'id' => 'id:'.hash('sha256', $folder), 'name' => basename($folder),
            'path_lower' => strtolower($folder), 'path_display' => $folder];
    }
    setPreparedEmailPreviewFixture($context, $root, $package, $payload);

    return ['root' => $root, 'payload' => $payload, 'package' => $package];
}

/** @param array<string, mixed> $package
 * @param  array<string, mixed>  $payload
 */
function setPreparedEmailPreviewFixture(object $context, string $root, array $package, array $payload, bool $updateHash = true): void
{
    $raw = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if ($updateHash) {
        $package['Empfaenger'][0]['Nachricht_SHA256'] = hash('sha256', $raw);
    }
    addEmailPreparationFile($context, $root.'/Schooltool-Versandvorbereitung.json', json_encode($package, JSON_THROW_ON_ERROR));
    addEmailPreparationFile($context, $root.'/E-Mail-Vorbereitung/Nachrichtenvorschauen/01.json', $raw);
}

test('email preparation preview returns the confirmed personal message without sending or modifying it', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $fixture = addPreparedEmailPreviewFixture($this);
    $before = $this->work->fresh()->getAttributes();
    $response = $this->getJson('/api/admin/teaching/course_works/'.$this->work->id.'/email-preparation-preview?date=2026-10-06');
    $response->assertOk()->assertJsonPath('data.work_id', $this->work->id)->assertJsonPath('data.course_id', $this->course->id)
        ->assertJsonPath('data.from', $fixture['payload']['From'])->assertJsonPath('data.to', 'ada@example.test')
        ->assertJsonPath('data.subject', $fixture['payload']['Subject'])->assertJsonPath('data.html', $fixture['payload']['HtmlBody'])
        ->assertJsonPath('data.attachments.0', 'Aufgaben.pdf');
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    $draft = collect($this->online)->firstWhere('name', 'nachricht_entwurf.md');
    expect(Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/files/download')
        && json_decode($request->header('Dropbox-API-Arg')[0], true)['path'] === 'rev:'.$draft['rev']))->toHaveCount(0);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('email preparation preview is independent of the preparation record while loading', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $fixture = addPreparedEmailPreviewFixture($this);
    $preview = collect($this->online)->firstWhere('name', '01.json');
    $packageFile = collect($this->online)->firstWhere('name', 'Schooltool-Versandvorbereitung.json');
    $package = json_decode($this->exerciseDocuments['rev:'.$packageFile['rev']], true, flags: JSON_THROW_ON_ERROR);
    $this->preparedPreviewRevision = 'rev:'.$preview['rev'];
    $this->onPreparedPreviewDownload = function () use ($fixture, $package): void {
        $package['Pruefhinweis'] = 'nachträglich geändert';
        addEmailPreparationFile($this, $fixture['root'].'/Schooltool-Versandvorbereitung.json', json_encode($package, JSON_THROW_ON_ERROR));
    };

    $this->getJson('/api/admin/teaching/course_works/'.$this->work->id.'/email-preparation-preview?date=2026-10-06')
        ->assertOk()->assertJsonPath('data.html', $fixture['payload']['HtmlBody']);
});

test('email preparation preview does not read an invalid preparation record', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $fixture = addPreparedEmailPreviewFixture($this);
    addEmailPreparationFile($this, $fixture['root'].'/Schooltool-Versandvorbereitung.json', '{invalid');

    $this->getJson('/api/admin/teaching/course_works/'.$this->work->id.'/email-preparation-preview?date=2026-10-06')
        ->assertOk()->assertJsonPath('data.html', $fixture['payload']['HtmlBody']);
});

test('email preparation preview displays a personal payload without checking readiness', function (string $case): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $fixture = addPreparedEmailPreviewFixture($this);
    $payload = $fixture['payload'];
    if ($case === 'recipient') {
        $payload['To'] = 'another@example.test';
    } elseif ($case === 'subject') {
        $payload['Subject'] = 'Geändert';
    } elseif ($case === 'source') {
        $payload['SourceTextSHA256'] = str_repeat('e', 64);
    } elseif ($case === 'attachment') {
        $payload['Attachments'][0]['Name'] = 'Andere.pdf';
    } else {
        $payload['HtmlBody'] = '<p>Verändert</p>';
    }
    setPreparedEmailPreviewFixture($this, $fixture['root'], $fixture['package'], $payload, updateHash: $case !== 'hash');
    $this->getJson('/api/admin/teaching/course_works/'.$this->work->id.'/email-preparation-preview?date=2026-10-06')
        ->assertOk()->assertJsonPath('data.to', $payload['To'])->assertJsonPath('data.subject', $payload['Subject'])
        ->assertJsonPath('data.html', $payload['HtmlBody']);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
})->with(['recipient', 'subject', 'source', 'attachment', 'hash']);

test('email preparation preview reads only one of multiple personal message files', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $fixture = addPreparedEmailPreviewFixture($this);
    $raw = json_encode($fixture['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    addEmailPreparationFile($this, $fixture['root'].'/E-Mail-Vorbereitung/Nachrichtenvorschauen/02.json', $raw);

    $this->getJson('/api/admin/teaching/course_works/'.$this->work->id.'/email-preparation-preview?date=2026-10-06')
        ->assertOk()->assertJsonPath('data.html', $fixture['payload']['HtmlBody']);
    $second = collect($this->online)->firstWhere('name', '02.json');
    expect(Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/files/download')
        && json_decode($request->header('Dropbox-API-Arg')[0], true)['path'] === 'rev:'.$second['rev']))->toHaveCount(0);
});

test('email preparation preview reuses a confirmed folder while rechecking its work identity', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $fixture = addPreparedEmailPreviewFixture($this);
    $this->getJson($this->url.'?include_exercise=1&date=2026-10-06')->assertOk()->assertJsonPath('exercise.status', 'found');
    $before = Http::recorded()->count();

    $this->getJson('/api/admin/teaching/course_works/'.$this->work->id.'/email-preparation-preview?date=2026-10-06')
        ->assertOk()->assertJsonPath('data.html', $fixture['payload']['HtmlBody']);
    $requests = Http::recorded()->slice($before);
    expect($requests->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/files/download')))->toHaveCount(2);
    $root = $fixture['root'];
    $recordFile = collect($this->online)->firstWhere('name', 'Anlage_und_Quellen.json');
    $record = json_decode($this->exerciseDocuments['rev:'.$recordFile['rev']], true, flags: JSON_THROW_ON_ERROR);
    $record['Schooltool_Arbeit_ID'] = $this->work->id + 1;
    addEmailPreparationFile($this, $root.'/Anlage_und_Quellen.json', json_encode($record, JSON_THROW_ON_ERROR));
    $this->getJson('/api/admin/teaching/course_works/'.$this->work->id.'/email-preparation-preview?date=2026-10-06')->assertStatus(409);
});

test('email preparation preview rejects a selected message changed during loading', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    addPreparedEmailPreviewFixture($this);
    $preview = collect($this->online)->firstWhere('name', '01.json');
    $this->preparedPreviewRevision = 'rev:'.$preview['rev'];
    $this->onPreparedPreviewDownload = function (): void {
        $this->changedPreparationFile = '01.json';
    };

    $this->getJson('/api/admin/teaching/course_works/'.$this->work->id.'/email-preparation-preview?date=2026-10-06')->assertStatus(409);
});

test('email preparation preview rejects an unrelated teacher before accessing Dropbox', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    addPreparedEmailPreviewFixture($this);
    $other = User::factory()->create(['school_id' => $this->course->school_id, 'schoolyear_id' => $this->course->schoolyear_id])->assignRole('teacher');
    $before = Http::recorded()->count();
    $this->actingAs($other, 'sanctum')->getJson('/api/admin/teaching/course_works/'.$this->work->id.'/email-preparation-preview?date=2026-10-06')->assertForbidden();
    expect(Http::recorded()->count())->toBe($before);
});

test('exercise checks keep creation confirmed when an independent dispatch journal is invalid', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $root = addEmailPreparationFixture($this);
    foreach (['Versand', 'Versand/Aufgaben', 'Versand/Aufgaben/Versand_2026-10-24_00-00-00'] as $relative) {
        $path = $root.'/'.$relative;
        $this->online[] = ['.tag' => 'folder', 'id' => 'id:'.hash('sha256', $path), 'name' => basename($path),
            'path_lower' => strtolower($path), 'path_display' => $path];
    }
    addEmailPreparationFile($this, $root.'/Versand/Aufgaben/Versand_2026-10-24_00-00-00/Versandprotokoll.txt', 'Unvollständiger Versandbeleg');
    $before = $this->work->fresh()->getAttributes();
    $this->getJson($this->url.'?include_exercise=1')->assertOk()
        ->assertJsonPath('exercise.status', 'found')->assertJsonPath('exercise.email_preparation.status', 'ready')
        ->assertJsonPath('exercise.email_dispatch.status', 'unknown');
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('exercise checks include complete email preparation without sending or importing', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $root = addEmailPreparationFixture($this);
    $before = $this->work->fresh()->getAttributes();

    $response = $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'found');
    $this->assertSame('ready', $response->json('exercise.email_preparation.status'), $response->json('exercise.email_preparation.message') ?? '');
    $response->assertJsonPath('exercise.email_preparation.recipients_count', 1);
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);

    addEmailPreparationFile($this, $root.'/nachricht_entwurf.md', 'Geänderter E-Mail-Entwurf');
    $this->getJson($this->url.'?include_exercise=1&refresh=1')->assertOk()->assertJsonPath('exercise.status', 'found')
        ->assertJsonPath('exercise.email_preparation.status', 'unknown');
});

test('email preparation is missing until its separate readiness evidence exists', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'found')
        ->assertJsonPath('exercise.email_preparation.status', 'missing');
});

test('email preparation matches the actual date group or all active members without saved groups', function (bool $datedGroup): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    addEmailPreparationFixture($this);
    if ($datedGroup) {
        $student = User::factory()->create(['school_id' => $this->course->school_id, 'schoolclass' => '1A']);
        $this->course->teachingCourseStudents()->create(['user_id' => $student->id]);
        $groups = $this->work->fresh()->groups;
        $groups[] = ['date' => '2026-10-07', 'student_ids' => [$student->id]];
        $this->work->update(['groups' => $groups]);
    } else {
        $this->work->update(['groups' => []]);
    }
    $this->getJson($this->url.'?include_exercise=1&date=2026-10-06')->assertOk()
        ->assertJsonPath('exercise.email_preparation.status', 'ready');
})->with([false, true]);

test('changed or incomplete email preparation never invalidates the created exercise or becomes ready', function (string $changed): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $root = addEmailPreparationFixture($this);
    if ($changed === 'roster') {
        addEmailPreparationFile($this, $root.'/Schülerlisten/Teilnehmer.json', '[]');
    } elseif ($changed === 'attachment') {
        addEmailPreparationFile($this, $root.'/Angaben/Aufgaben.pdf', '%PDF-Geändert');
    } elseif ($changed === 'deadline') {
        $this->work->update(['finish_until_date' => '2026-10-08', 'finish_until_time' => '12:00']);
    } elseif ($changed === 'recipient') {
        $student = User::factory()->create(['school_id' => $this->course->school_id, 'schoolclass' => '1A']);
        $this->course->teachingCourseStudents()->create(['user_id' => $student->id]);
        $this->work->update(['groups' => [['student_ids' => [$student->id]]]]);
    } else {
        addEmailPreparationFile($this, $root.'/Schooltool-Versandvorbereitung.json', '{invalid');
    }
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'found')
        ->assertJsonPath('exercise.email_preparation.status', 'unknown');
})->with(['roster', 'attachment', 'deadline', 'recipient', 'malformed']);

test('preparation rejects ambiguous files and changes during source or attachment checks', function (string $failure): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    addEmailPreparationFixture($this);
    if ($failure === 'source revision') {
        $this->changedPreparationFile = 'Teilnehmer.json';
    } elseif ($failure === 'attachment revision') {
        $this->changedPreparationFile = 'Aufgaben.pdf';
    } else {
        $name = $failure === 'duplicate source' ? 'Teilnehmer.json' : 'Aufgaben.pdf';
        $duplicate = collect($this->online)->firstWhere('name', $name);
        $duplicate['id'] .= '-duplicate';
        $this->online[] = $duplicate;
    }

    $this->getJson($this->url.'?include_exercise=1&refresh=1')->assertOk()->assertJsonPath('exercise.status', 'found')
        ->assertJsonPath('exercise.email_preparation.status', 'unknown');
})->with(['source revision', 'attachment revision', 'duplicate source', 'duplicate attachment']);

test('preparation enforces the attachment size limit', function (bool $tooLarge): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $root = addEmailPreparationFixture($this);
    $contents = str_repeat('A', 8 * 1024 * 1024 + (int) $tooLarge);
    addEmailPreparationFile($this, $root.'/Angaben/Aufgaben.pdf', $contents);
    $packageFile = collect($this->online)->firstWhere('name', 'Schooltool-Versandvorbereitung.json');
    $package = json_decode($this->exerciseDocuments['rev:'.$packageFile['rev']], true, flags: JSON_THROW_ON_ERROR);
    $package['Empfaenger'][0]['VorbereiteteAnhaenge'][0]['SHA256'] = hash('sha256', $contents);
    addEmailPreparationFile($this, $root.'/Schooltool-Versandvorbereitung.json', json_encode($package, JSON_THROW_ON_ERROR));

    $this->getJson($this->url.'?include_exercise=1&refresh=1')->assertOk()->assertJsonPath('exercise.status', 'found')
        ->assertJsonPath('exercise.email_preparation.status', $tooLarge ? 'unknown' : 'ready');
})->with([false, true]);

function addContentsRootFixture(object $context): void
{
    $link = TeachingWorkModuleLink::query()->where('teaching_course_work_id', $context->work->id)->firstOrFail();
    $context->online[] = ['.tag' => 'folder', 'id' => $link->folder_id, 'name' => $link->folder_name,
        'path_lower' => strtolower($link->folder_path), 'path_display' => $link->folder_path];
}

test('linked folder contents list files and folders without importing or editing', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this);
    addExerciseFixture($this, '2026-10-06_Andere-Pruefung', ['Schooltool_Arbeit_ID' => $this->work->id + 1]);
    $before = $this->work->fresh()->getAttributes();

    $this->getJson($this->url.'?include_exercise=1')->assertOk();
    $this->getJson($this->url.'/folders?contents=1')->assertOk()
        ->assertJsonPath('root.name', '2026-10-06_E-Mail_Fach_3B_Gruppe-2')
        ->assertJsonFragment(['name' => 'Anlage_und_Quellen.json', 'type' => 'file'])
        ->assertJsonFragment(['name' => 'Angaben', 'type' => 'folder'])
        ->assertJsonMissing(['name' => '2026-10-06_Andere-Pruefung'])
        ->assertJsonPath('cursor', null);

    expect($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('linked folder contents hide other exercises before this exercise exists and reject direct navigation', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this, override: ['Schooltool_Arbeit_ID' => $this->work->id + 1]);
    $this->getJson($this->url.'?include_exercise=1')->assertOk();
    $this->getJson($this->url.'/folders?contents=1')->assertOk()
        ->assertJsonPath('root.name', 'emails - kopie')
        ->assertJsonFragment(['name' => 'handshake.json', 'type' => 'file'])
        ->assertJsonMissing(['name' => 'Leistungsfeststellung']);
    $root = collect($this->online)->firstWhere('name', 'Leistungsfeststellung');
    $other = collect($this->online)->firstWhere('name', '2026-10-06_E-Mail_Fach_3B_Gruppe-2');
    foreach ([$root, $other] as $folder) {
        $this->getJson($this->url.'/folders?contents=1&folder_id='.$folder['id'])->assertForbidden();
    }
});

test('linked module icon opens the learning module even after its exercise exists', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this);
    $before = $this->work->fresh()->getAttributes();
    $this->getJson($this->url.'?include_exercise=1')->assertOk();
    $this->getJson($this->url.'/folders?contents=1&module=1')->assertOk()
        ->assertJsonPath('root.name', 'emails - kopie')->assertJsonFragment(['name' => 'handshake.json', 'type' => 'file'])
        ->assertJsonMissing(['name' => 'Leistungsfeststellung']);
    $this->getJson($this->url.'/folders?contents=1&module=1&exercise=1')->assertUnprocessable();
    expect($this->work->fresh()->getAttributes())->toEqual($before);
});

test('linked folder contents select the requested group date and stop on an uncertain exercise', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    $this->work->update(['groups' => [['date' => '2026-10-07']]]);
    addExerciseFixture($this);
    addExerciseFixture($this, '2026-10-07_Andere-Gruppe', ['Durchfuehrungsdatum' => '2026-10-07']);
    $this->getJson($this->url.'?include_exercise=1')->assertOk();
    $this->getJson($this->url.'/folders?contents=1&date=2026-10-07')->assertOk()
        ->assertJsonPath('root.name', '2026-10-07_Andere-Gruppe');
    $this->getJson($this->url.'/folders?contents=1')->assertStatus(409);
});

test('assignment sections can explicitly verify their folder without a current cached exercise check', function (string $cacheState): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    if ($cacheState === 'missing') {
        $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'missing');
    }
    addExerciseFixture($this);
    if ($cacheState === 'absent') {
        Cache::flush();
    }
    if ($cacheState === 'stale') {
        $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'found');
        $this->work->update(['title' => 'Geänderter Titel']);
    }
    $before = $this->work->fresh()->getAttributes();

    $this->getJson($this->url.'/folders?contents=1&exercise=1&verify_exercise=1&section=Angaben&date=2026-10-06')->assertOk()
        ->assertJsonPath('root.name', 'Angaben')->assertJsonPath('entries', []);

    expect($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
})->with(['absent', 'missing', 'stale']);

test('assignment sections return 409 for newly ambiguous exercise folders despite a cached successful check', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this);
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'found');
    addExerciseFixture($this, '2026-10-06_Doppelte-Pruefung');

    $this->getJson($this->url.'/folders?contents=1&exercise=1&verify_exercise=1&section=Angaben&date=2026-10-06')->assertStatus(409)
        ->assertJsonPath('message', 'Mehrere Leistungsfeststellungsordner passen. Bitte den konkreten Termin auswählen.');
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('linked folder contents open only the requested important section and reject sibling navigation', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this);
    $this->getJson($this->url.'?include_exercise=1')->assertOk();
    $this->getJson($this->url.'/folders?contents=1&exercise=1&section=Angaben&date=2026-10-06')->assertOk()
        ->assertJsonPath('root.name', 'Angaben')->assertJsonPath('entries', []);
    $sibling = collect($this->online)->firstWhere('name', 'Abgaben');
    $this->getJson($this->url.'/folders?contents=1&exercise=1&section=Angaben&folder_id='.$sibling['id'])->assertForbidden();
    $this->getJson($this->url.'/folders?contents=1&exercise=1&section=Bewertungskriterien')->assertNotFound();
    $this->getJson($this->url.'/folders?contents=1&section=Angaben')->assertUnprocessable();
    $this->getJson($this->url.'/folders?contents=1&exercise=1&section=Transaktionen')->assertUnprocessable();
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('linked folder contents return only a PDF from its verified section as private inline content', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this);
    $section = collect($this->online)->firstWhere('name', 'Angaben');
    $pdf = "%PDF-1.7\n%%EOF\n";
    $this->exerciseDocuments['rev:pdfrevision1'] = $pdf;
    $this->online[] = ['.tag' => 'file', 'id' => 'id:taskpdf', 'name' => 'Aufgabe.pdf', 'rev' => 'pdfrevision1',
        'path_lower' => $section['path_lower'].'/aufgabe.pdf', 'path_display' => $section['path_display'].'/Aufgabe.pdf', 'size' => strlen($pdf)];
    $url = $this->url.'/folders?contents=1&exercise=1&verify_exercise=1&section=Angaben&pdf=1&file_id=id:taskpdf';

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Content-Disposition', 'inline')
        ->assertContent($pdf);
    $this->getJson(str_replace('id:taskpdf', 'id:handshake', $url))->assertNotFound();
    $this->getJson(str_replace('exercise=1', 'exercise=0', $url))->assertUnprocessable();
    $otherTeacher = User::factory()->create(['school_id' => $this->user->school_id, 'schoolyear_id' => $this->user->schoolyear_id])->assignRole('teacher');
    $this->actingAs($otherTeacher, 'sanctum')->getJson($url)->assertForbidden();
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('linked folder contents open the verified exercise and refuse unrelated child folders', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this);
    $this->getJson($this->url.'?include_exercise=1')->assertOk();
    $this->getJson($this->url.'/folders?contents=1&exercise=1&date=2026-10-06')->assertOk()
        ->assertJsonPath('root.name', '2026-10-06_E-Mail_Fach_3B_Gruppe-2')
        ->assertJsonFragment(['name' => 'Anlage_und_Quellen.json', 'type' => 'file']);

    $this->online[] = ['.tag' => 'folder', 'id' => 'id:outside', 'name' => 'Private',
        'path_lower' => '/private', 'path_display' => '/Private'];
    $this->getJson($this->url.'/folders?contents=1&folder_id=id:outside')->assertForbidden();
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/list_folder') && $request['path'] === 'id:outside');
});

test('linked folder contents require a saved connection and support a legacy work folder', function (): void {
    fakeModuleDropbox($this);
    $this->getJson($this->url.'/folders?contents=1')->assertStatus(409);
    $this->online[] = ['.tag' => 'folder', 'id' => 'id:legacy', 'name' => 'Altordner',
        'path_lower' => '/altordner', 'path_display' => '/Altordner'];
    TeachingWorkDropboxFolder::query()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $this->work->id, 'folder_id' => 'id:legacy', 'folder_name' => 'Altordner']);

    $this->getJson($this->url.'/folders?contents=1')->assertOk()->assertJsonPath('root.id', 'id:legacy');
});

test('legacy folder icons distinguish the learning module from the concrete group exercise without changing results', function (): void {
    fakeModuleDropbox($this);
    $this->online = [
        ['.tag' => 'folder', 'id' => 'id:module', 'name' => 'E-Mails', 'path_lower' => '/materialien/emails', 'path_display' => '/Materialien/E-Mails'],
        ['.tag' => 'folder', 'id' => 'id:collection', 'name' => 'Leistungsfeststellung', 'path_lower' => '/materialien/emails/leistungsfeststellung',
            'path_display' => '/Materialien/E-Mails/Leistungsfeststellung'],
        ['.tag' => 'folder', 'id' => 'id:legacy', 'name' => '2026-10-04_E-Mail_DGB-3B-1_Tag',
            'path_lower' => '/materialien/emails/leistungsfeststellung/2026-10-04_e-mail_dgb-3b-1_tag',
            'path_display' => '/Materialien/E-Mails/Leistungsfeststellung/2026-10-04_E-Mail_DGB-3B-1_Tag'],
        ['.tag' => 'folder', 'id' => 'id:submissions', 'name' => 'Abgaben',
            'path_lower' => '/materialien/emails/leistungsfeststellung/2026-10-04_e-mail_dgb-3b-1_tag/abgaben',
            'path_display' => '/Materialien/E-Mails/Leistungsfeststellung/2026-10-04_E-Mail_DGB-3B-1_Tag/Abgaben'],
    ];
    TeachingWorkDropboxFolder::query()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $this->work->id, 'folder_id' => 'id:legacy', 'folder_name' => $this->online[2]['name']]);
    $before = $this->work->fresh()->getAttributes();
    $this->getJson($this->url.'?include_folder_path=1')->assertOk()->assertJsonPath('folder.id', 'id:legacy')
        ->assertJsonPath('legacy_folders.module.id', 'id:module')->assertJsonPath('legacy_folders.exercise.id', 'id:legacy');
    $this->getJson($this->url.'/folders?contents=1&module=1')->assertOk()->assertJsonPath('root.id', 'id:module')
        ->assertJsonMissing(['name' => 'Leistungsfeststellung']);
    $this->getJson($this->url.'/folders?contents=1&exercise=1')->assertOk()->assertJsonPath('root.id', 'id:legacy')
        ->assertJsonFragment(['id' => 'id:submissions', 'name' => 'Abgaben']);
    $this->getJson($this->url.'/folders?contents=1&exercise=1&folder_id=id:module')->assertForbidden();
    $this->getJson($this->url.'/folders?contents=1&module=1&folder_id=id:legacy')->assertForbidden();
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('linked folder contents never initiate a full LF check themselves', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this);
    $requests = Http::recorded()->count();

    $this->getJson($this->url.'/folders?contents=1&exercise=1&section=Angaben')->assertConflict();
    expect(Http::recorded()->count())->toBe($requests);
});

test('linked folder contents reuse one stored LF check across dates and days', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    $this->work->update(['groups' => [['date' => '2026-10-07']]]);
    addExerciseFixture($this);
    addExerciseFixture($this, '2026-10-07_Andere-Gruppe', ['Durchfuehrungsdatum' => '2026-10-07']);
    $checkedAt = $this->getJson($this->url.'?include_exercise=1&date=2026-10-06')->assertOk()
        ->assertJsonPath('exercise.status', 'found')->json('exercise.checked_at');
    $requests = Http::recorded()->count();

    $this->travel(3)->days();
    $this->getJson($this->url.'?include_exercise=1&date=2026-10-07')->assertOk()
        ->assertJsonPath('exercise.folder.name', '2026-10-07_Andere-Gruppe')->assertJsonPath('exercise.checked_at', $checkedAt);
    expect(Http::recorded()->count())->toBe($requests);
    $downloads = Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/files/download'))->count();
    $this->getJson($this->url.'/folders?contents=1&exercise=1&section=Angaben&date=2026-10-06')->assertOk();
    expect(Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/files/download'))->count())->toBe($downloads);
});

test('linked folder contents refresh the LF check only on explicit request and retain failures', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this);
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'found');
    $requests = Http::recorded()->count();
    $this->exerciseOffline = true;

    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'found');
    expect(Http::recorded()->count())->toBe($requests);
    $this->getJson($this->url.'?include_exercise=1&refresh=1')->assertOk()->assertJsonPath('exercise.status', 'unknown');
    $afterRefresh = Http::recorded()->count();
    expect($afterRefresh)->toBeGreaterThan($requests);
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'unknown');
    expect(Http::recorded()->count())->toBe($afterRefresh);
});

test('linked folder contents require manual confirmation after changing the schedule', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this);
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'found');
    $requests = Http::recorded()->count();
    $this->work->update(['date_for_all_groups' => '2026-10-08']);

    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'unknown');
    expect(Http::recorded()->count())->toBe($requests);
    $this->getJson($this->url.'?include_exercise=1&refresh=1')->assertOk()->assertJsonPath('exercise.status', 'missing');
});

test('an unrelated exercise never hides creation for this work', function (array $override): void {
    linkExerciseFixture($this);
    addExerciseFixture($this, override: $override);
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'missing')->assertJsonPath('exercise.folder', null);
})->with([
    'different work' => [['Schooltool_Arbeit_ID' => 99999]],
    'different course' => [['Schooltool_Kurs_ID' => 99999]],
    'different school' => [['Schooltool_Schule_ID' => 99999]],
    'different date' => [['Durchfuehrungsdatum' => '2026-10-04']],
    'different module' => [['Modulbindung' => ['module_id' => '50f45671-a0a0-4bb4-b0b0-123456789abc']]],
    'different root' => [['Modulbindung' => ['dropbox_folder_id' => 'id:originalemails']]],
]);

test('copying an export or an empty LF root is not proof of exercise creation', function (): void {
    linkExerciseFixture($this);
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'missing');
});

/** @return array{path:string,source:array<string,mixed>,roster:array<string,mixed>,metadata:array<string,mixed>,handshake:array<string,mixed>} */
function addLegacyPartOneFixture(object $context, string $name = '2026-10-06_Alte_LF'): array
{
    addExerciseFixture($context, $name, ['Modulbindung' => null]);
    $module = dirname($context->online[0]['path_display']);
    $path = $module.'/Leistungsfeststellung/'.$name;
    $sourcePath = $path.'/Schülerlisten/Quelle_Schooltool_Arbeitsdaten.json';
    $rosterPath = $path.'/Schülerlisten/Schuelerliste.json';
    $local = fn (string $path): string => 'C:\\Dropbox'.str_replace('/', '\\', $path);
    $source = ['school' => ['id' => $context->course->school_id], 'course' => ['id' => $context->course->id],
        'work' => ['id' => $context->work->id, 'scheduled_date' => '2026-10-06'],
        'module_binding' => ['status' => 'linked', 'schema_version' => 1, 'module_id' => json_decode($context->handshake, true)['module_id'],
            'dropbox_folder_id' => 'id:folder'.basename(strtolower($module)), 'dropbox_path' => $module]];
    $sourceBytes = json_encode($source, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $roster = ['complete' => true, 'scope' => 'active_saved_work_groups', 'participants' => [],
        'selection' => ['work_id' => $context->work->id, 'course_id' => $context->course->id, 'source_sha256' => hash('sha256', $sourceBytes)]];
    $rosterBytes = json_encode($roster, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $metadata = ['Status' => 'Teil 1 angelegt', 'Schooltool_Arbeit_ID' => $context->work->id,
        'Schooltool_Kurs_ID' => $context->course->id, 'Schooltool_Schule_ID' => $context->course->school_id,
        'Durchfuehrungsdatum' => '2026-10-06', 'Lerninhaltquelle' => $local($module),
        'Leistungsfeststellungsordner' => $local($path),
        'Dateien' => [['Rolle' => 'Unveränderte Teilnehmerquelle', 'Kopie' => $local($sourcePath), 'SHA256' => hash('sha256', $sourceBytes)]],
        'Teilnehmerliste' => $local($rosterPath), 'Teilnehmerliste_SHA256' => hash('sha256', $rosterBytes)];
    $handshake = ['schema_version' => 1, 'kind' => 'schooltool.exercise_folder', 'exercise_folder_id' => 'a263b873-4f27-4167-90f0-7d94ea0f67fa'];
    $context->online = array_values(array_filter($context->online, fn (array $entry): bool => $entry['path_lower'] !== strtolower($path.'/Transaktionen/History.json')));
    foreach ([$path.'/Anlage_und_Quellen.json' => $metadata, $path.'/handshake.json' => $handshake,
        $sourcePath => $source, $rosterPath => $roster] as $file => $contents) {
        addEmailPreparationFile($context, $file, json_encode($contents, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    return compact('path', 'source', 'roster', 'metadata', 'handshake');
}

test('a proven older part-one exercise is recognized without writing or inventing completed assignment evidence', function (): void {
    linkExerciseFixture($this);
    $fixture = addLegacyPartOneFixture($this);
    $before = $this->work->fresh()->getAttributes();
    $this->getJson($this->url.'?include_exercise=1&refresh=1&date=2026-10-06')->assertOk()
        ->assertJsonPath('exercise.status', 'found')->assertJsonPath('exercise.assignment_status', 'missing')
        ->assertJsonPath('exercise.folder.name', basename($fixture['path']))
        ->assertJsonPath('exercise.email_preparation.status', 'missing');
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('an older part-one exercise with altered or contradictory proof stays unconfirmed', function (string $scenario): void {
    linkExerciseFixture($this);
    $fixture = addLegacyPartOneFixture($this);
    $path = $fixture['path'];
    $source = $fixture['source'];
    $roster = $fixture['roster'];
    $metadata = $fixture['metadata'];
    $handshake = $fixture['handshake'];
    if ($scenario === 'source bytes') {
        $source['work']['id'] = 999;
    } elseif ($scenario === 'roster bytes') {
        $roster['complete'] = false;
    } elseif ($scenario === 'module identity') {
        $source['module_binding']['module_id'] = 'b263b873-4f27-4167-90f0-7d94ea0f67fa';
        $sourceBytes = json_encode($source, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $metadata['Dateien'][0]['SHA256'] = $roster['selection']['source_sha256'] = hash('sha256', $sourceBytes);
        $metadata['Teilnehmerliste_SHA256'] = hash('sha256', json_encode($roster, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    } elseif ($scenario === 'source work identity') {
        $source['work']['id'] = 999;
        $sourceBytes = json_encode($source, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $metadata['Dateien'][0]['SHA256'] = $roster['selection']['source_sha256'] = hash('sha256', $sourceBytes);
        $metadata['Teilnehmerliste_SHA256'] = hash('sha256', json_encode($roster, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    } elseif ($scenario === 'roster binding') {
        $roster['selection']['work_id'] = 999;
        $metadata['Teilnehmerliste_SHA256'] = hash('sha256', json_encode($roster, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    } elseif ($scenario === 'folder handshake') {
        $handshake['kind'] = 'wrong';
    } elseif ($scenario === 'folder path') {
        $metadata['Leistungsfeststellungsordner'] = 'C:\\Dropbox\\anderen-ordner';
    } elseif ($scenario === 'module path') {
        $metadata['Lerninhaltquelle'] = 'C:\\Dropbox\\anderes-modul';
    } elseif ($scenario === 'copy path') {
        $metadata['Dateien'][0]['Kopie'] = 'C:\\Dropbox\\fremde-quelle.json';
    } elseif ($scenario === 'other documents') {
        addEmailPreparationFile($this, $path.'/Angaben/fremd.pdf', '%PDF-1.4');
    } elseif ($scenario === 'other preparation') {
        addEmailPreparationFile($this, $path.'/Schooltool-Vorbereitung.json', '{}');
    } elseif ($scenario === 'wrong status') {
        $metadata['Status'] = 'Teil 2 angelegt';
    } elseif ($scenario === 'malformed binding') {
        $metadata['Modulbindung'] = 'invalid';
    }
    foreach ([$path.'/Anlage_und_Quellen.json' => $metadata, $path.'/handshake.json' => $handshake,
        $path.'/Schülerlisten/Quelle_Schooltool_Arbeitsdaten.json' => $source, $path.'/Schülerlisten/Schuelerliste.json' => $roster] as $file => $contents) {
        addEmailPreparationFile($this, $file, json_encode($contents, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
    $this->getJson($this->url.'?include_exercise=1&refresh=1&date=2026-10-06')->assertOk()
        ->assertJsonPath('exercise.status', 'unknown')->assertJsonPath('exercise.folder', null);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
})->with(['source bytes', 'roster bytes', 'module identity', 'source work identity', 'roster binding', 'folder handshake', 'folder path',
    'module path', 'copy path', 'other documents', 'other preparation', 'wrong status', 'malformed binding']);

test('a matching exercise with incomplete module binding is unconfirmed instead of inviting duplicate creation', function (mixed $binding, bool $alsoConfirmed): void {
    linkExerciseFixture($this);
    addExerciseFixture($this, '2026-10-06_unbestaetigt', ['Modulbindung' => $binding]);
    if ($alsoConfirmed) {
        addExerciseFixture($this, '2026-10-06_bestaetigt');
    }
    $before = $this->work->fresh()->getAttributes();
    $this->getJson($this->url.'?include_exercise=1&refresh=1&date=2026-10-06')->assertOk()
        ->assertJsonPath('exercise.status', 'unknown')->assertJsonPath('exercise.folder', null)
        ->assertJsonPath('exercise.message', 'Anlagezustand nicht bestätigt. Ein passender LF-Ordner wurde gefunden, aber seine Modulzuordnung ist unvollständig. Bitte den bestehenden Ordner prüfen.');
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
})->with([
    'missing' => [null, false],
    'missing module id' => [['module_id' => null], false],
    'unconfirmed status' => [['status' => 'pending'], false],
    'another confirmed folder' => [null, true],
]);

test('ambiguous or incomplete matching exercise packages stay unconfirmed', function (bool $duplicate): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    if ($duplicate) {
        addExerciseFixture($this, '2026-10-06_E-Mail_Duplicate');
    } else {
        $this->online = array_values(array_filter($this->online, fn (array $entry): bool => $entry['name'] !== 'History.json'));
    }
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'unknown')->assertJsonPath('exercise.folder', null);
})->with([true, false]);

test('exercise lookup without an owned module or without course access makes no Dropbox calls', function (): void {
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'missing');
    $teacher = User::factory()->create(['school_id' => $this->user->school_id, 'schoolyear_id' => $this->user->schoolyear_id])->assignRole('teacher');
    $this->actingAs($teacher, 'sanctum')->getJson($this->url.'?include_exercise=1')->assertForbidden();
    Http::assertNothingSent();
});

test('failed Dropbox reads or changed LF evidence never confirm creation', function (bool $offline): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    if ($offline) {
        $this->exerciseOffline = true;
    } else {
        $this->changedExerciseRevision = true;
    }
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'unknown')->assertJsonPath('exercise.folder', null);
})->with([true, false]);

test('candidate folders are checked concurrently and unrelated works do not hide the matching work', function (): void {
    $factory = new class extends HttpFactory
    {
        public function fake($callback = null): static
        {
            $this->record();
            $this->stubCallbacks = collect([$callback]);

            return $this;
        }
    };
    Http::swap($factory);
    Http::preventStrayRequests();
    linkExerciseFixture($this);
    addExerciseFixture($this);
    foreach (range(1, 7) as $index) {
        addExerciseFixture($this, '2026-10-06_Other_'.$index, ['Schooltool_Arbeit_ID' => 90000 + $index]);
    }
    $root = strtolower(dirname($this->online[0]['path_display']).'/Leistungsfeststellung');
    $this->deferredCandidateIds = collect($this->online)->filter(fn (array $entry): bool => $entry['.tag'] === 'folder' && dirname($entry['path_lower']) === $root)->pluck('id')->all();
    $this->candidatePending = [];
    $this->candidateWaves = [];
    $this->candidateMaximum = 0;
    $this->candidateDrainScheduled = false;
    $before = $this->work->fresh()->getAttributes();

    $this->getJson($this->url.'?include_exercise=1&refresh=1')->assertOk()->assertJsonPath('exercise.status', 'found');

    expect($this->deferredCandidateIds)->toHaveCount(8)
        ->and($this->candidateMaximum)->toBeGreaterThan(1)->toBeLessThanOrEqual(4)
        ->and($this->candidateWaves)->toBe([4, 4])
        ->and($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('candidate pagination checks metadata on subsequent pages', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $this->pagedCandidateFolderId = collect($this->online)->firstWhere('name', '2026-10-06_E-Mail_Fach_3B_Gruppe-2')['id'];

    $this->getJson($this->url.'?include_exercise=1&refresh=1')->assertOk()->assertJsonPath('exercise.status', 'found');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/list_folder/continue') && $request['cursor'] === 'candidate-page-2');
});

test('candidate errors do not confirm a partially checked exercise', function (string $failure): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $candidate = collect($this->online)->firstWhere('name', '2026-10-06_E-Mail_Fach_3B_Gruppe-2');
    if ($failure === 'remote') {
        $this->failedCandidateFolderId = $candidate['id'];
    } elseif ($failure === 'pagination') {
        $this->pagedCandidateFolderId = $candidate['id'];
        $this->missingCandidateCursor = true;
    } elseif ($failure === 'path') {
        $this->changedExercisePath = true;
    } else {
        foreach ($this->online as &$entry) {
            if ($entry['name'] === 'Anlage_und_Quellen.json') {
                $this->exerciseDocuments['rev:'.$entry['rev']] = '{';
                $entry['size'] = 1;
            }
        }
        unset($entry);
    }

    $this->getJson($this->url.'?include_exercise=1&refresh=1')->assertOk()->assertJsonPath('exercise.status', 'unknown')->assertJsonPath('exercise.folder', null);
})->with(['remote', 'pagination', 'path', 'invalid JSON']);

test('copied assessment metadata keeps the assignment when the assessment is unclear', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this, override: ['Leistungsfeststellungsordner' => '/old/another-folder']);
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'unknown');
    $version = $this->getJson($this->url)->assertOk()->json('link_version');
    $this->postJson($this->url.'/folder', ['unlink' => true, 'link_version' => $version])->assertConflict();
    expect(TeachingWorkModuleLink::query()->where('teaching_course_work_id', $this->work->id)->exists())->toBeTrue();
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('explicit linking keeps legacy imports and allows the same module for distinct works', function (): void {
    fakeModuleDropbox($this);
    $legacy = TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $this->connection->id, 'teaching_course_work_id' => $this->work->id]);
    $before = $this->work->fresh()->getAttributes();
    $legacyBefore = $legacy->fresh()->getAttributes();
    $resolved = $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()->json();

    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $resolved['proof']])
        ->assertOk()->assertJsonPath('module_link.folder.path', '/schule2020_2027/Materialien/Lerninhalte/IT_Grundlagen/emails - Kopie');

    $link = TeachingWorkModuleLink::query()->firstOrFail();
    expect($link->module_id)->toBe('A263B873-4f27-4167-90f0-7d94ea0f67fa')
        ->and($link->account_id)->toBe($this->connection->account_id)->and($link->namespace_id)->toBe('186736344');
    expect($legacy->fresh()->getAttributes())->toEqual($legacyBefore);
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    $second = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $secondUrl = '/api/admin/teaching/course_works/'.$second->id.'/dropbox/folder';
    $proof = $this->postJson($secondUrl, ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()->json('proof');
    $this->postJson($secondUrl, ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])->assertOk();
    expect(TeachingWorkModuleLink::query()->count())->toBe(2);
});

test('invalid or forged local handshakes cannot initiate Dropbox reads', function (): void {
    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => '{"schema_version":1}', 'resolve' => true])->assertUnprocessable();
    Http::assertNothingSent();
});

test('another filename cannot initiate online resolution even with valid handshake contents', function (string $name): void {
    $this->postJson($this->url.'/folder', ['filename' => $name, 'handshake' => $this->handshake, 'resolve' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('filename');
    Http::assertNothingSent();
})->with(['andere.json', 'handshake (1).json', 'handshake.json.txt', 'folder/handshake.json']);

test('Windows-compatible filename casing remains accepted', function (): void {
    fakeModuleDropbox($this);
    $this->postJson($this->url.'/folder', ['filename' => 'Handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk();
});

test('copied online module identities remain a visible conflict', function (): void {
    $this->online[] = array_replace($this->online[0], ['id' => 'id:copy', 'path_lower' => '/schule2020_2027/materialien/lerninhalte/it_grundlagen/zweites-modul/handshake.json',
        'path_display' => '/schule2020_2027/Materialien/Lerninhalte/IT_Grundlagen/Zweites-Modul/handshake.json']);
    fakeModuleDropbox($this);

    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])
        ->assertOk()->assertJsonPath('conflict', true)->assertJsonCount(2, 'candidates')->assertJsonMissingPath('proof');
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('a changed online revision invalidates the pending link', function (): void {
    fakeModuleDropbox($this);
    $proof = $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()->json('proof');
    $this->online[0]['rev'] = 'revision2';

    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])->assertConflict();
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('a concurrent assignment cannot be overwritten by an older confirmation', function (): void {
    fakeModuleDropbox($this);
    $proof = $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()->json('proof');
    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])->assertOk();
    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])->assertConflict();
    expect(TeachingWorkModuleLink::query()->count())->toBe(1);
});

test('a confirmation cannot be reused for a different work or Dropbox account', function (): void {
    fakeModuleDropbox($this);
    $proof = $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()->json('proof');
    $other = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $this->postJson('/api/admin/teaching/course_works/'.$other->id.'/dropbox/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])->assertConflict();
    $this->connection->update(['account_id' => 'dbid:changed']);
    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])->assertConflict();
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('an unrelated teacher cannot resolve another teachers work', function (): void {
    $teacher = User::factory()->create(['school_id' => $this->user->school_id, 'schoolyear_id' => $this->user->schoolyear_id])->assignRole('teacher');
    $this->actingAs($teacher, 'sanctum')->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertForbidden();
    Http::assertNothingSent();
});

test('a missing search match is described as unconfirmed rather than nonexistent', function (): void {
    $this->online = [];
    fakeModuleDropbox($this);
    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('dropbox');
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('technical work copies outside the verified material roots do not compete with the real learning module', function (): void {
    foreach (['/AI/tmp/emails-pdf-20261020-01a15b48/before', '/AI/tmp/module-handshake-skill-20261020-01a15b12/live/handshake/handshake-I_6yschn',
        '/AI/tmp/exercise64-20261021/snapshot/schule2020_2027/Materialien/Lerninhalte/IT_Grundlagen/emails - Kopie'] as $index => $path) {
        $this->online[] = array_replace($this->online[0], ['id' => 'id:technical'.$index, 'rev' => 'technical'.$index,
            'path_lower' => strtolower($path).'/handshake.json', 'path_display' => $path.'/handshake.json']);
    }
    $this->online[] = ['.tag' => 'folder', 'id' => 'id:folder'.basename(dirname($this->online[0]['path_lower'])),
        'name' => 'emails - Kopie', 'path_lower' => dirname($this->online[0]['path_lower'])];
    fakeModuleDropbox($this);

    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()
        ->assertJsonPath('candidate.folder_path', '/schule2020_2027/Materialien/Lerninhalte/IT_Grundlagen/emails - Kopie')
        ->assertJsonMissingPath('conflict');
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/download')
        && str_contains($request->header('Dropbox-API-Arg')[0] ?? '', 'technical'));
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('a module moved into a technical snapshot cannot be confirmed with an older proof', function (): void {
    fakeModuleDropbox($this);
    $proof = $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()->json('proof');
    $this->online[0]['path_display'] = '/AI/tmp/exercise64-20261021/snapshot/schule2020_2027/Materialien/Lerninhalte/IT_Grundlagen/emails - Kopie/handshake.json';
    $this->online[0]['path_lower'] = strtolower($this->online[0]['path_display']);

    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])
        ->assertConflict()->assertJsonPath('message', 'Die Online-Datei liegt außerhalb des Lerninhalte-Bestands. Bitte erneut auswählen.');
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('an unapplied migration still permits read-only resolution but prevents saving', function (): void {
    $modules = Mockery::mock(DropboxModuleLinks::class, [app(DropboxWorkImport::class), app(ModuleHandshake::class)])->makePartial();
    $modules->shouldReceive('installed')->andReturn(false);
    app()->instance(DropboxModuleLinks::class, $modules);
    fakeModuleDropbox($this);
    $resolved = $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()
        ->assertJsonPath('can_link', false)->json();
    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $resolved['proof']])->assertConflict();
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('unlinking verifies that an older work has no assessment and removes only its associations', function (): void {
    fakeModuleDropbox($this);
    $second = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    foreach ([$this->work, $second] as $work) {
        $url = '/api/admin/teaching/course_works/'.$work->id.'/dropbox/folder';
        $proof = $this->postJson($url, ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()->json('proof');
        $this->postJson($url, ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])->assertOk();
    }
    $module = TeachingWorkModuleLink::query()->where('teaching_course_work_id', $this->work->id)->firstOrFail();
    $legacy = TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $this->work->id, 'folder_id' => $module->folder_id]);
    $secondLegacy = TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $second->id]);
    $this->work->update(['status' => ['folder_imported_at' => '2026-10-20T12:00:00Z', 'custom' => 'keep'],
        'groups' => [['student_ids' => [12], 'points' => 4.5]]]);
    $before = $this->work->fresh()->getAttributes();
    $version = $this->getJson($this->url)->assertOk()->json('link_version');
    $requestsBefore = Http::recorded()->count();

    $this->postJson($this->url.'/folder', ['unlink' => true, 'link_version' => $version])->assertOk()
        ->assertJsonPath('module_link', null)->assertJsonPath('folder', null)
        ->assertJsonPath('connected', true)->assertJsonPath('can_quick_import', false);

    expect(TeachingWorkModuleLink::query()->where('teaching_course_work_id', $this->work->id)->exists())->toBeFalse();
    expect(TeachingWorkModuleLink::query()->where('teaching_course_work_id', $second->id)->exists())->toBeTrue();
    expect($legacy->fresh())->toBeNull()->and($secondLegacy->fresh())->not->toBeNull();
    expect($this->connection->fresh())->not->toBeNull();
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    expect(Http::recorded()->count())->toBeGreaterThan($requestsBefore);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
    $this->getJson($this->url)->assertOk()->assertJsonPath('module_link', null)->assertJsonPath('folder', null);
});

test('an old unlink confirmation cannot delete a changed or different works assignment', function (): void {
    $legacy = TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $this->work->id, 'folder_id' => 'id:before']);
    $version = $this->getJson($this->url)->assertOk()->json('link_version');
    $legacy->update(['folder_id' => 'id:changed']);

    $this->postJson($this->url.'/folder', ['unlink' => true, 'link_version' => $version])->assertConflict();
    expect($legacy->fresh()->folder_id)->toBe('id:changed');
    $other = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $otherLegacy = TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $other->id]);
    $this->postJson('/api/admin/teaching/course_works/'.$other->id.'/dropbox/folder', ['unlink' => true, 'link_version' => $version])->assertConflict();
    expect($otherLegacy->fresh())->not->toBeNull();
    Http::assertNothingSent();
});

test('unlink is serialized with assessment writes and rechecks after an assessment appears', function (): void {
    linkExerciseFixture($this);
    $link = TeachingWorkModuleLink::query()->firstOrFail();
    $requestCount = Http::recorded()->count();
    $this->getJson($this->url)->assertOk()->assertJsonPath('unlink_status', null);
    expect(Http::recorded()->count())->toBe($requestCount);
    $version = $this->getJson($this->url.'?include_unlink_status=1')->assertOk()
        ->assertJsonPath('unlink_status.status', 'available')->json('link_version');
    $lock = Cache::lock('teaching:direct-assessment:'.hash('sha256', $link->folder_id), 300);
    expect($lock->get())->toBeTrue();
    try {
        $this->postJson($this->url.'/folder', ['unlink' => true, 'link_version' => $version])->assertConflict();
    } finally {
        $lock->release();
    }
    addExerciseFixture($this);
    $this->getJson($this->url.'?include_unlink_status=1')->assertOk()->assertJsonPath('unlink_status.status', 'blocked');
    $this->postJson($this->url.'/folder', ['unlink' => true, 'link_version' => $version])->assertConflict()
        ->assertJsonPath('message', 'Zuerst „Leistungsfeststellung löschen“ bestätigen. Danach kann die Verbindung getrennt werden.');
    expect($link->fresh())->not->toBeNull();
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('a failed creation only blocks unlink when the current folder evidence requires it', function (bool $hasRemnants): void {
    linkExerciseFixture($this);
    $key = 'teaching-assessment-run:'.$this->work->id;
    Cache::store('database')->put($key, 'fixture-run', 60);
    Cache::store('database')->put($key.':fixture-run', ['state' => 'failed'], 60);
    if ($hasRemnants) {
        $path = addModernExerciseFixture($this);
        $this->online = array_values(array_filter($this->online, fn (array $entry): bool => $entry['path_lower'] !== mb_strtolower($path.'/status.json')));
    }

    $version = $this->getJson($this->url.'?include_unlink_status=1')->assertOk()
        ->assertJsonPath('unlink_status.status', $hasRemnants ? 'unknown' : 'available')->json('link_version');
    $this->postJson($this->url.'/folder', ['unlink' => true, 'link_version' => $version])->assertStatus($hasRemnants ? 409 : 200);
    expect(TeachingWorkModuleLink::query()->where('teaching_course_work_id', $this->work->id)->exists())->toBe($hasRemnants);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
})->with([false, true]);

test('an old direct assessment folder cannot be orphaned by unlinking', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $exam = collect($this->online)->firstWhere('name', '2026-10-06_E-Mail_Fach_3B_Gruppe-2');
    TeachingWorkModuleLink::query()->where('teaching_course_work_id', $this->work->id)->delete();
    $legacy = TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $this->work->id, 'folder_id' => $exam['id'], 'folder_name' => $exam['name']]);
    $version = $this->getJson($this->url.'?include_unlink_status=1')->assertOk()
        ->assertJsonPath('unlink_status.status', 'blocked')->json('link_version');
    $this->postJson($this->url.'/folder', ['unlink' => true, 'link_version' => $version])->assertConflict();
    expect($legacy->fresh())->not->toBeNull();
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('unlinking keeps foreign module links and other users connections intact', function (): void {
    fakeModuleDropbox($this);
    $proof = $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()->json('proof');
    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])->assertOk();
    $foreign = DropboxConnection::factory()->create();
    $module = TeachingWorkModuleLink::query()->firstOrFail();
    $module->update(['dropbox_connection_id' => $foreign->id, 'account_id' => $foreign->account_id]);
    $foreignLegacy = TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $foreign->id,
        'teaching_course_work_id' => $this->work->id]);
    $version = $this->getJson($this->url)->assertOk()->assertJsonPath('module_conflict', true)->json('link_version');

    $this->postJson($this->url.'/folder', ['unlink' => true, 'link_version' => $version])->assertConflict();
    expect($module->fresh()->dropbox_connection_id)->toBe($foreign->id);
    expect($foreignLegacy->fresh())->not->toBeNull();
    expect($foreign->fresh())->not->toBeNull();
});

test('unlinking remains server-authorized and requires the displayed assignment version', function (): void {
    $legacy = TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $this->work->id]);
    $version = $this->getJson($this->url)->assertOk()->json('link_version');
    $this->postJson($this->url.'/folder', ['unlink' => true])->assertUnprocessable();
    $teacher = User::factory()->create(['school_id' => $this->user->school_id, 'schoolyear_id' => $this->user->schoolyear_id])->assignRole('teacher');
    $this->actingAs($teacher, 'sanctum')->postJson($this->url.'/folder', ['unlink' => true, 'link_version' => $version])->assertForbidden();
    expect($legacy->fresh())->not->toBeNull();
    Http::assertNothingSent();
});

test('deleting a work removes its verified modern exam folder and preserves the module and foreign folders', function (): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $foreignPath = dirname($path).'/fremde Leistungsfeststellung';
    $this->online[] = ['.tag' => 'folder', 'id' => 'id:foreign', 'name' => basename($foreignPath),
        'path_lower' => mb_strtolower($foreignPath), 'path_display' => $foreignPath];
    $this->deleteJson('/api/admin/teaching/course_works/'.$this->work->id)->assertNoContent();
    expect(collect($this->online)->contains('path_lower', mb_strtolower($path)))->toBeFalse()
        ->and(collect($this->online)->contains('id', 'id:foreign'))->toBeTrue()
        ->and(collect($this->online)->contains('id', 'id:handshake'))->toBeTrue()
        ->and(count($this->deletedIds))->toBe(1);
});

test('clearing the selected exercise preserves the linked folder and the work before unlinking', function (string $failure): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this);
    $this->resetExerciseStatus = true;
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $before = $this->work->fresh()->getAttributes();
    $url = '/api/admin/teaching/course_works/'.$this->work->id.'/exercise';
    $id = collect($this->online)->firstWhere('path_display', $path)['id'];
    $status = collect($this->online)->firstWhere('path_lower', mb_strtolower($path).'/status.json');
    $version = $this->getJson($this->url.'?include_unlink_status=1')->assertOk()->assertJsonPath('unlink_status.status', 'blocked')->json('link_version');
    $unlink = ['unlink' => true, 'link_version' => $version];
    $this->postJson($this->url.'/folder', $unlink)->assertConflict();
    $this->deleteJson($url, ['date' => '2026-10-06', 'folder_id' => 'id:unrelated'])->assertConflict();
    $this->deleteJson($url, ['date' => '2026-10-07', 'folder_id' => $id])->assertConflict();
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/delete_v2'));

    $data = ['date' => '2026-10-06', 'folder_id' => $id];
    $readProgress = function () use ($url, $data): array {
        $response = $this->deleteJson($url.'?stream_progress=1', $data)->assertOk()->assertHeader('Content-Type', 'application/x-ndjson');

        return array_map(fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
            explode("\n", trim($response->streamedContent())));
    };
    if ($failure !== 'none') {
        $this->failDeleteAt = $failure === 'contents' ? 2 : 0;
        $this->failVerificationAfterReset = $failure === 'verification';
        $failed = $readProgress();
        expect(end($failed)['type'])->toBe('error');
        if ($failure === 'contents') {
            expect($failed[array_key_last($failed) - 1]['data'])->toMatchArray(['state' => 'failed', 'active' => 'exercise_delete_contents',
                'completed' => ['exercise_delete_check'], 'detail' => '1 von 3 Einträgen gelöscht']);
            expect(collect($this->online)->firstWhere('id', $status['id'])['rev'])->toBe($status['rev']);
        } else {
            expect(collect($this->online)->firstWhere('id', $status['id'])['rev'])->not->toBe($status['rev']);
            $this->failDownloadPath = null;
            $this->failVerificationAfterReset = false;
            $this->getJson($this->url.'?include_exercise=1&date=2026-10-06&refresh=1')->assertOk()
                ->assertJsonPath('exercise.deletion_pending', true)->assertJsonPath('exercise.folder.id', $id);
        }
        $this->getJson($this->url.'?include_unlink_status=1')->assertOk()->assertJsonPath('unlink_status.status', 'unknown');
        $this->postJson($this->url.'/folder', $unlink)->assertConflict();
    }
    $events = $readProgress();
    $progress = array_column(array_filter($events, fn (array $event): bool => $event['type'] === 'progress'), 'data');
    $steps = ['exercise_delete_check', 'exercise_delete_contents', 'exercise_delete_reset', 'exercise_delete_verify'];
    $completed = $failure === 'verification' ? ['exercise_delete_check', 'exercise_delete_verify'] : $steps;
    $result = end($events);
    $this->assertSame('result', $result['type'], $result['data']['message'] ?? 'LF-Löschung konnte nicht bestätigt werden.');
    expect($result['data']['deleted'])->toBeTrue();
    expect(array_values(array_unique(array_filter(array_column($progress, 'active')))))->toBe($completed);
    expect($result['data']['progress'])->toMatchArray(['state' => 'done', 'completed' => $completed]);
    $alreadyReset = $readProgress();
    $result = end($alreadyReset);
    expect($result['type'])->toBe('result')->and($result['data']['deleted'])->toBeTrue();
    expect($result['data']['progress'])->toMatchArray(['state' => 'done',
        'completed' => ['exercise_delete_check', 'exercise_delete_verify'], 'skipped' => ['exercise_delete_contents', 'exercise_delete_reset']]);
    $this->deleteJson($url, $data)->assertNoContent();
    expect($this->deletedIds)->not->toContain($id, $status['id'])
        ->and(collect($this->online)->firstWhere('path_display', $path)['id'])->toBe($id)
        ->and(collect($this->online)->filter(fn (array $entry): bool => str_starts_with($entry['path_lower'], mb_strtolower($path).'/'))->pluck('id')->all())->toBe([$status['id']])
        ->and($this->work->fresh()->getAttributes())->toBe($before)
        ->and(TeachingWorkModuleLink::query()->where('teaching_course_work_id', $this->work->id)->exists())->toBeTrue()
        ->and(collect($this->online)->contains('id', 'id:handshake'))->toBeTrue();
    $this->getJson($this->url.'?include_exercise=1&date=2026-10-06')->assertOk()->assertJsonPath('exercise.status', 'missing')
        ->assertJsonPath('exercise.reusable', 'linked');
    $this->getJson($this->url.'?include_unlink_status=1')->assertOk()->assertJsonPath('unlink_status.status', 'available');
    $this->postJson($this->url.'/folder', $unlink)->assertOk()->assertJsonPath('module_link', null);
    expect(collect($this->online)->contains('id', $id))->toBeFalse()
        ->and($this->deletedIds)->toContain($id)
        ->and(collect($this->online)->contains('id', 'id:handshake'))->toBeTrue()
        ->and($this->work->fresh()->getAttributes())->toBe($before);
})->with(['none', 'contents', 'verification']);

test('deleting a work removes a verified legacy exam folder', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $this->deleteJson('/api/admin/teaching/course_works/'.$this->work->id)->assertNoContent();
    expect(count($this->deletedIds))->toBe(1)
        ->and(collect($this->online)->contains('id', 'id:handshake'))->toBeTrue();
});

test('old and new exam folders are both deleted when they belong to distinct dates', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    addModernExerciseFixture($this, date: '2026-10-07');
    $students = User::factory()->count(2)->create(['school_id' => $this->course->school_id, 'schoolyear_id' => $this->course->schoolyear_id]);
    foreach ($students as $student) {
        $this->course->teachingCourseStudents()->create(['user_id' => $student->id]);
    }
    $this->work->update(['groups' => [['date' => '2026-10-06', 'student_ids' => [$students[0]->id]], ['date' => '2026-10-07', 'student_ids' => [$students[1]->id]]]]);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $this->deleteJson('/api/admin/teaching/course_works/'.$this->work->id)->assertNoContent();
    expect($this->deletedIds)->toHaveCount(2)
        ->and(collect($this->online)->contains('id', 'id:handshake'))->toBeTrue();
});

test('old and new exam folders for the same date are preserved as ambiguous', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    addModernExerciseFixture($this);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $this->deleteJson('/api/admin/teaching/course_works/'.$this->work->id)->assertOk()->assertJsonPath('deleted', true)->assertJsonPath('skipped_unverified', true);
    expect($this->work->fresh())->toBeNull()->and($this->deletedIds ?? [])->toBe([]);
});

test('a shared exam folder remains when its work is deleted', function (): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this);
    $folder = collect($this->online)->firstWhere('path_lower', mb_strtolower($path));
    $other = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $other->id, 'folder_id' => $folder['id']]);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $this->deleteJson('/api/admin/teaching/course_works/'.$this->work->id)->assertOk()->assertJsonPath('deleted', true)->assertJsonPath('skipped_unverified', true);
    expect($this->work->fresh())->toBeNull()
        ->and($this->deletedIds ?? [])->toBe([]);
});

test('a partial exam folder deletion can be retried without deleting other data', function (): void {
    linkExerciseFixture($this);
    $first = addModernExerciseFixture($this);
    $second = addModernExerciseFixture($this, date: '2026-10-07');
    $firstFolder = collect($this->online)->firstWhere('path_lower', mb_strtolower($first));
    TeachingWorkDropboxFolder::query()->updateOrCreate(['teaching_course_work_id' => $this->work->id,
        'dropbox_connection_id' => $this->connection->id], ['folder_id' => $firstFolder['id'], 'folder_name' => $firstFolder['name']]);
    $catalog = McCatalog::query()->create(['school_id' => $this->course->school_id, 'owner_id' => $this->user->id, 'title' => 'MC', 'questions' => []]);
    $test = McTest::query()->create(['school_id' => $this->course->school_id, 'schoolyear_id' => $this->course->schoolyear_id,
        'owner_id' => $this->user->id, 'catalog_id' => $catalog->id, 'title' => 'MC', 'status' => 'draft',
        'question_count' => 1, 'total_points' => 1, 'duration_seconds' => 300]);
    $this->work->update(['status' => ['multiple_choice_tests' => ['all' => ['work_id' => $this->work->id, 'test_id' => $test->id]]]]);
    $students = User::factory()->count(2)->create(['school_id' => $this->course->school_id, 'schoolyear_id' => $this->course->schoolyear_id]);
    foreach ($students as $student) {
        $this->course->teachingCourseStudents()->create(['user_id' => $student->id]);
    }
    $this->work->update(['groups' => [['date' => '2026-10-06', 'student_ids' => [$students[0]->id]], ['date' => '2026-10-07', 'student_ids' => [$students[1]->id]]]]);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $this->failDeleteAt = 2;
    $url = '/api/admin/teaching/course_works/'.$this->work->id;
    $this->deleteJson($url)->assertStatus(409);
    expect($this->work->fresh())->not->toBeNull()
        ->and($test->fresh())->not->toBeNull()
        ->and($this->work->fresh()->status['deletion_receipt']['folders'])->toHaveCount(2)
        ->and(collect($this->online)->contains('path_lower', mb_strtolower($first)))->toBeFalse()
        ->and(collect($this->online)->contains('path_lower', mb_strtolower($second)))->toBeTrue();
    $this->putJson($url, ['title' => 'Nicht speichern'])->assertStatus(409)
        ->assertJsonPath('message', 'Die Löschung ist noch nicht abgeschlossen. Bitte die Zuordnung prüfen und erneut löschen.');
    $this->failDeleteAt = 0;
    $this->deleteJson($url)->assertNoContent();
    expect(count($this->deletedIds))->toBe(2)
        ->and($test->fresh())->toBeNull()
        ->and(collect($this->online)->contains('id', 'id:handshake'))->toBeTrue();
});

test('a changed work after a partial deletion is stopped before the remaining folder is removed', function (): void {
    linkExerciseFixture($this);
    addModernExerciseFixture($this);
    addModernExerciseFixture($this, date: '2026-10-07');
    $students = User::factory()->count(2)->create(['school_id' => $this->course->school_id, 'schoolyear_id' => $this->course->schoolyear_id]);
    foreach ($students as $student) {
        $this->course->teachingCourseStudents()->create(['user_id' => $student->id]);
    }
    $this->work->update(['groups' => [['date' => '2026-10-06', 'student_ids' => [$students[0]->id]], ['date' => '2026-10-07', 'student_ids' => [$students[1]->id]]]]);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $this->failDeleteAt = 2;
    $url = '/api/admin/teaching/course_works/'.$this->work->id;
    $this->deleteJson($url)->assertStatus(409);
    $this->work->fresh()->update(['title' => 'Verändert']);
    $this->failDeleteAt = 0;
    $this->deleteJson($url)->assertStatus(409)
        ->assertJsonPath('message', 'Die Arbeit oder ihr gespeicherter Löschstand wurde seit dem letzten Versuch geändert. Bitte die Zuordnung prüfen.');
    expect($this->deletedIds)->toHaveCount(1)->and($this->work->fresh())->not->toBeNull();
});

test('a lost Dropbox response can be retried after the exact exam folder was removed', function (): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $this->loseDeleteResponseAt = 1;
    $url = '/api/admin/teaching/course_works/'.$this->work->id;
    $this->deleteJson($url)->assertStatus(409);
    expect($this->work->fresh())->not->toBeNull()
        ->and(collect($this->online)->contains('path_lower', mb_strtolower($path)))->toBeFalse();
    $this->deleteJson($url)->assertNoContent();
    expect($this->deletedIds)->toHaveCount(1);
});

test('shared contents and catalog references are preserved when deleting the work', function (string $scenario): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    if ($scenario === 'shared file') {
        foreach ($this->online as &$entry) {
            if (($entry['path_lower'] ?? '') === mb_strtolower($path.'/Beurteilung.pdf')) {
                $entry['has_explicit_shared_members'] = true;
            }
        }
        unset($entry);
    } elseif ($scenario === 'referenced child') {
        $folder = collect($this->online)->firstWhere('path_lower', mb_strtolower($path.'/Kandidaten'));
        $other = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
        TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $this->connection->id,
            'teaching_course_work_id' => $other->id, 'folder_id' => $folder['id']]);
    } else {
        $file = collect($this->online)->firstWhere('path_lower', mb_strtolower($path.'/Beurteilung.pdf'));
        McCatalog::query()->create(['school_id' => $this->course->school_id, 'owner_id' => $this->user->id,
            'title' => 'Geteilte Quelle', 'questions' => [], 'module_sources' => [['source_file_id' => $file['id']]]]);
    }
    $this->deleteJson('/api/admin/teaching/course_works/'.$this->work->id)
        ->assertOk()->assertJsonPath('deleted', true)->assertJsonPath('skipped_unverified', true);
    expect($this->work->fresh())->toBeNull();
    expect($this->deletedIds ?? [])->toBe([]);
})->with(['shared file', 'referenced child', 'catalog source']);

test('a legacy folder link without a module link is not guessed during deletion', function (): void {
    TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $this->work->id, 'folder_id' => 'id:unknown']);
    $this->deleteJson('/api/admin/teaching/course_works/'.$this->work->id)->assertOk()->assertJsonPath('deleted', true)->assertJsonPath('skipped_unverified', true);
    expect($this->work->fresh())->toBeNull();
    Http::assertNothingSent();
});

test('an unverified missing old linked folder skips external cleanup while deleting the work', function (): void {
    linkExerciseFixture($this);
    TeachingWorkDropboxFolder::query()->updateOrCreate(['teaching_course_work_id' => $this->work->id,
        'dropbox_connection_id' => $this->connection->id], ['folder_id' => 'id:missing', 'folder_name' => 'Unbekannt']);
    $this->deleteJson('/api/admin/teaching/course_works/'.$this->work->id)->assertOk()->assertJsonPath('deleted', true)->assertJsonPath('skipped_unverified', true);
    expect($this->work->fresh())->toBeNull()->and($this->deletedIds ?? [])->toBe([]);
});

test('a test used by another work and exam folders remain when the work is deleted', function (): void {
    linkExerciseFixture($this);
    addModernExerciseFixture($this);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $catalog = McCatalog::query()->create(['school_id' => $this->course->school_id, 'owner_id' => $this->user->id, 'title' => 'MC', 'questions' => []]);
    $test = McTest::query()->create(['school_id' => $this->course->school_id, 'schoolyear_id' => $this->course->schoolyear_id,
        'owner_id' => $this->user->id, 'catalog_id' => $catalog->id, 'title' => 'MC', 'status' => 'draft',
        'question_count' => 1, 'total_points' => 1, 'duration_seconds' => 300]);
    $binding = ['all' => ['work_id' => $this->work->id, 'test_id' => $test->id]];
    $this->work->update(['status' => ['multiple_choice_tests' => $binding]]);
    TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id, 'status' => ['multiple_choice_tests' => $binding]]);
    $this->deleteJson('/api/admin/teaching/course_works/'.$this->work->id)->assertOk()->assertJsonPath('deleted', true)->assertJsonPath('skipped_unverified', true);
    expect($this->work->fresh())->toBeNull()->and($test->fresh())->not->toBeNull()
        ->and($this->deletedIds ?? [])->toBe([]);
});

/** @return array{path: string, root: array<string, mixed>, test: McTest, catalog: McCatalog} */
function recursiveRootDeletionFixture(object $context): array
{
    linkExerciseFixture($context);
    $path = addModernExerciseFixture($context, withMc: true);
    $root = collect($context->online)->firstWhere('path_lower', mb_strtolower($path));
    $context->recursiveRootFolderId = $root['id'];
    for ($index = 0; $index < 55; $index++) {
        $file = $path.'/Kandidaten/Person/Abgaben/fixture-'.$index.'.txt';
        $context->online[] = ['.tag' => 'file', 'id' => 'id:fixture'.$index, 'name' => basename($file),
            'path_lower' => mb_strtolower($file), 'path_display' => $file];
    }
    $credentials = $context->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $context->connection->update(['credentials' => $credentials]);
    $catalog = McCatalog::query()->create(['school_id' => $context->course->school_id, 'owner_id' => $context->user->id,
        'title' => 'Gemeinsamer Fragenkatalog', 'questions' => []]);
    $test = McTest::query()->create(['school_id' => $context->course->school_id, 'schoolyear_id' => $context->course->schoolyear_id,
        'owner_id' => $context->user->id, 'catalog_id' => $catalog->id, 'title' => 'Prüfung',
        'status' => 'published', 'question_count' => 1, 'total_points' => 2, 'duration_seconds' => 300]);
    $students = User::factory()->count(12)->create(['school_id' => $context->course->school_id, 'schoolyear_id' => $context->course->schoolyear_id]);
    foreach ($students as $student) {
        McAttempt::query()->create(['test_id' => $test->id, 'student_id' => $student->id, 'student_name' => 'Testperson',
            'email' => $student->email, 'questions' => [], 'answers' => [], 'access_token' => 'fixture-'.$student->id,
            'access_token_hash' => hash('sha256', 'fixture-'.$student->id)]);
    }
    $context->work->update(['status' => ['multiple_choice_tests' => ['2026-10-06' => ['work_id' => $context->work->id, 'test_id' => $test->id]]]]);

    return ['path' => $path, 'root' => $root, 'test' => $test, 'catalog' => $catalog];
}

test('streams the actual Dropbox deletion step and reports failures without inventing success', function (bool $fail): void {
    $fixture = recursiveRootDeletionFixture($this);
    if ($fail) {
        $this->failDeleteAt = 1;
    }
    $response = $this->deleteJson('/api/admin/teaching/course_works/'.$this->work->id.'?stream_progress=1')->assertOk();
    $events = array_map(fn ($line) => json_decode($line, true), array_filter(explode("\n", $response->streamedContent())));
    $progress = array_values(array_filter($events, fn ($event) => $event['type'] === 'progress'));
    expect(collect($progress)->contains(fn ($event) => $event['data']['active'] === 'delete_folders'))->toBeTrue();
    $last = $progress[count($progress) - 1]['data'];
    expect($last['state'])->toBe($fail ? 'failed' : 'done');
    expect(in_array('delete_folders', $last['completed'], true))->toBe(! $fail);
    expect(end($events)['type'])->toBe($fail ? 'error' : 'result');
    if ($fail) {
        $this->assertDatabaseHas('teaching_course_works', ['id' => $this->work->id]);
        expect($this->deletedIds ?? [])->toBe([]);
    } else {
        $this->assertDatabaseMissing('teaching_course_works', ['id' => $this->work->id]);
        expect($this->deletedIds)->toBe([$fixture['root']['id']]);
    }
})->with([false, true]);

test('a paginated Dropbox listing containing its own root permits deleting the exact work and MC test', function (): void {
    $fixture = recursiveRootDeletionFixture($this);
    $otherWork = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $otherTest = McTest::query()->create(['school_id' => $this->course->school_id, 'schoolyear_id' => $this->course->schoolyear_id,
        'owner_id' => $this->user->id, 'catalog_id' => $fixture['catalog']->id, 'title' => 'Andere Prüfung',
        'status' => 'draft', 'question_count' => 1, 'total_points' => 2, 'duration_seconds' => 300]);
    expect($fixture['test']->attempts()->count())->toBe(12);
    $this->deleteJson('/api/admin/teaching/course_works/'.$this->work->id)->assertNoContent();
    $this->assertDatabaseMissing('teaching_course_works', ['id' => $this->work->id]);
    $this->assertDatabaseMissing('mc_tests', ['id' => $fixture['test']->id]);
    $this->assertDatabaseMissing('mc_attempts', ['test_id' => $fixture['test']->id]);
    $this->assertDatabaseHas('mc_catalogs', ['id' => $fixture['catalog']->id]);
    $this->assertDatabaseHas('mc_tests', ['id' => $otherTest->id]);
    $this->assertDatabaseHas('teaching_course_works', ['id' => $otherWork->id]);
    expect($this->deletedIds)->toBe([$fixture['root']['id']])
        ->and(collect($this->online)->contains('id', 'id:handshake'))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/list_folder/continue')
        && ($request['cursor'] ?? null) === 'recursive-exam-page-2');
});

test('an exam folder shared after preflight is preserved before the exact delete', function (): void {
    $fixture = recursiveRootDeletionFixture($this);
    $this->shareAfterPreflightId = $fixture['root']['id'];

    $this->deleteJson('/api/admin/teaching/course_works/'.$this->work->id)->assertConflict();
    $this->assertDatabaseHas('teaching_course_works', ['id' => $this->work->id]);
    $this->assertDatabaseHas('mc_tests', ['id' => $fixture['test']->id]);
    expect($this->work->fresh()->status['deletion_receipt']['folders'])->toHaveCount(1);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/delete_v2'));
});

test('a recursive listing still protects inconsistent or shared entries with the root present', function (string $scenario): void {
    $fixture = recursiveRootDeletionFixture($this);
    $extra = $fixture['root'];
    if ($scenario === 'same path different id') {
        $extra['id'] = 'id:foreignroot';
    } elseif ($scenario === 'same id and path different name') {
        $extra['name'] = 'Widersprüchlicher Name';
    } elseif ($scenario === 'same id different path') {
        $extra['path_lower'] .= '/fremd';
    } elseif ($scenario === 'outside path') {
        $extra['id'] = 'id:foreign';
        $extra['path_lower'] = '/anderer-ordner';
    } elseif ($scenario === 'shared root') {
        $extra['sharing_info'] = ['shared_folder_id' => 'shared-test'];
    } else {
        $extra['.tag'] = 'file';
        $extra['id'] = 'id:sharedfile';
        $extra['path_lower'] .= '/geteilte-datei';
        $extra['has_explicit_shared_members'] = true;
    }
    $this->recursiveExtraEntries = [$extra];
    $response = $this->deleteJson('/api/admin/teaching/course_works/'.$this->work->id);
    $response->assertOk()->assertJsonPath('deleted', true)->assertJsonPath('skipped_unverified', true);
    $this->assertDatabaseMissing('teaching_course_works', ['id' => $this->work->id]);
    $this->assertDatabaseHas('mc_tests', ['id' => $fixture['test']->id]);
    expect($fixture['test']->attempts()->count())->toBe(12)->and($this->deletedIds ?? [])->toBe([]);
})->with(['same path different id', 'same id and path different name', 'same id different path', 'outside path', 'shared root', 'shared child']);

/** Keep the canonical folder but remove every child to model a pre-existing empty folder. */
function makeModernFixtureEmpty(object $context, string $path): void
{
    $context->online = array_values(array_filter($context->online,
        fn (array $entry): bool => ! str_starts_with($entry['path_lower'] ?? '', mb_strtolower($path).'/')));
}

test('one streamed deletion preserves an unproven folder and independent MC attempts', function (): void {
    $fixture = recursiveRootDeletionFixture($this);
    makeModernFixtureEmpty($this, $fixture['path']);
    $url = '/api/admin/teaching/course_works/'.$this->work->id;
    $response = $this->deleteJson($url.'?stream_progress=1')->assertOk();
    $events = array_values(array_map(fn ($line) => json_decode($line, true), array_filter(explode("\n", $response->streamedContent()))));
    $last = end($events);
    expect($last['type'])->toBe('result')->and($last['data']['deleted'])->toBeTrue()
        ->and($last['data']['skipped_unverified'])->toBeTrue();
    $progress = array_values(array_filter($events, fn ($event) => $event['type'] === 'progress'));
    $finalProgress = end($progress)['data'];
    expect($finalProgress['state'])->toBe('done')->and($finalProgress['completed'])->toContain('delete_records')
        ->and($finalProgress['skipped'])->toContain('delete_folders', 'delete_mc_files');
    $this->assertDatabaseMissing('teaching_course_works', ['id' => $this->work->id]);
    $this->assertDatabaseMissing('teaching_work_module_links', ['teaching_course_work_id' => $this->work->id]);
    expect($fixture['test']->fresh())->not->toBeNull()->and($fixture['test']->attempts()->count())->toBe(12)
        ->and(collect($this->online)->contains('id', $fixture['root']['id']))->toBeTrue()
        ->and($this->deletedIds ?? [])->toBe([]);
    $this->deleteJson($url.'?stream_progress=1')->assertNotFound();
});

test('one deletion continues when the external Dropbox check cannot be reached', function (): void {
    $fixture = recursiveRootDeletionFixture($this);
    $this->exerciseOffline = true;
    $url = '/api/admin/teaching/course_works/'.$this->work->id.'?stream_progress=1';
    $response = $this->deleteJson($url)->assertOk();
    $events = array_values(array_map(fn ($line) => json_decode($line, true), array_filter(explode("\n", $response->streamedContent()))));
    expect(end($events)['type'])->toBe('result')->and(end($events)['data']['deleted'])->toBeTrue()
        ->and(end($events)['data']['skipped_unverified'])->toBeTrue()
        ->and(end($events)['data']['message'])->toContain('Dropbox');
    $this->assertDatabaseMissing('teaching_course_works', ['id' => $this->work->id]);
    expect($fixture['test']->fresh())->not->toBeNull()->and($fixture['test']->attempts()->count())->toBe(12)
        ->and(collect($this->online)->contains('id', $fixture['root']['id']))->toBeTrue()
        ->and($this->deletedIds ?? [])->toBe([]);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/delete_v2'));
});

test('one deletion preserves remote data when reading a status revision returns a Dropbox HTTP error', function (): void {
    $fixture = recursiveRootDeletionFixture($this);
    $file = collect($this->online)->first(fn (array $entry): bool => ($entry['name'] ?? null) === 'status.json'
        && ($entry['path_lower'] ?? null) === mb_strtolower($fixture['path'].'/status.json'));
    $this->failDownloadPath = 'rev:'.$file['rev'];
    $link = TeachingWorkModuleLink::query()->where('teaching_course_work_id', $this->work->id)->firstOrFail();
    $state = app(DropboxModuleLinks::class)->exercise($this->connection, $this->work->fresh('teachingCourse'), $link, '2026-10-06', refresh: true);
    expect($state['status'])->toBe('unknown')->and($state['message'])->toContain('Anlagezustand nicht bestätigt. Dropbox-Anfrage fehlgeschlagen.');
    $response = $this->deleteJson('/api/admin/teaching/course_works/'.$this->work->id.'?stream_progress=1')->assertOk();
    $events = array_values(array_map(fn ($line) => json_decode($line, true), array_filter(explode("\n", $response->streamedContent()))));
    $result = end($events);
    expect($result['type'])->toBe('result')->and($result['data']['deleted'])->toBeTrue()
        ->and($result['data']['skipped_unverified'])->toBeTrue()
        ->and($result['data']['message'])->toContain('Anlagezustand nicht bestätigt. Dropbox-Anfrage fehlgeschlagen.');
    $this->assertDatabaseMissing('teaching_course_works', ['id' => $this->work->id]);
    expect($fixture['test']->fresh())->not->toBeNull()->and($fixture['test']->attempts()->count())->toBe(12)
        ->and(collect($this->online)->contains('id', $fixture['root']['id']))->toBeTrue()
        ->and($this->deletedIds ?? [])->toBe([]);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/download')
        && (json_decode($request->header('Dropbox-API-Arg')[0], true)['path'] ?? null) === $this->failDownloadPath);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/delete_v2'));
});

test('a teacher without course access cannot trigger work deletion or Dropbox inspection', function (): void {
    $fixture = recursiveRootDeletionFixture($this);
    $otherTeacher = User::factory()->create(['school_id' => $this->course->school_id,
        'schoolyear_id' => $this->course->schoolyear_id])->assignRole('teacher');
    Http::fake();
    $this->actingAs($otherTeacher, 'sanctum')->deleteJson('/api/admin/teaching/course_works/'.$this->work->id.'?stream_progress=1')->assertForbidden();
    $this->assertDatabaseHas('teaching_course_works', ['id' => $this->work->id]);
    expect($fixture['test']->fresh())->not->toBeNull()->and($fixture['test']->attempts()->count())->toBe(12);
    Http::assertNothingSent();
});

test('internal work validation and runtime errors do not trigger deletion', function (string $kind): void {
    $fixture = recursiveRootDeletionFixture($this);
    $sync = Mockery::mock(TeachingCourseWorkEntrySyncService::class)->makePartial();
    $failure = $kind === 'validation' ? ValidationException::withMessages(['internal' => 'Interne Prüfung fehlgeschlagen.'])
        : new RuntimeException('Interner Fehler.');
    $sync->shouldReceive('groupsForWork')->once()->andThrow($failure);
    app()->instance(TeachingCourseWorkEntrySyncService::class, $sync);
    $response = $this->deleteJson('/api/admin/teaching/course_works/'.$this->work->id.'?stream_progress=1')->assertOk();
    $events = array_values(array_map(fn ($line) => json_decode($line, true), array_filter(explode("\n", $response->streamedContent()))));
    expect(end($events)['type'])->toBe('error')->and($this->work->fresh())->not->toBeNull()
        ->and($fixture['test']->fresh())->not->toBeNull()->and($fixture['test']->attempts()->count())->toBe(12)
        ->and($this->deletedIds ?? [])->toBe([]);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/delete_v2'));
})->with(['validation', 'runtime']);

test('a status only initialized exam folder with an explicit work binding is removed normally', function (): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this);
    makeModernFixtureEmpty($this, $path);
    $record = ['schema_version' => 1, 'kind' => 'leistungsfeststellung.status',
        'schooltool' => ['work_id' => $this->work->id, 'course_id' => $this->course->id],
        'folder_group' => '3B_Gruppe-2', 'scheduled_date' => '2026-10-06', 'prepared' => false,
        'components' => ['aufgabenblatt' => ['included' => false, 'points' => null],
            'multiple_choice' => ['included' => false, 'points' => null]], 'total_points' => null];
    addEmailPreparationFile($this, $path.'/status.json', json_encode($record, JSON_THROW_ON_ERROR));
    $folder = collect($this->online)->firstWhere('path_lower', mb_strtolower($path));
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $this->deleteJson('/api/admin/teaching/course_works/'.$this->work->id)->assertNoContent();
    expect($this->deletedIds)->toBe([$folder['id']])->and($this->work->fresh())->toBeNull();
    expect(Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/files/list_folder')
        && ($request['recursive'] ?? false) && $request['path'] === $folder['id']))->toHaveCount(2);
});

test('the confirmed initial exam can be deleted without reading unrelated exams', function (string $scenario): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this);
    makeModernFixtureEmpty($this, $path);
    addLinkedExerciseStatusFixture($this, $path);
    $this->work->update(['status' => ['creation_receipt' => ['id' => 'test-creation', 'user_id' => $this->user->id]]]);
    $credentials = $this->connection->credentials;
    $credentials['scopes'][] = 'files.content.write';
    $this->connection->update(['credentials' => $credentials]);
    $folder = collect($this->online)->firstWhere('path_lower', mb_strtolower($path));
    $status = collect($this->online)->firstWhere('name', 'status.json');
    $modules = app(DropboxModuleLinks::class);
    $modules->rememberInitializedExercise($this->connection, $this->work, $modules->current($this->work), '2026-10-06', $folder, lock: false);
    $neighbor = dirname($path).'/2026-10-06 – 3B_Gruppe-2 – Andere';
    $this->online[] = ['.tag' => 'folder', 'id' => 'id:neighbor', 'name' => basename($neighbor),
        'path_lower' => mb_strtolower($neighbor), 'path_display' => $neighbor];
    addEmailPreparationFile($this, $neighbor.'/status.json', 'Unrelated incomplete status');
    if ($scenario === 'catalog') {
        McCatalog::query()->create(['school_id' => $this->course->school_id, 'owner_id' => $this->user->id,
            'title' => 'Geteilte Quelle', 'questions' => [], 'module_sources' => [['source_file_id' => $status['id']]]]);
    } elseif ($scenario === 'changed') {
        $this->changeStatusOnRecheckId = $folder['id'];
    } elseif ($scenario === 'lost response') {
        $this->loseDeleteResponseAt = 1;
    }

    $url = '/api/admin/teaching/course_works/'.$this->work->id;
    $offset = Http::recorded()->count();
    $response = $this->deleteJson($url);
    if ($scenario === 'catalog') {
        $response->assertOk()->assertJsonPath('skipped_unverified', true);
    } elseif ($scenario === 'changed') {
        $response->assertConflict();
        expect($this->work->fresh()->status['deletion_receipt']['initialized_folder']['status_file']['rev'])->toBe($status['rev']);
    } elseif ($scenario === 'lost response') {
        $response->assertConflict();
        expect($this->work->fresh()->status['deletion_receipt']['folders'][0]['id'])->toBe($folder['id']);
        $this->deleteJson($url)->assertNoContent();
    } else {
        $response->assertNoContent();
        expect(Http::recorded()->slice($offset))->toHaveCount(12);
    }
    $calls = Http::recorded()->slice($offset);
    $listings = $calls->filter(fn (array $call): bool => str_contains($call[0]->url(), '/files/list_folder'));
    expect($listings->every(fn (array $call): bool => ($call[0]['recursive'] ?? false) && $call[0]['path'] === $folder['id']))->toBeTrue();
    $downloads = $calls->filter(fn (array $call): bool => str_ends_with($call[0]->url(), '/files/download'));
    expect($downloads)->toHaveCount(1)
        ->and(json_decode($downloads->first()[0]->header('Dropbox-API-Arg')[0], true)['path'])->toBe('rev:'.$status['rev']);
    expect(collect($this->online)->contains('id', 'id:neighbor'))->toBeTrue();
    expect($this->deletedIds ?? [])->toBe(in_array($scenario, ['catalog', 'changed'], true) ? [] : [$folder['id']]);
})->with(['normal', 'catalog', 'changed', 'lost response']);

test('one deletion preserves shared resources after a verified sharing conflict', function (string $resource): void {
    $fixture = recursiveRootDeletionFixture($this);
    $other = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    if ($resource === 'folder') {
        TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $this->connection->id,
            'teaching_course_work_id' => $other->id, 'folder_id' => $fixture['root']['id']]);
    } else {
        $other->update(['status' => ['multiple_choice_tests' => ['all' => ['work_id' => $other->id, 'test_id' => $fixture['test']->id]]]]);
    }
    $url = '/api/admin/teaching/course_works/'.$this->work->id.'?stream_progress=1';
    $response = $this->deleteJson($url)->assertOk();
    $events = array_values(array_map(fn ($line) => json_decode($line, true), array_filter(explode("\n", $response->streamedContent()))));
    expect(end($events)['type'])->toBe('result')->and(end($events)['data']['deleted'])->toBeTrue()
        ->and(end($events)['data']['skipped_unverified'])->toBeTrue()
        ->and($this->work->fresh())->toBeNull()->and($other->fresh())->not->toBeNull()
        ->and($fixture['test']->fresh())->not->toBeNull()->and($fixture['test']->attempts()->count())->toBe(12)
        ->and(collect($this->online)->contains('id', $fixture['root']['id']))->toBeTrue()
        ->and($this->deletedIds ?? [])->toBe([]);
})->with(['folder', 'mc']);

test('a confirmed unfinished modern status exposes a partial folder for safe revalidation', function (): void {
    linkExerciseFixture($this);
    $path = addModernExerciseFixture($this);
    $record = ['schema_version' => 1, 'kind' => 'leistungsfeststellung.status',
        'schooltool' => ['work_id' => $this->work->id, 'course_id' => $this->course->id],
        'folder_group' => '3B_Gruppe-2', 'scheduled_date' => '2026-10-06', 'prepared' => false,
        'components' => ['aufgabenblatt' => ['included' => true, 'points' => 35],
            'multiple_choice' => ['included' => false, 'points' => null]], 'total_points' => 35];
    addEmailPreparationFile($this, $path.'/status.json', json_encode($record, JSON_THROW_ON_ERROR));
    $link = TeachingWorkModuleLink::query()->where('teaching_course_work_id', $this->work->id)->firstOrFail();
    $state = app(DropboxModuleLinks::class)->exercise($this->connection, $this->work->fresh('teachingCourse'), $link, '2026-10-06', refresh: true);
    expect($state['status'])->toBe('missing')->and($state['reusable'])->toBe('partial');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/files/upload') || str_contains($request->url(), '/files/delete'));
});
