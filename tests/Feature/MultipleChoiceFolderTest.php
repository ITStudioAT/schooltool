<?php

use App\Models\DropboxConnection;
use App\Models\McAttempt;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\User;
use App\Services\DropboxWorkImport;
use App\Services\MultipleChoice\McTestExchange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('local');
    config(['app.url' => 'http://localhost:8000', 'services.dropbox.client_id' => 'fixture-client',
        'services.dropbox.client_secret' => 'fixture-secret', 'services.dropbox.redirect_uri' => 'http://localhost:8000/admin/teaching/dropbox/callback',
        'services.postmark.token' => '', 'mail.from.address' => 'schooltool@example.test', 'schooltool.preview.instance' => false]);
});

function mcFolderContext(): array
{
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    $teacher = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id]);
    Role::findOrCreate('teacher', 'web');
    $teacher->assignRole('teacher');
    enableSchoolToolModuleForTests($school, 'teaching');
    grantSchoolToolLicenceForTests($school, 'Lehrertool');
    $questions = [['id' => 'q1', 'prompt' => 'Fixture question', 'type' => 'single', 'options' => [['id' => 'a', 'text' => 'A'], ['id' => 'b', 'text' => 'B']],
        'correct_option_ids' => ['a'], 'active' => true, 'reviewed' => true]];
    $catalog = McCatalog::create(['school_id' => $school->id, 'owner_id' => $teacher->id, 'title' => 'Fixture', 'questions' => $questions]);
    $test = McTest::create(['school_id' => $school->id, 'schoolyear_id' => $year->id, 'owner_id' => $teacher->id,
        'catalog_id' => $catalog->id, 'title' => 'Fixture test', 'status' => 'published', 'question_count' => 1, 'total_points' => 2,
        'duration_seconds' => 60, 'snapshot' => ['questions' => $questions]]);
    $student = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id]);
    $attempt = McAttempt::create(['test_id' => $test->id, 'student_id' => $student->id, 'student_name' => $student->first_name,
        'email' => $student->email, 'questions' => $questions, 'answers' => [], 'access_token' => 'fixture-access-token',
        'access_token_hash' => hash('sha256', 'fixture-access-token')]);

    return compact('school', 'year', 'teacher', 'test', 'attempt', 'student');
}

function mcFolderCompletedPackage(McTest $test): array
{
    $exchange = app(McTestExchange::class);
    $package = $exchange->export($test);
    $row = &$package['results'][0];
    $row['ancestors'] = [$row['revision']];
    $row['result'] = ['answers' => ['q1' => ['a']], 'started_at' => '2026-10-19T08:00:00+00:00',
        'submitted_at' => '2026-10-19T08:00:30+00:00', 'completed_at' => '2026-10-19T08:00:30+00:00',
        'completion_reason' => 'submitted', 'correct_count' => 1, 'score' => '2.00', 'percentage' => '100.00'];
    $row['revision'] = $exchange->hash($row['result']);

    return $package;
}

function mcFolderSuggestionContext(): array
{
    $context = mcFolderContext();
    $context['test']->catalog->update(['title' => 'E-Mails']);
    McTest::whereKey($context['test']->id)->update(['title' => 'E-Mails – 3B1', 'starts_at' => '2026-10-19 08:00:00']);
    $context['test']->refresh();
    $context['student']->update(['schoolclass' => '3B']);
    $context['course'] = TeachingCourse::factory()->forSchool($context['school'])->forSchoolyear($context['year'])
        ->forTeacher($context['teacher'])->create(['title' => 'INF - 3B - 1', 'classes' => ['3B']]);
    $context['course']->teachingCourseStudents()->create(['user_id' => $context['student']->id]);
    DropboxConnection::factory()->create(['user_id' => $context['teacher']->id]);

    return $context;
}

