<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudentCategoryEvaluation;
use App\Models\TeachingCourseStudentEntry;
use App\Models\TeachingCourseWork;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingSchema;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect(['student', 'teacher', 'super_admin'])->each(function (string $role) {
        Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]);
    });

    $this->school = School::factory()->create();
    $teachingLicence = Licence::firstOrCreate(
        ['name' => 'Lehrertool'],
        ['long_name' => 'Lehrertool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($teachingLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
        'teaching_visible_user' => true,
    ]);

    $this->student = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Anna',
        'last_name' => 'Alpha',
        'schoolclass' => '1A',
    ]);
    $this->student->assignRole('student');

    $this->peer = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'first_name' => 'Berta',
        'last_name' => 'Beta',
        'schoolclass' => '1A',
    ]);
    $this->peer->assignRole('student');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teacher->assignRole('teacher');

    TeachingSchema::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'schema_id' => '10',
        'name' => 'Standard',
        'works' => [
            ['short_name' => 'TW', 'name' => 'Testarbeit'],
        ],
        'grading' => [],
    ]);

    $this->course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_schema_id' => '10',
        'students' => [
            ['id' => $this->student->id],
            ['id' => $this->peer->id],
        ],
    ]);
});

test('index returns entries with type labels and group members for enrolled student', function () {
    $work = TeachingCourseWork::query()->create([
        'teaching_course_id' => $this->course->id,
        'type' => 'TW',
        'title' => 'Kapitel 1',
        'description' => 'Gruppenarbeit',
        'is_group_work' => true,
        'group_size' => 2,
        'groups' => [
            [
                'student_ids' => [$this->student->id, $this->peer->id],
                'comments' => [
                    ['student_id' => $this->student->id, 'comment' => 'Meine Notiz'],
                ],
            ],
        ],
    ]);

    TeachingCourseStudentEntry::query()->create([
        'teaching_course_id' => $this->course->id,
        'user_id' => $this->student->id,
        'teaching_course_work_id' => $work->id,
        'date' => '2026-03-01',
        'type' => 'TW',
        'grade' => null,
        'status' => [],
        'source' => 'manual',
    ]);

    $response = $this->actingAs($this->student)
        ->getJson("/api/homepage/student/courses/{$this->course->id}/entries");

    $response->assertOk()
        ->assertJsonPath('type_labels.TW', 'Testarbeit')
        ->assertJsonPath('entries.0.type', 'TW')
        ->assertJsonPath('entries.0.category', 'Benotung')
        ->assertJsonPath('entries.0.is_required_entry', false)
        ->assertJsonPath('entries.0.comment', 'Meine Notiz')
        ->assertJsonPath('entries.0.work.is_group_work', true)
        ->assertJsonPath('entries.0.work.group_members.0', 'Berta Beta');
});

test('index returns 403 for non enrolled student', function () {
    $outsider = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $outsider->assignRole('student');

    $this->actingAs($outsider)
        ->getJson("/api/homepage/student/courses/{$this->course->id}/entries")
        ->assertStatus(403);
});

