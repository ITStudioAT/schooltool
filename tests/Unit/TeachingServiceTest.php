<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingSchema;
use App\Models\User;
use App\Services\TeachingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    $this->service = new TeachingService;
});

test('ensureDefaultSchema creates a standard schema when user has none', function () {
    expect(TeachingSchema::query()->count())->toBe(0);

    $this->service->ensureDefaultSchema($this->user, $this->schoolyear->id);

    $this->assertDatabaseCount('teaching_schemas', 1);
    $this->assertDatabaseHas('teaching_schemas', [
        'user_id' => $this->user->id,
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Standard',
    ]);

    $schema = TeachingSchema::query()->first();
    expect($schema)->not->toBeNull()
        ->and($schema->works)->toBeArray()
        ->and($schema->grading)->toBeArray()
        ->and($schema->grading['category_evaluation_values'] ?? null)->toMatchArray(TeachingService::defaultCategoryEvaluationValues())
        ->and($schema->grading['default_category_evaluation_value'] ?? null)->toBe(TeachingService::defaultCategoryEvaluationDefaultValue());
});

test('ensureDefaultSchema is idempotent and does not create duplicate rows', function () {
    $this->service->ensureDefaultSchema($this->user, $this->schoolyear->id);
    $firstSchemaId = TeachingSchema::query()->value('schema_id');

    $this->service->ensureDefaultSchema($this->user, $this->schoolyear->id);

    $this->assertDatabaseCount('teaching_schemas', 1);
    expect(TeachingSchema::query()->value('schema_id'))->toBe($firstSchemaId);
});

test('saveSchemas normalizes category evaluation values and falls back to defaults', function () {
    $this->service->saveSchemas($this->user, [[
        'id' => 'schema-custom',
        'name' => 'Standard',
        'works' => [],
        'grading' => [
            'semester_count' => 2,
            'semester_1_weight' => 40,
            'semester_2_weight' => 60,
            'category_evaluation_values' => [
                ['value' => ' Offen ', 'color' => '#fb8c00'],
                ['value' => '', 'color' => '#000000'],
                ['value' => 'Bestanden', 'color' => '43a047'],
                ['value' => 'Offen', 'color' => '#123456'],
                ['value' => '5 ', 'color' => '#e53935'],
            ],
            'default_category_evaluation_value' => 'Bestanden',
            'categories' => [],
        ],
    ]], $this->schoolyear->id);

    expect($this->service->schemaById($this->user, 'schema-custom', $this->schoolyear->id))
        ->and($this->service->schemaById($this->user, 'schema-custom', $this->schoolyear->id)['grading']['category_evaluation_values'] ?? null)
        ->toBe([
            ['value' => 'Offen', 'color' => '#fb8c00'],
            ['value' => 'Bestanden', 'color' => '#43a047'],
            ['value' => '5', 'color' => '#e53935'],
        ])
        ->and($this->service->schemaById($this->user, 'schema-custom', $this->schoolyear->id)['grading']['default_category_evaluation_value'] ?? null)
        ->toBe('Bestanden');

    $this->service->saveSchemas($this->user, [[
        'id' => 'schema-custom',
        'name' => 'Standard',
        'works' => [],
        'grading' => [
            'semester_count' => 2,
            'semester_1_weight' => 40,
            'semester_2_weight' => 60,
            'category_evaluation_values' => [],
            'categories' => [],
        ],
    ]], $this->schoolyear->id);

    expect($this->service->schemaById($this->user, 'schema-custom', $this->schoolyear->id))
        ->and($this->service->schemaById($this->user, 'schema-custom', $this->schoolyear->id)['grading']['category_evaluation_values'] ?? null)
        ->toMatchArray(TeachingService::defaultCategoryEvaluationValues())
        ->and($this->service->schemaById($this->user, 'schema-custom', $this->schoolyear->id)['grading']['default_category_evaluation_value'] ?? null)
        ->toBe(TeachingService::defaultCategoryEvaluationDefaultValue());
});

test('saveSchemas defaults category evaluation toggle on grading categories to false', function () {
    $this->service->saveSchemas($this->user, [[
        'id' => 'schema-categories',
        'name' => 'Standard',
        'works' => [],
        'grading' => [
            'semester_count' => 2,
            'semester_1_weight' => 40,
            'semester_2_weight' => 60,
            'categories' => [
                [
                    'name' => 'Projekt',
                    'weight' => 100,
                    'works' => [],
                ],
            ],
        ],
    ]], $this->schoolyear->id);

    expect($this->service->schemaById($this->user, 'schema-categories', $this->schoolyear->id))
        ->and($this->service->schemaById($this->user, 'schema-categories', $this->schoolyear->id)['grading']['categories'][0]['category_evaluation_enabled'] ?? null)
        ->toBeFalse();
});
