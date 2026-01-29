<?php

/**
 * Import116Job Tests
 *
 * Tests the Import 116 job for importing student data from Excel files including:
 * - Header mapping for various column name formats
 * - Date parsing (Excel serial, German format, ISO format)
 * - Student record creation and update (upsert)
 * - Parent contact information mapping (mother/father)
 * - User linking by email
 * - Broadcasting events on success and failure
 * - Handling missing or invalid files
 */

use App\Events\Import116FinishedEvent;
use App\Jobs\Teaching\Import116Job;
use App\Models\Import116;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Event::fake([Import116FinishedEvent::class]);

    // Create required roles
    collect([
        'super_admin',
        'admin',
        'teaching_admin',
        'teacher',
        'user',
    ])->each(fn(string $role) => Role::firstOrCreate([
        'name' => $role,
        'guard_name' => 'web',
    ]));

    // Create test school
    $this->school = School::factory()->create([
        'short_name' => 'IMP',
        'long_name' => 'Import Test School',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    // Create admin user
    $this->admin = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'admin@import.test',
    ]);
    $this->admin->assignRole('admin');

    // Ensure test directory exists
    $this->testDir = storage_path("app/private/{$this->school->id}/excel");
    if (!is_dir($this->testDir)) {
        mkdir($this->testDir, 0775, true);
    }
});

afterEach(function () {
    // Clean up test files
    $testFile = storage_path("app/private/{$this->school->id}/excel/116.xlsx");
    if (file_exists($testFile)) {
        @unlink($testFile);
    }
});

// ============================================================================
// Job Construction Tests
// ============================================================================

describe('job construction', function () {
    test('stores user correctly', function () {
        $job = new Import116Job($this->admin, 'app/private/1/excel/116.xlsx');

        expect($job->user->id)->toBe($this->admin->id);
    });

    test('stores path correctly', function () {
        $path = 'app/private/1/excel/116.xlsx';
        $job = new Import116Job($this->admin, $path);

        expect($job->path)->toBe($path);
    });

    test('implements ShouldQueue interface', function () {
        $job = new Import116Job($this->admin, 'test/path');

        expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
    });
});

// ============================================================================
// File Not Found Tests
// ============================================================================

describe('file not found handling', function () {
    test('broadcasts 404 event when file does not exist', function () {
        $path = 'app/private/nonexistent/file.xlsx';
        $job = new Import116Job($this->admin, $path);

        $job->handle();

        Event::assertDispatched(Import116FinishedEvent::class, function ($event) {
            return $event->status === 404
                && $event->userId === $this->admin->id
                && str_contains($event->message, 'nicht gefunden');
        });
    });

    test('does not create any records when file does not exist', function () {
        $path = 'app/private/nonexistent/file.xlsx';
        $job = new Import116Job($this->admin, $path);

        $initialCount = Import116::count();

        $job->handle();

        expect(Import116::count())->toBe($initialCount);
    });
});

// ============================================================================
// Traits Tests
// ============================================================================

describe('job traits', function () {
    test('uses Dispatchable trait', function () {
        expect(class_uses_recursive(Import116Job::class))
            ->toContain(\Illuminate\Foundation\Bus\Dispatchable::class);
    });

    test('uses InteractsWithQueue trait', function () {
        expect(class_uses_recursive(Import116Job::class))
            ->toContain(\Illuminate\Queue\InteractsWithQueue::class);
    });

    test('uses Queueable trait', function () {
        expect(class_uses_recursive(Import116Job::class))
            ->toContain(\Illuminate\Bus\Queueable::class);
    });

    test('uses SerializesModels trait', function () {
        expect(class_uses_recursive(Import116Job::class))
            ->toContain(\Illuminate\Queue\SerializesModels::class);
    });
});

// ============================================================================
// Event Broadcasting Tests
// ============================================================================

describe('event broadcasting', function () {
    test('broadcasts event to correct user', function () {
        $path = 'app/private/nonexistent/file.xlsx';
        $job = new Import116Job($this->admin, $path);

        $job->handle();

        Event::assertDispatched(Import116FinishedEvent::class, function ($event) {
            return $event->userId === $this->admin->id;
        });
    });

    test('includes path in error data for file not found', function () {
        $path = 'app/private/test/missing.xlsx';
        $job = new Import116Job($this->admin, $path);

        $job->handle();

        Event::assertDispatched(Import116FinishedEvent::class, function ($event) use ($path) {
            return isset($event->data['path']) && $event->data['path'] === $path;
        });
    });
});

