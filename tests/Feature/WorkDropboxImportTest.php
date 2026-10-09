<?php

use App\Models\DropboxConnection;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\User;
use App\Services\DropboxWorkImport;
use App\Services\TeachingCourseWorkEntrySyncService;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Support\TeachingWorkDispatchFixture;
use Tests\Support\TeachingWorkJsonFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('local');
    config(['app.url' => 'http://localhost:8000', 'services.dropbox.client_id' => 'test-client',
        'services.dropbox.client_secret' => 'test-secret', 'services.dropbox.redirect_uri' => 'http://localhost:8000/admin/teaching/dropbox/callback',
        'schooltool.preview.instance' => false]);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    enableSchoolToolModuleForTests($this->school, 'teaching');
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $this->user = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id])->assignRole('admin');
    $this->course = TeachingCourse::factory()->forSchool($this->school)->forSchoolyear($this->schoolyear)->forTeacher($this->user)->create();
    $this->work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $this->url = '/api/admin/teaching/course_works/'.$this->work->id.'/dropbox';
    $this->actingAs($this->user, 'sanctum');
});

function dropboxConnectionForWork(object $context): DropboxConnection
{
    $connection = DropboxConnection::factory()->create(['user_id' => $context->user->id]);
    TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $connection->id,
        'teaching_course_work_id' => $context->work->id, 'folder_id' => 'id:workfolder', 'folder_name' => 'Leistungsarbeit']);

    return $connection;
}

/** @param array<string, string> $contents */
function fakeDropboxWorkFiles(array $contents, ?Closure $downloadResponse = null, ?Factory $factory = null): void
{
    Http::swap($factory ?? new Factory);
    Http::preventStrayRequests();
    Http::fake(function (Request $request) use ($contents, $downloadResponse) {
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'test-access']);
        }
        if (str_ends_with($request->url(), '/files/get_metadata')) {
            return Http::response(['.tag' => 'folder', 'id' => 'id:workfolder', 'name' => 'Leistungsarbeit', 'path_lower' => '/leistungsarbeit']);
        }
        if (str_ends_with($request->url(), '/files/list_folder')) {
            return Http::response(['has_more' => false, 'entries' => collect($contents)->map(fn (string $content, string $path): array => [
                '.tag' => 'file', 'name' => basename($path), 'path_lower' => '/leistungsarbeit/'.mb_strtolower($path),
                'rev' => hash('sha256', $path), 'size' => strlen($content),
            ])->values()->all()]);
        }
        if (str_ends_with($request->url(), '/files/download')) {
            $argument = json_decode($request->header('Dropbox-API-Arg')[0], true);
            foreach ($contents as $path => $content) {
                if ($argument['path'] === 'rev:'.hash('sha256', $path)) {
                    return $downloadResponse ? $downloadResponse($path, $content) : Http::response($content);
                }
            }
        }

        throw new RuntimeException('Unexpected Dropbox request');
    });
}

function dropboxAssessmentPackage(object $context): array
{
    $context->schoolyear->update(['name' => '2026/27', 'concerns' => '2026/27']);
    $area = TeachingEntryArea::factory()->create(['school_id' => $context->school->id,
        'schoolyear_id' => $context->schoolyear->id, 'user_id' => $context->user->id]);
    $context->course->update(['teaching_entry_area_id' => $area->id]);
    TeachingEntryDefinition::factory()->create(['school_id' => $context->school->id,
        'schoolyear_id' => $context->schoolyear->id, 'user_id' => $context->user->id,
        'teaching_entry_area_id' => $area->id, 'short_name' => 'MA', 'category' => 'Benotung',
        'has_properties' => true, 'properties_mode' => 'points', 'maximum_points' => 5]);
    $student = User::factory()->create(['school_id' => $context->school->id, 'first_name' => 'Ada', 'last_name' => 'Van Alpha', 'schoolclass' => '3B']);
    $context->course->teachingCourseStudents()->create(['user_id' => $student->id]);
    $context->work->update(['is_group_work' => true, 'groups' => [['name' => 'Gruppe 1', 'use_individual_grades' => true,
        'student_ids' => [$student->id], 'grade' => '2', 'comments' => [['student_id' => $student->id, 'comment' => 'Vorher']]]]]);
    app(TeachingCourseWorkEntrySyncService::class)->syncWork($context->work);

    return TeachingWorkJsonFixture::sign(TeachingWorkJsonFixture::package());
}

