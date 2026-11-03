<?php

use App\Models\DropboxConnection;
use App\Models\Import116;
use App\Models\Import116Run;
use App\Models\Import116RunChange;
use App\Models\McAttempt;
use App\Models\McCatalog;
use App\Models\McTest;
use App\Models\NumberSystemGameGuest;
use App\Models\NumberSystemGameRun;
use App\Models\NumberSystemGameSession;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\TeachingExplanationShare;
use App\Models\TeachingPersonalAppointment;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\TeachingWorkModuleLink;
use App\Models\User;
use App\Services\RestaurantSynchronisationService;
use App\Services\TeachingLiveSource;
use App\Services\TeachingSynchronisationBackup;
use App\Services\TeachingSynchronisationGraph;
use App\Services\TeachingSynchronisationService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

class TeachingSynchronisationWindowsTargetTestService extends RestaurantSynchronisationService
{
    protected static function operatingSystemFamily(): string
    {
        return 'Windows';
    }
}

class TeachingSynchronisationLinuxTargetTestService extends RestaurantSynchronisationService
{
    protected static function operatingSystemFamily(): string
    {
        return 'Linux';
    }
}

beforeEach(function () {
    app()->bind(RestaurantSynchronisationService::class, TeachingSynchronisationWindowsTargetTestService::class);
    $this->app['env'] = 'local';
    config(['schooltool.preview.instance' => false]);
    Storage::fake('local');
    Storage::fake('public');
    config(['filesystems.disks.local.root' => Storage::disk('local')->path(''),
        'filesystems.disks.public.root' => Storage::disk('public')->path('')]);
    $this->school = School::factory()->create();
    $this->year = Schoolyear::factory()->create(['school_id' => $this->school->id, 'from' => '2025-09-01', 'until' => '2026-08-31']);
    $this->actor = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    Role::findOrCreate('super_admin', 'web');
    $this->actor->assignRole('super_admin');
    enableSchoolToolModuleForTests($this->school, 'teaching');
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $this->course = TeachingCourse::factory()->forSchool($this->school)->forSchoolyear($this->year)->forTeacher($this->actor)->create(['title' => 'Local']);
    $this->snapshot = app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id);
    $this->snapshot['school'] = ['id' => $this->school->id, 'short_name' => $this->school->short_name, 'long_name' => $this->school->long_name];
    $this->snapshot['captured_at'] = now()->toISOString();
    $this->snapshot['files'] = [];
    $this->snapshot['external'] = [];
    $source = Mockery::mock(TeachingLiveSource::class);
    $source->shouldReceive('snapshot')->andReturnUsing(fn (): array => $this->snapshot);
    app()->instance(TeachingLiveSource::class, $source);
});

function teachingSyncApplyPayload(string $token): array
{
    return ['token' => $token, 'replace_confirmed' => true, 'contacts_confirmed' => true];
}

function teachingSyncModuleLink(TeachingCourseWork $work, DropboxConnection $connection): TeachingWorkModuleLink
{
    return TeachingWorkModuleLink::query()->create([
        'teaching_course_work_id' => $work->id, 'dropbox_connection_id' => $connection->id,
        'module_id' => 'a263b873-4f27-4167-90f0-7d94ea0f67fa', 'schema_version' => 1,
        'account_id' => $connection->account_id, 'namespace_id' => 'fixture-namespace',
        'folder_id' => 'id:local-module', 'folder_name' => 'Local module', 'folder_path' => '/Local module',
        'handshake_file_id' => 'id:local-handshake', 'handshake_revision' => 'local-revision',
    ]);
}

function teachingSyncToolsFixture(User $teacher, TeachingCourse $course): array
{
    $student = User::factory()->create(['school_id' => $teacher->school_id, 'schoolyear_id' => $course->schoolyear_id]);
    $course->teachingCourseStudents()->create(['user_id' => $student->id]);
    $catalog = McCatalog::query()->create(['school_id' => $teacher->school_id, 'owner_id' => $teacher->id,
        'title' => 'Fixture catalog', 'questions' => [['id' => 'q1', 'text' => 'Fixture question']]]);
    $test = McTest::query()->create(['school_id' => $teacher->school_id, 'schoolyear_id' => $course->schoolyear_id,
        'owner_id' => $teacher->id, 'catalog_id' => $catalog->id, 'title' => 'Fixture test', 'status' => 'draft',
        'question_count' => 1, 'total_points' => 2, 'duration_seconds' => 60, 'snapshot' => ['questions' => $catalog->questions]]);
    $token = bin2hex(random_bytes(32));
    $attempt = McAttempt::query()->create(['test_id' => $test->id, 'student_id' => $student->id,
        'student_name' => $student->first_name, 'email' => $student->email, 'questions' => $catalog->questions,
        'answers' => ['q1' => ['a1']], 'score' => 2, 'correct_count' => 1, 'percentage' => 100,
        'access_token' => $token, 'access_token_hash' => hash('sha256', $token)]);
    $session = NumberSystemGameSession::factory()->create(['teaching_course_id' => $course->id]);
    $run = NumberSystemGameRun::factory()->create(['session_id' => $session->id, 'user_id' => $student->id,
        'puzzles' => [['answer' => 42]], 'history' => [['solved' => true]], 'team_name' => 'Fixture team',
        'team_code' => strtoupper(bin2hex(random_bytes(4)))]);
    DB::table('number_system_game_members')->insert(['session_id' => $session->id, 'run_id' => $run->id, 'user_id' => $student->id]);

    return compact('catalog', 'test', 'attempt', 'session', 'run', 'student', 'token');
}

function teachingSyncOtherToolsFixture(): array
{
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    $teacher = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id]);
    $course = TeachingCourse::factory()->forSchool($school)->forSchoolyear($year)->forTeacher($teacher)->create();

    return teachingSyncToolsFixture($teacher, $course);
}

test('teaching tools preview and apply include complete results and games and recover the replaced local state', function (): void {
    $tools = teachingSyncToolsFixture($this->actor, $this->course);
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $remoteEncrypter = new Encrypter(str_repeat('r', 32), 'AES-256-CBC');
    $this->snapshot['tables']['mc_attempts'][0]['access_token'] = $remoteEncrypter->encryptString($tools['token']);
    $this->snapshot['mc_access_tokens'] = app(TeachingSynchronisationGraph::class)->accessTokens($this->snapshot, $remoteEncrypter);
    $this->snapshot['tables']['mc_tests'][0]['title'] = 'Cloud test with results';
    $this->snapshot['tables']['number_system_game_runs'][0]['attempts'] = 3;
    $localOnly = teachingSyncToolsFixture($this->actor, $this->course);
    $other = teachingSyncOtherToolsFixture();
    $otherAttempt = $other['attempt']->fresh()->getAttributes();
    $originalTokenCiphertext = $tools['attempt']->fresh()->getRawOriginal('access_token');
    $this->actingAs($this->actor, 'sanctum');

    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    $preview->assertJsonPath('data.conflicts', []);
    $summary = array_column($preview->json('data.summary'), null, 'table');
    foreach (['mc_catalogs', 'mc_tests', 'mc_attempts', 'number_system_game_sessions', 'number_system_game_runs', 'number_system_game_members'] as $table) {
        expect($summary[$table])->toMatchArray(['live' => 1, 'local' => 2, 'replaced' => 2]);
    }
    expect($tools['test']->fresh()->title)->toBe('Fixture test');
    $result = $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))->assertSuccessful();

    expect($tools['test']->fresh()->title)->toBe('Cloud test with results');
    expect($tools['attempt']->fresh()->answers)->toBe(['q1' => ['a1']]);
    expect($tools['attempt']->fresh()->score)->toBe('2.00');
    expect($tools['attempt']->fresh()->access_token)->toBe($tools['token']);
    expect($tools['attempt']->fresh()->getRawOriginal('access_token'))->not->toBe($this->snapshot['tables']['mc_attempts'][0]['access_token']);
    expect($tools['run']->fresh()->attempts)->toBe(3);
    expect($tools['run']->fresh()->history)->toBe([['solved' => true]]);
    expect($localOnly['catalog']->fresh())->toBeNull();
    expect($localOnly['session']->fresh())->toBeNull();
    expect($other['attempt']->fresh()->getAttributes())->toBe($otherAttempt);
    expect($other['run']->fresh()->team_code)->toBe($other['run']->team_code);
    $backup = app(TeachingSynchronisationBackup::class)->read("teaching/synchronisation-backups/{$this->school->id}/{$result->json('backup')}.enc");
    expect($backup['state']['tables']['mc_tests'])->toHaveCount(2);
    expect($backup['state']['tables']['mc_attempts'][0]['access_token'])->toBe($originalTokenCiphertext);
    expect($backup['state']['tables']['number_system_game_members'])->toHaveCount(2);
    expect($backup['state']['tables']['number_system_game_runs'][0]['attempts'])->toBe(0);
});

