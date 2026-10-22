<?php

use App\Models\DropboxConnection;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\DropboxWorkImport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();

uses(RefreshDatabase::class);

function mcTemplateSyncContext(): object
{
    Http::preventStrayRequests();
    Storage::fake('local');
    config([
        'app.url' => 'http://localhost:8000',
        'services.dropbox.client_id' => 'test-client',
        'services.dropbox.client_secret' => 'test-secret',
        'services.dropbox.redirect_uri' => 'http://localhost:8000/admin/teaching/dropbox/callback',
        'schooltool.preview.instance' => false,
    ]);
    Role::findOrCreate('teacher', 'web');
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    enableSchoolToolModuleForTests($school, 'teaching');
    grantSchoolToolLicenceForTests($school, 'Lehrertool');
    $teacher = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id])->assignRole('teacher');
    $connection = DropboxConnection::factory()->create([
        'user_id' => $teacher->id,
        'credentials' => ['refresh_token' => 'fixture-refresh', 'scopes' => [...DropboxWorkImport::SCOPES, 'files.content.write']],
    ]);
    $catalog = McCatalog::query()->create([
        'school_id' => $school->id, 'owner_id' => $teacher->id, 'title' => 'Fragenvorlage', 'questions' => [],
    ]);
    $handshake = '{"schema_version":1,"module_id":"a263b873-4f27-4167-90f0-7d94ea0f67fa"}';
    $contents = "---\ntitle: Fragenvorlage\nfach: Informatik\n---\n\n### 1. Wo werden empfangene Nachrichten angezeigt?\n\n- [x] A) Posteingang\n- [ ] B) Entwürfe\n\n### 2. Welche Daten bleiben geheim?\n\n- [x] A) Kennwort\n- [ ] B) Schulname\n";
    $root = '/school/materialien/lerninhalte';
    $context = (object) [
        'teacher' => $teacher, 'connection' => $connection, 'catalog' => $catalog,
        'handshake' => $handshake, 'contents' => $contents, 'uploads' => [], 'timeout' => false, 'uploadConflict' => false,
        'url' => "/api/admin/multiple-choice/catalogs/{$catalog->id}",
        'entries' => [
            ['.tag' => 'folder', 'id' => 'id:root', 'name' => 'Lerninhalte', 'path_lower' => $root],
            ['.tag' => 'folder', 'id' => 'id:module', 'name' => 'E-Mails', 'path_lower' => "{$root}/emails"],
            ['.tag' => 'folder', 'id' => 'id:mc', 'name' => 'Multiple Choice Fragen', 'path_lower' => "{$root}/emails/multiple choice fragen"],
            ['.tag' => 'file', 'id' => 'id:handshake', 'name' => 'handshake.json', 'path_lower' => "{$root}/emails/handshake.json", 'rev' => 'handshake-rev', 'size' => strlen($handshake)],
            ['.tag' => 'file', 'id' => 'id:questions', 'name' => 'Fragen.md', 'path_lower' => "{$root}/emails/multiple choice fragen/fragen.md", 'rev' => 'source-rev', 'size' => strlen($contents)],
        ],
    ];

    Http::fake(function (Request $request) use ($context) {
        if ($request->url() === 'https://api.dropboxapi.com/oauth2/token') {
            return Http::response(['access_token' => 'fixture-access']);
        }
        if ($request->url() === 'https://api.dropboxapi.com/2/files/search_v2') {
            return Http::response(['matches' => [['metadata' => ['metadata' => $context->entries[3]]]], 'has_more' => false]);
        }
        if ($request->url() === 'https://api.dropboxapi.com/2/files/get_metadata') {
            foreach ($context->entries as $entry) {
                if ($entry['id'] === $request['path'] || $entry['path_lower'] === $request['path']) {
                    return Http::response($entry);
                }
            }
        }
        if ($request->url() === 'https://api.dropboxapi.com/2/files/list_folder') {
            $folder = collect($context->entries)->firstWhere('id', $request['path']);

            return Http::response(['entries' => array_values(array_filter($context->entries,
                fn (array $entry): bool => dirname($entry['path_lower']) === $folder['path_lower'])), 'has_more' => false]);
        }
        if ($request->url() === 'https://content.dropboxapi.com/2/files/download') {
            $arguments = json_decode($request->header('Dropbox-API-Arg')[0], true);
            if (in_array($arguments['path'], ['id:handshake', 'rev:handshake-rev'], true)) {
                return Http::response($context->handshake);
            }
            if (in_array($arguments['path'], ['id:questions', 'rev:'.$context->entries[4]['rev']], true)) {
                return Http::response($context->contents);
            }
        }
        if ($request->url() === 'https://content.dropboxapi.com/2/files/upload') {
            $arguments = json_decode($request->header('Dropbox-API-Arg')[0], true);
            expect($arguments['path'])->toBe('id:questions');
            expect($arguments['mode'])->toEqual(['.tag' => 'update', 'update' => $context->entries[4]['rev']]);
            expect($arguments['autorename'])->toBeFalse();
            expect($arguments['strict_conflict'])->toBeTrue();
            if ($context->uploadConflict) {
                $context->uploadConflict = false;
                mcTemplateSyncRemoteChange($context, str_replace('Wo werden empfangene Nachrichten angezeigt?', 'Gleichzeitig extern bearbeitete Frage', $context->contents), 'conflicting-revision');

                return Http::response(['error_summary' => 'path/conflict/file/'], 409);
            }
            $context->uploads[] = $request->body();
            mcTemplateSyncRemoteChange($context, $request->body(), 'uploaded-'.count($context->uploads));
            if ($context->timeout) {
                throw new ConnectionException('The upload response was lost.');
            }

            return Http::response($context->entries[4]);
        }

        throw new RuntimeException('Unexpected template synchronization request: '.$request->url());
    });

    return $context;
}

