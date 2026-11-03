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
use App\Services\TeachingCourseWorkEntrySyncService;
use App\Services\TeachingWorkAiExport;
use App\Services\TeachingWorkDispatchImport;
use App\Services\TeachingWorkJsonImport;
use App\Support\SchooltoolAssessmentJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\Support\TeachingWorkDispatchFixture;
use Tests\Support\TeachingWorkJsonFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('local');
    config(['app.url' => 'http://localhost:8000', 'schooltool.preview.instance' => false,
        'services.dropbox.client_id' => 'test-client', 'services.dropbox.client_secret' => 'test-secret',
        'services.dropbox.redirect_uri' => 'http://localhost:8000/admin/teaching/dropbox/callback']);
});

function portableIdentityFixture(object $test, int $count = 2): array
{
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id, 'name' => '2026/27', 'concerns' => '2026/27']);
    enableSchoolToolModuleForTests($school, 'teaching');
    grantSchoolToolLicenceForTests($school, 'Lehrertool');
    Role::findOrCreate('teacher', 'web');
    $actor = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id])->assignRole('teacher');
    $area = TeachingEntryArea::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id, 'user_id' => $actor->id]);
    TeachingEntryDefinition::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id, 'user_id' => $actor->id,
        'teaching_entry_area_id' => $area->id, 'short_name' => 'LF', 'category' => 'Benotung', 'has_properties' => true, 'properties_mode' => 'points', 'maximum_points' => 5]);
    $course = TeachingCourse::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id,
        'user_id' => $actor->id, 'title' => 'Gruppe 1', 'classes' => ['3B'], 'teaching_entry_area_id' => $area->id]);
    $work = TeachingCourseWork::factory()->create(['teaching_course_id' => $course->id, 'type' => 'LF', 'title' => 'Leistungsarbeit',
        'date_for_all_groups' => '2026-10-06', 'finish_until_date' => '2026-10-07', 'finish_until_time' => '12:00', 'is_group_work' => false, 'groups' => [], 'status' => []]);
    $people = [];
    for ($index = 0; $index < $count; $index++) {
        $people[] = $person = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id,
            'first_name' => 'Person'.$index, 'last_name' => 'Test'.$index, 'schoolclass' => '3B', 'email' => 'person'.$index.'@example.test']);
        $course->teachingCourseStudents()->create(['user_id' => $person->id]);
    }
    app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);
    $source = app(TeachingWorkAiExport::class)->build($work->fresh(), $actor)['context'];
    $sourceIds = array_reverse(array_map(fn (User $person): string => (string) $person->id, $people));
    $idMap = [];
    foreach ($people as $index => $person) {
        $idMap[(string) $person->id] = $sourceIds[$index];
    }
    foreach ($source['course_roster']['participants'] as &$person) {
        $person['schooltool_person_id'] = $idMap[$person['schooltool_person_id']];
        $person['participant_id'] = 'user:'.$person['schooltool_person_id'];
    }
    unset($person);
    foreach ($source['work']['groups'] as &$group) {
        $group['schooltool_student_ids'] = array_map(fn ($id): string => $idMap[(string) $id], $group['schooltool_student_ids']);
    }
    unset($group);
    $base = TeachingWorkJsonFixture::package();
    $absent = TeachingWorkJsonFixture::withFinalDownload($base)['records'][0];
    $package = $base;
    $package['records'] = [];
    foreach ($people as $index => $person) {
        $record = $count === 17 && $index < 7 ? $absent : $base['records'][0];
        $record['participant_id'] = 'person-'.$index;
        $record['identity'] = ['first_name' => $person->first_name, 'last_name' => $person->last_name,
            'class_name' => '3B', 'group_name' => 'Gruppe 1', 'schooltool_person_id' => $sourceIds[$index]];
        $package['records'][] = $record;
    }
    if ($count === 17) {
        $package = TeachingWorkJsonFixture::withFinalDownload($package);
    }
    $package = TeachingWorkJsonFixture::sign($package);
    $test->actingAs($actor, 'sanctum');

    return compact('school', 'year', 'actor', 'course', 'work', 'people', 'package', 'source');
}

function portableIdentityUpload(array $source): UploadedFile
{
    return UploadedFile::fake()->createWithContent('Schooltool_Arbeitsdaten.json', json_encode($source, JSON_THROW_ON_ERROR));
}