test('external game guests retain lobby or team membership across remapped IDs and have a recovery copy', function (bool $joinedTeam): void {
    $session = NumberSystemGameSession::factory()->create(['school_id' => $this->school->id,
        'teacher_id' => $this->actor->id, 'teaching_course_id' => null, 'audience' => 'external']);
    $run = NumberSystemGameRun::factory()->create(['session_id' => $session->id, 'user_id' => null, 'puzzles' => []]);
    $guest = NumberSystemGameGuest::query()->create(['session_id' => $session->id,
        'run_id' => $joinedTeam ? $run->id : null, 'first_name' => 'Source guest', 'token_hash' => hash('sha256', 'source-guest')]);
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $localOnly = NumberSystemGameGuest::query()->create(['session_id' => $session->id,
        'first_name' => 'Local guest', 'token_hash' => hash('sha256', 'local-guest')]);
    $otherSession = NumberSystemGameSession::factory()->create();
    $otherRun = NumberSystemGameRun::factory()->create(['session_id' => $otherSession->id]);
    $otherGuest = NumberSystemGameGuest::query()->create(['session_id' => $otherSession->id,
        'run_id' => $otherRun->id, 'first_name' => 'Other school', 'token_hash' => hash('sha256', 'other-guest')]);
    $otherAttributes = $otherGuest->fresh()->getAttributes();
    $this->snapshot['tables']['number_system_game_sessions'][0]['id'] = $otherSession->id;
    $this->snapshot['tables']['number_system_game_runs'][0]['id'] = $otherRun->id;
    $this->snapshot['tables']['number_system_game_runs'][0]['session_id'] = $otherSession->id;
    $this->snapshot['tables']['number_system_game_guests'][0]['id'] = $otherGuest->id;
    $this->snapshot['tables']['number_system_game_guests'][0]['session_id'] = $otherSession->id;
    $this->snapshot['tables']['number_system_game_guests'][0]['run_id'] = $joinedTeam ? $otherRun->id : null;
    $this->actingAs($this->actor, 'sanctum');

    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    $preview->assertJsonPath('data.conflicts', []);
    $summary = array_column($preview->json('data.summary'), null, 'table');
    expect($summary['number_system_game_guests'])->toMatchArray(['live' => 1, 'local' => 2, 'replaced' => 2]);
    $result = $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))->assertSuccessful();

    $importedSession = NumberSystemGameSession::query()->where('school_id', $this->school->id)->sole();
    $importedRun = NumberSystemGameRun::query()->where('session_id', $importedSession->id)->sole();
    $importedGuest = NumberSystemGameGuest::query()->where('session_id', $importedSession->id)->sole();
    expect($importedSession->id)->not->toBe($otherSession->id);
    expect($importedSession->teaching_course_id)->toBeNull();
    expect($importedSession->audience)->toBe('external');
    expect($importedRun->id)->not->toBe($otherRun->id);
    expect($importedRun->user_id)->toBeNull();
    expect($importedGuest->id)->not->toBe($otherGuest->id);
    $this->assertDatabaseHas('number_system_game_guests', ['id' => $importedGuest->id,
        'session_id' => $importedSession->id, 'run_id' => $joinedTeam ? $importedRun->id : null,
        'first_name' => 'Source guest', 'token_hash' => hash('sha256', 'source-guest')]);
    expect($localOnly->fresh())->toBeNull();
    expect($otherGuest->fresh()->getAttributes())->toBe($otherAttributes);
    $backup = app(TeachingSynchronisationBackup::class)->read("teaching/synchronisation-backups/{$this->school->id}/{$result->json('backup')}.enc");
    expect(array_column($backup['state']['tables']['number_system_game_guests'], 'id'))->toBe([$guest->id, $localOnly->id]);
})->with(['lobby' => false, 'team' => true]);

test('guest membership in a different game round blocks preview without replacement', function (): void {
    $session = NumberSystemGameSession::factory()->create(['teaching_course_id' => $this->course->id]);
    $otherSession = NumberSystemGameSession::factory()->create(['teaching_course_id' => $this->course->id]);
    $run = NumberSystemGameRun::factory()->create(['session_id' => $otherSession->id, 'user_id' => $this->actor->id]);
    $guest = NumberSystemGameGuest::query()->create(['session_id' => $session->id,
        'run_id' => $run->id, 'first_name' => 'Guest', 'token_hash' => hash('sha256', 'guest')]);
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));

    $preview = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();

    $preview->assertJsonPath('data.token', null);
    expect(implode(' ', $preview->json('data.conflicts')))->toContain('Gastteilnahme verweist auf eine andere Spielrunde');
    expect($guest->fresh()->run_id)->toBe($run->id);
    expect(Storage::disk('local')->allFiles('teaching/synchronisation-backups'))->toBe([]);
});

test('tool access code collisions with other schools block preview before any replacement', function (string $kind): void {
    $tools = teachingSyncToolsFixture($this->actor, $this->course);
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $this->snapshot['mc_access_tokens'] = [$tools['attempt']->id => $tools['token']];
    $other = teachingSyncOtherToolsFixture();
    if ($kind === 'mc') {
        $this->snapshot['tables']['mc_attempts'][0]['access_token_hash'] = $other['attempt']->access_token_hash;
        $this->snapshot['mc_access_tokens'][$tools['attempt']->id] = $other['token'];
    } elseif ($kind === 'round') {
        $this->snapshot['tables']['number_system_game_sessions'][0]['join_code'] = $other['session']->join_code;
    } else {
        $this->snapshot['tables']['number_system_game_runs'][0]['team_code'] = $other['run']->team_code;
    }

    $preview = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();

    $preview->assertJsonPath('data.token', null);
    expect(implode(' ', $preview->json('data.conflicts')))->toContain('kollidiert mit fremden Daten');
    expect($tools['attempt']->fresh()->access_token)->toBe($tools['token']);
    expect($tools['session']->fresh()->join_code)->toBe($tools['session']->join_code);
    expect(Storage::disk('local')->allFiles('teaching/synchronisation-backups'))->toBe([]);
})->with(['mc', 'round', 'team']);

test('missing tool links and foreign owners block preview without silently omitting attempts', function (): void {
    $tools = teachingSyncToolsFixture($this->actor, $this->course);
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $this->snapshot['tables']['mc_catalogs'][0]['owner_id'] = 999999;

    $preview = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();

    $preview->assertJsonPath('data.token', null);
    expect(implode(' ', $preview->json('data.conflicts')))->toContain('Beziehung', 'Multiple-Choice-Zugangslink fehlt');
    expect($tools['catalog']->fresh()->owner_id)->toBe($this->actor->id);
    expect($tools['attempt']->fresh()->access_token)->toBe($tools['token']);
});

