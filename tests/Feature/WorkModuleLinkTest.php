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
use App\Services\ModuleHandshake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\Support\TeachingWorkDispatchFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['app.url' => 'http://localhost:8000', 'services.dropbox.client_id' => 'test-client', 'services.dropbox.client_secret' => 'test-secret',
        'services.dropbox.redirect_uri' => 'http://localhost:8000/admin/teaching/dropbox/callback', 'schooltool.preview.instance' => false]);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    $school = School::factory()->create();
    $year = Schoolyear::factory()->create(['school_id' => $school->id]);
    enableSchoolToolModuleForTests($school, 'teaching');
    grantSchoolToolLicenceForTests($school, 'Lehrertool');
    $this->user = User::factory()->create(['school_id' => $school->id, 'schoolyear_id' => $year->id])->assignRole('admin');
    $this->course = TeachingCourse::factory()->forSchool($school)->forSchoolyear($year)->forTeacher($this->user)->create();
    $this->work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $this->connection = DropboxConnection::factory()->create(['user_id' => $this->user->id]);
    $this->url = '/api/admin/teaching/course_works/'.$this->work->id.'/dropbox';
    $this->handshake = '{"schema_version":1,"module_id":"A263B873-4f27-4167-90f0-7d94ea0f67fa"}';
    $this->online = [['.tag' => 'file', 'id' => 'id:handshake', 'name' => 'handshake.json', 'rev' => 'revision1',
        'path_lower' => '/schule2020_2027/materialien/lerninhalte/it_grundlagen/emails - kopie/handshake.json',
        'path_display' => '/schule2020_2027/Materialien/Lerninhalte/IT_Grundlagen/emails - Kopie/handshake.json',
        'size' => strlen($this->handshake)]];
    $this->remoteContents = $this->handshake;
    $this->actingAs($this->user, 'sanctum');
});

function fakeModuleDropbox(object $context): void
{
    Http::fake(function (Request $request) use ($context) {
        if ($context->exerciseOffline ?? false) {
            return Http::response([], 503);
        }
        if (str_ends_with($request->url(), '/oauth2/token')) {
            return Http::response(['access_token' => 'module-test-access']);
        }
        if (str_ends_with($request->url(), '/files/search_v2')) {
            expect($request['options']['filename_only'])->toBeTrue();

            return Http::response(['matches' => array_map(fn (array $entry): array => ['metadata' => ['metadata' => $entry]], $context->online), 'has_more' => false]);
        }
        if (str_ends_with($request->url(), '/files/download')) {
            $argument = json_decode($request->header('Dropbox-API-Arg')[0], true);
            if (isset($context->exerciseDocuments[$argument['path']])) {
                return Http::response($context->exerciseDocuments[$argument['path']]);
            }

            return Http::response($context->remoteContents);
        }
        if (str_ends_with($request->url(), '/files/get_metadata')) {
            if ($request['path'] === '/schule2020_2027/materialien/lerninhalte') {
                return Http::response(['.tag' => 'folder', 'id' => 'id:materialsroot', 'name' => 'Lerninhalte',
                    'path_lower' => $request['path'], 'path_display' => '/schule2020_2027/Materialien/Lerninhalte']);
            }
            foreach ($context->online as $entry) {
                if ($entry['id'] === $request['path'] || $entry['path_lower'] === $request['path']) {
                    if (($context->changedExerciseRevision ?? false) && $entry['name'] === 'Anlage_und_Quellen.json') {
                        $entry['rev'] = 'changed-revision';
                    }

                    return Http::response($entry);
                }
                $parent = substr($entry['path_lower'], 0, strrpos($entry['path_lower'], '/'));
                if ($request['path'] === $parent) {
                    return Http::response(['.tag' => 'folder', 'id' => 'id:folder'.basename($parent), 'name' => basename($parent),
                        'path_lower' => $parent, 'path_display' => substr($entry['path_display'], 0, strrpos($entry['path_display'], '/')),
                        'sharing_info' => ['parent_shared_folder_id' => '186736344']]);
                }
            }
        }
        if (str_ends_with($request->url(), '/files/list_folder')) {
            $parentFolder = collect($context->online)->firstWhere('id', $request['path']);

            return Http::response(['entries' => array_values(array_filter($context->online,
                fn (array $entry): bool => ($parentFolder['.tag'] ?? null) === 'folder'
                    ? dirname($entry['path_lower']) === $parentFolder['path_lower']
                    : $request['path'] === 'id:folder'.basename(dirname($entry['path_lower'])))), 'has_more' => false]);
        }

        throw new RuntimeException('Unexpected module Dropbox request: '.$request->url());
    });
}

