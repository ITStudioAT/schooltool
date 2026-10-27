<?php

use App\Models\DropboxConnection;
use App\Models\McAttempt;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Services\DropboxWorkImport;
use App\Services\MultipleChoice\McImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();

uses(RefreshDatabase::class);

function mcTestFolderImportContext(): object
{
    Http::preventStrayRequests();
    Mail::fake();
    Queue::fake();
    Storage::fake('local');
    config(['schooltool.preview.instance' => false, 'app.url' => 'http://localhost:8000', 'services.dropbox.client_id' => 'fixture-client',
        'services.dropbox.client_secret' => 'fixture-secret', 'services.dropbox.redirect_uri' => 'http://localhost:8000/admin/teaching/dropbox/callback']);
    foreach (['teacher', 'student', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    enableSchoolToolModuleForTests($school, 'teaching');
    grantSchoolToolLicenceForTests($school, 'Lehrertool');
    $teacher = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id])->assignRole('teacher');
    $student = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id, 'is_active' => true,
        'schoolclass' => '3B', 'first_name' => 'Anna', 'last_name' => 'Muster', 'email' => 'current@example.test'])->assignRole('student');
    $course = TeachingCourse::factory()->forSchool($school)->forSchoolyear($year)->forTeacher($teacher)->create(['title' => 'INF - 3B']);
    $course->teachingCourseStudents()->create(['user_id' => $student->id]);
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $course->id, 'title' => 'E-Mails', 'date_for_all_groups' => '2026-10-21']);
    $connection = DropboxConnection::factory()->create(['user_id' => $teacher->id, 'credentials' => [
        'refresh_token' => 'actor-refresh-token', 'scopes' => DropboxWorkImport::SCOPES,
    ]]);
    $export = ['schema_version' => 1, 'kind' => 'schooltool.ai-work-export',
        'school' => ['id' => $school->id], 'schoolyear' => ['id' => $year->id],
        'work' => ['id' => $work->id, 'title' => $work->title, 'scheduled_date' => '2026-10-21'],
        'course' => ['id' => $course->id, 'title' => $course->title],
        'course_roster' => ['complete' => true, 'participants' => [[
            'schooltool_person_id' => (string) $student->id, 'first_name' => $student->first_name,
            'last_name' => $student->last_name, 'class_name' => '3B', 'email' => 'obsolete@example.test', 'active' => true,
        ]]]];
    $context = (object) compact('school', 'year', 'teacher', 'student', 'course', 'work', 'connection', 'export');
    $context->folder = ['.tag' => 'folder', 'id' => 'id:test-folder', 'name' => 'Testordner', 'path_lower' => '/tests/testordner', 'path_display' => '/Tests/Testordner'];
    $context->parent = ['.tag' => 'folder', 'id' => 'id:tests', 'name' => 'Tests', 'path_lower' => '/tests', 'path_display' => '/Tests'];
    $context->child = ['.tag' => 'folder', 'id' => 'id:subfolder', 'name' => 'Unterordner', 'path_lower' => '/tests/testordner/unterordner'];
    $context->file = ['.tag' => 'file', 'id' => 'id:source', 'name' => 'Schooltool_Arbeitsdaten.json',
        'path_lower' => '/tests/testordner/schooltool_arbeitsdaten.json', 'path_display' => '/Tests/Testordner/Schooltool_Arbeitsdaten.json', 'rev' => 'source-revision'];
    $context->missing = false;
    $context->downloadFailure = false;
    $context->race = null;
    $context->downloaded = false;
    $context->paginate = false;
    $context->handshakeFile = null;
    $context->handshakeDownloaded = false;
    $context->extraEntries = [];
    $context->extraDownloads = [];
    $context->searchCandidates = [];
    $context->searchMore = false;
    $context->sourceInChild = false;
    $context->manifestFile = null;
    mcTestFolderImportContents($context, json_encode($export, JSON_THROW_ON_ERROR));

    Http::fake(function (Request $request) use ($context) {
        if ($request->url() === 'https://api.dropboxapi.com/oauth2/token') {
            expect($request['refresh_token'])->toBe('actor-refresh-token');

            return Http::response(['access_token' => 'actor-access-token']);
        }
        if ($request->url() === 'https://api.dropboxapi.com/2/files/get_metadata') {
            foreach ([$context->folder, $context->parent, $context->child, $context->file,
                ...($context->handshakeFile ? [$context->handshakeFile] : []),
                ...($context->manifestFile ? [$context->manifestFile] : []), ...$context->extraEntries] as $entry) {
                if (in_array($request['path'], [$entry['id'], $entry['path_lower']], true)) {
                    if ($context->downloaded && $context->race === 'revision' && $entry['id'] === 'id:source') {
                        $entry['rev'] = 'concurrently-changed';
                    }
                    if ($context->downloaded && $context->race === 'folder' && $entry['id'] === 'id:test-folder') {
                        $entry['path_lower'] = '/moved/testordner';
                    }
                    if ($context->handshakeDownloaded && $context->race === 'handshake' && $entry['id'] === 'id:handshake') {
                        $entry['rev'] = 'handshake-changed-during-read';
                    }

                    return Http::response($entry);
                }
            }

            return Http::response(['error_summary' => 'path/not_found/'], 409);
        }
        if (in_array($request->url(), ['https://api.dropboxapi.com/2/files/search_v2', 'https://api.dropboxapi.com/2/files/search/continue_v2'], true)) {
            if (str_ends_with($request->url(), '/files/search_v2')) {
                expect($request['query'])->toBe($context->searchQuery ?? 'handshake.json');
                expect($request['options']['filename_only'])->toBeTrue();
            }

            return Http::response(['matches' => array_map(fn (array $entry): array => ['metadata' => ['metadata' => $entry]], $context->searchCandidates),
                'has_more' => $context->searchMore, 'cursor' => 'locator-search-cursor']);
        }
        if ($request->url() === 'https://api.dropboxapi.com/2/files/list_folder') {
            expect($request['recursive'])->toBeFalse();
            $entries = $request['path'] === '' ? [$context->folder]
                : [...($context->missing || $context->sourceInChild ? [] : [$context->file]), $context->child,
                    ...($context->handshakeFile ? [$context->handshakeFile] : []),
                    ...($context->manifestFile ? [$context->manifestFile] : []),
                    ...array_values(array_filter($context->extraEntries, fn (array $entry): bool => dirname($entry['path_lower']) === $context->folder['path_lower']))];
            if ($context->sourceInChild && $request['path'] === $context->child['id']) {
                $entries = $context->missing ? [] : [$context->file];
            }

            return Http::response(['entries' => $entries, 'has_more' => $context->paginate, 'cursor' => 'fixture-cursor']);
        }
        if ($request->url() === 'https://api.dropboxapi.com/2/files/list_folder/continue') {
            expect($request['cursor'])->toBe('fixture-cursor');

            return Http::response(['entries' => [$context->child], 'has_more' => false]);
        }
        if ($request->url() === 'https://content.dropboxapi.com/2/files/download') {
            $arguments = json_decode($request->header('Dropbox-API-Arg')[0], true, flags: JSON_THROW_ON_ERROR);
            if ($context->handshakeFile && $arguments['path'] === 'rev:'.$context->handshakeFile['rev']) {
                $context->handshakeDownloaded = true;

                return Http::response($context->handshakeContents);
            }
            if ($context->manifestFile && $arguments['path'] === 'rev:'.$context->manifestFile['rev']) {
                return Http::response($context->manifestContents);
            }
            if (isset($context->extraDownloads[$arguments['path']])) {
                return Http::response($context->extraDownloads[$arguments['path']]);
            }
            expect($arguments['path'])->toBe('rev:'.$context->file['rev']);
            if ($context->downloadFailure) {
                throw new ConnectionException('Simulated unavailable Dropbox download.');
            }
            $context->downloaded = true;

            return Http::response($context->contents, 200, ['Dropbox-API-Result' => json_encode($context->file, JSON_THROW_ON_ERROR)]);
        }

        throw new RuntimeException('Unexpected test folder import request: '.$request->url());
    });

    $context->work->refresh();
    $context->course->refresh();
    $context->student->refresh();

    return $context;
}

