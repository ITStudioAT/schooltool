<?php

use App\Models\DropboxConnection;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\TeachingWorkModuleLink;
use App\Models\User;
use App\Services\DropboxModuleLinks;
use App\Services\DropboxWorkImport;
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Services\TeachingWorkStudentDistribution;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

require_once dirname(__DIR__).'/Support/MultipleChoiceTestDatabase.php';
requireMultipleChoiceTestDatabase();

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 19:00', 'Europe/Vienna'));
    Storage::fake('local');
    Http::preventStrayRequests();
    config(['app.url' => 'http://localhost:8000', 'services.dropbox.client_id' => 'test-client',
        'services.dropbox.client_secret' => 'test-secret',
        'services.dropbox.redirect_uri' => 'http://localhost:8000/admin/teaching/dropbox/callback']);
    foreach (['teacher', 'student'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    enableSchoolToolModuleForTests($school, 'teaching');
    grantSchoolToolLicenceForTests($school, 'Lehrertool');
    $account = ['school_id' => $school->id, 'schoolyear_id' => $year->id];
    $this->teacher = User::factory()->create($account)->assignRole('teacher');
    $this->otherTeacher = User::factory()->create($account)->assignRole('teacher');
    $this->student = User::factory()->create($account + ['first_name' => 'Ada', 'last_name' => 'Test', 'is_active' => true])->assignRole('student');
    $this->peer = User::factory()->create($account + ['first_name' => 'Sam', 'last_name' => 'Beispiel', 'is_active' => true])->assignRole('student');
    $this->course = TeachingCourse::factory()->create($account + ['user_id' => $this->teacher->id, 'title' => 'IT - 3B - 1', 'classes' => ['3B']]);
    foreach ([$this->student, $this->peer] as $student) {
        $this->course->teachingCourseStudents()->create(['user_id' => $student->id]);
    }
    $this->date = '2026-11-26';
    $this->otherDate = '2026-11-25';
    $this->ended = now()->subHour()->toIso8601String();
    $this->work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id, 'title' => 'Dokument',
        'date_for_all_groups' => null, 'finish_until_date' => '2026-11-27', 'finish_until_time' => '17:00',
        'groups' => [['date' => $this->date, 'student_ids' => [$this->student->id]], ['date' => $this->otherDate, 'student_ids' => [$this->peer->id]]],
        'status' => ['submission_method' => ['channel' => 'portal'],
            'student_distribution' => ['published_at' => now()->subDay()->toIso8601String(), 'ended_at' => $this->ended,
                'student_ids' => [$this->student->id, $this->peer->id]]]]);
    $this->url = route('teaching.work-submissions.copy.prepare', $this->work);
    $this->fileUrl = route('teaching.work-submissions.copy.file', $this->work);
    $connection = DropboxConnection::factory()->create(['user_id' => $this->teacher->id,
        'credentials' => ['refresh_token' => 'synthetic-refresh', 'scopes' => ['files.content.write']]]);
    $this->modulePath = '/schule2020_2027/materialien/lerninhalte/dokument';
    TeachingWorkModuleLink::query()->create(['dropbox_connection_id' => $connection->id, 'account_id' => $connection->account_id,
        'teaching_course_work_id' => $this->work->id, 'module_id' => Str::uuid()->toString(), 'schema_version' => 1,
        'folder_id' => 'id:module', 'folder_name' => 'Dokument', 'folder_path' => $this->modulePath,
        'handshake_file_id' => 'id:handshake', 'handshake_revision' => 'handshake-rev']);
    $this->actingAs($this->teacher, 'sanctum');
});

function portalCopyReceipt(object $test, User $student, string $name, string $bytes, bool $withdrawn = false): array
{
    $id = Str::uuid()->toString();
    $path = "teaching/work_submissions/{$test->course->school_id}/{$test->work->id}/{$id}";
    Storage::disk('local')->put($path, $bytes, 'private');
    $file = ['id' => $id, 'student_id' => $student->id, 'student_name' => $student->full_name, 'name' => $name,
        'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'storage_disk' => 'local', 'file_path' => $path,
        'submitted_at' => now()->subHours(2)->toIso8601String(), 'withdrawn_at' => $withdrawn ? now()->toIso8601String() : null];
    $status = $test->work->status;
    $status['portal_submissions'][] = $file;
    $test->work->update(['status' => $status]);

    return $file;
}

