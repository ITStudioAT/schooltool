<?php

use App\Models\DropboxConnection;
use App\Models\McCatalog;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkModuleLink;
use App\Models\User;
use App\Services\MultipleChoice\McModuleTemplateSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['app.url' => 'http://localhost:8000', 'services.dropbox.client_id' => 'test-client', 'services.dropbox.client_secret' => 'test-secret',
        'services.dropbox.redirect_uri' => 'http://localhost:8000/admin/teaching/dropbox/callback']);
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

            return Http::response($args['path'] === 'rev:hs' ? $context->handshake : ($context->documents[$args['path']] ?? $context->template));
        }

        throw new RuntimeException('Unexpected module source request: '.$request->url());
    });
}

function mcModuleRequest(object $context): array
{
    return ['filename' => 'handshake.json', 'handshake' => $context->handshake, 'catalog_id' => $context->catalog->id, 'generate' => false];
}

function workModuleSourceLink(object $context): TeachingWorkModuleLink
{
    return new TeachingWorkModuleLink(['dropbox_connection_id' => $context->connection->id,
        'account_id' => $context->connection->account_id, 'module_id' => 'a263b873-4f27-4167-90f0-7d94ea0f67fa',
        'folder_id' => 'id:module', 'folder_path' => $context->root.'/emails',
        'handshake_file_id' => 'id:handshake', 'handshake_revision' => 'hs']);
}

test('work module questions are checked in the confirmed module and do not use an unrelated catalog', function (): void {
    fakeMcModuleSource($this);
    $service = app(McModuleTemplateSource::class);
    $link = workModuleSourceLink($this);
    expect($service->hasUsableQuestions($this->teacher, $link))->toBeTrue();
    $this->entries = array_values(array_filter($this->entries, fn (array $entry): bool => $entry['id'] !== 'id:mc'));
    expect($service->hasUsableQuestions($this->teacher, $link))->toBeFalse();
});

test('empty or invalid module questions are not considered usable', function (): void {
    $this->template = '# Keine Fragen';
    $this->entries[4]['size'] = strlen($this->template);
    fakeMcModuleSource($this);
    expect(app(McModuleTemplateSource::class)->hasUsableQuestions($this->teacher, workModuleSourceLink($this)))->toBeFalse();
});

test('assessment points are read only from an unambiguous explicit template total', function (string $contents, ?float $expected): void {
    $this->template = $contents;
    $this->entries[] = ['.tag' => 'folder', 'id' => 'id:assessment', 'name' => 'Assessment Template',
        'path_lower' => $this->root.'/emails/assessment template'];
    $this->entries[] = ['.tag' => 'file', 'id' => 'id:assessment-file', 'name' => 'Aufgaben.md',
        'path_lower' => $this->root.'/emails/assessment template/aufgaben.md', 'rev' => 'source', 'size' => strlen($contents)];
    fakeMcModuleSource($this);
    expect(app(McModuleTemplateSource::class)->assessmentPoints($this->teacher, workModuleSourceLink($this)))->toBe($expected);
})->with([
    ['# Aufgaben'."\n".'**Gesamtpunkte:** 30 Punkte', 30.0],
    ['# Aufgaben'."\n".'Gesamt: 30 Punkte', 30.0],
    ['# Aufgaben'."\n".'Gesamt: 30', null],
    ['# Aufgaben'."\n".'Gesamtpunkte: 30 Punkte'."\n".'Gesamtpunktzahl: 35 Punkte', null],
    ['# Aufgaben'."\n".'10 Fragen und jeweils 3 Punkte', null],
    ['# Aufgaben'."\n".'Gesamtpunkte: 30 Punkte'."\n".'Multiple-Choice-Teil enthalten', null],
]);

test('a changed module binding is not reported as an absence of questions', function (): void {
    fakeMcModuleSource($this);
    $link = workModuleSourceLink($this);
    $link->folder_id = 'id:other';
    app(McModuleTemplateSource::class)->hasUsableQuestions($this->teacher, $link);
})->throws(HttpException::class);