function mcTestFolderImportContents(object $context, string $contents): void
{
    $context->contents = $contents;
    $context->file['size'] = strlen($contents);
    $context->file['content_hash'] = hash('sha256', hash('sha256', $contents, true));
}

function mcTestFolderImportHandshake(object $context, array $identity, string $revision = 'handshake-revision'): void
{
    $context->handshakeContents = json_encode($identity, JSON_THROW_ON_ERROR);
    $context->handshakeFile = ['.tag' => 'file', 'id' => 'id:handshake', 'name' => 'handshake.json',
        'path_lower' => $context->folder['path_lower'].'/handshake.json', 'rev' => $revision,
        'size' => strlen($context->handshakeContents)];
}

function mcTestFolderImportLocator(object $context): array
{
    config(['services.dropbox.module_material_roots' => []]);
    $path = '/schule/materialien/lerninhalte/emails/leistungsfeststellung/testordner';
    $context->folder['path_lower'] = $path;
    $context->folder['path_display'] = $path;
    $context->parent['path_lower'] = dirname($path);
    $context->parent['path_display'] = dirname($path);
    $context->child['path_lower'] = $path.'/unterordner';
    $context->file['path_lower'] = $path.'/schooltool_arbeitsdaten.json';
    $context->file['path_display'] = $path.'/Schooltool_Arbeitsdaten.json';
    mcTestFolderImportHandshake($context, ['schema_version' => 1, 'kind' => 'schooltool.exercise_folder',
        'exercise_folder_id' => 'eeab9711-59ac-45ef-9c32-67f1414886c4']);
    $context->searchCandidates = [$context->handshakeFile];

    return ['filename' => 'handshake.json', 'handshake' => $context->handshakeContents];
}

test('a markdown file resolves its test folder only when the online content matches', function (): void {
    $context = mcTestFolderImportContext();
    mcTestFolderImportLocator($context);
    $context->searchQuery = 'Aufgabe.md';
    $hash = hash('sha256', hash('sha256', 'Aufgabe', true));
    $markdown = ['.tag' => 'file', 'id' => 'id:markdown', 'name' => 'Aufgabe.md',
        'path_lower' => $context->folder['path_lower'].'/aufgabe.md', 'content_hash' => $hash];
    $context->extraEntries[] = $markdown;
    $context->searchCandidates = [$markdown];
    $this->actingAs($context->teacher);

    $this->postJson('/api/admin/multiple-choice/test-folder/resolve', ['filename' => 'Aufgabe.md', 'content_hash' => $hash])
        ->assertOk()->assertJsonPath('folder_id', $context->folder['id']);
    $this->postJson('/api/admin/multiple-choice/test-folder/resolve', ['filename' => 'Aufgabe.md', 'content_hash' => str_repeat('0', 64)])
        ->assertUnprocessable();
});

