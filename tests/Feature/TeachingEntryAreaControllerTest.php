<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingEntryArea;
use App\Models\TeachingEntryDefinition;
use App\Models\TeachingEntryGradingPart;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use Spatie\Permission\Models\Role;

trait RefreshTeachingEntryAreaDatabase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        if (config('database.default') !== 'sqlite') {
            return [];
        }

        return [
            '--realpath' => true,
            '--path' => array_values(array_filter(
                glob(database_path('migrations/*.php')),
                fn (string $path): bool => basename($path) !== '2026_07_27_154239_enforce_restaurant_booking_slot_uniqueness.php',
            )),
        ];
    }

    public function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('DATE_FORMAT', fn (?string $date, string $format): ?string => $date === null
            ? null
            : date(strtr($format, ['%Y' => 'Y', '%m' => 'm', '%d' => 'd']), strtotime($date)), 2);
        $pdo->sqliteCreateFunction('CONCAT_WS', fn (string $separator, mixed ...$values): string => implode($separator, array_filter($values, fn (mixed $value): bool => $value !== null)));
        $pdo->sqliteCreateFunction('SHA2', fn (?string $value, int $bits): ?string => $value === null ? null : hash('sha'.$bits, $value), 2);
        $pdo->sqliteCreateFunction('NOW', fn (): string => date('Y-m-d H:i:s'), 0);
        DB::connection()->setSchemaGrammar(new class(DB::connection()) extends SQLiteGrammar
        {
            public function compileFulltext(Blueprint $blueprint, Fluent $command): string
            {
                return $this->compileIndex($blueprint, $command);
            }

            public function compileDropFullText(Blueprint $blueprint, Fluent $command): string
            {
                return $this->compileDropIndex($blueprint, $command);
            }
        });
    }
}

uses(RefreshTeachingEntryAreaDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
        'teaching_visible_admin' => true,
    ]);
    $licence = Licence::firstOrCreate(['name' => 'Lehrertool'], ['long_name' => 'Lehrertool', 'is_selectable' => true]);
    $this->school->licences()->attach($licence->id, ['valid_until' => now()->addYear()->toDateString()]);
    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teacher->assignRole('teacher');
    $this->otherTeacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->otherTeacher->assignRole('teacher');
});

function teachingEntryAreaFor(User $user, Schoolyear $schoolyear, string $name): TeachingEntryArea
{
    return TeachingEntryArea::query()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'user_id' => $user->id,
        'name' => $name,
    ]);
}

test('areas require authentication and a teaching role', function () {
    $this->getJson('/api/admin/teaching/entry_areas')->assertUnauthorized();
    $this->postJson('/api/admin/teaching/entry-area-imports')->assertUnauthorized();

    $user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $user->assignRole('user');
    $this->actingAs($user, 'sanctum')->getJson('/api/admin/teaching/entry_areas')->assertForbidden();
    $this->postJson('/api/admin/teaching/entry-area-imports')->assertForbidden();
});

test('index returns only owned areas with entry counts', function () {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Unterstufe');
    teachingEntryAreaFor($this->otherTeacher, $this->schoolyear, 'Fremd');
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $area->id,
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson('/api/admin/teaching/entry_areas')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Unterstufe')
        ->assertJsonPath('data.0.entry_count', 1);
});

test('index offers a previous schoolyear import only when the current year has no areas', function () {
    $this->schoolyear->update(['concerns' => '2026/27']);
    $previousSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'concerns' => '2025/26',
    ]);
    $sourceArea = teachingEntryAreaFor($this->teacher, $previousSchoolyear, 'Unterstufe');
    TeachingEntryDefinition::factory()->count(2)->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $previousSchoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $sourceArea->id,
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->getJson('/api/admin/teaching/entry_areas')
        ->assertOk()
        ->assertJsonPath('meta.previous_year_import.schoolyear.id', $previousSchoolyear->id)
        ->assertJsonPath('meta.previous_year_import.schoolyear.label', '2025/26')
        ->assertJsonPath('meta.previous_year_import.area_count', 1)
        ->assertJsonPath('meta.previous_year_import.entry_count', 2);

    teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Aktuell');

    $this->getJson('/api/admin/teaching/entry_areas')
        ->assertOk()
        ->assertJsonPath('meta.previous_year_import', null);
});

