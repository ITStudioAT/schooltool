<?php

use App\Http\Resources\Homepage\LicenceResource as HomepageLicenceResource;
use App\Http\Resources\Homepage\RegisterDateBookingResource as HomepageRegisterDateBookingResource;
use App\Http\Resources\Homepage\RegisterDateResource as HomepageRegisterDateResource;
use App\Http\Resources\Homepage\RegisterResource as HomepageRegisterResource;
use App\Http\Resources\Homepage\SchoolResource as HomepageSchoolResource;
use App\Http\Resources\Homepage\SchoolWithLicenceRecource;
use App\Models\Licence;
use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\Schoolyear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('homepage licence resource returns basic fields', function () {
    $licence = Licence::create([
        'name' => 'Tool',
        'long_name' => 'Tool Long',
        'price_per_year' => 100,
        'is_selectable' => 1,
    ]);

    $data = (new HomepageLicenceResource($licence))->toArray(request());

    expect($data)->toMatchArray([
        'id' => $licence->id,
        'name' => 'Tool',
        'long_name' => 'Tool Long',
    ]);
});

test('homepage register date booking resource exposes register date fields', function () {
    $registerDate = RegisterDate::factory()->create([
        'date' => '2024-05-10',
        'from' => '08:00:00',
        'to' => '09:00:00',
    ]);
    $booking = RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDate->id,
        'register_id' => $registerDate->register_id,
        'school_id' => $registerDate->school_id,
        'schoolyear_id' => $registerDate->schoolyear_id,
        'student_first_name' => 'Sam',
        'student_last_name' => 'Sample',
        'student_birthdate' => '2011-01-01',
    ]);

    $data = (new HomepageRegisterDateBookingResource($booking))->toArray(request());

    expect($data['date'])->toBe('2024-05-10')
        ->and($data['from'])->toBe('08:00:00')
        ->and($data['student_first_name'])->toBe('Sam');
});

test('homepage register date resource includes lock state and bookings count', function () {
    $registerDate = RegisterDate::factory()->create([
        'is_locked' => 1,
    ]);
    $registerDate->bookings_count = 5;

    $data = (new HomepageRegisterDateResource($registerDate))->toArray(request());

    expect($data['is_locked'])->toBeTrue()
        ->and($data['bookings_count'])->toBe(5);
});

test('homepage register resource includes schoolyear name', function () {
    $schoolyear = Schoolyear::factory()->create(['name' => '2024/2025']);
    $register = Register::factory()->create([
        'schoolyear_id' => $schoolyear->id,
        'show_phone' => 0,
        'must_phone' => 1,
        'is_active' => 1,
    ]);
    $register->load('schoolyear');

    $data = (new HomepageRegisterResource($register))->toArray(request());

    expect($data['show_phone'])->toBeFalse()
        ->and($data['must_phone'])->toBeTrue()
        ->and($data['schoolyear_name'])->toBe('2024/2025');
});

test('homepage school resource returns basic fields', function () {
    $school = School::factory()->create([
        'short_name' => 'ABG',
        'long_name' => 'Example School',
        'logo' => 'logo.png',
    ]);

    $data = (new HomepageSchoolResource($school))->toArray(request());

    expect($data)->toMatchArray([
        'short_name' => 'ABG',
        'long_name' => 'Example School',
        'logo' => 'logo.png',
    ]);
});

test('homepage school with licence resource includes licence data', function () {
    $school = School::factory()->create([
        'short_name' => 'ABG',
        'email' => 'school@example.test',
    ]);
    $licence = Licence::create([
        'name' => 'Register',
        'long_name' => 'Register Long',
        'price_per_year' => 200,
        'is_selectable' => 1,
    ]);
    $school->licences()->attach($licence->id, ['valid_until' => '2031-12-31']);

    $school->load('licences');

    $data = (new SchoolWithLicenceRecource($school))->toArray(request());

    expect($data['licence'])->toBe('Register')
        ->and($data['licence_valid_until'])->toBe('2031-12-31');
});