function fakePortalCopyDropbox(object $test, bool $confirm = true, bool $wrongWork = false, bool $destinationExists = true, ?Closure $existing = null): void
{
    $folder = fn (string $id, string $path): array => ['.tag' => 'folder', 'id' => 'id:'.$id, 'path_lower' => $path, 'name' => basename($path)];
    $root = $test->modulePath.'/leistungsfeststellungen';
    $exam = $root.'/'.$test->date.' – 3b_gruppe-1 – dokument';
    $destination = $exam.'/kandidaten/test ada/abgaben';
    $status = json_encode(['schema_version' => 1, 'kind' => 'leistungsfeststellung.status', 'schooltool' => [
        'work_id' => $wrongWork ? $test->work->id + 1 : $test->work->id, 'course_id' => $test->course->id,
    ], 'folder_group' => '3B_Gruppe-1', 'scheduled_date' => $test->date, 'prepared' => true, 'total_points' => 20,
        'components' => ['aufgabenblatt' => ['included' => true, 'points' => 20], 'multiple_choice' => ['included' => false, 'points' => null]]], JSON_THROW_ON_ERROR);
    $stored = [];
    $metadata = [
        'id:handshake' => ['.tag' => 'file', 'id' => 'id:handshake', 'name' => 'handshake.json',
            'rev' => 'handshake-rev', 'size' => 49, 'path_lower' => $test->modulePath.'/handshake.json'],
        'id:module' => $folder('module', $test->modulePath),
        'id:module/Leistungsfeststellungen' => $folder('root', $root),
        'id:root/'.$test->date.' – 3B_Gruppe-1 – Dokument' => $folder('exam', $exam),
        'id:exam' => $folder('exam', $exam),
        'id:exam/status.json' => ['.tag' => 'file', 'size' => strlen($status), 'rev' => 'status-rev'],
        'id:exam/Kandidaten' => $folder('candidates', $exam.'/kandidaten'),
        'id:candidates/Test Ada' => $folder('candidate', $exam.'/kandidaten/test ada'),
        ...($destinationExists ? ['id:candidate/Abgaben' => $folder('destination', $destination)] : []),
    ];
    Http::fake(function (Request $request) use ($status, $metadata, $destination, $confirm, $existing, &$stored) {
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'synthetic-access']);
        }
        if (str_ends_with($request->url(), '/files/get_metadata')) {
            $path = $request['path'];
            if ($existing && str_starts_with($path, 'id:destination/')) {
                $file = $existing(substr($path, strlen('id:destination/')));
                if ($file !== null) {
                    return Http::response(['.tag' => 'file', 'id' => 'id:existing-'.sha1($path), 'rev' => 'existing-rev',
                        'path_lower' => $destination.'/'.mb_strtolower(substr($path, strlen('id:destination/'))),
                        'size' => strlen($file), 'content_hash' => hash('sha256', hash('sha256', $file, true))]);
                }
            }

            return isset($metadata[$path]) || isset($stored[$path])
                ? Http::response($metadata[$path] ?? $stored[$path])
                : Http::response(['error_summary' => 'path/not_found/'], 409);
        }
        if (str_ends_with($request->url(), '/files/list_folder')) {
            return Http::response(['entries' => [], 'has_more' => false]);
        }
        $arg = json_decode($request->header('Dropbox-API-Arg')[0], true, flags: JSON_THROW_ON_ERROR);
        if (str_ends_with($request->url(), '/files/download')) {
            expect($arg['path'])->toBe('rev:status-rev');

            return Http::response($status);
        }
        expect($request->url())->toEndWith('/files/upload')
            ->and($arg)->toMatchArray(['mode' => 'add', 'strict_conflict' => true, 'autorename' => false])
            ->and($arg['path'])->toStartWith('id:destination/')
            ->and($stored)->not->toHaveKey($arg['path']);
        $bytes = $request->body();
        $hashes = array_map(fn (string $block): string => hash('sha256', $block, true), str_split($bytes, 4194304));
        $file = ['id' => 'id:copied', 'path_lower' => $destination.'/'.mb_strtolower(substr($arg['path'], strlen('id:destination/'))),
            'size' => strlen($bytes), 'rev' => 'copy-rev', 'content_hash' => hash('sha256', implode($hashes))];
        if ($confirm) {
            $stored[$arg['path']] = ['.tag' => 'file', ...$file];
        }

        return Http::response($file);
    });
}

