<?php

/**
 * Admin RegisterDate Form Request Tests
 *
 * Tests for register date and booking management requests.
 */

use App\Http\Requests\Admin\RegisterDateIndexRequest;
use App\Http\Requests\Admin\RegisterDateCreateDatesRequest;
use App\Http\Requests\Admin\RegisterDateBookingStoreRequest;
use App\Http\Requests\Admin\RegisterDateBookingUpdateOrCreateUserRequest;
use App\Http\Requests\Admin\RegisterDateBookingDeleteBookingsRequest;
use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->registerDate = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
    ]);
});

function validateRegisterDateRequest(string $requestClass, array $data): \Illuminate\Validation\Validator
{
    $request = new $requestClass();
    return Validator::make($data, $request->rules());
}

// ============================================================================
// RegisterDateIndexRequest
// ============================================================================

describe('RegisterDateIndexRequest', function () {
    it('requires authentication', function () {
        $request = new RegisterDateIndexRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new RegisterDateIndexRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid date', function () {
        $validator = validateRegisterDateRequest(RegisterDateIndexRequest::class, [
            'date' => '2025-06-01',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when date is missing', function () {
        $validator = validateRegisterDateRequest(RegisterDateIndexRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('date'))->toBeTrue();
    });

    it('fails when date is invalid', function () {
        $validator = validateRegisterDateRequest(RegisterDateIndexRequest::class, [
            'date' => 'not-a-date',
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// RegisterDateCreateDatesRequest
// ============================================================================

describe('RegisterDateCreateDatesRequest', function () {
    it('requires authentication', function () {
        $request = new RegisterDateCreateDatesRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new RegisterDateCreateDatesRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateRegisterDateRequest(RegisterDateCreateDatesRequest::class, [
            'date_from' => '2025-06-01',
            'date_until' => '2025-06-30',
            'time_from' => '08:00',
            'time_until' => '16:00',
            'pause' => 15,
            'min_per_date' => 30,
            'max_registrations' => 5,
            'monday' => true,
            'tuesday' => true,
            'wednesday' => true,
            'thursday' => true,
            'friday' => true,
            'saturday' => false,
            'sunday' => false,
            'supervisor_1' => 'Herr Müller',
            'supervisor_2' => null,
            'supervisor_3' => null,
            'supervisor_4' => null,
            'supervisor_5' => null,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('passes with minimal required data', function () {
        $validator = validateRegisterDateRequest(RegisterDateCreateDatesRequest::class, [
            'date_from' => '2025-06-01',
            'time_from' => '08:00',
            'time_until' => '16:00',
            'pause' => 0,
            'min_per_date' => 15,
            'max_registrations' => 1,
            'supervisor_1' => 'Supervisor',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when date_from is missing', function () {
        $validator = validateRegisterDateRequest(RegisterDateCreateDatesRequest::class, [
            'time_from' => '08:00',
            'time_until' => '16:00',
            'pause' => 0,
            'min_per_date' => 15,
            'max_registrations' => 1,
            'supervisor_1' => 'Supervisor',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('date_from'))->toBeTrue();
    });

    it('fails when date_from is invalid date', function () {
        $validator = validateRegisterDateRequest(RegisterDateCreateDatesRequest::class, [
            'date_from' => 'not-a-date',
            'time_from' => '08:00',
            'time_until' => '16:00',
            'pause' => 0,
            'min_per_date' => 15,
            'max_registrations' => 1,
            'supervisor_1' => 'Supervisor',
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when time_from is missing', function () {
        $validator = validateRegisterDateRequest(RegisterDateCreateDatesRequest::class, [
            'date_from' => '2025-06-01',
            'time_until' => '16:00',
            'pause' => 0,
            'min_per_date' => 15,
            'max_registrations' => 1,
            'supervisor_1' => 'Supervisor',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('time_from'))->toBeTrue();
    });

    it('fails when time_from has invalid format', function () {
        $validator = validateRegisterDateRequest(RegisterDateCreateDatesRequest::class, [
            'date_from' => '2025-06-01',
            'time_from' => '8:00 AM',
            'time_until' => '16:00',
            'pause' => 0,
            'min_per_date' => 15,
            'max_registrations' => 1,
            'supervisor_1' => 'Supervisor',
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when min_per_date is less than 1', function () {
        $validator = validateRegisterDateRequest(RegisterDateCreateDatesRequest::class, [
            'date_from' => '2025-06-01',
            'time_from' => '08:00',
            'time_until' => '16:00',
            'pause' => 0,
            'min_per_date' => 0,
            'max_registrations' => 1,
            'supervisor_1' => 'Supervisor',
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when max_registrations is negative', function () {
        $validator = validateRegisterDateRequest(RegisterDateCreateDatesRequest::class, [
            'date_from' => '2025-06-01',
            'time_from' => '08:00',
            'time_until' => '16:00',
            'pause' => 0,
            'min_per_date' => 15,
            'max_registrations' => -1,
            'supervisor_1' => 'Supervisor',
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when supervisor_1 is missing', function () {
        $validator = validateRegisterDateRequest(RegisterDateCreateDatesRequest::class, [
            'date_from' => '2025-06-01',
            'time_from' => '08:00',
            'time_until' => '16:00',
            'pause' => 0,
            'min_per_date' => 15,
            'max_registrations' => 1,
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('supervisor_1'))->toBeTrue();
    });

    it('fails when supervisor_1 exceeds max length', function () {
        $validator = validateRegisterDateRequest(RegisterDateCreateDatesRequest::class, [
            'date_from' => '2025-06-01',
            'time_from' => '08:00',
            'time_until' => '16:00',
            'pause' => 0,
            'min_per_date' => 15,
            'max_registrations' => 1,
            'supervisor_1' => str_repeat('a', 256),
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('allows nullable date_until', function () {
        $validator = validateRegisterDateRequest(RegisterDateCreateDatesRequest::class, [
            'date_from' => '2025-06-01',
            'date_until' => null,
            'time_from' => '08:00',
            'time_until' => '16:00',
            'pause' => 0,
            'min_per_date' => 15,
            'max_registrations' => 1,
            'supervisor_1' => 'Supervisor',
        ]);

        expect($validator->passes())->toBeTrue();
    });
});

// ============================================================================
// RegisterDateBookingStoreRequest
// ============================================================================

describe('RegisterDateBookingStoreRequest', function () {
    it('requires authentication', function () {
        $request = new RegisterDateBookingStoreRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new RegisterDateBookingStoreRequest();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// RegisterDateBookingUpdateOrCreateUserRequest
// ============================================================================

describe('RegisterDateBookingUpdateOrCreateUserRequest', function () {
    it('requires authentication', function () {
        $request = new RegisterDateBookingUpdateOrCreateUserRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new RegisterDateBookingUpdateOrCreateUserRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid email', function () {
        $validator = validateRegisterDateRequest(RegisterDateBookingUpdateOrCreateUserRequest::class, [
            'email' => 'test@example.com',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when email is missing', function () {
        $validator = validateRegisterDateRequest(RegisterDateBookingUpdateOrCreateUserRequest::class, [
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('email'))->toBeTrue();
    });

    it('fails when email is invalid', function () {
        $validator = validateRegisterDateRequest(RegisterDateBookingUpdateOrCreateUserRequest::class, [
            'email' => 'not-an-email',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// RegisterDateBookingDeleteBookingsRequest
// ============================================================================

describe('RegisterDateBookingDeleteBookingsRequest', function () {
    it('requires authentication', function () {
        $request = new RegisterDateBookingDeleteBookingsRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new RegisterDateBookingDeleteBookingsRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid bookings array and notify flag', function () {
        $booking = RegisterDateBooking::factory()->create([
            'register_date_id' => $this->registerDate->id,
            'user_id' => $this->user->id,
        ]);

        $validator = validateRegisterDateRequest(RegisterDateBookingDeleteBookingsRequest::class, [
            'bookings' => [$booking->id],
            'notify' => true,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when bookings is missing', function () {
        $validator = validateRegisterDateRequest(RegisterDateBookingDeleteBookingsRequest::class, [
            'notify' => true,
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('bookings'))->toBeTrue();
    });

    it('fails when bookings is not an array', function () {
        $validator = validateRegisterDateRequest(RegisterDateBookingDeleteBookingsRequest::class, [
            'bookings' => 1,
            'notify' => true,
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when notify is missing', function () {
        $booking = RegisterDateBooking::factory()->create([
            'register_date_id' => $this->registerDate->id,
            'user_id' => $this->user->id,
        ]);

        $validator = validateRegisterDateRequest(RegisterDateBookingDeleteBookingsRequest::class, [
            'bookings' => [$booking->id],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('notify'))->toBeTrue();
    });
});
