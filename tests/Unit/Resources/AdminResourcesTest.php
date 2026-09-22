<?php

use App\Http\Resources\Admin\LicenceResource as AdminLicenceResource;
use App\Http\Resources\Admin\PaginateResource;
use App\Http\Resources\Admin\RegisterDateBookingResource as AdminRegisterDateBookingResource;
use App\Http\Resources\Admin\RegisterDateResource as AdminRegisterDateResource;
use App\Http\Resources\Admin\RegisterResource as AdminRegisterResource;
use App\Http\Resources\Admin\RegisterUserResource;
use App\Http\Resources\Admin\RoleResource;
use App\Http\Resources\Admin\SchoolResource as AdminSchoolResource;
use App\Http\Resources\Admin\SchoolToolResource;
use App\Http\Resources\Admin\SchoolyearResource as AdminSchoolyearResource;
use App\Http\Resources\Admin\TeacherResource;
use App\Http\Resources\Admin\TeachersListResource;
use App\Http\Resources\Admin\Teaching\Import116Resource;
use App\Http\Resources\Admin\UserResource as AdminUserResource;
use App\Http\Resources\Admin\UserWithRoleResource;
use App\Models\Import116;
use App\Models\Licence;
use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('admin licence resource exposes pivot fields and booleans', function () {
    $school = School::factory()->create();
    $licence = Licence::create([
        'name' => 'Tool',
        'long_name' => 'Tool Long',
        'price_per_year' => 100,
        'start_day_month' => '09-01',
        'end_day_month' => '07-31',
        'is_selectable' => 0,
    ]);
    $school->licences()->attach($licence->id, ['valid_until' => '2030-01-01']);

    $licence = $school->licences()->first();

    $data = (new AdminLicenceResource($licence))->toArray(request());

    expect($data['name'])->toBe('Tool')
        ->and($data['valid_until'])->toBe('2030-01-01')
        ->and($data['start_day_month'])->toBe('01.09.')
        ->and($data['end_day_month'])->toBe('31.07.')
        ->and($data['is_selectable'])->toBeFalse()
        ->and(array_key_exists('school_licence_id', $data))->toBeTrue();
});

test('admin licence resource normalizes whole-number decimal prices for editing', function () {
    $licence = Licence::create([
        'name' => 'Tool Decimal',
        'long_name' => 'Tool Decimal Long',
        'price_per_year' => '200.00',
        'is_selectable' => 1,
    ]);

    $data = (new AdminLicenceResource($licence))->toArray(request());

    expect($data['price_per_year'])->toBe(200);
});

test('admin licence resource exposes structured storage tariff fields in licence model', function () {
    $licence = Licence::create([
        'name' => 'Storage Tool',
        'long_name' => 'Storage Tool Long',
        'licence_schema_version' => 2,
        'school_licence_enabled' => true,
        'school_price_per_year' => '199',
        'school_included_storage_gb' => 10,
        'school_extra_storage_step_gb' => 100,
        'school_extra_storage_step_price' => '5.00',
        'admin_licence_enabled' => true,
        'admin_price_per_year' => '10',
        'admin_role_names' => ['admin'],
        'admin_included_storage_gb' => 10,
        'admin_extra_storage_step_gb' => 100,
        'admin_extra_storage_step_price' => '5.00',
        'user_licence_enabled' => true,
        'user_price_per_year' => '5',
        'user_role_names' => ['teacher'],
        'user_included_storage_gb' => 20,
        'user_extra_storage_step_gb' => 100,
        'user_extra_storage_step_price' => '5.00',
    ]);

    $data = (new AdminLicenceResource($licence))->toArray(request());

    expect(data_get($data, 'licence_model.school_included_storage_gb'))->toBe('10')
        ->and(data_get($data, 'licence_model.school_extra_storage_step_gb'))->toBe('100')
        ->and(data_get($data, 'licence_model.school_extra_storage_step_price'))->toBe('5')
        ->and(data_get($data, 'licence_model.admin_included_storage_gb'))->toBe('10')
        ->and(data_get($data, 'licence_model.admin_extra_storage_step_gb'))->toBe('100')
        ->and(data_get($data, 'licence_model.admin_extra_storage_step_price'))->toBe('5')
        ->and(data_get($data, 'licence_model.user_included_storage_gb'))->toBe('20')
        ->and(data_get($data, 'licence_model.user_extra_storage_step_gb'))->toBe('100')
        ->and(data_get($data, 'licence_model.user_extra_storage_step_price'))->toBe('5');
});

