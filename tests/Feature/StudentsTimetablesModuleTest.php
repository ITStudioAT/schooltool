<?php

use App\Jobs\StudentsTimetables\ProcessTimetableUnimportJob;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEntry;
use App\Models\StudentTimetableSubjectMapping;
use App\Models\StudentTimetableSubjectRow;
use App\Models\TeachingSchoolHour;
use App\Models\TimetableImport;
use App\Models\User;
use App\Services\AdminNavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('registers StudentsTimetables as a configured licence', function () {
    $licence = collect(config('schooltool.licences'))
        ->firstWhere('name', 'StudentsTimetables');

    expect($licence)
        ->not->toBeNull()
        ->and($licence['long_name'])->toBe('Tool zum Verwalten von Schülerstundenplänen')
        ->and($licence['school_licence_enabled'])->toBeTrue();
});

it('shows the admin navigation item for an active StudentsTimetables school licence', function () {
    $user = createStudentsTimetablesUserWithLicence();

    Auth::login($user);

    $menu = app(AdminNavigationService::class)->dashboardMenu();
    $item = collect($menu)->firstWhere('title', 'Schülerstundenpläne');

    expect($item)
        ->not->toBeNull()
        ->and($item['to'])->toBe('/admin/students-timetables')
        ->and($item['is_active'])->toBeTrue();
});

it('allows the studentstimetables admin role to use the dummy module', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');

    Auth::login($user);

    $menu = app(AdminNavigationService::class)->dashboardMenu();
    $item = collect($menu)->firstWhere('title', 'Schülerstundenpläne');

    expect($item)
        ->not->toBeNull()
        ->and($item['to'])->toBe('/admin/students-timetables')
        ->and($item['is_active'])->toBeTrue();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables')
        ->assertSuccessful()
        ->assertJsonPath('data.module', 'StudentsTimetables')
        ->assertJsonPath('data.status', 'dummy');
});

it('allows the studentstimetables admin role to load admin home school infos', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');

    collect([
        'admin',
        'register_admin',
        'super_admin',
        'tutoring_admin',
        'teaching_admin',
        'materials_admin',
        'teacher',
    ])->each(fn (string $roleName): Role => Role::firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]));

    $this->actingAs($user)
        ->getJson('/api/admin/config?include_school_infos=1')
        ->assertSuccessful()
        ->assertJsonPath('is_auth', true)
        ->assertJsonStructure([
            'school_infos' => [
                'licences',
                'admins',
                'teachers',
            ],
        ]);

    $this->actingAs($user)
        ->postJson('/api/admin/schools/load_school_infos', [
            'school_id' => $user->school_id,
        ])
        ->assertSuccessful()
        ->assertJsonStructure([
            'licences',
            'admins',
            'teachers',
        ]);
});