function mcTemplateSyncRemoteChange(object $context, string $contents, string $revision): void
{
    $context->contents = $contents;
    $context->entries[4]['rev'] = $revision;
    $context->entries[4]['size'] = strlen($contents);
    $context->entries[4]['content_hash'] = hash('sha256', hash('sha256', $contents, true));
}

function mcTemplateSyncImport(object $test, object $context): void
{
    $test->actingAs($context->teacher, 'sanctum');
    $preview = $test->postJson('/api/admin/multiple-choice/import-preview', [
        'filename' => 'handshake.json', 'handshake' => $context->handshake,
        'catalog_id' => $context->catalog->id, 'generate' => false,
    ])->assertOk()->json();
    $context->sourceProof = $preview['source_proof'];
    $context->version = $test->patchJson($context->url, [
        'title' => $context->catalog->title, 'questions' => $preview['preview']['questions'],
        'module_source_proofs' => [$preview['source_proof']],
    ])->assertOk()->json('catalog.template_version');
    $context->catalog->refresh();
}

function mcTemplateSyncConnect(object $test, object $context): void
{
    mcTemplateSyncImport($test, $context);
    $preview = $test->postJson($context->url.'/sync-preview')->assertOk()->assertJsonPath('can_apply', true)->json();
    $context->version = $test->postJson($context->url.'/sync-apply', ['token' => $preview['token']])
        ->assertOk()->json('catalog.template_version');
    $context->catalog->refresh();
}

function mcTemplateSyncAdditionalQuestion(): array
{
    return ['id' => 'local-added-question', 'type' => 'single', 'prompt' => 'Welche Anwendung bearbeitet E-Mails?',
        'options' => [['id' => 'local-a', 'text' => 'Outlook'], ['id' => 'local-b', 'text' => 'Taschenrechner']],
        'correct_option_ids' => ['local-a'], 'active' => true, 'reviewed' => true];
}

