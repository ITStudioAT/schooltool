<?php

use App\Http\Resources\Admin\Teaching\CourseDateResource;
use App\Http\Resources\Admin\Teaching\CourseResource;
use App\Http\Resources\Admin\Teaching\CourseSummaryResource;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Services\TeachingCourseDateService;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->previousConnectionResolver = Model::getConnectionResolver();
    $this->previousContainer = Container::getInstance();
    Container::setInstance(new Application);
    $this->connection = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:');
    $resolver = new ConnectionResolver(['attachment_counts_unit' => $this->connection]);
    $resolver->setDefaultConnection('attachment_counts_unit');
    Model::setConnectionResolver($resolver);

    $this->connection->statement('CREATE TABLE teaching_course_dates (id INTEGER PRIMARY KEY, teaching_course_id INTEGER, date TEXT, hours TEXT, content TEXT, status TEXT, attendance_checked INTEGER)');
    $this->connection->statement('CREATE TABLE teaching_course_date_materials (id INTEGER PRIMARY KEY, teaching_course_date_id INTEGER, title TEXT)');
    $this->connection->statement('CREATE TABLE teaching_course_date_material_attachments (id INTEGER PRIMARY KEY, teaching_course_date_material_id INTEGER, student_visible INTEGER, source_teaching_curriculum_document_id INTEGER)');
    $this->connection->statement('CREATE TABLE teaching_courses (id INTEGER PRIMARY KEY, school_id INTEGER, schoolyear_id INTEGER, teaching_curriculum_id INTEGER)');
    $this->connection->statement('CREATE TABLE teaching_curricula (id INTEGER PRIMARY KEY, school_id INTEGER, schoolyear_id INTEGER, topics TEXT)');
    $this->connection->statement('CREATE TABLE teaching_curriculum_documents (id INTEGER PRIMARY KEY, teaching_curriculum_id INTEGER, topic_id TEXT, unit_id TEXT, source_type TEXT)');
});

afterEach(function () {
    if ($this->previousConnectionResolver) {
        Model::setConnectionResolver($this->previousConnectionResolver);
    } else {
        Model::unsetConnectionResolver();
    }

    Container::setInstance($this->previousContainer);
    Mockery::close();
});

test('timetable summaries count individual shared and private attachments without loading their contents', function () {
    foreach (range(1, 5) as $id) {
        $this->connection->table('teaching_course_dates')->insert([
            'id' => $id,
            'teaching_course_id' => 10,
            'date' => '2026-09-'.(13 + $id),
            'hours' => '[1]',
        ]);
    }

    $this->connection->table('teaching_course_date_materials')->insert([
        ['id' => 20, 'teaching_course_date_id' => 2],
        ['id' => 30, 'teaching_course_date_id' => 3],
        ['id' => 40, 'teaching_course_date_id' => 4],
        ['id' => 50, 'teaching_course_date_id' => 5],
        ['id' => 51, 'teaching_course_date_id' => 5],
        ['id' => 99, 'teaching_course_date_id' => 99],
    ]);
    $this->connection->table('teaching_course_date_material_attachments')->insert([
        ['teaching_course_date_material_id' => 30, 'student_visible' => true],
        ['teaching_course_date_material_id' => 30, 'student_visible' => true],
        ['teaching_course_date_material_id' => 40, 'student_visible' => false],
        ['teaching_course_date_material_id' => 40, 'student_visible' => false],
        ['teaching_course_date_material_id' => 50, 'student_visible' => true],
        ['teaching_course_date_material_id' => 50, 'student_visible' => false],
        ['teaching_course_date_material_id' => 51, 'student_visible' => true],
        ['teaching_course_date_material_id' => 99, 'student_visible' => true],
        ['teaching_course_date_material_id' => 99, 'student_visible' => false],
    ]);

    $this->connection->enableQueryLog();
    $dates = TeachingCourseDate::query()
        ->where('teaching_course_id', 10)
        ->withExists('materials')
        ->orderBy('id')
        ->get();
    (new TeachingCourseDateService)->loadCurriculumAttachmentCounts($dates);
    $course = (new TeachingCourse)->forceFill(['id' => 10]);
    $course->setRelation('teachingCourseDates', $dates);
    $payload = (new CourseSummaryResource($course))->resolve(new Request);

    expect($this->connection->getQueryLog())->toHaveCount(4);

    foreach ([[false, 0, 0], [true, 0, 0], [true, 2, 0], [true, 0, 2], [true, 2, 1]] as $index => [$assigned, $shared, $private]) {
        $date = $payload['course_dates'][$index];
        expect($date['has_curriculum_assignment'])->toBe($assigned)
            ->and($date['shared_curriculum_attachments_count'])->toBe($shared)
            ->and($date['private_curriculum_attachments_count'])->toBe($private)
            ->and($date['unadopted_curriculum_attachments_count'])->toBe(0)
            ->and($date['has_shared_curriculum_attachments'])->toBe($shared > 0)
            ->and($date['has_private_curriculum_attachments'])->toBe($private > 0)
            ->and($date)->not->toHaveKey('adopted_materials')
            ->and($dates[$index]->relationLoaded('materials'))->toBeFalse()
            ->and($dates[$index]->relationLoaded('materialAttachments'))->toBeFalse();
    }
});