test('a markdown file in multiple test folders is not linked ambiguously', function (): void {
    $context = mcTestFolderImportContext();
    mcTestFolderImportLocator($context);
    $context->searchQuery = 'Aufgabe.md';
    $hash = hash('sha256', hash('sha256', 'Aufgabe', true));
    $first = ['.tag' => 'file', 'id' => 'id:markdown', 'name' => 'Aufgabe.md',
        'path_lower' => $context->folder['path_lower'].'/aufgabe.md', 'content_hash' => $hash];
    $second = [...$first, 'id' => 'id:other-markdown', 'path_lower' => dirname($context->folder['path_lower']).'/another/aufgabe.md'];
    $context->extraEntries = [$first, $second];
    $context->searchCandidates = [$first, $second];
    $this->actingAs($context->teacher);

    $this->postJson('/api/admin/multiple-choice/test-folder/resolve', ['filename' => 'Aufgabe.md', 'content_hash' => $hash])
        ->assertConflict();
});

function mcTestFolderImportCanonicalSource(object $context): void
{
    $context->sourceInChild = true;
    $context->child['name'] = 'Schülerlisten';
    $context->child['path_lower'] = $context->folder['path_lower'].'/schülerlisten';
    $context->file['name'] = 'Quelle_Schooltool_Arbeitsdaten.json';
    $context->file['path_lower'] = $context->child['path_lower'].'/quelle_schooltool_arbeitsdaten.json';
    $context->file['path_display'] = $context->folder['path_display'].'/Schülerlisten/Quelle_Schooltool_Arbeitsdaten.json';
}

function mcTestFolderImportManifest(object $context, array $overrides = []): void
{
    $data = ['Schooltool_Arbeit_ID' => $context->work->id, 'Schooltool_Kurs_ID' => $context->course->id,
        'Schooltool_Schule_ID' => $context->school->id, 'Durchfuehrungsdatum' => '2026-10-21',
        'Arbeitsdaten' => 'C:\\Dropbox\\historischer-pfad\\Schülerlisten\\Quelle_Schooltool_Arbeitsdaten.json'];
    $context->manifestContents = json_encode([...$data, ...$overrides], JSON_THROW_ON_ERROR);
    $context->manifestFile = ['.tag' => 'file', 'id' => 'id:manifest', 'name' => 'Anlage_und_Quellen.json',
        'path_lower' => $context->folder['path_lower'].'/anlage_und_quellen.json', 'rev' => 'manifest-revision',
        'size' => strlen($context->manifestContents)];
}

function mcTestFolderImportAssertReadOnly(object $context): void
{
    expect(McCatalog::query()->count())->toBe(0);
    expect(McTest::query()->count())->toBe(0);
    expect($context->work->fresh()->getAttributes())->toEqual($context->work->getAttributes());
    expect($context->course->fresh()->getAttributes())->toEqual($context->course->getAttributes());
    expect($context->student->fresh()->getAttributes())->toEqual($context->student->getAttributes());
    Http::assertNotSent(fn (Request $request): bool => preg_match('~/files/(upload|delete|move|copy|create_folder)~', $request->url()) === 1);
}

function mcTestFolderImportTestInput(object $context, array $preview): array
{
    $catalog = McCatalog::query()->create(['school_id' => $context->school->id, 'owner_id' => $context->teacher->id,
        'title' => 'E-Mails', 'questions' => [['id' => 'q1', 'prompt' => 'Welche Daten bleiben geheim?', 'type' => 'single',
            'options' => [['id' => 'a', 'text' => 'Kennwort'], ['id' => 'b', 'text' => 'Schulname']],
            'correct_option_ids' => ['a'], 'active' => true, 'reviewed' => true]]]);

    $question = $catalog->questions[0];
    $catalog->update(['questions' => array_map(fn (int $number): array => [...$question, 'id' => 'q'.$number, 'prompt' => 'Frage '.$number], range(1, 10))]);

    return ['catalog_id' => $catalog->id, 'title' => 'Mein frei gewählter Testname', 'question_count' => 10,
        'total_points' => 2, 'duration_seconds' => 600, 'lead_seconds' => 30, 'balance_types' => false,
        'shuffle_questions' => true, 'shuffle_options' => true, 'roster_csv' => $preview['csv'],
        'test_folder_proof' => $preview['folder_proof']];
}