test('template synchronization previews a local addition without saving and applies it only after confirmation', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncImport($this, $context);
    $before = $context->catalog->getAttributes();
    $sourceBefore = $context->contents;
    $questions = [...$context->catalog->questions, mcTemplateSyncAdditionalQuestion()];

    $preview = $this->postJson($context->url.'/sync-preview', ['title' => 'Geänderter Entwurf', 'questions' => $questions, 'expected_version' => $context->version])
        ->assertOk()->assertJsonPath('can_apply', true)->assertJsonPath('initial_connection', true)
        ->assertJsonCount(1, 'source_changes.added')->json();

    expect($context->catalog->fresh()->getAttributes())->toEqual($before);
    expect($context->contents)->toBe($sourceBefore);
    expect($context->uploads)->toBe([]);

    $this->postJson($context->url.'/sync-apply', ['token' => $preview['token']])
        ->assertOk()->assertJsonPath('catalog.title', 'Geänderter Entwurf')
        ->assertJsonPath('catalog.template_connection.status', 'connected')->assertJsonCount(3, 'catalog.questions');

    expect($context->catalog->fresh()->questions)->toHaveCount(3);
    expect($context->contents)->toContain('Welche Anwendung bearbeitet E-Mails?', '<!-- schooltool-mc:');
    expect($context->uploads)->toHaveCount(1);
});

test('template synchronization imports remote additions without modifying a published test snapshot', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncConnect($this, $context);
    $snapshot = ['questions' => $context->catalog->questions];
    $published = McTest::query()->create([
        'school_id' => $context->teacher->school_id, 'schoolyear_id' => $context->teacher->schoolyear_id,
        'owner_id' => $context->teacher->id, 'catalog_id' => $context->catalog->id, 'title' => 'Freigegebener Test',
        'status' => 'published', 'question_count' => 2, 'total_points' => 2, 'duration_seconds' => 300,
        'lead_seconds' => 0, 'balance_types' => false, 'shuffle_questions' => false, 'shuffle_options' => false,
        'snapshot' => $snapshot,
    ]);
    $testBefore = $published->fresh()->getAttributes();
    mcTemplateSyncRemoteChange($context, $context->contents."\n### 3. Wo stehen gesendete E-Mails?\n\n- [x] A) Gesendete Elemente\n- [ ] B) Posteingang\n", 'remote-addition');

    $preview = $this->postJson($context->url.'/sync-preview')->assertOk()
        ->assertJsonPath('can_apply', true)->assertJsonCount(1, 'catalog_changes.added')->json();
    $this->postJson($context->url.'/sync-apply', ['token' => $preview['token']])->assertOk();

    expect($context->catalog->fresh()->questions)->toHaveCount(3);
    expect(array_column($context->catalog->fresh()->questions, 'prompt'))->toContain('Wo stehen gesendete E-Mails?');
    expect($published->fresh()->getAttributes())->toEqual($testBefore);
});

test('template synchronization blocks remote question order changes without saving or uploading', function (string $change): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncConnect($this, $context);
    $before = $context->catalog->getAttributes();
    $uploads = $context->uploads;
    $sections = preg_split('/(?=^### \d+\. )/m', $context->contents);
    expect($sections)->toHaveCount(3);

    $remote = $change === 'reorder'
        ? $sections[0].preg_replace('/^### 2\./', '### 1.', $sections[2])."\n".preg_replace('/^### 1\./', '### 2.', $sections[1])
        : $sections[0].$sections[1]."\n### 2. Wo stehen gesendete E-Mails?\n\n- [x] A) Gesendete Elemente\n- [ ] B) Posteingang\n\n".preg_replace('/^### 2\./', '### 3.', $sections[2]);
    mcTemplateSyncRemoteChange($context, $remote, 'remote-order-'.$change);

    $response = $this->postJson($context->url.'/sync-preview')->assertOk()
        ->assertJsonPath('can_apply', false)->assertJsonPath('token', null);

    expect(implode(' ', $response->json('conflicts')))->toContain('Reihenfolge');
    expect($context->catalog->fresh()->getAttributes())->toEqual($before);
    expect($context->contents)->toBe($remote);
    expect($context->uploads)->toBe($uploads);
})->with([
    'existing questions reordered remotely' => ['reorder'],
    'new question inserted between existing questions remotely' => ['insert'],
]);