function fakeMcFolderSuggestions(array $candidates, ?array $remembered = null): void
{
    $root = '/schule/materialien/lerninhalte';
    $folder = fn (string $id, string $name, string $path): array => ['.tag' => 'folder', 'id' => $id, 'name' => $name, 'path_lower' => $path];
    Http::fake(function ($request) use ($root, $folder, $candidates, $remembered) {
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'fixture-access']);
        }
        if (str_ends_with($request->url(), '/files/get_metadata') && $remembered) {
            return Http::response($remembered);
        }
        if (str_ends_with($request->url(), '/files/search_v2')) {
            expect($request['query'])->toBeIn(['Lerninhalte', 'emails']);
            $entry = $request['query'] === 'Lerninhalte' ? $folder('id:root', 'Lerninhalte', $root) : $folder('id:topic', 'E-Mails', $root.'/e-mails');

            return Http::response(['matches' => [['metadata' => ['metadata' => $entry]]], 'has_more' => false]);
        }
        if (str_ends_with($request->url(), '/files/list_folder')) {
            $entries = match ($request['path']) {
                'id:topic' => [$folder('id:collection', 'Leistungsfeststellung', $root.'/e-mails/leistungsfeststellung')],
                'id:collection' => array_map(fn (array $entry): array => $folder($entry['id'], $entry['name'], $root.'/e-mails/leistungsfeststellung/'.mb_strtolower($entry['name'])), $candidates),
                default => [],
            };

            return Http::response(['entries' => $entries, 'has_more' => false]);
        }
        throw new RuntimeException('Unexpected suggestion request.');
    });
}

test('MC folder proposals reuse the work search with the proven course roster group and test date without saving', function (): void {
    $context = mcFolderSuggestionContext();
    fakeMcFolderSuggestions([
        ['id' => 'id:matching', 'name' => '2026-10-19_Informatik_3B_Gruppe-1'],
        ['id' => 'id:old', 'name' => '2026-10-18_Informatik_3B_Gruppe-1'],
        ['id' => 'id:wrong', 'name' => '2026-10-19_Informatik_4A_Gruppe-1'],
    ]);

    $this->actingAs($context['teacher'])->getJson('/api/admin/multiple-choice/tests/'.$context['test']->id.'/folder/folders?suggest=1')
        ->assertOk()->assertJsonPath('suggested_folder.id', 'id:matching')->assertJsonPath('folder', null)->assertJsonCount(2, 'suggestions');

    expect(Storage::disk('local')->allFiles('multiple-choice/test-folders'))->toBe([]);
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/files/upload') || str_contains($request->url(), '/files/delete'));
});

test('MC folder proposals show tied matches as choices and never infer a unique assignment', function (): void {
    $context = mcFolderSuggestionContext();
    fakeMcFolderSuggestions([
        ['id' => 'id:first', 'name' => '2026-10-19_Informatik_3B_Gruppe-1'],
        ['id' => 'id:second', 'name' => '2026-10-19_MC_Informatik_3B_Gruppe-1'],
    ]);

    $this->actingAs($context['teacher'])->getJson('/api/admin/multiple-choice/tests/'.$context['test']->id.'/folder/folders?suggest=1')
        ->assertOk()->assertJsonPath('suggested_folder', null)->assertJsonCount(2, 'suggestions');

    expect(Storage::disk('local')->allFiles('multiple-choice/test-folders'))->toBe([]);
});

test('MC folder proposals do not invent a course group when several owned courses have exactly the same participants', function (): void {
    $context = mcFolderSuggestionContext();
    $otherCourse = TeachingCourse::factory()->forSchool($context['school'])->forSchoolyear($context['year'])
        ->forTeacher($context['teacher'])->create(['title' => 'INF - 3B - 2', 'classes' => ['3B']]);
    $otherCourse->teachingCourseStudents()->create(['user_id' => $context['student']->id]);
    fakeMcFolderSuggestions([['id' => 'id:second', 'name' => '2026-10-19_Informatik_3B_Gruppe-2']]);

    $this->actingAs($context['teacher'])->getJson('/api/admin/multiple-choice/tests/'.$context['test']->id.'/folder/folders?suggest=1')
        ->assertOk()->assertJsonPath('suggested_folder', null)->assertJsonCount(1, 'suggestions');

    expect(Storage::disk('local')->allFiles('multiple-choice/test-folders'))->toBe([]);
});

