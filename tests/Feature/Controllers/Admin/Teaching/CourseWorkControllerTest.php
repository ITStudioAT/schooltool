<?php

use App\Models\Import116;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingSchema;
use App\Models\User;
use App\Services\TeachingCourseWorkEntrySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Support\TeachingWorkEvaluationFixture;

uses(RefreshDatabase::class);

function prepareWorkEvaluationImport(object $context): TeachingCourseWork
{
    $definition = enableCourseWorkMaximumPlus($context);
    $definition->update(['properties_mode' => 'points', 'maximum_points' => 5]);
    $context->student->update(['first_name' => 'Ada', 'last_name' => 'VAN Alpha', 'schoolclass' => '1A']);
    $context->course->teachingCourseStudents()->create(['user_id' => $context->student->id]);
    $context->openStudent = User::factory()->create(['school_id' => $context->school->id, 'first_name' => 'Bea', 'last_name' => 'Beta', 'schoolclass' => '1A']);
    $context->course->teachingCourseStudents()->create(['user_id' => $context->openStudent->id]);
    $work = TeachingCourseWork::create(['teaching_course_id' => $context->course->id, 'type' => 'MA', 'title' => 'E-Mails', 'date_for_all_groups' => '2026-10-02',
        'groups' => [['student_ids' => [$context->student->id], 'grade' => '2', 'points' => [['student_id' => $context->student->id, 'points' => 2]], 'comments' => [['student_id' => $context->student->id, 'comment' => 'Vorher']]],
            ['student_ids' => [$context->openStudent->id], 'grade' => '3', 'points' => [['student_id' => $context->openStudent->id, 'points' => 3]], 'comments' => [['student_id' => $context->openStudent->id, 'comment' => 'Offen vorher']]]]]);
    app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);
    Storage::fake('local');

    return $work;
}

function workEvaluationPdf(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n% {$name}\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n");
}

test('work evaluation folder preview and apply import points comments and private paired PDFs without touching open or foreign students', function () {
    $work = prepareWorkEvaluationImport($this);
    $pdfs = [workEvaluationPdf('Gesamtuebersicht_Beurteilungen_Test.pdf'), workEvaluationPdf('Beurteilung_Van Alpha_Ada.pdf')];
    $payload = ['reports' => TeachingWorkEvaluationFixture::payload(), 'pdfs' => $pdfs];
    $url = "/api/admin/teaching/course_works/{$work->id}/import-evaluations";
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    expect($work->fresh()->groups[0]['comments'][0]['comment'])->toBe('Vorher')
        ->and($preview['rows'][2]['status'])->toBe('Nicht im Kurs – übersprungen');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    $fresh = $work->fresh();
    expect($fresh->groups[0]['grades'][0]['grade'])->toBe('4.5')
        ->and($fresh->groups[0]['points'][0]['points'])->toBe(4.5)
        ->and($fresh->groups[0]['comments'][0]['comment'])->toBe('**Gesamt: 4,5 von 5,0 Punkten.** MC: 2,0 von 2,0; E-Mail: 2,5 von 3,0.')
        ->and($fresh->groups[1]['points'][0]['points'])->toBe(3)
        ->and($fresh->groups[1]['comments'][0]['comment'])->toBe('Offen vorher')
        ->and($fresh->status['evaluation_pdfs'])->toHaveCount(2);
    foreach ($fresh->status['evaluation_pdfs'] as $pdf) {
        Storage::disk('local')->assertExists($pdf['file_path']);
    }
    $sha = $fresh->status['evaluation_pdfs'][0]['sha256'];
    $this->getJson("/api/admin/teaching/course_works/{$work->id}/evaluations/{$sha}")->assertOk();
    $second = $this->postJson($url, $payload)->assertOk()->json('preview');
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $second['hash']])->assertOk();
    expect($work->fresh()->status['evaluation_pdfs'])->toHaveCount(2);
    $oldPdf = $work->fresh()->status['evaluation_pdfs'][1];
    $changed = array_map(fn (string $text): string => str_replace(['2,5', '4,5', 'Die Begründung bleibt vollständig.'], ['2,05', '4,05', 'Neue Detailbeurteilung.'], $text), TeachingWorkEvaluationFixture::reports());
    $newPdf = UploadedFile::fake()->createWithContent('Beurteilung_Van Alpha_Ada.pdf', "%PDF-1.4\n% Aktualisierte Auswertung\n%%EOF\n");
    $newPayload = ['reports' => TeachingWorkEvaluationFixture::payload($changed), 'pdfs' => [$pdfs[0], $newPdf]];
    $changedPreview = $this->postJson($url, $newPayload)->assertOk()->json('preview');
    $this->postJson($url, $newPayload + ['apply' => true, 'hash' => $changedPreview['hash']])->assertOk();
    expect($work->fresh()->groups[0]['grades'][0]['grade'])->toBe('4.05')
        ->and($work->fresh()->groups[0]['comments'][0]['comment'])->toBe('**Gesamt: 4,05 von 5,0 Punkten.** MC: 2,0 von 2,0; E-Mail: 2,05 von 3,0.')
        ->and($work->fresh()->status['evaluation_pdfs'])->toHaveCount(2)
        ->and($work->fresh()->status['evaluation_pdfs'][1]['sha256'])->not->toBe($oldPdf['sha256']);
    Storage::disk('local')->assertExists($oldPdf['file_path']);
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['status' => []])->assertOk();
    expect($work->fresh()->status['evaluation_pdfs'])->toHaveCount(2);
});

