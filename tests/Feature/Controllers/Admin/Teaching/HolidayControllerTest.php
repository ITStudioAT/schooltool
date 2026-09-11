<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use App\Models\TeachingHoliday;
use App\Models\User;
use App\Services\TeachingHolidaySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    collect([
        'super_admin',
        'admin',
        'teaching_admin',
        'teacher',
        'user',
        'student',
    ])->each(fn (string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    $this->school = School::factory()->create();
    enableSchoolToolModuleForTests($this->school, 'teaching');
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    $teachingLicence = Licence::firstOrCreate(
        ['name' => 'Lehrertool'],
        ['long_name' => 'Lehrertool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($teachingLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->admin->assignRole('admin');

    $this->superAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->superAdmin->assignRole('super_admin');

    $this->teacher = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teacher->assignRole('teacher');

    $this->teachingAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->teachingAdmin->assignRole('teaching_admin');
});

describe('authorization', function () {
    test('returns 401 when unauthenticated', function () {
        $this->getJson('/api/admin/teaching/holidays')->assertStatus(401);
    });

    test('returns 403 for teacher on admin holiday index', function () {
        $this->actingAs($this->teacher, 'sanctum');

        $this->getJson('/api/admin/teaching/holidays')->assertStatus(403);
    });

    test('allows super_admin on admin holiday index', function () {
        $this->actingAs($this->superAdmin, 'sanctum');

        $this->getJson('/api/admin/teaching/holidays')->assertOk();
    });

    test('allows teaching_admin on admin holiday index', function () {
        $this->actingAs($this->teachingAdmin, 'sanctum');

        $this->getJson('/api/admin/teaching/holidays')->assertOk();
    });
});

test('index returns only school scope holidays', function () {
    $this->actingAs($this->admin, 'sanctum');

    TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-04-01',
        'reason' => 'School holiday',
    ]);

    TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'teacher',
        'user_id' => $this->teacher->id,
        'date' => '2026-04-01',
        'reason' => 'Teacher absence',
    ]);

    $response = $this->getJson('/api/admin/teaching/holidays');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['scope'])->toBe('school')
        ->and($data[0]['reason'])->toBe('School holiday');
});

test('store creates school holidays for a range and syncs free status', function () {
    $this->actingAs($this->admin, 'sanctum');

    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'classes' => ['2A'],
        'students' => [],
    ]);

    $courseDate = TeachingCourseDate::create([
        'teaching_course_id' => $course->id,
        'date' => '2026-05-06',
        'hours' => [2],
        'status' => [],
    ]);

    $response = $this->postJson('/api/admin/teaching/holidays', [
        'date_from' => '2026-05-05',
        'date_until' => '2026-05-06',
        'reason' => 'Pfingstferien',
    ]);

    $response->assertCreated()
        ->assertJsonPath('created', 2)
        ->assertJsonPath('updated', 0);

    $this->assertDatabaseHas('teaching_holidays', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-05-05',
        'reason' => 'Pfingstferien',
    ]);

    $this->assertDatabaseHas('teaching_holidays', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-05-06',
        'reason' => 'Pfingstferien',
    ]);

    $courseDate->refresh();
    expect($courseDate->status)->toContain('free');
});

test('destroy removes school holiday and keeps non-free status entries', function () {
    $this->actingAs($this->admin, 'sanctum');

    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id,
        'classes' => ['2A'],
        'students' => [],
    ]);

    $holiday = TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-05-07',
        'reason' => 'Holiday',
    ]);

    $courseDate = TeachingCourseDate::create([
        'teaching_course_id' => $course->id,
        'date' => '2026-05-07',
        'hours' => [3],
        'status' => ['pruefung', 'free'],
    ]);

    $this->deleteJson("/api/admin/teaching/holidays/{$holiday->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('teaching_holidays', [
        'id' => $holiday->id,
    ]);

    $courseDate->refresh();
    expect($courseDate->status)->toBe(['pruefung']);
});

test('destroy rejects teacher-scope holiday on admin endpoint', function () {
    $this->actingAs($this->admin, 'sanctum');

    $teacherHoliday = TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'teacher',
        'user_id' => $this->teacher->id,
        'date' => '2026-05-10',
        'reason' => 'Individual day',
    ]);

    $this->deleteJson("/api/admin/teaching/holidays/{$teacherHoliday->id}")
        ->assertStatus(403);

    $this->assertDatabaseHas('teaching_holidays', [
        'id' => $teacherHoliday->id,
    ]);
});

test('teaching_admin can create school holidays', function () {
    $this->actingAs($this->teachingAdmin, 'sanctum');

    $response = $this->postJson('/api/admin/teaching/holidays', [
        'date_from' => '2026-06-01',
        'date_until' => '2026-06-02',
        'reason' => 'Sommerferien',
    ]);

    $response->assertCreated()
        ->assertJsonPath('created', 2)
        ->assertJsonPath('updated', 0);

    $this->assertDatabaseHas('teaching_holidays', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-06-01',
        'reason' => 'Sommerferien',
    ]);

    $this->assertDatabaseHas('teaching_holidays', [
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-06-02',
        'reason' => 'Sommerferien',
    ]);
});