test('multiple possible question folders remain an ambiguous check', function (): void {
    $this->entries[] = ['.tag' => 'folder', 'id' => 'id:other-questions', 'name' => 'Fragenkatalog',
        'path_lower' => $this->root.'/emails/fragenkatalog'];
    fakeMcModuleSource($this);
    app(McModuleTemplateSource::class)->hasUsableQuestions($this->teacher, workModuleSourceLink($this));
})->throws(HttpException::class);

test('the work endpoint checks its own confirmed module instead of a global catalog', function (): void {
    $this->documents = [
        'rev:source' => "### 1. Frage?\n- [ ] A\n- [ ] B\n",
        'rev:answers' => "### 1. Frage?\n- [x] A\n- [ ] B\n",
    ];
    $this->entries[4]['size'] = strlen($this->documents['rev:source']);
    $this->entries[] = ['.tag' => 'file', 'id' => 'id:answers', 'name' => 'Multiple Choice Fragen_E-Mails_Antworten.md',
        'path_lower' => $this->root.'/emails/multiple choice fragen/answers.md', 'rev' => 'answers', 'size' => strlen($this->documents['rev:answers'])];
    $course = TeachingCourse::factory()->forSchool(School::findOrFail($this->teacher->school_id))
        ->forSchoolyear(Schoolyear::findOrFail($this->teacher->schoolyear_id))->forTeacher($this->teacher)->create();
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $course->id]);
    $link = workModuleSourceLink($this);
    $link->teaching_course_work_id = $work->id;
    $link->schema_version = 1;
    $link->folder_name = 'E-Mails';
    $link->save();
    fakeMcModuleSource($this);
    $url = '/api/admin/teaching/course_works/'.$work->id.'/exercise-options?check_module_questions=1';
    $this->getJson($url)->assertOk()->assertJsonPath('module_questions', true)->assertJsonPath('module_catalog.count', 1)
        ->assertJsonMissingPath('module_catalog.questions');
    $this->entries = array_values(array_filter($this->entries, fn (array $entry): bool => $entry['id'] !== 'id:mc'));
    $this->getJson($url)->assertOk()->assertJsonPath('module_questions', false);
    $link->update(['folder_id' => 'id:other']);
    $this->getJson($url)->assertConflict();
});

test('module catalog checks the answer key and rejects changed options without saving', function (): void {
    $this->documents = [
        'rev:source' => "### 1. Frage?\n- [ ] A\n- [ ] B\n",
        'rev:answers' => "### 1. Frage?\n- [x] A\n- [ ] B\n",
    ];
    $this->entries[4]['size'] = strlen($this->documents['rev:source']);
    $this->entries[] = ['.tag' => 'file', 'id' => 'id:answers', 'name' => 'Multiple Choice Fragen_E-Mails_Antworten.md',
        'path_lower' => $this->root.'/emails/multiple choice fragen/answers.md', 'rev' => 'answers', 'size' => strlen($this->documents['rev:answers'])];
    fakeMcModuleSource($this);
    $service = app(McModuleTemplateSource::class);
    $before = McCatalog::query()->count();
    expect($service->workCatalog($this->teacher, workModuleSourceLink($this))['questions'])->toHaveCount(1);
    expect(McCatalog::query()->count())->toBe($before);
    $this->documents['rev:answers'] = "### 1. Frage?\n- [x] A\n- [ ] C\n";
    expect($service->workCatalog($this->teacher, workModuleSourceLink($this)))->toBeNull();
});