test('dropping a handshake resolves an online path without linking or importing', function (): void {
    fakeModuleDropbox($this);
    $before = $this->work->fresh()->getAttributes();

    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])
        ->assertOk()->assertJsonPath('candidate.folder_path', '/schule2020_2027/Materialien/Lerninhalte/IT_Grundlagen/emails - Kopie')
        ->assertJsonPath('module_id', 'A263B873-4f27-4167-90f0-7d94ea0f67fa')->assertJsonPath('can_link', true);

    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('module lookup reuses unchanged handshake revisions but still detects newly copied identities', function (): void {
    fakeModuleDropbox($this);
    $payload = ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true];
    $this->postJson($this->url.'/folder', $payload)->assertOk()->assertJsonPath('can_link', true);
    $downloads = Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/files/download'))->count();

    $this->postJson($this->url.'/folder', $payload)->assertOk()->assertJsonPath('can_link', true);
    expect(Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/files/download'))->count())->toBe($downloads + 1);

    $copy = $this->online[0];
    $copy['id'] = 'id:copy';
    $copy['rev'] = 'revision-copy';
    $copy['path_lower'] = str_replace('emails - kopie', 'copied-module', $copy['path_lower']);
    $copy['path_display'] = str_replace('emails - Kopie', 'copied-module', $copy['path_display']);
    $this->online[] = $copy;
    $this->postJson($this->url.'/folder', $payload)->assertOk()->assertJsonPath('conflict', true)->assertJsonCount(2, 'candidates');
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('a new handshake revision never reuses the previous module identity', function (): void {
    fakeModuleDropbox($this);
    $payload = ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true];
    $this->postJson($this->url.'/folder', $payload)->assertOk();
    $this->online[0]['rev'] = 'revision2';
    $this->remoteContents = str_replace('A263B873', 'B263B873', $this->handshake);
    $this->postJson($this->url.'/folder', $payload)->assertUnprocessable();
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

function addExerciseFixture(object $context, string $name = '2026-10-06_E-Mail_Fach_3B_Gruppe-2', array $override = []): void
{
    $module = dirname($context->online[0]['path_display']);
    $root = $module.'/Leistungsfeststellung';
    $path = $root.'/'.$name;
    $record = array_replace_recursive(['Schooltool_Arbeit_ID' => $context->work->id, 'Schooltool_Kurs_ID' => $context->course->id,
        'Schooltool_Schule_ID' => $context->course->school_id, 'Durchfuehrungsdatum' => '2026-10-06', 'Leistungsfeststellungsordner' => $path,
        'Modulbindung' => ['status' => 'linked', 'module_id' => json_decode($context->handshake, true)['module_id'],
            'dropbox_folder_id' => 'id:folder'.basename(strtolower($module)), 'dropbox_path' => $module]], $override);
    $history = ['kind' => 'create-exercise.history', 'schema_version' => 1,
        'binding' => ['work_id' => $context->work->id, 'course_id' => $context->course->id, 'school_id' => $context->course->school_id, 'folder' => $path],
        'entries' => [['action' => 'exercise_folder_created', 'result' => 'created']]];
    foreach ([$root, $path, ...array_map(fn (string $child): string => $path.'/'.$child, ['Schülerlisten', 'Angaben', 'Abgaben', 'Auswertungen', 'Transaktionen'])] as $folder) {
        $id = 'id:folder'.hash('sha256', strtolower($folder));
        $context->online = array_values(array_filter($context->online, fn (array $entry): bool => $entry['id'] !== $id));
        $context->online[] = ['.tag' => 'folder', 'id' => $id, 'name' => basename($folder), 'path_lower' => strtolower($folder), 'path_display' => $folder];
    }
    foreach ([$path.'/Anlage_und_Quellen.json' => $record, $path.'/Transaktionen/History.json' => $history] as $file => $data) {
        $revision = hash('sha256', $file);
        $contents = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $context->exerciseDocuments['rev:'.$revision] = $contents;
        $context->online[] = ['.tag' => 'file', 'id' => 'id:'.$revision, 'name' => basename($file), 'path_lower' => strtolower($file),
            'path_display' => $file, 'rev' => $revision, 'size' => strlen($contents)];
    }
}

function linkExerciseFixture(object $context): void
{
    fakeModuleDropbox($context);
    $proof = test()->postJson($context->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $context->handshake, 'resolve' => true])->assertOk()->json('proof');
    test()->postJson($context->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $context->handshake, 'proof' => $proof])->assertOk();
    $context->work->update(['date_for_all_groups' => '2026-10-06']);
}

test('the actual externally created exercise folder is read only and confirmed for this work and module', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $before = $this->work->fresh()->getAttributes();
    $this->getJson($this->url.'?include_exercise=1&date=2026-10-06')->assertOk()
        ->assertJsonPath('exercise.status', 'found')->assertJsonPath('exercise.folder.name', '2026-10-06_E-Mail_Fach_3B_Gruppe-2');
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

function addEmailPreparationFile(object $context, string $path, string $contents): void
{
    $revision = hash('sha256', $path.$contents);
    $context->exerciseDocuments['rev:'.$revision] = $contents;
    $context->online = array_values(array_filter($context->online, fn (array $entry): bool => $entry['path_lower'] !== strtolower($path)));
    $context->online[] = ['.tag' => 'file', 'id' => 'id:'.$revision, 'name' => basename($path), 'path_lower' => strtolower($path),
        'path_display' => $path, 'rev' => $revision, 'size' => strlen($contents)];
}

function addEmailPreparationFixture(object $context): string
{
    $context->course->update(['title' => 'INF 1']);
    $student = User::factory()->create(['school_id' => $context->course->school_id, 'schoolyear_id' => $context->course->schoolyear_id,
        'first_name' => 'Ada', 'last_name' => 'Van Alpha', 'schoolclass' => '1A', 'email' => 'ada@example.test']);
    $context->course->teachingCourseStudents()->create(['user_id' => $student->id]);
    $context->work->update(['groups' => [['student_ids' => [$student->id]]], 'finish_until_date' => null, 'finish_until_time' => null]);
    $metadataFile = collect($context->online)->firstWhere('name', 'Anlage_und_Quellen.json');
    $root = dirname($metadataFile['path_display']);
    $roster = json_encode([['id' => $student->id, 'email' => $student->email]], JSON_THROW_ON_ERROR);
    addEmailPreparationFile($context, $root.'/Schülerlisten/Teilnehmer.json', $roster);
    addEmailPreparationFile($context, $root.'/nachricht_entwurf.md', 'Persönlicher E-Mail-Entwurf');
    addEmailPreparationFile($context, $root.'/Angaben/Aufgaben.pdf', '%PDF-Aufgaben');
    $package = json_decode(TeachingWorkDispatchFixture::readyTasksText(), true, flags: JSON_THROW_ON_ERROR);
    $package['Leistungsfeststellung'] = $root;
    $package['Textquelle_SHA256'] = hash('sha256', 'Persönlicher E-Mail-Entwurf');
    $package['Empfaenger'][0]['VorbereiteteAnhaenge'][0]['SHA256'] = hash('sha256', '%PDF-Aufgaben');
    $package['Quellen'] = [['Datei' => 'Anlage_und_Quellen.json', 'SHA256' => hash('sha256', $context->exerciseDocuments['rev:'.$metadataFile['rev']])],
        ['Datei' => 'Schülerlisten/Teilnehmer.json', 'SHA256' => hash('sha256', $roster)]];
    addEmailPreparationFile($context, $root.'/Schooltool-Versandvorbereitung.json', json_encode($package, JSON_THROW_ON_ERROR));

    return $root;
}

test('exercise checks keep creation confirmed when an independent dispatch journal is invalid', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $root = addEmailPreparationFixture($this);
    foreach (['Versand', 'Versand/Aufgaben', 'Versand/Aufgaben/Versand_2026-10-24_00-00-00'] as $relative) {
        $path = $root.'/'.$relative;
        $this->online[] = ['.tag' => 'folder', 'id' => 'id:'.hash('sha256', $path), 'name' => basename($path),
            'path_lower' => strtolower($path), 'path_display' => $path];
    }
    addEmailPreparationFile($this, $root.'/Versand/Aufgaben/Versand_2026-10-24_00-00-00/Versandprotokoll.txt', 'Unvollständiger Versandbeleg');
    $before = $this->work->fresh()->getAttributes();
    $this->getJson($this->url.'?include_exercise=1')->assertOk()
        ->assertJsonPath('exercise.status', 'found')->assertJsonPath('exercise.email_preparation.status', 'ready')
        ->assertJsonPath('exercise.email_dispatch.status', 'unknown');
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('exercise checks include complete email preparation without sending or importing', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $root = addEmailPreparationFixture($this);
    $before = $this->work->fresh()->getAttributes();

    $response = $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'found');
    $this->assertSame('ready', $response->json('exercise.email_preparation.status'), $response->json('exercise.email_preparation.message') ?? '');
    $response->assertJsonPath('exercise.email_preparation.recipients_count', 1);
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);

    addEmailPreparationFile($this, $root.'/nachricht_entwurf.md', 'Geänderter E-Mail-Entwurf');
    $this->getJson($this->url.'?include_exercise=1&refresh=1')->assertOk()->assertJsonPath('exercise.status', 'found')
        ->assertJsonPath('exercise.email_preparation.status', 'unknown');
});