test('changed tool answers after preview reject an outdated apply token', function (bool $cloudChanged): void {
    $tools = teachingSyncToolsFixture($this->actor, $this->course);
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $this->snapshot['mc_access_tokens'] = [$tools['attempt']->id => $tools['token']];
    $this->actingAs($this->actor, 'sanctum');
    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    if ($cloudChanged) {
        $this->snapshot['tables']['mc_attempts'][0]['answers'] = '{"q1":["a2"]}';
    } else {
        McAttempt::query()->whereKey($tools['attempt']->id)->update(['answers' => '{"q1":["a2"]}']);
    }

    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))->assertUnprocessable();

    expect($tools['attempt']->fresh()->answers)->toBe(['q1' => [$cloudChanged ? 'a1' : 'a2']]);
    expect(Storage::disk('local')->allFiles('teaching/synchronisation-backups'))->toBe([]);
})->with([false, true]);

test('a rejected safety archive leaves all data and files untouched', function (): void {
    $backups = Mockery::mock(TeachingSynchronisationBackup::class)->makePartial();
    $backups->shouldReceive('write')->once()->andThrow(new RuntimeException('Fixture safety archive failure.'));
    app()->instance(TeachingSynchronisationBackup::class, $backups);
    $this->snapshot['tables']['teaching_courses'][0]['title'] = 'Cloud change';
    $this->actingAs($this->actor, 'sanctum');
    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();

    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))
        ->assertUnprocessable()->assertJsonPath('message', 'Fixture safety archive failure.');

    expect($this->course->fresh()->title)->toBe('Local');
    expect(Storage::disk('local')->allFiles('teaching'))->toBe([]);
});

test('safety downloads preserve encrypted bytes and reject another actor for old and streamed archives', function (bool $legacy): void {
    $token = str_repeat('b', 64);
    $path = "teaching/synchronisation-backups/{$this->school->id}/{$token}.enc";
    $payload = ['format' => 'schooltool-teaching-sync-safety-v2', 'school' => $this->school->id,
        'actor' => $this->actor->id, 'created_at' => now()->toISOString(), 'state' => []];
    if ($legacy) {
        $payload['format'] = 'schooltool-teaching-sync-safety-v1';
        Storage::disk('local')->put($path, Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)));
    } else {
        app(TeachingSynchronisationBackup::class)->write($payload, $path);
    }
    $expected = Storage::disk('local')->get($path);
    $this->actingAs($this->actor, 'sanctum');
    $response = $this->get('/api/admin/teaching/synchronisation/backups/'.$token)->assertSuccessful();
    expect($response->streamedContent())->toBe($expected);
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
    $other = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $other->assignRole('super_admin');

    $this->actingAs($other, 'sanctum')->get('/api/admin/teaching/synchronisation/backups/'.$token)->assertForbidden();
})->with([false, true]);

test('preview and full apply preserve 63 local files while importing 81 files within 512 MiB', function (): void {
    $previousLimit = ini_get('memory_limit');
    ini_set('memory_limit', '512M');
    config(['filesystems.disks.local.root' => rtrim(Storage::disk('local')->path(''), '/\\')]);
    $localReferences = [];
    $localHashes = [];
    for ($index = 0; $index < 63; $index++) {
        $path = "teaching/old-{$index}.pdf";
        $bytes = random_bytes((int) floor(55.1 * 1024 * 1024 / 63));
        Storage::disk('local')->put($path, $bytes);
        $localHashes[$path] = hash('sha256', $bytes);
        $localReferences[] = ['file_path' => $path, 'storage_disk' => 'local'];
    }
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id,
        'status' => ['dispatch_logs' => $localReferences]]);
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $incomingReferences = [];
    $incomingHashes = [];
    for ($index = 0; $index < 81; $index++) {
        $path = "teaching/live-{$index}.pdf";
        $bytes = random_bytes((int) floor(55.1 * 1024 * 1024 / 81));
        $hash = hash('sha256', $bytes);
        $this->snapshot['files'][] = ['disk' => 'local', 'path' => $path,
            'size' => strlen($bytes), 'sha256' => $hash, 'content' => base64_encode($bytes)];
        $incomingReferences[] = ['file_path' => $path, 'storage_disk' => 'local'];
        $incomingHashes[] = $hash;
    }
    unset($bytes);
    $this->snapshot['tables']['teaching_course_works'][0]['status'] = json_encode(['dispatch_logs' => $incomingReferences], JSON_THROW_ON_ERROR);
    $this->snapshot['tables']['teaching_courses'][0]['title'] = 'Large reviewed cloud course';
    $this->actingAs($this->actor, 'sanctum');
    memory_reset_peak_usage();

    try {
        $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
        $preview->assertJsonPath('data.files', 81)->assertJsonPath('data.local_files', 63)->assertJsonPath('data.conflicts', []);
        expect($this->course->fresh()->title)->toBe('Local');
        $result = $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))
            ->assertSuccessful();
        $applyPeak = memory_get_peak_usage(true);
        expect($this->course->fresh()->title)->toBe('Large reviewed cloud course');
        expect(Storage::disk('local')->allFiles('teaching/synchronisation/'.$this->school->id))->toHaveCount(81);
        foreach ($incomingHashes as $hash) {
            expect(hash_file('sha256', Storage::disk('local')->path("teaching/synchronisation/{$this->school->id}/{$hash}.pdf")))->toBe($hash);
        }
        foreach ($localHashes as $path => $hash) {
            expect(hash_file('sha256', Storage::disk('local')->path($path)))->toBe($hash);
        }
        $backupPath = "teaching/synchronisation-backups/{$this->school->id}/{$result->json('backup')}.enc";
        $backups = app(TeachingSynchronisationBackup::class);
        $this->get('/api/admin/teaching/synchronisation/backups/'.$result->json('backup'))->assertSuccessful();
        unset($this->snapshot['files']);
        $saved = $backups->read($backupPath);
        expect($saved['state']['files'])->toHaveCount(63);
        foreach ($saved['state']['files'] as $file) {
            expect(hash('sha256', base64_decode($file['content'], true)))->toBe($localHashes[$file['path']]);
        }
        expect($saved['state']['tables']['teaching_course_works'][0]['id'])->toBe($work->id);
        expect($applyPeak)->toBeLessThan(512 * 1024 * 1024);
        fwrite(STDERR, "\nFull synchronisation peak: {$applyPeak} bytes (512M limit).\n");
    } finally {
        ini_set('memory_limit', $previousLimit);
    }
});

test('preview caches only the reviewed source fingerprint and still accepts older reviewed tokens', function (bool $legacy): void {
    $preview = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    $cached = json_decode(Crypt::decryptString(Cache::store('file')->get('teaching-sync:'.$preview->json('data.token'))),
        true, flags: JSON_THROW_ON_ERROR);
    expect($cached)->toHaveKeys(['actor', 'school', 'local_hash', 'source_hash'])
        ->not->toHaveKey('source')
        ->and(strlen($cached['source_hash']))->toBe(64);
    if ($legacy) {
        unset($cached['source_hash']);
        $cached['source'] = $this->snapshot;
        Cache::store('file')->put('teaching-sync:'.$preview->json('data.token'),
            Crypt::encryptString(json_encode($cached, JSON_THROW_ON_ERROR)), now()->addMinutes(15));
    }
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))->assertSuccessful();
})->with([false, true]);

test('multiple local Dropbox choices cannot silently converge on one incoming work', function (): void {
    $first = TeachingCourseWork::factory()->create([
        'teaching_course_id' => $this->course->id, 'title' => 'Same assignment',
        'type' => 'A5', 'date_for_all_groups' => '2026-10-06',
    ]);
    $second = TeachingCourseWork::factory()->create([
        'teaching_course_id' => $this->course->id, 'title' => $first->title,
        'type' => $first->type, 'date_for_all_groups' => $first->date_for_all_groups,
    ]);
    $connection = DropboxConnection::factory()->create(['user_id' => $this->actor->id]);
    $folders = collect([$first, $second])->map(fn (TeachingCourseWork $work): array => TeachingWorkDropboxFolder::factory()->create([
        'teaching_course_work_id' => $work->id, 'dropbox_connection_id' => $connection->id,
    ])->fresh()->getAttributes())->all();
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $this->snapshot['tables']['teaching_course_works'] = [$this->snapshot['tables']['teaching_course_works'][0]];
    $preview = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.conflicts'))->toBe([])
        ->and($preview->json('data.dropbox_warnings'))->toHaveCount(2);
    $result = $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))->assertSuccessful();
    expect(DB::table('teaching_work_dropbox_folders')->count())->toBe(0);
    $backup = app(TeachingSynchronisationBackup::class)->read(
        "teaching/synchronisation-backups/{$this->school->id}/{$result->json('backup')}.enc"
    );
    expect($backup['dropbox_recovery'])->toBe($folders);
});

