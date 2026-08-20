<?php

use App\Models\Import116;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEntry;
use App\Models\StudentTimetablePublishedTimetable;
use App\Models\StudentTimetableSubjectRow;
use App\Models\StudentTimetableV2State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

afterEach(function () {
    Str::createRandomStringsNormally();
});

it('publishes a manual timetable for exactly one selected student', function () {
    [$user, $student] = createPublishedTimetableAdminUser();
    fakePublishedTimetableNameSuffixes(['xyz']);

    $payload = [
        'student_code' => $student->student_code,
        'student_label' => 'Gefälschter Name',
        'name' => '99ZZZ',
        'timetable' => publishedTimetablePayload('LET1'),
        'state' => [
            'activeCourseGroupFilterKeys' => ['d-1'],
            'manualPanelOpen' => true,
        ],
    ];

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/overview/student-timetable', $payload)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Stundenplan für SCHROLL Lukas wurde gespeichert.')
        ->assertJsonPath('data.student_code', $student->student_code)
        ->assertJsonPath('data.student_label', 'SCHROLL Lukas')
        ->assertJsonPath('data.name', '26XYZ');

    $publishedTimetable = StudentTimetablePublishedTimetable::query()->firstOrFail();

    expect($publishedTimetable->school_id)->toBe($user->school_id)
        ->and($publishedTimetable->schoolyear_id)->toBe($user->schoolyear_id)
        ->and($publishedTimetable->published_by_user_id)->toBe($user->id)
        ->and($publishedTimetable->student_code)->toBe($student->student_code)
        ->and($publishedTimetable->student_label)->toBe('SCHROLL Lukas')
        ->and($publishedTimetable->name)->toBe('26XYZ')
        ->and($publishedTimetable->name)->toMatch('/^26[A-Z]{3}$/')
        ->and($publishedTimetable->timetable['semesters'][0]['weeks'][0]['hours'][0]['cells'][0]['courses'][0]['label'])
        ->toBe('LPT1')
        ->and($publishedTimetable->timetable['semesters'][0]['weeks'][0]['hours'][0]['cells'][0]['courses'][0]['is_fu'])
        ->toBeTrue()
        ->and($publishedTimetable->timetable['semesters'][0]['weeks'][0]['hours'][0]['cells'][0]['courses'][0]['recurrence_label'])
        ->toBe('2-wöchig')
        ->and($publishedTimetable->timetable['semesters'][0]['weeks'][0]['hours'][0]['cells'][0]['courses'][0]['recurrence_interval'])
        ->toBe(2)
        ->and($publishedTimetable->state['activeCourseGroupFilterKeys'])
        ->toBe(['d-1']);
});

it('replaces an existing published timetable for the same student', function () {
    [$user, $student] = createPublishedTimetableAdminUser();
    fakePublishedTimetableNameSuffixes(['abc', 'def']);

    foreach (['D1', 'D2'] as $courseLabel) {
        $this->actingAs($user)
            ->postJson('/api/admin/students-timetables/overview/student-timetable', [
                'student_code' => $student->student_code,
                'student_label' => 'SCHROLL Lukas',
                'timetable' => publishedTimetablePayload($courseLabel),
                'state' => [
                    'activeCourseGroupFilterKeys' => [strtolower($courseLabel)],
                ],
            ])
            ->assertSuccessful();
    }

    expect(StudentTimetablePublishedTimetable::query()->count())->toBe(1);

    $publishedTimetable = StudentTimetablePublishedTimetable::query()->firstOrFail();

    expect($publishedTimetable->name)->toBe('26ABC')
        ->and($publishedTimetable->timetable['semesters'][0]['weeks'][0]['hours'][0]['cells'][0]['courses'][0]['label'])
        ->toBe('D2')
        ->and($publishedTimetable->state['activeCourseGroupFilterKeys'])
        ->toBe(['d2']);
});