test('email preparation is missing until its separate readiness evidence exists', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'found')
        ->assertJsonPath('exercise.email_preparation.status', 'missing');
});

test('email preparation matches the actual date group or all active members without saved groups', function (bool $datedGroup): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    addEmailPreparationFixture($this);
    if ($datedGroup) {
        $student = User::factory()->create(['school_id' => $this->course->school_id, 'schoolclass' => '1A']);
        $this->course->teachingCourseStudents()->create(['user_id' => $student->id]);
        $groups = $this->work->fresh()->groups;
        $groups[] = ['date' => '2026-10-07', 'student_ids' => [$student->id]];
        $this->work->update(['groups' => $groups]);
    } else {
        $this->work->update(['groups' => []]);
    }
    $this->getJson($this->url.'?include_exercise=1&date=2026-10-06')->assertOk()
        ->assertJsonPath('exercise.email_preparation.status', 'ready');
})->with([false, true]);

test('changed or incomplete email preparation never invalidates the created exercise or becomes ready', function (string $changed): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    $root = addEmailPreparationFixture($this);
    if ($changed === 'roster') {
        addEmailPreparationFile($this, $root.'/Schülerlisten/Teilnehmer.json', '[]');
    } elseif ($changed === 'attachment') {
        addEmailPreparationFile($this, $root.'/Angaben/Aufgaben.pdf', '%PDF-Geändert');
    } elseif ($changed === 'deadline') {
        $this->work->update(['finish_until_date' => '2026-10-08', 'finish_until_time' => '12:00']);
    } elseif ($changed === 'recipient') {
        $student = User::factory()->create(['school_id' => $this->course->school_id, 'schoolclass' => '1A']);
        $this->course->teachingCourseStudents()->create(['user_id' => $student->id]);
        $this->work->update(['groups' => [['student_ids' => [$student->id]]]]);
    } else {
        addEmailPreparationFile($this, $root.'/Schooltool-Versandvorbereitung.json', '{invalid');
    }
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'found')
        ->assertJsonPath('exercise.email_preparation.status', 'unknown');
})->with(['roster', 'attachment', 'deadline', 'recipient', 'malformed']);