test('MC folder proposals validate and reuse the saved Dropbox folder without searching again', function (): void {
    $context = mcFolderSuggestionContext();
    $url = '/api/admin/multiple-choice/tests/'.$context['test']->id.'/folder';
    fakeMcFolderSuggestions([], ['.tag' => 'folder', 'id' => 'id:saved', 'name' => '2026-10-19_Informatik_3B_Gruppe-1', 'path_lower' => '/saved']);
    $this->actingAs($context['teacher'])->putJson($url, ['provider' => 'dropbox', 'folder_id' => 'id:saved'])->assertOk()->assertJsonPath('folder.path', '/saved');
    $before = Storage::disk('local')->allFiles('multiple-choice/test-folders');

    $this->getJson($url.'/folders?suggest=1')->assertOk()->assertJsonPath('reused', true)->assertJsonPath('folder.folder_id', 'id:saved')
        ->assertJsonPath('folder.path', '/saved')->assertJsonCount(0, 'suggestions');

    expect(Storage::disk('local')->allFiles('multiple-choice/test-folders'))->toBe($before);
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/files/search') || str_contains($request->url(), '/files/list_folder'));
});

test('MC folder proposals reuse local choices without claiming a browser search and reject foreign access before contacting Dropbox', function (): void {
    $context = mcFolderSuggestionContext();
    $url = '/api/admin/multiple-choice/tests/'.$context['test']->id.'/folder';
    $this->actingAs($context['teacher'])->putJson($url, ['provider' => 'local', 'name' => 'Chosen local folder'])->assertOk();
    $this->getJson($url.'/folders?suggest=1')->assertOk()->assertJsonPath('reused', true)->assertJsonPath('folder.provider', 'local');
    $other = User::factory()->create(['school_id' => $context['school']->id]);
    $other->assignRole('teacher');

    $this->actingAs($other)->getJson($url.'/folders?suggest=1')->assertNotFound();
    Http::assertNothingSent();
});

test('MC folder selection rejects a wrong class or course group rather than storing the proposed folder', function (): void {
    $context = mcFolderSuggestionContext();
    fakeMcFolderSuggestions([], ['.tag' => 'folder', 'id' => 'id:wrong', 'name' => '2026-10-19_Informatik_3B_Gruppe-2', 'path_lower' => '/wrong']);

    $this->actingAs($context['teacher'])->putJson('/api/admin/multiple-choice/tests/'.$context['test']->id.'/folder',
        ['provider' => 'dropbox', 'folder_id' => 'id:wrong'])->assertUnprocessable();

    expect(Storage::disk('local')->allFiles('multiple-choice/test-folders'))->toBe([]);
});

test('MC reconnect explicitly requests write permission and returns to the selected test after consent', function (): void {
    $context = mcFolderContext();
    $this->actingAs($context['teacher']);
    $response = $this->postJson('/api/admin/multiple-choice/tests/'.$context['test']->id.'/folder/connect')->assertOk();
    parse_str(parse_url($response->json('url'), PHP_URL_QUERY), $parameters);
    expect(explode(' ', $parameters['scope']))->toBe([...DropboxWorkImport::SCOPES, 'files.content.write']);
    Http::fake(['https://api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'fixture-access',
        'refresh_token' => 'fixture-refresh', 'account_id' => 'dbid:fixture', 'scope' => $parameters['scope']])]);

    $this->get('/admin/teaching/dropbox/callback?'.http_build_query(['state' => $parameters['state'], 'code' => 'fixture-code']))->assertRedirectContains('folder=1');

    $this->getJson('/api/admin/multiple-choice/tests/'.$context['test']->id.'/folder')->assertOk()->assertJsonPath('connected', true)->assertJsonPath('writable', true);
    $connection = DropboxConnection::where('user_id', $context['teacher']->id)->sole();
    expect($connection->getRawOriginal('credentials'))->not->toContain('fixture-refresh');
    $this->get('/admin/teaching/dropbox/callback?'.http_build_query(['state' => $parameters['state'], 'code' => 'fixture-code']))->assertForbidden();
});

test('MC local folder survives later requests and is hidden from another teacher', function (): void {
    $context = mcFolderContext();
    $url = '/api/admin/multiple-choice/tests/'.$context['test']->id.'/folder';
    $this->actingAs($context['teacher'])->putJson($url, ['provider' => 'local', 'name' => 'Testordner E-Mails'])->assertOk();
    $this->getJson($url)->assertJsonPath('folder.name', 'Testordner E-Mails')->assertJsonPath('folder.provider', 'local');
    $other = User::factory()->create(['school_id' => $context['school']->id, 'schoolyear_id' => $context['year']->id]);
    $other->assignRole('teacher');

    $this->actingAs($other)->getJson($url)->assertNotFound();
    $this->putJson($url, ['provider' => 'local', 'name' => 'Wrong'])->assertNotFound();
});