test('template synchronization reports divergent edits to the same question without a usable apply token', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncConnect($this, $context);
    $before = $context->catalog->getAttributes();
    $questions = $context->catalog->questions;
    $questions[0]['prompt'] = 'Lokal bearbeitete Frage';
    mcTemplateSyncRemoteChange($context, str_replace('Wo werden empfangene Nachrichten angezeigt?', 'Extern bearbeitete Frage', $context->contents), 'remote-conflict');
    $uploadsBefore = $context->uploads;

    $response = $this->postJson($context->url.'/sync-preview', ['title' => $context->catalog->title, 'questions' => $questions, 'expected_version' => $context->version])
        ->assertOk()->assertJsonPath('can_apply', false)->assertJsonPath('token', null);

    expect($response->json('conflicts'))->not->toBeEmpty();
    expect($context->catalog->fresh()->getAttributes())->toEqual($before);
    expect($context->uploads)->toBe($uploadsBefore);
});

test('template synchronization returns 409 when the remote revision changes after preview without uploading', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncImport($this, $context);
    $before = $context->catalog->getAttributes();
    $preview = $this->postJson($context->url.'/sync-preview')->assertOk()->json();
    mcTemplateSyncRemoteChange($context, str_replace('Wo werden empfangene Nachrichten angezeigt?', 'Extern bearbeitete Frage', $context->contents), 'changed-after-preview');

    $this->postJson($context->url.'/sync-apply', ['token' => $preview['token']])->assertConflict();

    expect($context->catalog->fresh()->getAttributes())->toEqual($before);
    expect($context->uploads)->toBe([]);
});

test('template synchronization returns 409 when the catalog changes after preview without overwriting that change', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncImport($this, $context);
    $preview = $this->postJson($context->url.'/sync-preview')->assertOk()->json();
    $context->catalog->update(['title' => 'Parallel gespeicherter Titel']);
    $before = $context->catalog->fresh()->getAttributes();

    $this->postJson($context->url.'/sync-apply', ['token' => $preview['token']])->assertConflict();

    expect($context->catalog->fresh()->getAttributes())->toEqual($before);
    expect($context->uploads)->toBe([]);
});

test('template synchronization returns 422 when the Dropbox connection has no write permission', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncImport($this, $context);
    $context->connection->update(['credentials' => ['refresh_token' => 'fixture-refresh', 'scopes' => DropboxWorkImport::SCOPES]]);
    $before = $context->catalog->getAttributes();

    $this->postJson($context->url.'/sync-preview')->assertUnprocessable();

    expect($context->catalog->fresh()->getAttributes())->toEqual($before);
    expect($context->uploads)->toBe([]);
});

test('template synchronization returns 404 for another owner before any Dropbox request', function (string $method, string $suffix): void {
    $context = mcTemplateSyncContext();
    $other = User::factory()->create(['school_id' => $context->teacher->school_id]);
    $context->catalog->update(['owner_id' => $other->id]);
    $this->actingAs($context->teacher, 'sanctum');

    $this->json($method, $context->url.$suffix, ['token' => 'foreign-token'])->assertNotFound();

    Http::assertNothingSent();
})->with([
    'preview' => ['POST', '/sync-preview'],
    'apply' => ['POST', '/sync-apply'],
    'disconnect' => ['DELETE', '/template-connection'],
]);

test('template synchronization returns 401 without authentication before any Dropbox request', function (string $method, string $suffix): void {
    $context = mcTemplateSyncContext();

    $this->json($method, $context->url.$suffix, ['token' => 'untrusted-token'])->assertUnauthorized();

    Http::assertNothingSent();
})->with([
    'preview' => ['POST', '/sync-preview'],
    'apply' => ['POST', '/sync-apply'],
    'disconnect' => ['DELETE', '/template-connection'],
]);

test('template synchronization returns 422 for an invalid draft without saving or uploading it', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncImport($this, $context);
    $before = $context->catalog->getAttributes();

    $this->postJson($context->url.'/sync-preview', ['title' => $context->catalog->title, 'questions' => 'invalid', 'expected_version' => $context->version])
        ->assertUnprocessable()->assertJsonValidationErrors('questions');

    expect($context->catalog->fresh()->getAttributes())->toEqual($before);
    expect($context->uploads)->toBe([]);
});