test('surname first evaluations preview and import paired overall and personal PDFs while retaining open values', function () {
    $work = prepareWorkEvaluationImport($this);
    $reports = TeachingWorkEvaluationFixture::surnameFirstReports();
    $pdfs = array_map(fn (string $name): UploadedFile => workEvaluationPdf(str_replace('.md', '.pdf', $name)), array_keys($reports));
    $payload = ['reports' => TeachingWorkEvaluationFixture::payload($reports), 'pdfs' => $pdfs];
    $url = "/api/admin/teaching/course_works/{$work->id}/import-evaluations";
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');

    expect($preview['pdf']['name'])->toBe('Gesamtübersicht.pdf')
        ->and($preview['rows'][0]['student_id'])->toBe($this->student->id)
        ->and($preview['rows'][0]['status'])->toBe('Übernehmen')
        ->and($preview['rows'][1]['status'])->toBe('Offen – unverändert')
        ->and($preview['rows'][2]['status'])->toBe('Nicht im Kurs – übersprungen')
        ->and($work->fresh()->groups[0]['points'][0]['points'])->toBe(2);

    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    $fresh = $work->fresh();
    expect($fresh->groups[0]['points'][0]['points'])->toBe(4.5)
        ->and($fresh->groups[0]['comments'][0]['comment'])->toBe('**Gesamt: 4,50 von 5,0 Punkten.** MC: 2,00 von 2,0; E-Mail: 2,50 von 3,0.')
        ->and($fresh->groups[1]['points'][0]['points'])->toBe(3)
        ->and($fresh->groups[1]['comments'][0]['comment'])->toBe('Offen vorher')
        ->and($fresh->status['evaluation_pdfs'])->toHaveCount(3)
        ->and(array_column($fresh->status['evaluation_pdfs'], 'student_id'))->toEqual([null, $this->student->id, $this->openStudent->id]);

    $attachments = $fresh->status['evaluation_pdfs'];
    $this->getJson("/api/admin/teaching/course_works?course_id={$this->course->id}")->assertOk()
        ->assertJsonPath('data.0.status.evaluation_pdfs.0.name', 'Gesamtübersicht.pdf')
        ->assertJsonCount(3, 'data.0.status.evaluation_pdfs');
    $this->getJson("/api/admin/teaching/course_works/{$work->id}")->assertOk()
        ->assertJsonPath('data.status.evaluation_pdfs.1.student_id', $this->student->id);
    $teacherUrl = "/api/admin/teaching/course_works/{$work->id}/evaluations/{$attachments[0]['sha256']}";
    expect($this->get($teacherUrl.'?inline=1')->assertOk()->headers->get('Content-Disposition'))->toStartWith('inline;');
    expect($this->get($teacherUrl)->assertOk()->headers->get('Content-Disposition'))->toStartWith('attachment;');

    SchoolTool::where('school_id', $this->school->id)->update(['active_schoolyear_id' => $this->schoolyear->id]);
    foreach ([$this->student, $this->openStudent] as $index => $student) {
        $student->assignRole('student');
        $this->actingAs($student, 'sanctum')->actingAs($student, 'web');
        $pdf = $attachments[$index + 1];
        $this->getJson("/api/homepage/student/courses/{$this->course->id}/entries")->assertOk()
            ->assertJsonPath('entries.0.work.evaluation_pdf.sha256', $pdf['sha256'])
            ->assertJsonMissing(['sha256' => $attachments[0]['sha256']])
            ->assertJsonMissing(['sha256' => $attachments[$index === 0 ? 2 : 1]['sha256']]);
        $studentUrl = "/api/homepage/student/courses/{$this->course->id}/works/{$work->id}/evaluations/";
        $response = $this->get($studentUrl.$pdf['sha256'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect($response->streamedContent())->toBe(Storage::disk('local')->get($pdf['file_path']));
        $this->get($studentUrl.$attachments[0]['sha256'])->assertNotFound();
        $this->get($studentUrl.$attachments[$index === 0 ? 2 : 1]['sha256'])->assertNotFound();
        $this->get($teacherUrl.'?inline=1')->assertForbidden();
    }
});

test('work evaluation uses the linked current student import identity and class without modifying accounts', function (bool $surnameFirst) {
    $work = prepareWorkEvaluationImport($this);
    $this->student->update(['first_name' => 'Registered name', 'schoolclass' => null]);
    $import = Import116::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->student->id, 'first_name' => 'Ada', 'last_name' => 'Van Alpha', 'class' => '1A',
        'import_user_id' => $this->admin->id,
    ]);
    $membership = $this->course->teachingCourseStudents()->where('user_id', $this->student->id)->firstOrFail();
    $membership->update(['import116_id' => $import->id]);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-evaluations";
    $payload = ['reports' => TeachingWorkEvaluationFixture::payload($surnameFirst ? TeachingWorkEvaluationFixture::surnameFirstReports() : null)];
    $count = User::count();
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    expect($preview['rows'][0]['status'])->toBe('Übernehmen')
        ->and($preview['rows'][0]['student_id'])->toBe($this->student->id)
        ->and($this->student->fresh()->schoolclass)->toBeNull()
        ->and(User::count())->toBe($count);
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertOk();
    expect($work->fresh()->groups[0]['points'][0]['points'])->toBe(4.5);

    $import->update(['schoolyear_id' => $this->otherSchoolyear->id]);
    expect($this->postJson($url, $payload)->assertOk()->json('preview.rows.0.status'))->toBe('Nicht im Kurs – übersprungen');
    $import->update(['schoolyear_id' => $this->schoolyear->id, 'school_id' => $this->otherSchool->id]);
    expect($this->postJson($url, $payload)->assertOk()->json('preview.rows.0.status'))->toBe('Nicht im Kurs – übersprungen');
    $import->update(['school_id' => $this->school->id, 'user_id' => $this->openStudent->id]);
    expect($this->postJson($url, $payload)->assertOk()->json('preview.rows.0.status'))->toBe('Nicht im Kurs – übersprungen');
})->with(['legacy' => false, 'surname first' => true]);

