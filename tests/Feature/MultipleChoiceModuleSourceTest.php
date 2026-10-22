<?php

use App\Models\DropboxConnection;
use App\Models\McCatalog;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingWorkModuleLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['app.url' => 'http://localhost:8000', 'services.dropbox.client_id' => 'test-client', 'services.dropbox.client_secret' => 'test-secret',
        'services.dropbox.redirect_uri' => 'http://localhost:8000/admin/teaching/dropbox/callback', 'schooltool.preview.instance' => false]);
    Role::findOrCreate('teacher', 'web');
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    enableSchoolToolModuleForTests($school, 'teaching');
    grantSchoolToolLicenceForTests($school, 'Lehrertool');
    $this->teacher = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id])->assignRole('teacher');
    $this->connection = DropboxConnection::factory()->create(['user_id' => $this->teacher->id]);
    $this->catalog = McCatalog::query()->create(['school_id' => $school->id, 'owner_id' => $this->teacher->id,
        'title' => 'Vorhandener Katalog', 'questions' => []]);
    $this->handshake = '{"schema_version":1,"module_id":"a263b873-4f27-4167-90f0-7d94ea0f67fa"}';
    $this->template = file_get_contents(dirname(__DIR__).'/Support/mc-module-emails.md');
    $this->root = '/school/materialien/lerninhalte';
    $this->entries = [
        ['.tag' => 'folder', 'id' => 'id:root', 'name' => 'Lerninhalte', 'path_lower' => $this->root],
        ['.tag' => 'folder', 'id' => 'id:module', 'name' => 'E-Mails', 'path_lower' => $this->root.'/emails'],
        ['.tag' => 'folder', 'id' => 'id:mc', 'name' => 'Multiple Choice Fragen', 'path_lower' => $this->root.'/emails/multiple choice fragen'],
        ['.tag' => 'file', 'id' => 'id:handshake', 'name' => 'handshake.json', 'path_lower' => $this->root.'/emails/handshake.json', 'rev' => 'hs', 'size' => strlen($this->handshake)],
        ['.tag' => 'file', 'id' => 'id:questions', 'name' => 'Multiple Choice Fragen_E-Mails.md', 'path_lower' => $this->root.'/emails/multiple choice fragen/questions.md', 'rev' => 'source', 'size' => strlen($this->template)],
    ];
    $this->url = '/api/admin/multiple-choice/import-preview';
    $this->actingAs($this->teacher, 'sanctum');
});

function fakeMcModuleSource(object $context): void
{
    Http::fake(function (Request $request) use ($context) {
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'source-test-access']);
        }
        if (str_ends_with($request->url(), '/files/search_v2')) {
            return Http::response(['matches' => [['metadata' => ['metadata' => $context->entries[3]]]], 'has_more' => false]);
        }
        if (str_ends_with($request->url(), '/files/get_metadata')) {
            foreach ($context->entries as $entry) {
                if ($entry['id'] === $request['path'] || $entry['path_lower'] === $request['path']) {
                    return Http::response($entry);
                }
            }
        }
        if (str_ends_with($request->url(), '/files/list_folder')) {
            $folder = collect($context->entries)->firstWhere('id', $request['path']);

            return Http::response(['entries' => array_values(array_filter($context->entries,
                fn (array $entry): bool => dirname($entry['path_lower']) === $folder['path_lower'])), 'has_more' => false]);
        }
        if (str_ends_with($request->url(), '/files/download')) {
            $args = json_decode($request->header('Dropbox-API-Arg')[0], true);

            return Http::response($args['path'] === 'rev:hs' ? $context->handshake : $context->template);
        }

        throw new RuntimeException('Unexpected module source request: '.$request->url());
    });
}

function mcModuleRequest(object $context): array
{
    return ['filename' => 'handshake.json', 'handshake' => $context->handshake, 'catalog_id' => $context->catalog->id, 'generate' => false];
}