test('teaching sync rejects Linux at HTTP and service boundaries', function (): void {
    app()->bind(RestaurantSynchronisationService::class, TeachingSynchronisationLinuxTargetTestService::class);
    $service = app(TeachingSynchronisationService::class);

    expect($service::available())->toBeFalse();
    $this->actingAs($this->actor, 'sanctum')->getJson('/api/admin/teaching/synchronisation/status')
        ->assertSuccessful()->assertJsonPath('available', false);
    $this->postJson('/api/admin/teaching/synchronisation/preview')->assertForbidden();
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload(str_repeat('a', 64)))
        ->assertForbidden();
    expect(fn () => $service->preview($this->actor))->toThrow(HttpException::class)
        ->and(fn () => $service->apply($this->actor, str_repeat('a', 64)))->toThrow(HttpException::class)
        ->and($this->course->fresh()->title)->toBe('Local');
});

test('teaching sync requires authentication superadmin local target and explicit replacement confirmation', function () {
    $this->postJson('/api/admin/teaching/synchronisation/preview')->assertUnauthorized();
    $ordinary = User::factory()->create(['school_id' => $this->school->id]);
    Role::findOrCreate('teaching_admin', 'web');
    $ordinary->assignRole('teaching_admin');
    $this->actingAs($ordinary, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')->assertForbidden();
    $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/apply', ['token' => str_repeat('a', 64)])->assertUnprocessable();
    $this->app['env'] = 'production';
    $this->postJson('/api/admin/teaching/synchronisation/preview')->assertForbidden();
    expect($this->course->fresh()->title)->toBe('Local');
});

test('preview is read only and apply imports every year with private backup and new disabled identities', function () {
    $teacher = Teacher::query()->create(['school_id' => $this->school->id, 'email' => 'roster@example.test',
        'first_name' => 'Local name', 'last_name' => 'Teacher', 'short' => 'ALT', 'is_active' => true, 'token' => 'local-token']);
    $this->snapshot['teachers'] = [['id' => $teacher->id, 'school_id' => $this->school->id, 'email' => $teacher->email,
        'first_name' => 'Cloud name', 'last_name' => 'Teacher', 'short' => 'NEU', 'is_active' => false]];
    $this->snapshot['schoolyears'][0]['name'] = 'Cloud reviewed year';
    $newYear = $this->snapshot['schoolyears'][0];
    $newYear['id'] = 90001;
    $newYear['name'] = '2026/27';
    $newYear['from'] = '2026-09-01';
    $newYear['until'] = '2027-08-31';
    $this->snapshot['schoolyears'][] = $newYear;
    $newUser = $this->snapshot['users'][0];
    $newUser['id'] = 90002;
    $newUser['email'] = 'new-teacher@example.test';
    $this->snapshot['users'][] = $newUser;
    $this->snapshot['tables']['teaching_courses'][0]['title'] = 'Cloud old year';
    $newCourse = $this->snapshot['tables']['teaching_courses'][0];
    $newCourse['id'] = 90003;
    $newCourse['schoolyear_id'] = 90001;
    $newCourse['user_id'] = 90002;
    $newCourse['title'] = 'Cloud new year';
    $this->snapshot['tables']['teaching_courses'][] = $newCourse;
    $password = $this->actor->password;
    $roles = $this->actor->getRoleNames()->all();
    $this->actingAs($this->actor, 'sanctum');
    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.conflicts'))->toBe([]);
    $preview->assertJsonPath('data.new_schoolyears', 1)->assertJsonPath('data.new_accounts', 1);
    $preview->assertJsonPath('data.changed_teachers.0.changes.0.before', 'Local name')
        ->assertJsonPath('data.changed_teachers.0.changes.0.after', 'Cloud name')
        ->assertJsonPath('data.updated_schoolyears.0.values.name', 'Cloud reviewed year');
    expect($teacher->fresh()->first_name)->toBe('Local name');
    expect($this->course->fresh()->title)->toBe('Local');
    $token = $preview->json('data.token');
    $result = $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertSuccessful();
    expect($this->course->fresh()->title)->toBe('Cloud old year');
    expect($teacher->fresh()->first_name)->toBe('Cloud name')
        ->and($teacher->fresh()->is_active)->toBeTrue()
        ->and($teacher->fresh()->token)->toBe('local-token')
        ->and($this->year->fresh()->name)->toBe('Cloud reviewed year');
    $newAccount = User::where('email', 'new-teacher@example.test')->firstOrFail();
    expect($newAccount->is_active)->toBeFalsy()
        ->and($newAccount->getRoleNames()->all())->toBe([])
        ->and($newAccount->password)->not->toBe($password)
        ->and($this->actor->fresh()->password)->toBe($password)
        ->and($this->actor->fresh()->getRoleNames()->all())->toBe($roles);
    $course = TeachingCourse::findOrFail(90003);
    expect($course->user_id)->toBe($newAccount->id)->and($course->schoolyear_id)->not->toBe(90001);
    $backup = $result->json('backup');
    $contents = Storage::disk('local')->get("teaching/synchronisation-backups/{$this->school->id}/{$backup}.enc");
    expect($contents)->not->toContain('Local');
    $saved = app(TeachingSynchronisationBackup::class)->read("teaching/synchronisation-backups/{$this->school->id}/{$backup}.enc");
    expect($saved['state']['tables']['teaching_courses'][0]['title'])->toBe('Local');
    $this->get('/api/admin/teaching/synchronisation/backups/'.$backup)->assertSuccessful();
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();
});

test('personal appointments are scoped to the school and imported across years with remapped identities and a recovery copy', function () {
    $local = TeachingPersonalAppointment::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->actor->id,
        'title' => 'Local appointment', 'date' => '2026-02-03',
    ]);
    $foreignSchool = School::factory()->create();
    $foreignYear = Schoolyear::factory()->create(['school_id' => $foreignSchool->id]);
    $foreignUser = User::factory()->create(['school_id' => $foreignSchool->id, 'schoolyear_id' => $foreignYear->id]);
    $foreign = TeachingPersonalAppointment::factory()->create([
        'school_id' => $foreignSchool->id, 'schoolyear_id' => $foreignYear->id, 'user_id' => $foreignUser->id,
        'title' => 'Foreign appointment',
    ]);
    $localAttributes = $local->fresh()->getAttributes();
    $foreignAttributes = $foreign->fresh()->getAttributes();
    $captured = app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id);
    expect(array_column($captured['tables']['teaching_personal_appointments'], 'id'))->toBe([$local->id]);
    $this->snapshot = array_replace($this->snapshot, $captured);
    $this->snapshot['schoolyears'][] = [...$this->snapshot['schoolyears'][0], 'id' => 90001,
        'name' => '2026/27', 'from' => '2026-09-01', 'until' => '2027-08-31'];
    $this->snapshot['users'][] = [...$this->snapshot['users'][0], 'id' => 90002, 'email' => 'appointment-teacher@example.test'];
    $cloud = [...$localAttributes, 'id' => $foreign->id, 'schoolyear_id' => 90001, 'user_id' => 90002,
        'title' => 'Cloud weekly appointment', 'date' => '2026-10-12', 'repeat_until' => '2027-06-28',
        'school_hours' => json_encode([6, 7], JSON_THROW_ON_ERROR),
        'time_segments' => json_encode([['starts_at' => '12:30', 'ends_at' => '13:20']], JSON_THROW_ON_ERROR),
        'title_exceptions' => json_encode(['2026-10-19:6' => 'Only this occurrence'], JSON_THROW_ON_ERROR)];
    $this->snapshot['tables']['teaching_personal_appointments'] = [
        $cloud,
        [...$localAttributes, 'id' => 90003, 'title' => 'Cloud previous year appointment'],
    ];

    $this->actingAs($this->actor, 'sanctum');
    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.conflicts'))->toBe([]);
    expect($local->fresh()->getAttributes())->toBe($localAttributes);
    $result = $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))
        ->assertSuccessful();

    $imported = TeachingPersonalAppointment::where('school_id', $this->school->id)->where('title', 'Cloud weekly appointment')->firstOrFail();
    $newAccount = User::where('email', 'appointment-teacher@example.test')->firstOrFail();
    $newYear = Schoolyear::where('school_id', $this->school->id)->where('from', '2026-09-01')->firstOrFail();
    expect($imported->id)->not->toBe($foreign->id);
    expect($imported->user_id)->toBe($newAccount->id)->not->toBe(90002);
    expect($imported->schoolyear_id)->toBe($newYear->id)->not->toBe(90001);
    expect($imported->school_hours)->toBe([6, 7]);
    expect($imported->time_segments)->toEqual([['starts_at' => '12:30', 'ends_at' => '13:20']]);
    expect($imported->title_exceptions)->toBe(['2026-10-19:6' => 'Only this occurrence']);
    expect($imported->getRawOriginal('repeat_until'))->toBe('2027-06-28');
    $this->assertDatabaseHas('teaching_personal_appointments', [
        'id' => 90003, 'school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'user_id' => $this->actor->id, 'title' => 'Cloud previous year appointment',
    ]);
    expect(TeachingPersonalAppointment::where('school_id', $this->school->id)->count())->toBe(2);
    expect($foreign->fresh()->getAttributes())->toBe($foreignAttributes);
    $backup = $result->json('backup');
    $saved = app(TeachingSynchronisationBackup::class)->read(
        "teaching/synchronisation-backups/{$this->school->id}/{$backup}.enc"
    );
    expect($saved['state']['tables']['teaching_personal_appointments'])->toBe([$localAttributes]);
});