test('work evaluation imports enforce school ownership maximum points and unchanged preview', function () {
    $work = prepareWorkEvaluationImport($this);
    $payload = ['reports' => TeachingWorkEvaluationFixture::payload()];
    $url = "/api/admin/teaching/course_works/{$work->id}/import-evaluations";
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    $work->update(['title' => 'Geändert']);
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertConflict();
    $this->course->teachingEntryArea->entryDefinitions()->update(['maximum_points' => 10]);
    $this->postJson($url, $payload)->assertUnprocessable();
    $this->actingAs(User::factory()->create(['school_id' => $this->otherSchool->id, 'schoolyear_id' => $this->otherSchoolyear->id])->assignRole('teacher'), 'sanctum');
    $this->postJson($url, $payload)->assertForbidden();
});

test('work evaluation imports reject ambiguous PDF pairs and roll back a failed PDF write', function () {
    $work = prepareWorkEvaluationImport($this);
    $url = "/api/admin/teaching/course_works/{$work->id}/import-evaluations";
    $name = 'Beurteilung_Van Alpha_Ada.pdf';
    $this->postJson($url, ['reports' => TeachingWorkEvaluationFixture::payload(), 'pdfs' => [workEvaluationPdf($name), workEvaluationPdf($name)]])->assertUnprocessable();
    $payload = ['reports' => TeachingWorkEvaluationFixture::payload(), 'pdfs' => [workEvaluationPdf($name)]];
    $preview = $this->postJson($url, $payload)->assertOk()->json('preview');
    $disk = Storage::disk('local');
    Storage::shouldReceive('disk')->with('local')->andReturn($mock = Mockery::mock($disk)->makePartial());
    $mock->shouldReceive('putFileAs')->once()->andReturn(false);
    $this->postJson($url, $payload + ['apply' => true, 'hash' => $preview['hash']])->assertUnprocessable();
    expect($work->fresh()->groups[0]['comments'][0]['comment'])->toBe('Vorher')
        ->and($work->fresh()->status['evaluation_pdfs'] ?? [])->toBeEmpty()
        ->and($disk->allFiles())->toBeEmpty();
});

function enableCourseWorkMaximumPlus(object $context): TeachingEntryDefinition
{
    $context->schoolyear->update(['name' => '2026/27', 'concerns' => '2026/27']);
    $area = TeachingEntryArea::factory()->create([
        'school_id' => $context->school->id, 'schoolyear_id' => $context->schoolyear->id, 'user_id' => $context->admin->id,
    ]);
    $context->course->update(['teaching_entry_area_id' => $area->id]);
    $context->actingAs($context->admin, 'sanctum');

    return TeachingEntryDefinition::factory()->create([
        'school_id' => $context->school->id, 'schoolyear_id' => $context->schoolyear->id, 'user_id' => $context->admin->id,
        'teaching_entry_area_id' => $area->id, 'short_name' => 'MA', 'category' => 'Benotung',
        'has_properties' => true, 'properties_mode' => 'plus', 'allows_maximum_plus' => true,
    ]);
}

test('requires a strict positive maximum plus for eligible course works', function (mixed $maximum) {
    enableCourseWorkMaximumPlus($this);
    $this->postJson('/api/admin/teaching/course_works', [
        'teaching_course_id' => $this->course->id, 'type' => 'MA', 'maximum_plus' => $maximum,
    ])->assertUnprocessable()->assertJsonValidationErrors('maximum_plus');
})->with([null, false, true, 0, -1, 1.5, '3']);