function overallPortalStatusRequest(object $test, ?string $date = null): TestResponse
{
    return $test->postJson(route('teaching.work-dropbox.refresh', $test->work), ['overall' => true, 'date' => $date ?? $test->date,
        'finish_until_date' => null, 'finish_until_time' => null, 'expected' => ['date' => null, 'time' => null]]);
}

it('reconciles only the selected active portal originals by content without Dropbox write permission', function (): void {
    $first = portalCopyReceipt($this, $this->student, 'Erste.bin', 'eins');
    $second = portalCopyReceipt($this, $this->student, 'Zweite.bin', 'zwei');
    portalCopyReceipt($this, $this->student, 'Dritte.bin', 'drei');
    portalCopyReceipt($this, $this->student, 'Zurückgezogen.bin', 'withdrawn', true);
    portalCopyReceipt($this, $this->peer, 'Anderer-Termin.bin', 'other');
    $before = $this->work->fresh()->status;
    DropboxConnection::query()->where('user_id', $this->teacher->id)->firstOrFail()->update([
        'credentials' => ['refresh_token' => 'synthetic-refresh', 'scopes' => ['files.content.read', 'files.metadata.read']],
    ]);
    $remote = ['Erste.bin' => 'eins', 'Zweite.bin' => 'vier'];
    fakePortalCopyDropbox($this, existing: function (string $file) use (&$remote): ?string {
        return $remote[$file] ?? null;
    });
    $progress = "saved_work.portal_copy_progress.{$this->date}.complete";

    overallPortalStatusRequest($this)->assertOk()->assertJsonPath('portal_reconciliation', [
        'total' => 3, 'confirmed' => 1, 'missing' => 1, 'different' => 1, 'unverifiable' => 0,
    ])->assertJsonPath($progress, false);
    expect(array_keys($this->work->fresh()->status['portal_submission_copy'][$this->date]['files']))->toBe([$first['id']]);

    $remote['Zweite.bin'] = 'zwei';
    $remote['Dritte.bin'] = 'drei';
    overallPortalStatusRequest($this)->assertOk()->assertJsonPath('portal_reconciliation.confirmed', 3)->assertJsonPath($progress, true);
    $after = $this->work->fresh()->status;
    overallPortalStatusRequest($this)->assertOk()->assertJsonPath($progress, true);
    expect($this->work->fresh()->status)->toBe($after);

    $remote['Erste.bin'] = 'zwei';
    overallPortalStatusRequest($this)->assertOk()->assertJsonPath('portal_reconciliation.different', 1)->assertJsonPath($progress, false);
    $current = $this->work->fresh()->status;
    expect($current['portal_submission_copy'])->toHaveKeys([$this->date])->not->toHaveKey($this->otherDate)
        ->and($current['portal_submission_copy'][$this->date]['files'])->toHaveKey($second['id'])->not->toHaveKey($first['id'])
        ->and(collect($current)->except('portal_submission_copy')->all())->toBe($before);
    Http::assertNotSent(fn (Request $request): bool => ! in_array(parse_url($request->url(), PHP_URL_PATH), [
        '/oauth2/token', '/2/files/get_metadata', '/2/files/list_folder', '/2/files/download',
    ], true));
});

