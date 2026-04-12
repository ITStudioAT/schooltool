<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCurriculum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect([
        'admin',
        'teaching_admin',
        'teacher',
        'user',
    ])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $this->school = School::factory()->create([
        'short_name' => 'CURR',
        'long_name' => 'Curriculum Test School',
    ]);

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
        'teaching_visible_admin' => true,
        'teaching_visible_user' => true,
    ]);

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'teacher@curriculum.test',
    ]);
    $this->teacher->assignRole('teacher');

    $this->otherSchool = School::factory()->create();
    $this->otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->otherSchool->id,
    ]);

    SchoolTool::factory()->create([
        'school_id' => $this->otherSchool->id,
        'teaching_visible_admin' => true,
        'teaching_visible_user' => true,
    ]);

    $this->otherTeacher = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'email' => 'other-teacher@curriculum.test',
    ]);
    $this->otherTeacher->assignRole('teacher');
});

test('teacher can create a curriculum with free weeks and topics', function () {
    $response = $this->actingAs($this->teacher, 'sanctum')->postJson('/api/admin/teaching/curricula', [
        'title' => 'Deutsch 5A',
        'description' => 'Jahresplanung',
        'semester_count' => 2,
        'free_weeks' => ['2026-02-16', '2025-12-22'],
        'topics' => [
            [
                'id' => 'topic-all',
                'title' => 'Leseförderung',
                'assignment_type' => 'all_weeks',
                'units' => [
                    [
                        'id' => 'unit-all-1',
                        'title' => 'Lesetagebuch',
                        'assignment_type' => 'month',
                        'month_keys' => ['2025-09', '2025-10', '2025-09'],
                    ],
                ],
            ],
            [
                'id' => 'topic-month',
                'title' => 'Grammatikblock',
                'assignment_type' => 'month',
                'month_keys' => ['2025-10', '2025-11', '2025-10'],
                'units' => [
                    [
                        'id' => 'unit-month-1',
                        'title' => 'Wortarten',
                        'assignment_type' => 'weeks',
                        'week_keys' => ['2025-10-06', '2025-10-13'],
                    ],
                ],
            ],
            [
                'id' => 'topic-weeks',
                'title' => 'Projektarbeit',
                'assignment_type' => 'weeks',
                'week_keys' => ['2025-12-22', '2026-02-16'],
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Deutsch 5A')
        ->assertJsonPath('data.free_weeks.0', '2025-12-22')
        ->assertJsonPath('data.free_weeks.1', '2026-02-16')
        ->assertJsonPath('data.topics.0.id', 'topic-all')
        ->assertJsonPath('data.topics.0.units.0.id', 'unit-all-1')
        ->assertJsonPath('data.topics.0.units.0.month_key', '2025-09')
        ->assertJsonPath('data.topics.0.units.0.month_keys.1', '2025-10')
        ->assertJsonPath('data.topics.1.month_key', '2025-10')
        ->assertJsonPath('data.topics.1.month_keys.0', '2025-10')
        ->assertJsonPath('data.topics.1.month_keys.1', '2025-11')
        ->assertJsonPath('data.topics.1.units.0.week_keys.1', '2025-10-13')
        ->assertJsonPath('data.topics.2.week_keys.1', '2026-02-16');

    expect(TeachingCurriculum::query()->firstOrFail()->free_weeks)->toBe(['2025-12-22', '2026-02-16'])
        ->and(TeachingCurriculum::query()->firstOrFail()->topics)->toHaveCount(3)
        ->and(TeachingCurriculum::query()->firstOrFail()->topics[0]['units'])->toHaveCount(1);
});

test('teacher can update curriculum free weeks and duplicates are normalized', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Mathematik 2B',
        'description' => 'Planung',
        'semester_count' => 1,
        'free_weeks' => ['2026-04-13'],
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/curricula/{$curriculum->id}", [
        'title' => 'Mathematik 2B',
        'description' => 'Planung',
        'semester_count' => 1,
        'free_weeks' => ['2026-05-04', '2026-04-13', '2026-05-04'],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.free_weeks.0', '2026-04-13')
        ->assertJsonPath('data.free_weeks.1', '2026-05-04');

    expect($curriculum->fresh()->free_weeks)->toBe(['2026-04-13', '2026-05-04']);
});

test('curriculum free weeks must use monday date keys', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Biologie',
        'description' => null,
        'semester_count' => 2,
        'free_weeks' => [],
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/curricula/{$curriculum->id}", [
        'title' => 'Biologie',
        'description' => null,
        'semester_count' => 2,
        'free_weeks' => ['2026-04-15'],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['free_weeks.0']);
});

test('curriculum topics preserve existing assignments when omitted during update', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Geschichte',
        'description' => 'Vorher',
        'semester_count' => 2,
        'free_weeks' => ['2026-01-12'],
        'topics' => [
            [
                'id' => 'topic-existing',
                'title' => 'Antike',
                'assignment_type' => 'month',
                'month_key' => '2025-11',
                'month_keys' => ['2025-11', '2025-12'],
                'week_keys' => [],
                'units' => [
                    [
                        'id' => 'unit-existing',
                        'title' => 'Stadtstaaten',
                        'assignment_type' => 'weeks',
                        'month_key' => null,
                        'month_keys' => [],
                        'week_keys' => ['2026-01-12'],
                    ],
                ],
            ],
        ],
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->putJson("/api/admin/teaching/curricula/{$curriculum->id}", [
            'title' => 'Geschichte aktualisiert',
            'description' => 'Nachher',
            'semester_count' => 2,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.free_weeks.0', '2026-01-12')
        ->assertJsonPath('data.topics.0.id', 'topic-existing');

    expect($curriculum->fresh()->free_weeks)->toBe(['2026-01-12'])
        ->and($curriculum->fresh()->topics)->toHaveCount(1)
        ->and($curriculum->fresh()->topics[0]['id'])->toBe('topic-existing')
        ->and($curriculum->fresh()->topics[0]['title'])->toBe('Antike')
        ->and($curriculum->fresh()->topics[0]['assignment_type'])->toBe('month')
        ->and($curriculum->fresh()->topics[0]['month_key'])->toBe('2025-11')
        ->and($curriculum->fresh()->topics[0]['month_keys'])->toBe(['2025-11', '2025-12'])
        ->and($curriculum->fresh()->topics[0]['week_keys'])->toBe([])
        ->and($curriculum->fresh()->topics[0]['units'])->toHaveCount(1)
        ->and($curriculum->fresh()->topics[0]['units'][0]['id'])->toBe('unit-existing')
        ->and($curriculum->fresh()->topics[0]['units'][0]['week_keys'])->toBe(['2026-01-12']);
});

test('curriculum topics require valid week assignments', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Chemie',
        'description' => null,
        'semester_count' => 2,
        'free_weeks' => [],
        'topics' => [],
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/curricula/{$curriculum->id}", [
        'title' => 'Chemie',
        'description' => null,
        'semester_count' => 2,
        'topics' => [
            [
                'id' => 'topic-invalid',
                'title' => 'Versuchsreihe',
                'assignment_type' => 'weeks',
                'week_keys' => ['2026-04-15'],
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['topics.0.week_keys.0']);
});

test('curriculum topic units require valid week assignments', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Physik',
        'description' => null,
        'semester_count' => 2,
        'free_weeks' => [],
        'topics' => [],
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/curricula/{$curriculum->id}", [
        'title' => 'Physik',
        'description' => null,
        'semester_count' => 2,
        'topics' => [
            [
                'id' => 'topic-units',
                'title' => 'Elektrizität',
                'assignment_type' => 'none',
                'units' => [
                    [
                        'id' => 'unit-invalid',
                        'title' => 'Stromkreis',
                        'assignment_type' => 'weeks',
                        'week_keys' => ['2026-04-15'],
                    ],
                ],
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['topics.0.units.0.week_keys.0']);
});

test('curriculum topics normalize multiple month assignments', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Geografie',
        'description' => null,
        'semester_count' => 2,
        'free_weeks' => [],
        'topics' => [],
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/curricula/{$curriculum->id}", [
        'title' => 'Geografie',
        'description' => null,
        'semester_count' => 2,
        'topics' => [
            [
                'id' => 'topic-months',
                'title' => 'Klimazonen',
                'assignment_type' => 'month',
                'month_keys' => ['2025-11', '2025-09', '2025-11'],
            ],
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.topics.0.month_key', '2025-09')
        ->assertJsonPath('data.topics.0.month_keys.0', '2025-09')
        ->assertJsonPath('data.topics.0.month_keys.1', '2025-11');

    expect($curriculum->fresh()->topics[0]['month_key'])->toBe('2025-09')
        ->and($curriculum->fresh()->topics[0]['month_keys'])->toBe(['2025-09', '2025-11']);
});

test('curriculum topics can be saved without a date assignment', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Musik',
        'description' => null,
        'semester_count' => 2,
        'free_weeks' => [],
        'topics' => [],
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/curricula/{$curriculum->id}", [
        'title' => 'Musik',
        'description' => null,
        'semester_count' => 2,
        'topics' => [
            [
                'id' => 'topic-none',
                'title' => 'Hören',
                'assignment_type' => 'none',
            ],
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.topics.0.assignment_type', 'none')
        ->assertJsonPath('data.topics.0.month_key', null)
        ->assertJsonPath('data.topics.0.month_keys', [])
        ->assertJsonPath('data.topics.0.week_keys', []);

    expect($curriculum->fresh()->topics[0]['assignment_type'])->toBe('none')
        ->and($curriculum->fresh()->topics[0]['month_key'])->toBeNull()
        ->and($curriculum->fresh()->topics[0]['month_keys'])->toBe([])
        ->and($curriculum->fresh()->topics[0]['week_keys'])->toBe([]);
});

test('teacher cannot update curriculum from another school', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'user_id' => $this->otherTeacher->id,
        'title' => 'Fremdes Curriculum',
        'description' => null,
        'semester_count' => 2,
        'free_weeks' => ['2026-01-12'],
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->putJson("/api/admin/teaching/curricula/{$curriculum->id}", [
            'title' => 'Fremdes Curriculum',
            'description' => null,
            'semester_count' => 2,
            'free_weeks' => ['2026-02-09'],
        ])
        ->assertStatus(403);
});