test('persists maximum plus on works and validates groups without discarding existing grades', function () {
    enableCourseWorkMaximumPlus($this);
    $this->course->teachingCourseStudents()->create(['user_id' => $this->student->id]);
    $response = $this->postJson('/api/admin/teaching/course_works', [
        'teaching_course_id' => $this->course->id, 'type' => 'MA', 'maximum_plus' => 5,
        'groups' => [['student_ids' => [$this->student->id], 'grade' => '+++']],
    ])->assertCreated()->assertJsonPath('data.maximum_plus', 5);
    $work = TeachingCourseWork::findOrFail($response->json('data.id'));
    $this->getJson("/api/admin/teaching/course_works/{$work->id}")->assertOk()->assertJsonPath('data.maximum_plus', 5);
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['maximum_plus' => 2])
        ->assertUnprocessable();
    expect($work->fresh()->maximum_plus)->toBe(5);
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['title' => 'Renamed'])
        ->assertOk()->assertJsonPath('data.maximum_plus', 5);
    expect($work->fresh()->teachingCourseStudentEntries()->first()->grade)->toBe('+++');
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", [
        'groups' => [['student_ids' => [$this->student->id], 'grades' => [['student_id' => $this->student->id, 'grade' => '++++++']]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('groups.0.grades.0.grade');
});

test('allows missing legacy maximum plus to be read and repaired', function () {
    $definition = enableCourseWorkMaximumPlus($this);
    $work = TeachingCourseWork::create(['teaching_course_id' => $this->course->id, 'type' => 'MA', 'groups' => []]);
    $this->getJson("/api/admin/teaching/course_works/{$work->id}")->assertOk()->assertJsonPath('data.maximum_plus', null);
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['title' => 'Repair'])
        ->assertUnprocessable()->assertJsonValidationErrors('maximum_plus');
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['maximum_plus' => 4])
        ->assertOk()->assertJsonPath('data.maximum_plus', 4);
    $definition->update(['allows_maximum_plus' => false]);
    $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['maximum_plus' => 8])
        ->assertOk()->assertJsonPath('data.maximum_plus', null);
});

test('validates repeated sign grades for work groups and students on create and update', function (string $mode, ?string $grade, bool $valid) {
    $this->schoolyear->update(['name' => '2026/27', 'concerns' => '2026/27']);
    $area = TeachingEntryArea::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->admin->id,
    ]);
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id, 'user_id' => $this->admin->id,
        'teaching_entry_area_id' => $area->id, 'short_name' => 'MA', 'category' => 'Benotung',
        'has_properties' => true, 'properties_mode' => $mode,
        'maximum_points' => $mode === 'points' ? 10.5 : null,
    ]);
    $this->course->update(['teaching_entry_area_id' => $area->id]);
    $this->actingAs($this->admin, 'sanctum');
    $groups = [['student_ids' => [$this->student->id], 'grade' => $grade, 'grades' => [['student_id' => $this->student->id, 'grade' => $grade]]]];
    $response = $this->postJson('/api/admin/teaching/course_works', [
        'teaching_course_id' => $this->course->id, 'type' => 'MA', 'groups' => $groups,
    ]);
    if ($valid) {
        $response->assertCreated();
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors(['groups.0.grade', 'groups.0.grades.0.grade']);
    }
    $work = TeachingCourseWork::query()->create(['teaching_course_id' => $this->course->id, 'type' => 'MA', 'groups' => []]);
    if (! $valid) {
        $work->update(['type' => 'OLD', 'groups' => $groups]);
        $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['type' => 'MA'])
            ->assertUnprocessable()->assertJsonValidationErrors(['groups.0.grade', 'groups.0.grades.0.grade']);
        $work->update(['type' => 'MA', 'groups' => []]);
    }
    $response = $this->putJson("/api/admin/teaching/course_works/{$work->id}", ['groups' => $groups]);
    if ($valid) {
        $response->assertOk();
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors(['groups.0.grade', 'groups.0.grades.0.grade']);
        expect($work->fresh()->groups)->toBe([]);
    }
})->with([
    ['points', '0', true], ['points', '10.5', true], ['points', 'NA', true],
    ['points', '-1', false], ['points', '10.6', false], ['points', 'abc', false], ['points', '1e999', false],
    ['plus', '+++', true], ['plus', '--', false], ['plus_minus', '---', true],
    ['plus_minus', '+-', false], ['plus_minus', '', true], ['plus', null, true],
    ['plus', 'NA', true], ['plus_minus', 'F', true],
]);

beforeEach(function () {
    collect([
        'super_admin',
        'admin',
        'teaching_admin',
        'teacher',
        'student',
        'user',
    ])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $this->school = School::factory()->create();
    enableSchoolToolModuleForTests($this->school, 'teaching');
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    $teachingLicence = Licence::firstOrCreate(
        ['name' => 'Lehrertool'],
        ['long_name' => 'Lehrertool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($teachingLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'teaching_visible_admin' => true,
        'teaching_visible_user' => true,
    ]);

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->admin->assignRole('admin');

    $this->teachingAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teachingAdmin->assignRole('teaching_admin');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teacher->assignRole('teacher');

    $this->regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->regularUser->assignRole('user');

    $this->student = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->student->assignRole('student');

    $this->otherSchool = School::factory()->create();
    $this->otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->otherSchool->id,
    ]);

    $this->schemaId = 'schema-work';
    $schemaWorks = [
        [
            'short_name' => 'MA',
            'name' => 'Mitarbeit',
            'calculation' => 'average',
            'grades' => [
                ['grade' => '1', 'value' => '1'],
                ['grade' => '2', 'value' => '2'],
                ['grade' => '3', 'value' => '3'],
                ['grade' => 'NA', 'value' => ''],
            ],
            'default_grade' => null,
        ],
        [
            'short_name' => 'SA',
            'name' => 'Schularbeit',
            'calculation' => 'average',
            'grades' => [
                ['grade' => '1', 'value' => '1'],
                ['grade' => '2', 'value' => '2'],
                ['grade' => '3', 'value' => '3'],
                ['grade' => 'NA', 'value' => ''],
            ],
            'default_grade' => null,
        ],
    ];

    foreach ([$this->admin, $this->teachingAdmin, $this->teacher] as $schemaOwner) {
        TeachingSchema::query()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $schemaOwner->id,
            'schema_id' => $this->schemaId,
            'name' => 'Standard',
            'works' => $schemaWorks,
            'grading' => [],
        ]);
    }

    $this->course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->admin->id,
        'teaching_schema_id' => $this->schemaId,
        'classes' => ['1A'],
    ]);

    $this->otherCourse = TeachingCourse::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'teaching_schema_id' => $this->schemaId,
        'classes' => ['9Z'],
    ]);
});