test('Dropbox test folder preview accepts a UTF8 BOM and keeps the existing roster preview behavior', function (): void {
    $context = mcTestFolderImportContext();
    $context->export['course_roster']['participants'][] = [...$context->export['course_roster']['participants'][0], 'active' => false];
    mcTestFolderImportContents($context, "\xEF\xBB\xBF".json_encode($context->export, JSON_THROW_ON_ERROR));
    $expected = app(McImportService::class)->workRosterPreview($context->export, $context->school->id);

    $response = $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])
        ->assertOk()->assertJsonPath('folder_id', 'id:test-folder')->assertJsonPath('file_id', 'id:source')
        ->assertJsonPath('revision', 'source-revision')->assertJsonPath('valid', true)
        ->assertJsonPath('rows.0.email', 'current@example.test')->assertJsonPath('rows.0.user_id', $context->student->id);

    foreach (['csv', 'rows', 'source', 'valid', 'errors', 'warnings'] as $key) {
        expect($response->json($key))->toEqual($expected[$key]);
    }
    expect($response->getContent())->not->toContain('actor-refresh-token', 'actor-access-token', 'obsolete@example.test');
    mcTestFolderImportAssertReadOnly($context);
});

test('Dropbox test folder endpoints require authentication before contacting Dropbox', function (string $method, string $endpoint): void {
    mcTestFolderImportContext();

    $this->json($method, '/api/admin/multiple-choice/test-folder/'.$endpoint, ['folder_id' => 'id:test-folder'])->assertUnauthorized();

    Http::assertNothingSent();
})->with(['folders' => ['GET', 'folders'], 'preview' => ['POST', 'preview']]);

test('Dropbox test folder endpoints reject a non-teaching account before contacting Dropbox', function (string $method, string $endpoint): void {
    $context = mcTestFolderImportContext();
    $context->teacher->syncRoles(['user']);

    $this->actingAs($context->teacher)->json($method, '/api/admin/multiple-choice/test-folder/'.$endpoint, ['folder_id' => 'id:test-folder'])->assertForbidden();

    Http::assertNothingSent();
})->with(['folders' => ['GET', 'folders'], 'preview' => ['POST', 'preview']]);

test('Dropbox test folder preview never uses another teachers connection', function (): void {
    $context = mcTestFolderImportContext();
    $other = User::factory()->create(['school_id' => $context->school->id, 'schoolyear_id' => $context->year->id])->assignRole('teacher');
    $context->connection->update(['user_id' => $other->id]);

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])->assertUnprocessable();

    Http::assertNothingSent();
    mcTestFolderImportAssertReadOnly($context);
});

test('Dropbox test folder preview rejects incompatible or incomplete work exports', function (string $field, mixed $value): void {
    $context = mcTestFolderImportContext();
    data_set($context->export, $field, $value);
    mcTestFolderImportContents($context, json_encode($context->export, JSON_THROW_ON_ERROR));

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])
        ->assertUnprocessable()->assertJsonMissingPath('csv');

    mcTestFolderImportAssertReadOnly($context);
})->with([
    'foreign school' => ['school.id', 999999999],
    'foreign schoolyear' => ['schoolyear.id', 999999999],
    'unsupported schema' => ['schema_version', 2],
    'wrong document kind' => ['kind', 'schooltool.assessment-results'],
    'incomplete roster' => ['course_roster.complete', false],
    'missing participants' => ['course_roster.participants', []],
]);

test('Dropbox test folder preview rejects a course owned by another teacher', function (): void {
    $context = mcTestFolderImportContext();
    $other = User::factory()->create(['school_id' => $context->school->id, 'schoolyear_id' => $context->year->id])->assignRole('teacher');
    $context->course->update(['user_id' => $other->id]);

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])
        ->assertNotFound()->assertJsonMissingPath('csv');

    mcTestFolderImportAssertReadOnly($context);
});

test('Dropbox test folder preview rejects a work from a different owned course', function (): void {
    $context = mcTestFolderImportContext();
    $otherCourse = TeachingCourse::factory()->forSchool($context->school)->forSchoolyear($context->year)->forTeacher($context->teacher)->create();
    $context->export['course']['id'] = $otherCourse->id;
    mcTestFolderImportContents($context, json_encode($context->export, JSON_THROW_ON_ERROR));

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])
        ->assertNotFound()->assertJsonMissingPath('csv');

    mcTestFolderImportAssertReadOnly($context);
});

test('Dropbox test folder preview rejects a missing work export without downloading unrelated files', function (): void {
    $context = mcTestFolderImportContext();
    $context->missing = true;

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])->assertUnprocessable();

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/files/download'));
    mcTestFolderImportAssertReadOnly($context);
});

test('Dropbox test folder preview rejects an oversized file before downloading it', function (): void {
    $context = mcTestFolderImportContext();
    $context->file['size'] = 1048577;

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])->assertUnprocessable();

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/files/download'));
    mcTestFolderImportAssertReadOnly($context);
});

test('Dropbox test folder preview rejects malformed bytes without a participant preview', function (string $contents): void {
    $context = mcTestFolderImportContext();
    mcTestFolderImportContents($context, $contents);

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])
        ->assertUnprocessable()->assertJsonMissingPath('csv');

    mcTestFolderImportAssertReadOnly($context);
})->with(['invalid JSON' => ['{"schema_version":'], 'invalid UTF8' => ["{\"title\":\"\xFF\"}"]]);

test('Dropbox test folder preview rejects an export outside the chosen folder', function (): void {
    $context = mcTestFolderImportContext();
    $context->file['path_lower'] = '/tests/testordner/unterordner/schooltool_arbeitsdaten.json';
    $context->file['path_display'] = '/Tests/Testordner/Unterordner/Schooltool_Arbeitsdaten.json';

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])->assertUnprocessable();

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/files/download'));
    mcTestFolderImportAssertReadOnly($context);
});