test('teaching_admin can delete school holidays', function () {
    $this->actingAs($this->teachingAdmin, 'sanctum');

    $holiday = TeachingHoliday::create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school',
        'user_id' => null,
        'date' => '2026-07-01',
        'reason' => 'Test Holiday',
    ]);

    $this->deleteJson("/api/admin/teaching/holidays/{$holiday->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('teaching_holidays', [
        'id' => $holiday->id,
    ]);
});

function holidayTransferFile(array $holidays, array $metadata = []): UploadedFile
{
    return UploadedFile::fake()->createWithContent('ferien.json', json_encode([
        'export_type' => 'teaching_holidays',
        'schema_version' => 1,
        'holidays' => $holidays,
        ...$metadata,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

test('holiday transfer requires an authenticated administrator', function () {
    $this->getJson(route('teaching.holidays.export'))->assertUnauthorized();
    $this->postJson(route('teaching.holidays.import'))->assertUnauthorized();

    $this->actingAs($this->teacher, 'sanctum');
    $this->getJson(route('teaching.holidays.export'))->assertForbidden();
    $this->postJson(route('teaching.holidays.import'))->assertForbidden();
});

test('holiday export roundtrips dates and reasons in the selected school and year', function (string $actor) {
    $this->actingAs($this->{$actor}, 'sanctum');
    $rows = [
        ['date' => '2026-12-24', 'reason' => 'Weihnachten – Grüße, "Ferien"'],
        ['date' => '2026-12-25', 'reason' => null],
    ];
    foreach ($rows as $row) {
        TeachingHoliday::create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'scope' => 'school',
            ...$row,
        ]);
    }
    foreach ([
        ['scope' => 'teacher', 'user_id' => $this->teacher->id],
        ['school_id' => School::factory()->create()->id],
        ['schoolyear_id' => Schoolyear::factory()->create(['school_id' => $this->school->id])->id],
    ] as $scope) {
        TeachingHoliday::create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'scope' => 'school',
            'date' => '2026-12-26',
            'reason' => 'Nicht exportieren',
            ...$scope,
        ]);
    }

    $export = $this->getJson(route('teaching.holidays.export'))
        ->assertOk()->assertDownload("ferien-{$this->schoolyear->id}.json")
        ->streamedContent();
    $payload = json_decode($export, true, flags: JSON_THROW_ON_ERROR);
    expect($payload)->toBe([
        'export_type' => 'teaching_holidays', 'schema_version' => 1, 'holidays' => $rows,
    ]);

    $this->postJson(route('teaching.holidays.import'), [
        'file' => UploadedFile::fake()->createWithContent('ferien.json', $export),
    ])->assertOk()->assertExactJson(['created' => 0, 'updated' => 0, 'unchanged' => 2]);

    $targetYear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->{$actor}->update(['schoolyear_id' => $targetYear->id]);
    $this->postJson(route('teaching.holidays.import'), [
        'file' => UploadedFile::fake()->createWithContent('ferien.json', $export),
    ])->assertOk()->assertExactJson(['created' => 2, 'updated' => 0, 'unchanged' => 0]);
    foreach ($rows as $row) {
        $this->assertDatabaseHas('teaching_holidays', [
            'school_id' => $this->school->id, 'schoolyear_id' => $targetYear->id,
            'scope' => 'school', 'user_id' => null, ...$row,
        ]);
    }
    $this->assertDatabaseCount('teaching_holidays', 7);
})->with(['admin', 'teachingAdmin', 'superAdmin']);

