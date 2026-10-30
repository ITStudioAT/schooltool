<?php

use App\Ai\Agents\TeachingExerciseDraftAgent;
use App\Models\DropboxConnection;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\TeachingCourseWork;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingWorkModuleLink;
use App\Models\User;
use App\Services\DropboxModuleLinks;
use App\Services\DropboxWorkImport;
use App\Services\TeachingWorkDispatchImport;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Ai\Prompts\AgentPrompt;
use Smalot\PdfParser\Parser;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('teacher', 'web');
    $this->school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $this->school->id, 'name' => '2026/27', 'concerns' => '2026/27']);
    enableSchoolToolModuleForTests($this->school, 'teaching');
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $this->teacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $year->id]);
    $this->teacher->assignRole('teacher');
    $area = TeachingEntryArea::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $year->id, 'user_id' => $this->teacher->id]);
    TeachingEntryDefinition::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $year->id,
        'user_id' => $this->teacher->id, 'teaching_entry_area_id' => $area->id, 'short_name' => 'LF',
        'category' => 'Benotung', 'name' => 'Leistungsfeststellung', 'has_properties' => true, 'properties_mode' => 'points', 'maximum_points' => 5]);
    $this->course = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $year->id,
        'user_id' => $this->teacher->id, 'title' => 'INF - 3B - 1', 'classes' => ['3B'], 'teaching_entry_area_id' => $area->id]);
    $this->work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id, 'type' => 'LF',
        'title' => 'Datenschutz', 'date_for_all_groups' => '2026-11-10', 'finish_until_date' => '2026-11-12',
        'finish_until_time' => '20:15', 'is_group_work' => false, 'groups' => [], 'status' => []]);
    $this->student = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $year->id,
        'first_name' => 'Änne', 'last_name' => 'Ćelebić', 'schoolclass' => '3B', 'email' => 'private@example.test']);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $this->student->id]);
    $this->url = '/api/admin/teaching/course_works/'.$this->work->id;
    $this->payload = ['execution' => 'openai', 'request_id' => (string) Str::uuid(), 'expected_test_id' => null,
        'date' => '2026-11-10', 'email_form_policy' => 'Keine Formabzüge für Betreff, Anrede, Inhalt und Signatur.'];
    $this->actingAs($this->teacher, 'sanctum');
});