test('remembered exports preserve their last shared revision and block an externally changed file', function (): void {
    $context = mcFolderContext();
    $url = '/api/admin/multiple-choice/tests/'.$context['test']->id.'/folder';
    $this->actingAs($context['teacher'])->putJson($url, ['provider' => 'local', 'name' => 'Fixture folder'])->assertOk();
    $first = $this->postJson($url.'/export')->assertOk()->json('package');
    expect($first['kind'])->toBe('schooltool.multiple_choice_links');
    expect($first['participants'])->toHaveCount(1);
    $this->postJson($url.'/remember', ['package' => $first])->assertOk();
    McAttempt::whereKey($context['attempt']->id)->update(['answers' => '{"q1":["a"]}', 'started_at' => '2026-10-19 10:00:00',
        'submitted_at' => '2026-10-19 10:00:30', 'completed_at' => '2026-10-19 10:00:30', 'completion_reason' => 'submitted',
        'correct_count' => 1, 'score' => 2, 'percentage' => 100]);

    $updated = $this->postJson($url.'/export', ['previous' => $first])->assertOk()->json('package');

    expect($updated['exchange']['results'][0]['ancestors'])->toContain($first['exchange']['results'][0]['revision']);
    expect($updated['exchange']['results'][0]['result']['score'])->toBe('2.00');
    $external = $first;
    $external['exchange']['results'][0]['revision'] = str_repeat('a', 64);
    $this->postJson($url.'/export', ['previous' => $external])->assertUnprocessable();
});

test('disabled access links still allow a result-only test exchange without exposing a personal access link', function (): void {
    $context = mcFolderContext();
    McTest::whereKey($context['test']->id)->update(['access_links_disabled_at' => now()]);
    $url = '/api/admin/multiple-choice/tests/'.$context['test']->id.'/folder';

    $response = $this->actingAs($context['teacher'])->postJson($url.'/export')->assertOk();

    $response->assertJsonPath('package.kind', 'schooltool.multiple_choice_exchange');
    expect($response->json('package.exchange.results'))->toHaveCount(1);
    expect($response->getContent())->not->toContain('access_url', 'fixture-access-token');
    $this->postJson($url.'/import', ['package' => $response->json('package')])->assertOk()->assertJsonPath('changes', []);
});

test('MC completed results round trip through preview and apply without duplicating results or changing access links', function (): void {
    $context = mcFolderContext();
    $url = '/api/admin/multiple-choice/tests/'.$context['test']->id.'/folder/import';
    $package = mcFolderCompletedPackage($context['test']);
    $token = $context['attempt']->getRawOriginal('access_token');
    $this->actingAs($context['teacher']);
    $preview = $this->postJson($url, ['package' => $package])->assertOk()->assertJsonPath('conflicts', []);
    expect($preview->json('changes'))->toHaveCount(1);
    expect($context['attempt']->fresh()->completed_at)->toBeNull();

    $this->postJson($url, ['package' => $package, 'apply' => true, 'hash' => $preview->json('hash')])->assertOk()->assertJsonPath('imported', 1);

    expect($context['attempt']->fresh()->score)->toBe('2.00');
    expect($context['attempt']->fresh()->answers)->toBe(['q1' => ['a']]);
    expect($context['attempt']->fresh()->getRawOriginal('access_token'))->toBe($token);
    $again = $this->postJson($url, ['package' => $package])->assertOk()->assertJsonPath('unchanged', 1)->assertJsonPath('changes', []);
    $this->postJson($url, ['package' => $package, 'apply' => true, 'hash' => $again->json('hash')])->assertOk()->assertJsonPath('imported', 0);
    expect(Storage::disk('local')->allFiles('multiple-choice/exchange-history'))->toHaveCount(1);
});

