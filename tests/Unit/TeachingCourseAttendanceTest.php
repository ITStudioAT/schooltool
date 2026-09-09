<?php

use App\Http\Resources\Admin\Teaching\CourseDateResource;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Services\TeachingCourseDateService;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\SQLiteConnection;

beforeEach(function () {
    $this->previousConnectionResolver = Model::getConnectionResolver();
    $connection = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:');
    $resolver = new ConnectionResolver(['attendance_unit' => $connection]);
    $resolver->setDefaultConnection('attendance_unit');
    Model::setConnectionResolver($resolver);
    $connection->statement('CREATE TABLE teaching_course_dates (id INTEGER PRIMARY KEY, teaching_course_id INTEGER, date TEXT NULL, status TEXT NULL, attendance TEXT NULL, attendance_checked INTEGER, created_at TEXT, updated_at TEXT)');

    $this->service = new TeachingCourseDateService;
    $this->course = new TeachingCourse;
    $this->course->setRelation('teachingCourseStudents', collect([
        (object) ['user_id' => 492, 'import116_id' => null],
        (object) ['user_id' => 493, 'import116_id' => null],
        (object) ['user_id' => null, 'import116_id' => 3474],
    ]));
    $this->courseDate = TeachingCourseDate::create([
        'teaching_course_id' => 18,
        'status' => [],
        'attendance' => [],
        'attendance_checked' => false,
    ]);
});

afterEach(function () {
    if ($this->previousConnectionResolver) {
        Model::setConnectionResolver($this->previousConnectionResolver);
    } else {
        Model::unsetConnectionResolver();
    }
});

test('persists and reloads all three individual attendance states', function () {
    foreach ([false, true, null, false] as $state) {
        $this->service->updateCourseDateStatus($this->courseDate, ['toggle_student_id' => 492], $this->course);
        $this->courseDate = $this->courseDate->fresh();

        expect($this->courseDate->attendance)->toBe(['s_492' => $state])
            ->and($this->courseDate->attendance_checked)->toBeFalse();

        $resource = new CourseDateResource($this->courseDate);
        $normalize = new ReflectionMethod($resource, 'normalizeAttendanceForOutput');
        expect($normalize->invoke($resource, $this->courseDate->attendance, $this->service, $this->course))
            ->toBe(['s_492' => $state]);
    }
});

test('sets an exact individual state without depending on the previous state', function (?bool $state) {
    foreach ([true, false] as $checked) {
        $this->courseDate->update(['attendance_checked' => $checked]);
        $this->service->updateCourseDateStatus($this->courseDate, [
            'toggle_student_id' => 492,
            'attendance_state' => $state,
        ], $this->course);

        expect($this->courseDate->fresh()->attendance)->toBe(['s_492' => $state]);
    }
})->with([true, false, null]);

test('retains individual choices when checking and resetting the whole date', function () {
    $attendance = ['s_492' => false, 's_493' => true, 's_3474' => null];
    $this->service->updateCourseDateStatus($this->courseDate, ['attendance' => $attendance], $this->course);

    foreach ([true, false] as $checked) {
        $this->service->updateCourseDateStatus($this->courseDate, ['attendance_checked' => $checked], $this->course);
        $reloaded = $this->courseDate->fresh();

        expect($reloaded->attendance)->toBe($attendance)
            ->and($reloaded->attendance_checked)->toBe($checked);
    }
});

test('cycles inherited checked presence to explicit unchecked', function () {
    $this->courseDate->update(['attendance_checked' => true]);
    $this->service->updateCourseDateStatus($this->courseDate, ['toggle_student_id' => 492], $this->course);

    expect($this->courseDate->fresh()->attendance)->toBe(['s_492' => null]);

    $this->service->updateCourseDateStatus($this->courseDate, ['toggle_student_id' => 492], $this->course);

    expect($this->courseDate->fresh()->attendance)->toBe(['s_492' => false]);
});

test('resets a whole date without altering another date or public status', function () {
    $attendance = ['s_492' => false, 's_493' => true, 's_3474' => null];
    $this->courseDate->update(['attendance' => $attendance, 'attendance_checked' => true, 'status' => ['pruefung']]);
    $otherDate = TeachingCourseDate::create([
        'teaching_course_id' => 18,
        'status' => [],
        'attendance' => $attendance,
        'attendance_checked' => true,
    ]);

    $this->service->updateCourseDateStatus($this->courseDate, [
        'attendance' => [],
        'attendance_checked' => false,
    ], $this->course);

    expect($this->courseDate->fresh()->attendance)->toBe([])
        ->and($this->courseDate->fresh()->attendance_checked)->toBeFalse()
        ->and($this->courseDate->fresh()->status)->toBe(['pruefung'])
        ->and($otherDate->fresh()->attendance)->toBe($attendance)
        ->and($otherDate->fresh()->attendance_checked)->toBeTrue();
});

test('normalizes indexed legacy states and retains untouched student states', function () {
    $this->courseDate->update(['attendance' => [0 => false, 1 => true, 2 => null]]);
    $this->service->updateCourseDateStatus($this->courseDate, ['toggle_student_id' => 492], $this->course);

    expect($this->courseDate->fresh()->attendance)->toBe(['s_492' => true, 's_493' => true, 's_3474' => null]);
});

test('round trips all explicit attendance states in legacy status storage', function () {
    $service = new class extends TeachingCourseDateService
    {
        public function supportsAttendanceColumns(): bool
        {
            return false;
        }
    };

    foreach ([false, true, null] as $state) {
        $service->updateCourseDateStatus($this->courseDate, ['toggle_student_id' => 492], $this->course);
        $this->courseDate = $this->courseDate->fresh();

        expect($service->attendanceFromStatus($this->courseDate->status))->toBe(['s_492' => $state]);
    }

    expect($service->attendanceFromStatus(['att:492:0', 'att:493:1', 'att:3474:null']))
        ->toBe([492 => false, 493 => true, 3474 => null]);
});

test('uses authoritative empty column values instead of stale legacy attendance', function () {
    $this->courseDate->update(['status' => ['att:492:1', 'att_checked:1']]);
    $this->service->updateCourseDateStatus($this->courseDate, ['toggle_student_id' => 492], $this->course);

    expect($this->courseDate->fresh()->attendance)->toBe(['s_492' => false])
        ->and($this->courseDate->fresh()->attendance_checked)->toBeFalse();
});