test('invalid personal appointment scope or references block synchronisation without replacing local appointments', function (string $field, string $conflict): void {
    $local = TeachingPersonalAppointment::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->actor->id,
        'title' => 'Preserve local appointment',
    ]);
    $attributes = $local->fresh()->getAttributes();
    $this->snapshot['tables']['teaching_personal_appointments'] = [[...$attributes, $field => 90099]];

    $preview = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')
        ->assertSuccessful();

    expect($preview->json('data.token'))->toBeNull();
    expect(implode(' ', $preview->json('data.conflicts')))->toContain($conflict);
    expect($local->fresh()->getAttributes())->toBe($attributes);
    expect(Storage::disk('local')->allFiles('teaching/synchronisation-backups'))->toBe([]);
})->with([
    'missing owner' => ['user_id', 'Beziehung zu users'],
    'missing schoolyear' => ['schoolyear_id', 'Beziehung zu schoolyears'],
    'foreign school' => ['school_id', 'Fremde Schule'],
]);

test('synchronisation preserves installation explanation links without exporting access or changing other schools', function () {
    $share = TeachingExplanationShare::factory()->create([
        'school_id' => $this->school->id, 'user_id' => $this->actor->id,
        'access_mode' => 'authenticated', 'is_active' => true,
    ]);
    $foreignSchool = School::factory()->create();
    $foreignTeacher = User::factory()->create(['school_id' => $foreignSchool->id]);
    $foreignShare = TeachingExplanationShare::factory()->create([
        'school_id' => $foreignSchool->id, 'user_id' => $foreignTeacher->id,
        'access_mode' => 'public', 'is_active' => true,
    ]);
    $before = $share->fresh()->getAttributes();
    $foreignBefore = $foreignShare->fresh()->getAttributes();
    $captured = app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id);

    expect($captured['tables'])->not->toHaveKey('teaching_explanation_shares');
    expect(json_encode($captured))->not->toContain($share->token, $foreignShare->token);

    $this->snapshot['tables']['teaching_courses'][0]['title'] = 'Updated cloud course';
    $this->actingAs($this->actor, 'sanctum');
    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.conflicts'))->toBe([]);
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))
        ->assertSuccessful();

    expect($this->course->fresh()->title)->toBe('Updated cloud course')
        ->and($share->fresh()->getAttributes())->toBe($before)
        ->and($foreignShare->fresh()->getAttributes())->toBe($foreignBefore);
});

test('synchronisation preserves local Dropbox folder choices and credentials without exporting them or changing foreign choices', function () {
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $connection = DropboxConnection::factory()->create(['user_id' => $this->actor->id]);
    $folder = TeachingWorkDropboxFolder::factory()->create([
        'teaching_course_work_id' => $work->id, 'dropbox_connection_id' => $connection->id,
    ]);
    $foreignCourse = TeachingCourse::factory()->create();
    $foreignWork = TeachingCourseWork::factory()->create(['teaching_course_id' => $foreignCourse->id]);
    $foreignConnection = DropboxConnection::factory()->create(['user_id' => $foreignCourse->user_id]);
    $foreignFolder = TeachingWorkDropboxFolder::factory()->create([
        'teaching_course_work_id' => $foreignWork->id, 'dropbox_connection_id' => $foreignConnection->id,
    ]);
    $folderAttributes = $folder->fresh()->getAttributes();
    $foreignAttributes = $foreignFolder->fresh()->getAttributes();
    $credentials = $connection->fresh()->getRawOriginal('credentials');
    $captured = app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id);
    expect($captured)->not->toHaveKey('dropbox_folders');
    expect($captured['tables'])->not->toHaveKey('teaching_work_dropbox_folders')->not->toHaveKey('dropbox_connections');
    $this->snapshot = array_replace($this->snapshot, $captured);
    $this->snapshot['tables']['teaching_course_works'][0]['description'] = 'Reviewed cloud description';

    $this->actingAs($this->actor, 'sanctum');
    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.conflicts'))->toBe([]);
    $result = $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))
        ->assertSuccessful();

    expect($work->fresh()->description)->toBe('Reviewed cloud description');
    expect($folder->fresh()->getAttributes())->toBe($folderAttributes);
    expect($foreignFolder->fresh()->getAttributes())->toBe($foreignAttributes);
    expect($connection->fresh()->getRawOriginal('credentials'))->toBe($credentials);
    $backup = $result->json('backup');
    $saved = app(TeachingSynchronisationBackup::class)->read("teaching/synchronisation-backups/{$this->school->id}/{$backup}.enc");
    $savedBytes = json_encode($saved, JSON_THROW_ON_ERROR);
    expect($savedBytes)->not->toContain($credentials, 'refresh_token');
    expect($saved['state']['dropbox_folders'])->toBe([$folderAttributes]);
});

