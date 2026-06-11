<?php

use App\Models\Import116;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\StudentTimetablePublishedTimetable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('publishes a manual timetable for exactly one selected student', function () {
    [$user, $student] = createPublishedTimetableAdminUser();

    $payload = [
        'student_code' => $student->student_code,
        'student_label' => 'SCHROLL Lukas',
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
        ->assertJsonPath('data.student_label', 'SCHROLL Lukas');

    $publishedTimetable = StudentTimetablePublishedTimetable::query()->firstOrFail();

    expect($publishedTimetable->school_id)->toBe($user->school_id)
        ->and($publishedTimetable->schoolyear_id)->toBe($user->schoolyear_id)
        ->and($publishedTimetable->published_by_user_id)->toBe($user->id)
        ->and($publishedTimetable->student_code)->toBe($student->student_code)
        ->and($publishedTimetable->student_label)->toBe('SCHROLL Lukas')
        ->and($publishedTimetable->timetable['semesters'][0]['weeks'][0]['hours'][0]['cells'][0]['courses'][0]['label'])
        ->toBe('LPT1')
        ->and($publishedTimetable->state['activeCourseGroupFilterKeys'])
        ->toBe(['d-1']);
});

it('replaces an existing published timetable for the same student', function () {
    [$user, $student] = createPublishedTimetableAdminUser();

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

    expect($publishedTimetable->timetable['semesters'][0]['weeks'][0]['hours'][0]['cells'][0]['courses'][0]['label'])
        ->toBe('D2')
        ->and($publishedTimetable->state['activeCourseGroupFilterKeys'])
        ->toBe(['d2']);
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
                                                'dates' => ['2026-02-16'],
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