/** External Dropbox boundary; folder contents and create-only conflicts remain observable. */
function exerciseGenerationDropbox(object $test): object
{
    config(['ai.providers.openai.key' => 'test-key', 'cache.default' => 'array', 'laravel-pdf.driver' => 'dompdf']);
    $connection = DropboxConnection::factory()->create(['user_id' => $test->teacher->id]);
    TeachingWorkModuleLink::query()->create(['teaching_course_work_id' => $test->work->id, 'dropbox_connection_id' => $connection->id,
        'account_id' => $connection->account_id, 'module_id' => 'a263b873-4f27-4167-90f0-7d94ea0f67fa', 'schema_version' => 1,
        'handshake_file_id' => 'id:handshake', 'handshake_revision' => 'rev1', 'folder_id' => 'id:module', 'folder_name' => 'Modul', 'folder_path' => '/Materialien/Modul']);
    $state = (object) ['folders' => [
        'id:module' => ['.tag' => 'folder', 'id' => 'id:module', 'name' => 'Modul', 'path_lower' => '/materialien/modul', 'path_display' => '/Materialien/Modul'],
        'id:template' => ['.tag' => 'folder', 'id' => 'id:template', 'name' => 'Assessment Template', 'path_lower' => '/materialien/modul/assessment template'],
    ], 'files' => [], 'writes' => [], 'failAt' => null, 'revision' => 'r1', 'source' => '# Aufgaben'."\n".'Datenschutzfälle erklären. 5 Punkte.'];
    $source = fn (): array => ['.tag' => 'file', 'id' => 'id:source', 'name' => 'Vorlage.md', 'rev' => $state->revision,
        'size' => strlen($state->source), 'path_lower' => '/materialien/modul/assessment template/vorlage.md'];
    $dropbox = Mockery::mock(DropboxWorkImport::class)->makePartial();
    app()->instance(DropboxWorkImport::class, $dropbox);
    $dropbox->shouldReceive('connection')->andReturn($connection);
    $dropbox->shouldReceive('canWrite')->andReturn(true);
    $dropbox->shouldReceive('metadata')->andReturnUsing(fn (DropboxConnection $connection, string $id): array => $state->folders[$id]);
    $dropbox->shouldReceive('findModuleEntry')->andReturnUsing(function (DropboxConnection $connection, string $path) use ($state): ?array {
        return collect($state->folders)->first(fn (array $folder): bool => strcasecmp($folder['path_lower'], $path) === 0);
    });
    $dropbox->shouldReceive('readModuleApi')->andReturnUsing(function (DropboxConnection $connection, string $endpoint, array $parameters) use ($state, $source) {
        $body = match ($endpoint) {
            'files/list_folder' => ['entries' => $parameters['path'] === 'id:template' ? [$source()]
                : array_values(array_filter($state->folders, fn (array $folder): bool => dirname($folder['path_lower']) === $state->folders[$parameters['path']]['path_lower'])), 'has_more' => false],
            'files/download' => $state->source,
            'files/get_metadata' => $source(),
            default => throw new RuntimeException('Unexpected Dropbox call'),
        };

        return new Illuminate\Http\Client\Response(new Response(200, [], is_string($body) ? $body : json_encode($body)));
    });
    $dropbox->shouldReceive('createExerciseChildFolder')->andReturnUsing(function (DropboxConnection $connection, string $parent, string $name, string $expected) use ($state): array {
        expect($expected)->toBe($state->folders[$parent]['path_lower']);
        $path = $expected.'/'.mb_strtolower($name);
        expect(collect($state->folders)->contains('path_lower', $path))->toBeFalse();
        $id = 'id:created'.count($state->folders);
        $state->folders[$id] = ['.tag' => 'folder', 'id' => $id, 'name' => $name, 'path_lower' => $path, 'path_display' => $path];

        return $state->folders[$id];
    });
    $dropbox->shouldReceive('writeDocument')->andReturnUsing(function (DropboxConnection $connection, string $folder, string $name, string $bytes, ?string $revision) use ($state): string {
        if ($name === $state->failAt) {
            throw new RuntimeException('Simulated upload failure');
        }
        $key = $folder.'/'.$name;
        expect(isset($state->files[$key]) ? $state->files[$key]['rev'] : null)->toBe($revision);
        $rev = 'written'.count($state->writes);
        $state->files[$key] = ['contents' => $bytes, 'rev' => $rev];
        $state->writes[] = ['folder' => $folder, 'name' => $name, 'revision' => $revision, 'bytes' => $bytes];

        return $rev;
    });
    $dropbox->shouldReceive('readDocument')->andReturnUsing(fn (DropboxConnection $connection, string $folder, string $name): ?array => $state->files[$folder.'/'.$name] ?? null);
    $modules = Mockery::mock(DropboxModuleLinks::class)->makePartial();
    app()->instance(DropboxModuleLinks::class, $modules);
    $modules->shouldReceive('exercise')->andReturnUsing(function () use ($state): array {
        $package = collect($state->writes)->firstWhere('name', 'Schooltool-Vorbereitung.json');

        return $package ? ['status' => 'found', 'assignment_status' => 'ready', 'folder' => $state->folders[$package['folder']]
            + ['path' => $state->folders[$package['folder']]['path_lower']]] : ['status' => 'missing'];
    });

    return $state;
}

function exerciseGenerationDraft(array $changes = []): array
{
    return array_replace(['assignment_markdown' => "# Aufgaben\n\n## Aufgabe 1: Datenschutz\n\nErklären Sie die Datenschutzfälle.",
        'criteria_markdown' => "# Konkrete Kriterien\n\nDatenschutzfälle fachlich richtig erklären: 5 Punkte.\n\nKein Formabzug für Betreff, Anrede, Inhalt und Signatur.",
        'criteria' => [['title' => 'Datenschutzfälle', 'description' => 'Fachlich richtige Erklärung', 'points' => 5]],
        'questions' => [], 'warnings' => []], $changes);
}

test('OpenAI prepares reviewable content without creating folders or sending personal roster data', function (): void {
    Http::preventStrayRequests();
    Mail::fake();
    $state = exerciseGenerationDropbox($this);
    TeachingExerciseDraftAgent::fake([exerciseGenerationDraft()])->preventStrayPrompts();
    $before = $this->work->fresh()->getAttributes();
    $response = $this->postJson($this->url.'/prepare-exercise', $this->payload)->assertOk()->assertJsonPath('generation.ready', true);
    expect($response->json('generation.id'))->not->toBeEmpty();
    expect($response->json('generation.sources.0.sha256'))->toBe(hash('sha256', $state->source));
    expect($state->writes)->toBe([]);
    expect($state->folders)->toHaveCount(2);
    expect($this->work->fresh()->getAttributes())->toBe($before);
    TeachingExerciseDraftAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->provider->name() === 'openai'
        && str_contains($prompt->prompt, '# Auftrag: Leistungsfeststellung anlegen')
        && ! str_contains($prompt->prompt, 'private@example.test') && ! str_contains($prompt->prompt, 'Ćelebić')
        && $prompt->attachments->count() === 1);
    Http::assertNothingSent();
    Mail::assertNothingSent();
});