test('portable identities preview all seventeen original records through either import entry without applying', function (string $entry): void {
    $data = portableIdentityFixture($this, 17);
    ['actor' => $actor, 'work' => $work, 'package' => $package, 'source' => $source] = $data;
    $before = $work->fresh()->getAttributes();
    $packageText = json_encode($package, JSON_THROW_ON_ERROR);
    $sourceText = json_encode($source, JSON_THROW_ON_ERROR);
    $url = '/api/admin/teaching/course_works/'.$work->id;
    $payload = ['package' => TeachingWorkJsonFixture::upload($package), 'identity_export' => portableIdentityUpload($source)];
    if ($entry === 'dropbox') {
        $connection = DropboxConnection::factory()->create(['user_id' => $actor->id]);
        TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $connection->id, 'teaching_course_work_id' => $work->id,
            'folder_id' => 'id:portable', 'folder_name' => 'Leistungsarbeit']);
        $contents = ['Schooltool-Bewertungen.json' => $packageText, 'Schooltool_Arbeitsdaten.json' => $sourceText];
        Http::fake(function (Request $request) use ($contents) {
            if (str_ends_with($request->url(), '/oauth2/token')) {
                return Http::response(['access_token' => 'test-access']);
            }
            if (str_ends_with($request->url(), '/files/get_metadata')) {
                return Http::response(['.tag' => 'folder', 'id' => 'id:portable', 'name' => 'Leistungsarbeit', 'path_lower' => '/leistungsarbeit']);
            }
            if (str_ends_with($request->url(), '/files/list_folder')) {
                return Http::response(['has_more' => false, 'entries' => collect($contents)->map(fn (string $text, string $path): array => [
                    '.tag' => 'file', 'name' => $path, 'path_lower' => '/leistungsarbeit/'.mb_strtolower($path), 'rev' => hash('sha256', $path), 'size' => strlen($text),
                ])->values()->all()]);
            }
            $argument = json_decode($request->header('Dropbox-API-Arg')[0] ?? '{}', true);
            foreach ($contents as $path => $text) {
                if (($argument['path'] ?? null) === 'rev:'.hash('sha256', $path)) {
                    return Http::response($text);
                }
            }
            throw new RuntimeException('Unexpected request');
        });
        $payload = [];
        $url .= '/dropbox/import';
    } else {
        $url .= '/import-json';
    }
    $preview = $this->postJson($url, $payload)->assertOk()->assertJsonPath('preview.can_import', true)->json('preview');
    expect($preview['rows'])->toHaveCount(17)
        ->and(collect($preview['rows'])->where('submission_state', 'not_received'))->toHaveCount(7)
        ->and($preview['package_checksum'])->toBe($package['package_checksum'])
        ->and($preview['identity_binding']['source_sha256'])->toBe(hash('sha256', $sourceText))
        ->and($work->fresh()->getAttributes())->toBe($before);
    foreach ($preview['rows'] as $index => $row) {
        expect($row['student_id'])->toBe($data['people'][$index]->id)->and($row['identity'])->toBe($package['records'][$index]['identity']);
    }
    expect($preview['identity_binding']['student_ids'])->toHaveCount(17);
    Storage::disk('local')->assertEmpty();
})->with(['normal', 'dropbox']);

test('portable identities reject incomplete ambiguous foreign and changed roster evidence', function (string $change): void {
    $data = portableIdentityFixture($this);
    ['source' => $source, 'work' => $work, 'package' => $package] = $data;
    match ($change) {
        'school' => $source['school']['name'] = 'Other school',
        'year' => $source['schoolyear']['name'] = '2025/26',
        'course' => $source['course']['title'] = 'Other course',
        'work' => $source['work']['title'] = 'Other work',
        'missing' => array_pop($source['course_roster']['participants']),
        'extra' => $source['course_roster']['participants'][] = $source['course_roster']['participants'][0],
        'ambiguous' => $source['course_roster']['participants'][1] = $source['course_roster']['participants'][0],
        'email' => $source['course_roster']['participants'][0]['email'] = 'foreign@example.test',
        'active' => $source['course_roster']['participants'][0]['active'] = false,
        'incomplete' => $source['course_roster']['complete'] = false,
        'groups' => $source['work']['groups'][0]['schooltool_student_ids'] = [],
        'date' => $source['work']['scheduled_date'] = '2026-10-08',
        'source context id' => $source['schoolyear']['id'] = 0,
        'source participant id' => $source['course_roster']['participants'][0]['participant_id'] = 'user:999999',
    };
    $before = $work->fresh()->getAttributes();
    $this->postJson('/api/admin/teaching/course_works/'.$work->id.'/import-json', [
        'package' => TeachingWorkJsonFixture::upload($package), 'identity_export' => portableIdentityUpload($source),
    ])->assertUnprocessable()->assertJsonValidationErrors('package');
    expect($work->fresh()->getAttributes())->toBe($before);
    Http::assertNothingSent();
})->with(['school', 'year', 'course', 'work', 'missing', 'extra', 'ambiguous', 'email', 'active', 'incomplete', 'groups', 'date', 'source context id', 'source participant id']);