function addContentsRootFixture(object $context): void
{
    $link = TeachingWorkModuleLink::query()->where('teaching_course_work_id', $context->work->id)->firstOrFail();
    $context->online[] = ['.tag' => 'folder', 'id' => $link->folder_id, 'name' => $link->folder_name,
        'path_lower' => strtolower($link->folder_path), 'path_display' => $link->folder_path];
}

test('linked folder contents list files and folders without importing or editing', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this);
    addExerciseFixture($this, '2026-10-06_Andere-Pruefung', ['Schooltool_Arbeit_ID' => $this->work->id + 1]);
    $before = $this->work->fresh()->getAttributes();

    $this->getJson($this->url.'?include_exercise=1')->assertOk();
    $this->getJson($this->url.'/folders?contents=1')->assertOk()
        ->assertJsonPath('root.name', '2026-10-06_E-Mail_Fach_3B_Gruppe-2')
        ->assertJsonFragment(['name' => 'Anlage_und_Quellen.json', 'type' => 'file'])
        ->assertJsonFragment(['name' => 'Angaben', 'type' => 'folder'])
        ->assertJsonMissing(['name' => '2026-10-06_Andere-Pruefung'])
        ->assertJsonPath('cursor', null);

    expect($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('linked folder contents hide other exercises before this exercise exists and reject direct navigation', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this, override: ['Schooltool_Arbeit_ID' => $this->work->id + 1]);
    $this->getJson($this->url.'?include_exercise=1')->assertOk();
    $this->getJson($this->url.'/folders?contents=1')->assertOk()
        ->assertJsonPath('root.name', 'emails - kopie')
        ->assertJsonFragment(['name' => 'handshake.json', 'type' => 'file'])
        ->assertJsonMissing(['name' => 'Leistungsfeststellung']);
    $root = collect($this->online)->firstWhere('name', 'Leistungsfeststellung');
    $other = collect($this->online)->firstWhere('name', '2026-10-06_E-Mail_Fach_3B_Gruppe-2');
    foreach ([$root, $other] as $folder) {
        $this->getJson($this->url.'/folders?contents=1&folder_id='.$folder['id'])->assertForbidden();
    }
});

test('linked module icon opens the learning module even after its exercise exists', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this);
    $before = $this->work->fresh()->getAttributes();
    $this->getJson($this->url.'?include_exercise=1')->assertOk();
    $this->getJson($this->url.'/folders?contents=1&module=1')->assertOk()
        ->assertJsonPath('root.name', 'emails - kopie')->assertJsonFragment(['name' => 'handshake.json', 'type' => 'file'])
        ->assertJsonMissing(['name' => 'Leistungsfeststellung']);
    $this->getJson($this->url.'/folders?contents=1&module=1&exercise=1')->assertUnprocessable();
    expect($this->work->fresh()->getAttributes())->toEqual($before);
});

test('linked folder contents select the requested group date and stop on an uncertain exercise', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    $this->work->update(['groups' => [['date' => '2026-10-07']]]);
    addExerciseFixture($this);
    addExerciseFixture($this, '2026-10-07_Andere-Gruppe', ['Durchfuehrungsdatum' => '2026-10-07']);
    $this->getJson($this->url.'?include_exercise=1')->assertOk();
    $this->getJson($this->url.'/folders?contents=1&date=2026-10-07')->assertOk()
        ->assertJsonPath('root.name', '2026-10-07_Andere-Gruppe');
    $this->getJson($this->url.'/folders?contents=1')->assertStatus(409);
});

test('linked folder contents open only the requested important section and reject sibling navigation', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this);
    $this->getJson($this->url.'?include_exercise=1')->assertOk();
    $this->getJson($this->url.'/folders?contents=1&exercise=1&section=Angaben&date=2026-10-06')->assertOk()
        ->assertJsonPath('root.name', 'Angaben')->assertJsonPath('entries', []);
    $sibling = collect($this->online)->firstWhere('name', 'Abgaben');
    $this->getJson($this->url.'/folders?contents=1&exercise=1&section=Angaben&folder_id='.$sibling['id'])->assertForbidden();
    $this->getJson($this->url.'/folders?contents=1&exercise=1&section=Bewertungskriterien')->assertNotFound();
    $this->getJson($this->url.'/folders?contents=1&section=Angaben')->assertUnprocessable();
    $this->getJson($this->url.'/folders?contents=1&exercise=1&section=Transaktionen')->assertUnprocessable();
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('linked folder contents return only a PDF from its verified section as private inline content', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this);
    $section = collect($this->online)->firstWhere('name', 'Angaben');
    $pdf = "%PDF-1.7\n%%EOF\n";
    $this->exerciseDocuments['rev:pdfrevision1'] = $pdf;
    $this->online[] = ['.tag' => 'file', 'id' => 'id:taskpdf', 'name' => 'Aufgabe.pdf', 'rev' => 'pdfrevision1',
        'path_lower' => $section['path_lower'].'/aufgabe.pdf', 'path_display' => $section['path_display'].'/Aufgabe.pdf', 'size' => strlen($pdf)];
    $url = $this->url.'/folders?contents=1&exercise=1&section=Angaben&pdf=1&file_id=id:taskpdf';

    $this->getJson($this->url.'?include_exercise=1')->assertOk();
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Content-Disposition', 'inline')
        ->assertContent($pdf);
    $this->getJson(str_replace('id:taskpdf', 'id:handshake', $url))->assertNotFound();
    $this->getJson(str_replace('exercise=1', 'exercise=0', $url))->assertUnprocessable();
    $otherTeacher = User::factory()->create(['school_id' => $this->user->school_id, 'schoolyear_id' => $this->user->schoolyear_id])->assignRole('teacher');
    $this->actingAs($otherTeacher, 'sanctum')->getJson($url)->assertForbidden();
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('linked folder contents open the verified exercise and refuse unrelated child folders', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this);
    $this->getJson($this->url.'?include_exercise=1')->assertOk();
    $this->getJson($this->url.'/folders?contents=1&exercise=1&date=2026-10-06')->assertOk()
        ->assertJsonPath('root.name', '2026-10-06_E-Mail_Fach_3B_Gruppe-2')
        ->assertJsonFragment(['name' => 'Anlage_und_Quellen.json', 'type' => 'file']);

    $this->online[] = ['.tag' => 'folder', 'id' => 'id:outside', 'name' => 'Private',
        'path_lower' => '/private', 'path_display' => '/Private'];
    $this->getJson($this->url.'/folders?contents=1&folder_id=id:outside')->assertForbidden();
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/list_folder') && $request['path'] === 'id:outside');
});

