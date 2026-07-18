<?php

use App\Models\Licence;
use App\Models\MaterialCard;
use App\Models\MaterialCardAttachment;
use App\Models\MaterialCardClassification;
use App\Models\MaterialShareRule;
use App\Models\MaterialShareTarget;
use App\Models\MaterialSubject;
use App\Models\MaterialTopic;
use App\Models\MaterialUnit;
use App\Models\MaterialWorkspace;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCurriculum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
        'from' => '2026-09-01',
        'until' => '2027-07-31',
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
        'from' => '2026-09-01',
        'until' => '2027-07-31',
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

test('teacher can create a curriculum with themes and units only', function () {
    $response = $this->actingAs($this->teacher, 'sanctum')->postJson('/api/admin/teaching/curricula', [
        'title' => 'Deutsch 5A',
        'description' => 'Themenplan',
        'topics' => [
            [
                'id' => 'topic-reading',
                'title' => 'Leseförderung',
                'units' => [
                    [
                        'id' => 'unit-diary',
                        'title' => 'Lesetagebuch',
                        'is_exam' => true,
                    ],
                ],
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Deutsch 5A')
        ->assertJsonPath('data.topics.0.id', 'topic-reading')
        ->assertJsonPath('data.topics.0.units.0.id', 'unit-diary')
        ->assertJsonPath('data.topics.0.units.0.is_exam', true)
        ->assertJsonMissingPath('data.semester_count')
        ->assertJsonMissingPath('data.free_weeks')
        ->assertJsonMissingPath('data.topics.0.assignment_type')
        ->assertJsonMissingPath('data.topics.0.units.0.week_keys');

    $curriculum = TeachingCurriculum::query()->firstOrFail();

    expect($curriculum->topics)->toHaveCount(1)
        ->and($curriculum->topics[0])->not->toHaveKeys(['assignment_type', 'month_key', 'month_keys', 'week_keys'])
        ->and($curriculum->topics[0]['units'][0])->not->toHaveKeys(['assignment_type', 'month_key', 'month_keys', 'week_keys', 'checked_week_keys'])
        ->and($curriculum->topics[0]['units'][0]['is_exam'])->toBeTrue();
});

test('curriculum free-week settings are removed', function () {
    expect(Schema::hasColumn('teaching_curricula', 'free_weeks'))->toBeFalse()
        ->and(Schema::hasColumn('teaching_imported_curricula', 'free_weeks'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'teaching_curriculum_free_weeks_template'))->toBeFalse();

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson('/api/admin/teaching/curricula/free-weeks-template')
        ->assertNotFound();

    $this->actingAs($this->teacher, 'sanctum')
        ->putJson('/api/admin/teaching/curricula/free-weeks-template')
        ->assertNotFound();
});

test('teacher can save and load a curriculum free weeks template across schoolyears', function () {
    $response = $this->actingAs($this->teacher, 'sanctum')->putJson('/api/admin/teaching/curricula/free-weeks-template', [
        'free_weeks_template' => [
            'week_keys' => ['2025-01-13', '2024-09-09'],
            'named_ranges' => [
                [
                    'title' => 'Weihnachtsferien',
                    'start_week_key' => '2025-01-13',
                    'end_week_key' => '2025-01-13',
                ],
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.week_keys.0', '2026-09-07')
        ->assertJsonPath('data.week_keys.1', '2027-01-18')
        ->assertJsonPath('data.named_ranges.0.title', 'Weihnachtsferien')
        ->assertJsonPath('data.named_ranges.0.start_week_key', '2027-01-18')
        ->assertJsonPath('data.named_ranges.0.end_week_key', '2027-01-18');

    expect($this->teacher->fresh()->teaching_curriculum_free_weeks_template)->toEqual([
        'week_keys' => ['2024-09-09', '2025-01-13'],
        'named_ranges' => [
            [
                'title' => 'Weihnachtsferien',
                'start_week_key' => '2025-01-13',
                'end_week_key' => '2025-01-13',
            ],
        ],
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson('/api/admin/teaching/curricula/free-weeks-template')
        ->assertOk()
        ->assertJson([
            'data' => [
                'week_keys' => ['2026-09-07', '2027-01-18'],
                'named_ranges' => [
                    [
                        'title' => 'Weihnachtsferien',
                        'start_week_key' => '2027-01-18',
                        'end_week_key' => '2027-01-18',
                    ],
                ],
            ],
        ]);
})->skip('Curriculum scheduling was removed.');

test('teacher creates new curriculum with inherited free weeks template when none are provided', function () {
    $this->teacher->update([
        'teaching_curriculum_free_weeks_template' => [
            'week_keys' => ['2024-09-09', '2025-01-13'],
            'named_ranges' => [
                [
                    'title' => 'Weihnachtsferien',
                    'start_week_key' => '2025-01-13',
                    'end_week_key' => '2025-01-13',
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')->postJson('/api/admin/teaching/curricula', [
        'title' => 'Englisch 1A',
        'description' => 'Neue Planung',
        'semester_count' => 2,
        'topics' => [],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.free_weeks.0', '2026-09-07')
        ->assertJsonPath('data.free_weeks.1', '2027-01-18');

    expect(TeachingCurriculum::query()->firstOrFail()->free_weeks)->toBe(['2026-09-07', '2027-01-18']);
})->skip('Curriculum scheduling was removed.');

test('teacher can update curriculum free weeks and duplicates are normalized', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Mathematik 2B',
        'description' => 'Planung',
        'semester_count' => 1,
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/curricula/{$curriculum->id}", [
        'title' => 'Mathematik 2B',
        'description' => 'Planung',
        'semester_count' => 1,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.free_weeks.0', '2026-04-13')
        ->assertJsonPath('data.free_weeks.1', '2026-05-04');

    expect($curriculum->fresh()->free_weeks)->toBe(['2026-04-13', '2026-05-04']);
})->skip('Curriculum scheduling was removed.');

test('curriculum free weeks must use monday date keys', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Biologie',
        'description' => null,
        'semester_count' => 2,
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/curricula/{$curriculum->id}", [
        'title' => 'Biologie',
        'description' => null,
        'semester_count' => 2,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['free_weeks.0']);
})->skip('Curriculum scheduling was removed.');

test('curriculum topics preserve existing assignments when omitted during update', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Geschichte',
        'description' => 'Vorher',
        'semester_count' => 2,
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
})->skip('Curriculum scheduling was removed.');

test('curriculum topics require valid week assignments', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Chemie',
        'description' => null,
        'semester_count' => 2,
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
})->skip('Curriculum scheduling was removed.');

test('curriculum topic units require valid week assignments', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Physik',
        'description' => null,
        'semester_count' => 2,
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
})->skip('Curriculum scheduling was removed.');

test('unit date assignments remove overlapping topic dates', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Deutsch',
        'description' => null,
        'semester_count' => 2,
        'topics' => [
            [
                'id' => 'topic-overlap',
                'title' => 'Schreiben',
                'assignment_type' => 'month',
                'month_key' => '2025-09',
                'month_keys' => ['2025-09', '2025-10'],
                'week_keys' => [],
                'units' => [
                    [
                        'id' => 'unit-overlap',
                        'title' => 'Bericht',
                        'assignment_type' => 'none',
                        'month_key' => null,
                        'month_keys' => [],
                        'week_keys' => [],
                    ],
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/curricula/{$curriculum->id}", [
        'title' => 'Deutsch',
        'description' => null,
        'semester_count' => 2,
        'topics' => [
            [
                'id' => 'topic-overlap',
                'title' => 'Schreiben',
                'assignment_type' => 'month',
                'month_keys' => ['2025-09', '2025-10'],
                'units' => [
                    [
                        'id' => 'unit-overlap',
                        'title' => 'Bericht',
                        'assignment_type' => 'weeks',
                        'week_keys' => ['2025-09-08'],
                    ],
                ],
            ],
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.topics.0.month_key', '2025-10')
        ->assertJsonPath('data.topics.0.month_keys.0', '2025-10')
        ->assertJsonPath('data.topics.0.units.0.week_keys.0', '2025-09-08');

    expect($curriculum->fresh()->topics[0]['month_keys'])->toBe(['2025-10'])
        ->and($curriculum->fresh()->topics[0]['units'][0]['week_keys'])->toBe(['2025-09-08']);
})->skip('Curriculum scheduling was removed.');

test('topic date assignments remove overlapping unit dates', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Sachunterricht',
        'description' => null,
        'semester_count' => 2,
        'topics' => [
            [
                'id' => 'topic-wins',
                'title' => 'Natur',
                'assignment_type' => 'none',
                'month_key' => null,
                'month_keys' => [],
                'week_keys' => [],
                'units' => [
                    [
                        'id' => 'unit-month',
                        'title' => 'Bäume',
                        'assignment_type' => 'month',
                        'month_key' => '2025-09',
                        'month_keys' => ['2025-09'],
                        'week_keys' => [],
                    ],
                    [
                        'id' => 'unit-week',
                        'title' => 'Blätter',
                        'assignment_type' => 'weeks',
                        'month_key' => null,
                        'month_keys' => [],
                        'week_keys' => ['2025-10-06'],
                    ],
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/curricula/{$curriculum->id}", [
        'title' => 'Sachunterricht',
        'description' => null,
        'semester_count' => 2,
        'topics' => [
            [
                'id' => 'topic-wins',
                'title' => 'Natur',
                'assignment_type' => 'month',
                'month_keys' => ['2025-09', '2025-10'],
                'units' => [
                    [
                        'id' => 'unit-month',
                        'title' => 'Bäume',
                        'assignment_type' => 'month',
                        'month_keys' => ['2025-09'],
                    ],
                    [
                        'id' => 'unit-week',
                        'title' => 'Blätter',
                        'assignment_type' => 'weeks',
                        'week_keys' => ['2025-10-06'],
                    ],
                ],
            ],
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.topics.0.month_keys.0', '2025-09')
        ->assertJsonPath('data.topics.0.month_keys.1', '2025-10')
        ->assertJsonPath('data.topics.0.units.0.assignment_type', 'none')
        ->assertJsonPath('data.topics.0.units.1.assignment_type', 'none');

    expect($curriculum->fresh()->topics[0]['units'][0]['assignment_type'])->toBe('none')
        ->and($curriculum->fresh()->topics[0]['units'][0]['month_keys'])->toBe([])
        ->and($curriculum->fresh()->topics[0]['units'][1]['assignment_type'])->toBe('none')
        ->and($curriculum->fresh()->topics[0]['units'][1]['week_keys'])->toBe([]);
})->skip('Curriculum scheduling was removed.');

test('curriculum topics normalize multiple month assignments', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Geografie',
        'description' => null,
        'semester_count' => 2,
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
})->skip('Curriculum scheduling was removed.');

test('curriculum month assignments preserve week counts for selected months', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Informatik',
        'description' => null,
        'semester_count' => 2,
        'topics' => [],
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/curricula/{$curriculum->id}", [
        'title' => 'Informatik',
        'description' => null,
        'semester_count' => 2,
        'topics' => [
            [
                'id' => 'topic-basics',
                'title' => 'Grundlagen',
                'assignment_type' => 'month',
                'month_keys' => ['2025-09', '2025-11'],
                'month_week_counts' => [
                    '2025-10' => 3,
                    '2025-11' => 0,
                ],
                'units' => [
                    [
                        'id' => 'unit-login',
                        'title' => 'Am System anmelden',
                        'assignment_type' => 'month',
                        'month_keys' => ['2025-12'],
                        'month_week_counts' => [
                            '2025-12' => 4,
                            '2025-10' => 3,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $response->assertSuccessful();

    expect($response->json('data.topics.0.month_week_counts'))->toBe([
        '2025-09' => 1,
        '2025-11' => 0,
    ])->and($response->json('data.topics.0.units.0.month_week_counts'))->toBe([
        '2025-12' => 4,
    ])->and($curriculum->fresh()->topics[0]['month_week_counts'])->toBe([
        '2025-09' => 1,
        '2025-11' => 0,
    ])->and($curriculum->fresh()->topics[0]['units'][0]['month_week_counts'])->toBe([
        '2025-12' => 4,
    ]);
})->skip('Curriculum scheduling was removed.');

test('curriculum topics can be saved without a date assignment', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Musik',
        'description' => null,
        'semester_count' => 2,
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
})->skip('Curriculum scheduling was removed.');

test('unit checked weeks are limited to inherited topic weeks', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Informatik',
        'description' => null,
        'semester_count' => 2,
        'topics' => [],
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/curricula/{$curriculum->id}", [
        'title' => 'Informatik',
        'description' => null,
        'semester_count' => 2,
        'topics' => [
            [
                'id' => 'topic-office',
                'title' => 'Office',
                'assignment_type' => 'weeks',
                'week_keys' => ['2025-09-08', '2025-09-15'],
                'units' => [
                    [
                        'id' => 'unit-word',
                        'title' => 'Word',
                        'assignment_type' => 'none',
                        'checked_week_keys' => ['2025-09-08', '2025-09-22'],
                    ],
                ],
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.topics.0.week_keys.0', '2025-09-08')
        ->assertJsonPath('data.topics.0.units.0.checked_week_keys.0', '2025-09-08');

    expect($curriculum->fresh()->topics[0]['units'][0]['checked_week_keys'])->toBe(['2025-09-08']);
})->skip('Curriculum scheduling was removed.');

test('teacher can save materials on curriculum units', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Biologie',
        'description' => null,
        'semester_count' => 2,
        'topics' => [],
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/curricula/{$curriculum->id}", [
        'title' => 'Biologie',
        'description' => null,
        'semester_count' => 2,
        'topics' => [
            [
                'id' => 'topic-zelle',
                'title' => 'Zelle',
                'assignment_type' => 'none',
                'units' => [
                    [
                        'id' => 'unit-mikroskop',
                        'title' => 'Mikroskopieren',
                        'assignment_type' => 'none',
                        'materials' => [
                            [
                                'id' => 202,
                                'title' => 'Mikroskop-Protokoll',
                                'subject' => 'Biologie',
                                'topic' => 'Zelle',
                                'unit' => 'Mikroskopieren',
                                'type' => 'Vorlage',
                                'status' => 'in_progress',
                                'attachments_count' => 1,
                                'is_shared_material' => true,
                                'shared_rule_id' => 78,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.topics.0.units.0.materials.0.id', 202)
        ->assertJsonPath('data.topics.0.units.0.materials.0.unit', 'Mikroskopieren');

    expect($curriculum->fresh()->topics[0])->not->toHaveKey('materials')
        ->and($curriculum->fresh()->topics[0]['units'][0]['materials'][0])->toMatchArray([
            'id' => 202,
            'title' => 'Mikroskop-Protokoll',
            'subject' => 'Biologie',
            'topic' => 'Zelle',
            'unit' => 'Mikroskopieren',
            'type' => 'Vorlage',
            'status' => 'in_progress',
            'attachments_count' => 1,
            'is_shared_material' => true,
            'shared_rule_id' => 78,
        ]);
});

test('teacher cannot save materials on curriculum topics', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Biologie',
        'description' => null,
        'semester_count' => 2,
        'topics' => [],
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')->putJson("/api/admin/teaching/curricula/{$curriculum->id}", [
        'title' => 'Biologie',
        'description' => null,
        'semester_count' => 2,
        'topics' => [
            [
                'id' => 'topic-zelle',
                'title' => 'Zelle',
                'materials' => [
                    [
                        'id' => 101,
                        'title' => 'Zellaufbau Arbeitsblatt',
                    ],
                ],
                'units' => [],
            ],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('topics.0.materials');

    expect($curriculum->fresh()->topics)->toBe([]);
});

test('teacher can browse materials through curriculum endpoints', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Deutsch',
        'description' => null,
        'semester_count' => 2,
        'topics' => [],
    ]);

    $workspace = MaterialWorkspace::query()->create([
        'user_id' => $this->teacher->id,
        'name' => 'Workspace',
        'is_default' => true,
    ]);

    $subject = MaterialSubject::query()->create([
        'user_id' => $this->teacher->id,
        'workspace_id' => $workspace->id,
        'name' => 'Deutsch',
    ]);

    $topic = MaterialTopic::query()->create([
        'subject_id' => $subject->id,
        'name' => 'Grammatik',
    ]);

    $unit = MaterialUnit::query()->create([
        'topic_id' => $topic->id,
        'name' => 'Nebensätze',
    ]);

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'workspace_id' => $workspace->id,
        'title' => 'Nebensätze Arbeitsblatt',
        'subject' => 'Deutsch',
        'area' => 'Grammatik',
        'unit' => 'Nebensätze',
        'type' => 'Arbeitsblatt',
        'status' => 'done',
    ]);

    MaterialCardClassification::query()->create([
        'material_card_id' => $card->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'unit_id' => $unit->id,
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson("/api/admin/teaching/curricula/{$curriculum->id}/materials/config")
        ->assertOk()
        ->assertJsonPath('workspace.name', 'Workspace')
        ->assertJsonPath('classification_tree.0.name', 'Deutsch')
        ->assertJsonPath('classification_tree.0.topics.0.name', 'Grammatik')
        ->assertJsonPath('classification_tree.0.topics.0.units.0.name', 'Nebensätze');

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson("/api/admin/teaching/curricula/{$curriculum->id}/materials/cards?search=Nebensätze&subject=Deutsch&topic=Grammatik&unit=Nebensätze")
        ->assertOk()
        ->assertJsonPath('data.0.id', $card->id)
        ->assertJsonPath('data.0.title', 'Nebensätze Arbeitsblatt')
        ->assertJsonPath('data.0.subject', 'Deutsch')
        ->assertJsonPath('data.0.area', 'Grammatik')
        ->assertJsonPath('data.0.unit', 'Nebensätze');
});

test('teacher can preview a material attachment through curriculum route', function () {
    Storage::fake('local');

    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Informatik',
        'description' => null,
        'semester_count' => 2,
        'topics' => [],
    ]);

    $workspace = MaterialWorkspace::query()->create([
        'user_id' => $this->teacher->id,
        'name' => 'Workspace',
        'is_default' => true,
    ]);

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'workspace_id' => $workspace->id,
        'title' => 'Word Handout',
    ]);

    $filePath = 'materials/previews/word-handout.html';
    Storage::disk('local')->put($filePath, '<html><body><h1>Word Handout</h1></body></html>');

    $attachment = MaterialCardAttachment::factory()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'Word Handout.html',
        'file_path' => $filePath,
        'mime_type' => 'text/html',
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->get("/api/admin/teaching/curricula/{$curriculum->id}/materials/attachments/{$attachment->id}/preview")
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8');
});

test('teacher can load a curriculum material card with attachments', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Informatik',
        'description' => null,
        'semester_count' => 2,
        'topics' => [],
    ]);

    $workspace = MaterialWorkspace::query()->create([
        'user_id' => $this->teacher->id,
        'name' => 'Workspace',
        'is_default' => true,
    ]);

    $card = MaterialCard::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $this->teacher->id,
        'workspace_id' => $workspace->id,
        'title' => 'Word Handout',
        'subject' => 'Informatik',
        'area' => 'Textverarbeitung',
        'unit' => 'Einführung',
    ]);

    $attachment = MaterialCardAttachment::factory()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'Word Handout.pdf',
        'file_path' => 'materials/previews/word-handout.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson("/api/admin/teaching/curricula/{$curriculum->id}/materials/cards/{$card->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $card->id)
        ->assertJsonPath('data.title', 'Word Handout')
        ->assertJsonPath('data.attachments.0.id', $attachment->id)
        ->assertJsonPath('data.attachments.0.name', 'Word Handout.pdf');
});

test('teacher can browse hopper account materials through curriculum endpoints', function () {
    $this->otherSchool->forceFill([
        'long_name' => 'Hopper Curriculum School',
    ])->save();

    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Musik',
        'description' => null,
        'semester_count' => 2,
        'topics' => [],
    ]);

    $workspace = MaterialWorkspace::query()->create([
        'user_id' => $this->otherTeacher->id,
        'name' => 'Hopper Workspace',
        'is_default' => true,
    ]);

    $card = MaterialCard::factory()->create([
        'school_id' => $this->otherSchool->id,
        'user_id' => $this->otherTeacher->id,
        'workspace_id' => $workspace->id,
        'title' => 'Rhythmus Arbeitsblatt',
        'subject' => 'Musik',
        'area' => 'Rhythmus',
        'unit' => 'Taktarten',
        'type' => 'Arbeitsblatt',
        'status' => MaterialCard::STATUS_DONE,
    ]);

    $this->teacher->forceFill([
        'hopper_account_ids' => [$this->otherTeacher->id],
    ])->save();

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson("/api/admin/teaching/curricula/{$curriculum->id}/materials/cards?search=Rhythmus")
        ->assertOk()
        ->assertJsonPath('data.0.id', $card->id)
        ->assertJsonPath('data.0.title', 'Rhythmus Arbeitsblatt')
        ->assertJsonPath('data.0.source_school_label', $this->otherSchool->long_name)
        ->assertJsonPath('data.0.is_hopper_material', true);

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson("/api/admin/teaching/curricula/{$curriculum->id}/materials/cards/{$card->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $card->id)
        ->assertJsonPath('data.is_hopper_material', true);

    $this->teacher->forceFill([
        'hopper_account_ids' => [],
    ])->save();

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson("/api/admin/teaching/curricula/{$curriculum->id}/materials/cards/{$card->id}")
        ->assertForbidden();
});

test('teacher sees a preview message when a hopper material attachment file is missing', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Informatik',
        'description' => null,
        'semester_count' => 2,
        'topics' => [],
    ]);

    $workspace = MaterialWorkspace::query()->create([
        'user_id' => $this->otherTeacher->id,
        'name' => 'Hopper Workspace',
        'is_default' => true,
    ]);

    $card = MaterialCard::factory()->create([
        'school_id' => $this->otherSchool->id,
        'user_id' => $this->otherTeacher->id,
        'workspace_id' => $workspace->id,
        'title' => 'Fehlender Anhang',
    ]);

    $attachment = MaterialCardAttachment::factory()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'Fehlender Anhang.pdf',
        'file_path' => 'materials/tests/missing-hopper-preview.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $this->teacher->forceFill([
        'hopper_account_ids' => [$this->otherTeacher->id],
    ])->save();

    $this->actingAs($this->teacher, 'sanctum')
        ->get("/api/admin/teaching/curricula/{$curriculum->id}/materials/attachments/{$attachment->id}/preview")
        ->assertOk()
        ->assertSee('Die Datei wurde im Speicher nicht gefunden', false);
});

test('teacher can preview a hopper material attachment stored on a configured disk', function () {
    Storage::fake('s3');

    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Informatik',
        'description' => null,
        'semester_count' => 2,
        'topics' => [],
    ]);

    $workspace = MaterialWorkspace::query()->create([
        'user_id' => $this->otherTeacher->id,
        'name' => 'Hopper Workspace',
        'is_default' => true,
    ]);

    $card = MaterialCard::factory()->create([
        'school_id' => $this->otherSchool->id,
        'user_id' => $this->otherTeacher->id,
        'workspace_id' => $workspace->id,
        'title' => 'Rechnen mit Prozenten',
    ]);

    $filePath = 'materials/tests/hopper-configured-disk.txt';
    Storage::disk('s3')->put($filePath, 'Prozentrechnung Vorschau');

    $attachment = MaterialCardAttachment::factory()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'Infoblatt_Excel_Prozentrechnung.txt',
        'file_path' => $filePath,
        'mime_type' => 'text/plain',
    ]);

    $this->teacher->forceFill([
        'hopper_account_ids' => [$this->otherTeacher->id],
    ])->save();

    $this->actingAs($this->teacher, 'sanctum')
        ->get("/api/admin/teaching/curricula/{$curriculum->id}/materials/attachments/{$attachment->id}/preview")
        ->assertOk()
        ->assertSee('Prozentrechnung Vorschau', false);
});

test('teacher can use shared materials through curriculum material endpoints', function () {
    Storage::fake('local');

    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Informatik',
        'description' => null,
        'semester_count' => 2,
        'topics' => [],
    ]);

    $workspace = MaterialWorkspace::query()->create([
        'user_id' => $this->otherTeacher->id,
        'name' => 'Shared Workspace',
        'is_default' => true,
    ]);

    $card = MaterialCard::factory()->create([
        'school_id' => $this->otherSchool->id,
        'user_id' => $this->otherTeacher->id,
        'workspace_id' => $workspace->id,
        'title' => 'Geteiltes Prozent Material',
        'subject' => 'Informatik',
        'area' => 'Excel',
        'unit' => 'Prozentrechnungen',
    ]);

    $filePath = 'materials/tests/shared-curriculum-material.txt';
    Storage::disk('local')->put($filePath, 'Geteilte Prozent Vorschau');

    $attachment = MaterialCardAttachment::factory()->create([
        'material_card_id' => $card->id,
        'attachment_type' => MaterialCardAttachment::TYPE_FILE,
        'name' => 'Geteiltes Material.txt',
        'file_path' => $filePath,
        'mime_type' => 'text/plain',
    ]);

    $rule = MaterialShareRule::query()->create([
        'school_id' => $this->otherSchool->id,
        'workspace_id' => $workspace->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'created_by_user_id' => $this->otherTeacher->id,
        'is_active' => true,
    ]);

    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $rule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->teacher->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson("/api/admin/teaching/curricula/{$curriculum->id}/materials/cards?shared_only=1&search=Prozent")
        ->assertOk()
        ->assertJsonPath('data.0.id', $card->id)
        ->assertJsonPath('data.0.is_shared_material', true)
        ->assertJsonPath('data.0.shared_rule_id', $rule->id)
        ->assertJsonPath('data.0.attachments.0.id', $attachment->id);

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson("/api/admin/teaching/curricula/{$curriculum->id}/materials/cards/{$card->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $card->id)
        ->assertJsonPath('data.is_shared_material', true);

    $this->actingAs($this->teacher, 'sanctum')
        ->get("/api/admin/teaching/curricula/{$curriculum->id}/materials/attachments/{$attachment->id}/preview")
        ->assertOk()
        ->assertSee('Geteilte Prozent Vorschau', false);
});