test('MC import rejects forged scores and unknown options and test identities without changing results', function (string $change): void {
    $context = mcFolderContext();
    $package = mcFolderCompletedPackage($context['test']);
    if ($change === 'identity') {
        $package['identity'] = str_repeat('0', 64);
    } else {
        $package['results'][0]['result'][$change === 'score' ? 'score' : 'answers'] = $change === 'score' ? '999.00' : ['q1' => ['unknown']];
        $package['results'][0]['revision'] = app(McTestExchange::class)->hash($package['results'][0]['result']);
    }
    $response = $this->actingAs($context['teacher'])->postJson('/api/admin/multiple-choice/tests/'.$context['test']->id.'/folder/import', ['package' => $package]);
    if ($change === 'identity') {
        $response->assertUnprocessable();
    } else {
        $response->assertOk();
        expect($response->json('conflicts'))->not->toBe([]);
    }
    expect($context['attempt']->fresh()->completed_at)->toBeNull();
})->with(['identity', 'score', 'options']);

test('MC reimport protects a participation changed since preview', function (): void {
    $context = mcFolderContext();
    $package = mcFolderCompletedPackage($context['test']);
    $url = '/api/admin/multiple-choice/tests/'.$context['test']->id.'/folder/import';
    $this->actingAs($context['teacher']);
    $preview = $this->postJson($url, ['package' => $package])->assertOk();
    McAttempt::whereKey($context['attempt']->id)->update(['answers' => '{"q1":["b"]}', 'started_at' => '2026-10-19 08:00:01']);

    $this->postJson($url, ['package' => $package, 'apply' => true, 'hash' => $preview->json('hash')])->assertUnprocessable();

    expect($context['attempt']->fresh()->answers)->toBe(['q1' => ['b']]);
    expect(Storage::disk('local')->allFiles('multiple-choice/exchange-history'))->toBe([]);
});

test('connections distinguish a saved grant from a successful read check and expose no credentials', function (): void {
    $context = mcFolderContext();
    Role::findOrCreate('super_admin', 'web');
    $context['teacher']->assignRole('super_admin');
    DropboxConnection::factory()->create(['user_id' => $context['teacher']->id, 'credentials' => ['refresh_token' => 'fixture-refresh',
        'scopes' => [...DropboxWorkImport::SCOPES, 'files.content.write']]]);
    $this->actingAs($context['teacher']);
    $status = $this->getJson('/api/admin/connections')->assertOk()->assertJsonPath('dropbox.connected', true)
        ->assertJsonPath('dropbox.write_granted', true)->assertJsonPath('dropbox.read_checked_at', null)->assertJsonPath('dropbox.write_checked_at', null);
    expect($status->getContent())->not->toContain('fixture-refresh', 'account_id', 'credentials');
    Http::assertNothingSent();
    Http::fake(['https://api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'fixture-access']),
        'https://api.dropboxapi.com/2/files/list_folder' => Http::response(['entries' => [], 'has_more' => false])]);
    $dropbox = Mockery::mock(DropboxWorkImport::class)->makePartial();
    $dropbox->shouldReceive('verifyTemporaryWrite')->once()->with(Mockery::on(fn (User $user): bool => $user->id === $context['teacher']->id))
        ->andReturn(['created_read_removed' => true, 'temporary_folder_removed' => true]);
    app()->instance(DropboxWorkImport::class, $dropbox);

    $checked = $this->postJson('/api/admin/connections/check')->assertOk()->assertJsonPath('dropbox.write_checked_at', null);

    expect($checked->json('dropbox.read_checked_at'))->not->toBeNull();
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/files/upload') || str_contains($request->url(), '/email'));
});

test('Postmark checks send only the super admins own testmail after API confirmation and protect against double clicks', function (): void {
    $context = mcFolderContext();
    Role::findOrCreate('super_admin', 'web');
    $context['teacher']->assignRole('super_admin');
    config(['services.postmark.token' => 'fixture-postmark-secret', 'mail.default' => 'log']);
    Http::fake(['https://api.postmarkapp.com/server' => Http::response(['ID' => '123', 'ApiTokens' => ['provider-secret']]),
        'https://api.postmarkapp.com/email' => Http::response(['ErrorCode' => 0, 'MessageID' => 'fixture-message'])]);

    $response = $this->actingAs($context['teacher'])->postJson('/api/admin/connections/check')->assertOk()
        ->assertJsonPath('postmark.configured', true)->assertJsonPath('postmark.used_for_mail', false)->assertJsonPath('office.configured', false);

    expect($response->json('postmark.checked_at'))->not->toBeNull();
    expect($response->json('postmark.test_mail.accepted'))->toBeTrue();
    expect($response->getContent())->not->toContain('fixture-postmark-secret', 'provider-secret');
    $this->postJson('/api/admin/connections/check')->assertOk();
    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => $request->method() === 'GET' && $request->url() === 'https://api.postmarkapp.com/server');
    Http::assertSent(fn ($request): bool => $request->method() === 'POST' && $request->url() === 'https://api.postmarkapp.com/email'
        && $request['To'] === $context['teacher']->email && $request['From'] === 'schooltool@example.test');
});