it('leaves ambiguous Dropbox destinations and revisions changed during the overall check open', function (): void {
    portalCopyReceipt($this, $this->student, 'Original.bin', 'original');
    portalCopyReceipt($this, $this->student, 'original.bin', 'conflict');
    portalCopyReceipt($this, $this->student, 'Revision.bin', 'original');
    $seen = [];
    fakePortalCopyDropbox($this, existing: function (string $file) use (&$seen): string {
        $seen[] = $file;

        return count($seen) === 1 ? 'original' : 'changed!';
    });

    overallPortalStatusRequest($this)->assertOk()->assertJsonPath('portal_reconciliation.unverifiable', 3)
        ->assertJsonPath("saved_work.portal_copy_progress.{$this->date}.complete", false);
    expect($seen)->toBe(['Revision.bin', 'Revision.bin'])
        ->and($this->work->fresh()->status['portal_submission_copy'][$this->date]['files'])->toBe([]);
});

it('allows only a current owned scope to save an overall status proof', function (): void {
    $file = portalCopyReceipt($this, $this->student, 'Original.bin', 'original');
    $this->actingAs($this->otherTeacher, 'sanctum');
    overallPortalStatusRequest($this)->assertForbidden();
    Http::assertNothingSent();
    $this->actingAs($this->teacher, 'sanctum');
    fakePortalCopyDropbox($this, existing: function () use ($file): string {
        $current = $this->work->fresh();
        $status = $current->status;
        $status['portal_submissions'][0] = array_replace($file, ['withdrawn_at' => now()->toIso8601String()]);
        $current->update(['status' => $status]);

        return 'original';
    });
    overallPortalStatusRequest($this, '2099-12-31')->assertOk()->assertJsonPath('portal_reconciliation.message',
        'Bitte wähle einen gültigen Durchführungstermin dieser Arbeit.');
    overallPortalStatusRequest($this)->assertOk()->assertJsonPath('portal_reconciliation.message',
        'Arbeit, Portalabgaben oder Kopierstand wurden während der Prüfung geändert. Bitte erneut prüfen.');
    expect($this->work->fresh()->status)->not->toHaveKey('portal_submission_copy');
});

it('copies original bytes for the selected date, preserves every receipt and detects repeats and conflicting originals', function (): void {
    $first = portalCopyReceipt($this, $this->student, 'Original.bin', "a\0b\xff");
    portalCopyReceipt($this, $this->student, 'Original.bin', 'different bytes');
    portalCopyReceipt($this, $this->student, 'withdrawn.bin', 'withdrawn', true);
    portalCopyReceipt($this, $this->peer, 'other-date.bin', 'different date');
    $before = $this->work->fresh()->status;
    fakePortalCopyDropbox($this);
    $run = $this->postJson($this->url, ['date' => $this->date])->assertOk()->assertJsonCount(2, 'data.files')
        ->assertJsonPath('data.candidates', [['id' => $this->student->id, 'name' => $this->student->full_name]])
        ->assertJsonPath('data.files.0.candidate_id', $this->student->id)->json('data.run_id');
    $this->postJson($this->fileUrl, ['run_id' => $run, 'item' => 0])->assertOk()->assertJsonPath('data.status', 'copied')
        ->assertJsonPath("data.portal_copy_progress.{$this->date}.complete", false);
    $this->postJson($this->fileUrl, ['run_id' => $run, 'item' => 1])->assertOk()->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.message', 'Im Abgabeordner gibt es einen Namenskonflikt. Die vorhandene Datei bleibt erhalten.');
    $retry = $this->postJson($this->url, ['date' => $this->date])->assertOk()->json('data.run_id');
    $this->postJson($this->fileUrl, ['run_id' => $retry, 'item' => 0])->assertOk()->assertJsonPath('data.status', 'existing');
    $this->postJson($this->fileUrl, ['run_id' => $retry, 'item' => 2])->assertNotFound();
    $uploads = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/files/upload'));
    expect($uploads)->toHaveCount(1)->and($uploads->first()[0]->body())->toBe("a\0b\xff")
        ->and(collect($this->work->fresh()->status)->except('portal_submission_copy')->all())->toBe($before);
    Storage::disk('local')->assertExists($first['file_path']);
});