test('holiday import updates reasons in place and preserves unrelated days and course status', function () {
    $this->actingAs($this->admin, 'sanctum');
    $existing = TeachingHoliday::create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school', 'date' => '2026-05-05', 'reason' => 'Alter Grund',
    ]);
    $unrelated = TeachingHoliday::create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school', 'date' => '2026-05-07', 'reason' => 'Beibehalten',
    ]);
    $course = TeachingCourse::factory()->create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->teacher->id, 'classes' => ['2A'], 'students' => [],
    ]);
    $courseDate = TeachingCourseDate::create([
        'teaching_course_id' => $course->id, 'date' => '2026-05-06', 'hours' => [2], 'status' => ['pruefung'],
    ]);
    $rows = [
        ['date' => '2026-05-05', 'reason' => 'Neuer Grund'],
        ['date' => '2026-05-06', 'reason' => null],
        ['date' => '2026-05-06', 'reason' => null],
    ];
    $this->postJson(route('teaching.holidays.import'), ['file' => holidayTransferFile($rows)])
        ->assertOk()->assertExactJson(['created' => 1, 'updated' => 1, 'unchanged' => 0]);
    expect($existing->refresh()->reason)->toBe('Neuer Grund')
        ->and($unrelated->refresh()->reason)->toBe('Beibehalten')
        ->and($courseDate->refresh()->status)->toBe(['pruefung', 'free']);
    $this->assertDatabaseCount('teaching_holidays', 3);

    $savedAt = $existing->updated_at;
    $this->travel(1)->minutes();
    $this->postJson(route('teaching.holidays.import'), ['file' => holidayTransferFile($rows)])
        ->assertOk()->assertExactJson(['created' => 0, 'updated' => 0, 'unchanged' => 2]);
    expect($existing->refresh()->updated_at->eq($savedAt))->toBeTrue();

    $this->postJson(route('teaching.holidays.import'), ['file' => holidayTransferFile([
        ['date' => '2026-05-05', 'reason' => null],
    ])])->assertOk()->assertJsonPath('updated', 1);
    expect($existing->refresh()->reason)->toBeNull();
});

test('invalid holiday rows reject the entire import before changing records', function (array $invalidRow, string $errorKey) {
    $this->actingAs($this->admin, 'sanctum');
    $holiday = TeachingHoliday::create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school', 'date' => '2026-05-05', 'reason' => 'Bestand',
    ]);
    $this->postJson(route('teaching.holidays.import'), ['file' => holidayTransferFile([
        ['date' => '2026-05-05', 'reason' => 'Änderung'],
        ['date' => '2026-05-06', 'reason' => 'Neu'],
        $invalidRow,
    ])])->assertUnprocessable()->assertJsonValidationErrors($errorKey);
    expect($holiday->refresh()->reason)->toBe('Bestand');
    $this->assertDatabaseCount('teaching_holidays', 1);
})->with([
    'invalid calendar date' => [['date' => '2026-02-30', 'reason' => null], 'holidays.2.date'],
    'ambiguous date' => [['date' => '01.05.2026', 'reason' => null], 'holidays.2.date'],
    'long reason' => [['date' => '2026-05-08', 'reason' => str_repeat('a', 256)], 'holidays.2.reason'],
    'missing reason' => [['date' => '2026-05-08'], 'holidays.2.reason'],
    'foreign scope' => [['date' => '2026-05-08', 'reason' => null, 'school_id' => 42], 'holidays.2'],
    'conflicting duplicate' => [['date' => '2026-05-06', 'reason' => 'Anderer Grund'], 'holidays.2.reason'],
]);

test('holiday import validates the file format and supports an empty export', function () {
    $this->actingAs($this->admin, 'sanctum');
    $this->postJson(route('teaching.holidays.import'))->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->postJson(route('teaching.holidays.import'), [
        'file' => UploadedFile::fake()->createWithContent('ferien.json', '{broken'),
    ])->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->postJson(route('teaching.holidays.import'), [
        'file' => holidayTransferFile([], ['schema_version' => 99]),
    ])->assertUnprocessable()->assertJsonValidationErrors('schema_version');
    $this->postJson(route('teaching.holidays.import'), [
        'file' => holidayTransferFile([], ['export_type' => 'other']),
    ])->assertUnprocessable()->assertJsonValidationErrors('export_type');
    $this->postJson(route('teaching.holidays.import'), [
        'file' => UploadedFile::fake()->create('ferien.json', 2049, 'application/json'),
    ])->assertUnprocessable()->assertJsonValidationErrors('file');

    $export = $this->getJson(route('teaching.holidays.export'))->assertOk()->streamedContent();
    $this->postJson(route('teaching.holidays.import'), [
        'file' => UploadedFile::fake()->createWithContent('ferien.json', $export),
    ])->assertOk()->assertExactJson(['created' => 0, 'updated' => 0, 'unchanged' => 0]);
    $this->assertDatabaseCount('teaching_holidays', 0);
});

test('holiday import rolls back reason updates and new days when course synchronization fails', function () {
    $this->actingAs($this->admin, 'sanctum');
    $existing = TeachingHoliday::create([
        'school_id' => $this->school->id, 'schoolyear_id' => $this->schoolyear->id,
        'scope' => 'school', 'date' => '2026-05-05', 'reason' => 'Bestand',
    ]);
    $this->mock(TeachingHolidaySyncService::class)->shouldReceive('syncForSchoolyear')
        ->once()->andThrow(new RuntimeException('Synchronization failed'));

    $this->postJson(route('teaching.holidays.import'), ['file' => holidayTransferFile([
        ['date' => '2026-05-05', 'reason' => 'Änderung'],
        ['date' => '2026-05-06', 'reason' => 'Neu'],
    ])])->assertServerError();

    expect($existing->refresh()->reason)->toBe('Bestand');
    $this->assertDatabaseCount('teaching_holidays', 1);
});