test('paginate resource maps paginator properties', function () {
    $paginator = new LengthAwarePaginator([1, 2], 2, 1, 1);

    $data = (new PaginateResource($paginator))->toArray(request());

    expect($data)->toMatchArray([
        'current_page' => $paginator->currentPage(),
        'per_page' => $paginator->perPage(),
        'total' => $paginator->total(),
        'last_page' => $paginator->lastPage(),
        'from' => $paginator->firstItem(),
        'to' => $paginator->lastItem(),
    ]);
});

test('admin register date booking resource maps user and student data', function () {
    $user = User::factory()->create([
        'first_name' => 'Eva',
        'last_name' => 'Example',
        'email' => 'eva@example.test',
        'phone' => '555',
    ]);

    $registerDate = RegisterDate::factory()->create();
    $booking = RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDate->id,
        'register_id' => $registerDate->register_id,
        'user_id' => $user->id,
        'student_first_name' => 'Student',
        'student_last_name' => 'Person',
        'student_birthdate' => '2010-01-01',
        'note' => 'Note',
    ]);

    $data = (new AdminRegisterDateBookingResource($booking))->toArray(request());

    expect($data['first_name'])->toBe('Eva')
        ->and($data['student_first_name'])->toBe('Student')
        ->and($data['note'])->toBe('Note');
});

test('admin register date resource includes bookings and count', function () {
    $registerDate = RegisterDate::factory()->create(['is_locked' => 1]);
    RegisterDateBooking::factory()->count(2)->create([
        'register_date_id' => $registerDate->id,
        'register_id' => $registerDate->register_id,
        'school_id' => $registerDate->school_id,
        'schoolyear_id' => $registerDate->schoolyear_id,
    ]);

    $registerDate->load('bookings');

    $data = (new AdminRegisterDateResource($registerDate))->toArray(request());

    expect($data['count_bookings'])->toBe(2)
        ->and($data['is_locked'])->toBeTrue()
        ->and($data['bookings'])->toHaveCount(2);
});

test('admin register resource maps flags and loaded schoolyear name', function () {
    $schoolyear = Schoolyear::factory()->create(['name' => '2024/2025']);
    $register = Register::factory()->create([
        'schoolyear_id' => $schoolyear->id,
        'show_phone' => 1,
        'must_phone' => 0,
        'show_note' => 1,
        'must_note' => 0,
        'is_active' => 1,
    ]);
    $register->bookings_count = 3;
    $register->dates_count = 2;
    $register->different_dates_count = 2;

    $register->load('schoolyear');

    $data = (new AdminRegisterResource($register))->toArray(request());

    expect($data['show_phone'])->toBeTrue()
        ->and($data['must_phone'])->toBeFalse()
        ->and($data['bookings_count'])->toBe(3)
        ->and($data['schoolyear_name'])->toBe('2024/2025');
});

test('admin register user resource includes bookings relation', function () {
    $registerDate = RegisterDate::factory()->create();
    $user = User::factory()->create();
    RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDate->id,
        'register_id' => $registerDate->register_id,
        'user_id' => $user->id,
        'school_id' => $registerDate->school_id,
        'schoolyear_id' => $registerDate->schoolyear_id,
    ]);

    $user->load('registerDateBookings');

    $data = (new RegisterUserResource($user))->toArray(request());

    expect($data['registerDateBookings'])->toHaveCount(1);
});

test('role resource returns id, name, and is_admin', function () {
    $role = Role::create(['name' => 'admin', 'guard_name' => 'web', 'is_admin' => true]);

    $data = (new RoleResource($role))->toArray(request());

    expect($data)->toMatchArray([
        'id' => $role->id,
        'name' => 'admin',
        'is_admin' => true,
    ]);
});

test('admin school resource exposes its color and selectable state as their expected types', function () {
    $school = School::factory()->create([
        'color' => '#336699',
        'is_selectable' => 0,
    ]);

    $data = (new AdminSchoolResource($school))->toArray(request());

    expect($data['color'])->toBe('#336699')
        ->and($data['is_selectable'])->toBeFalse();
});

test('admin school resource exposes structured licence role names for assignment dialogs', function () {
    $school = School::factory()->create();
    $licence = Licence::create([
        'name' => 'Structured Roles',
        'long_name' => 'Structured Roles Long',
        'admin_licence_enabled' => true,
        'admin_role_names' => ['admin', 'register_admin'],
        'user_licence_enabled' => true,
        'user_role_names' => ['teacher'],
    ]);
    $school->licences()->attach($licence->id, ['valid_until' => '2030-01-01']);
    $school->load(['licences', 'schoolLicences', 'schoolUserLicences']);

    $data = (new AdminSchoolResource($school))->toArray(request());

    expect(data_get($data, 'licences.0.admin_role_names'))->toBe(['admin', 'register_admin'])
        ->and(data_get($data, 'licences.0.user_role_names'))->toBe(['teacher']);
});