test('Dropbox test folder preview rejects a source changed during the download', function (string $race): void {
    $context = mcTestFolderImportContext();
    $context->race = $race;

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])
        ->assertConflict()->assertJsonMissingPath('csv');

    expect($context->downloaded)->toBeTrue();
    mcTestFolderImportAssertReadOnly($context);
})->with(['file revision changed' => ['revision'], 'folder moved' => ['folder']]);

test('Dropbox test folder preview reports an unavailable download without making changes', function (): void {
    $context = mcTestFolderImportContext();
    $context->downloadFailure = true;

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])
        ->assertUnprocessable()->assertJsonMissingPath('csv');

    mcTestFolderImportAssertReadOnly($context);
});

test('Dropbox test folder preview rejects an unknown work', function (): void {
    $context = mcTestFolderImportContext();
    $context->export['work']['id'] = 999999999;
    mcTestFolderImportContents($context, json_encode($context->export, JSON_THROW_ON_ERROR));

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])
        ->assertNotFound()->assertJsonMissingPath('csv');

    mcTestFolderImportAssertReadOnly($context);
});

test('Dropbox test folder preview reports unusable participant accounts with the existing roster rules', function (string $identity): void {
    $context = mcTestFolderImportContext();
    if ($identity === 'deactivated') {
        $context->student->forceFill(['is_active' => false])->save();
    } else {
        $foreign = User::factory()->create(['email' => 'private-foreign@example.test']);
        $context->export['course_roster']['participants'][0]['schooltool_person_id'] = $foreign->id;
    }
    mcTestFolderImportContents($context, json_encode($context->export, JSON_THROW_ON_ERROR));
    $expected = app(McImportService::class)->workRosterPreview($context->export, $context->school->id);

    $response = $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])
        ->assertOk()->assertJsonPath('valid', false)->assertJsonPath('rows.0.user_id', null);

    expect($response->json('rows'))->toEqual($expected['rows']);
    expect($response->getContent())->not->toContain('private-foreign@example.test');
    mcTestFolderImportAssertReadOnly($context);
})->with(['deactivated', 'foreign']);

test('Dropbox test folder selection lists only folders and uses a bound pagination cursor', function (): void {
    $context = mcTestFolderImportContext();
    $this->actingAs($context->teacher)->getJson('/api/admin/multiple-choice/test-folder/folders')
        ->assertOk()->assertJsonPath('folders.0.id', 'id:test-folder')->assertJsonPath('parent', null)->assertJsonPath('current', null);
    $context->paginate = true;
    $selected = $this->getJson('/api/admin/multiple-choice/test-folder/folders?'.http_build_query(['folder_id' => 'id:test-folder']))
        ->assertOk()->assertJsonPath('current.id', 'id:test-folder')->assertJsonPath('parent.id', 'id:tests')
        ->assertJsonCount(1, 'folders')->assertJsonPath('folders.0.id', 'id:subfolder');
    $cursor = $selected->json('cursor');
    expect($cursor)->toBeString()->not->toBe('fixture-cursor');

    $this->getJson('/api/admin/multiple-choice/test-folder/folders?'.http_build_query(['folder_id' => 'id:test-folder', 'cursor' => $cursor]))
        ->assertOk()->assertJsonPath('cursor', null)->assertJsonPath('folders.0.id', 'id:subfolder');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/files/download'));
    mcTestFolderImportAssertReadOnly($context);
});

test('Dropbox test folder selection rejects an invalid or stale cursor before another Dropbox request', function (string $change): void {
    $context = mcTestFolderImportContext();
    $context->paginate = true;
    $cursor = $this->actingAs($context->teacher)->getJson('/api/admin/multiple-choice/test-folder/folders?'.http_build_query(['folder_id' => 'id:test-folder']))
        ->assertOk()->json('cursor');
    $folderId = 'id:test-folder';
    if ($change === 'folder') {
        $folderId = 'id:subfolder';
    } elseif ($change === 'actor') {
        $other = User::factory()->create(['school_id' => $context->school->id, 'schoolyear_id' => $context->year->id])->assignRole('teacher');
        DropboxConnection::factory()->create(['user_id' => $other->id]);
        $this->actingAs($other);
    } elseif ($change === 'account') {
        $context->connection->update(['account_id' => 'dbid:different-account']);
    } elseif ($change === 'expired') {
        $this->travel(31)->minutes();
    } else {
        $cursor = 'fixture-cursor';
    }
    $requestsBefore = Http::recorded()->count();

    $this->getJson('/api/admin/multiple-choice/test-folder/folders?'.http_build_query(['folder_id' => $folderId, 'cursor' => $cursor]))->assertConflict();

    expect(Http::recorded()->count())->toBe($requestsBefore);
    mcTestFolderImportAssertReadOnly($context);
})->with(['folder', 'actor', 'account', 'expired', 'tampered']);

test('Dropbox test folder preview rejects a local path or missing folder selection before Dropbox access', function (string $folderId): void {
    $context = mcTestFolderImportContext();

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => $folderId])->assertUnprocessable();

    Http::assertNothingSent();
    mcTestFolderImportAssertReadOnly($context);
})->with(['local sync path' => ['C:\\Dropbox\\Testordner'], 'empty selection' => ['']]);