test('synchronisation keeps unmatched Dropbox choices recoverable without assigning them to different work', function (string $change): void {
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $connection = DropboxConnection::factory()->create(['user_id' => $this->actor->id]);
    $folder = TeachingWorkDropboxFolder::factory()->create([
        'teaching_course_work_id' => $work->id, 'dropbox_connection_id' => $connection->id,
    ]);
    $attributes = $folder->fresh()->getAttributes();
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    if ($change === 'missing work') {
        $this->snapshot['tables']['teaching_course_works'] = [];
    } elseif ($change === 'different owner') {
        $this->snapshot['users'][] = [...$this->snapshot['users'][0], 'id' => 90002, 'email' => 'other-owner@example.test'];
        $this->snapshot['tables']['teaching_courses'][0]['user_id'] = 90002;
    } elseif ($change === 'different assignment') {
        $this->snapshot['tables']['teaching_course_works'][0]['title'] = 'Different assignment';
    } elseif ($change === 'different course') {
        $this->snapshot['tables']['teaching_courses'][0]['title'] = 'Other course with the same owner';
    } elseif ($change === 'different class') {
        $this->snapshot['tables']['teaching_courses'][0]['classes'] = json_encode(['Other class'], JSON_THROW_ON_ERROR);
    } else {
        $this->snapshot['tables']['teaching_course_works'][] = [
            ...$this->snapshot['tables']['teaching_course_works'][0], 'id' => 99999,
        ];
    }

    $preview = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();

    expect($preview->json('data.token'))->toBeString();
    expect($preview->json('data.conflicts'))->toBe([]);
    expect(implode(' ', $preview->json('data.dropbox_warnings')))->toContain('Sicherheitsbackup', 'keiner anderen Arbeit');
    expect($folder->fresh()->getAttributes())->toBe($attributes);
    $this->assertModelExists($work);
    $result = $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))
        ->assertSuccessful();
    $this->assertDatabaseMissing('teaching_work_dropbox_folders', ['id' => $folder->id]);
    expect($result->json('dropbox_warnings'))->toBe($preview->json('data.dropbox_warnings'));
    $backup = app(TeachingSynchronisationBackup::class)->read(
        "teaching/synchronisation-backups/{$this->school->id}/{$result->json('backup')}.enc"
    );
    expect($backup['dropbox_recovery'])->toBe([$attributes])
        ->and($backup['state']['dropbox_folders'])->toBe([$attributes])
        ->and($backup['state']['tables']['teaching_course_works'][0]['id'])->toBe($work->id);
})->with(['missing work', 'different owner', 'different assignment', 'different course', 'different class', 'ambiguous assignment']);

test('Dropbox folder follows E-Mail from local work 66 to cloud work 63 instead of colliding cloud work 66', function (bool $changedCourseId): void {
    $work = TeachingCourseWork::factory()->create([
        'id' => 66, 'teaching_course_id' => $this->course->id,
        'title' => 'E-Mail', 'type' => 'A5', 'date_for_all_groups' => '2026-10-06',
    ]);
    $connection = DropboxConnection::factory()->create(['user_id' => $this->actor->id]);
    $folder = TeachingWorkDropboxFolder::factory()->create([
        'teaching_course_work_id' => $work->id, 'dropbox_connection_id' => $connection->id,
    ]);
    $attributes = $folder->fresh()->getAttributes();
    $credentials = $connection->fresh()->getRawOriginal('credentials');
    $moduleLink = teachingSyncModuleLink($work, $connection);
    $moduleAttributes = $moduleLink->fresh()->getAttributes();
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    expect($this->snapshot['tables'])->not->toHaveKey('teaching_work_module_links');
    $this->snapshot['tables']['teaching_course_works'][0]['id'] = 63;
    if ($changedCourseId) {
        $this->snapshot['tables']['teaching_courses'][0]['id'] = 901;
        $this->snapshot['tables']['teaching_course_works'][0]['teaching_course_id'] = 901;
    }
    $this->snapshot['tables']['teaching_courses'][] = [
        ...$this->snapshot['tables']['teaching_courses'][0], 'id' => 902, 'title' => 'INF - 5A - 1',
    ];
    $this->snapshot['tables']['teaching_course_works'][] = [
        ...$this->snapshot['tables']['teaching_course_works'][0], 'id' => 66, 'teaching_course_id' => 902,
        'title' => 'E-Mails', 'date_for_all_groups' => '2026-10-05',
    ];

    $preview = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.conflicts'))->toBe([])
        ->and($preview->json('data.dropbox_warnings'))->toBe([])
        ->and(implode(' ', $preview->json('data.dropbox_notices')))->toContain('#66 → #63');
    expect($folder->fresh()->getAttributes())->toBe($attributes);
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))->assertSuccessful();
    expect($folder->fresh()->getAttributes())->toBe([...$attributes, 'teaching_course_work_id' => 63]);
    expect($connection->fresh()->getRawOriginal('credentials'))->toBe($credentials);
    expect($moduleLink->fresh()->getAttributes())->toBe([...$moduleAttributes, 'teaching_course_work_id' => 63]);
    $this->assertDatabaseMissing('teaching_work_dropbox_folders', ['teaching_course_work_id' => 66]);
})->with([false, true]);

test('changed local module links invalidate the reviewed synchronisation preview', function (): void {
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $connection = DropboxConnection::factory()->create(['user_id' => $this->actor->id]);
    $link = teachingSyncModuleLink($work, $connection);
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $this->snapshot['tables']['teaching_course_works'][0]['description'] = 'Incoming description';
    $this->actingAs($this->actor, 'sanctum');
    $token = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful()->json('data.token');
    $link->update(['handshake_revision' => 'changed-after-preview']);

    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();

    expect($link->fresh()->handshake_revision)->toBe('changed-after-preview');
    expect($work->fresh()->description)->not->toBe('Incoming description');
    expect(Storage::disk('local')->allFiles('teaching/synchronisation-backups'))->toBe([]);
});

test('ambiguous local module links remain in the encrypted recovery copy instead of being reassigned', function (): void {
    $first = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id,
        'title' => 'Same module assignment', 'type' => 'A5', 'date_for_all_groups' => '2026-10-06']);
    $second = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id,
        'title' => $first->title, 'type' => $first->type, 'date_for_all_groups' => $first->date_for_all_groups]);
    $connection = DropboxConnection::factory()->create(['user_id' => $this->actor->id]);
    $links = collect([$first, $second])->map(fn (TeachingCourseWork $work): array => teachingSyncModuleLink($work, $connection)->fresh()->getAttributes())->all();
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $this->snapshot['tables']['teaching_course_works'] = [$this->snapshot['tables']['teaching_course_works'][0]];
    $this->actingAs($this->actor, 'sanctum');
    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();

    expect($preview->json('data.conflicts'))->toBe([]);
    expect($preview->json('data.dropbox_warnings'))->toHaveCount(2);
    $result = $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))->assertSuccessful();

    expect(DB::table('teaching_work_module_links')->count())->toBe(0);
    $backup = app(TeachingSynchronisationBackup::class)->read(
        "teaching/synchronisation-backups/{$this->school->id}/{$result->json('backup')}.enc"
    );
    expect($backup['dropbox_module_recovery'])->toBe($links);
    expect($backup['state']['dropbox_module_links'])->toBe($links);
});

test('module links owned by a Dropbox account outside the school block replacement', function (): void {
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $link = teachingSyncModuleLink($work, DropboxConnection::factory()->create());
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));

    $preview = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();

    expect($preview->json('data.token'))->toBeNull();
    expect(implode(' ', $preview->json('data.conflicts')))->toContain('Konto außerhalb der Schule');
    $this->assertModelExists($work);
    $this->assertModelExists($link);
});

test('changed local Dropbox folder choices invalidate the synchronisation preview before replacement', function () {
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $connection = DropboxConnection::factory()->create(['user_id' => $this->actor->id]);
    $folder = TeachingWorkDropboxFolder::factory()->create([
        'teaching_course_work_id' => $work->id, 'dropbox_connection_id' => $connection->id,
    ]);
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $this->snapshot['tables']['teaching_course_works'][0]['description'] = 'Incoming description';
    $this->actingAs($this->actor, 'sanctum');
    $token = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful()->json('data.token');
    $folder->update(['folder_id' => 'id:changed-after-preview']);

    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();

    expect($folder->fresh()->folder_id)->toBe('id:changed-after-preview');
    expect($work->fresh()->description)->not->toBe('Incoming description');
    expect(Storage::disk('local')->allFiles('teaching/synchronisation-backups'))->toBe([]);
});

test('a Dropbox connection from another school blocks replacement of locally linked work', function () {
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $foreignConnection = DropboxConnection::factory()->create();
    $folder = TeachingWorkDropboxFolder::factory()->create([
        'teaching_course_work_id' => $work->id, 'dropbox_connection_id' => $foreignConnection->id,
    ]);
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));

    $preview = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();

    expect($preview->json('data.token'))->toBeNull();
    expect(implode(' ', $preview->json('data.conflicts')))->toContain('Konto außerhalb der Schule');
    $this->assertModelExists($work);
    $this->assertModelExists($folder);
});