it('includes assigned candidates without an active original from the chosen date', function (): void {
    portalCopyReceipt($this, $this->student, 'Original.bin', 'original');
    $this->work->update(['groups' => [['date' => $this->date, 'student_ids' => [$this->student->id, $this->peer->id]]]]);
    fakePortalCopyDropbox($this);
    $data = $this->postJson($this->url, ['date' => $this->date])->assertOk()->assertJsonCount(1, 'data.files')->json('data');
    expect(array_column($data['candidates'], 'id'))->toEqualCanonicalizing([$this->student->id, $this->peer->id])
        ->and(array_column($data['files'], 'candidate_id'))->toBe([$this->student->id]);
});

it('rebuilds the copy recommendation from current active originals and the current target after reload', function (): void {
    portalCopyReceipt($this, $this->student, 'Erste.bin', 'eins');
    $second = portalCopyReceipt($this, $this->student, 'Zweite.bin', 'zwei');
    portalCopyReceipt($this, $this->peer, 'Andere-Gruppe.bin', 'andere');
    fakePortalCopyDropbox($this);
    $progress = fn (): array => app(TeachingCourseWorkEntrySyncService::class)->serializeWork($this->work->fresh())['portal_copy_progress'];
    $releaseVersion = fn (): string => app(TeachingWorkStudentDistribution::class)->version($this->work->fresh());
    $beforeCopy = $releaseVersion();
    $run = $this->postJson($this->url, ['date' => $this->date])->assertOk()->json('data.run_id');
    expect($progress()[$this->date])->toMatchArray(['linked' => true, 'folder_id' => null, 'complete' => false]);
    $this->postJson($this->fileUrl, ['run_id' => $run, 'item' => 0])->assertOk()->assertJsonPath("data.portal_copy_progress.{$this->date}.complete", false);
    $this->postJson($this->fileUrl, ['run_id' => $run, 'item' => 1])->assertOk()->assertJsonPath("data.portal_copy_progress.{$this->date}.complete", true);
    expect($progress()[$this->date])->toMatchArray(['folder_id' => 'id:exam', 'complete' => true])
        ->and($progress()[$this->otherDate])->toMatchArray(['folder_id' => null, 'complete' => false])
        ->and($releaseVersion())->toBe($beforeCopy);

    $this->work->refresh();
    $new = portalCopyReceipt($this, $this->student, 'Neu.bin', 'neu');
    expect($progress()[$this->date]['complete'])->toBeFalse();
    $status = $this->work->status;
    foreach ($status['portal_submissions'] as &$file) {
        if (in_array($file['id'], [$new['id'], $second['id']], true)) {
            $file['withdrawn_at'] = now()->toIso8601String();
        }
    }
    unset($file);
    $this->work->update(['status' => $status]);
    expect($progress()[$this->date]['complete'])->toBeTrue();

    $link = TeachingWorkModuleLink::query()->where('teaching_course_work_id', $this->work->id)->firstOrFail();
    $connection = DropboxConnection::query()->findOrFail($link->dropbox_connection_id);
    $this->travel(1)->minutes();
    app(DropboxModuleLinks::class)->rememberInitializedExercise($connection, $this->work, $link, $this->otherDate, [
        'id' => 'id:other-date', 'name' => 'Anderer Termin', 'path_lower' => '/synthetic/other-date',
    ]);
    expect($progress()[$this->date]['complete'])->toBeTrue();
    app(DropboxModuleLinks::class)->rememberInitializedExercise($connection, $this->work, $link, $this->date, [
        'id' => 'id:replacement', 'name' => 'Ersatz', 'path_lower' => '/synthetic/replacement',
    ]);
    expect($progress()[$this->date])->toMatchArray(['linked' => true, 'folder_id' => null, 'complete' => false]);

    $link->update(['folder_id' => 'id:new-module']);
    expect($progress()[$this->date])->toMatchArray(['linked' => true, 'folder_id' => null, 'complete' => false]);
});