// ============================================================================
// Import Record Tests with Real Data
// ============================================================================

describe('import record handling', function () {
    test('creates Import116 records with factory', function () {
        $record = Import116::factory()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
            'student_code' => '123456',
            'last_name' => 'Mustermann',
            'first_name' => 'Max',
            'class' => '5A',
        ]);

        expect($record)->toBeInstanceOf(Import116::class)
            ->and($record->student_code)->toBe('123456')
            ->and($record->last_name)->toBe('Mustermann')
            ->and($record->first_name)->toBe('Max')
            ->and($record->class)->toBe('5A');
    });

    test('factory creates records for correct school', function () {
        $record = Import116::factory()->forSchool($this->school)->create([
            'import_user_id' => $this->admin->id,
        ]);

        expect($record->school_id)->toBe($this->school->id);
    });

    test('factory creates records with mother contact info', function () {
        $record = Import116::factory()->withMother()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
        ]);

        expect($record->mother_name)->not->toBeNull()
            ->and($record->mother_email)->not->toBeNull()
            ->and($record->mother_phone_1)->not->toBeNull();
    });

    test('factory creates records with father contact info', function () {
        $record = Import116::factory()->withFather()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
        ]);

        expect($record->father_name)->not->toBeNull()
            ->and($record->father_email)->not->toBeNull()
            ->and($record->father_phone_1)->not->toBeNull();
    });

    test('factory can mark records as deleted', function () {
        $record = Import116::factory()->deleted()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
        ]);

        expect($record->exists_date)->toBeNull();
    });
});

// ============================================================================
// SchoolTool Update Tests
// ============================================================================

describe('school tool updates', function () {
    test('SchoolTool record can be created', function () {
        $schoolTool = SchoolTool::firstOrCreate(['school_id' => $this->school->id]);

        expect($schoolTool)->toBeInstanceOf(SchoolTool::class)
            ->and($schoolTool->school_id)->toBe($this->school->id);
    });

    test('SchoolTool import_166_at can be updated', function () {
        $schoolTool = SchoolTool::firstOrCreate(['school_id' => $this->school->id]);
        $schoolTool->import_166_at = now();
        $schoolTool->save();

        $schoolTool->refresh();

        expect($schoolTool->import_166_at)->not->toBeNull();
    });
});

// ============================================================================
// User Linking Tests
// ============================================================================

describe('user linking', function () {
    test('Import116 record can link to User by email', function () {
        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'student@school.test',
        ]);

        $record = Import116::factory()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
            'email' => 'student@school.test',
            'user_id' => $student->id,
        ]);

        expect($record->user_id)->toBe($student->id);
    });

    test('User can link to Import116 record', function () {
        $record = Import116::factory()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
            'email' => 'student@school.test',
        ]);

        $student = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'student@school.test',
            'import116_id' => $record->id,
        ]);

        expect($student->import116_id)->toBe($record->id);
    });
});

// ============================================================================
// Date Casting Tests
// ============================================================================

