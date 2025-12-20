<?php

use App\Events\TeachersListImportFinishedEvent;
use App\Jobs\ImportTeachersListJob;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Event::fake();
    Storage::fake('local');

    // Create test school and schoolyear
    $this->school = School::factory()->create([
        'short_name' => 'TestSchool',
        'long_name' => 'Test School Name',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);

    // Create test user
    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
});

describe('handle', function () {
    it('imports teachers from valid Excel file', function () {
        // Create Excel file
        $path = 'app/test/teachers.xlsx';
        $fullPath = storage_path($path);
        Storage::disk('local')->makeDirectory('app/test');

        $writer = SimpleExcelWriter::create(storage_path($path));
        $writer->addRows([
            ['Kurz' => 'KRO', 'Nachname' => 'Kron', 'Vorname' => 'Max', 'Email' => 'kron@example.com'],
            ['Kurz' => 'MUS', 'Nachname' => 'Mueller', 'Vorname' => 'Maria', 'Email' => 'mueller@example.com'],
        ]);

        $job = new ImportTeachersListJob($this->user, $path);
        $job->handle();

        expect(Teacher::count())->toBe(2);

        $teacher1 = Teacher::where('email', 'kron@example.com')->first();
        expect($teacher1->short)->toBe('KRO')
            ->and($teacher1->last_name)->toBe('Kron')
            ->and($teacher1->first_name)->toBe('Max')
            ->and($teacher1->school_id)->toBe($this->school->id);
    });

    it('converts short to uppercase', function () {
        $path = 'app/test/teachers.xlsx';
        Storage::disk('local')->makeDirectory('app/test');

        $writer = SimpleExcelWriter::create(storage_path($path));
        $writer->addRows([
            ['Kurz' => 'kro', 'Nachname' => 'Kron', 'Vorname' => 'Max', 'Email' => 'kron@example.com'],
        ]);

        $job = new ImportTeachersListJob($this->user, $path);
        $job->handle();

        $teacher = Teacher::where('email', 'kron@example.com')->first();
        expect($teacher->short)->toBe('KRO');
    });

    it('updates existing teacher by email', function () {
        // Create existing teacher
        Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'OLD',
            'first_name' => 'OldFirst',
            'last_name' => 'OldLast',
            'email' => 'kron@example.com',
        ]);

        $path = 'app/test/teachers.xlsx';
        Storage::disk('local')->makeDirectory('app/test');

        $writer = SimpleExcelWriter::create(storage_path($path));
        $writer->addRows([
            ['Kurz' => 'KRO', 'Nachname' => 'Kron', 'Vorname' => 'Max', 'Email' => 'kron@example.com'],
        ]);

        $job = new ImportTeachersListJob($this->user, $path);
        $job->handle();

        expect(Teacher::count())->toBe(1);

        $teacher = Teacher::where('email', 'kron@example.com')->first();
        expect($teacher->short)->toBe('KRO')
            ->and($teacher->last_name)->toBe('Kron')
            ->and($teacher->first_name)->toBe('Max');
    });

    it('deletes teachers not in Excel file', function () {
        // Create existing teachers
        Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'KRO',
            'first_name' => 'Max',
            'last_name' => 'Kron',
            'email' => 'kron@example.com',
        ]);

        Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'DEL',
            'first_name' => 'Delete',
            'last_name' => 'Me',
            'email' => 'delete@example.com',
        ]);

        $path = 'app/test/teachers.xlsx';
        Storage::disk('local')->makeDirectory('app/test');

        // Excel only contains KRO
        $writer = SimpleExcelWriter::create(storage_path($path));
        $writer->addRows([
            ['Kurz' => 'KRO', 'Nachname' => 'Kron', 'Vorname' => 'Max', 'Email' => 'kron@example.com'],
        ]);

        $job = new ImportTeachersListJob($this->user, $path);
        $job->handle();

        expect(Teacher::count())->toBe(1);
        expect(Teacher::where('email', 'delete@example.com')->exists())->toBeFalse();
    });

    it('does not delete teachers from other schools', function () {
        $otherSchool = School::factory()->create();

        // Create teacher in other school
        Teacher::create([
            'school_id' => $otherSchool->id,
            'short' => 'OTH',
            'first_name' => 'Other',
            'last_name' => 'School',
            'email' => 'other@example.com',
        ]);

        $path = 'app/test/teachers.xlsx';
        Storage::disk('local')->makeDirectory('app/test');

        $writer = SimpleExcelWriter::create(storage_path($path));
        $writer->addRows([
            ['Kurz' => 'KRO', 'Nachname' => 'Kron', 'Vorname' => 'Max', 'Email' => 'kron@example.com'],
        ]);

        $job = new ImportTeachersListJob($this->user, $path);
        $job->handle();

        // Other school's teacher should still exist
        expect(Teacher::where('school_id', $otherSchool->id)->exists())->toBeTrue();
    });

    it('broadcasts success event with correct counts', function () {
        $path = 'app/test/teachers.xlsx';
        Storage::disk('local')->makeDirectory('app/test');

        // Create one existing teacher to be updated
        Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'KRO',
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'kron@example.com',
        ]);

        // Create one teacher to be deleted
        Teacher::create([
            'school_id' => $this->school->id,
            'short' => 'DEL',
            'first_name' => 'Delete',
            'last_name' => 'Me',
            'email' => 'delete@example.com',
        ]);

        $writer = SimpleExcelWriter::create(storage_path($path));
        $writer->addRows([
            ['Kurz' => 'KRO', 'Nachname' => 'Kron', 'Vorname' => 'Max', 'Email' => 'kron@example.com'], // Updated
            ['Kurz' => 'NEW', 'Nachname' => 'New', 'Vorname' => 'Teacher', 'Email' => 'new@example.com'], // Created
        ]);

        $job = new ImportTeachersListJob($this->user, $path);
        $job->handle();

        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return $event->status === 200
                && $event->userId === $this->user->id
                && $event->data['created'] === 1
                && $event->data['updated'] === 1
                && $event->data['deleted'] === 1;
        });
    });

    it('supports alternative German column headers', function () {
        $path = 'app/test/teachers.xlsx';
        Storage::disk('local')->makeDirectory('app/test');

        $writer = SimpleExcelWriter::create(storage_path($path));
        $writer->addRows([
            ['Kürzel' => 'KRO', 'Nachname' => 'Kron', 'Vorname' => 'Max', 'E-Mail' => 'kron@example.com'],
        ]);

        $job = new ImportTeachersListJob($this->user, $path);
        $job->handle();

        expect(Teacher::count())->toBe(1);
        $teacher = Teacher::first();
        expect($teacher->short)->toBe('KRO');
    });

    it('supports English column headers', function () {
        $path = 'app/test/teachers.xlsx';
        Storage::disk('local')->makeDirectory('app/test');

        $writer = SimpleExcelWriter::create(storage_path($path));
        $writer->addRows([
            ['Short' => 'KRO', 'Surname' => 'Kron', 'FirstName' => 'Max', 'Email' => 'kron@example.com'],
        ]);

        $job = new ImportTeachersListJob($this->user, $path);
        $job->handle();

        expect(Teacher::count())->toBe(1);
        $teacher = Teacher::first();
        expect($teacher->short)->toBe('KRO');
    });

    it('broadcasts error event when headers are invalid', function () {
        $path = 'app/test/teachers.xlsx';
        Storage::disk('local')->makeDirectory('app/test');

        $writer = SimpleExcelWriter::create(storage_path($path));
        $writer->addRows([
            ['InvalidColumn' => 'KRO', 'Another' => 'Kron', 'Bad' => 'Max', 'Wrong' => 'kron@example.com'],
        ]);

        $job = new ImportTeachersListJob($this->user, $path);
        $job->handle();

        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return $event->status === 500
                && $event->userId === $this->user->id
                && str_contains($event->message, 'nicht korrekt');
        });

        expect(Teacher::count())->toBe(0);
    });

    it('is case insensitive for column headers', function () {
        $path = 'app/test/teachers.xlsx';
        Storage::disk('local')->makeDirectory('app/test');

        $writer = SimpleExcelWriter::create(storage_path($path));
        $writer->addRows([
            ['KURZ' => 'KRO', 'NACHNAME' => 'Kron', 'VORNAME' => 'Max', 'EMAIL' => 'kron@example.com'],
        ]);

        $job = new ImportTeachersListJob($this->user, $path);
        $job->handle();

        expect(Teacher::count())->toBe(1);
    });

    it('trims whitespace from column headers', function () {
        $path = 'app/test/teachers.xlsx';
        Storage::disk('local')->makeDirectory('app/test');

        $writer = SimpleExcelWriter::create(storage_path($path));
        $writer->addRows([
            [' Kurz ' => 'KRO', ' Nachname ' => 'Kron', ' Vorname ' => 'Max', ' Email ' => 'kron@example.com'],
        ]);

        $job = new ImportTeachersListJob($this->user, $path);
        $job->handle();

        expect(Teacher::count())->toBe(1);
    });

    it('includes success message in event', function () {
        $path = 'app/test/teachers.xlsx';
        Storage::disk('local')->makeDirectory('app/test');

        $writer = SimpleExcelWriter::create(storage_path($path));
        $writer->addRows([
            ['Kurz' => 'KRO', 'Nachname' => 'Kron', 'Vorname' => 'Max', 'Email' => 'kron@example.com'],
        ]);

        $job = new ImportTeachersListJob($this->user, $path);
        $job->handle();

        Event::assertDispatched(TeachersListImportFinishedEvent::class, function ($event) {
            return str_contains($event->message, 'erfolgreich importiert');
        });
    });
});