test('a failure restoring local Dropbox choices rolls back all replacements and retains the recovery copy', function () {
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $connection = DropboxConnection::factory()->create(['user_id' => $this->actor->id]);
    $folder = TeachingWorkDropboxFolder::factory()->create([
        'teaching_course_work_id' => $work->id, 'dropbox_connection_id' => $connection->id,
    ]);
    $attributes = $folder->fresh()->getAttributes();
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $this->snapshot['tables']['teaching_courses'][0]['description'] = 'Incoming course description';
    $this->actingAs($this->actor, 'sanctum');
    $token = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful()->json('data.token');
    DB::listen(function (QueryExecuted $query): void {
        if (str_starts_with(strtolower($query->sql), 'insert into `teaching_work_dropbox_folders`')) {
            throw new RuntimeException('Fixture failure restoring local Dropbox choices.');
        }
    });

    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();

    expect($this->course->fresh()->title)->toBe('Local');
    expect($folder->fresh()->getAttributes())->toBe($attributes);
    $this->assertModelExists($work);
    expect(Storage::disk('local')->allFiles('teaching/synchronisation-backups'))->toHaveCount(1);
});

test('absent LIVE links preserve local student accounts and records used in another year', function () {
    $account = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'is_active' => true]);
    Role::findOrCreate('lunch_user', 'web');
    $account->assignRole('lunch_user');
    $laterYear = Schoolyear::factory()->create(['school_id' => $this->school->id, 'from' => '2026-09-01', 'until' => '2027-08-31']);
    $oldStudent = Import116::factory()->forSchool($this->school)->importedBy($this->actor)->create([
        'schoolyear_id' => $this->year->id, 'student_code' => 'S1', 'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'email' => $account->email, 'user_id' => $account->id, 'mother_email' => 'local@example.test',
    ]);
    $laterStudent = Import116::factory()->forSchool($this->school)->importedBy($this->actor)->create([
        'schoolyear_id' => $laterYear->id, 'student_code' => 'S1', 'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'email' => $account->email, 'user_id' => $account->id,
    ]);
    $account->update(['import116_id' => $laterStudent->id]);
    $laterAttributes = $laterStudent->fresh()->getAttributes();
    $password = $account->password;
    $active = $account->fresh()->is_active;
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $this->snapshot['import116'] = array_values(array_filter($this->snapshot['import116'], fn (array $row): bool => $row['id'] !== $laterStudent->id));
    $this->snapshot['import116'][0]['user_id'] = null;
    $this->snapshot['import116'][0]['mother_email'] = 'cloud@example.test';
    foreach ($this->snapshot['users'] as &$user) {
        if ($user['id'] === $account->id) {
            $user['import116_id'] = null;
        }
    }
    unset($user);
    $this->snapshot['tables']['teaching_courses'][0]['title'] = 'Cloud synced';
    $this->actingAs($this->actor, 'sanctum');

    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.conflicts'))->toBe([])
        ->and($preview->json('data.changed_contacts.0.fields'))->not->toContain('user_id')
        ->and($oldStudent->fresh()->mother_email)->toBe('local@example.test');
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))->assertSuccessful();

    expect($oldStudent->fresh()->user_id)->toBe($account->id)
        ->and($oldStudent->fresh()->mother_email)->toBe('cloud@example.test')
        ->and($account->fresh()->import116_id)->toBe($laterStudent->id)
        ->and($account->fresh()->password)->toBe($password)
        ->and($account->fresh()->is_active)->toBe($active)
        ->and($account->fresh()->getRoleNames()->all())->toBe(['lunch_user'])
        ->and($laterStudent->fresh()->getAttributes())->toBe($laterAttributes)
        ->and($this->course->fresh()->title)->toBe('Cloud synced');
});

test('Unicode teacher accounts and roster entries survive the full synchronisation', function () {
    $this->actor->update(['email' => 'müller@büro.test']);
    $teacher = Teacher::query()->create(['school_id' => $this->school->id, 'email' => $this->actor->email,
        'first_name' => $this->actor->first_name, 'last_name' => $this->actor->last_name, 'short' => 'MÜ', 'is_active' => true]);
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $this->snapshot['tables']['teaching_courses'][0]['title'] = 'Unicode cloud course';
    $password = $this->actor->password;
    $this->actingAs($this->actor, 'sanctum');
    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.conflicts'))->toBe([])->and($preview->json('data.new_accounts'))->toBe(0);
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))->assertSuccessful();
    expect($this->actor->fresh()->email)->toBe('müller@büro.test')
        ->and($this->actor->fresh()->password)->toBe($password)
        ->and($teacher->fresh()->email)->toBe('müller@büro.test')
        ->and($this->course->fresh()->title)->toBe('Unicode cloud course');
});

test('proven changed student emails and deleted historical aliases preserve the existing login and links', function () {
    $account = User::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Ada',
        'last_name' => 'Lovelace', 'email' => 'local-login@example.test', 'import116_id' => null]);
    Role::findOrCreate('student', 'web');
    $account->assignRole('student');
    $student = Import116::factory()->forSchool($this->school)->importedBy($this->actor)->create([
        'schoolyear_id' => $this->year->id, 'student_code' => 'S1', 'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'birth_date' => '2002-08-03', 'email' => 'cloud-contact@example.test', 'user_id' => $account->id,
    ]);
    $run = Import116Run::query()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'user_id' => $this->actor->id, 'status' => 'finished', 'started_at' => now()]);
    $historical = [...$student->fresh()->getAttributes(), 'user_id' => 90011];
    $change = Import116RunChange::query()->create(['import116_run_id' => $run->id, 'school_id' => $this->school->id,
        'schoolyear_id' => $this->year->id, 'student_code' => 'S1', 'change_type' => 'updated', 'after_snapshot' => $historical]);
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    $this->snapshot['missing_user_ids'] = [90011];
    foreach ($this->snapshot['users'] as &$user) {
        if ($user['id'] === $account->id) {
            $user['id'] = 90010;
            $user['email'] = 'cloud-contact@example.test';
            $user['import116_id'] = $student->id;
        }
    }
    unset($user);
    $this->snapshot['import116'][0]['user_id'] = 90010;
    $this->snapshot['tables']['teaching_courses'][0]['user_id'] = 90010;
    $password = $account->password;
    $this->actingAs($this->actor, 'sanctum');
    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.conflicts'))->toBe([])->and($preview->json('data.new_accounts'))->toBe(0);
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($preview->json('data.token')))->assertSuccessful();
    expect($account->fresh()->email)->toBe('local-login@example.test')
        ->and($account->fresh()->password)->toBe($password)
        ->and($account->fresh()->getRoleNames()->all())->toBe(['student'])
        ->and($account->fresh()->import116_id)->toBe($student->id)
        ->and($student->fresh()->user_id)->toBe($account->id)
        ->and($this->course->fresh()->user_id)->toBe($account->id)
        ->and($change->fresh()->after_snapshot['user_id'])->toBe($account->id)
        ->and(User::where('email', 'cloud-contact@example.test')->exists())->toBeFalse();
});

test('changed local data and another actor invalidate the preview', function () {
    $this->actingAs($this->actor, 'sanctum');
    $token = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful()->json('data.token');
    $other = User::factory()->create(['school_id' => $this->school->id]);
    $other->assignRole('super_admin');
    $this->actingAs($other, 'sanctum')->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();
    $this->actingAs($this->actor, 'sanctum');
    $this->course->update(['title' => 'Changed meanwhile']);
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();
    expect($this->course->fresh()->title)->toBe('Changed meanwhile');
});