test('creating a test from a Dropbox preview persists its folder ID and survives a folder rename', function (bool $withHandshake): void {
    $context = mcTestFolderImportContext();
    $uuid = 'eeab9711-59ac-45ef-9c32-67f1414886c4';
    if ($withHandshake) {
        mcTestFolderImportHandshake($context, ['schema_version' => 1, 'kind' => 'schooltool.exercise_folder', 'exercise_folder_id' => $uuid]);
    }
    $preview = $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])->assertOk()->json();
    expect($preview['exercise_folder_id'])->toBe($withHandshake ? $uuid : null);
    $input = mcTestFolderImportTestInput($context, $preview);

    $created = $this->postJson('/api/admin/multiple-choice/tests', $input)->assertCreated()
        ->assertJsonPath('test.title', 'Mein frei gewählter Testname')->assertJsonPath('test.status', 'draft')
        ->assertJsonPath('folder_binding.folder.provider', 'dropbox')->assertJsonPath('folder_binding.folder.folder_id', 'id:test-folder');
    $testId = $created->json('test.id');
    expect(McTest::query()->count())->toBe(1);
    expect(McAttempt::query()->sole()->student_id)->toBe($context->student->id);
    expect(McAttempt::query()->sole()->questions)->toHaveCount(10);
    $context->folder['name'] = 'Umbenannter Testordner';
    $context->folder['path_lower'] = '/tests/umbenannter-testordner';
    $context->folder['path_display'] = '/Tests/Umbenannter-Testordner';
    if ($withHandshake) {
        $context->handshakeFile['path_lower'] = $context->folder['path_lower'].'/handshake.json';
    }

    $this->getJson('/api/admin/multiple-choice/tests/'.$testId.'/folder')->assertOk()
        ->assertJsonPath('folder.folder_id', 'id:test-folder')->assertJsonPath('folder.name', 'Umbenannter Testordner')
        ->assertJsonPath('folder.path', '/Tests/Umbenannter-Testordner');

    $bindings = Storage::disk('local')->allFiles('multiple-choice/test-folders');
    expect($bindings)->toHaveCount(1);
    if ($withHandshake) {
        $binding = json_decode(Storage::disk('local')->get($bindings[0]), true, flags: JSON_THROW_ON_ERROR);
        expect($binding['exercise_folder_identity']['exercise_folder_id'])->toBe($uuid);
        mcTestFolderImportHandshake($context, ['schema_version' => 1, 'kind' => 'schooltool.exercise_folder',
            'exercise_folder_id' => 'ec4894ac-868c-47e2-a153-5b7289c99909'], 'replaced-handshake');
        $this->getJson('/api/admin/multiple-choice/tests/'.$testId.'/folder')->assertConflict();
    }
    Http::assertNotSent(fn (Request $request): bool => preg_match('~/files/(upload|delete|move|copy|create_folder)~', $request->url()) === 1);
    Mail::assertNothingSent();
    Queue::assertNothingPushed();
})->with(['existing folder without a handshake' => [false], 'folder with a verified additional identity' => [true]]);

test('creating a test rejects changed or expired Dropbox preview evidence without publishing a test', function (string $change): void {
    $context = mcTestFolderImportContext();
    $preview = $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])->assertOk()->json();
    $input = mcTestFolderImportTestInput($context, $preview);
    if ($change === 'revision') {
        $context->file['rev'] = 'changed-after-preview';
    } elseif ($change === 'csv') {
        $input['roster_csv'] = str_replace('INF - 3B', 'Anderer Kursname', $input['roster_csv']);
    } elseif ($change === 'expired') {
        $this->travel(31)->minutes();
    } elseif ($change === 'account') {
        $context->connection->update(['account_id' => 'dbid:changed-account']);
    } else {
        $input['test_folder_proof'] = 'invalid-proof';
    }

    $this->postJson('/api/admin/multiple-choice/tests', $input)->assertConflict();

    expect(McTest::query()->count())->toBe(0);
    expect(McAttempt::query()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles('multiple-choice/test-folders'))->toBe([]);
    Http::assertNotSent(fn (Request $request): bool => preg_match('~/files/(upload|delete|move|copy|create_folder)~', $request->url()) === 1);
    Mail::assertNothingSent();
    Queue::assertNothingPushed();
})->with(['revision', 'csv', 'expired', 'account', 'invalid proof']);

test('creating a test rolls back publication and participants if its Dropbox binding cannot be persisted', function (): void {
    $context = mcTestFolderImportContext();
    $preview = $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])->assertOk()->json();
    $input = mcTestFolderImportTestInput($context, $preview);
    $disk = Mockery::mock(Storage::disk('local'));
    $disk->shouldReceive('put')->withArgs(fn (string $path, mixed $contents): bool => str_starts_with($path, 'multiple-choice/test-folders/'))->once()->andReturnFalse();
    Storage::set('local', $disk);

    $this->postJson('/api/admin/multiple-choice/tests', $input)->assertUnprocessable();

    expect(McTest::query()->count())->toBe(0);
    expect(McAttempt::query()->count())->toBe(0);
    expect($disk->allFiles('multiple-choice/test-folders'))->toBe([]);
    Http::assertNotSent(fn (Request $request): bool => preg_match('~/files/(upload|delete|move|copy|create_folder)~', $request->url()) === 1);
    Mail::assertNothingSent();
    Queue::assertNothingPushed();
});