test('unconfigured Dropbox leaves quick import unavailable without network requests', function (): void {
    config(['services.dropbox.client_secret' => null]);
    $this->getJson($this->url)->assertOk()->assertJsonPath('configured', false)->assertJsonPath('can_quick_import', false);
    $this->postJson($this->url.'/connect')->assertUnprocessable();
    Http::assertNothingSent();
});

test('OAuth requests only read scopes and rejects wrong expired replayed and foreign-user state', function (string $case): void {
    $url = $this->postJson($this->url.'/connect')->assertOk()->json('url');
    parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
    expect($parameters['scope'])->toBe(implode(' ', DropboxWorkImport::SCOPES))
        ->and($parameters['token_access_type'])->toBe('offline');
    if ($case === 'expired') {
        $this->travel(11)->minutes();
    }
    if ($case === 'foreign-user') {
        $this->actingAs(User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id])->assignRole('admin'), 'sanctum');
    }
    $state = $case === 'wrong' ? str_repeat('b', 64) : $parameters['state'];
    $this->get('/admin/teaching/dropbox/callback?'.http_build_query(['state' => $state, 'code' => 'test-code']))->assertForbidden();
    $this->get('/admin/teaching/dropbox/callback?'.http_build_query(['state' => $parameters['state'], 'code' => 'test-code']))->assertForbidden();
    $this->assertDatabaseCount('dropbox_connections', 0);
    Http::assertNothingSent();
})->with(['wrong', 'expired', 'foreign-user']);

test('OAuth stores encrypted refresh credentials and reconnecting a different account clears folder mappings', function (): void {
    $connection = dropboxConnectionForWork($this);
    Http::fake(['api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'test-access', 'refresh_token' => 'new-refresh',
        'account_id' => 'dbid:new-account', 'scope' => implode(' ', DropboxWorkImport::SCOPES)])]);
    $url = $this->postJson($this->url.'/connect')->assertOk()->json('url');
    parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
    $callback = '/admin/teaching/dropbox/callback?'.http_build_query(['state' => $parameters['state'], 'code' => 'test-code']);
    $this->get($callback)->assertRedirectContains('dropbox=connected');
    expect($connection->fresh()->credentials)->toBe(['refresh_token' => 'new-refresh'])
        ->and(DB::table('dropbox_connections')->value('credentials'))->not->toContain('new-refresh')
        ->and($connection->fresh()->toArray())->not->toHaveKey('credentials');
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
    $this->get($callback)->assertForbidden();
});

test('redirect misconfiguration preview installations and cancelled OAuth cannot connect', function (): void {
    config(['services.dropbox.redirect_uri' => 'https://unrelated.example/callback']);
    $this->postJson($this->url.'/connect')->assertUnprocessable();
    config(['services.dropbox.redirect_uri' => 'http://localhost:8000/admin/teaching/dropbox/callback', 'schooltool.preview.instance' => true]);
    $this->postJson($this->url.'/connect')->assertNotFound();
    config(['schooltool.preview.instance' => false]);
    $url = $this->postJson($this->url.'/connect')->assertOk()->json('url');
    parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
    $this->get('/admin/teaching/dropbox/callback?'.http_build_query(['state' => $parameters['state'], 'error' => 'access_denied']))->assertRedirectContains('dropbox=cancelled');
    Http::assertNothingSent();
});

test('folder assignment validates Dropbox identity and is private to the user and work', function (): void {
    $connection = DropboxConnection::factory()->create(['user_id' => $this->user->id]);
    Http::fake(['api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'test-access']),
        'api.dropboxapi.com/2/files/list_folder' => Http::response(['entries' => [['.tag' => 'folder', 'id' => 'id:folder', 'name' => 'Arbeit']], 'has_more' => false]),
        'api.dropboxapi.com/2/files/get_metadata' => Http::response(['.tag' => 'folder', 'id' => 'id:folder', 'name' => 'Arbeit', 'path_lower' => '/arbeit'])]);
    $this->getJson($this->url.'/folders')->assertOk()->assertJsonPath('folders.0.id', 'id:folder');
    $this->postJson($this->url.'/folder', ['folder_id' => 'id:folder'])->assertOk()->assertJsonPath('can_quick_import', true);
    $otherWork = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $this->getJson('/api/admin/teaching/course_works/'.$otherWork->id.'/dropbox')->assertOk()->assertJsonPath('can_quick_import', false);
    $this->postJson($this->url.'/folder', ['folder_id' => 'https://unrelated.example'])->assertUnprocessable();
    $this->actingAs(User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id])->assignRole('admin'), 'sanctum');
    $this->getJson($this->url)->assertOk()->assertJsonPath('connected', false)->assertJsonPath('folder', null);
    expect($connection->fresh()->credentials)->not->toBeEmpty();
});