test('index classifies entry area definitions and respects behaviour visibility', function (bool $showBehaviour) {
    $this->schoolyear->update(['name' => '2026/27', 'concerns' => '2026/27']);
    $this->teacher->update(['teaching_show_behaviour' => $showBehaviour]);
    $area = TeachingEntryArea::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
    ]);
    $this->course->update(['teaching_entry_area_id' => $area->id]);
    $definitions = [
        ['short_name' => 'D', 'name' => 'Disziplinarbogen', 'category' => 'Verhalten'],
        ['short_name' => 'E', 'name' => 'Ermahnung', 'category' => 'Verhalten'],
        ['short_name' => 'FW', 'name' => 'Mahnung', 'category' => 'Weitere'],
        ['short_name' => 'LA', 'name' => 'Leistungsabfall', 'category' => 'Weitere'],
        ['short_name' => 'MA', 'name' => 'Mitarbeit', 'category' => 'Benotung'],
    ];

    foreach ($definitions as $definition) {
        TeachingEntryDefinition::factory()->create($definition + [
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'teaching_entry_area_id' => $area->id,
        ]);
        TeachingCourseStudentEntry::query()->create([
            'teaching_course_id' => $this->course->id,
            'user_id' => $this->student->id,
            'date' => '2026-09-05',
            'type' => $definition['short_name'],
            'grade' => null,
            'description' => $definition['name'],
            'status' => [],
            'source' => 'manual',
        ]);
    }

    TeachingSchema::query()->where('user_id', $this->teacher->id)->update([
        'works' => collect($definitions)->map(fn (array $definition): array => [
            'short_name' => $definition['short_name'],
            'name' => 'Legacy name',
            'default_grade' => '4',
            'grades' => [['grade' => '4', 'name' => 'Genügend', 'value' => '4']],
        ])->all(),
        'grading' => ['categories' => [[
            'name' => 'Legacy category',
            'weight' => 100,
            'require_all_entries' => true,
            'works' => collect($definitions)->map(fn (array $definition): array => [
                'short_name' => $definition['short_name'],
                'factor' => 20,
            ])->all(),
        ]]],
    ]);

    $response = $this->actingAs($this->student)
        ->getJson("/api/homepage/student/courses/{$this->course->id}/entries")
        ->assertOk()
        ->assertJsonCount($showBehaviour ? 5 : 3, 'entries');
    $entries = collect($response->json('entries'))->keyBy('type');

    foreach ($definitions as $definition) {
        $type = $definition['short_name'];
        if (! $showBehaviour && $definition['category'] === 'Verhalten') {
            expect($entries->has($type))->toBeFalse();
            $response->assertJsonMissingPath("type_labels.{$type}");

            continue;
        }

        expect($entries[$type]['category'])->toBe($definition['category']);
        $response->assertJsonPath("type_labels.{$type}", $definition['name']);
        if ($definition['category'] !== 'Benotung') {
            expect($entries[$type]['grade'])->toBeNull()
                ->and($entries[$type]['is_required_entry'])->toBeFalse();
        }
    }
})->with([true, false]);

test('index ignores definitions outside the course entry area scope', function (string $scope) {
    $this->schoolyear->update(['name' => '2026/27', 'concerns' => '2026/27']);
    $area = TeachingEntryArea::factory()->create([
        'school_id' => $scope === 'school' ? School::factory()->create()->id : $this->school->id,
        'schoolyear_id' => $scope === 'schoolyear' ? Schoolyear::factory()->create(['school_id' => $this->school->id])->id : $this->schoolyear->id,
        'user_id' => $scope === 'owner' ? $this->peer->id : $this->teacher->id,
    ]);
    TeachingEntryDefinition::factory()->create([
        'school_id' => $area->school_id,
        'schoolyear_id' => $area->schoolyear_id,
        'user_id' => $area->user_id,
        'teaching_entry_area_id' => $area->id,
        'short_name' => 'TW',
        'name' => 'Foreign definition',
        'category' => 'Verhalten',
    ]);
    if ($scope === 'legacy schoolyear') {
        $this->schoolyear->update(['name' => '2025/26', 'concerns' => '2025/26']);
    }
    $this->course->update(['teaching_entry_area_id' => $area->id]);
    TeachingCourseStudentEntry::query()->create([
        'teaching_course_id' => $this->course->id,
        'user_id' => $this->student->id,
        'type' => 'TW',
        'date' => '2026-03-01',
        'status' => [],
        'source' => 'manual',
    ]);

    $this->actingAs($this->student)
        ->getJson("/api/homepage/student/courses/{$this->course->id}/entries")
        ->assertOk()
        ->assertJsonPath('entries.0.category', 'Benotung')
        ->assertJsonPath('type_labels.TW', 'Testarbeit');
})->with(['owner', 'school', 'schoolyear', 'legacy schoolyear']);

test('index uses default grade from schema when entry grade is empty', function () {
    TeachingSchema::query()
        ->where('user_id', $this->teacher->id)
        ->where('schoolyear_id', $this->schoolyear->id)
        ->update([
            'works' => [
                [
                    'short_name' => 'TW',
                    'name' => 'Testarbeit',
                    'default_grade' => '4',
                    'grades' => [
                        ['grade' => '1', 'name' => 'Sehr gut', 'value' => '1'],
                        ['grade' => '2', 'name' => 'Gut', 'value' => '2'],
                        ['grade' => '3', 'name' => 'Befriedigend', 'value' => '3'],
                        ['grade' => '4', 'name' => 'Genügend', 'value' => '4'],
                        ['grade' => '5', 'name' => 'Nicht genügend', 'value' => '5'],
                    ],
                ],
            ],
        ]);

    TeachingCourseStudentEntry::query()->create([
        'teaching_course_id' => $this->course->id,
        'user_id' => $this->student->id,
        'date' => '2026-03-01',
        'type' => 'TW',
        'grade' => null,
        'status' => [],
        'source' => 'manual',
    ]);

    $response = $this->actingAs($this->student)
        ->getJson("/api/homepage/student/courses/{$this->course->id}/entries");

    $response->assertOk()
        ->assertJsonPath('entries.0.grade', '4');
});