it('marks students that have a published timetable in the admin robot student list', function () {
    [$user, $student] = createPublishedTimetableAdminUser();

    StudentTimetablePublishedTimetable::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $user->schoolyear_id,
        'published_by_user_id' => $user->id,
        'student_code' => $student->student_code,
        'student_label' => 'SCHROLL Lukas',
        'timetable' => publishedTimetablePayload('D1'),
        'state' => [
            'activeCourseGroupFilterKeys' => ['d-1'],
            'manualPanelOpen' => true,
        ],
        'published_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/robot/students')
        ->assertSuccessful()
        ->assertJsonPath('data.0.student_code', $student->student_code)
        ->assertJsonPath('data.0.has_published_timetable', true)
        ->assertJsonPath('data.0.published_timetable_id', StudentTimetablePublishedTimetable::query()->value('id'));
});

it('returns a published timetable state for the selected admin student', function () {
    [$user, $student] = createPublishedTimetableAdminUser();

    StudentTimetablePublishedTimetable::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $user->schoolyear_id,
        'published_by_user_id' => $user->id,
        'student_code' => $student->student_code,
        'student_label' => 'SCHROLL Lukas',
        'name' => '26GET',
        'timetable' => publishedTimetablePayload('D1'),
        'state' => [
            'activeCourseGroupFilterKeys' => ['d-1'],
            'manualPanelOpen' => true,
            'manualPanelSource' => 'direct',
        ],
        'published_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson("/api/admin/students-timetables/overview/student-timetable?student_code={$student->student_code}")
        ->assertSuccessful()
        ->assertJsonPath('data.student_code', $student->student_code)
        ->assertJsonPath('data.student_label', 'SCHROLL Lukas')
        ->assertJsonPath('data.name', '26GET')
        ->assertJsonPath('data.state.activeCourseGroupFilterKeys.0', 'd-1')
        ->assertJsonPath('data.state.manualPanelOpen', true)
        ->assertJsonPath('data.timetable.semesters.0.weeks.0.hours.0.cells.0.courses.0.label', 'D1');
});

it('keeps published timetable names unique within a school and retries collisions', function () {
    [$user, $student] = createPublishedTimetableAdminUser();
    $otherSchool = School::factory()->create();
    $otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $otherSchool->id,
        'name' => 'Schuljahr 2026/27',
        'from' => '2026-09-07',
    ]);
    $otherUser = User::factory()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherSchoolyear->id,
    ]);

    StudentTimetablePublishedTimetable::query()->create([
        'school_id' => $otherSchool->id,
        'schoolyear_id' => $otherSchoolyear->id,
        'published_by_user_id' => $otherUser->id,
        'student_code' => 'other-school-student',
        'student_label' => 'Andere Schule',
        'name' => '26AAA',
        'timetable' => publishedTimetablePayload('D1'),
        'published_at' => now(),
    ]);

    StudentTimetablePublishedTimetable::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $user->schoolyear_id,
        'published_by_user_id' => $user->id,
        'student_code' => 'existing-student',
        'student_label' => 'Bestehender Schüler',
        'name' => '26AAA',
        'timetable' => publishedTimetablePayload('D1'),
        'published_at' => now(),
    ]);

    fakePublishedTimetableNameSuffixes(['aaa', 'aab']);

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/overview/student-timetable', [
            'student_code' => $student->student_code,
            'timetable' => publishedTimetablePayload('D2'),
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', '26AAB');

    expect(StudentTimetablePublishedTimetable::query()
        ->where('school_id', $user->school_id)
        ->where('name', '26AAA')
        ->count())->toBe(1)
        ->and(StudentTimetablePublishedTimetable::query()
            ->where('school_id', $otherSchool->id)
            ->where('name', '26AAA')
            ->count())->toBe(1);
});

it('uses the schoolyear label when no start date is available', function () {
    [$user, $student] = createPublishedTimetableAdminUser();
    Schoolyear::query()->whereKey($user->schoolyear_id)->update([
        'name' => 'Schuljahr 26/27',
        'from' => null,
    ]);
    fakePublishedTimetableNameSuffixes(['def']);

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/overview/student-timetable', [
            'student_code' => $student->student_code,
            'timetable' => publishedTimetablePayload('D1'),
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', '26DEF');
});