test('assigned curriculum source files count as hidden until copied and are deduplicated against published copies', function () {
    $this->connection->table('teaching_courses')->insert([
        ['id' => 10, 'school_id' => 1, 'schoolyear_id' => 2, 'teaching_curriculum_id' => 100],
        ['id' => 11, 'school_id' => 99, 'schoolyear_id' => 2, 'teaching_curriculum_id' => 100],
    ]);
    $this->connection->table('teaching_curricula')->insert([
        'id' => 100, 'school_id' => 1, 'schoolyear_id' => 2,
        'topics' => json_encode([['id' => 'topic', 'title' => 'Introduction', 'units' => [
            ['id' => 'assigned', 'title' => 'Welcome'],
            ['id' => 'other', 'title' => 'Later'],
        ]]]),
    ]);
    $this->connection->table('teaching_curriculum_documents')->insert([
        ['id' => 201, 'teaching_curriculum_id' => 100, 'topic_id' => 'topic', 'unit_id' => 'assigned', 'source_type' => 'unit_file'],
        ['id' => 202, 'teaching_curriculum_id' => 100, 'topic_id' => 'topic', 'unit_id' => 'assigned', 'source_type' => 'unit_file'],
        ['id' => 203, 'teaching_curriculum_id' => 100, 'topic_id' => 'topic', 'unit_id' => 'other', 'source_type' => 'unit_file'],
        ['id' => 204, 'teaching_curriculum_id' => 100, 'topic_id' => 'topic', 'unit_id' => 'assigned', 'source_type' => 'upload'],
        ['id' => 205, 'teaching_curriculum_id' => 100, 'topic_id' => 'removed', 'unit_id' => 'assigned', 'source_type' => 'unit_file'],
    ]);
    foreach (range(1, 6) as $id) {
        $this->connection->table('teaching_course_dates')->insert([
            'id' => $id, 'teaching_course_id' => $id === 6 ? 11 : 10,
            'date' => '2026-09-'.(13 + $id), 'hours' => '[1]',
        ]);
    }
    $this->connection->table('teaching_course_date_materials')->insert([
        ['id' => 20, 'teaching_course_date_id' => 2, 'title' => 'Introduction: Welcome'],
        ['id' => 30, 'teaching_course_date_id' => 3, 'title' => 'Introduction: Welcome'],
        ['id' => 31, 'teaching_course_date_id' => 3, 'title' => 'Introduction: Welcome'],
        ['id' => 40, 'teaching_course_date_id' => 4, 'title' => 'Introduction: Welcome'],
        ['id' => 50, 'teaching_course_date_id' => 5, 'title' => 'Unknown unit'],
        ['id' => 60, 'teaching_course_date_id' => 6, 'title' => 'Introduction: Welcome'],
    ]);
    $this->connection->table('teaching_course_date_material_attachments')->insert([
        ['id' => 1, 'teaching_course_date_material_id' => 30, 'source_teaching_curriculum_document_id' => 201, 'student_visible' => true],
        ['id' => 2, 'teaching_course_date_material_id' => 31, 'source_teaching_curriculum_document_id' => 201, 'student_visible' => false],
        ['id' => 3, 'teaching_course_date_material_id' => 40, 'source_teaching_curriculum_document_id' => 201, 'student_visible' => false],
        ['id' => 4, 'teaching_course_date_material_id' => 40, 'source_teaching_curriculum_document_id' => 202, 'student_visible' => true],
        ['id' => 5, 'teaching_course_date_material_id' => 40, 'source_teaching_curriculum_document_id' => null, 'student_visible' => true],
    ]);

    $dates = TeachingCourseDate::query()->withExists('materials')->orderBy('id')->get();
    $this->connection->enableQueryLog();
    (new TeachingCourseDateService)->loadCurriculumAttachmentCounts($dates);
    expect($this->connection->getQueryLog())->toHaveCount(5);
    $course = (new TeachingCourse)->forceFill(['id' => 10]);
    $course->setRelation('teachingCourseDates', $dates);
    $payload = (new CourseSummaryResource($course))->resolve(new Request);

    foreach ([[0, 0, 0], [0, 2, 2], [1, 1, 1], [2, 1, 0], [0, 0, 0], [0, 0, 0]] as $index => [$shared, $private, $unadopted]) {
        expect($payload['course_dates'][$index]['shared_curriculum_attachments_count'])->toBe($shared)
            ->and($payload['course_dates'][$index]['private_curriculum_attachments_count'])->toBe($private)
            ->and($payload['course_dates'][$index]['unadopted_curriculum_attachments_count'])->toBe($unadopted);
    }

    $service = Mockery::mock(TeachingCourseDateService::class)->makePartial();
    $service->shouldReceive('supportsAttendanceColumns')->andReturn(false);
    Container::getInstance()->instance(TeachingCourseDateService::class, $service);
    $course->forceFill(['students' => [], 'students_deleted' => []]);
    $dates->load('materials.attachments');
    $dates->each(fn ($date) => $date->setRelation('teachingCourse', $course));
    $request = new Request;
    Container::getInstance()->instance('request', $request);
    $detail = (new CourseResource($course))->resolve($request);
    $detailDates = $detail['course_dates']->resolve($request);
    expect($detailDates[1]['shared_curriculum_attachments_count'])->toBe(0)
        ->and($detailDates[1]['private_curriculum_attachments_count'])->toBe(2)
        ->and($detailDates[1]['unadopted_curriculum_attachments_count'])->toBe(2)
        ->and($detailDates[2]['shared_curriculum_attachments_count'])->toBe(1)
        ->and($detailDates[2]['private_curriculum_attachments_count'])->toBe(1);

    $dates[1]->offsetUnset('unadopted_curriculum_attachments_count');
    $updatedDate = (new CourseDateResource($dates[1]))->resolve($request);
    expect($updatedDate['private_curriculum_attachments_count'])->toBe(2)
        ->and($updatedDate['unadopted_curriculum_attachments_count'])->toBe(2);
});