test('admin school resource counts assigned admin and user licences from school licence assignments', function () {
    $school = School::factory()->create();
    $adminUser = User::factory()->create(['school_id' => $school->id]);
    $expiredAdminUser = User::factory()->create(['school_id' => $school->id]);
    $teacherUser = User::factory()->create(['school_id' => $school->id]);

    $licence = Licence::create([
        'name' => 'Assigned Roles',
        'long_name' => 'Assigned Roles Long',
        'admin_licence_enabled' => true,
        'admin_role_names' => ['register_admin', 'teaching_admin'],
        'user_licence_enabled' => true,
        'user_role_names' => ['teacher'],
    ]);

    SchoolLicence::create([
        'school_id' => $school->id,
        'licence_id' => $licence->id,
        'valid_until' => '2030-01-01',
        'licence_model' => [
            'admin_role_names' => ['register_admin', 'teaching_admin'],
            'user_role_names' => ['teacher'],
        ],
        'user_licence_assignments' => [
            (string) $adminUser->id => [
                'register_admin' => ['valid_until' => '2030-01-01'],
                'teaching_admin' => ['valid_until' => '2030-01-01'],
            ],
            (string) $expiredAdminUser->id => [
                'register_admin' => ['valid_until' => '2020-01-01'],
            ],
            (string) $teacherUser->id => [
                'teacher' => ['valid_until' => '2030-01-01'],
            ],
        ],
    ]);

    $school->load(['licences', 'schoolLicences', 'schoolUserLicences']);

    $data = (new AdminSchoolResource($school))->toArray(request());

    expect(data_get($data, 'licences.0.admin_licence_count'))->toBe(2)
        ->and(data_get($data, 'licences.0.admin_licence_active_count'))->toBe(1)
        ->and(data_get($data, 'licences.0.admin_licence_expired_count'))->toBe(1)
        ->and(data_get($data, 'licences.0.user_licence_count'))->toBe(1);
});

test('admin school resource exposes admin billing overrides for school licences', function () {
    $school = School::factory()->create();
    $licence = Licence::create([
        'name' => 'Admin Billing Fields',
        'long_name' => 'Admin Billing Fields Long',
        'admin_licence_enabled' => true,
        'admin_role_names' => ['register_admin'],
        'admin_extra_storage_step_gb' => 100,
        'admin_extra_storage_step_price' => '5',
    ]);

    SchoolLicence::create([
        'school_id' => $school->id,
        'licence_id' => $licence->id,
        'charged_admin_price' => 79.5,
        'admin_extra_storage_units' => 2,
        'admin_extra_storage_unit_price' => 5,
    ]);

    $school->load(['licences', 'schoolLicences', 'schoolUserLicences']);

    $data = (new AdminSchoolResource($school))->toArray(request());

    expect(data_get($data, 'licences.0.charged_admin_price'))->toBe('79.50')
        ->and(data_get($data, 'licences.0.admin_extra_storage_units'))->toBe(2)
        ->and(data_get($data, 'licences.0.admin_extra_storage_unit_price'))->toBe('5.00');
});

test('admin school resource performs no database queries while serializing loaded relations', function () {
    $school = School::factory()->create();
    $licence = Licence::create([
        'name' => 'Query Safe',
        'long_name' => 'Query Safe',
    ]);
    $school->licences()->attach($licence->id, ['valid_until' => '2030-01-01']);
    $school->load(['licences', 'schoolLicences', 'schoolUserLicences']);

    DB::flushQueryLog();
    DB::enableQueryLog();

    (new AdminSchoolResource($school))->toArray(request());

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
});