it('returns the cold-copy state in the normal work list and detail payloads from a saved binding', function (string $binding): void {
    portalCopyReceipt($this, $this->student, 'Original.bin', 'original');
    if ($binding !== 'module') {
        $module = TeachingWorkModuleLink::query()->where('teaching_course_work_id', $this->work->id)->firstOrFail();
        TeachingWorkDropboxFolder::query()->create(['teaching_course_work_id' => $this->work->id,
            'dropbox_connection_id' => $module->dropbox_connection_id, 'folder_id' => 'id:exam', 'folder_name' => 'Älterer Prüfungsordner']);
        $module->delete();
    }
    $list = '/api/admin/teaching/course_works?course_id='.$this->course->id;
    $detail = '/api/admin/teaching/course_works/'.$this->work->id;
    $path = "portal_copy_progress.{$this->date}";
    $this->getJson($list)->assertOk()->assertJsonPath("data.0.{$path}.linked", true)
        ->assertJsonPath("data.0.{$path}.folder_id", null)->assertJsonPath("data.0.{$path}.complete", false);
    $this->getJson($detail)->assertOk()->assertJsonPath("data.{$path}.linked", true)
        ->assertJsonPath("data.{$path}.complete", false);
    Http::assertNothingSent();

    if ($binding === 'folder') {
        fakePortalCopyDropbox($this);
        $run = $this->postJson($this->url, ['date' => $this->date])->assertOk()->json('data.run_id');
        $this->postJson($this->fileUrl, ['run_id' => $run, 'item' => 0])->assertOk()
            ->assertJsonPath("data.{$path}.complete", true);
        $this->getJson($detail)->assertOk()->assertJsonPath("data.{$path}.complete", true);
    }
})->with(['module', 'folder']);

it('reports a missing target before contacting Dropbox for a closed portal work with originals', function (): void {
    portalCopyReceipt($this, $this->student, 'Original.bin', 'original');
    TeachingWorkModuleLink::query()->where('teaching_course_work_id', $this->work->id)->delete();
    DropboxConnection::query()->where('user_id', $this->teacher->id)->delete();
    $before = $this->work->fresh()->status;

    $this->getJson('/api/admin/teaching/course_works/'.$this->work->id)->assertOk()
        ->assertJsonPath('data.portal_copy_progress', []);
    $this->postJson($this->url, ['date' => $this->date])->assertConflict()
        ->assertJsonPath('message', 'Für diesen Termin fehlt eine eindeutige Dropbox-Zuordnung. Bitte die Arbeit unter „Vorbereiten“ verknüpfen.');
    Http::assertNothingSent();
    expect($this->work->fresh()->status)->toBe($before);
});

it('suggests only a unique dated exam even when the general folder search finds no candidate', function (string $result): void {
    $root = '/schule2020_2027/Materialien/Lerninhalte';
    $this->partialMock(DropboxWorkImport::class, function ($mock) use ($root): void {
        $mock->shouldReceive('suggestFolders')->once()->andReturn([
            'suggested_folder' => null, 'suggestions' => [], 'start_folder' => ['id' => 'id:root', 'name' => 'Lerninhalte', 'path' => $root],
        ]);
    });
    $name = $this->date.' – 3B_Gruppe-1 – Dokument';
    $folder = fn (string $id, string $module): array => ['metadata' => ['metadata' => [
        '.tag' => 'folder', 'id' => $id, 'name' => $name,
        'path_lower' => mb_strtolower($root.'/'.$module.'/Leistungsfeststellungen/'.$name),
    ]]];
    Http::fake([
        '*/oauth2/token' => Http::response(['access_token' => 'synthetic-access']),
        '*/files/search_v2' => function (Request $request) use ($root, $name, $folder, $result) {
            expect($request['query'])->toBe($name)->and($request['options']['path'])->toBe($root);

            return Http::response(['matches' => [$folder('id:exam', 'Word'),
                ...($result === 'ambiguous' ? [$folder('id:other-exam', 'Word-Kopie')] : [])],
                'has_more' => $result === 'incomplete']);
        },
    ]);
    $response = $this->getJson(route('teaching.work-dropbox.folders', ['course_work' => $this->work,
        'suggest' => 1, 'portal_date' => $this->date]))->assertOk()->assertJsonPath('suggested_folder', null);
    if ($result === 'unique') {
        $response->assertJsonPath('portal_candidate.id', 'id:exam');
    } else {
        $response->assertJsonMissingPath('portal_candidate');
    }
    $this->assertDatabaseMissing('teaching_work_dropbox_folders', ['teaching_course_work_id' => $this->work->id]);
})->with(['unique', 'ambiguous', 'incomplete']);