test('legacy identical ids still preview without an export and unchanged id permits a later contact update', function (): void {
    $data = portableIdentityFixture($this);
    ['work' => $work, 'package' => $package, 'source' => $source, 'people' => $people] = $data;
    foreach ($package['records'] as $index => &$record) {
        $record['identity']['schooltool_person_id'] = (string) $people[$index]->id;
    }
    unset($record);
    $url = '/api/admin/teaching/course_works/'.$work->id.'/import-json';
    $this->postJson($url, ['package' => TeachingWorkJsonFixture::upload($package)])->assertOk()->assertJsonPath('preview.can_import', true);
    $source = app(TeachingWorkAiExport::class)->build($work->fresh(), $data['actor'])['context'];
    $source['course_roster']['participants'][0]['email'] = 'old-address@example.test';
    $this->postJson($url, ['package' => TeachingWorkJsonFixture::upload($package), 'identity_export' => portableIdentityUpload($source)])
        ->assertOk()->assertJsonPath('preview.can_import', true);
});

test('portable binding freshness changes when the target contact changes and keeps original source checksum', function (): void {
    $data = portableIdentityFixture($this);
    $importer = app(TeachingWorkJsonImport::class);
    $text = json_encode($data['package'], JSON_THROW_ON_ERROR);
    $sourceText = json_encode($data['source'], JSON_THROW_ON_ERROR);
    $bundle = $importer->parse($text, [], $sourceText);
    $preview = $importer->preview($data['work']->fresh(), $data['actor'], $bundle);
    $data['people'][0]->update(['email' => 'changed@example.test']);
    expect(fn () => $importer->preview($data['work']->fresh(), $data['actor'], $bundle))->toThrow(ValidationException::class);
    expect($bundle['package'])->toBe($data['package'])->and($bundle['identity_export']['sha256'])->toBe(hash('sha256', $sourceText));
});

test('portable availability evidence binds original provision identities before a version two submission check', function (bool $foreignIdentity): void {
    $data = portableIdentityFixture($this);
    ['work' => $work, 'source' => $source, 'package' => $package] = $data;
    $work->update(['finish_until_date' => '2026-10-06']);
    $package = TeachingWorkJsonFixture::withSubmissionCheck($package);
    $provision = TeachingWorkJsonFixture::provision($package);
    if ($foreignIdentity) {
        $provision['participants'][0]['identity']['schooltool_person_id'] = '999999';
        unset($provision['provision_checksum']);
        $provision['provision_checksum'] = SchooltoolAssessmentJson::digest($provision);
    }
    $text = json_encode($provision, JSON_THROW_ON_ERROR);
    $package = TeachingWorkJsonFixture::withAvailabilityCheck($package, hash('sha256', $text));
    $before = $work->fresh()->getAttributes();
    $response = $this->postJson('/api/admin/teaching/course_works/'.$work->id.'/import-json', [
        'package' => TeachingWorkJsonFixture::upload($package), 'identity_export' => portableIdentityUpload($source),
        'folder' => 'Leistungsarbeit', 'documents' => json_encode([['path' => 'Leistungsarbeit/Schooltool-Bereitstellung.json', 'text' => $text]], JSON_THROW_ON_ERROR),
    ]);
    if ($foreignIdentity) {
        $response->assertUnprocessable()->assertJsonValidationErrors('package');
    } else {
        $response->assertOk()->assertJsonPath('preview.can_import', true)->assertJsonPath('preview.provisions.0.can_import', true)
            ->assertJsonPath('preview.rows.0.student_id', $data['people'][0]->id)
            ->assertJsonPath('preview.provisions.0.rows.0.student_id', $data['people'][0]->id);
    }
    expect($work->fresh()->getAttributes())->toBe($before);
    Http::assertNothingSent();
})->with([false, true]);