test('Dropbox test folder preview rejects an invalid identity or a learning module handshake', function (array $identity): void {
    $context = mcTestFolderImportContext();
    mcTestFolderImportHandshake($context, $identity);

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])
        ->assertUnprocessable()->assertJsonMissingPath('folder_proof');

    mcTestFolderImportAssertReadOnly($context);
})->with([
    'invalid UUID' => [['schema_version' => 1, 'kind' => 'schooltool.exercise_folder', 'exercise_folder_id' => 'invalid']],
    'module identity' => [['schema_version' => 1, 'module_id' => 'eeab9711-59ac-45ef-9c32-67f1414886c4']],
]);

test('creating a test rejects a changed optional folder identity before publication', function (): void {
    $context = mcTestFolderImportContext();
    mcTestFolderImportHandshake($context, ['schema_version' => 1, 'kind' => 'schooltool.exercise_folder',
        'exercise_folder_id' => 'eeab9711-59ac-45ef-9c32-67f1414886c4']);
    $preview = $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])->assertOk()->json();
    $input = mcTestFolderImportTestInput($context, $preview);
    mcTestFolderImportHandshake($context, ['schema_version' => 1, 'kind' => 'schooltool.exercise_folder',
        'exercise_folder_id' => 'ec4894ac-868c-47e2-a153-5b7289c99909'], 'changed-handshake');

    $this->postJson('/api/admin/multiple-choice/tests', $input)->assertConflict();

    expect(McTest::query()->count())->toBe(0);
    expect(McAttempt::query()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles('multiple-choice/test-folders'))->toBe([]);
    Http::assertNotSent(fn (Request $request): bool => preg_match('~/files/(upload|delete|move|copy|create_folder)~', $request->url()) === 1);
});

test('a local test folder locator resolves one online identity and produces a usable bound preview', function (): void {
    $context = mcTestFolderImportContext();
    $locator = mcTestFolderImportLocator($context);

    $preview = $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/resolve', $locator)
        ->assertOk()->assertJsonPath('folder_id', 'id:test-folder')->assertJsonPath('file_id', 'id:source')
        ->assertJsonPath('revision', 'source-revision')->assertJsonPath('exercise_folder_id', 'eeab9711-59ac-45ef-9c32-67f1414886c4')
        ->assertJsonPath('rows.0.email', 'current@example.test')->json();
    expect($preview['folder_proof'])->toBeString()->not->toBeEmpty();
    mcTestFolderImportAssertReadOnly($context);

    $this->postJson('/api/admin/multiple-choice/tests', mcTestFolderImportTestInput($context, $preview))
        ->assertCreated()->assertJsonPath('folder_binding.folder.folder_id', 'id:test-folder');

    expect(McTest::query()->count())->toBe(1);
    Http::assertNotSent(fn (Request $request): bool => preg_match('~/files/(upload|delete|move|copy|create_folder)~', $request->url()) === 1);
});

test('a local test folder locator cannot create a preview from an absent or incomplete online match', function (string $case): void {
    $context = mcTestFolderImportContext();
    $locator = mcTestFolderImportLocator($context);
    if ($case === 'none') {
        $context->searchCandidates = [];
    } elseif ($case === 'different UUID') {
        mcTestFolderImportHandshake($context, ['schema_version' => 1, 'kind' => 'schooltool.exercise_folder',
            'exercise_folder_id' => 'ec4894ac-868c-47e2-a153-5b7289c99909']);
    } else {
        $context->searchMore = true;
    }

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/resolve', $locator)
        ->assertUnprocessable()->assertJsonMissingPath('folder_proof');

    mcTestFolderImportAssertReadOnly($context);
})->with(['none', 'different UUID', 'incomplete search']);

test('a local test folder locator reports a duplicated online UUID instead of choosing a folder', function (): void {
    $context = mcTestFolderImportContext();
    $locator = mcTestFolderImportLocator($context);
    $duplicateFolder = [...$context->folder, 'id' => 'id:duplicate-folder', 'name' => 'Zweiter Testordner',
        'path_lower' => dirname($context->folder['path_lower']).'/zweiter-testordner', 'path_display' => dirname($context->folder['path_lower']).'/zweiter-testordner'];
    $duplicateFile = [...$context->handshakeFile, 'id' => 'id:duplicate-handshake', 'rev' => 'duplicate-handshake-revision',
        'path_lower' => $duplicateFolder['path_lower'].'/handshake.json'];
    $context->extraEntries = [$duplicateFolder, $duplicateFile];
    $context->extraDownloads['rev:duplicate-handshake-revision'] = $context->handshakeContents;
    $context->searchCandidates[] = $duplicateFile;

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/resolve', $locator)
        ->assertConflict()->assertJsonMissingPath('folder_proof');

    mcTestFolderImportAssertReadOnly($context);
});

test('a local test folder locator rejects an online identity changed while it is read', function (): void {
    $context = mcTestFolderImportContext();
    $locator = mcTestFolderImportLocator($context);
    $context->race = 'handshake';

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/resolve', $locator)
        ->assertConflict()->assertJsonMissingPath('folder_proof');

    mcTestFolderImportAssertReadOnly($context);
});