test('versioned points match task headings and rubric subtotals', function (bool $conflict): void {
    $task = "---\nfacts: \"35 Punkte\"\n---\n**1. Aufgabe** <small>(8 Punkte)</small>\n**2. Aufgabe** <small>(11 Punkte)</small>\n**3. Aufgabe** <small>(8 Punkte)</small>\n**4. Aufgabe** <small>(8 Punkte)</small>\n";
    $rubric = "---\nfacts: \"35 Punkte\"\n---\n| | **Teilsumme Aufgabe 1** | **8** |\n| | **Teilsumme Aufgabe 2** | **11** |\n| | **Teilsumme Aufgabe 3** | **8** |\n| | **Teilsumme Aufgabe 4** | **8** |\n| | **Gesamt** | **35** |\n";
    $json = json_encode(['schema_version' => 1, 'gesamtpunkte' => 35, 'aufgaben' => [
        ['nummer' => 1, 'punkte' => $conflict ? 9 : 8], ['nummer' => 2, 'punkte' => 11],
        ['nummer' => 3, 'punkte' => 8], ['nummer' => 4, 'punkte' => 8],
    ]], JSON_THROW_ON_ERROR);
    $this->documents = ['rev:task' => $task, 'rev:rubric' => $rubric, 'rev:points' => $json];
    $path = $this->root.'/emails/prüfungsvorlage';
    $this->entries[] = ['.tag' => 'folder', 'id' => 'id:assessment', 'name' => 'Prüfungsvorlage', 'path_lower' => $path];
    foreach (['task' => ['LF_Wordtest.md', $task], 'rubric' => ['Beurteilungskriterien_Wordtest.md', $rubric], 'points' => ['Pruefungspunkte.json', $json]] as $id => [$name, $content]) {
        $this->entries[] = ['.tag' => 'file', 'id' => 'id:'.$id, 'name' => $name, 'path_lower' => $path.'/'.mb_strtolower($name), 'rev' => $id, 'size' => strlen($content)];
    }
    $this->entries[] = ['.tag' => 'file', 'id' => 'id:pdf', 'name' => 'LF_Wordtest.pdf', 'path_lower' => $path.'/lf_wordtest.pdf', 'rev' => 'pdf', 'size' => 123];
    fakeMcModuleSource($this);
    expect(app(McModuleTemplateSource::class)->assessmentPoints($this->teacher, workModuleSourceLink($this)))->toBe($conflict ? null : 35.0);
})->with([false, true]);

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

test('a merged module offers its confirmed source templates and checks the manifest again before saving', function (): void {
    $sourcePath = $this->root.'/source-module';
    $manifest = json_encode(['schema_version' => 1, 'type' => 'lernmodule-zusammenfuehrung',
        'target' => ['id' => 'id:module', 'path' => $this->root.'/emails'],
        'sources' => [['id' => 'id:source-module', 'path' => $sourcePath]]], JSON_THROW_ON_ERROR);
    $this->documents = ['rev:manifest' => $manifest];
    $this->entries[] = ['.tag' => 'file', 'id' => 'id:manifest', 'name' => 'lernmodule-zusammenfuehrung.json',
        'path_lower' => $this->root.'/emails/lernmodule-zusammenfuehrung.json', 'rev' => 'manifest', 'size' => strlen($manifest)];
    $this->entries[] = ['.tag' => 'folder', 'id' => 'id:source-module', 'name' => 'source-module', 'path_lower' => $sourcePath];
    $this->entries[] = ['.tag' => 'folder', 'id' => 'id:source-mc', 'name' => 'Multiple Choice Fragen', 'path_lower' => $sourcePath.'/multiple choice fragen'];
    $this->entries[] = ['.tag' => 'file', 'id' => 'id:source-question', 'name' => 'Vorlage.md',
        'path_lower' => $sourcePath.'/multiple choice fragen/vorlage.md', 'rev' => 'source-question', 'size' => strlen($this->template)];
    fakeMcModuleSource($this);

    $this->postJson($this->url, mcModuleRequest($this))->assertOk()->assertJsonCount(2, 'sources')->assertJsonMissingPath('source_proof');
    $data = $this->postJson($this->url, mcModuleRequest($this) + ['source_id' => 'id:source-question'])
        ->assertOk()->assertJsonPath('module_source.source_file_id', 'id:source-question')->json();
    $this->patchJson('/api/admin/multiple-choice/catalogs/'.$this->catalog->id, ['title' => $this->catalog->title,
        'questions' => $data['preview']['questions'], 'module_source_proofs' => [$data['source_proof']]])
        ->assertOk()->assertJsonPath('catalog.module_sources.0.source_file_id', 'id:source-question');

    $this->documents['rev:changed'] = $manifest;
    $this->entries[5]['rev'] = 'changed';
    $this->patchJson('/api/admin/multiple-choice/catalogs/'.$this->catalog->id, ['title' => $this->catalog->title,
        'questions' => $data['preview']['questions'], 'module_source_proofs' => [$data['source_proof']]])->assertConflict();
});