it('allows the studentstimetables admin role to list and select schoolyears', function () {
    $user = createStudentsTimetablesUserWithLicence(roleName: 'studentstimetables_admin');
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'name' => '2026/2027',
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/schoolyears')
        ->assertSuccessful()
        ->assertJsonFragment([
            'id' => $schoolyear->id,
            'name' => '2026/2027',
        ]);

    $this->actingAs($user)
        ->postJson('/api/admin/schoolyears/set_active', [
            'schoolyear_id' => $schoolyear->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('id', $schoolyear->id);

    expect($user->refresh()->schoolyear_id)->toBe($schoolyear->id);
});

it('returns dummy dashboard data for a licensed school', function () {
    $user = createStudentsTimetablesUserWithLicence();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables')
        ->assertSuccessful()
        ->assertJsonPath('data.module', 'StudentsTimetables')
        ->assertJsonPath('data.status', 'dummy')
        ->assertJsonPath('data.school.id', $user->school_id);
});

it('stores a subject overview json file for the selected school', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    File::deleteDirectory(storage_path("app/private/{$user->school_id}/student-timetable-subjects"));

    $json = json_encode([
        'course_abbreviations' => [
            'ÖKO' => 'Ökonomie',
            'INF' => 'Informatik',
            'BU' => 'Biologie',
            'GS' => 'Geschichte',
            'L/F/S' => 'Latein / Französisch / Spanisch',
            'D' => 'Deutsch',
        ],
        'branches' => [
            'wirtschaftskundlich' => [
                'label' => 'Wirtschaftskundlicher Zweig',
            ],
            'gymnasial' => [
                'label' => 'Gymnasialer Zweig',
            ],
        ],
        'semesters' => [
            [
                'semester' => 1,
                'subjects' => [
                    ['short_name' => 'M', 'name' => 'Mathematik'],
                    ['short_name' => 'D', 'name' => 'Deutsch'],
                ],
            ],
            [
                'semester' => 2,
                'subjects' => [
                    ['short_name' => 'M', 'name' => 'Mathematik'],
                ],
            ],
            [
                'semester' => 3,
                'common_courses' => [
                    [
                        'code' => 'BU1',
                        'subject' => 'BU',
                        'hours_per_week' => 4,
                    ],
                    [
                        'code' => 'GS2',
                        'subject' => 'GS',
                        'hours_per_week' => 4,
                    ],
                ],
            ],
            [
                'semester' => 7,
                'common_courses' => [
                    [
                        'code' => 'D7',
                        'subject' => 'D',
                        'hours_per_week' => 4,
                    ],
                ],
                'branch_courses' => [
                    'wirtschaftskundlich' => [
                        [
                            'code' => 'ÖKO1',
                            'subject' => 'ÖKO',
                            'hours_per_week' => 2,
                        ],
                        [
                            'code' => 'INF2',
                            'subject' => 'INF',
                            'hours_per_week' => 3,
                        ],
                    ],
                    'gymnasial' => [
                        [
                            'code' => 'L/F/S6',
                            'subject' => 'L/F/S',
                            'hours_per_week' => 3,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $uploadId = $this->actingAs($user)
        ->withHeader('Upload-Name', 'faecher.json')
        ->post('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful()
        ->getContent();

    $response = $this->actingAs($user)
        ->call('PATCH', "/api/admin/students-timetables/subjects-overview-json?patch={$uploadId}", [], [], [], [
            'HTTP_UPLOAD_NAME' => 'faecher.json',
            'HTTP_UPLOAD_LENGTH' => strlen($json),
        ], $json);

    $response->assertSuccessful();

    $storedFilename = $response->getContent();
    $storedPath = storage_path("app/private/{$user->school_id}/student-timetable-subjects/{$schoolyear->id}/{$storedFilename}");

    expect($storedFilename)
        ->toStartWith('faecher_')
        ->and(str_ends_with($storedFilename, '.json'))->toBeTrue()
        ->and(File::exists($storedPath))->toBeTrue()
        ->and(json_decode(File::get($storedPath), true))->toHaveKey('semesters');

    $listingResponse = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.filename', $storedFilename)
        ->assertJsonPath('data.0.analysis.subjects_total', 8)
        ->assertJsonPath('data.0.analysis.semesters.0.label', '1. Semester')
        ->assertJsonPath('data.0.analysis.semesters.0.subjects_count', 2)
        ->assertJsonPath('data.0.analysis.semesters.0.subjects.0.name', 'Deutsch')
        ->assertJsonPath('data.0.analysis.semesters.0.branch_variants.0.key', 'common')
        ->assertJsonPath('data.0.analysis.semesters.0.branch_variants.0.label', '')
        ->assertJsonPath('data.0.analysis.semesters.0.branch_variants.0.common_subjects.0.name', 'Deutsch')
        ->assertJsonPath('data.0.analysis.semesters.0.branch_variants.0.different_subjects', [])
        ->assertJsonPath('data.0.analysis.semesters.1.label', '2. Semester')
        ->assertJsonPath('data.0.analysis.semesters.1.subjects_count', 1)
        ->assertJsonPath('data.0.analysis.semesters.1.branch_variants.0.key', 'common')
        ->assertJsonPath('data.0.analysis.semesters.2.label', '3. Semester')
        ->assertJsonPath('data.0.analysis.semesters.2.subjects_count', 2)
        ->assertJsonPath('data.0.analysis.semesters.2.subjects.0.name', 'Biologie 1')
        ->assertJsonPath('data.0.analysis.semesters.2.subjects.0.short_name', 'BU1')
        ->assertJsonPath('data.0.analysis.semesters.2.branch_variants.0.key', 'common')
        ->assertJsonPath('data.0.analysis.semesters.3.label', '7. Semester')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.0.key', 'common')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.0.label', '')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.0.subjects_count', 1)
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.0.subjects.0.short_name', 'D7')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.0.common_subjects.0.short_name', 'D7')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.0.different_subjects', [])
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.1.key', 'wirtschaftskundlich')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.1.label', 'Wirtschaftskundlicher Zweig')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.1.subjects_count', 2)
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.1.subjects.0.short_name', 'INF2')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.1.subjects.1.short_name', 'ÖKO1')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.1.common_subjects', [])
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.1.different_subjects.0.short_name', 'INF2')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.1.different_subjects.1.short_name', 'ÖKO1')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.2.key', 'gymnasial')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.2.label', 'Gymnasialer Zweig')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.2.subjects_count', 1)
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.2.subjects.0.short_name', 'L/F/S6')
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.2.common_subjects', [])
        ->assertJsonPath('data.0.analysis.semesters.3.branch_variants.2.different_subjects.0.short_name', 'L/F/S6')
        ->assertJsonPath('data.0.analysis.branches.0.key', 'wirtschaftskundlich')
        ->assertJsonPath('data.0.analysis.branches.0.label', 'Wirtschaftskundlicher Zweig')
        ->assertJsonPath('data.0.analysis.branches.0.subjects_total', 2)
        ->assertJsonPath('data.0.analysis.branches.0.semesters.0.label', '7. Semester')
        ->assertJsonPath('data.0.analysis.branches.0.semesters.0.subjects.0.short_name', 'INF2')
        ->assertJsonPath('data.0.analysis.branches.1.key', 'gymnasial')
        ->assertJsonPath('data.0.analysis.branches.1.label', 'Gymnasialer Zweig')
        ->assertJsonPath('data.0.analysis.branches.1.subjects_total', 1)
        ->assertJsonPath('data.0.analysis.branches.1.semesters.0.subjects.0.short_name', 'L/F/S6')
        ->assertJsonPath('data.0.analysis.subject_rows.0.semester', 1)
        ->assertJsonPath('data.0.analysis.subject_rows.0.json_code', 'D')
        ->assertJsonPath('data.0.analysis.subject_rows.4.json_code', 'GS2')
        ->assertJsonPath('data.0.analysis.subject_rows.4.json_subject', 'GS')
        ->assertJsonPath('data.0.analysis.subject_rows.4.name', 'Geschichte 2')
        ->assertJsonPath('data.0.analysis.subject_rows.4.hours_per_week', 4);

    $semesters = $listingResponse->json('data.0.analysis.semesters');

    expect($semesters[0]['branch_variants'])
        ->toHaveCount(1)
        ->and($semesters[1]['branch_variants'])->toHaveCount(1)
        ->and($semesters[2]['branch_variants'])->toHaveCount(1)
        ->and($semesters[3]['branch_variants'])->toHaveCount(3);

    StudentTimetableSubjectRow::query()
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $schoolyear->id)
        ->where('json_code', 'GS2')
        ->update([
            'name' => 'GS',
            'source' => 'manual',
        ]);

    $settingsResponse = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-settings')
        ->assertSuccessful()
        ->assertJsonPath('data.subjects.0.semester', 1)
        ->assertJsonPath('data.subjects.0.branch', null)
        ->assertJsonPath('data.subjects.0.json_code', 'D')
        ->assertJsonPath('data.subjects.4.name', 'Geschichte 2')
        ->assertJsonPath('data.mappings.0.json_subject', 'GS')
        ->assertJsonPath('data.mappings.0.tt_subject', 'GPB')
        ->assertJsonPath('data.mappings.0.note', null);

    expect(StudentTimetableSubjectRow::query()
        ->where('school_id', $user->school_id)
        ->where('schoolyear_id', $schoolyear->id)
        ->count())->toBe(9)
        ->and(StudentTimetableSubjectRow::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyear->id)
            ->where('json_code', 'D')
            ->value('branch'))->toBeNull()
        ->and(StudentTimetableSubjectMapping::query()
            ->where('school_id', $user->school_id)
            ->where('schoolyear_id', $schoolyear->id)
            ->pluck('tt_subject', 'json_subject')
            ->all())->toMatchArray([
                'GS' => 'GPB',
                'ÖKO' => 'OKON',
            ]);

    $subjects = $settingsResponse->json('data.subjects');
    $subjects[0]['name'] = 'Deutsch manuell';
    $subjects[0]['is_active'] = false;

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/subjects', [
            'subjects' => $subjects,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.subjects.0.name', 'Deutsch manuell')
        ->assertJsonPath('data.subjects.0.is_active', false);

    $this->actingAs($user)
        ->putJson('/api/admin/students-timetables/subjects-overview-settings/mappings', [
            'mappings' => [
                [
                    'json_subject' => 'GW',
                    'tt_subject' => 'GWB',
                    'note' => 'manuell',
                    'is_active' => true,
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.mappings.0.json_subject', 'GW')
        ->assertJsonPath('data.mappings.0.tt_subject', 'GWB')
        ->assertJsonPath('data.mappings.0.note', 'manuell');
});

it('keeps only the latest subject overview json import for a schoolyear', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    File::deleteDirectory(storage_path("app/private/{$user->school_id}/student-timetable-subjects"));

    $firstJson = json_encode([
        'semesters' => [
            [
                'semester' => 1,
                'subjects' => [
                    ['short_name' => 'D', 'name' => 'Deutsch'],
                ],
            ],
        ],
    ]);

    $firstUploadId = $this->actingAs($user)
        ->withHeader('Upload-Name', 'faecher-alt.json')
        ->post('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful()
        ->getContent();

    $firstFilename = $this->actingAs($user)
        ->call('PATCH', "/api/admin/students-timetables/subjects-overview-json?patch={$firstUploadId}", [], [], [], [
            'HTTP_UPLOAD_NAME' => 'faecher-alt.json',
            'HTTP_UPLOAD_LENGTH' => strlen($firstJson),
        ], $firstJson)
        ->assertSuccessful()
        ->getContent();

    $secondJson = json_encode([
        'semesters' => [
            [
                'semester' => 2,
                'subjects' => [
                    ['short_name' => 'M', 'name' => 'Mathematik'],
                ],
            ],
        ],
    ]);

    $secondUploadId = $this->actingAs($user)
        ->withHeader('Upload-Name', 'faecher-neu.json')
        ->post('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful()
        ->getContent();

    $secondFilename = $this->actingAs($user)
        ->call('PATCH', "/api/admin/students-timetables/subjects-overview-json?patch={$secondUploadId}", [], [], [], [
            'HTTP_UPLOAD_NAME' => 'faecher-neu.json',
            'HTTP_UPLOAD_LENGTH' => strlen($secondJson),
        ], $secondJson)
        ->assertSuccessful()
        ->getContent();

    $storedDirectory = storage_path("app/private/{$user->school_id}/student-timetable-subjects/{$schoolyear->id}");

    expect(File::exists("{$storedDirectory}/{$firstFilename}"))->toBeFalse()
        ->and(File::exists("{$storedDirectory}/{$secondFilename}"))->toBeTrue()
        ->and(File::glob("{$storedDirectory}/*.json"))->toHaveCount(1);

    $stalePath = "{$storedDirectory}/faecher-stale_20260101_000000.json";
    File::put($stalePath, $firstJson);
    touch($stalePath, now()->subHour()->timestamp);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.filename', $secondFilename)
        ->assertJsonPath('data.0.analysis.semesters.0.label', '2. Semester')
        ->assertJsonPath('data.0.analysis.semesters.0.subjects.0.name', 'Mathematik');

    expect(File::exists($stalePath))->toBeFalse()
        ->and(File::glob("{$storedDirectory}/*.json"))->toHaveCount(1);
});

it('expands compact multi-module subject codes in subject overview json imports', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    File::deleteDirectory(storage_path("app/private/{$user->school_id}/student-timetable-subjects"));

    $json = json_encode([
        'course_abbreviations' => [
            'ÖKO' => 'Ökonomie',
        ],
        'semesters' => [
            [
                'semester' => 8,
                'common_courses' => [
                    [
                        'code' => 'ÖKO23',
                        'subject' => 'ÖKO',
                        'hours_per_week' => 4,
                    ],
                    [
                        'code' => 'ÖKO2/ÖKO3',
                        'subject' => 'ÖKO',
                        'hours_per_week' => 4,
                    ],
                ],
            ],
        ],
    ]);

    $uploadId = $this->actingAs($user)
        ->withHeader('Upload-Name', 'faecher.json')
        ->post('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful()
        ->getContent();

    $this->actingAs($user)
        ->call('PATCH', "/api/admin/students-timetables/subjects-overview-json?patch={$uploadId}", [], [], [], [
            'HTTP_UPLOAD_NAME' => 'faecher.json',
            'HTTP_UPLOAD_LENGTH' => strlen($json),
        ], $json)
        ->assertSuccessful();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful()
        ->assertJsonPath('data.0.analysis.subject_rows.0.json_code', 'ÖKO2')
        ->assertJsonPath('data.0.analysis.subject_rows.0.name', 'Ökonomie 2')
        ->assertJsonPath('data.0.analysis.subject_rows.1.json_code', 'ÖKO3')
        ->assertJsonPath('data.0.analysis.subject_rows.1.name', 'Ökonomie 3');

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/subjects-overview-settings')
        ->assertSuccessful()
        ->assertJsonPath('data.subjects.0.json_code', 'ÖKO2')
        ->assertJsonPath('data.subjects.0.name', 'Ökonomie 2')
        ->assertJsonPath('data.subjects.1.json_code', 'ÖKO3')
        ->assertJsonPath('data.subjects.1.name', 'Ökonomie 3');
});

it('rejects invalid subject overview json uploads', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    File::deleteDirectory(storage_path("app/private/{$user->school_id}/student-timetable-subjects"));

    $contents = '{invalid';

    $uploadId = $this->actingAs($user)
        ->withHeader('Upload-Name', 'faecher.json')
        ->post('/api/admin/students-timetables/subjects-overview-json')
        ->assertSuccessful()
        ->getContent();

    $this->actingAs($user)
        ->call('PATCH', "/api/admin/students-timetables/subjects-overview-json?patch={$uploadId}", [], [], [], [
            'HTTP_UPLOAD_NAME' => 'faecher.json',
            'HTTP_UPLOAD_LENGTH' => strlen($contents),
        ], $contents)
        ->assertUnprocessable();

    $storedDirectory = storage_path("app/private/{$user->school_id}/student-timetable-subjects/{$schoolyear->id}");

    expect(File::glob("{$storedDirectory}/*.json"))->toBe([]);
});

it('returns school hours for the selected schoolyear', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    TeachingSchoolHour::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'hour' => 1,
        'from' => '08:00:00',
        'until' => '08:45:00',
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/school-hours')
        ->assertSuccessful()
        ->assertJsonPath('data.0.hour', 1)
        ->assertJsonPath('data.0.from', '08:00')
        ->assertJsonPath('data.0.until', '08:45');
});

it('returns the requested timetable import history for the selected schoolyear', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $otherSchoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $imports = collect(range(1, 12))->map(fn (int $index): TimetableImport => TimetableImport::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'original_filename' => "stundenplan-{$index}.txt",
        'imported_at' => now()->subMinutes($index),
    ]));

    TimetableImport::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $otherSchoolyear->id,
    ]);

    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'timetable_import_id' => $imports->first()->id,
        'date' => '2026-02-16',
        'period' => '1',
        'starts_at' => '08:00',
        'class_name' => 'PH2-6A-ALT',
    ]);
    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'timetable_import_id' => $imports->first()->id,
        'date' => '2026-02-16',
        'period' => '2',
        'starts_at' => '08:50',
        'class_name' => 'PH2-6A-ALT',
    ]);
    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'timetable_import_id' => $imports->first()->id,
        'date' => '2026-07-11',
        'period' => '1',
        'class_name' => 'M2-2A-ALT',
    ]);
    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'timetable_import_id' => $imports->first()->id,
        'date' => '2026-07-04',
        'period' => '1',
        'class_name' => 'M2-2A-ALT',
    ]);
    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $otherSchoolyear->id,
        'date' => '2026-01-01',
        'class_name' => 'OTHER',
    ]);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/imports?per_page=20')
        ->assertSuccessful()
        ->assertJsonCount(12, 'data')
        ->assertJsonPath('per_page', 20)
        ->assertJsonPath('main_dataset.table', 'student_timetable_entries')
        ->assertJsonPath('main_dataset.entries_count', 4)
        ->assertJsonPath('main_dataset.courses_count', 2)
        ->assertJsonPath('main_dataset.first_date', '2026-02-16')
        ->assertJsonPath('main_dataset.last_date', '2026-07-11')
        ->assertJsonPath('main_dataset.courses.0.name', 'M2-2A-ALT')
        ->assertJsonPath('main_dataset.courses.0.entries_count', 2)
        ->assertJsonPath('main_dataset.courses.0.weekly_hours', 1)
        ->assertJsonPath('main_dataset.courses.0.first_date', '2026-07-04')
        ->assertJsonPath('main_dataset.courses.0.last_date', '2026-07-11')
        ->assertJsonPath('main_dataset.courses.1.name', 'PH2-6A-ALT')
        ->assertJsonPath('main_dataset.courses.1.entries_count', 2)
        ->assertJsonPath('main_dataset.courses.1.weekly_hours', 2);
});