test('automatic connection probes only read the selected provider and never run write or mail tests', function (): void {
    $context = mcFolderContext();
    Role::findOrCreate('super_admin', 'web');
    $context['teacher']->assignRole('super_admin');
    config(['services.postmark.token' => 'automatic-read-fixture']);
    DropboxConnection::factory()->create(['user_id' => $context['teacher']->id, 'credentials' => ['refresh_token' => 'automatic-refresh',
        'scopes' => [...DropboxWorkImport::SCOPES, 'files.content.write']]]);
    Http::fake(['https://api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'automatic-access']),
        'https://api.dropboxapi.com/2/files/list_folder' => Http::response(['entries' => [], 'has_more' => false]),
        'https://api.postmarkapp.com/server' => Http::response(['ID' => 123, 'ApiTokens' => ['private-provider-token']])]);
    $this->actingAs($context['teacher']);

    $dropbox = $this->getJson('/api/admin/connections?probe=dropbox')->assertOk()
        ->assertJsonPath('dropbox.write_checked_at', null)->assertJsonPath('postmark.test_mail', null);
    expect($dropbox->json('dropbox.read_checked_at'))->not->toBeNull();
    Http::assertSentCount(2);

    $postmark = $this->getJson('/api/admin/connections?probe=postmark')->assertOk()->assertJsonPath('postmark.test_mail', null);
    expect($postmark->json('postmark.checked_at'))->not->toBeNull();
    expect($postmark->getContent())->not->toContain('automatic-read-fixture', 'private-provider-token', 'automatic-refresh');
    Http::assertSentCount(3);
    Http::assertNotSent(fn ($request): bool => ! in_array($request->url(), [
        'https://api.dropboxapi.com/oauth2/token', 'https://api.dropboxapi.com/2/files/list_folder', 'https://api.postmarkapp.com/server',
    ], true));
});

test('automatic Postmark probes replace stale success and recover from provider errors', function (string $failure): void {
    $context = mcFolderContext();
    Role::findOrCreate('super_admin', 'web');
    $context['teacher']->assignRole('super_admin');
    config(['services.postmark.token' => 'automatic-postmark-'.$failure]);
    $this->actingAs($context['teacher']);
    Http::fake(['https://api.postmarkapp.com/server' => Http::sequence()->push(['ID' => 123])->pushResponse(match ($failure) {
        'token' => Http::response(['ErrorCode' => 10], 401),
        'malformed' => Http::response(['ID' => 'invalid']),
        'timeout' => Http::failedConnection(),
    })->push(['ID' => 123])]);
    $success = $this->getJson('/api/admin/connections?probe=postmark')->assertOk();
    expect($success->json('postmark.checked_at'))->not->toBeNull();

    $failed = $this->getJson('/api/admin/connections?probe=postmark')->assertOk()
        ->assertJsonPath('postmark.checked_at', null)->assertJsonPath('postmark.test_mail', null);
    expect($failed->json('postmark.error'))->not->toBeNull();

    $recovered = $this->getJson('/api/admin/connections?probe=postmark')->assertOk()
        ->assertJsonPath('postmark.error', null)->assertJsonPath('postmark.http_status', null)->assertJsonPath('postmark.error_code', null);
    expect($recovered->json('postmark.checked_at'))->not->toBeNull();
    Http::assertNotSent(fn ($request): bool => $request->method() !== 'GET');
})->with(['token', 'malformed', 'timeout']);