test('a merged module cannot reference a folder outside the confirmed learning content root', function (): void {
    $manifest = json_encode(['schema_version' => 1, 'type' => 'lernmodule-zusammenfuehrung',
        'target' => ['id' => 'id:module', 'path' => $this->root.'/emails'],
        'sources' => [['id' => 'id:private', 'path' => '/private']]], JSON_THROW_ON_ERROR);
    $this->documents = ['rev:manifest' => $manifest];
    $this->entries[] = ['.tag' => 'file', 'id' => 'id:manifest', 'name' => 'lernmodule-zusammenfuehrung.json',
        'path_lower' => $this->root.'/emails/lernmodule-zusammenfuehrung.json', 'rev' => 'manifest', 'size' => strlen($manifest)];
    fakeMcModuleSource($this);

    $this->postJson($this->url, mcModuleRequest($this))->assertUnprocessable();
    expect($this->catalog->fresh()->questions)->toBe([]);
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/get_metadata') && $request['path'] === 'id:private');
});

test('a legacy questions folder can be previewed and its source remains verifiable when saving', function (string $folderName): void {
    $this->entries[2] = array_replace($this->entries[2], ['name' => $folderName, 'path_lower' => $this->root.'/emails/fragenkatalog']);
    $this->entries[4]['path_lower'] = $this->root.'/emails/fragenkatalog/questions.md';
    fakeMcModuleSource($this);

    $data = $this->postJson($this->url, mcModuleRequest($this))->assertOk()
        ->assertJsonPath('preview.valid', true)
        ->assertJsonCount(40, 'preview.questions')
        ->assertJsonPath('module_source.source_path', $this->root.'/emails/fragenkatalog/questions.md')->json();

    expect($this->catalog->fresh()->questions)->toBe([]);
    $this->patchJson('/api/admin/multiple-choice/catalogs/'.$this->catalog->id, ['title' => $this->catalog->title,
        'questions' => $data['preview']['questions'], 'module_source_proofs' => [$data['source_proof']]])->assertOk();
    expect($this->catalog->fresh()->questions)->toHaveCount(40);
    expect($this->catalog->fresh()->module_sources[0]['source_path'])->toBe($this->root.'/emails/fragenkatalog/questions.md');
})->with(['Fragenkatalog', 'FRAGENKATALOG']);

test('two supported questions folders are rejected without choosing or changing the catalog', function (): void {
    $this->entries[] = ['.tag' => 'folder', 'id' => 'id:legacy', 'name' => 'Fragenkatalog',
        'path_lower' => $this->root.'/emails/fragenkatalog'];
    fakeMcModuleSource($this);

    $this->postJson($this->url, mcModuleRequest($this))->assertUnprocessable()
        ->assertJsonValidationErrors('handshake')
        ->assertJsonPath('errors.handshake.0', 'Im bestätigten Lernmodul sind mehrere mögliche Fragenordner vorhanden. Die Quelle ist nicht eindeutig.');

    expect($this->catalog->fresh()->questions)->toBe([]);
    expect($this->catalog->fresh()->module_sources)->toBeNull();
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