test('linked folder contents require a saved connection and support a legacy work folder', function (): void {
    fakeModuleDropbox($this);
    $this->getJson($this->url.'/folders?contents=1')->assertStatus(409);
    $this->online[] = ['.tag' => 'folder', 'id' => 'id:legacy', 'name' => 'Altordner',
        'path_lower' => '/altordner', 'path_display' => '/Altordner'];
    TeachingWorkDropboxFolder::query()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $this->work->id, 'folder_id' => 'id:legacy', 'folder_name' => 'Altordner']);

    $this->getJson($this->url.'/folders?contents=1')->assertOk()->assertJsonPath('root.id', 'id:legacy');
});

test('legacy folder icons distinguish the learning module from the concrete group exercise without changing results', function (): void {
    fakeModuleDropbox($this);
    $this->online = [
        ['.tag' => 'folder', 'id' => 'id:module', 'name' => 'E-Mails', 'path_lower' => '/materialien/emails', 'path_display' => '/Materialien/E-Mails'],
        ['.tag' => 'folder', 'id' => 'id:collection', 'name' => 'Leistungsfeststellung', 'path_lower' => '/materialien/emails/leistungsfeststellung',
            'path_display' => '/Materialien/E-Mails/Leistungsfeststellung'],
        ['.tag' => 'folder', 'id' => 'id:legacy', 'name' => '2026-10-04_E-Mail_DGB-3B-1_Tag',
            'path_lower' => '/materialien/emails/leistungsfeststellung/2026-10-04_e-mail_dgb-3b-1_tag',
            'path_display' => '/Materialien/E-Mails/Leistungsfeststellung/2026-10-04_E-Mail_DGB-3B-1_Tag'],
        ['.tag' => 'folder', 'id' => 'id:submissions', 'name' => 'Abgaben',
            'path_lower' => '/materialien/emails/leistungsfeststellung/2026-10-04_e-mail_dgb-3b-1_tag/abgaben',
            'path_display' => '/Materialien/E-Mails/Leistungsfeststellung/2026-10-04_E-Mail_DGB-3B-1_Tag/Abgaben'],
    ];
    TeachingWorkDropboxFolder::query()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $this->work->id, 'folder_id' => 'id:legacy', 'folder_name' => $this->online[2]['name']]);
    $before = $this->work->fresh()->getAttributes();
    $this->getJson($this->url.'?include_folder_path=1')->assertOk()->assertJsonPath('folder.id', 'id:legacy')
        ->assertJsonPath('legacy_folders.module.id', 'id:module')->assertJsonPath('legacy_folders.exercise.id', 'id:legacy');
    $this->getJson($this->url.'/folders?contents=1&module=1')->assertOk()->assertJsonPath('root.id', 'id:module')
        ->assertJsonMissing(['name' => 'Leistungsfeststellung']);
    $this->getJson($this->url.'/folders?contents=1&exercise=1')->assertOk()->assertJsonPath('root.id', 'id:legacy')
        ->assertJsonFragment(['id' => 'id:submissions', 'name' => 'Abgaben']);
    $this->getJson($this->url.'/folders?contents=1&exercise=1&folder_id=id:module')->assertForbidden();
    $this->getJson($this->url.'/folders?contents=1&module=1&folder_id=id:legacy')->assertForbidden();
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    Http::assertNotSent(fn (Request $request): bool => preg_match('#/files/(upload|delete|move|create)#', $request->url()) === 1);
});

test('linked folder contents never initiate a full LF check themselves', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this);
    $requests = Http::recorded()->count();

    $this->getJson($this->url.'/folders?contents=1&exercise=1&section=Angaben')->assertConflict();
    expect(Http::recorded()->count())->toBe($requests);
});