test('confirmed preview creates and verifies the full Dropbox package once without releasing or grading the work', function (): void {
    Http::preventStrayRequests();
    Mail::fake();
    $state = exerciseGenerationDropbox($this);
    TeachingExerciseDraftAgent::fake([exerciseGenerationDraft()])->preventStrayPrompts();
    $id = $this->postJson($this->url.'/prepare-exercise', $this->payload)->assertOk()->json('generation.id');
    $before = $this->work->fresh()->getAttributes();
    $payload = ['generation_id' => $id, 'approved' => true];
    $this->postJson($this->url.'/store-exercise', $payload)->assertOk()->assertJsonPath('data.status', 'created');
    $writes = count($state->writes);
    $this->postJson($this->url.'/store-exercise', $payload)->assertOk();
    expect($state->writes)->toHaveCount($writes);
    $names = array_column($state->writes, 'name');
    foreach (['Aufgabenstellung_datenschutz.md', 'Aufgabenstellung_datenschutz.pdf', 'Beurteilungskriterien_datenschutz.md',
        'Beurteilungskriterien_datenschutz.pdf', 'Beurteilungskriterien_datenschutz.json', 'Schuelerliste.json',
        'Schooltool-Quelle.json', 'handshake.json', 'Anlage_und_Quellen.json', 'History.json', 'Schooltool-Vorbereitung.json'] as $name) {
        expect($names)->toContain($name);
    }
    $metadata = json_decode(collect($state->writes)->firstWhere('name', 'Anlage_und_Quellen.json')['bytes'], true);
    expect($metadata['Abgabeschluss'])->toBe('2026-11-12T20:15:00+01:00');
    expect($metadata['MitgeteilteAbgabefrist'])->toBeNull();
    expect($metadata['Schooltool_Arbeit_ID'])->toBe($this->work->id);
    $package = collect($state->writes)->firstWhere('name', 'Schooltool-Vorbereitung.json')['bytes'];
    expect(app(TeachingWorkDispatchImport::class)->parse($package)['mode'])->toBe('prepared');
    $pdf = collect($state->writes)->firstWhere('name', 'Aufgabenstellung_datenschutz.pdf')['bytes'];
    expect($pdf)->toStartWith('%PDF-');
    expect((new Parser)->parseContent($pdf)->getText())->toContain('Datenschutzfälle');
    expect($this->work->fresh()->getAttributes())->toBe($before);
    Http::assertNothingSent();
    Mail::assertNothingSent();
});

test('returns clarification questions without a writable plan', function (): void {
    $state = exerciseGenerationDropbox($this);
    TeachingExerciseDraftAgent::fake([exerciseGenerationDraft(['questions' => ['Welche Aufgaben sind freigegeben?']])]);
    $this->postJson($this->url.'/prepare-exercise', $this->payload)->assertOk()->assertJsonPath('generation.ready', false)
        ->assertJsonMissingPath('generation.id')->assertJsonPath('generation.questions.0', 'Welche Aufgaben sind freigegeben?');
    expect($state->writes)->toBe([]);
});

test('rejects inconsistent generated points with 422 before any folder is created', function (): void {
    $state = exerciseGenerationDropbox($this);
    TeachingExerciseDraftAgent::fake([exerciseGenerationDraft(['criteria' => [['title' => 'Falsch', 'description' => 'Falsch', 'points' => 6]]])]);
    $this->postJson($this->url.'/prepare-exercise', $this->payload)->assertUnprocessable();
    expect($state->folders)->toHaveCount(2);
});

test('rejects changed work or source with 409 before writing the approved preview', function (string $changed): void {
    $state = exerciseGenerationDropbox($this);
    TeachingExerciseDraftAgent::fake([exerciseGenerationDraft()]);
    $id = $this->postJson($this->url.'/prepare-exercise', $this->payload)->assertOk()->json('generation.id');
    if ($changed === 'source') {
        $state->revision = 'r2';
    } else {
        $this->work->update(['title' => 'Geänderte Arbeit']);
    }
    $this->postJson($this->url.'/store-exercise', ['generation_id' => $id, 'approved' => true])->assertConflict();
    expect($state->writes)->toBe([]);
})->with(['source', 'work']);

