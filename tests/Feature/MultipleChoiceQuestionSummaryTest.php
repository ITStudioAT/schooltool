<?php

use App\Models\DropboxConnection;
use App\Models\McCatalog;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\DropboxWorkImport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();

uses(RefreshDatabase::class);

function mcQuestionSummaryContext(): object
{
    Http::preventStrayRequests();
    Storage::fake('local');
    config([
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
    $sourceFolder = '/School/E-Mails/Multiple Choice Fragen';
    $sourcePath = $sourceFolder.'/Fragen.md';
    $source = ['module_id' => 'a263b873-4f27-4167-90f0-7d94ea0f67fa', 'module_folder_id' => 'id:module',
        'module_path' => '/School/E-Mails', 'source_file_id' => 'id:source', 'source_revision' => 'source-rev',
        'source_path' => $sourcePath];
    $questions = [
        ['id' => 'q1', 'type' => 'single', 'prompt' => 'Was bedeutet <script>?',
            'options' => [['id' => 'a1', 'text' => 'Ein & sicherer Posteingang'], ['id' => 'a2', 'text' => 'Papierkorb']],
            'correct_option_ids' => ['a1'], 'active' => true, 'reviewed' => true],
        ['id' => 'q2', 'type' => 'multiple', 'prompt' => 'Welche Angaben bleiben privat?',
            'options' => [['id' => 'a3', 'text' => 'Kennwort'], ['id' => 'a4', 'text' => 'Sicherheitscode'],
                ['id' => 'a5', 'text' => 'Schulname']],
            'correct_option_ids' => ['a3', 'a4'], 'active' => true, 'reviewed' => true],
        ['id' => 'q3', 'type' => 'single', 'prompt' => 'Noch offene Frage',
            'options' => [['id' => 'a6', 'text' => 'Ja'], ['id' => 'a7', 'text' => 'Nein']],
            'correct_option_ids' => [], 'active' => false, 'reviewed' => false],
    ];
    $catalog = McCatalog::query()->create(['school_id' => $school->id, 'owner_id' => $teacher->id,
        'title' => 'E-Mails', 'questions' => $questions, 'module_sources' => [$source]]);
    $statePath = "multiple-choice/template-sync/{$school->id}/{$teacher->id}/{$catalog->id}/connection.json.enc";
    Storage::disk('local')->put($statePath, Crypt::encryptString(json_encode(['binding' => [
        'source' => $source, 'account_id' => $connection->account_id, 'revision' => 'source-rev',
        'questions' => $questions, 'synced_at' => now()->toISOString(),
    ]], JSON_THROW_ON_ERROR)));
    $context = (object) [
        'teacher' => $teacher, 'connection' => $connection, 'catalog' => $catalog, 'source' => $source,
        'sourceFolder' => $sourceFolder, 'folder' => null, 'file' => null, 'uploads' => [], 'createdFolders' => 0,
        'targetPath' => $sourceFolder.'/Question Summary/Question Summary_Katalog-'.$catalog->id.'.md',
        'url' => "/api/admin/multiple-choice/catalogs/{$catalog->id}/question-summary",
    ];
    Http::fake(function (Request $request) use ($context) {
        if ($request->url() === 'https://api.dropboxapi.com/oauth2/token') {
            return Http::response(['access_token' => 'fixture-access']);
        }
        if ($request->url() === 'https://api.dropboxapi.com/2/files/get_metadata') {
            $path = $request['path'];
            if ($path === 'id:source') {
                return Http::response(['.tag' => 'file', 'id' => 'id:source', 'name' => 'Fragen.md',
                    'path_display' => $context->source['source_path'], 'path_lower' => mb_strtolower($context->source['source_path']),
                    'rev' => 'source-rev', 'size' => 40]);
            }
            if ($path === mb_strtolower($context->sourceFolder) || $path === 'id:folder') {
                return Http::response(['.tag' => 'folder', 'id' => 'id:folder', 'name' => 'Multiple Choice Fragen',
                    'path_display' => $context->sourceFolder, 'path_lower' => mb_strtolower($context->sourceFolder)]);
            }
            if (in_array($path, [$context->sourceFolder.'/Question Summary', 'id:summary-folder'], true)
                && $context->folder !== null) {
                return Http::response($context->folder);
            }
            if ($path === $context->targetPath && $context->file !== null) {
                return Http::response($context->file);
            }

            return Http::response(['error_summary' => 'path/not_found/...'], 409);
        }
        if ($request->url() === 'https://api.dropboxapi.com/2/files/create_folder_v2') {
            expect($request['path'])->toBe($context->sourceFolder.'/Question Summary');
            expect($request['autorename'])->toBeFalse();
            $context->createdFolders++;
            $context->folder = ['.tag' => 'folder', 'id' => 'id:summary-folder', 'name' => 'Question Summary',
                'path_display' => $context->sourceFolder.'/Question Summary',
                'path_lower' => mb_strtolower($context->sourceFolder.'/Question Summary')];

            return Http::response(['metadata' => $context->folder]);
        }
        if ($request->url() === 'https://content.dropboxapi.com/2/files/upload') {
            $arguments = json_decode($request->header('Dropbox-API-Arg')[0], true);
            $context->uploads[] = ['arguments' => $arguments, 'contents' => $request->body()];
            $context->file = ['.tag' => 'file', 'id' => 'id:summary', 'name' => basename($context->targetPath),
                'path_display' => $context->targetPath, 'path_lower' => mb_strtolower($context->targetPath),
                'size' => strlen($request->body()), 'rev' => 'summary-'.count($context->uploads)];
            $context->contents = $request->body();

            return Http::response($context->file);
        }
        if ($request->url() === 'https://content.dropboxapi.com/2/files/download') {
            $arguments = json_decode($request->header('Dropbox-API-Arg')[0], true);
            if ($arguments['path'] === 'rev:'.($context->file['rev'] ?? '')) {
                return Http::response($context->contents);
            }
        }

        throw new RuntimeException('Unexpected Dropbox request: '.$request->url());
    });

    return $context;
}

test('question summary previews all saved questions and correct answers without writing to Dropbox', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 12:00:00 UTC'));
    $context = mcQuestionSummaryContext();

    $response = $this->actingAs($context->teacher, 'sanctum')->postJson($context->url.'-preview')
        ->assertOk()->assertJsonPath('action', 'create')->assertJsonPath('question_count', 3)
        ->assertJsonPath('incomplete_count', 1)->assertJsonPath('can_apply', true);
    $content = $response->json('content');

    expect($content)->toContain('update: 21.10.2026', 'Ein &amp; sicherer Posteingang',
        'Kennwort', 'Sicherheitscode', '**Unvollständig – bitte prüfen.**')
        ->not->toContain('Papierkorb', 'Schulname', '<script>');
    expect($context->createdFolders)->toBe(0);
    expect($context->uploads)->toBe([]);
});

test('question summary preserves line breaks in questions and answers without enabling Markdown markup', function (): void {
    $context = mcQuestionSummaryContext();
    $questions = $context->catalog->questions;
    $questions[0]['prompt'] = "Erste Zeile\r\n# Zweite Zeile\n\nDritter Absatz";
    $questions[0]['options'][0]['text'] = "Antwort eins\n* zweite Zeile";
    $context->catalog->update(['questions' => $questions]);

    $content = $this->actingAs($context->teacher, 'sanctum')
        ->postJson($context->url.'-preview')->assertOk()->json('content');

    expect($content)->toContain("Erste Zeile  \n\\# Zweite Zeile\n\nDritter Absatz",
        "- Antwort eins  \n  \\* zweite Zeile")
        ->not->toContain('Erste Zeile # Zweite Zeile', 'Antwort eins * zweite Zeile');
    expect($context->uploads)->toBe([]);
});

test('question summary keeps ordinary hyphens readable and provides a safe display snapshot', function (): void {
    $context = mcQuestionSummaryContext();
    $questions = $context->catalog->questions;
    $questions[0]['prompt'] = 'Office-365-Anmeldung und A-B';
    $questions[0]['options'][0]['text'] = 'E-Mail-Adresse';
    $context->catalog->update(['questions' => $questions]);

    $preview = $this->actingAs($context->teacher, 'sanctum')
        ->postJson($context->url.'-preview')->assertOk()->json();

    expect($preview['content'])->toContain('Office-365-Anmeldung und A-B', 'E-Mail-Adresse')
        ->not->toContain('Office\\-365', 'A\\-B', 'E\\-Mail');
    expect($preview['rows'][0])->toMatchArray(['number' => 1, 'type' => 'Einfachauswahl',
        'prompt' => 'Office-365-Anmeldung und A-B', 'answers' => ['E-Mail-Adresse'],
        'active' => true, 'incomplete' => false]);
    expect($context->uploads)->toBe([]);
});

test('question summary preserves only the exact standalone multiple answer instruction as bold prompt text', function (): void {
    $context = mcQuestionSummaryContext();
    $instruction = 'Mehrfachantworten: Wähle alle richtigen Antworten.';
    $marked = '**'.$instruction.'**';
    $questions = $context->catalog->questions;
    $questions[1]['prompt'] = "Welche Angaben bleiben privat?\r\n \t{$marked} \t\n**other**\nZusatz {$marked}";
    $questions[1]['options'][0]['text'] = $marked;
    $questions[1]['options'][1]['text'] = '**other**';
    $context->catalog->update(['title' => $marked, 'questions' => $questions]);
    $savedQuestions = $context->catalog->fresh()->questions;

    $preview = $this->actingAs($context->teacher, 'sanctum')
        ->postJson($context->url.'-preview')->assertOk()->json();

    expect($preview['content'])->toContain(
        "Welche Angaben bleiben privat?  \n".$marked."  \n\\*\\*other\\*\\*  \nZusatz \\*\\*".$instruction.'\\*\\*',
        '# Question Summary: \\*\\*'.$instruction.'\\*\\*',
        '- \\*\\*'.$instruction.'\\*\\*',
        '- \\*\\*other\\*\\*',
    );
    expect($preview['rows'][1]['prompt'])->toBe("Welche Angaben bleiben privat?\n{$instruction}\n**other**\nZusatz {$marked}");
    expect($preview['rows'][1]['answers'])->toBe([$marked, '**other**']);
    expect($context->catalog->fresh()->questions)->toBe($savedQuestions);
    expect($context->createdFolders)->toBe(0);
    expect($context->uploads)->toBe([]);
});

test('question summary creates its own Dropbox subfolder and verifies the uploaded Markdown after confirmation', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 12:00:00 UTC'));
    $context = mcQuestionSummaryContext();
    $this->actingAs($context->teacher, 'sanctum');
    $preview = $this->postJson($context->url.'-preview')->assertOk()->json();

    $this->postJson($context->url.'-apply', ['token' => $preview['token']])
        ->assertOk()->assertJsonPath('action', 'create')->assertJsonPath('path', $context->targetPath)
        ->assertJsonPath('question_count', 3);

    expect($context->createdFolders)->toBe(1);
    expect($context->uploads)->toHaveCount(1);
    expect($context->uploads[0]['arguments'])->toMatchArray(['path' => $context->targetPath,
        'mode' => 'add', 'autorename' => false, 'strict_conflict' => true]);
    expect($context->contents)->toBe($preview['content']);
});