test('template synchronization returns 404 when a preview token belongs to another catalog', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncImport($this, $context);
    $preview = $this->postJson($context->url.'/sync-preview')->assertOk()->json();
    $other = McCatalog::query()->create(['school_id' => $context->teacher->school_id, 'owner_id' => $context->teacher->id,
        'title' => 'Anderer Katalog', 'questions' => []]);

    $this->postJson("/api/admin/multiple-choice/catalogs/{$other->id}/sync-apply", ['token' => $preview['token']])->assertNotFound();

    expect($other->fresh()->questions)->toBe([]);
    expect($context->uploads)->toBe([]);
});

test('disconnecting a template keeps the catalog questions and remote document unchanged', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncConnect($this, $context);
    $questions = $context->catalog->questions;
    $contents = $context->contents;
    $uploads = $context->uploads;

    $this->deleteJson($context->url.'/template-connection', ['recovery_token' => 'invalid'])->assertConflict();
    $this->deleteJson($context->url.'/template-connection')->assertOk()->assertJsonPath('catalog.template_connection', null);

    expect($context->catalog->fresh()->questions)->toEqual($questions);
    expect($context->contents)->toBe($contents);
    expect($context->uploads)->toBe($uploads);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/files/delete'));
});

test('an unconfirmed upload returns 422 and does not save the catalog even if Dropbox received the document', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncImport($this, $context);
    $before = $context->catalog->getAttributes();
    $questions = [...$context->catalog->questions, mcTemplateSyncAdditionalQuestion()];
    $preview = $this->postJson($context->url.'/sync-preview', ['title' => $context->catalog->title, 'questions' => $questions, 'expected_version' => $context->version])
        ->assertOk()->json();
    $context->timeout = true;

    $this->postJson($context->url.'/sync-apply', ['token' => $preview['token']])->assertUnprocessable();

    expect($context->catalog->fresh()->getAttributes())->toEqual($before);
    expect($context->contents)->toContain('Welche Anwendung bearbeitet E-Mails?');
    expect($context->uploads)->toHaveCount(1);
});

test('an accepted upload with a lost response resumes without uploading the same document again', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncImport($this, $context);
    $questions = [...$context->catalog->questions, mcTemplateSyncAdditionalQuestion()];
    $preview = $this->postJson($context->url.'/sync-preview', ['title' => $context->catalog->title, 'questions' => $questions, 'expected_version' => $context->version])
        ->assertOk()->json();
    $context->timeout = true;
    $this->postJson($context->url.'/sync-apply', ['token' => $preview['token']])->assertUnprocessable();
    $context->timeout = false;

    $this->deleteJson($context->url, ['test_ids' => []])->assertConflict();
    $this->assertModelExists($context->catalog);
    $this->postJson($context->url.'/sync-preview', ['title' => 'Weiter bearbeiteter Entwurf', 'questions' => $questions, 'expected_version' => $context->version])
        ->assertConflict();
    $resuming = $this->postJson($context->url.'/sync-preview')->assertOk()->assertJsonPath('resuming', true)->json();
    $this->postJson($context->url.'/sync-apply', ['token' => $resuming['token']])
        ->assertOk()->assertJsonPath('catalog.template_connection.status', 'connected');

    expect($context->catalog->fresh()->questions)->toHaveCount(3);
    expect($context->uploads)->toHaveCount(1);
});

test('an actively connected catalog requires the current version when saving ordinary edits', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncConnect($this, $context);
    $payload = ['title' => 'Lokal gespeicherter Titel', 'questions' => $context->catalog->questions];
    $before = $context->catalog->getAttributes();

    $this->patchJson($context->url, $payload)->assertConflict();
    expect($context->catalog->fresh()->getAttributes())->toEqual($before);
    $this->patchJson($context->url, $payload + ['expected_version' => $context->version])
        ->assertOk()->assertJsonPath('catalog.title', 'Lokal gespeicherter Titel');
    $this->patchJson($context->url, $payload + ['expected_version' => $context->version])->assertConflict();

    expect($context->catalog->fresh()->title)->toBe('Lokal gespeicherter Titel');
    expect($context->uploads)->toHaveCount(1);
});