test('portable preparation and live dispatch preserve original identity and current email guards', function (): void {
    $data = portableIdentityFixture($this);
    ['work' => $work, 'actor' => $actor, 'source' => $source, 'package' => $package] = $data;
    $importer = app(TeachingWorkJsonImport::class);
    $bundle = $importer->parse(json_encode($package, JSON_THROW_ON_ERROR), [], json_encode($source, JSON_THROW_ON_ERROR));
    $binding = $importer->identityBinding($work->fresh(), $actor, $bundle);
    $dispatches = app(TeachingWorkDispatchImport::class);
    $preparation = TeachingWorkDispatchFixture::documentPreparation($work->id, $work->teaching_course_id);
    $preparation['exercise'] = ['title' => $work->title, 'course_title' => $work->teachingCourse->title, 'scheduled_date' => '2026-10-06'];
    $identity = $package['records'][0]['identity'];
    $preparation['participants'] = [['participant_id' => 'person-0'] + array_intersect_key($identity, array_flip(['schooltool_person_id', 'first_name', 'last_name', 'class_name']))];
    unset($preparation['preparation_checksum']);
    $preparation['preparation_checksum'] = SchooltoolAssessmentJson::digest($preparation);
    $text = json_encode($preparation, JSON_THROW_ON_ERROR);
    $preview = $dispatches->preview($work->fresh(), $dispatches->parse($text), hash('sha256', $text), requireMatchingTitle: false, identityBinding: $binding);
    expect($preview['can_import'])->toBeTrue()->and($preview['rows'][0]['student_id'])->toBe($data['people'][0]->id)
        ->and($preview['rows'][0]['accepted'])->toBeFalse();
    $recipient = array_replace(TeachingWorkDispatchFixture::recipients()[0], ['Vorname' => $identity['first_name'], 'Nachname' => $identity['last_name'],
        'Klasse' => '3B', 'Kurs' => 'Gruppe 1', 'To' => $data['people'][0]->email, 'Betreff' => 'Ergebnisse zur Leistungsfeststellung: Leistungsarbeit',
        'Providerzeit' => '2026-10-07T12:15:39+02:00']);
    $text = TeachingWorkDispatchFixture::text([$recipient]);
    $report = $dispatches->parse($text);
    $preview = $dispatches->preview($work->fresh(), $report, hash('sha256', $text), requireMatchingTitle: false, identityBinding: $binding);
    expect($preview['can_import'])->toBeTrue()->and($preview['rows'][0]['student_id'])->toBe($data['people'][0]->id)->and($preview['rows'][0]['accepted'])->toBeTrue();
    $report['recipients'][0]['To'] = 'foreign@example.test';
    expect(fn () => $dispatches->preview($work->fresh(), $report, str_repeat('a', 64), requireMatchingTitle: false, identityBinding: $binding))
        ->toThrow(ValidationException::class);
    Http::assertNothingSent();
});