test('linked folder contents reuse one stored LF check across dates and days', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    $this->work->update(['groups' => [['date' => '2026-10-07']]]);
    addExerciseFixture($this);
    addExerciseFixture($this, '2026-10-07_Andere-Gruppe', ['Durchfuehrungsdatum' => '2026-10-07']);
    $checkedAt = $this->getJson($this->url.'?include_exercise=1&date=2026-10-06')->assertOk()
        ->assertJsonPath('exercise.status', 'found')->json('exercise.checked_at');
    $requests = Http::recorded()->count();

    $this->travel(3)->days();
    $this->getJson($this->url.'?include_exercise=1&date=2026-10-07')->assertOk()
        ->assertJsonPath('exercise.folder.name', '2026-10-07_Andere-Gruppe')->assertJsonPath('exercise.checked_at', $checkedAt);
    expect(Http::recorded()->count())->toBe($requests);
    $downloads = Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/files/download'))->count();
    $this->getJson($this->url.'/folders?contents=1&exercise=1&section=Angaben&date=2026-10-06')->assertOk();
    expect(Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/files/download'))->count())->toBe($downloads);
});

test('linked folder contents refresh the LF check only on explicit request and retain failures', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this);
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'found');
    $requests = Http::recorded()->count();
    $this->exerciseOffline = true;

    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'found');
    expect(Http::recorded()->count())->toBe($requests);
    $this->getJson($this->url.'?include_exercise=1&refresh=1')->assertOk()->assertJsonPath('exercise.status', 'unknown');
    $afterRefresh = Http::recorded()->count();
    expect($afterRefresh)->toBeGreaterThan($requests);
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'unknown');
    expect(Http::recorded()->count())->toBe($afterRefresh);
});

test('linked folder contents require manual confirmation after changing the schedule', function (): void {
    linkExerciseFixture($this);
    addContentsRootFixture($this);
    addExerciseFixture($this);
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'found');
    $requests = Http::recorded()->count();
    $this->work->update(['date_for_all_groups' => '2026-10-08']);

    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'unknown');
    expect(Http::recorded()->count())->toBe($requests);
    $this->getJson($this->url.'?include_exercise=1&refresh=1')->assertOk()->assertJsonPath('exercise.status', 'missing');
});

test('an unrelated exercise never hides creation for this work', function (array $override): void {
    linkExerciseFixture($this);
    addExerciseFixture($this, override: $override);
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'missing')->assertJsonPath('exercise.folder', null);
})->with([
    'different work' => [['Schooltool_Arbeit_ID' => 99999]],
    'different course' => [['Schooltool_Kurs_ID' => 99999]],
    'different school' => [['Schooltool_Schule_ID' => 99999]],
    'different date' => [['Durchfuehrungsdatum' => '2026-10-04']],
    'different module' => [['Modulbindung' => ['module_id' => '50f45671-a0a0-4bb4-b0b0-123456789abc']]],
    'different root' => [['Modulbindung' => ['dropbox_folder_id' => 'id:originalemails']]],
]);

test('copying an export or an empty LF root is not proof of exercise creation', function (): void {
    linkExerciseFixture($this);
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'missing');
});

test('ambiguous or incomplete matching exercise packages stay unconfirmed', function (bool $duplicate): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    if ($duplicate) {
        addExerciseFixture($this, '2026-10-06_E-Mail_Duplicate');
    } else {
        $this->online = array_values(array_filter($this->online, fn (array $entry): bool => $entry['name'] !== 'History.json'));
    }
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'unknown')->assertJsonPath('exercise.folder', null);
})->with([true, false]);

test('exercise lookup without an owned module or without course access makes no Dropbox calls', function (): void {
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'missing');
    $teacher = User::factory()->create(['school_id' => $this->user->school_id, 'schoolyear_id' => $this->user->schoolyear_id])->assignRole('teacher');
    $this->actingAs($teacher, 'sanctum')->getJson($this->url.'?include_exercise=1')->assertForbidden();
    Http::assertNothingSent();
});

test('failed Dropbox reads or changed LF evidence never confirm creation', function (bool $offline): void {
    linkExerciseFixture($this);
    addExerciseFixture($this);
    if ($offline) {
        $this->exerciseOffline = true;
    } else {
        $this->changedExerciseRevision = true;
    }
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'unknown')->assertJsonPath('exercise.folder', null);
})->with([true, false]);

test('copied metadata for a different folder is not accepted and unlinking removes the displayed LF state', function (): void {
    linkExerciseFixture($this);
    addExerciseFixture($this, override: ['Leistungsfeststellungsordner' => '/old/another-folder']);
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'unknown');
    $version = $this->getJson($this->url)->assertOk()->json('link_version');
    $this->postJson($this->url.'/folder', ['unlink' => true, 'link_version' => $version])->assertOk()->assertJsonPath('module_link', null);
    $this->getJson($this->url.'?include_exercise=1')->assertOk()->assertJsonPath('exercise.status', 'missing')->assertJsonPath('exercise.folder', null);
});

