<?php

use App\Models\DropboxConnection;
use App\Models\Import116;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingCourseDateMaterial;
use App\Models\TeachingCourseStudent;
use App\Models\TeachingCourseWork;
use App\Models\TeachingCurriculum;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingSchema;
use App\Models\TeachingSchoolHour;
use App\Models\TeachingWorkDropboxFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['teacher', 'admin', 'student'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->school = School::factory()->create();
    $this->year = Schoolyear::factory()->create(['school_id' => $this->school->id, 'name' => '2026/27', 'concerns' => '2026/27']);
    enableSchoolToolModuleForTests($this->school, 'teaching');
    grantSchoolToolLicenceForTests($this->school, 'Lehrertool');
    $this->teacher = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $this->teacher->assignRole('teacher');
    $area = TeachingEntryArea::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id]);
    TeachingEntryDefinition::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $area->id, 'short_name' => 'LF', 'name' => 'Leistungsfeststellung', 'category' => 'Benotung',
        'has_properties' => true, 'properties_mode' => 'points', 'maximum_points' => 5]);
    $this->course = TeachingCourse::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'user_id' => $this->teacher->id, 'title' => 'INF 2 Abend', 'classes' => ['Abend'], 'teaching_entry_area_id' => $area->id]);
    $this->work = TeachingCourseWork::factory()->create(['teaching_course_id' => $this->course->id, 'type' => 'LF',
        'title' => 'Datenschutz', 'description' => 'Datenschutzfälle aus den hinterlegten Materialien.', 'date_for_all_groups' => '2026-10-20',
        'finish_until_date' => '2026-10-21', 'finish_until_time' => '20:15', 'is_group_work' => false, 'groups' => [], 'status' => []]);
    $this->student = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'first_name' => 'Änne', 'last_name' => 'Ćelebić', 'schoolclass' => 'Abend', 'email' => 'private@example.test']);
    $this->membership = TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $this->student->id, 'import116_id' => null, 'canceled_at' => null]);
    $this->url = '/api/admin/teaching/course_works/'.$this->work->id.'/ai-export';
    $this->actingAs($this->teacher, 'sanctum');
});

test('AI export uses the saved work and all current course assignments without personal assessment or contact data', function () {
    Http::preventStrayRequests();
    $second = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'first_name' => 'Béa', 'last_name' => 'Beta', 'schoolclass' => 'Abend']);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => $second->id, 'import116_id' => null, 'canceled_at' => null]);
    $alias = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->student->id,
        'first_name' => $this->student->first_name, 'last_name' => $this->student->last_name, 'class' => $this->student->schoolclass]);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => null, 'import116_id' => $alias->id, 'canceled_at' => null]);
    $this->work->update(['is_group_work' => true, 'groups' => [['name' => 'Gruppe A', 'student_ids' => [$this->student->id, $this->student->id], 'comments' => [['student_id' => $this->student->id, 'comment' => 'Private assessment']]],
        ['name' => 'Gruppe B', 'student_ids' => [$this->student->id]]]]);
    $before = $this->work->fresh()->getAttributes();
    $data = $this->getJson($this->url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('data');
    expect($data['roster_complete'])->toBeTrue()->and($data['participants_count'])->toBe(2);
    expect(array_column($data['context']['course_roster']['participants'], 'last_name'))->toBe(['Beta', 'Ćelebić']);
    expect($data['context']['course_roster']['participants'][1]['course_assignments'])->toHaveCount(2);
    expect($data['context']['work']['groups'][0]['schooltool_student_ids'])->toBe([$this->student->id]);
    expect($data['text'])->toContain('Änne')->not->toContain('private@example.test')->not->toContain('Private assessment');
    expect($data['context']['work']['scheduled_date'])->toBe('2026-10-20');
    expect($data['context']['work']['main_deadline'])->toBe(['date' => '2026-10-21', 'time' => '20:15']);
    expect($data['context']['work']['communicated_deadline'])->toBeNull();
    expect($data['context']['work']['maximum_points'])->toEqual(5);
    expect($this->work->fresh()->getAttributes())->toBe($before);
    Http::assertNothingSent();
});