test('the actual forty-question module source preserves thirty single and ten multiple questions without generating or saving', function (): void {
    fakeMcModuleSource($this);
    $before = $this->catalog->fresh()->getAttributes();
    $data = $this->postJson($this->url, mcModuleRequest($this))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();

    expect($data['preview']['valid'])->toBeTrue();
    expect($data['text'])->toBe($this->template);
    expect($data['preview']['questions'])->toHaveCount(40);
    expect(collect($data['preview']['questions'])->where('type', 'single'))->toHaveCount(30);
    expect(collect($data['preview']['questions'])->where('type', 'multiple'))->toHaveCount(10);
    $correctIndices = [[1], [0], [3], [2], [1], [0], [3], [2], [1], [0], [3], [2], [1], [0], [3], [2], [1], [0], [3], [2],
        [1], [0], [3], [2], [1], [0], [3], [2], [1], [0], [0, 2, 3], [1, 2], [0, 2, 3], [1, 3], [0, 2], [1, 2], [0, 2, 3], [1, 2, 3, 4], [0, 3], [1, 2, 3]];
    foreach ($correctIndices as $index => $indices) {
        $question = $data['preview']['questions'][$index];
        expect($question['correct_option_ids'])->toBe(array_map(fn (int $option): string => $question['options'][$option]['id'], $indices));
    }
    expect($data['preview']['questions'][0]['options'][1]['text'])->toContain('Office 365');
    expect($data['preview']['questions'][0]['correct_option_ids'])->toBe([$data['preview']['questions'][0]['options'][1]['id']]);
    expect($this->catalog->fresh()->getAttributes())->toEqual($before);
    Http::assertNotSent(fn (Request $request): bool => ! str_contains($request->url(), 'dropboxapi.com'));
});

test('a module source is persisted only with explicit catalog saving and remains distinct from work bindings', function (): void {
    fakeMcModuleSource($this);
    $data = $this->postJson($this->url, mcModuleRequest($this))->assertOk()->json();
    expect($this->catalog->fresh()->module_sources)->toBeNull();

    $this->patchJson('/api/admin/multiple-choice/catalogs/'.$this->catalog->id, ['title' => $this->catalog->title,
        'questions' => $data['preview']['questions'], 'module_source_proofs' => [$data['source_proof']]])->assertOk()
        ->assertJsonPath('catalog.module_sources.0.module_id', 'a263b873-4f27-4167-90f0-7d94ea0f67fa');

    expect($this->catalog->fresh()->questions)->toHaveCount(40);
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('multiple compatible files require a deliberate source selection', function (): void {
    $this->entries[] = array_replace($this->entries[4], ['id' => 'id:second', 'name' => 'Zweite Vorlage.md',
        'path_lower' => $this->root.'/emails/multiple choice fragen/second.md', 'rev' => 'second']);
    fakeMcModuleSource($this);

    $this->postJson($this->url, mcModuleRequest($this))->assertOk()->assertJsonCount(2, 'sources')->assertJsonMissingPath('source_proof');
    $this->postJson($this->url, mcModuleRequest($this) + ['source_id' => 'id:second'])->assertOk()
        ->assertJsonPath('module_source.source_file_id', 'id:second');
    expect($this->catalog->fresh()->questions)->toBe([]);
});

test('a missing fixed folder does not fall back to arbitrary files or modify the editor catalog', function (): void {
    unset($this->entries[2]);
    fakeMcModuleSource($this);
    $this->postJson($this->url, mcModuleRequest($this))->assertUnprocessable()->assertJsonValidationErrors('handshake');
    expect($this->catalog->fresh()->questions)->toBe([]);
});

test('changed sources cannot be saved using an old import preview', function (): void {
    fakeMcModuleSource($this);
    $data = $this->postJson($this->url, mcModuleRequest($this))->assertOk()->json();
    $this->entries[4]['rev'] = 'changed';
    $this->patchJson('/api/admin/multiple-choice/catalogs/'.$this->catalog->id, ['title' => $this->catalog->title,
        'questions' => $data['preview']['questions'], 'module_source_proofs' => [$data['source_proof']]])->assertConflict();
    expect($this->catalog->fresh()->questions)->toBe([]);
    expect($this->catalog->fresh()->module_sources)->toBeNull();
});

test('module resolution is restricted to the real catalog owner before any Dropbox access', function (): void {
    $other = User::factory()->create(['school_id' => $this->teacher->school_id]);
    $this->catalog->update(['owner_id' => $other->id]);
    $this->postJson($this->url, mcModuleRequest($this))->assertNotFound();
    Http::assertNothingSent();
});

test('invalid handshake names or contents are rejected without cloud reads', function (): void {
    $this->postJson($this->url, array_replace(mcModuleRequest($this), ['filename' => 'handshake (1).json']))->assertUnprocessable();
    $this->postJson($this->url, array_replace(mcModuleRequest($this), ['handshake' => '{"schema_version":2,"module_id":"invalid"}']))->assertUnprocessable();
    Http::assertNothingSent();
});
