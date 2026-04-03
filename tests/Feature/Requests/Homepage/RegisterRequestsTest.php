<?php

/**
 * Homepage Register Form Request Tests
 *
 * Tests for public registration-related requests.
 */

use App\Http\Requests\Homepage\HomepageLoadSchoolsForToolRequest;
use App\Http\Requests\Homepage\HomepageRoutingRequest;
use App\Http\Requests\Homepage\RegisterBookRequest;
use App\Http\Requests\Homepage\RegisterCheckEmailRequest;
use App\Http\Requests\Homepage\RegisterConfirmEmailRequest;
use App\Http\Requests\Homepage\RegisterDeleteBookingRequest;
use App\Http\Requests\Homepage\RegisterLoginTokenRequest;
use App\Http\Requests\Homepage\RegisterSaveUserDataRequest;
use App\Models\Licence;
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
        'email' => 'testuser@example.com',
    ]);
    $this->register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    $this->registerDate = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
    ]);
    // Create a licence for HomepageLoadSchoolsForToolRequest test
    $this->licence = Licence::create([
        'name' => 'register',
        'description' => 'Register licence',
    ]);
});

function validateHomepageRegisterRequest(string $requestClass, array $data): Illuminate\Validation\Validator
{
    $request = new $requestClass;

    return Validator::make($data, $request->rules());
}

// ============================================================================
// HomepageRoutingRequest
// ============================================================================