describe('authorization', function () {
    test('index returns 401 when unauthenticated', function () {
        $this->getJson('/api/admin/teaching/course_works?course_id='.$this->course->id)->assertStatus(401);
    });

    test('returns 403 for users without allowed role', function () {
        $this->actingAs($this->regularUser, 'sanctum');

        $this->getJson('/api/admin/teaching/course_works?course_id='.$this->course->id)->assertStatus(403);
    });
});

describe('index', function () {
    test('returns empty array when no course_id is given', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/admin/teaching/course_works')
            ->assertOk()
            ->assertJson(['data' => []]);
    });

    test('returns works for course sorted by date descending', function () {
        $this->actingAs($this->admin, 'sanctum');

        $older = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Older',
            'date_for_all_groups' => '2026-02-01',
            'groups' => [],
        ]);

        $newer = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Newer',
            'date_for_all_groups' => '2026-03-01',
            'groups' => [],
        ]);

        $response = $this->getJson('/api/admin/teaching/course_works?course_id='.$this->course->id);

        $response->assertOk()->assertJsonCount(2, 'data');
        expect($response->json('data.0.id'))->toBe($newer->id)
            ->and($response->json('data.1.id'))->toBe($older->id);
    });

    test('returns 403 for course from other school', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/admin/teaching/course_works?course_id='.$this->otherCourse->id)
            ->assertStatus(403);
    });

    test('teacher cannot access another teachers course or another schoolyear', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $sameSchoolOtherYear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);
        $otherYearCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $sameSchoolOtherYear->id,
            'user_id' => $this->teacher->id,
            'teaching_schema_id' => $this->schemaId,
            'classes' => ['2B'],
        ]);

        $this->getJson('/api/admin/teaching/course_works?course_id='.$this->course->id)
            ->assertStatus(403);

        $this->getJson('/api/admin/teaching/course_works?course_id='.$otherYearCourse->id)
            ->assertStatus(403);
    });
});