test('explicit linking keeps legacy imports and allows the same module for distinct works', function (): void {
    fakeModuleDropbox($this);
    $legacy = TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $this->connection->id, 'teaching_course_work_id' => $this->work->id]);
    $before = $this->work->fresh()->getAttributes();
    $legacyBefore = $legacy->fresh()->getAttributes();
    $resolved = $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()->json();

    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $resolved['proof']])
        ->assertOk()->assertJsonPath('module_link.folder.path', '/schule2020_2027/Materialien/Lerninhalte/IT_Grundlagen/emails - Kopie');

    $link = TeachingWorkModuleLink::query()->firstOrFail();
    expect($link->module_id)->toBe('A263B873-4f27-4167-90f0-7d94ea0f67fa')
        ->and($link->account_id)->toBe($this->connection->account_id)->and($link->namespace_id)->toBe('186736344');
    expect($legacy->fresh()->getAttributes())->toEqual($legacyBefore);
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    $second = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $secondUrl = '/api/admin/teaching/course_works/'.$second->id.'/dropbox/folder';
    $proof = $this->postJson($secondUrl, ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()->json('proof');
    $this->postJson($secondUrl, ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])->assertOk();
    expect(TeachingWorkModuleLink::query()->count())->toBe(2);
});

test('invalid or forged local handshakes cannot initiate Dropbox reads', function (): void {
    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => '{"schema_version":1}', 'resolve' => true])->assertUnprocessable();
    Http::assertNothingSent();
});

test('another filename cannot initiate online resolution even with valid handshake contents', function (string $name): void {
    $this->postJson($this->url.'/folder', ['filename' => $name, 'handshake' => $this->handshake, 'resolve' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('filename');
    Http::assertNothingSent();
})->with(['andere.json', 'handshake (1).json', 'handshake.json.txt', 'folder/handshake.json']);

test('Windows-compatible filename casing remains accepted', function (): void {
    fakeModuleDropbox($this);
    $this->postJson($this->url.'/folder', ['filename' => 'Handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk();
});

test('copied online module identities remain a visible conflict', function (): void {
    $this->online[] = array_replace($this->online[0], ['id' => 'id:copy', 'path_lower' => '/schule2020_2027/materialien/lerninhalte/it_grundlagen/zweites-modul/handshake.json',
        'path_display' => '/schule2020_2027/Materialien/Lerninhalte/IT_Grundlagen/Zweites-Modul/handshake.json']);
    fakeModuleDropbox($this);

    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])
        ->assertOk()->assertJsonPath('conflict', true)->assertJsonCount(2, 'candidates')->assertJsonMissingPath('proof');
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('a changed online revision invalidates the pending link', function (): void {
    fakeModuleDropbox($this);
    $proof = $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()->json('proof');
    $this->online[0]['rev'] = 'revision2';

    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])->assertConflict();
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('a concurrent assignment cannot be overwritten by an older confirmation', function (): void {
    fakeModuleDropbox($this);
    $proof = $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()->json('proof');
    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])->assertOk();
    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])->assertConflict();
    expect(TeachingWorkModuleLink::query()->count())->toBe(1);
});

