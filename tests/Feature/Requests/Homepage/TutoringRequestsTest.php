<?php

/**
 * Homepage Tutoring Form Request Tests
 *
 * Tests for public tutoring-related requests.
 */

use App\Http\Requests\Homepage\TutoringCheckEmailRequest;
use App\Http\Requests\Homepage\TutoringConfirmEmailRequest;
use App\Http\Requests\Homepage\TutoringConfirmUserRequest;
use App\Http\Requests\Homepage\TutoringCreateUserRequest;
use App\Http\Requests\Homepage\TutoringLoginWithTokenRequest;
use App\Http\Requests\Homepage\TutoringUnknownPasswordRequest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'tutoringuser@example.com',
    ]);
});

function validateHomepageTutoringRequest(string $requestClass, array $data): Illuminate\Validation\Validator
{
    $request = new $requestClass;

    return Validator::make($data, $request->rules());
}

// ============================================================================
// TutoringCheckEmailRequest
// ============================================================================

describe('TutoringCheckEmailRequest', function () {
    it('authorizes all requests', function () {
        $request = new TutoringCheckEmailRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateHomepageTutoringRequest(TutoringCheckEmailRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'student@example.com',
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when school_id is missing', function () {
        $validator = validateHomepageTutoringRequest(TutoringCheckEmailRequest::class, [
            'data' => [
                'email' => 'student@example.com',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.school_id'))->toBeTrue();
    });

    it('fails when email is missing', function () {
        $validator = validateHomepageTutoringRequest(TutoringCheckEmailRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.email'))->toBeTrue();
    });

    it('fails when school_id does not exist', function () {
        $validator = validateHomepageTutoringRequest(TutoringCheckEmailRequest::class, [
            'data' => [
                'school_id' => 99999,
                'email' => 'student@example.com',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when email is invalid', function () {
        $validator = validateHomepageTutoringRequest(TutoringCheckEmailRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'not-an-email',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// TutoringConfirmEmailRequest
// ============================================================================

describe('TutoringConfirmEmailRequest', function () {
    it('authorizes all requests', function () {
        $request = new TutoringConfirmEmailRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateHomepageTutoringRequest(TutoringConfirmEmailRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'user_id' => $this->user->id,
                'email' => 'student@example.com',
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when data.user_id is missing', function () {
        $validator = validateHomepageTutoringRequest(TutoringConfirmEmailRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'student@example.com',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.user_id'))->toBeTrue();
    });

    it('fails when token_2fa is not exactly 6 characters', function () {
        $validator = validateHomepageTutoringRequest(TutoringConfirmEmailRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'user_id' => $this->user->id,
                'email' => 'student@example.com',
                'token_2fa' => '12345',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// TutoringConfirmUserRequest
// ============================================================================

describe('TutoringConfirmUserRequest', function () {
    it('authorizes all requests', function () {
        $request = new TutoringConfirmUserRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateHomepageTutoringRequest(TutoringConfirmUserRequest::class, [
            'user_id' => $this->user->id,
            'token' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when user_id is missing', function () {
        $validator = validateHomepageTutoringRequest(TutoringConfirmUserRequest::class, [
            'token' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('user_id'))->toBeTrue();
    });

    it('fails when user_id does not exist', function () {
        $validator = validateHomepageTutoringRequest(TutoringConfirmUserRequest::class, [
            'user_id' => 99999,
            'token' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when token is not a valid uuid', function () {
        $validator = validateHomepageTutoringRequest(TutoringConfirmUserRequest::class, [
            'user_id' => $this->user->id,
            'token' => 'not-a-uuid',
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// TutoringCreateUserRequest
// ============================================================================

describe('TutoringCreateUserRequest', function () {
    it('authorizes all requests', function () {
        $request = new TutoringCreateUserRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateHomepageTutoringRequest(TutoringCreateUserRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'newstudent@example.com',
                'status' => 'NEW_USER',
                'last_name' => 'Müller',
                'first_name' => 'Anna',
                'schoolclass' => '10A',
                'sex' => 'f',
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when school_id is missing', function () {
        $validator = validateHomepageTutoringRequest(TutoringCreateUserRequest::class, [
            'data' => [
                'email' => 'newstudent@example.com',
                'last_name' => 'Müller',
                'schoolclass' => '10A',
                'sex' => 'f',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.school_id'))->toBeTrue();
    });

    it('fails when email is missing', function () {
        $validator = validateHomepageTutoringRequest(TutoringCreateUserRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'last_name' => 'Müller',
                'schoolclass' => '10A',
                'sex' => 'f',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.email'))->toBeTrue();
    });

    it('fails when last_name is missing', function () {
        $validator = validateHomepageTutoringRequest(TutoringCreateUserRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'newstudent@example.com',
                'schoolclass' => '10A',
                'sex' => 'f',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.last_name'))->toBeTrue();
    });

    it('fails when schoolclass is missing', function () {
        $validator = validateHomepageTutoringRequest(TutoringCreateUserRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'newstudent@example.com',
                'last_name' => 'Müller',
                'sex' => 'f',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.schoolclass'))->toBeTrue();
    });

    it('fails when sex is missing', function () {
        $validator = validateHomepageTutoringRequest(TutoringCreateUserRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'newstudent@example.com',
                'last_name' => 'Müller',
                'schoolclass' => '10A',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.sex'))->toBeTrue();
    });

    it('fails when sex has invalid value', function () {
        $validator = validateHomepageTutoringRequest(TutoringCreateUserRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'newstudent@example.com',
                'last_name' => 'Müller',
                'schoolclass' => '10A',
                'sex' => 'x',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('passes with all valid sex values', function () {
        foreach (['m', 'f', 'd'] as $sex) {
            $validator = validateHomepageTutoringRequest(TutoringCreateUserRequest::class, [
                'data' => [
                    'school_id' => $this->school->id,
                    'email' => "student{$sex}@example.com",
                    'last_name' => 'Müller',
                    'schoolclass' => '10A',
                    'sex' => $sex,
                ],
            ]);

            expect($validator->passes())->toBeTrue();
        }
    });

    it('fails when status has invalid value', function () {
        $validator = validateHomepageTutoringRequest(TutoringCreateUserRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'newstudent@example.com',
                'status' => 'INVALID_STATUS',
                'last_name' => 'Müller',
                'schoolclass' => '10A',
                'sex' => 'f',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('allows nullable first_name', function () {
        $validator = validateHomepageTutoringRequest(TutoringCreateUserRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'newstudent@example.com',
                'last_name' => 'Müller',
                'first_name' => null,
                'schoolclass' => '10A',
                'sex' => 'f',
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when schoolclass exceeds max length', function () {
        $validator = validateHomepageTutoringRequest(TutoringCreateUserRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'newstudent@example.com',
                'last_name' => 'Müller',
                'schoolclass' => str_repeat('A', 11),
                'sex' => 'f',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// TutoringLoginWithTokenRequest
// ============================================================================

describe('TutoringLoginWithTokenRequest', function () {
    it('authorizes all requests', function () {
        $request = new TutoringLoginWithTokenRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateHomepageTutoringRequest(TutoringLoginWithTokenRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'test@example.com',
                'user_id' => $this->user->id,
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when data.user_id is missing', function () {
        $validator = validateHomepageTutoringRequest(TutoringLoginWithTokenRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'test@example.com',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.user_id'))->toBeTrue();
    });

    it('fails when data.school_id is missing', function () {
        $validator = validateHomepageTutoringRequest(TutoringLoginWithTokenRequest::class, [
            'data' => [
                'email' => 'test@example.com',
                'user_id' => $this->user->id,
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.school_id'))->toBeTrue();
    });

    it('fails when data.email is missing', function () {
        $validator = validateHomepageTutoringRequest(TutoringLoginWithTokenRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'user_id' => $this->user->id,
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.email'))->toBeTrue();
    });
});

// ============================================================================
// TutoringUnknownPasswordRequest
// ============================================================================

describe('TutoringUnknownPasswordRequest', function () {
    it('authorizes all requests', function () {
        $request = new TutoringUnknownPasswordRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateHomepageTutoringRequest(TutoringUnknownPasswordRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'student@example.com',
                'user_id' => $this->user->id,
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when data.email is missing', function () {
        $validator = validateHomepageTutoringRequest(TutoringUnknownPasswordRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'user_id' => $this->user->id,
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.email'))->toBeTrue();
    });

    it('fails when data.school_id is missing', function () {
        $validator = validateHomepageTutoringRequest(TutoringUnknownPasswordRequest::class, [
            'data' => [
                'email' => 'student@example.com',
                'user_id' => $this->user->id,
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.school_id'))->toBeTrue();
    });

    it('fails when data.user_id is missing', function () {
        $validator = validateHomepageTutoringRequest(TutoringUnknownPasswordRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'student@example.com',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.user_id'))->toBeTrue();
    });

    it('fails when email is invalid', function () {
        $validator = validateHomepageTutoringRequest(TutoringUnknownPasswordRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'not-an-email',
                'user_id' => $this->user->id,
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});