it('links a dated portal target without import checksums and advances from target confirmation to a confirmed copy', function (): void {
    portalCopyReceipt($this, $this->student, 'Original.bin', 'original');
    TeachingWorkModuleLink::query()->where('teaching_course_work_id', $this->work->id)->delete();
    fakePortalCopyDropbox($this);
    $url = route('teaching.work-dropbox.store-folder', $this->work);
    $scope = ['folder_id' => 'id:exam', 'portal_date' => $this->date];
    $this->getJson(route('teaching.work-dropbox.show', $this->work))->assertOk()
        ->assertJsonPath('folder_verification_required', false);
    $this->postJson($url, $scope)->assertConflict();
    $this->postJson($url, array_replace($scope, ['folder_id' => 'id:module', 'verify' => true]))->assertConflict();
    $this->postJson($url, array_replace($scope, ['portal_date' => $this->otherDate, 'verify' => true]))->assertConflict();
    $proof = $this->postJson($url, $scope + ['verify' => true])->assertOk()
        ->assertJsonPath('identity', 'portal_target')->assertJsonPath('checked_files', [])->json('proof');
    $this->assertDatabaseMissing('teaching_work_dropbox_folders', ['teaching_course_work_id' => $this->work->id]);
    $path = "portal_copy_progress.{$this->date}";
    $this->postJson($url, $scope + ['folder_proof' => $proof])->assertOk()->assertJsonPath('folder.id', 'id:exam')
        ->assertJsonPath("saved_work.{$path}.folder_id", 'id:exam')->assertJsonPath("saved_work.{$path}.complete", false);
    $this->getJson('/api/admin/teaching/course_works/'.$this->work->id)->assertOk()
        ->assertJsonPath("data.{$path}.complete", false)->assertJsonPath("data.{$path}.folder_id", 'id:exam');
    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/files/upload'));

    $run = $this->postJson($this->url, ['date' => $this->date])->assertOk()->json('data.run_id');
    $this->postJson($this->fileUrl, ['run_id' => $run, 'item' => 0])->assertOk()->assertJsonPath("data.{$path}.complete", true);
});

it('still requires original proofs when a portal target is selected for a previously imported work', function (): void {
    portalCopyReceipt($this, $this->student, 'Original.bin', 'original');
    $this->work->update(['status' => $this->work->status + ['folder_imported_at' => now()->toIso8601String()]]);
    TeachingWorkModuleLink::query()->where('teaching_course_work_id', $this->work->id)->delete();
    fakePortalCopyDropbox($this);
    $this->getJson(route('teaching.work-dropbox.show', $this->work))->assertOk()
        ->assertJsonPath('folder_verification_required', true);
    $this->postJson(route('teaching.work-dropbox.store-folder', $this->work), [
        'folder_id' => 'id:exam', 'portal_date' => $this->date, 'verify' => true,
    ])->assertUnprocessable()->assertJsonPath('message', 'Keine gespeicherten Originalprüfsummen für einen sicheren Ordnerabgleich vorhanden.');
    $this->assertDatabaseMissing('teaching_work_dropbox_folders', ['teaching_course_work_id' => $this->work->id]);
});

it('stops stale running copies when the selected date has reopened while ignoring a reopening for another date', function (): void {
    portalCopyReceipt($this, $this->student, 'Original.bin', 'bytes');
    $reopening = fn (User $student, string $date): array => ['student_id' => $student->id, 'date' => $date,
        'ends_at' => now()->addHour()->utc()->toIso8601String(), 'release_ended_at' => $this->ended];
    $this->work->update(['status' => $this->work->status + ['portal_submission_reopenings' => [$reopening($this->peer, $this->otherDate)]]]);
    fakePortalCopyDropbox($this);
    $run = $this->postJson($this->url, ['date' => $this->date])->assertOk()->json('data.run_id');
    $this->work->update(['status' => array_replace($this->work->status, ['portal_submission_reopenings' => [
        $reopening($this->student, $this->date),
    ]])]);
    $this->postJson($this->fileUrl, ['run_id' => $run, 'item' => 0])->assertConflict();
    $this->postJson($this->url, ['date' => $this->date])->assertConflict();
    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/files/upload'));
});