test('automatic Dropbox probes clear stale read confirmation on rejected or malformed responses', function (string $failure): void {
    $context = mcFolderContext();
    Role::findOrCreate('super_admin', 'web');
    $context['teacher']->assignRole('super_admin');
    DropboxConnection::factory()->create(['user_id' => $context['teacher']->id]);
    $this->actingAs($context['teacher']);
    Http::fake(['https://api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'automatic-access']),
        'https://api.dropboxapi.com/2/files/list_folder' => Http::sequence()->push(['entries' => [], 'has_more' => false])->pushResponse(match ($failure) {
            'revoked' => Http::response([], 401),
            'malformed' => Http::response([]),
            'timeout' => Http::failedConnection(),
        })]);
    $success = $this->getJson('/api/admin/connections?probe=dropbox')->assertOk();
    expect($success->json('dropbox.read_checked_at'))->not->toBeNull();

    $failed = $this->getJson('/api/admin/connections?probe=dropbox')->assertOk()
        ->assertJsonPath('dropbox.read_checked_at', null)->assertJsonPath('dropbox.write_checked_at', null);
    expect($failed->json('dropbox.error'))->not->toBeNull();
    if ($failure === 'revoked') {
        $failed->assertJsonPath('dropbox.connected', false);
    }
    Http::assertNotSent(fn ($request): bool => ! in_array($request->url(), [
        'https://api.dropboxapi.com/oauth2/token', 'https://api.dropboxapi.com/2/files/list_folder',
    ], true));
})->with(['revoked', 'malformed', 'timeout']);

test('automatic probes reject unsupported providers before making external requests', function (): void {
    $context = mcFolderContext();
    Role::findOrCreate('super_admin', 'web');
    $context['teacher']->assignRole('super_admin');
    $this->actingAs($context['teacher'])->getJson('/api/admin/connections?probe=write')->assertUnprocessable()->assertJsonValidationErrors('probe');
    Http::assertNothingSent();
});

test('the global connections overview and checks are restricted to super admins without blocking owned MC folders', function (): void {
    $context = mcFolderContext();
    $this->actingAs($context['teacher'])->getJson('/api/admin/connections')->assertForbidden();
    $this->getJson('/api/admin/connections?probe=dropbox')->assertForbidden();
    $this->getJson('/api/admin/connections?probe=postmark')->assertForbidden();
    $this->postJson('/api/admin/connections/check')->assertForbidden();
    $this->postJson('/api/admin/connections/dropbox/connect')->assertForbidden();
    $this->getJson('/api/admin/multiple-choice/tests/'.$context['test']->id.'/folder')->assertOk();
    Role::findOrCreate('admin', 'web');
    $context['teacher']->assignRole('admin');
    $this->getJson('/api/admin/connections')->assertForbidden();
    Http::assertNothingSent();
});

test('Postmark invalid server tokens show the provider code and block the testmail without confusing it with SMTP', function (): void {
    $context = mcFolderContext();
    Role::findOrCreate('super_admin', 'web');
    $context['teacher']->assignRole('super_admin');
    config(['services.postmark.token' => 'fixture-secret', 'mail.default' => 'smtp']);
    Http::fake(['https://api.postmarkapp.com/server' => Http::response(['ErrorCode' => 10, 'Message' => 'Request does not contain a valid Server token.'], 401)]);

    $response = $this->actingAs($context['teacher'])->postJson('/api/admin/connections/check')->assertOk()
        ->assertJsonPath('postmark.http_status', 401)->assertJsonPath('postmark.error_code', 10)
        ->assertJsonPath('postmark.smtp_selected', true)->assertJsonPath('postmark.test_mail.accepted', false);

    expect($response->json('postmark.error'))->toContain('Server-API-Token', 'SMTP-Zugang wird hier nicht geprüft');
    Http::assertSentCount(1);
});

test('Dropbox exports use a strict revision check and reject a concurrent remote update', function (): void {
    $context = mcFolderContext();
    $connection = DropboxConnection::factory()->create(['user_id' => $context['teacher']->id,
        'credentials' => ['refresh_token' => 'fixture-refresh', 'scopes' => [...DropboxWorkImport::SCOPES, 'files.content.write']]]);
    Http::fake(['https://api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'fixture-access']),
        'https://api.dropboxapi.com/2/files/get_metadata' => Http::response(['.tag' => 'folder', 'id' => 'id:fixture', 'name' => 'Fixture', 'path_lower' => '/fixture']),
        'https://content.dropboxapi.com/2/files/upload' => Http::response(['error_summary' => 'path/conflict/file/'], 409)]);

    expect(fn () => app(DropboxWorkImport::class)->writeDocument($connection, 'id:fixture', 'MC-Test_1.json', '{}', 'a123456789'))
        ->toThrow(ValidationException::class);

    Http::assertSent(function ($request): bool {
        if (! str_ends_with($request->url(), '/files/upload')) {
            return false;
        }
        $arguments = json_decode($request->header('Dropbox-API-Arg')[0], true);

        return $arguments['mode'] === ['.tag' => 'update', 'update' => 'a123456789']
            && $arguments['strict_conflict'] === true && $arguments['autorename'] === false;
    });
});