test('a stale editor draft returns 409 before reading Dropbox and preserves the newer catalog', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncImport($this, $context);
    $context->catalog->update(['title' => 'Neuer gespeicherter Titel']);
    $requestsBefore = Http::recorded()->count();

    $this->postJson($context->url.'/sync-preview', ['title' => 'Veralteter Entwurf', 'questions' => $context->catalog->questions,
        'expected_version' => $context->version])->assertConflict();

    expect($context->catalog->fresh()->title)->toBe('Neuer gespeicherter Titel');
    expect(Http::recorded()->count())->toBe($requestsBefore);
    expect($context->uploads)->toBe([]);
});

test('an active template connection rejects a new source proof with 409 before reading Dropbox', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncConnect($this, $context);
    $before = $context->catalog->getAttributes();
    $requestsBefore = Http::recorded()->count();

    $this->patchJson($context->url, ['title' => $context->catalog->title, 'questions' => $context->catalog->questions,
        'expected_version' => $context->version, 'module_source_proofs' => [$context->sourceProof]])->assertConflict();

    expect($context->catalog->fresh()->getAttributes())->toEqual($before);
    expect(Http::recorded()->count())->toBe($requestsBefore);
    expect($context->uploads)->toHaveCount(1);
});

test('a rejected revision update returns 409 without leaving a pending operation that blocks normal editing', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncImport($this, $context);
    $preview = $this->postJson($context->url.'/sync-preview')->assertOk()->json();
    $before = $context->catalog->getAttributes();
    $context->uploadConflict = true;

    $this->postJson($context->url.'/sync-apply', ['token' => $preview['token']])->assertConflict();

    expect($context->catalog->fresh()->getAttributes())->toEqual($before);
    expect($context->uploads)->toBe([]);
    $this->postJson($context->url.'/sync-preview')->assertOk()->assertJsonMissingPath('resuming')->assertJsonPath('can_apply', false);
    $this->patchJson($context->url, ['title' => 'Nach Konflikt gespeichert', 'questions' => $context->catalog->questions,
        'expected_version' => $context->version])->assertOk();
    expect($context->catalog->fresh()->title)->toBe('Nach Konflikt gespeichert');
});

test('a divergent interrupted upload can be disconnected only with its recovery token without changing either question set', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncConnect($this, $context);
    $savedQuestions = $context->catalog->questions;
    $questions = [...$savedQuestions, mcTemplateSyncAdditionalQuestion()];
    $preview = $this->postJson($context->url.'/sync-preview', ['title' => $context->catalog->title, 'questions' => $questions,
        'expected_version' => $context->version])->assertOk()->json();
    $context->timeout = true;
    $this->postJson($context->url.'/sync-apply', ['token' => $preview['token']])->assertUnprocessable();
    $context->timeout = false;
    mcTemplateSyncRemoteChange($context, str_replace('Wo werden empfangene Nachrichten angezeigt?', 'Spätere externe Bearbeitung', $context->contents), 'changed-after-timeout');
    $contents = $context->contents;
    $uploads = $context->uploads;

    $recovery = $this->postJson($context->url.'/sync-preview')->assertOk()
        ->assertJsonPath('can_apply', false)->assertJsonPath('can_disconnect', true)->assertJsonPath('token', null)->json();
    $this->deleteJson($context->url.'/template-connection')->assertConflict();
    $this->deleteJson($context->url.'/template-connection', ['recovery_token' => 'invalid'])->assertConflict();
    $this->deleteJson($context->url.'/template-connection', ['recovery_token' => $recovery['recovery_token']])
        ->assertOk()->assertJsonPath('catalog.template_connection', null);
    $this->postJson($context->url.'/sync-apply', ['token' => $preview['token']])->assertConflict();

    expect($context->catalog->fresh()->questions)->toEqual($savedQuestions);
    expect($context->contents)->toBe($contents);
    expect($context->uploads)->toBe($uploads);
});