describe('store', function () {
    test('creates work and syncs derived student entry', function () {
        $this->actingAs($this->admin, 'sanctum');

        $payload = [
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Mitarbeit Woche 1',
            'description' => 'Lernzielkontrolle',
            'is_group_work' => true,
            'date_for_all_groups' => '2026-03-10',
            'finish_until_date' => '2026-03-17',
            'groups' => [[
                'student_ids' => [$this->student->id],
                'grade' => '2',
                'comment' => 'Gute Leistung',
                'date' => '2026-03-10',
            ]],
        ];

        $response = $this->postJson('/api/admin/teaching/course_works', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'MA')
            ->assertJsonPath('data.finish_until_date', '2026-03-17');

        $workId = $response->json('data.id');
        $this->assertDatabaseHas('teaching_course_works', [
            'id' => $workId,
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'finish_until_date' => '2026-03-17',
        ]);

        $this->assertDatabaseHas('teaching_course_student_entries', [
            'teaching_course_work_id' => $workId,
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'date' => '2026-03-17',
            'type' => 'MA',
            'grade' => '2',
            'source' => 'course_work',
        ]);

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $workId,
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
        ]);
    });

    test('validates work type against schema', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->course->id,
            'type' => 'INVALID',
            'groups' => [],
        ])->assertStatus(422)->assertJsonValidationErrors(['type']);
    });

    test('validates finish until date', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'finish_until_date' => 'not-a-date',
            'groups' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('finish_until_date');
    });

    test('defaults finish until date to the work date', function () {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'date_for_all_groups' => '2026-03-10',
            'groups' => [],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.finish_until_date', '2026-03-10');

        $this->assertDatabaseHas('teaching_course_works', [
            'id' => $response->json('data.id'),
            'date_for_all_groups' => '2026-03-10',
            'finish_until_date' => '2026-03-10',
        ]);
    });

    test('limits random group size to the number of active course students', function () {
        $this->actingAs($this->admin, 'sanctum');

        $courseStudents = User::factory()->count(3)->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $courseStudents->each(fn (User $student) => $this->course->teachingCourseStudents()->create([
            'user_id' => $student->id,
        ]));

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'is_group_work' => true,
            'is_random_groups' => true,
            'group_size' => 4,
            'groups' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('group_size');

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'is_group_work' => true,
            'is_random_groups' => true,
            'group_size' => 3,
            'groups' => [],
        ])->assertCreated()->assertJsonPath('data.group_size', 3);
    });

    test('uses grading entries from the area assigned to courses from 2026/27 onward', function (string $schoolyearLabel) {
        $this->actingAs($this->admin, 'sanctum');
        $this->schoolyear->update(['name' => $schoolyearLabel, 'concerns' => $schoolyearLabel]);

        $entryArea = TeachingEntryArea::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'name' => 'Digitale Grundbildung',
        ]);
        TeachingEntryDefinition::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'teaching_entry_area_id' => $entryArea->id,
            'short_name' => 'A',
            'name' => 'Auftrag',
            'category' => 'Benotung',
        ]);
        TeachingEntryDefinition::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->admin->id,
            'teaching_entry_area_id' => $entryArea->id,
            'short_name' => 'E',
            'name' => 'Ermahnung',
            'category' => 'Verhalten',
        ]);
        $this->course->update(['teaching_entry_area_id' => $entryArea->id]);

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->course->id,
            'type' => 'A',
            'groups' => [],
        ])->assertCreated()->assertJsonPath('data.type', 'A');

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->course->id,
            'type' => 'E',
            'groups' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('type');
    })->with(['2026/27', '2027/28']);

    test('returns 403 when trying to store on course of other school', function () {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $this->otherCourse->id,
            'type' => 'MA',
            'groups' => [],
        ])->assertStatus(403);
    });

    test('store uses the course owner schema definitions for admins', function () {
        $this->actingAs($this->admin, 'sanctum');

        TeachingSchema::query()
            ->where('user_id', $this->teacher->id)
            ->where('schoolyear_id', $this->schoolyear->id)
            ->where('schema_id', $this->schemaId)
            ->update([
                'works' => [[
                    'short_name' => 'TE',
                    'name' => 'Teacher Work',
                    'calculation' => 'average',
                    'grades' => [
                        ['grade' => '1', 'value' => '1'],
                        ['grade' => '2', 'value' => '2'],
                    ],
                    'default_grade' => null,
                ]],
            ]);

        $teacherCourse = TeachingCourse::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'teaching_schema_id' => $this->schemaId,
            'classes' => ['2A'],
        ]);

        $this->postJson('/api/admin/teaching/course_works', [
            'teaching_course_id' => $teacherCourse->id,
            'type' => 'TE',
            'groups' => [],
        ])->assertCreated()->assertJsonPath('data.type', 'TE');
    });
});