test('Dropbox write verification creates reads and removes only its unique own probe using its revision', function (): void {
    $context = mcFolderContext();
    $connection = DropboxConnection::factory()->create(['user_id' => $context['teacher']->id,
        'credentials' => ['refresh_token' => 'fixture-refresh', 'scopes' => [...DropboxWorkImport::SCOPES, 'files.content.write']]]);
    $contents = null;
    $probePath = null;
    Http::fake(function ($request) use (&$contents, &$probePath) {
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'fixture-access']);
        }
        if (str_ends_with($request->url(), '/files/upload')) {
            $arguments = json_decode($request->header('Dropbox-API-Arg')[0], true);
            $probePath = $arguments['path'];
            expect($arguments['mode'])->toBe('add');
            expect($arguments['strict_conflict'])->toBeTrue();
            expect($probePath)->toMatch('/\Aid:fixture\/Schooltool-Schreibtest-[a-f0-9]{32}\.txt\z/');
            $contents = $request->body();

            return Http::response(['rev' => 'a123456789', 'size' => strlen($contents)]);
        }
        if (str_ends_with($request->url(), '/files/get_metadata')) {
            return $request['path'] === 'id:fixture'
                ? Http::response(['.tag' => 'folder', 'id' => 'id:fixture', 'name' => 'Fixture', 'path_lower' => '/fixture'])
                : Http::response(['.tag' => 'file', 'rev' => 'a123456789', 'size' => strlen($contents)]);
        }
        if (str_ends_with($request->url(), '/files/download')) {
            return Http::response($contents);
        }
        if (str_ends_with($request->url(), '/files/delete_v2')) {
            expect($request['path'])->toBe($probePath);
            expect($request['parent_rev'])->toBe('a123456789');

            return Http::response(['metadata' => ['.tag' => 'file']]);
        }
        throw new RuntimeException('Unexpected Dropbox request.');
    });

    $result = app(DropboxWorkImport::class)->verifyWrite($connection, 'id:fixture');

    expect($result['created_read_removed'])->toBeTrue();
    $status = app(DropboxWorkImport::class)->connectionStatus($context['teacher']);
    expect($status['read_checked_at'])->toBe($result['checked_at']);
    expect($status['write_checked_at'])->toBe($result['checked_at']);
    expect($status['cleanup_complete'])->toBeTrue();
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/files/delete_v2'));
});

test('temporary Dropbox checks never remove a folder after unexpected files appear', function (): void {
    $context = mcFolderContext();
    DropboxConnection::factory()->create(['user_id' => $context['teacher']->id,
        'credentials' => ['refresh_token' => 'fixture-refresh', 'scopes' => [...DropboxWorkImport::SCOPES, 'files.content.write']]]);
    Http::fake(function ($request) {
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'fixture-access']);
        }
        if (str_ends_with($request->url(), '/files/create_folder_v2')) {
            expect($request['autorename'])->toBeFalse();

            return Http::response(['metadata' => ['id' => 'id:owned-probe', 'name' => substr($request['path'], 1)]]);
        }
        if (str_ends_with($request->url(), '/files/list_folder')) {
            return Http::response(['entries' => [['name' => 'unexpected.txt']], 'has_more' => false]);
        }
        throw new RuntimeException('Unexpected provider request.');
    });
    $dropbox = Mockery::mock(DropboxWorkImport::class)->makePartial();
    $dropbox->shouldReceive('verifyWrite')->once()->andReturn(['created_read_removed' => true]);

    expect(fn () => $dropbox->verifyTemporaryWrite($context['teacher']))->toThrow(ValidationException::class);

    Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/files/delete_v2'));
});