test('a confirmation cannot be reused for a different work or Dropbox account', function (): void {
    fakeModuleDropbox($this);
    $proof = $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()->json('proof');
    $other = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $this->postJson('/api/admin/teaching/course_works/'.$other->id.'/dropbox/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])->assertConflict();
    $this->connection->update(['account_id' => 'dbid:changed']);
    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])->assertConflict();
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('an unrelated teacher cannot resolve another teachers work', function (): void {
    $teacher = User::factory()->create(['school_id' => $this->user->school_id, 'schoolyear_id' => $this->user->schoolyear_id])->assignRole('teacher');
    $this->actingAs($teacher, 'sanctum')->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertForbidden();
    Http::assertNothingSent();
});

test('a missing search match is described as unconfirmed rather than nonexistent', function (): void {
    $this->online = [];
    fakeModuleDropbox($this);
    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('dropbox');
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('technical work copies outside the verified material roots do not compete with the real learning module', function (): void {
    foreach (['/AI/tmp/emails-pdf-20261020-01a15b48/before', '/AI/tmp/module-handshake-skill-20261020-01a15b12/live/handshake/handshake-I_6yschn',
        '/AI/tmp/exercise64-20261021/snapshot/schule2020_2027/Materialien/Lerninhalte/IT_Grundlagen/emails - Kopie'] as $index => $path) {
        $this->online[] = array_replace($this->online[0], ['id' => 'id:technical'.$index, 'rev' => 'technical'.$index,
            'path_lower' => strtolower($path).'/handshake.json', 'path_display' => $path.'/handshake.json']);
    }
    $this->online[] = ['.tag' => 'folder', 'id' => 'id:folder'.basename(dirname($this->online[0]['path_lower'])),
        'name' => 'emails - Kopie', 'path_lower' => dirname($this->online[0]['path_lower'])];
    fakeModuleDropbox($this);

    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()
        ->assertJsonPath('candidate.folder_path', '/schule2020_2027/Materialien/Lerninhalte/IT_Grundlagen/emails - Kopie')
        ->assertJsonMissingPath('conflict');
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/files/download')
        && str_contains($request->header('Dropbox-API-Arg')[0] ?? '', 'technical'));
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('a module moved into a technical snapshot cannot be confirmed with an older proof', function (): void {
    fakeModuleDropbox($this);
    $proof = $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()->json('proof');
    $this->online[0]['path_display'] = '/AI/tmp/exercise64-20261021/snapshot/schule2020_2027/Materialien/Lerninhalte/IT_Grundlagen/emails - Kopie/handshake.json';
    $this->online[0]['path_lower'] = strtolower($this->online[0]['path_display']);

    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])
        ->assertConflict()->assertJsonPath('message', 'Die Online-Datei liegt außerhalb des Lerninhalte-Bestands. Bitte erneut auswählen.');
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('an unapplied migration still permits read-only resolution but prevents saving', function (): void {
    $modules = Mockery::mock(DropboxModuleLinks::class, [app(DropboxWorkImport::class), app(ModuleHandshake::class)])->makePartial();
    $modules->shouldReceive('installed')->andReturn(false);
    app()->instance(DropboxModuleLinks::class, $modules);
    fakeModuleDropbox($this);
    $resolved = $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()
        ->assertJsonPath('can_link', false)->json();
    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $resolved['proof']])->assertConflict();
    expect(TeachingWorkModuleLink::query()->count())->toBe(0);
});

test('unlinking removes only this works module and legacy associations without reading or changing Dropbox or assessment data', function (): void {
    fakeModuleDropbox($this);
    $second = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    foreach ([$this->work, $second] as $work) {
        $url = '/api/admin/teaching/course_works/'.$work->id.'/dropbox/folder';
        $proof = $this->postJson($url, ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()->json('proof');
        $this->postJson($url, ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])->assertOk();
    }
    $legacy = TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $this->work->id]);
    $secondLegacy = TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $second->id]);
    $this->work->update(['status' => ['folder_imported_at' => '2026-10-20T12:00:00Z', 'custom' => 'keep'],
        'groups' => [['student_ids' => [12], 'points' => 4.5]]]);
    $before = $this->work->fresh()->getAttributes();
    $version = $this->getJson($this->url)->assertOk()->json('link_version');
    $requestsBefore = Http::recorded()->count();

    $this->postJson($this->url.'/folder', ['unlink' => true, 'link_version' => $version])->assertOk()
        ->assertJsonPath('module_link', null)->assertJsonPath('folder', null)
        ->assertJsonPath('connected', true)->assertJsonPath('can_quick_import', false);

    expect(TeachingWorkModuleLink::query()->where('teaching_course_work_id', $this->work->id)->exists())->toBeFalse();
    expect(TeachingWorkModuleLink::query()->where('teaching_course_work_id', $second->id)->exists())->toBeTrue();
    expect($legacy->fresh())->toBeNull()->and($secondLegacy->fresh())->not->toBeNull();
    expect($this->connection->fresh())->not->toBeNull();
    expect($this->work->fresh()->getAttributes())->toEqual($before);
    expect(Http::recorded()->count())->toBe($requestsBefore);
    $this->getJson($this->url)->assertOk()->assertJsonPath('module_link', null)->assertJsonPath('folder', null);
});

test('an old unlink confirmation cannot delete a changed or different works assignment', function (): void {
    $legacy = TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $this->work->id, 'folder_id' => 'id:before']);
    $version = $this->getJson($this->url)->assertOk()->json('link_version');
    $legacy->update(['folder_id' => 'id:changed']);

    $this->postJson($this->url.'/folder', ['unlink' => true, 'link_version' => $version])->assertConflict();
    expect($legacy->fresh()->folder_id)->toBe('id:changed');
    $other = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id]);
    $otherLegacy = TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $other->id]);
    $this->postJson('/api/admin/teaching/course_works/'.$other->id.'/dropbox/folder', ['unlink' => true, 'link_version' => $version])->assertConflict();
    expect($otherLegacy->fresh())->not->toBeNull();
    Http::assertNothingSent();
});

test('unlinking keeps foreign module links and other users connections intact', function (): void {
    fakeModuleDropbox($this);
    $proof = $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'resolve' => true])->assertOk()->json('proof');
    $this->postJson($this->url.'/folder', ['filename' => 'handshake.json', 'handshake' => $this->handshake, 'proof' => $proof])->assertOk();
    $foreign = DropboxConnection::factory()->create();
    $module = TeachingWorkModuleLink::query()->firstOrFail();
    $module->update(['dropbox_connection_id' => $foreign->id, 'account_id' => $foreign->account_id]);
    $foreignLegacy = TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $foreign->id,
        'teaching_course_work_id' => $this->work->id]);
    $version = $this->getJson($this->url)->assertOk()->assertJsonPath('module_conflict', true)->json('link_version');

    $this->postJson($this->url.'/folder', ['unlink' => true, 'link_version' => $version])->assertConflict();
    expect($module->fresh()->dropbox_connection_id)->toBe($foreign->id);
    expect($foreignLegacy->fresh())->not->toBeNull();
    expect($foreign->fresh())->not->toBeNull();
});

test('unlinking remains server-authorized and requires the displayed assignment version', function (): void {
    $legacy = TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $this->connection->id,
        'teaching_course_work_id' => $this->work->id]);
    $version = $this->getJson($this->url)->assertOk()->json('link_version');
    $this->postJson($this->url.'/folder', ['unlink' => true])->assertUnprocessable();
    $teacher = User::factory()->create(['school_id' => $this->user->school_id, 'schoolyear_id' => $this->user->schoolyear_id])->assignRole('teacher');
    $this->actingAs($teacher, 'sanctum')->postJson($this->url.'/folder', ['unlink' => true, 'link_version' => $version])->assertForbidden();
    expect($legacy->fresh())->not->toBeNull();
    Http::assertNothingSent();
});