describe('show update destroy', function () {
    test('show returns work for own school', function () {
        $this->actingAs($this->admin, 'sanctum');

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Show me',
            'groups' => [],
        ]);

        $this->getJson('/api/admin/teaching/course_works/'.$work->id)
            ->assertOk()
            ->assertJsonPath('data.id', $work->id);
    });

    test('update re-syncs derived entries', function () {
        $this->actingAs($this->admin, 'sanctum');

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Sync me',
            'groups' => [[
                'student_ids' => [$this->student->id],
                'grade' => '2',
                'comment' => 'Initial',
                'date' => '2026-03-01',
            ]],
        ]);
        app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);

        $this->putJson('/api/admin/teaching/course_works/'.$work->id, [
            'type' => 'MA',
            'title' => 'Sync me updated',
            'is_group_work' => true,
            'finish_until_date' => '2026-03-09',
            'groups' => [[
                'student_ids' => [$this->student->id],
                'grade' => '1',
                'comment' => 'Updated',
                'date' => '2026-03-02',
            ]],
        ])->assertOk();

        $work->refresh();
        expect($work->title)->toBe('Sync me updated')
            ->and($work->finish_until_date?->toDateString())->toBe('2026-03-09');

        $entry = TeachingCourseStudentEntry::query()
            ->where('teaching_course_work_id', $work->id)
            ->where('user_id', $this->student->id)
            ->where('source', 'course_work')
            ->first();

        expect($entry)->not->toBeNull()
            ->and($entry->grade)->toBe('1')
            ->and($entry->description)->toBe('Updated');

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
        ]);
    });

    test('update persists an individual students grade and comment', function () {
        $this->actingAs($this->admin, 'sanctum');
        $this->course->teachingCourseStudents()->create(['user_id' => $this->student->id]);

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Individual work',
            'is_group_work' => false,
            'date_for_all_groups' => '2026-09-21',
            'groups' => [],
        ]);

        $this->putJson('/api/admin/teaching/course_works/'.$work->id, [
            'type' => 'MA',
            'title' => 'Individual work',
            'is_group_work' => false,
            'date_for_all_groups' => '2026-09-21',
            'finish_until_date' => '2026-10-12',
            'groups' => [[
                'student_ids' => [$this->student->id],
                'date' => '2026-09-21',
                'comment' => null,
                'grade' => null,
                'grades' => [[
                    'student_id' => $this->student->id,
                    'grade' => '1',
                ]],
                'comments' => [[
                    'student_id' => $this->student->id,
                    'comment' => 'Sehr sauber gearbeitet',
                ]],
                'points' => [],
            ]],
        ])->assertOk()
            ->assertJsonPath('data.groups.0.student_ids.0', $this->student->id)
            ->assertJsonPath('data.groups.0.date', '2026-09-21')
            ->assertJsonPath('data.groups.0.grades.0.grade', '1')
            ->assertJsonPath('data.groups.0.comments.0.comment', 'Sehr sauber gearbeitet');

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $this->student->id,
            'student_grade' => '1',
            'student_comment' => 'Sehr sauber gearbeitet',
        ]);
        $this->assertDatabaseHas('teaching_course_student_entries', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $this->student->id,
            'date' => '2026-10-12',
            'grade' => '1',
            'description' => 'Sehr sauber gearbeitet',
            'source' => 'course_work',
        ]);
    });

    test('destroy deletes work and derived entries', function () {
        $this->actingAs($this->admin, 'sanctum');
        $secondStudent = User::factory()->create(['school_id' => $this->school->id]);

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Delete me',
            'is_group_work' => true,
            'groups' => [[
                'student_ids' => [$this->student->id, $secondStudent->id],
                'grade' => '3',
                'comment' => 'Remove',
            ]],
        ]);
        app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);

        expect(TeachingCourseStudentEntry::where('teaching_course_work_id', $work->id)->where('source', 'course_work')->count())->toBe(2);

        $this->deleteJson('/api/admin/teaching/course_works/'.$work->id)->assertNoContent();

        $this->assertDatabaseMissing('teaching_course_works', ['id' => $work->id]);
        $this->assertDatabaseMissing('teaching_course_student_entries', [
            'teaching_course_work_id' => $work->id,
            'source' => 'course_work',
        ]);
        $this->assertDatabaseMissing('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
        ]);
    });

    test('syncWork rebuilds non-group work groups to current course students and preserves existing grade data', function () {
        $studentA = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $studentA->assignRole('student');

        $studentB = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $studentB->assignRole('student');

        $staleStudent = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $staleStudent->assignRole('student');

        $this->course->teachingCourseStudents()->create(['user_id' => $studentA->id]);
        $this->course->teachingCourseStudents()->create(['user_id' => $studentB->id]);

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Legacy non-group list',
            'is_group_work' => false,
            'date_for_all_groups' => '2026-03-09',
            'groups' => [[
                'student_ids' => [$studentA->id],
                'date' => '2026-03-09',
                'grade' => null,
                'comment' => null,
                'grades' => [[
                    'student_id' => $studentA->id,
                    'grade' => '2',
                ]],
                'points' => [[
                    'student_id' => $studentA->id,
                    'points' => 37.5,
                ]],
                'comments' => [[
                    'student_id' => $studentA->id,
                    'comment' => 'already graded',
                ]],
            ], [
                'student_ids' => [$staleStudent->id],
                'date' => '2026-03-09',
                'grade' => null,
                'comment' => null,
                'grades' => [[
                    'student_id' => $staleStudent->id,
                    'grade' => '',
                ]],
                'comments' => [[
                    'student_id' => $staleStudent->id,
                    'comment' => '',
                ]],
            ]],
        ]);

        app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);

        $work->refresh();
        $groupStudentIds = collect($work->groups)
            ->flatMap(fn ($group) => (array) ($group['student_ids'] ?? []))
            ->unique()
            ->values()
            ->all();

        expect($groupStudentIds)->toContain($studentA->id, $studentB->id)
            ->and($groupStudentIds)->not->toContain($staleStudent->id);

        $groupForStudentA = collect($work->groups)
            ->first(fn ($group) => in_array($studentA->id, (array) ($group['student_ids'] ?? []), true));

        expect($groupForStudentA)->not->toBeNull()
            ->and($groupForStudentA['grades'][0]['grade'] ?? null)->toBe('2')
            ->and($groupForStudentA['points'][0]['points'] ?? null)->toBe(37.5)
            ->and($groupForStudentA['comments'][0]['comment'] ?? null)->toBe('already graded');

        $this->assertDatabaseHas('teaching_course_student_entries', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $studentA->id,
            'source' => 'course_work',
        ]);

        $this->assertDatabaseHas('teaching_course_student_entries', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $studentB->id,
            'source' => 'course_work',
        ]);

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $studentA->id,
        ]);

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $studentB->id,
        ]);

        $this->assertDatabaseMissing('teaching_course_student_entries', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $staleStudent->id,
            'source' => 'course_work',
        ]);

        $this->assertDatabaseMissing('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $staleStudent->id,
        ]);
    });

    test('syncWork resolves collided import course students to placeholder users', function () {
        $collisionId = 880001;

        $collidingUser = User::factory()->create([
            'id' => $collisionId,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'clara.work-collision@test.invalid',
            'first_name' => 'Clara',
            'last_name' => 'Foetschl',
            'schoolclass' => '4T',
        ]);

        $import = Import116::factory()->create([
            'id' => $collisionId,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'student_code' => 'WORK-COLLISION-001',
            'import_user_id' => $this->admin->id,
            'first_name' => 'Alina',
            'last_name' => 'Husic',
            'class' => '5A',
            'email' => null,
        ]);

        $courseStudent = $this->course->teachingCourseStudents()->create([
            'user_id' => $collidingUser->id,
            'import116_id' => $import->id,
        ]);

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'title' => 'Import collision work',
            'is_group_work' => false,
            'date_for_all_groups' => '2026-06-03',
            'groups' => [[
                'student_ids' => [$import->id],
                'date' => '2026-06-03',
                'grade' => null,
                'comment' => null,
                'grades' => [[
                    'student_id' => $import->id,
                    'grade' => '0',
                ]],
                'comments' => [[
                    'student_id' => $import->id,
                    'comment' => 'Preserve me',
                ]],
            ]],
        ]);

        app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);

        $placeholderUserId = $import->fresh()->user_id;

        expect($placeholderUserId)->not->toBeNull()
            ->and($placeholderUserId)->not->toBe($collidingUser->id)
            ->and($courseStudent->fresh()->user_id)->toBe($placeholderUserId)
            ->and($courseStudent->fresh()->import116_id)->toBe($import->id);

        $work->refresh();
        $group = collect($work->groups)->first();

        expect($group['student_ids'] ?? [])->toBe([$placeholderUserId])
            ->and($group['grades'][0]['student_id'] ?? null)->toBe($placeholderUserId)
            ->and($group['grades'][0]['grade'] ?? null)->toBe('0')
            ->and($group['comments'][0]['comment'] ?? null)->toBe('Preserve me');

        $this->assertDatabaseHas('teaching_course_student_entries', [
            'teaching_course_id' => $this->course->id,
            'teaching_course_work_id' => $work->id,
            'user_id' => $placeholderUserId,
            'grade' => '0',
            'source' => 'course_work',
        ]);

        $this->assertDatabaseMissing('teaching_course_student_entries', [
            'teaching_course_id' => $this->course->id,
            'teaching_course_work_id' => $work->id,
            'user_id' => $collidingUser->id,
            'source' => 'course_work',
        ]);
    });

    test('syncWork indexes nested group student references', function () {
        $this->actingAs($this->admin, 'sanctum');

        $studentA = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $studentA->assignRole('student');

        $studentB = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $studentB->assignRole('student');

        $studentC = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $studentC->assignRole('student');

        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'is_group_work' => true,
            'groups' => [[
                'student_ids' => [$studentA->id],
                'date' => '2026-03-11',
                'name' => 'Gruppe Alpha',
                'comment' => 'Gemeinsame Gruppenrückmeldung',
                'grades' => [[
                    'student_id' => $studentB->id,
                    'grade' => '2',
                ]],
                'points' => [[
                    'student_id' => $studentC->id,
                    'points' => 12,
                ]],
                'comments' => [[
                    'student_id' => $this->student->id,
                    'comment' => 'OK',
                ]],
            ]],
        ]);

        app(TeachingCourseWorkEntrySyncService::class)->syncWork($work);

        foreach ([$studentA, $studentB, $studentC, $this->student] as $student) {
            $this->assertDatabaseHas('teaching_course_work_group_students', [
                'teaching_course_work_id' => $work->id,
                'teaching_course_id' => $this->course->id,
                'user_id' => $student->id,
            ]);
        }

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $studentB->id,
            'group_index' => 0,
            'group_name' => 'Gruppe Alpha',
            'group_date' => '2026-03-11',
            'group_comment' => 'Gemeinsame Gruppenrückmeldung',
            'uses_individual_grades' => true,
            'student_grade' => '2',
        ]);

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $studentC->id,
            'student_points' => 12,
        ]);

        $work->forceFill(['groups' => []])->save();

        $response = $this->getJson('/api/admin/teaching/course_works/'.$work->id);

        $response->assertOk()
            ->assertJsonPath('data.groups.0.name', 'Gruppe Alpha')
            ->assertJsonPath('data.groups.0.comment', 'Gemeinsame Gruppenrückmeldung')
            ->assertJsonPath('data.groups.0.student_ids.0', $studentA->id)
            ->assertJsonPath('data.groups.0.grades.0.student_id', $studentA->id)
            ->assertJsonPath('data.groups.0.grades.1.student_id', $studentB->id)
            ->assertJsonPath('data.groups.0.grades.1.grade', '2')
            ->assertJsonPath('data.groups.0.points.0.student_id', $studentC->id)
            ->assertJsonPath('data.groups.0.points.0.points', 12)
            ->assertJsonPath('data.groups.0.comments.2.student_id', $this->student->id)
            ->assertJsonPath('data.groups.0.comments.2.comment', 'OK');
    });

    test('backfill command indexes existing legacy work groups', function () {
        $work = TeachingCourseWork::query()->create([
            'teaching_course_id' => $this->course->id,
            'type' => 'MA',
            'is_group_work' => true,
            'groups' => [[
                'student_ids' => [$this->student->id],
                'grade' => '2',
            ]],
        ]);

        $this->artisan('schooltool:backfill-teaching-course-work-group-students', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'user_id' => $this->student->id,
        ]);

        $this->artisan('schooltool:backfill-teaching-course-work-group-students')
            ->assertSuccessful();

        $this->assertDatabaseHas('teaching_course_work_group_students', [
            'teaching_course_work_id' => $work->id,
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
        ]);
    });
});