test('protects a partial package and never retries into the existing folder', function (): void {
    $state = exerciseGenerationDropbox($this);
    TeachingExerciseDraftAgent::fake([exerciseGenerationDraft()]);
    $id = $this->postJson($this->url.'/prepare-exercise', $this->payload)->assertOk()->json('generation.id');
    $state->failAt = 'Aufgabenstellung_datenschutz.pdf';
    $payload = ['generation_id' => $id, 'approved' => true];
    $this->postJson($this->url.'/store-exercise', $payload)->assertUnprocessable()->assertJsonValidationErrors('generation');
    $writes = count($state->writes);
    $this->postJson($this->url.'/store-exercise', $payload)->assertConflict();
    expect($state->writes)->toHaveCount($writes);
});

test('requires the concrete form decision and explicit preview confirmation', function (): void {
    TeachingExerciseDraftAgent::fake()->preventStrayPrompts();
    unset($this->payload['email_form_policy']);
    $this->postJson($this->url.'/prepare-exercise', $this->payload)->assertUnprocessable()->assertJsonValidationErrors('email_form_policy');
    $this->postJson($this->url.'/store-exercise', ['generation_id' => (string) Str::uuid(), 'approved' => false])
        ->assertUnprocessable()->assertJsonValidationErrors('approved');
    TeachingExerciseDraftAgent::assertNeverPrompted();
});

test('rejects a missing OpenAI key without preparing MC or writing Dropbox', function (): void {
    config(['ai.providers.openai.key' => '']);
    TeachingExerciseDraftAgent::fake()->preventStrayPrompts();
    $this->postJson($this->url.'/prepare-exercise', $this->payload)->assertUnprocessable();
    TeachingExerciseDraftAgent::assertNeverPrompted();
});

test('rejects a foreign teacher for both API phases with 403', function (): void {
    TeachingExerciseDraftAgent::fake()->preventStrayPrompts();
    $other = User::factory()->create(['school_id' => School::factory()->create()->id]);
    $other->assignRole('teacher');
    $this->actingAs($other, 'sanctum')->postJson($this->url.'/prepare-exercise', $this->payload)->assertForbidden();
    $this->postJson($this->url.'/store-exercise', ['generation_id' => (string) Str::uuid(), 'approved' => true])->assertForbidden();
    TeachingExerciseDraftAgent::assertNeverPrompted();
});

test('blocks an incomplete package with the same work ID even at another date', function (): void {
    $state = exerciseGenerationDropbox($this);
    $state->folders['id:root'] = ['.tag' => 'folder', 'id' => 'id:root', 'name' => 'Leistungsfeststellung', 'path_lower' => '/materialien/modul/leistungsfeststellung'];
    $state->folders['id:older'] = ['.tag' => 'folder', 'id' => 'id:older', 'name' => 'Andere Durchführung', 'path_lower' => '/materialien/modul/leistungsfeststellung/andere'];
    $state->files['id:older/Anlage_und_Quellen.json'] = ['contents' => json_encode(['Schooltool_Arbeit_ID' => $this->work->id,
        'Schooltool_Kurs_ID' => $this->course->id, 'Durchfuehrungsdatum' => '2026-10-01']), 'rev' => 'old1'];
    TeachingExerciseDraftAgent::fake()->preventStrayPrompts();
    $this->postJson($this->url.'/prepare-exercise', $this->payload)->assertConflict();
    expect($state->writes)->toBe([]);
    TeachingExerciseDraftAgent::assertNeverPrompted();
});

test('blocks already released work before generation even without an online assignment receipt', function (): void {
    $state = exerciseGenerationDropbox($this);
    $this->work->update(['status' => ['student_distribution' => ['published_at' => now()->toIso8601String()]]]);
    TeachingExerciseDraftAgent::fake()->preventStrayPrompts();
    $this->postJson($this->url.'/prepare-exercise', $this->payload)->assertConflict();
    expect($state->writes)->toBe([]);
    TeachingExerciseDraftAgent::assertNeverPrompted();
});

test('rejects remote images in generated PDFs before creating a writable preview', function (): void {
    $state = exerciseGenerationDropbox($this);
    TeachingExerciseDraftAgent::fake([exerciseGenerationDraft(['assignment_markdown' => '# Aufgaben'."\n\n".'![Extern](https://example.test/tracker.png)'])]);
    $this->postJson($this->url.'/prepare-exercise', $this->payload)->assertUnprocessable()->assertJsonMissingPath('generation.id');
    expect($state->writes)->toBe([]);
});