it('unimports a timetable import run and removes its associated entries', function () {
    Queue::fake();

    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    $import = TimetableImport::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
    ]);

    $entry = StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'timetable_import_id' => $import->id,
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/admin/students-timetables/imports/{$import->id}")
        ->assertAccepted()
        ->assertJsonPath('message', 'Import-Löschung wurde in die Warteschlange gestellt.')
        ->assertJsonPath('data.import_status', 'deleting');

    $this->assertDatabaseHas('timetable_imports', [
        'id' => $import->id,
        'import_status' => 'deleting',
    ]);
    $this->assertDatabaseHas('student_timetable_entries', ['id' => $entry->id]);

    Queue::assertPushed(
        ProcessTimetableUnimportJob::class,
        fn (ProcessTimetableUnimportJob $job): bool => $job->timetableImportId === $import->id,
    );
});

it('returns grouped timetable courses with recurrence and block markers', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2026-09-07',
        'sem_2_start' => '2026-10-20',
        'until' => '2027-02-14',
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    foreach (['2026-09-07', '2026-09-14', '2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19'] as $date) {
        StudentTimetableEntry::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'date' => $date,
            'semester' => 1,
            'period' => '1',
            'course' => 'MATH',
            'subject' => 'Mathematik',
            'teacher' => 'AB',
            'room' => '101',
            'class_name' => '1A',
        ]);
    }

    foreach (['2026-09-08', '2026-09-22', '2026-10-06'] as $date) {
        StudentTimetableEntry::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'date' => $date,
            'semester' => 1,
            'period' => '2',
            'course' => 'BIO',
            'subject' => 'Biologie',
            'teacher' => 'CD',
            'room' => '202',
            'class_name' => '1A',
        ]);
    }

    foreach (['2026-09-30', '2026-10-07'] as $date) {
        StudentTimetableEntry::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'date' => $date,
            'semester' => 1,
            'period' => '3',
            'course' => 'CHEM',
            'subject' => 'Chemie',
            'teacher' => 'EF',
            'room' => '303',
            'class_name' => '1A',
        ]);
    }

    foreach (['2026-09-11', '2026-10-02'] as $date) {
        StudentTimetableEntry::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'date' => $date,
            'semester' => 1,
            'period' => '4',
            'course' => 'GEO',
            'subject' => 'Geografie',
            'teacher' => 'GH',
            'room' => '404',
            'class_name' => '1A',
        ]);
    }

    foreach (['2026-09-11', '2026-10-09'] as $date) {
        StudentTimetableEntry::factory()->create([
            'school_id' => $user->school_id,
            'schoolyear_id' => $schoolyear->id,
            'date' => $date,
            'semester' => 1,
            'period' => '5',
            'course' => 'HIST',
            'subject' => 'Geschichte',
            'teacher' => 'IJ',
            'room' => '505',
            'class_name' => '1A',
        ]);
    }

    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2026-09-10',
        'semester' => 1,
        'period' => '6',
        'course' => 'SprStd',
        'subject' => 'SprStd',
        'teacher' => 'KL',
        'room' => '606',
        'class_name' => '1A',
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/admin/students-timetables/course-groups')
        ->assertSuccessful();

    $groups = collect($response->json('data'));
    $math = $groups->firstWhere('title', 'MATH');
    $bio = $groups->firstWhere('title', 'BIO');
    $chem = $groups->firstWhere('title', 'CHEM');
    $geo = $groups->firstWhere('title', 'GEO');
    $history = $groups->firstWhere('title', 'HIST');

    expect($groups)->toHaveCount(5)
        ->and($groups->firstWhere('title', 'SprStd'))->toBeNull()
        ->and($math['recurrence_type'])->toBe('weekly')
        ->and($math['recurrence_label'])->toBe('1-wöchig')
        ->and($math['display_label'])->toBe('MATH1AAB')
        ->and($math['dates'])->toBe(['2026-09-07', '2026-09-14', '2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19'])
        ->and($math['is_block'])->toBeFalse()
        ->and($bio['recurrence_type'])->toBe('every_2_weeks')
        ->and($bio['recurrence_label'])->toBe('2-wöchig')
        ->and($geo['recurrence_type'])->toBe('every_3_weeks')
        ->and($geo['recurrence_label'])->toBe('3-wöchig')
        ->and($history['recurrence_type'])->toBe('every_4_weeks')
        ->and($history['recurrence_label'])->toBe('4-wöchig')
        ->and($chem['is_block'])->toBeTrue()
        ->and($chem['block_label'])->toBe('Block');
});