test('imports owned areas and entries from the previous schoolyear', function () {
    $this->schoolyear->update(['concerns' => '2026/27']);
    $previousSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'concerns' => '2025/26',
    ]);
    $underSchoolArea = teachingEntryAreaFor($this->teacher, $previousSchoolyear, 'Unterstufe');
    $underSchoolArea->update(['semester_count' => 2, 'semester_1_weight' => 40, 'semester_2_weight' => 60]);
    $upperSchoolArea = teachingEntryAreaFor($this->teacher, $previousSchoolyear, 'Oberstufe');
    $foreignArea = teachingEntryAreaFor($this->otherTeacher, $previousSchoolyear, 'Fremd');
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $previousSchoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $underSchoolArea->id,
        'short_name' => 'M',
        'name' => 'Mitarbeit',
        'fixed_properties' => ['+', '-'],
        'has_table_marking' => true,
        'table_marking_color' => 'purple',
    ]);
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $previousSchoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $upperSchoolArea->id,
        'short_name' => 'A',
        'name' => 'Abfrage',
        'category' => 'Weitere',
        'has_notifications' => true,
        'notification_recipients' => ['parents'],
    ]);
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $previousSchoolyear->id,
        'user_id' => $this->otherTeacher->id,
        'teaching_entry_area_id' => $foreignArea->id,
        'short_name' => 'F',
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')
        ->postJson('/api/admin/teaching/entry-area-imports');

    $response->assertCreated()
        ->assertJsonPath('imported_area_count', 2)
        ->assertJsonPath('imported_entry_count', 2)
        ->assertJsonCount(2, 'data.areas')
        ->assertJsonCount(2, 'data.entries')
        ->assertJsonCount(2, 'data.grading_parts');

    $copiedUnderSchoolArea = TeachingEntryArea::query()
        ->where('user_id', $this->teacher->id)
        ->where('schoolyear_id', $this->schoolyear->id)
        ->where('name', 'Unterstufe')
        ->firstOrFail();

    expect($underSchoolArea->entryDefinitions()->count())->toBe(1)
        ->and($copiedUnderSchoolArea->semester_count)->toBe(2)
        ->and($copiedUnderSchoolArea->semester_1_weight)->toBe(40)
        ->and($copiedUnderSchoolArea->semester_2_weight)->toBe(60)
        ->and($upperSchoolArea->entryDefinitions()->count())->toBe(1)
        ->and($copiedUnderSchoolArea->entryDefinitions()->firstOrFail()->fixed_properties)->toBe(['+', '-'])
        ->and($copiedUnderSchoolArea->entryDefinitions()->firstOrFail()->has_table_marking)->toBeTrue()
        ->and($copiedUnderSchoolArea->entryDefinitions()->firstOrFail()->table_marking_color)->toBe('purple')
        ->and($copiedUnderSchoolArea->gradingParts()->value('name'))->toBe('Unterstufe')
        ->and(TeachingEntryArea::query()
            ->where('user_id', $this->teacher->id)
            ->where('schoolyear_id', $this->schoolyear->id)
            ->where('name', 'Fremd')
            ->exists())->toBeFalse();
});

test('previous schoolyear import is rejected when current areas already exist', function () {
    $this->schoolyear->update(['concerns' => '2026/27']);
    $previousSchoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'concerns' => '2025/26',
    ]);
    teachingEntryAreaFor($this->teacher, $previousSchoolyear, 'Unterstufe');
    teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Aktuell');

    $this->actingAs($this->teacher, 'sanctum')
        ->postJson('/api/admin/teaching/entry-area-imports')
        ->assertConflict();
});

test('previous schoolyear import is rejected when no source areas exist', function () {
    $this->schoolyear->update(['concerns' => '2026/27']);
    Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'concerns' => '2025/26',
    ]);

    $this->actingAs($this->teacher, 'sanctum')
        ->postJson('/api/admin/teaching/entry-area-imports')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Im vorherigen Schuljahr sind keine Bereiche vorhanden.');
});