test('school tool resource maps remaining modules and omits tutoring settings', function () {
    Licence::query()->create([
        'name' => 'ABA',
        'long_name' => 'ABA',
        'is_selectable' => true,
    ]);
    Licence::query()->create([
        'name' => 'Anmeldetool',
        'long_name' => 'Anmeldetool',
        'is_selectable' => true,
    ]);

    $schoolTool = SchoolTool::create([
        'school_id' => School::factory()->create()->id,
        'register_visible_admin' => true,
        'register_visible_user' => false,
        'register_user_test_mode' => false,
        'register_user_comming_soon' => true,
        'teaching_visible_admin' => true,
        'teaching_visible_user' => true,
        'teaching_user_test_mode' => false,
        'teaching_user_comming_soon' => false,
        'materials_visible_admin' => false,
        'materials_visible_user' => false,
        'materials_user_test_mode' => false,
        'materials_user_comming_soon' => false,
        'restaurant_visible_admin' => true,
        'restaurant_visible_user' => true,
        'restaurant_user_test_mode' => false,
        'restaurant_user_comming_soon' => false,
        'aba_visible_admin' => true,
        'aba_visible_user' => true,
        'aba_user_test_mode' => false,
        'aba_user_comming_soon' => false,
    ]);

    $data = (new SchoolToolResource($schoolTool))->toArray(request());

    expect($data)->not->toHaveKey('tutoring_student_must_be_confirmed')
        ->and($data['module_rows'])->toBeArray()
        ->and(collect($data['module_rows'])->pluck('key')->all())->toBe(['aba', 'register'])
        ->and($data['register_visible_admin'])->toBeTrue()
        ->and($data['register_visible_user'])->toBeFalse()
        ->and($data['register_user_comming_soon'])->toBeTrue()
        ->and($data['teaching_visible_user'])->toBeTrue()
        ->and($data['materials_visible_admin'])->toBeFalse()
        ->and($data['restaurant_visible_user'])->toBeTrue()
        ->and($data['aba_visible_admin'])->toBeTrue()
        ->and(collect($data['module_rows'])->firstWhere('key', 'register')['label'] ?? null)->toBe('Anmeldetool')
        ->and(collect($data['module_rows'])->firstWhere('key', 'aba')['label'] ?? null)->toBe('ABA')
        ->and($data)->not->toHaveKey('may_visible_for_other_schools');
});

test('schoolyear resource returns date fields', function () {
    $schoolyear = Schoolyear::factory()->create([
        'name' => '2024/2025',
        'from' => '2024-09-01',
        'until' => '2025-06-30',
        'sem_2_start' => '2025-02-01',
    ]);

    $data = (new AdminSchoolyearResource($schoolyear))->toArray(request());

    expect($data['name'])->toBe('2024/2025')
        ->and($data['sem_2_start'])->toBe('2025-02-01');
});

test('teacher resource formats login and sorts roles', function () {
    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $teacher = User::factory()->create([
        'login_at' => '2024-02-02 12:34:00',
        'login_ip' => '127.0.0.1',
    ]);
    $teacher->assignRole('teacher');
    $teacher->assignRole('admin');

    $teacher->load('roles');

    $data = (new TeacherResource($teacher))->toArray(request());

    expect($data['login_at'])->toBe('02.02.2024  12:34')
        ->and($data['roles']->values()->all())->toBe(['admin', 'teacher'])
        ->and($data['is_active'])->toBeBool();
});

test('teachers list resource returns teacher fields', function () {
    $teacher = Teacher::create([
        'school_id' => School::factory()->create()->id,
        'first_name' => 'Tom',
        'last_name' => 'Tutor',
        'short' => 'TT',
        'email' => 'tom@example.test',
    ]);

    $data = (new TeachersListResource($teacher))->toArray(request());

    expect($data['short'])->toBe('TT')
        ->and($data['email'])->toBe('tom@example.test');
});

test('retired admin tutoring offer resource is not loadable', function () {
    expect(class_exists('App\\Http\\Resources\\Admin\\Tutoring\\OfferResource'))->toBeFalse();
});

test('user with role resource formats date flags and role list', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'confirmed_at' => '2024-01-01 00:00:00',
        'email_verified_at' => '2024-01-02 00:00:00',
        'login_at' => '2024-01-03 10:00:00',
        'is_active' => 1,
        'is_2fa' => 1,
        'use_school_color_for_admin_ui' => false,
    ]);
    $user->assignRole('admin');
    $user->load('roles');

    $data = (new UserWithRoleResource($user))->toArray(request());

    expect($data['is_confirmed'])->toBeTrue()
        ->and($data['confirmed_at'])->toBe('01.01.2024')
        ->and($data['is_verified'])->toBeTrue()
        ->and($data['email_verified_at'])->toBe('02.01.2024')
        ->and($data['use_school_color_for_admin_ui'])->toBeFalse()
        ->and($data['roles']->values()->all())->toBe(['admin']);
});

test('admin user resource exposes the personal admin shell color preference', function () {
    $user = User::factory()->create(['use_school_color_for_admin_ui' => false]);
    $user->load('roles');

    $data = (new AdminUserResource($user))->toArray(request());

    expect($data['use_school_color_for_admin_ui'])->toBeFalse();
});

test('admin teaching import116 resource exposes school level attendance year and religion', function () {
    $record = Import116::factory()->create([
        'school_level' => '5',
        'attendance_year' => '2',
        'religion' => 'Rk',
    ]);

    $data = (new Import116Resource($record))->toArray(request());

    expect($data['school_level'])->toBe('5')
        ->and($data['attendance_year'])->toBe('2')
        ->and($data['religion'])->toBe('Rk')
        ->and($data['Religion'])->toBe('Rk');
});