describe('HomepageRoutingRequest', function () {
    it('authorizes all requests', function () {
        $request = new HomepageRoutingRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid school and licence', function () {
        $validator = validateHomepageRegisterRequest(HomepageRoutingRequest::class, [
            'school' => 'test-school',
            'licence' => 'register',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('passes with empty data', function () {
        $validator = validateHomepageRegisterRequest(HomepageRoutingRequest::class, []);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when school exceeds max length', function () {
        $validator = validateHomepageRegisterRequest(HomepageRoutingRequest::class, [
            'school' => str_repeat('a', 256),
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// HomepageLoadSchoolsForToolRequest
// ============================================================================

describe('HomepageLoadSchoolsForToolRequest', function () {
    it('authorizes all requests', function () {
        $request = new HomepageLoadSchoolsForToolRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid tool', function () {
        $validator = validateHomepageRegisterRequest(HomepageLoadSchoolsForToolRequest::class, [
            'tool' => 'register',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when tool is missing', function () {
        $validator = validateHomepageRegisterRequest(HomepageLoadSchoolsForToolRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('tool'))->toBeTrue();
    });

    it('fails when tool has invalid value', function () {
        $validator = validateHomepageRegisterRequest(HomepageLoadSchoolsForToolRequest::class, [
            'tool' => 'invalid',
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// RegisterCheckEmailRequest
// ============================================================================

describe('RegisterCheckEmailRequest', function () {
    it('authorizes all requests', function () {
        $request = new RegisterCheckEmailRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateHomepageRegisterRequest(RegisterCheckEmailRequest::class, [
            'data' => [
                'step' => 'EMAIL',
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => 'test@example.com',
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when step is missing', function () {
        $validator = validateHomepageRegisterRequest(RegisterCheckEmailRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => 'test@example.com',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.step'))->toBeTrue();
    });

    it('fails when step has invalid value', function () {
        $validator = validateHomepageRegisterRequest(RegisterCheckEmailRequest::class, [
            'data' => [
                'step' => 'INVALID',
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => 'test@example.com',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when school_id does not exist', function () {
        $validator = validateHomepageRegisterRequest(RegisterCheckEmailRequest::class, [
            'data' => [
                'step' => 'EMAIL',
                'school_id' => 99999,
                'register_id' => $this->register->id,
                'email' => 'test@example.com',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when register_id does not exist', function () {
        $validator = validateHomepageRegisterRequest(RegisterCheckEmailRequest::class, [
            'data' => [
                'step' => 'EMAIL',
                'school_id' => $this->school->id,
                'register_id' => 99999,
                'email' => 'test@example.com',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when email is invalid', function () {
        $validator = validateHomepageRegisterRequest(RegisterCheckEmailRequest::class, [
            'data' => [
                'step' => 'EMAIL',
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => 'not-an-email',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when email exceeds max length', function () {
        $validator = validateHomepageRegisterRequest(RegisterCheckEmailRequest::class, [
            'data' => [
                'step' => 'EMAIL',
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => str_repeat('a', 250).'@test.com',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// RegisterConfirmEmailRequest
// ============================================================================

describe('RegisterConfirmEmailRequest', function () {
    it('authorizes all requests', function () {
        $request = new RegisterConfirmEmailRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateHomepageRegisterRequest(RegisterConfirmEmailRequest::class, [
            'data' => [
                'step' => 'EMAIL_TOKEN',
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => $this->user->email,
                'user_id' => $this->user->id,
                'token_2fa' => '123456',
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when token_2fa is not exactly 6 characters', function () {
        $validator = validateHomepageRegisterRequest(RegisterConfirmEmailRequest::class, [
            'data' => [
                'step' => 'EMAIL_TOKEN',
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => $this->user->email,
                'user_id' => $this->user->id,
                'token_2fa' => '12345',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when user_id does not exist', function () {
        $validator = validateHomepageRegisterRequest(RegisterConfirmEmailRequest::class, [
            'data' => [
                'step' => 'EMAIL_TOKEN',
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => 'test@example.com',
                'user_id' => 99999,
                'token_2fa' => '123456',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// RegisterSaveUserDataRequest
// ============================================================================

describe('RegisterSaveUserDataRequest', function () {
    it('authorizes all requests', function () {
        $request = new RegisterSaveUserDataRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateHomepageRegisterRequest(RegisterSaveUserDataRequest::class, [
            'data' => [
                'step' => 'ENTER_USER_DATA',
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => $this->user->email,
                'user_id' => $this->user->id,
                'token_2fa' => '123456',
                'last_name' => 'Mustermann',
                'first_name' => 'Max',
                'phone' => '+49123456789',
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when last_name is missing', function () {
        $validator = validateHomepageRegisterRequest(RegisterSaveUserDataRequest::class, [
            'data' => [
                'step' => 'ENTER_USER_DATA',
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => $this->user->email,
                'user_id' => $this->user->id,
                'token_2fa' => '123456',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.last_name'))->toBeTrue();
    });

    it('allows nullable phone', function () {
        $validator = validateHomepageRegisterRequest(RegisterSaveUserDataRequest::class, [
            'data' => [
                'step' => 'ENTER_USER_DATA',
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => $this->user->email,
                'user_id' => $this->user->id,
                'token_2fa' => '123456',
                'last_name' => 'Mustermann',
                'phone' => null,
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });
});

// ============================================================================
// RegisterLoginTokenRequest
// ============================================================================

describe('RegisterLoginTokenRequest', function () {
    it('authorizes all requests', function () {
        $request = new RegisterLoginTokenRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateHomepageRegisterRequest(RegisterLoginTokenRequest::class, [
            'data' => [
                'step' => 'LOGIN_TOKEN',
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => $this->user->email,
                'user_id' => $this->user->id,
                'token_2fa' => '123456',
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when data.user_id is missing', function () {
        $validator = validateHomepageRegisterRequest(RegisterLoginTokenRequest::class, [
            'data' => [
                'step' => 'LOGIN_TOKEN',
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => $this->user->email,
                'token_2fa' => '123456',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.user_id'))->toBeTrue();
    });

    it('fails when user_id does not exist', function () {
        $validator = validateHomepageRegisterRequest(RegisterLoginTokenRequest::class, [
            'data' => [
                'step' => 'LOGIN_TOKEN',
                'school_id' => $this->school->id,
                'register_id' => $this->register->id,
                'email' => 'test@example.com',
                'user_id' => 99999,
                'token_2fa' => '123456',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// RegisterDeleteBookingRequest
// ============================================================================

describe('RegisterDeleteBookingRequest', function () {
    it('requires authentication', function () {
        $request = new RegisterDeleteBookingRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new RegisterDeleteBookingRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid booking id', function () {
        $booking = RegisterDateBooking::factory()->create([
            'register_date_id' => $this->registerDate->id,
            'user_id' => $this->user->id,
        ]);

        $validator = validateHomepageRegisterRequest(RegisterDeleteBookingRequest::class, [
            'booking_id' => $booking->id,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when booking_id is missing', function () {
        $validator = validateHomepageRegisterRequest(RegisterDeleteBookingRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('booking_id'))->toBeTrue();
    });

    it('fails when booking_id does not exist', function () {
        $validator = validateHomepageRegisterRequest(RegisterDeleteBookingRequest::class, [
            'booking_id' => 99999,
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// RegisterBookRequest
// ============================================================================

describe('RegisterBookRequest', function () {
    it('requires authentication', function () {
        $request = new RegisterBookRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new RegisterBookRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateHomepageRegisterRequest(RegisterBookRequest::class, [
            'data' => [
                'register_id' => $this->register->id,
                'register_date_id' => $this->registerDate->id,
                'student_last_name' => 'Schüler',
                'student_first_name' => 'Max',
                'student_birthdate' => '2010-05-15',
                'note' => 'Bitte um frühen Termin',
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when register_id is missing', function () {
        $validator = validateHomepageRegisterRequest(RegisterBookRequest::class, [
            'data' => [
                'register_date_id' => $this->registerDate->id,
                'student_last_name' => 'Schüler',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.register_id'))->toBeTrue();
    });

    it('fails when register_date_id is missing', function () {
        $validator = validateHomepageRegisterRequest(RegisterBookRequest::class, [
            'data' => [
                'register_id' => $this->register->id,
                'student_last_name' => 'Schüler',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.register_date_id'))->toBeTrue();
    });

    it('fails when student_last_name is missing', function () {
        $validator = validateHomepageRegisterRequest(RegisterBookRequest::class, [
            'data' => [
                'register_id' => $this->register->id,
                'register_date_id' => $this->registerDate->id,
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.student_last_name'))->toBeTrue();
    });

    it('fails when register_id does not exist', function () {
        $validator = validateHomepageRegisterRequest(RegisterBookRequest::class, [
            'data' => [
                'register_id' => 99999,
                'register_date_id' => $this->registerDate->id,
                'student_last_name' => 'Schüler',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when register_date_id does not exist', function () {
        $validator = validateHomepageRegisterRequest(RegisterBookRequest::class, [
            'data' => [
                'register_id' => $this->register->id,
                'register_date_id' => 99999,
                'student_last_name' => 'Schüler',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('allows nullable optional fields', function () {
        $validator = validateHomepageRegisterRequest(RegisterBookRequest::class, [
            'data' => [
                'register_id' => $this->register->id,
                'register_date_id' => $this->registerDate->id,
                'student_last_name' => 'Schüler',
                'student_first_name' => null,
                'student_birthdate' => null,
                'note' => null,
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when student_birthdate is invalid date', function () {
        $validator = validateHomepageRegisterRequest(RegisterBookRequest::class, [
            'data' => [
                'register_id' => $this->register->id,
                'register_date_id' => $this->registerDate->id,
                'student_last_name' => 'Schüler',
                'student_birthdate' => 'not-a-date',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});