test('template synchronization previews both the old and new answer text before changing an option', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncConnect($this, $context);
    $before = $context->catalog->getAttributes();
    $questions = $context->catalog->questions;
    $questions[0]['options'][0]['text'] = 'Postfach für eingegangene Nachrichten';
    $uploads = $context->uploads;

    $this->postJson($context->url.'/sync-preview', ['title' => $context->catalog->title, 'questions' => $questions,
        'expected_version' => $context->version])->assertOk()->assertJsonPath('can_apply', true)
        ->assertJsonPath('source_changes.modified.0.before.options.0.text', 'A) Posteingang')
        ->assertJsonPath('source_changes.modified.0.after.options.0.text', 'Postfach für eingegangene Nachrichten');

    expect($context->catalog->fresh()->getAttributes())->toEqual($before);
    expect($context->uploads)->toBe($uploads);
});

test('writing a changed template updates the Vienna date and preserves its other metadata', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-21T23:30:00Z'));
    $context = mcTemplateSyncContext();
    mcTemplateSyncRemoteChange($context, str_replace("fach: Informatik\n---", "fach: Informatik\ncreated: 08.09.2025\nupdate: 01.01.2020\nextra: erhalten\n---", $context->contents), 'dated-source');
    mcTemplateSyncImport($this, $context);
    $questions = [...$context->catalog->questions, mcTemplateSyncAdditionalQuestion()];
    $preview = $this->postJson($context->url.'/sync-preview', ['title' => $context->catalog->title, 'questions' => $questions,
        'expected_version' => $context->version])->assertOk()->json();

    $this->postJson($context->url.'/sync-apply', ['token' => $preview['token']])->assertOk();

    expect($context->contents)->toContain("update: 22.10.2026\n", "created: 08.09.2025\n", "fach: Informatik\n", "extra: erhalten\n", "title: Fragenvorlage\n");
    expect($context->contents)->not->toContain('update: 01.01.2020');
});

test('expired unused previews cannot be applied and are replaced by a fresh bounded preview', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncImport($this, $context);
    $expired = $this->postJson($context->url.'/sync-preview')->assertOk()->json();
    $files = Storage::disk('local')->allFiles();
    expect($files)->toHaveCount(1);
    $this->travel(31)->minutes();

    $this->postJson($context->url.'/sync-apply', ['token' => $expired['token']])->assertConflict();
    $this->postJson($context->url.'/sync-preview')->assertOk()->assertJsonPath('can_apply', true);

    Storage::disk('local')->assertMissing($files[0]);
    expect(Storage::disk('local')->allFiles())->toHaveCount(1);
    expect($context->uploads)->toBe([]);
});

test('initial template synchronization requires confirmation when the source changed since its import', function (): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncImport($this, $context);
    $before = $context->catalog->getAttributes();
    mcTemplateSyncRemoteChange($context, str_replace('Wo werden empfangene Nachrichten angezeigt?', 'Extern bearbeitete Frage', $context->contents), 'changed-before-connection');

    $response = $this->postJson($context->url.'/sync-preview')->assertOk()->assertJsonPath('can_apply', false)->assertJsonPath('token', null);

    expect($response->json('conflicts'))->not->toBeEmpty();
    expect($context->catalog->fresh()->getAttributes())->toEqual($before);
    expect($context->uploads)->toBe([]);
});

test('template synchronization returns 422 rather than dropping content between question sections', function (string $additionalContent): void {
    $context = mcTemplateSyncContext();
    mcTemplateSyncRemoteChange($context, str_replace('### 2.', $additionalContent."\n\n### 2.", $context->contents), 'source-with-notes');
    mcTemplateSyncImport($this, $context);
    $before = $context->catalog->getAttributes();
    $contents = $context->contents;

    $this->postJson($context->url.'/sync-preview')->assertUnprocessable()->assertJsonValidationErrors('template');

    expect($context->catalog->fresh()->getAttributes())->toEqual($before);
    expect($context->contents)->toBe($contents);
    expect($context->uploads)->toBe([]);
})->with([
    'intermediate heading' => ['## Zusätzliche Hinweise'],
    'explanatory prose' => ['Dieser zusätzliche Hinweis muss erhalten bleiben.'],
]);