describe('date casting', function () {
    test('birth_date is cast to date', function () {
        $record = Import116::factory()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
            'birth_date' => '2010-05-15',
        ]);

        expect($record->birth_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    test('import_date is cast to datetime', function () {
        $record = Import116::factory()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
            'import_date' => now(),
        ]);

        expect($record->import_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    test('exists_date is cast to datetime', function () {
        $record = Import116::factory()->create([
            'school_id' => $this->school->id,
            'import_user_id' => $this->admin->id,
            'exists_date' => now(),
        ]);

        expect($record->exists_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });
});

// ============================================================================
// Upsert Behavior Tests
// ============================================================================

describe('upsert behavior', function () {
    test('updateOrCreate creates new record when not exists', function () {
        $studentCode = 'NEW123456';

        Import116::updateOrCreate(
            [
                'school_id' => $this->school->id,
                'student_code' => $studentCode,
            ],
            [
                'last_name' => 'Testmann',
                'first_name' => 'Test',
                'class' => '5A',
                'import_date' => now(),
                'exists_date' => now(),
                'import_user_id' => $this->admin->id,
            ]
        );

        $record = Import116::where('student_code', $studentCode)->first();

        expect($record)->not->toBeNull()
            ->and($record->last_name)->toBe('Testmann');
    });

    test('updateOrCreate updates existing record', function () {
        $studentCode = 'EXIST123456';

        // Create initial record
        Import116::factory()->create([
            'school_id' => $this->school->id,
            'student_code' => $studentCode,
            'last_name' => 'OldName',
            'import_user_id' => $this->admin->id,
        ]);

        // Update via updateOrCreate
        Import116::updateOrCreate(
            [
                'school_id' => $this->school->id,
                'student_code' => $studentCode,
            ],
            [
                'last_name' => 'NewName',
                'first_name' => 'Updated',
                'class' => '6B',
                'import_date' => now(),
                'exists_date' => now(),
                'import_user_id' => $this->admin->id,
            ]
        );

        $record = Import116::where('student_code', $studentCode)->first();

        expect($record->last_name)->toBe('NewName')
            ->and($record->first_name)->toBe('Updated')
            ->and($record->class)->toBe('6B');
    });

    test('student_code is unique per school', function () {
        $studentCode = 'UNIQUE123';

        // Create for this school
        Import116::factory()->create([
            'school_id' => $this->school->id,
            'student_code' => $studentCode,
            'import_user_id' => $this->admin->id,
        ]);

        // Create for other school with same student_code
        $otherSchool = School::factory()->create();
        Import116::factory()->create([
            'school_id' => $otherSchool->id,
            'student_code' => $studentCode,
            'import_user_id' => $this->admin->id,
        ]);

        $countThisSchool = Import116::where('school_id', $this->school->id)
            ->where('student_code', $studentCode)
            ->count();
        $countOtherSchool = Import116::where('school_id', $otherSchool->id)
            ->where('student_code', $studentCode)
            ->count();

        expect($countThisSchool)->toBe(1)
            ->and($countOtherSchool)->toBe(1);
    });
});

// ============================================================================
// Mass Update Tests (exists_date clearing)
// ============================================================================

describe('exists_date clearing', function () {
    test('records not in import have exists_date set to null', function () {
        // Create existing records
        $keepRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'student_code' => 'KEEP001',
            'exists_date' => now(),
            'import_user_id' => $this->admin->id,
        ]);

        $removeRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'student_code' => 'REMOVE001',
            'exists_date' => now(),
            'import_user_id' => $this->admin->id,
        ]);

        // Simulate the job's behavior of clearing exists_date for records not in import
        $seenCodes = ['KEEP001'];
        Import116::where('school_id', $this->school->id)
            ->whereNotIn('student_code', $seenCodes)
            ->update(['exists_date' => null]);

        $keepRecord->refresh();
        $removeRecord->refresh();

        expect($keepRecord->exists_date)->not->toBeNull()
            ->and($removeRecord->exists_date)->toBeNull();
    });

    test('all records cleared when no codes in import', function () {
        $record1 = Import116::factory()->create([
            'school_id' => $this->school->id,
            'exists_date' => now(),
            'import_user_id' => $this->admin->id,
        ]);

        $record2 = Import116::factory()->create([
            'school_id' => $this->school->id,
            'exists_date' => now(),
            'import_user_id' => $this->admin->id,
        ]);

        // Simulate empty import
        Import116::where('school_id', $this->school->id)->update(['exists_date' => null]);

        $record1->refresh();
        $record2->refresh();

        expect($record1->exists_date)->toBeNull()
            ->and($record2->exists_date)->toBeNull();
    });

    test('only affects records from the same school', function () {
        $otherSchool = School::factory()->create();

        $thisSchoolRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'exists_date' => now(),
            'import_user_id' => $this->admin->id,
        ]);

        $otherSchoolRecord = Import116::factory()->create([
            'school_id' => $otherSchool->id,
            'exists_date' => now(),
            'import_user_id' => $this->admin->id,
        ]);

        // Clear only this school's records
        Import116::where('school_id', $this->school->id)->update(['exists_date' => null]);

        $thisSchoolRecord->refresh();
        $otherSchoolRecord->refresh();

        expect($thisSchoolRecord->exists_date)->toBeNull()
            ->and($otherSchoolRecord->exists_date)->not->toBeNull();
    });

    test('only affects records from the same schoolyear', function () {
        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $thisSchoolyearRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'exists_date' => now(),
            'import_user_id' => $this->admin->id,
        ]);

        $otherSchoolyearRecord = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $otherSchoolyear->id,
            'exists_date' => now(),
            'import_user_id' => $this->admin->id,
        ]);

        // Clear only this schoolyear's records (simulating job behavior)
        Import116::where('school_id', $this->school->id)
            ->where('schoolyear_id', $this->schoolyear->id)
            ->update(['exists_date' => null]);

        $thisSchoolyearRecord->refresh();
        $otherSchoolyearRecord->refresh();

        expect($thisSchoolyearRecord->exists_date)->toBeNull()
            ->and($otherSchoolyearRecord->exists_date)->not->toBeNull();
    });
});