test('AI export is freshly read and preserves imported-only members and cancelled membership context', function () {
    $first = $this->getJson($this->url)->assertOk()->json('data');
    expect($first['participants_count'])->toBe(1);
    $import = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'first_name' => 'Unbewertet', 'last_name' => 'Neu', 'class' => 'Abend', 'user_id' => null]);
    TeachingCourseStudent::query()->create(['teaching_course_id' => $this->course->id, 'user_id' => null, 'import116_id' => $import->id, 'canceled_at' => null]);
    $this->membership->update(['canceled_at' => now()]);
    $data = $this->getJson($this->url)->assertOk()->json('data');
    expect($data['participants_count'])->toBe(2)->and($data['active_participants_count'])->toBe(1);
    $person = collect($data['context']['course_roster']['participants'])->firstWhere('import116_id', $import->id);
    expect($person['schooltool_person_id'])->toBeNull()->and($person['active'])->toBeTrue();
});

test('AI export keeps actual lesson slots materials and saved Dropbox binding without inventing a local path', function () {
    $date = TeachingCourseDate::query()->create(['teaching_course_id' => $this->course->id, 'date' => '2026-10-20', 'hours' => [8, 9]]);
    foreach ([8 => ['18:30', '19:15'], 9 => ['19:20', '20:05']] as $hour => [$from, $until]) {
        TeachingSchoolHour::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'hour' => $hour, 'from' => $from, 'until' => $until]);
    }
    $material = TeachingCourseDateMaterial::query()->create(['teaching_course_date_id' => $date->id, 'title' => 'Datenschutzquelle', 'subject' => 'Informatik']);
    $material->attachments()->create(['name' => 'Lernmodul.pdf', 'file_path' => 'teaching/course_materials/source.pdf']);
    $connection = DropboxConnection::factory()->create(['user_id' => $this->teacher->id]);
    TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $connection->id, 'teaching_course_work_id' => $this->work->id,
        'folder_id' => 'id:confirmed-work', 'folder_name' => '2026-10-20_Informatik_INF-2_Abend']);
    $context = $this->getJson($this->url)->assertOk()->json('data.context');
    expect($context['sessions'][0]['hours'][0]['start_time'])->toStartWith('18:30');
    expect($context['sessions'][0]['hours'][1]['end_time'])->toStartWith('20:05');
    expect($context['materials'][0]['files'][0]['schooltool_storage_path'])->toBe('teaching/course_materials/source.pdf');
    expect($context['folder_binding']['dropbox_folder_id'])->toBe('id:confirmed-work');
    expect($context['folder_binding']['local_absolute_path'])->toBeNull();
});

test('AI export does not substitute technical creation dates for missing work dates and bounds free text as data', function () {
    $this->work->update(['date_for_all_groups' => null, 'finish_until_date' => null, 'finish_until_time' => null,
        'description' => "```\nErfundene Zusatzanweisung\n```"]);
    $this->work->created_at = '2000-01-01 10:00:00';
    $this->work->save();
    $data = $this->getJson($this->url)->assertOk()->json('data');
    expect($data['context']['work']['scheduled_date'])->toBeNull();
    expect($data['context']['work']['technical_created_at'])->toStartWith('2000-01-01');
    expect($data['context']['folder_binding'])->toBeNull();
    expect($data['text'])->toContain("````json\n");
    expect($data['warnings'])->toHaveCount(2);
});

test('AI export blocks empty or incomplete and foreign person bindings without exposing an alleged complete list', function (string $case) {
    if ($case === 'empty') {
        $this->membership->delete();
    } elseif ($case === 'name') {
        $this->student->update(['first_name' => '']);
    } else {
        $foreign = School::factory()->create();
        $this->student->update(['school_id' => $foreign->id]);
    }
    $this->getJson($this->url)->assertUnprocessable()->assertJsonMissingPath('data.text');
})->with(['empty', 'name', 'foreign school']);