it('does not duplicate the course prefix in timetable display labels', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2026-09-07',
        'sem_2_start' => '2026-10-20',
        'until' => '2027-02-14',
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2026-09-07',
        'semester' => 1,
        'period' => '1',
        'course' => 'ETH',
        'subject' => 'ETH',
        'teacher' => 'RU-HER',
        'room' => '14:305R~5U',
        'class_name' => 'ETH3-5',
    ]);

    $groups = collect($this->actingAs($user)
        ->getJson('/api/admin/students-timetables/course-groups')
        ->assertSuccessful()
        ->json('data'));

    expect($groups->firstWhere('title', 'ETH')['display_label'])
        ->toBe('ETH3 - 5RU - HER');
});

it('removes embedded end times from timetable display labels', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2026-09-07',
        'sem_2_start' => '2026-10-20',
        'until' => '2027-02-14',
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2026-09-07',
        'semester' => 1,
        'period' => '1',
        'course' => 'GPB',
        'subject' => 'GPB',
        'teacher' => 'S-DREI15:302S',
        'room' => null,
        'class_name' => 'GPB2-2',
    ]);

    $groups = collect($this->actingAs($user)
        ->getJson('/api/admin/students-timetables/course-groups')
        ->assertSuccessful()
        ->json('data'));

    expect($groups->firstWhere('title', 'GPB')['display_label'])
        ->toBe('GPB2 - 2S - DREI');
});