test('question summary rejects a newer remote revision instead of overwriting it', function (): void {
    $context = mcQuestionSummaryContext();
    $context->folder = ['.tag' => 'folder', 'id' => 'id:summary-folder', 'name' => 'Question Summary',
        'path_display' => $context->sourceFolder.'/Question Summary',
        'path_lower' => mb_strtolower($context->sourceFolder.'/Question Summary')];
    $context->contents = 'Ältere Zusammenfassung';
    $context->file = ['.tag' => 'file', 'id' => 'id:summary', 'name' => basename($context->targetPath),
        'path_display' => $context->targetPath, 'path_lower' => mb_strtolower($context->targetPath),
        'size' => strlen($context->contents), 'rev' => 'previous-rev'];
    $this->actingAs($context->teacher, 'sanctum');
    $preview = $this->postJson($context->url.'-preview')->assertOk()->assertJsonPath('action', 'update')->json();
    $context->file['rev'] = 'newer-rev';

    $this->postJson($context->url.'-apply', ['token' => $preview['token']])->assertConflict();

    expect($context->uploads)->toBe([]);
    expect($context->contents)->toBe('Ältere Zusammenfassung');
});

test('question summary updates an existing Dropbox file only at its reviewed revision', function (): void {
    $context = mcQuestionSummaryContext();
    $context->folder = ['.tag' => 'folder', 'id' => 'id:summary-folder', 'name' => 'Question Summary',
        'path_display' => $context->sourceFolder.'/Question Summary',
        'path_lower' => mb_strtolower($context->sourceFolder.'/Question Summary')];
    $context->contents = 'Ältere Zusammenfassung';
    $context->file = ['.tag' => 'file', 'id' => 'id:summary', 'name' => basename($context->targetPath),
        'path_display' => $context->targetPath, 'path_lower' => mb_strtolower($context->targetPath),
        'size' => strlen($context->contents), 'rev' => 'previous-rev'];
    $this->actingAs($context->teacher, 'sanctum');
    $preview = $this->postJson($context->url.'-preview')->assertOk()->assertJsonPath('action', 'update')->json();

    $this->postJson($context->url.'-apply', ['token' => $preview['token']])
        ->assertOk()->assertJsonPath('action', 'update');

    expect($context->createdFolders)->toBe(0);
    expect($context->uploads)->toHaveCount(1);
    expect($context->uploads[0]['arguments']['mode'])->toEqual(['.tag' => 'update', 'update' => 'previous-rev']);
    expect($context->contents)->toBe($preview['content']);
});