test('a local test folder locator rejects a wrong schema before contacting Dropbox', function (): void {
    $context = mcTestFolderImportContext();
    $locator = mcTestFolderImportLocator($context);
    $locator['handshake'] = json_encode(['schema_version' => 1, 'module_id' => 'eeab9711-59ac-45ef-9c32-67f1414886c4'], JSON_THROW_ON_ERROR);

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/resolve', $locator)
        ->assertUnprocessable()->assertJsonMissingPath('folder_proof');

    Http::assertNothingSent();
    mcTestFolderImportAssertReadOnly($context);
});

test('a local test folder locator rejects duplicate identity keys before contacting Dropbox', function (string $duplicateKey): void {
    $context = mcTestFolderImportContext();
    $locator = mcTestFolderImportLocator($context);
    $locator['handshake'] = '{"schema_version":1,"kind":"schooltool.exercise_folder",'
        .'"exercise_folder_id":"ec4894ac-868c-47e2-a153-5b7289c99909",'
        .'"'.$duplicateKey.'":"eeab9711-59ac-45ef-9c32-67f1414886c4"}';

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/resolve', $locator)
        ->assertUnprocessable()->assertJsonMissingPath('folder_proof');

    Http::assertNothingSent();
    mcTestFolderImportAssertReadOnly($context);
})->with([
    'plain duplicate key' => ['exercise_folder_id'],
    'Unicode escaped duplicate key' => ['exercise_folder_\u0069d'],
]);

test('a local test folder locator ignores matching identities outside the permitted material paths', function (): void {
    $context = mcTestFolderImportContext();
    $locator = mcTestFolderImportLocator($context);
    $context->handshakeFile['path_lower'] = '/private/unrelated/handshake.json';
    $context->searchCandidates = [$context->handshakeFile];

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/resolve', $locator)
        ->assertUnprocessable()->assertJsonMissingPath('folder_proof');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/files/download'));
    mcTestFolderImportAssertReadOnly($context);
});

test('a local test folder locator never searches a different teachers Dropbox account', function (): void {
    $context = mcTestFolderImportContext();
    $locator = mcTestFolderImportLocator($context);
    $other = User::factory()->create(['school_id' => $context->school->id, 'schoolyear_id' => $context->year->id]);
    $context->connection->update(['user_id' => $other->id]);

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/resolve', $locator)
        ->assertUnprocessable()->assertJsonMissingPath('folder_proof');

    Http::assertNothingSent();
    mcTestFolderImportAssertReadOnly($context);
});

test('Dropbox test folder import reads the canonical student list source and validates its manifest', function (): void {
    $context = mcTestFolderImportContext();
    mcTestFolderImportCanonicalSource($context);
    mcTestFolderImportManifest($context);

    $preview = $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])
        ->assertOk()->assertJsonPath('file_id', 'id:source')->assertJsonPath('revision', 'source-revision')
        ->assertJsonPath('rows.0.email', 'current@example.test')->assertJsonPath('valid', true)->json();
    mcTestFolderImportAssertReadOnly($context);

    $this->postJson('/api/admin/multiple-choice/tests', mcTestFolderImportTestInput($context, $preview))
        ->assertCreated()->assertJsonPath('folder_binding.folder.folder_id', 'id:test-folder');

    Http::assertNotSent(fn (Request $request): bool => preg_match('~/files/(upload|delete|move|copy|create_folder)~', $request->url()) === 1);
});

test('Dropbox test folder import rejects a manifest contradicting the work source', function (string $field, mixed $value): void {
    $context = mcTestFolderImportContext();
    mcTestFolderImportCanonicalSource($context);
    mcTestFolderImportManifest($context, [$field => $value]);

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])
        ->assertUnprocessable()->assertJsonMissingPath('folder_proof');

    mcTestFolderImportAssertReadOnly($context);
})->with([
    'different work' => ['Schooltool_Arbeit_ID', 999999999],
    'different course' => ['Schooltool_Kurs_ID', 999999999],
    'different school' => ['Schooltool_Schule_ID', 999999999],
    'different scheduled date' => ['Durchfuehrungsdatum', '2026-10-22'],
]);

test('creating a test rejects a manifest revision changed after its canonical source preview', function (): void {
    $context = mcTestFolderImportContext();
    mcTestFolderImportCanonicalSource($context);
    mcTestFolderImportManifest($context);
    $preview = $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])->assertOk()->json();
    $input = mcTestFolderImportTestInput($context, $preview);
    $context->manifestFile['rev'] = 'manifest-changed-after-preview';

    $this->postJson('/api/admin/multiple-choice/tests', $input)->assertConflict();

    expect(McTest::query()->count())->toBe(0);
    expect(McAttempt::query()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles('multiple-choice/test-folders'))->toBe([]);
});

test('Dropbox test folder import refuses ambiguous root and canonical student list sources', function (): void {
    $context = mcTestFolderImportContext();
    $rootSource = [...$context->file, 'id' => 'id:root-source'];
    mcTestFolderImportCanonicalSource($context);
    $context->extraEntries = [$rootSource];

    $this->actingAs($context->teacher)->postJson('/api/admin/multiple-choice/test-folder/preview', ['folder_id' => 'id:test-folder'])
        ->assertUnprocessable()->assertJsonMissingPath('folder_proof');

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/files/download'));
    mcTestFolderImportAssertReadOnly($context);
});