test('portable import receipt retains original checksums records and verified provenance only in the owned test database', function (): void {
    $data = portableIdentityFixture($this);
    ['work' => $work, 'package' => $package, 'source' => $source] = $data;
    $url = '/api/admin/teaching/course_works/'.$work->id.'/import-json';
    $payload = fn (): array => ['package' => TeachingWorkJsonFixture::upload($package), 'identity_export' => portableIdentityUpload($source)];
    $preview = $this->postJson($url, $payload())->assertOk()->assertJsonPath('preview.can_import', true)->json('preview');
    $this->postJson($url, $payload() + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    $status = $work->fresh()->status;
    expect($status['assessment_json_packages'][0]['package_checksum'])->toBe($package['package_checksum'])
        ->and($status['assessment_json_packages'][0]['identity_binding']['source_sha256'])->toBe(hash('sha256', json_encode($source, JSON_THROW_ON_ERROR)))
        ->and($status['assessment_json_imports'][0]['record'])->toEqual($package['records'][0])
        ->and($status['assessment_json_imports'][0]['student_id'])->toBe($data['people'][0]->id);
    Http::assertNothingSent();
});

test('portable regrading accepts only proven group order normalization with unchanged original submission evidence', function (string $change): void {
    ['work' => $work, 'package' => $package, 'source' => $source] = portableIdentityFixture($this);
    $work->update(['finish_until_date' => '2026-10-06']);
    $source['work']['groups'] = array_reverse($source['work']['groups']);
    $package = TeachingWorkJsonFixture::withSubmissionCheck($package);
    $url = '/api/admin/teaching/course_works/'.$work->id.'/import-json';
    $payload = fn (): array => ['package' => TeachingWorkJsonFixture::upload($package), 'identity_export' => portableIdentityUpload($source)];
    $preview = $this->postJson($url, $payload())->assertOk()->assertJsonPath('preview.can_import', true)->json('preview');
    $this->postJson($url, $payload() + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    $status = $work->fresh()->status;
    $status['submission_checks'][0]['roster'] = $status['assessment_json_packages'][0]['identity_binding']['source_roster'];
    $work->update(['status' => $status]);
    $work->update(['groups' => array_reverse($work->fresh()->groups)]);
    $package['records'][0]['comment'] = 'Fachlich korrigierte Neubewertung.';
    if ($change === 'submission') {
        $package['records'][0]['source_fingerprint'] = str_repeat('e', 64);
    } elseif ($change === 'source export') {
        $source['exported_at'] = '2026-10-08T12:00:00Z';
    } elseif ($change === 'roster') {
        $status = $work->fresh()->status;
        $status['submission_checks'][0]['roster'] = str_repeat('f', 64);
        $work->update(['status' => $status]);
    }
    $package = TeachingWorkJsonFixture::sign($package);
    $before = $work->fresh()->getAttributes();
    $response = $this->postJson($url, ['package' => TeachingWorkJsonFixture::upload($package), 'identity_export' => portableIdentityUpload($source)]);
    if ($change === 'order only') {
        $response->assertOk()->assertJsonPath('preview.can_import', true);
    } else {
        $response->assertUnprocessable();
    }
    expect($work->fresh()->getAttributes())->toBe($before);
    Http::assertNothingSent();
})->with(['order only', 'submission', 'source export', 'roster']);

test('portable numeric exercise id must bind to the original exported work rather than another matching context', function (): void {
    $data = portableIdentityFixture($this);
    ['work' => $work, 'package' => $package, 'source' => $source] = $data;
    $source['work']['id'] = 800;
    $package['exercise_id'] = '800';
    $url = '/api/admin/teaching/course_works/'.$work->id.'/import-json';
    $this->postJson($url, ['package' => TeachingWorkJsonFixture::upload($package), 'identity_export' => portableIdentityUpload($source)])
        ->assertOk()->assertJsonPath('preview.can_import', true);
    $source['work']['id'] = 801;
    $this->postJson($url, ['package' => TeachingWorkJsonFixture::upload($package), 'identity_export' => portableIdentityUpload($source)])
        ->assertUnprocessable()->assertJsonValidationErrors('package');
});

test('portable unchanged ids retain current dispatch address verification after a contact update', function (): void {
    $data = portableIdentityFixture($this);
    ['work' => $work, 'actor' => $actor, 'package' => $package] = $data;
    $source = app(TeachingWorkAiExport::class)->build($work->fresh(), $actor)['context'];
    foreach ($package['records'] as $index => &$record) {
        $record['identity']['schooltool_person_id'] = (string) $data['people'][$index]->id;
    }
    unset($record);
    $source['course_roster']['participants'][0]['email'] = 'old@example.test';
    $importer = app(TeachingWorkJsonImport::class);
    $binding = $importer->identityBinding($work->fresh(), $actor, $importer->parse(json_encode(TeachingWorkJsonFixture::sign($package), JSON_THROW_ON_ERROR), [], json_encode($source, JSON_THROW_ON_ERROR)));
    $person = $data['people'][0];
    $recipient = array_replace(TeachingWorkDispatchFixture::recipients()[0], ['Vorname' => $person->first_name, 'Nachname' => $person->last_name,
        'Klasse' => '3B', 'Kurs' => 'Gruppe 1', 'To' => $person->email, 'Providerzeit' => '2026-10-07T12:15:39+02:00',
        'Betreff' => 'Ergebnisse zur Leistungsfeststellung: Leistungsarbeit']);
    $dispatches = app(TeachingWorkDispatchImport::class);
    $report = $dispatches->parse(TeachingWorkDispatchFixture::text([$recipient]));
    $preview = $dispatches->preview($work->fresh(), $report, str_repeat('a', 64), requireMatchingTitle: false, identityBinding: $binding);
    expect($preview['can_import'])->toBeTrue()->and($preview['rows'][0]['accepted'])->toBeTrue();
    $report['recipients'][0]['To'] = 'old@example.test';
    $preview = $dispatches->preview($work->fresh(), $report, str_repeat('b', 64), requireMatchingTitle: false, identityBinding: $binding);
    expect($preview['can_import'])->toBeFalse()->and($preview['rows'][0]['accepted'])->toBeFalse();
    Http::assertNothingSent();
});