test('changed cloud rows or files require a new confirmed preview before creating a backup', function () {
    $this->actingAs($this->actor, 'sanctum');
    $token = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful()->json('data.token');
    $this->snapshot['tables']['teaching_courses'][0]['title'] = 'Changed on cloud';
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();
    expect($this->course->fresh()->title)->toBe('Local');
    $token = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful()->json('data.token');
    $this->snapshot['files'][] = ['path' => 'teaching/new.pdf', 'disk' => 's3', 'content' => base64_encode('new'), 'size' => 3, 'sha256' => hash('sha256', 'new')];
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();
    expect($this->course->fresh()->title)->toBe('Local')
        ->and(Storage::disk('local')->allFiles('teaching/synchronisation-backups'))->toBe([]);
});

test('schema conflicts are visible while foreign teaching IDs are safely remapped', function () {
    $foreign = TeachingCourse::factory()->create();
    $incoming = $this->snapshot['tables']['teaching_courses'][0];
    $incoming['id'] = $foreign->id;
    $this->snapshot['tables']['teaching_courses'][] = $incoming;
    $this->snapshot['columns']['teaching_class_heads'][] = ['name' => 'unknown'];
    $result = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($result->json('data.token'))->toBeNull()
        ->and(implode(' ', $result->json('data.conflicts')))->toContain('Schema');
    expect(implode(' ', $result->json('data.conflicts')))->not->toContain('ID-Kollision');
    expect($foreign->fresh())->not->toBeNull();
    $foreignAttributes = $foreign->fresh()->getAttributes();
    array_pop($this->snapshot['columns']['teaching_class_heads']);
    $token = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful()->json('data.token');
    expect($token)->toBeString();
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertSuccessful();
    expect($foreign->fresh()->getAttributes())->toBe($foreignAttributes)
        ->and(TeachingCourse::where('school_id', $this->school->id)->count())->toBe(2);
});

test('equivalent foreign key order and restrict spelling pass but different cascades block', function () {
    foreach ($this->snapshot['foreign_keys'] as $table => $keys) {
        $this->snapshot['foreign_keys'][$table] = array_reverse(array_map(function (array $key): array {
            $key['name'] = 'different_constraint_name';
            $key['foreign_schema'] = 'different_installation';
            foreach (['on_update', 'on_delete'] as $action) {
                if ($key[$action] === 'no action') {
                    $key[$action] = 'RESTRICT';
                }
            }

            return $key;
        }, $keys));
    }
    $this->actingAs($this->actor, 'sanctum');
    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.conflicts'))->toBe([]);
    $key = &$this->snapshot['foreign_keys']['teaching_curricula'][0];
    $key['on_delete'] = $key['on_delete'] === 'cascade' ? 'set null' : 'cascade';
    $preview = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($preview->json('data.token'))->toBeNull()
        ->and(implode(' ', $preview->json('data.conflicts')))->toContain('Beziehungsschema');
});

test('cross school course groups cannot be deleted through the school sync scope', function () {
    $foreignSchool = School::factory()->create();
    $group = DB::table('user_groups')->insertGetId(['school_id' => $foreignSchool->id, 'type' => 'teaching',
        'name' => 'Foreign group', 'teaching_course_id' => $this->course->id, 'created_by_user_id' => $this->actor->id]);
    $result = $this->actingAs($this->actor, 'sanctum')->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful();
    expect($result->json('data.token'))->toBeNull()
        ->and(implode(' ', $result->json('data.conflicts')))->toContain('anderen Schule');
    expect(DB::table('user_groups')->where('id', $group)->exists())->toBeTrue();
});

test('file failure rolls back replacements and leaves old files and the encrypted safety backup', function () {
    $bytes = 'Cloud attachment';
    $this->snapshot['files'] = [['disk' => 's3', 'path' => 'teaching/cloud.pdf', 'size' => strlen($bytes),
        'sha256' => hash('sha256', $bytes), 'content' => base64_encode($bytes)]];
    $this->snapshot['tables']['teaching_courses'][0]['title'] = 'Cloud';
    $this->actingAs($this->actor, 'sanctum');
    $token = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful()->json('data.token');
    Storage::disk('local')->put('teaching/synchronisation/'.$this->school->id.'/'.hash('sha256', $bytes).'.pdf', 'Conflicting file');
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();
    expect($this->course->fresh()->title)->toBe('Local')
        ->and(Storage::disk('local')->allFiles('teaching/synchronisation-backups'))->toHaveCount(1);
});

test('synchronisation remaps provision people and privately copies their unchanged original evidence', function () {
    config(['filesystems.disks.local.root' => rtrim(Storage::disk('local')->path(''), '/\\')]);
    $path = 'teaching/provision-original.json';
    $bytes = 'Provision original';
    Storage::disk('local')->put($path, $bytes);
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id, 'title' => 'Provision work', 'status' => [
        'dispatch_logs' => [['origin' => 'provision_import', 'purpose' => 'provision', 'file_path' => $path, 'storage_disk' => 'local', 'sha256' => hash('sha256', $bytes)]],
        'provision_events' => [['student_id' => $this->actor->id, 'participant_id' => 'stable-person', 'origin' => 'provision_import', 'method' => 'direct_issue', 'available_at' => '2026-04-01T08:00:00Z']],
        'provision_imports' => [['student_id' => $this->actor->id, 'participant_id' => 'stable-person', 'exercise_id' => 'exercise-1', 'origin' => 'provision_import']],
    ]]);
    $this->snapshot = array_replace($this->snapshot, app(TeachingSynchronisationGraph::class)->capture(DB::connection(), $this->school->id));
    foreach ($this->snapshot['users'] as &$user) {
        if ($user['id'] === $this->actor->id) {
            $user['id'] = 90010;
        }
    }
    unset($user);
    $this->snapshot['tables']['teaching_courses'][0]['user_id'] = 90010;
    $status = json_decode($this->snapshot['tables']['teaching_course_works'][0]['status'], true, flags: JSON_THROW_ON_ERROR);
    $status['provision_events'][0]['student_id'] = $status['provision_imports'][0]['student_id'] = 90010;
    $this->snapshot['tables']['teaching_course_works'][0]['status'] = json_encode($status, JSON_THROW_ON_ERROR);
    $this->snapshot['files'] = [['disk' => 'local', 'path' => $path, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'content' => base64_encode($bytes)]];
    $this->actingAs($this->actor, 'sanctum');
    $token = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful()->assertJsonPath('data.conflicts', [])->json('data.token');
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertSuccessful();
    $restored = TeachingCourseWork::query()->where('title', $work->title)->firstOrFail();
    expect($restored->status['provision_events'][0]['student_id'])->toBe($this->actor->id)
        ->and($restored->status['provision_imports'][0]['student_id'])->toBe($this->actor->id)
        ->and($restored->status['provision_events'][0]['participant_id'])->toBe('stable-person')
        ->and(Storage::disk('local')->get($restored->status['dispatch_logs'][0]['file_path']))->toBe($bytes);
});

test('database failure after file creation rolls back the full graph and cleans only newly copied files', function () {
    $bytes = 'Cloud copy';
    $this->snapshot['files'] = [['disk' => 's3', 'path' => 'teaching/copy.pdf', 'size' => strlen($bytes),
        'sha256' => hash('sha256', $bytes), 'content' => base64_encode($bytes)]];
    $this->snapshot['tables']['teaching_courses'][0]['title'] = 'Cloud';
    $this->actingAs($this->actor, 'sanctum');
    $token = $this->postJson('/api/admin/teaching/synchronisation/preview')->assertSuccessful()->json('data.token');
    DB::listen(function (QueryExecuted $query): void {
        if (str_starts_with(strtolower($query->sql), 'insert into `teaching_courses`')) {
            throw new RuntimeException('Fixture failure after course insertion.');
        }
    });
    $this->postJson('/api/admin/teaching/synchronisation/apply', teachingSyncApplyPayload($token))->assertUnprocessable();
    expect($this->course->fresh()->title)->toBe('Local')
        ->and(Storage::disk('local')->allFiles('teaching/synchronisation/'.$this->school->id))->toBe([])
        ->and(Storage::disk('local')->allFiles('teaching/synchronisation-backups'))->toHaveCount(1);
});