test('foreign teachers schools and students cannot access Dropbox work endpoints', function (string $case): void {
    $attributes = $case === 'school' ? [] : ['school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id];
    $this->actingAs(User::factory()->create($attributes)->assignRole($case === 'student' ? 'student' : 'teacher'), 'sanctum');
    $this->getJson($this->url)->assertForbidden();
    $this->postJson($this->url.'/connect')->assertForbidden();
    $this->postJson($this->url.'/folder', ['folder_id' => 'id:folder'])->assertForbidden();
    $this->postJson($this->url.'/import')->assertForbidden();
    $this->deleteJson($this->url.'/connection')->assertForbidden();
    Http::assertNothingSent();
})->with(['teacher', 'school', 'student']);

test('Dropbox previews fresh content without writes rejects stale apply and imports only after separate confirmation', function (): void {
    $package = dropboxAssessmentPackage($this);
    dropboxConnectionForWork($this);
    $before = $this->work->fresh()->getAttributes();
    fakeDropboxWorkFiles(['Schooltool-Bewertungen.json' => json_encode($package)]);
    $preview = $this->postJson($this->url.'/import')->assertOk()->assertJsonPath('preview.can_import', true)->json('preview');
    expect($this->work->fresh()->getAttributes())->toBe($before)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
    $package['records'][0]['comment'] = 'Geänderter Cloudstand';
    $package = TeachingWorkJsonFixture::sign($package);
    fakeDropboxWorkFiles(['Schooltool-Bewertungen.json' => json_encode($package)]);
    $this->postJson($this->url.'/import', ['apply' => true, 'hash' => $preview['hash']])->assertConflict();
    expect($this->work->fresh()->getAttributes())->toBe($before);
    $fresh = $this->postJson($this->url.'/import')->assertOk()->json('preview');
    expect($fresh['hash'])->not->toBe($preview['hash']);
    $this->postJson($this->url.'/import', ['apply' => true, 'hash' => $fresh['hash']])->assertOk();
    expect($this->work->fresh()->status['assessment_json_imports'])->not->toBeEmpty();
});

test('missing malformed ambiguous and incomplete Dropbox packages never apply', function (string $case): void {
    dropboxConnectionForWork($this);
    $before = $this->work->fresh()->getAttributes();
    $package = TeachingWorkJsonFixture::package();
    $contents = ['Schooltool-Bewertungen.json' => json_encode($package)];
    if ($case === 'missing') {
        $contents = [];
    }
    if ($case === 'malformed') {
        $contents['Schooltool-Bewertungen.json'] = 'invalid';
    }
    if ($case === 'ambiguous') {
        $contents['Beurteilungen/Schooltool-Bewertungen.json'] = json_encode($package);
    }
    if ($case === 'missing pdf') {
        $package['overview_pdf'] = ['filename' => 'missing.pdf', 'sha256' => str_repeat('a', 64)];
        $contents['Schooltool-Bewertungen.json'] = json_encode($package);
    }
    if ($case === 'traversal') {
        $package['overview_pdf'] = ['filename' => '../private.pdf'];
        $contents['Schooltool-Bewertungen.json'] = json_encode($package);
    }
    if ($case === 'oversize') {
        $contents['Schooltool-Bewertungen.json'] = str_repeat('x', 262145);
    }
    fakeDropboxWorkFiles($contents);
    $this->postJson($this->url.'/import')->assertUnprocessable()->assertJsonValidationErrors('dropbox');
    expect($this->work->fresh()->getAttributes())->toBe($before)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['missing', 'malformed', 'ambiguous', 'missing pdf', 'traversal', 'oversize']);

test('revoked connection and missing folder provide recoverable errors and disconnect removes only owned mappings', function (): void {
    $connection = dropboxConnectionForWork($this);
    Http::fake(['api.dropboxapi.com/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400)]);
    $this->postJson($this->url.'/import')->assertUnprocessable();
    expect($connection->fresh()->revoked_at)->not->toBeNull();
    $this->getJson($this->url)->assertOk()->assertJsonPath('can_quick_import', false);
    $connection->update(['revoked_at' => null]);
    Http::fake(['api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'test-access']),
        'api.dropboxapi.com/2/files/get_metadata' => Http::response(['error' => ['.tag' => 'path']], 409)]);
    $this->postJson($this->url.'/import')->assertUnprocessable()->assertJsonValidationErrors('dropbox');
    $other = DropboxConnection::factory()->create();
    $this->deleteJson($this->url.'/connection')->assertOk()->assertJsonPath('connected', false);
    $this->assertDatabaseMissing('dropbox_connections', ['id' => $connection->id]);
    $this->assertDatabaseHas('dropbox_connections', ['id' => $other->id]);
    $this->assertDatabaseCount('teaching_work_dropbox_folders', 0);
});

test('OAuth rejects additional write permissions without storing credentials', function (): void {
    Http::fake(['api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'test-access', 'refresh_token' => 'refresh',
        'account_id' => 'dbid:account', 'scope' => implode(' ', DropboxWorkImport::SCOPES).' files.content.write'])]);
    $url = $this->postJson($this->url.'/connect')->assertOk()->json('url');
    parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
    $this->get('/admin/teaching/dropbox/callback?'.http_build_query(['state' => $parameters['state'], 'code' => 'test-code']))->assertRedirectContains('dropbox=failed');
    $this->assertDatabaseCount('dropbox_connections', 0);
});

test('Dropbox downloads referenced PDFs and validates their hashes through the existing importer', function (): void {
    $package = dropboxAssessmentPackage($this);
    dropboxConnectionForWork($this);
    $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
    $package['overview_pdf'] = ['filename' => 'Gesamtübersicht.pdf', 'sha256' => hash('sha256', $pdf)];
    $package = TeachingWorkJsonFixture::sign($package);
    fakeDropboxWorkFiles(['Beurteilungen/Schooltool-Bewertungen.json' => json_encode($package), 'Beurteilungen/Gesamtübersicht.pdf' => $pdf]);
    $this->postJson($this->url.'/import')->assertOk()->assertJsonPath('preview.can_import', true);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    fakeDropboxWorkFiles(['Beurteilungen/Schooltool-Bewertungen.json' => json_encode($package), 'Beurteilungen/Gesamtübersicht.pdf' => $pdf.'changed']);
    $this->postJson($this->url.'/import')->assertUnprocessable()->assertJsonValidationErrors('package');
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('Dropbox includes recognized result dispatch protocols in the existing preview validation', function (): void {
    $package = dropboxAssessmentPackage($this);
    dropboxConnectionForWork($this);
    fakeDropboxWorkFiles(['Schooltool-Bewertungen.json' => json_encode($package),
        'Versand/Ergebnisse/Versand_2026-10-04_02-15-39/Versandprotokoll.txt' => TeachingWorkDispatchFixture::officeResultsText()]);
    $this->postJson($this->url.'/import')->assertOk()->assertJsonCount(1, 'preview.dispatches');
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('concurrent downloads keep PDF and protocol mappings when responses finish out of order and clean temporary files', function (): void {
    dropboxConnectionForWork($this);
    $package = TeachingWorkJsonFixture::package();
    $contents = [];
    $package['records'] = array_map(function (int $index) use (&$contents, $package): array {
        $name = 'Person_'.$index.'.pdf';
        $contents[$name] = '%PDF-1.4 unique '.$index;

        return array_replace($package['records'][0], ['pdf' => ['filename' => $name]]);
    }, range(1, 8));
    $contents['Schooltool-Bewertungen.json'] = json_encode($package);
    $contents['Versand/Aufgaben/Versand_2026-10-04_02-15-39/Versandprotokoll.txt'] = 'Task protocol';
    $contents['Versand/Ergebnisse/Versand_2026-10-05_02-15-39/Versandprotokoll.txt'] = 'Result protocol';
    $pending = [];
    $active = 0;
    $maximum = 0;
    /** Keep deferred fake responses asynchronous instead of waiting for transfer statistics. */
    $factory = new class extends Factory
    {
        public function fake($callback = null): static
        {
            $this->record();
            $this->stubCallbacks = collect([$callback]);

            return $this;
        }
    };
    fakeDropboxWorkFiles($contents, function (string $path, string $content) use (&$pending, &$active, &$maximum) {
        if (str_ends_with($path, '.json')) {
            return Http::response($content);
        }
        $promise = new Promise(fn () => Utils::queue()->run());
        $pending[] = [$promise, $content];
        $maximum = max($maximum, ++$active);
        Utils::queue()->add(function () use (&$pending, &$active): void {
            [$promise, $content] = array_pop($pending);
            $active--;
            $promise->resolve(new Response(200, [], $content));
        });

        return $promise;
    }, $factory);
    $temporaryPaths = [];
    app(DropboxWorkImport::class)->withImportFiles($this->user, $this->work, function (array $parameters, array $uploads) use ($contents, &$temporaryPaths): void {
        foreach ($uploads['pdfs'] as $upload) {
            expect($upload->get())->toBe($contents[$upload->getClientOriginalName()]);
            $temporaryPaths[] = $upload->getRealPath();
        }
        $temporaryPaths[] = $uploads['package']->getRealPath();
        expect($uploads['pdfs'])->toHaveCount(8);
        $documents = json_decode($parameters['documents'], true);
        expect(array_column($documents, 'text'))->toBe(['Task protocol', 'Result protocol'])
            ->and($documents[0]['path'])->toContain('/Versand/Aufgaben/')
            ->and($documents[1]['path'])->toContain('/Versand/Ergebnisse/');
    });
    expect($maximum)->toBeGreaterThan(1)->toBeLessThanOrEqual(4);
    foreach ($temporaryPaths as $path) {
        expect(is_file($path))->toBeFalse();
    }
});

test('one failed parallel download prevents any partial preview or import and cleans temporary files', function (string $failure): void {
    $package = dropboxAssessmentPackage($this);
    dropboxConnectionForWork($this);
    $pdf = "%PDF-1.4\n%%EOF\n";
    $package['overview_pdf'] = ['filename' => 'Overview.pdf', 'sha256' => hash('sha256', $pdf)];
    $package['records'][0]['pdf'] = ['filename' => 'Person.pdf', 'sha256' => hash('sha256', $pdf)];
    $package = TeachingWorkJsonFixture::sign($package);
    $before = $this->work->fresh()->getAttributes();
    $temporaryBefore = glob(sys_get_temp_dir().'/schooltool-dropbox-*');
    fakeDropboxWorkFiles(['Schooltool-Bewertungen.json' => json_encode($package), 'Overview.pdf' => $pdf, 'Person.pdf' => $pdf],
        function (string $path, string $content) use ($failure) {
            if ($path !== 'Person.pdf') {
                return Http::response($content);
            }

            return $failure === 'connection' ? Http::failedConnection() : Http::response('Provider detail must stay private', (int) $failure);
        });
    $response = $this->postJson($this->url.'/import')->assertUnprocessable()->assertJsonValidationErrors('dropbox');
    expect($response->getContent())->not->toContain('Provider detail must stay private');
    if ($failure === '429') {
        expect($response->getContent())->toContain('kurz warten');
    }
    expect($this->work->fresh()->getAttributes())->toBe($before)
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and(array_diff(glob(sys_get_temp_dir().'/schooltool-dropbox-*'), $temporaryBefore))->toBe([]);
})->with(['401', '409', '429', 'connection']);

test('combined file size is rejected before any parallel download begins', function (): void {
    dropboxConnectionForWork($this);
    $package = TeachingWorkJsonFixture::package();
    $package['overview_pdf'] = ['filename' => 'Overview.pdf'];
    $package['records'][0]['pdf'] = ['filename' => 'Person.pdf'];
    fakeDropboxWorkFiles(['Schooltool-Bewertungen.json' => json_encode($package), 'Overview.pdf' => str_repeat('x', 3 * 1024 * 1024),
        'Person.pdf' => str_repeat('x', 3 * 1024 * 1024)]);
    $this->postJson($this->url.'/import')->assertUnprocessable()->assertJsonValidationErrors('dropbox');
    Http::assertSentCount(4);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});