test('store trims names and rejects duplicate names', function () {
    $this->actingAs($this->teacher, 'sanctum')
        ->postJson('/api/admin/teaching/entry_areas', ['name' => '  Oberstufe  '])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Oberstufe')
        ->assertJsonPath('data.semester_count', 1)
        ->assertJsonPath('data.semester_1_weight', 100)
        ->assertJsonPath('data.semester_2_weight', 0)
        ->assertJsonPath('data.entry_count', 0)
        ->assertJsonPath('grading_part.name', 'Oberstufe');

    expect(TeachingEntryGradingPart::query()
        ->where('teaching_entry_area_id', TeachingEntryArea::query()->where('name', 'Oberstufe')->value('id'))
        ->value('name'))->toBe('Oberstufe');

    $this->postJson('/api/admin/teaching/entry_areas', ['name' => 'Oberstufe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('update renames an owned area but rejects a foreign area', function () {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Unterstufe');
    $foreignArea = teachingEntryAreaFor($this->otherTeacher, $this->schoolyear, 'Fremd');
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson("/api/admin/teaching/entry_areas/{$area->id}", ['name' => 'Mittelstufe'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Mittelstufe');
    $this->putJson("/api/admin/teaching/entry_areas/{$foreignArea->id}", ['name' => 'Geändert'])
        ->assertForbidden();
});

test('semester settings persist per area and survive name-only updates', function () {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Unterstufe');
    $otherArea = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Oberstufe');
    $this->actingAs($this->teacher, 'sanctum');

    $this->putJson("/api/admin/teaching/entry_areas/{$area->id}", [
        'name' => 'Unterstufe',
        'semester_count' => 2,
        'semester_1_weight' => 40,
        'semester_2_weight' => 60,
    ])->assertOk()
        ->assertJsonPath('data.semester_count', 2)
        ->assertJsonPath('data.semester_1_weight', 40)
        ->assertJsonPath('data.semester_2_weight', 60);

    $this->putJson("/api/admin/teaching/entry_areas/{$area->id}", ['name' => 'Mittelstufe'])
        ->assertOk()
        ->assertJsonPath('data.semester_count', 2)
        ->assertJsonPath('data.semester_1_weight', 40)
        ->assertJsonPath('data.semester_2_weight', 60);

    $this->getJson('/api/admin/teaching/entry_areas')->assertOk()->assertJsonFragment([
        'id' => $area->id,
        'name' => 'Mittelstufe',
        'semester_count' => 2,
        'semester_1_weight' => 40,
        'semester_2_weight' => 60,
        'entry_count' => 0,
    ]);

    expect($otherArea->refresh()->semester_count)->toBe(1)
        ->and($otherArea->semester_1_weight)->toBe(100)
        ->and($otherArea->semester_2_weight)->toBe(0);

    $this->putJson("/api/admin/teaching/entry_areas/{$area->id}", [
        'name' => 'Mittelstufe',
        'semester_count' => 1,
        'semester_1_weight' => 100,
        'semester_2_weight' => 0,
    ])->assertOk()->assertJsonPath('data.semester_count', 1);
});

test('semester settings reject invalid values without changing the area', function (array $settings, string $error) {
    $area = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Unterstufe');

    $this->actingAs($this->teacher, 'sanctum')
        ->putJson("/api/admin/teaching/entry_areas/{$area->id}", ['name' => 'Unterstufe', ...$settings])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);

    expect($area->refresh()->semester_count)->toBe(1)
        ->and($area->semester_1_weight)->toBe(100)
        ->and($area->semester_2_weight)->toBe(0);
})->with([
    'zero semesters' => [['semester_count' => 0], 'semester_count'],
    'three semesters' => [['semester_count' => 3], 'semester_count'],
    'missing semester choice' => [['semester_count' => null], 'semester_count'],
    'negative weight' => [['semester_1_weight' => -1], 'semester_1_weight'],
    'excess weight' => [['semester_2_weight' => 101], 'semester_2_weight'],
    'fractional weight' => [['semester_1_weight' => 49.5], 'semester_1_weight'],
    'missing weight' => [['semester_2_weight' => null], 'semester_2_weight'],
    'incomplete total' => [['semester_count' => 2, 'semester_1_weight' => 40, 'semester_2_weight' => 50], 'semester_2_weight'],
    'partial update checks stored weight' => [['semester_count' => 2, 'semester_1_weight' => 40], 'semester_2_weight'],
]);

test('semester settings cannot be changed for another teacher or schoolyear', function () {
    $previousYear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $areas = [
        teachingEntryAreaFor($this->otherTeacher, $this->schoolyear, 'Fremd'),
        teachingEntryAreaFor($this->teacher, $previousYear, 'Vorjahr'),
    ];
    $this->actingAs($this->teacher, 'sanctum');

    foreach ($areas as $area) {
        $this->putJson("/api/admin/teaching/entry_areas/{$area->id}", [
            'name' => $area->name,
            'semester_count' => 2,
            'semester_1_weight' => 50,
            'semester_2_weight' => 50,
        ])->assertForbidden();

        expect($area->refresh()->semester_count)->toBe(1);
    }
});

test('destroy removes an empty area and protects an area with entries', function () {
    $emptyArea = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Leer');
    $usedArea = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Unterstufe');
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $usedArea->id,
    ]);
    $this->actingAs($this->teacher, 'sanctum');

    $this->deleteJson("/api/admin/teaching/entry_areas/{$emptyArea->id}")->assertNoContent();
    $this->deleteJson("/api/admin/teaching/entry_areas/{$usedArea->id}")
        ->assertConflict()
        ->assertJsonPath('entry_count', 1);

    $this->assertModelMissing($emptyArea);
    $this->assertModelExists($usedArea);
});