it('returns timetable v2 selection bootstrap data in one response', function () {
    [$user, $student] = createPublishedTimetableAdminUser();

    StudentTimetableV2State::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $user->schoolyear_id,
        'user_id' => $user->id,
        'state' => [
            'timetableV2Selection' => [
                'semester' => 4,
            ],
            'transferredStudentContext' => [
                'student' => [
                    'studentCode' => $student->student_code,
                ],
            ],
        ],
    ]);

    StudentTimetableSubjectRow::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $user->schoolyear_id,
        'semester' => 4,
        'branch' => 'gymnasial',
        'json_code' => 'D4',
        'json_subject' => 'D',
        'name' => 'Deutsch',
        'hours_per_week' => 3,
        'is_active' => true,
        'sort_order' => 1,
        'source' => 'manual',
    ]);

    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $user->schoolyear_id,
        'date' => '2026-02-16',
        'semester' => 1,
        'period' => '1',
        'subject' => 'D',
        'course' => 'D1',
        'class_name' => 'D1-4S-AB',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->getJson("/api/admin/students-timetables/timetable-v2-selection-bootstrap?student_code={$student->student_code}&strict_selection=0&selection[semester]=4")
        ->assertSuccessful()
        ->assertJsonPath('data.state.timetableV2Selection.semester', 4)
        ->assertJsonPath('data.subjects.0.json_code', 'D4')
        ->assertJsonPath('data.course_groups.0.subject', 'D')
        ->assertJsonPath('data.course_groups.0.course', 'D1')
        ->assertJsonPath('data.student_overview.student.student_code', $student->student_code)
        ->assertJsonPath('data.student_overview.selection.semester', 4);
});

it('rejects publishing a timetable for a student outside the selected schoolyear', function () {
    [$user] = createPublishedTimetableAdminUser();

    $this->actingAs($user)
        ->postJson('/api/admin/students-timetables/overview/student-timetable', [
            'student_code' => 'missing-student',
            'student_label' => 'Missing Student',
            'timetable' => publishedTimetablePayload('D1'),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Der ausgewählte Schüler wurde nicht gefunden.');
});

function createPublishedTimetableAdminUser(): array
{
    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $school->id,
        'name' => 'Schuljahr 2026/27',
        'from' => '2026-09-07',
    ]);

    SchoolTool::factory()->create([
        'school_id' => $school->id,
        'active_schoolyear_id' => $schoolyear->id,
        'students_timetables_visible_admin' => true,
        'students_timetables_visible_user' => true,
        'students_timetables_user_test_mode' => false,
        'students_timetables_user_comming_soon' => false,
    ]);

    $licence = Licence::query()->create([
        'name' => 'StudentsTimetables',
        'long_name' => 'Tool zum Verwalten von Schülerstundenplänen',
        'price_per_year' => 200,
    ]);

    SchoolLicence::query()->create([
        'school_id' => $school->id,
        'licence_id' => $licence->id,
        'valid_until' => now()->addMonth()->toDateString(),
    ]);

    Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);
    $user->assignRole('admin');

    $student = Import116::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'class' => '4S',
        'last_name' => 'SCHROLL',
        'first_name' => 'Lukas',
        'student_code' => '100',
        'import_user_id' => $user->id,
    ]);

    return [$user, $student];
}

function publishedTimetablePayload(string $courseLabel): array
{
    return [
        'title' => 'Stundenplan',
        'schoolyear' => '2025/26',
        'student' => 'SCHROLL Lukas',
        'generated_at' => '11.06.2026, 23:40',
        'weekdays' => [
            ['label' => 'Mo'],
        ],
        'semesters' => [
            [
                'label' => 'Semester 6',
                'date_range' => '16.02.2026 - 10.07.2026',
                'weeks' => [
                    [
                        'label' => '',
                        'hours' => [
                            [
                                'hour' => 1,
                                'from' => '08:00',
                                'until' => '08:50',
                                'cells' => [
                                    [
                                        'status' => 'filled',
                                        'courses' => [
                                            [
                                                'label' => $courseLabel,
                                                'details' => '4S',
                                                'dates' => ['2026-02-16', '2026-03-02'],
                                                'is_fu' => true,
                                                'recurrence_label' => '2-wöchig',
                                                'recurrence_interval' => 2,
                                            ],
                                        ],
                                        'markers' => [],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

/**
 * @param  list<string>  $suffixes
 */
function fakePublishedTimetableNameSuffixes(array $suffixes): void
{
    Str::createRandomStringsUsing(function (int $length) use (&$suffixes): string {
        if ($length === 6) {
            return array_shift($suffixes) ?? 'zzz';
        }

        return str_repeat('x', $length);
    });
}