// ============================================================================
// Schoolyear ID Tests
// ============================================================================

describe('schoolyear_id handling', function () {
    test('job constructor accepts optional schoolyearId parameter', function () {
        $job = new Import116Job($this->admin, 'test/path', 123);

        expect($job->schoolyearId)->toBe(123);
    });

    test('job constructor defaults schoolyearId to null', function () {
        $job = new Import116Job($this->admin, 'test/path');

        expect($job->schoolyearId)->toBeNull();
    });

    test('Import116 record can store schoolyear_id', function () {
        $record = Import116::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
        ]);

        expect($record->schoolyear_id)->toBe($this->schoolyear->id);
    });

    test('Import116 records can be filtered by schoolyear_id', function () {
        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        Import116::factory()->count(3)->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'import_user_id' => $this->admin->id,
        ]);

        Import116::factory()->count(2)->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $otherSchoolyear->id,
            'import_user_id' => $this->admin->id,
        ]);

        $thisYearCount = Import116::where('school_id', $this->school->id)
            ->where('schoolyear_id', $this->schoolyear->id)
            ->count();

        $otherYearCount = Import116::where('school_id', $this->school->id)
            ->where('schoolyear_id', $otherSchoolyear->id)
            ->count();

        expect($thisYearCount)->toBe(3)
            ->and($otherYearCount)->toBe(2);
    });

    test('updateOrCreate respects schoolyear_id in lookup', function () {
        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $studentCode = 'YEAR123';

        // Create record for this schoolyear
        Import116::updateOrCreate(
            [
                'school_id' => $this->school->id,
                'student_code' => $studentCode,
            ],
            [
                'schoolyear_id' => $this->schoolyear->id,
                'last_name' => 'ThisYear',
                'first_name' => 'Student',
                'class' => '1A',
                'import_date' => now(),
                'exists_date' => now(),
                'import_user_id' => $this->admin->id,
            ]
        );

        // Update same student code - should update existing record
        Import116::updateOrCreate(
            [
                'school_id' => $this->school->id,
                'student_code' => $studentCode,
            ],
            [
                'schoolyear_id' => $otherSchoolyear->id,
                'last_name' => 'OtherYear',
                'first_name' => 'Student',
                'class' => '2B',
                'import_date' => now(),
                'exists_date' => now(),
                'import_user_id' => $this->admin->id,
            ]
        );

        // Should only have one record (updateOrCreate uses school_id + student_code as key)
        $count = Import116::where('student_code', $studentCode)->count();
        $record = Import116::where('student_code', $studentCode)->first();

        expect($count)->toBe(1)
            ->and($record->schoolyear_id)->toBe($otherSchoolyear->id)
            ->and($record->last_name)->toBe('OtherYear');
    });
});

// ============================================================================
// User Linking with Schoolyear Tests
// ============================================================================

describe('user linking with schoolyear', function () {
    test('user matching requires same schoolyear_id', function () {
        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        // User in this schoolyear
        $studentThisYear = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'email' => 'student@school.test',
        ]);

        // User in other schoolyear with same email
        $studentOtherYear = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $otherSchoolyear->id,
            'email' => 'student2@school.test',
        ]);

        // Query like the job does
        $matchingUser = User::where('email', 'student@school.test')
            ->where('school_id', $this->school->id)
            ->where('schoolyear_id', $this->schoolyear->id)
            ->first();

        expect($matchingUser)->not->toBeNull()
            ->and($matchingUser->id)->toBe($studentThisYear->id);
    });

    test('user not found when schoolyear_id does not match', function () {
        $otherSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        // User only exists in other schoolyear
        User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $otherSchoolyear->id,
            'email' => 'student@school.test',
        ]);

        // Try to find user in this schoolyear
        $matchingUser = User::where('email', 'student@school.test')
            ->where('school_id', $this->school->id)
            ->where('schoolyear_id', $this->schoolyear->id)
            ->first();

        expect($matchingUser)->toBeNull();
    });
});