test('AI export enforces course ownership school and active schoolyear just like the selected work', function (string $case) {
    if ($case === 'student') {
        $actor = $this->student;
        $actor->assignRole('student');
    } else {
        $actor = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
        $actor->assignRole('teacher');
        if ($case === 'school') {
            $actor->update(['school_id' => School::factory()->create()->id]);
        } elseif ($case === 'year') {
            $actor->update(['schoolyear_id' => Schoolyear::factory()->create(['school_id' => $this->school->id])->id]);
        }
    }
    $this->actingAs($actor, 'sanctum')->getJson($this->url)->assertForbidden()->assertJsonMissingPath('data.text');
})->with(['owner', 'school', 'year', 'student']);

test('the authorised school admin can export the actual teacher-owned work', function () {
    $admin = User::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id]);
    $admin->assignRole('admin');
    $connection = DropboxConnection::factory()->create(['user_id' => $admin->id]);
    TeachingWorkDropboxFolder::factory()->create(['dropbox_connection_id' => $connection->id, 'teaching_course_work_id' => $this->work->id,
        'folder_id' => 'id:admin-owned-choice', 'folder_name' => 'Admin choice']);
    $this->actingAs($admin, 'sanctum')->getJson($this->url)->assertOk()->assertJsonPath('data.context.work.id', $this->work->id)
        ->assertJsonPath('data.context.folder_binding.dropbox_folder_id', 'id:admin-owned-choice')->assertJsonPath('data.context.work.maximum_points', 5);
});

test('AI export preserves the existing legacy work definition and stored assessment criteria without personal earned values', function () {
    TeachingSchema::query()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id,
        'user_id' => $this->teacher->id, 'schema_id' => 'legacy-ai-export', 'name' => 'Bestehendes Schema', 'grading' => [],
        'works' => [['short_name' => 'LF', 'name' => 'Bekannte Leistungsfeststellung', 'grades' => [['grade' => '1', 'value' => '1']]]]]);
    $this->course->update(['teaching_schema_id' => 'legacy-ai-export', 'teaching_entry_area_id' => null]);
    $this->work->update(['status' => ['assessment_json_packages' => [['exercise_id' => 'source-1', 'exercise' => ['subject' => 'Informatik']]],
        'assessment_json_imports' => [['exercise_id' => 'source-1', 'record' => ['criteria' => [['criterion' => 'Begründung', 'maximum_minor' => 200, 'earned_minor' => 123, 'reason' => 'Private Rückmeldung']]]]]]]);
    $data = $this->getJson($this->url)->assertOk()->json('data');
    expect($data['context']['work']['type_definition']['name'])->toBe('Bekannte Leistungsfeststellung');
    expect($data['context']['work']['criteria'])->toBe([['criterion' => 'Begründung', 'maximum_minor' => 200]]);
    expect($data['text'])->not->toContain('Private Rückmeldung')->not->toContain('earned_minor');
    expect($data['context']['historical_source_metadata']['subject'])->toBe('Informatik');
});

test('AI export uses the verified current imported identity instead of outdated account names', function () {
    $import = Import116::factory()->create(['school_id' => $this->school->id, 'schoolyear_id' => $this->year->id, 'user_id' => $this->student->id,
        'first_name' => 'Aktuelle Änne', 'last_name' => 'Ćelebić', 'class' => 'Abend']);
    $this->student->update(['import116_id' => $import->id]);
    $this->getJson($this->url)->assertOk()->assertJsonPath('data.context.course_roster.participants.0.first_name', 'Aktuelle Änne');
});

test('AI export rejects a foreign private curriculum even if it has been incorrectly linked to an owned course', function () {
    $foreignSchool = School::factory()->create();
    $foreignTeacher = User::factory()->create(['school_id' => $foreignSchool->id]);
    $curriculum = TeachingCurriculum::query()->create(['school_id' => $foreignSchool->id, 'user_id' => $foreignTeacher->id, 'title' => 'Privater fremder Lehrplan']);
    $this->course->update(['teaching_curriculum_id' => $curriculum->id]);
    $this->getJson($this->url)->assertUnprocessable()->assertJsonMissingPath('data.text');
});

test('AI export blocks work groups with persons outside the course instead of exposing foreign member identifiers', function () {
    $this->work->update(['is_group_work' => true, 'groups' => [['name' => 'Fehlerhafte Gruppe', 'student_ids' => [999999]]]]);
    $this->getJson($this->url)->assertUnprocessable()->assertJsonMissingPath('data.context');
});