test('destroy protects an area used as a course grading schema', function () {
    $usedArea = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Unterstufe');
    TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $usedArea->id,
    ]);
    $this->actingAs($this->teacher, 'sanctum');

    $this->deleteJson("/api/admin/teaching/entry_areas/{$usedArea->id}")
        ->assertConflict()
        ->assertJsonPath('course_count', 1);

    $this->assertModelExists($usedArea);
});

test('copies all entries from one owned area to another', function () {
    $sourceArea = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Unterstufe');
    $targetArea = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Oberstufe');
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $sourceArea->id,
        'short_name' => 'M',
        'name' => 'Mitarbeit',
        'fixed_properties' => ['+', '-'],
        'has_table_marking' => true,
        'table_marking_color' => 'purple',
    ]);
    TeachingEntryDefinition::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'teaching_entry_area_id' => $sourceArea->id,
        'short_name' => 'A',
        'name' => 'Abfrage',
        'category' => 'Weitere',
        'has_notifications' => true,
        'notification_recipients' => ['class_teacher', 'student'],
    ]);

    $response = $this->actingAs($this->teacher, 'sanctum')->postJson(
        "/api/admin/teaching/entry_areas/{$targetArea->id}/entry-copies",
        ['source_area_id' => $sourceArea->id]
    );

    $response->assertCreated()
        ->assertJsonPath('copied_count', 2)
        ->assertJsonCount(2, 'data');

    expect($sourceArea->entryDefinitions()->count())->toBe(2)
        ->and($targetArea->entryDefinitions()->count())->toBe(2)
        ->and($targetArea->entryDefinitions()->where('short_name', 'M')->firstOrFail()->fixed_properties)
        ->toBe(['+', '-'])
        ->and($targetArea->entryDefinitions()->where('short_name', 'M')->firstOrFail()->has_table_marking)
        ->toBeTrue()
        ->and($targetArea->entryDefinitions()->where('short_name', 'M')->firstOrFail()->table_marking_color)
        ->toBe('purple')
        ->and($targetArea->entryDefinitions()->where('short_name', 'A')->firstOrFail()->has_notifications)
        ->toBeTrue()
        ->and($targetArea->entryDefinitions()->where('short_name', 'A')->firstOrFail()->notification_recipients)
        ->toBe(['class_teacher', 'student']);
});

test('rejects copying when a short name already exists in the target area', function () {
    $sourceArea = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Unterstufe');
    $targetArea = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Oberstufe');
    foreach ([$sourceArea, $targetArea] as $area) {
        TeachingEntryDefinition::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'user_id' => $this->teacher->id,
            'teaching_entry_area_id' => $area->id,
            'short_name' => 'M',
        ]);
    }

    $this->actingAs($this->teacher, 'sanctum')
        ->postJson("/api/admin/teaching/entry_areas/{$targetArea->id}/entry-copies", [
            'source_area_id' => $sourceArea->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('source_area_id');

    expect($targetArea->entryDefinitions()->count())->toBe(1);
});

test('rejects copying entries from a foreign area or into the same area', function () {
    $targetArea = teachingEntryAreaFor($this->teacher, $this->schoolyear, 'Oberstufe');
    $foreignArea = teachingEntryAreaFor($this->otherTeacher, $this->schoolyear, 'Fremd');
    $this->actingAs($this->teacher, 'sanctum');

    $this->postJson("/api/admin/teaching/entry_areas/{$targetArea->id}/entry-copies", [
        'source_area_id' => $foreignArea->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('source_area_id');

    $this->postJson("/api/admin/teaching/entry_areas/{$targetArea->id}/entry-copies", [
        'source_area_id' => $targetArea->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('source_area_id');
});
