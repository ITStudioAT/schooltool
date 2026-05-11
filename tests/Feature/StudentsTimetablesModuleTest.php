<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
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
