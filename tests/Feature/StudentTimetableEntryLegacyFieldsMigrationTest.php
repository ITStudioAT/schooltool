<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TimetableImport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('removes legacy timetable fields and consolidates their identity hashes', function () {
    Schema::table('student_timetable_entries', function (Blueprint $table) {
        $table->dropUnique('student_tt_entries_identity_unique');
        $table->string('teacher')->nullable()->after('subject');
        $table->string('room')->nullable()->after('teacher');
        $table->string('student_group')->nullable()->after('course');
    });

    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create(['school_id' => $school->id]);
    $import = TimetableImport::factory()->create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);
    $now = now();

    $baseEntry = [
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'timetable_import_id' => $import->id,
        'line_number' => 1,
        'date' => '2026-02-17',
        'semester' => 2,
        'source_identifier' => '82',
        'period' => '11',
        'starts_at' => '17:50',
        'ends_at' => '18:35',
        'subject' => 'PH',
        'teacher' => null,
        'room' => null,
        'class_name' => 'PH2-6A-ALT',
        'course' => 'PH',
        'module_code' => 'PH2',
        'is_active' => true,
        'raw_columns' => json_encode(['TT'], JSON_THROW_ON_ERROR),
        'raw_line' => 'TT',
        'created_at' => $now,
        'updated_at' => $now,
    ];

    DB::table('student_timetable_entries')->insert([
        [
            ...$baseEntry,
            'student_group' => 'A',
            'identity_hash' => hash('sha256', 'legacy-group-a'),
        ],
        [
            ...$baseEntry,
            'line_number' => 2,
            'starts_at' => '17:55',
            'student_group' => 'B',
            'identity_hash' => hash('sha256', 'legacy-group-b'),
        ],
        [
            ...$baseEntry,
            'line_number' => 3,
            'source_identifier' => 'legacy-layout',
            'starts_at' => null,
            'ends_at' => null,
            'student_group' => 'C',
            'identity_hash' => hash('sha256', 'legacy-without-times'),
        ],
    ]);

    Schema::table('student_timetable_entries', function (Blueprint $table) {
        $table->unique(['school_id', 'schoolyear_id', 'identity_hash'], 'student_tt_entries_identity_unique');
    });

    $migration = require database_path('migrations/2026_08_20_013900_remove_legacy_fields_from_student_timetable_entries_table.php');
    $migration->up();

    expect(Schema::hasColumn('student_timetable_entries', 'teacher'))->toBeFalse()
        ->and(Schema::hasColumn('student_timetable_entries', 'room'))->toBeFalse()
        ->and(Schema::hasColumn('student_timetable_entries', 'student_group'))->toBeFalse()
        ->and(DB::table('student_timetable_entries')->count())->toBe(1)
        ->and(DB::table('student_timetable_entries')->value('line_number'))->toBe(2)
        ->and(DB::table('student_timetable_entries')->value('starts_at'))->toBe('17:55')
        ->and(DB::table('student_timetable_entries')->value('identity_hash'))->toBe(hash('sha256', implode("\x1F", [
            $school->id,
            $schoolyear->id,
            '82',
            '2026-02-17',
            '11',
            'PH2-6A-ALT',
            'PH',
        ])));
});