test('legacy assignments with empty curriculum topics retain adopted attachment counts', function (?string $topics) {
    $this->connection->table('teaching_courses')->insert([
        'id' => 10, 'school_id' => 1, 'schoolyear_id' => 2, 'teaching_curriculum_id' => 100,
    ]);
    $this->connection->table('teaching_curricula')->insert([
        'id' => 100, 'school_id' => 1, 'schoolyear_id' => 2, 'topics' => $topics,
    ]);
    $this->connection->table('teaching_curriculum_documents')->insert([
        'id' => 201, 'teaching_curriculum_id' => 100, 'topic_id' => 'old-topic', 'unit_id' => 'old-unit', 'source_type' => 'unit_file',
    ]);
    $this->connection->table('teaching_course_dates')->insert([
        'id' => 1, 'teaching_course_id' => 10, 'date' => '2026-09-14', 'hours' => '[1]',
    ]);
    $this->connection->table('teaching_course_date_materials')->insert([
        'id' => 20, 'teaching_course_date_id' => 1, 'title' => 'Legacy: Assignment',
    ]);
    $this->connection->table('teaching_course_date_material_attachments')->insert([
        'id' => 1, 'teaching_course_date_material_id' => 20, 'student_visible' => true,
    ]);

    $dates = TeachingCourseDate::query()->get();
    (new TeachingCourseDateService)->loadCurriculumAttachmentCounts($dates);

    expect($dates[0]->shared_curriculum_attachments_count)->toBe(1)
        ->and($dates[0]->private_curriculum_attachments_count)->toBe(0)
        ->and($dates[0]->unadopted_curriculum_attachments_count)->toBe(0);
})->with(['null topics' => [null], 'empty topics' => ['[]']]);