it('removes end times from fully concatenated timetable labels', function () {
    $user = createStudentsTimetablesUserWithLicence();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $user->school_id,
        'from' => '2026-09-07',
        'sem_2_start' => '2026-10-20',
        'until' => '2027-02-14',
    ]);
    $user->forceFill(['schoolyear_id' => $schoolyear->id])->save();

    StudentTimetableEntry::factory()->create([
        'school_id' => $user->school_id,
        'schoolyear_id' => $schoolyear->id,
        'date' => '2026-09-07',
        'semester' => 1,
        'period' => '1',
        'course' => 'ETH',
        'subject' => 'ETH',
        'teacher' => null,
        'room' => null,
        'class_name' => 'ETH4-5RU-HER15:305R~5U',
    ]);

    $groups = collect($this->actingAs($user)
        ->getJson('/api/admin/students-timetables/course-groups')
        ->assertSuccessful()
        ->json('data'));

    expect($groups->firstWhere('title', 'ETH')['display_label'])
        ->toBe('ETH4 - 5RU - HER');
});

it('denies the dummy dashboard without a school licence', function () {
    $user = createStudentsTimetablesUserWithLicence(withLicence: false);

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables')
        ->assertForbidden();
});

function createStudentsTimetablesUserWithLicence(bool $withLicence = true, string $roleName = 'admin'): User
{
    $school = School::factory()->create([
        'long_name' => 'Abendgymnasium',
        'short_name' => 'abendgym',
    ]);

    SchoolTool::factory()->create([
        'school_id' => $school->id,
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

    if ($withLicence) {
        SchoolLicence::query()->create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addMonth()->toDateString(),
        ]);
    }

    Role::firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create([
        'school_id' => $school->id,
    ]);
    $user->assignRole($roleName);

    return $user;
}
