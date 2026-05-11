<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEntry;
use App\Models\TeachingSchoolHour;
use App\Models\User;
use App\Services\AdminNavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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

it('returns dummy dashboard data for a licensed school', function () {
    $user = createStudentsTimetablesUserWithLicence();

    $this->actingAs($user)
        ->getJson('/api/admin/students-timetables')
        ->assertSuccessful()
        ->assertJsonPath('data.module', 'StudentsTimetables')
        ->assertJsonPath('data.status', 'dummy')
        ->assertJsonPath('data.school.id', $user->school_id);
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