it('requires teacher access, the terminal release and an unambiguous date before reading Dropbox', function (): void {
    portalCopyReceipt($this, $this->student, 'Original.bin', 'bytes');
    $this->postJson($this->url, [])->assertUnprocessable();
    $this->postJson($this->url, ['date' => '2026-11-19'])->assertUnprocessable();
    $this->actingAs($this->otherTeacher, 'sanctum')->postJson($this->url, ['date' => $this->date])->assertForbidden();
    $this->actingAs($this->student, 'sanctum')->postJson($this->fileUrl, ['run_id' => Str::uuid()->toString(), 'item' => 0])->assertForbidden();
    $status = $this->work->status;
    unset($status['student_distribution']['ended_at']);
    $status['student_distribution']['withdrawn_at'] = $this->ended;
    $this->work->update(['status' => $status]);
    $this->actingAs($this->teacher, 'sanctum')->postJson($this->url, ['date' => $this->date])->assertConflict();
    Http::assertNothingSent();
});

it('leaves an unconfirmed upload open and forbids a missing folder or an exam belonging to another work', function (string $failure): void {
    portalCopyReceipt($this, $this->student, 'Original.bin', 'original');
    fakePortalCopyDropbox($this, confirm: $failure !== 'unconfirmed', wrongWork: $failure === 'wrong work', destinationExists: $failure !== 'missing folder');
    $plan = $this->postJson($this->url, ['date' => $this->date]);
    if ($failure === 'wrong work') {
        $plan->assertConflict();
    } else {
        $run = $plan->assertOk()->json('data.run_id');
        $this->postJson($this->fileUrl, ['run_id' => $run, 'item' => 0])->assertOk()->assertJsonPath('data.status', 'open');
    }
    expect(Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/files/upload')))->toHaveCount($failure === 'unconfirmed' ? 1 : 0);
})->with(['unconfirmed', 'wrong work', 'missing folder']);

it('reports ambiguous candidates, including a namesake from an earlier assignment', function (string $ambiguity): void {
    portalCopyReceipt($this, $this->student, 'Original.bin', 'original');
    if ($ambiguity === 'two dates') {
        $groups = $this->work->groups;
        $groups[1]['student_ids'][] = $this->student->id;
        $this->work->update(['groups' => $groups]);
    } else {
        $this->peer->update(['first_name' => $this->student->first_name, 'last_name' => $this->student->last_name]);
        $this->course->teachingCourseStudents()->where('user_id', $this->peer->id)->update(['canceled_at' => now()]);
    }
    fakePortalCopyDropbox($this);
    $this->postJson($this->url, ['date' => $this->date])->assertOk()->assertJsonCount(1, 'data.files')
        ->assertJsonPath('data.run_id', null)->assertJsonPath('data.files.0.message',
            'Die Datei oder der persönliche Prüfungsordner ist dieser Person und diesem Termin nicht eindeutig zugeordnet.');
    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/files/upload'));
})->with(['two dates', 'namesake']);

it('confirms a multi-block original and rejects changed private bytes with the same recorded size', function (): void {
    $large = str_repeat('W', 4194304).'end';
    portalCopyReceipt($this, $this->student, 'Gross.bin', $large);
    $small = portalCopyReceipt($this, $this->student, 'Original.bin', 'copy');
    Storage::disk('local')->put($small['file_path'], 'fake');
    fakePortalCopyDropbox($this);
    $run = $this->postJson($this->url, ['date' => $this->date])->assertOk()->json('data.run_id');
    $this->postJson($this->fileUrl, ['run_id' => $run, 'item' => 0])->assertOk()->assertJsonPath('data.status', 'copied');
    $this->postJson($this->fileUrl, ['run_id' => $run, 'item' => 1])->assertOk()->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.message', 'Die Prüfsumme des Portaloriginals stimmt nicht.');
    expect(Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/files/upload')))->toHaveCount(1);
});