test('index marks entry as required when type belongs to require-all category', function () {
    TeachingSchema::query()
        ->where('user_id', $this->teacher->id)
        ->where('schoolyear_id', $this->schoolyear->id)
        ->update([
            'works' => [
                ['short_name' => 'TW', 'name' => 'Testarbeit'],
            ],
            'grading' => [
                'categories' => [
                    [
                        'name' => 'Mitarbeit',
                        'weight' => 100,
                        'require_all_entries' => true,
                        'works' => [
                            ['short_name' => 'TW', 'factor' => 100],
                        ],
                    ],
                ],
            ],
        ]);

    TeachingCourseStudentEntry::query()->create([
        'teaching_course_id' => $this->course->id,
        'user_id' => $this->student->id,
        'date' => '2026-03-01',
        'type' => 'TW',
        'grade' => null,
        'status' => [],
        'source' => 'manual',
    ]);

    $response = $this->actingAs($this->student)
        ->getJson("/api/homepage/student/courses/{$this->course->id}/entries");

    $response->assertOk()
        ->assertJsonPath('entries.0.type', 'TW')
        ->assertJsonPath('entries.0.is_required_entry', true);
});

test('index returns category evaluations for the enrolled student', function () {
    TeachingSchema::query()
        ->where('user_id', $this->teacher->id)
        ->where('schoolyear_id', $this->schoolyear->id)
        ->update([
            'works' => [
                ['short_name' => 'TW', 'name' => 'Testarbeit'],
            ],
            'grading' => [
                'category_evaluation_values' => [
                    ['value' => 'Offen', 'color' => '#fb8c00'],
                    ['value' => 'Bestanden', 'color' => '#43a047'],
                ],
                'default_category_evaluation_value' => 'Offen',
                'categories' => [
                    [
                        'name' => 'Schriftlich',
                        'weight' => 100,
                        'category_evaluation_enabled' => true,
                        'works' => [
                            ['short_name' => 'TW', 'factor' => 100],
                        ],
                    ],
                ],
            ],
        ]);

    TeachingCourseStudentCategoryEvaluation::query()->create([
        'teaching_course_id' => $this->course->id,
        'user_id' => $this->student->id,
        'semester' => 2,
        'category_name' => 'Schriftlich',
        'value' => 'Bestanden',
    ]);

    TeachingCourseStudentCategoryEvaluation::query()->create([
        'teaching_course_id' => $this->course->id,
        'user_id' => $this->peer->id,
        'semester' => 2,
        'category_name' => 'Schriftlich',
        'value' => 'Offen',
    ]);

    $response = $this->actingAs($this->student)
        ->getJson("/api/homepage/student/courses/{$this->course->id}/entries");

    $response->assertOk()
        ->assertJsonPath('grading_categories.0.name', 'Schriftlich')
        ->assertJsonPath('grading_categories.0.category_evaluation_enabled', true)
        ->assertJsonPath('grading_categories.0.works.0', 'TW')
        ->assertJsonPath('category_evaluation_values.0.value', 'Offen')
        ->assertJsonPath('category_evaluation_values.0.color', '#fb8c00')
        ->assertJsonPath('category_evaluation_default_value', 'Offen')
        ->assertJsonPath('category_evaluations.0.semester', 2)
        ->assertJsonPath('category_evaluations.0.category_name', 'Schriftlich')
        ->assertJsonPath('category_evaluations.0.value', 'Bestanden')
        ->assertJsonCount(1, 'category_evaluations');
});

test('index returns 403 for non student role', function () {
    $this->actingAs($this->teacher)
        ->getJson("/api/homepage/student/courses/{$this->course->id}/entries")
        ->assertStatus(403);
});