test('question summary rejects a changed Dropbox account after preview', function (): void {
    $context = mcQuestionSummaryContext();
    $this->actingAs($context->teacher, 'sanctum');
    $preview = $this->postJson($context->url.'-preview')->assertOk()->json();
    $context->connection->update(['account_id' => 'another-account']);

    $this->postJson($context->url.'-apply', ['token' => $preview['token']])->assertConflict();

    expect($context->uploads)->toBe([]);
});

test('question summary rejects a changed saved catalog after preview', function (): void {
    $context = mcQuestionSummaryContext();
    $this->actingAs($context->teacher, 'sanctum');
    $preview = $this->postJson($context->url.'-preview')->assertOk()->json();
    $context->catalog->update(['title' => 'E-Mails und Chats']);

    $this->postJson($context->url.'-apply', ['token' => $preview['token']])->assertConflict();

    expect($context->uploads)->toBe([]);
});

test('question summary rejects a catalog without a confirmed source binding', function (): void {
    $context = mcQuestionSummaryContext();
    Storage::disk('local')->delete("multiple-choice/template-sync/{$context->catalog->school_id}/{$context->teacher->id}/{$context->catalog->id}/connection.json.enc");

    $this->actingAs($context->teacher, 'sanctum')->postJson($context->url.'-preview')->assertConflict();

    expect($context->uploads)->toBe([]);
});