test('teacher can filter shared curriculum materials by source user', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'title' => 'Mathematik',
        'description' => null,
        'semester_count' => 2,
        'topics' => [],
    ]);

    $firstWorkspace = MaterialWorkspace::query()->create([
        'user_id' => $this->otherTeacher->id,
        'name' => 'First Shared Workspace',
        'is_default' => true,
    ]);

    $firstSubject = MaterialSubject::query()->create([
        'user_id' => $this->otherTeacher->id,
        'workspace_id' => $firstWorkspace->id,
        'name' => 'Mathematik',
    ]);

    $firstCard = MaterialCard::factory()->create([
        'school_id' => $this->otherSchool->id,
        'user_id' => $this->otherTeacher->id,
        'workspace_id' => $firstWorkspace->id,
        'title' => 'Sokrates Abenddaten Stundenplan',
    ]);

    MaterialCardClassification::query()->create([
        'material_card_id' => $firstCard->id,
        'subject_id' => $firstSubject->id,
    ]);

    $firstRule = MaterialShareRule::query()->create([
        'school_id' => $this->otherSchool->id,
        'workspace_id' => $firstWorkspace->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'created_by_user_id' => $this->otherTeacher->id,
        'is_active' => true,
    ]);

    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $firstRule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->teacher->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $secondTeacher = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'email' => 'second-source@curriculum.test',
    ]);
    $secondTeacher->assignRole('teacher');

    $secondWorkspace = MaterialWorkspace::query()->create([
        'user_id' => $secondTeacher->id,
        'name' => 'Second Shared Workspace',
        'is_default' => true,
    ]);

    $secondSubject = MaterialSubject::query()->create([
        'user_id' => $secondTeacher->id,
        'workspace_id' => $secondWorkspace->id,
        'name' => 'Mathematik',
    ]);

    $secondCard = MaterialCard::factory()->create([
        'school_id' => $this->otherSchool->id,
        'user_id' => $secondTeacher->id,
        'workspace_id' => $secondWorkspace->id,
        'title' => 'Sokrates Abenddaten Stundenplan Alternative',
    ]);

    MaterialCardClassification::query()->create([
        'material_card_id' => $secondCard->id,
        'subject_id' => $secondSubject->id,
    ]);

    $secondRule = MaterialShareRule::query()->create([
        'school_id' => $this->otherSchool->id,
        'workspace_id' => $secondWorkspace->id,
        'scope_type' => MaterialShareRule::SCOPE_ALL,
        'scope_id' => null,
        'created_by_user_id' => $secondTeacher->id,
        'is_active' => true,
    ]);

    MaterialShareTarget::query()->create([
        'material_share_rule_id' => $secondRule->id,
        'target_type' => MaterialShareTarget::TARGET_USER,
        'user_id' => $this->teacher->id,
        'permission' => MaterialShareTarget::PERMISSION_READ_ONLY,
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson("/api/admin/teaching/curricula/{$curriculum->id}/materials/cards?shared_only=1&subject=Mathematik&source_user_id={$this->otherTeacher->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $firstCard->id)
        ->assertJsonPath('data.0.source_user_id', $this->otherTeacher->id);
});

test('teacher cannot update curriculum from another school', function () {
    $curriculum = TeachingCurriculum::query()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'user_id' => $this->otherTeacher->id,
        'title' => 'Fremdes Curriculum',
        'description' => null,
        'semester_count' => 2,
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->putJson("/api/admin/teaching/curricula/{$curriculum->id}", [
            'title' => 'Fremdes Curriculum',
            'description' => null,
            'semester_count' => 2,
        ])
        ->assertStatus(403);
});
