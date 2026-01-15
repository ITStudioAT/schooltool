<?php

/**
 * Admin Security Form Request Tests
 *
 * Tests for 2FA, password, and verification requests.
 */

use App\Http\Requests\Admin\Save2FaRequest;
use App\Http\Requests\Admin\Save2FaWithCodeRequest;
use App\Http\Requests\Admin\SavePasswordRequest;
use App\Http\Requests\Admin\SavePasswordWithCodeRequest;
use App\Http\Requests\Admin\EmailVerificationRequest;
use App\Http\Requests\Admin\SendVerificationMailRequest;
use App\Http\Requests\Admin\SendVerificationEmailInitializedFromUserRequest;
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
    ]);
});

function validateSecurityRequest(string $requestClass, array $data): \Illuminate\Validation\Validator
{
    $request = new $requestClass();
    return Validator::make($data, $request->rules());
}

// ============================================================================
// Save2FaRequest
// ============================================================================

describe('Save2FaRequest', function () {
    it('authorizes all requests', function () {
        $request = new Save2FaRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid is_2fa boolean', function () {
        $validator = validateSecurityRequest(Save2FaRequest::class, [
            'id' => $this->user->id,
            'is_2fa' => true,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('passes with false is_2fa', function () {
        $validator = validateSecurityRequest(Save2FaRequest::class, [
            'id' => $this->user->id,
            'is_2fa' => false,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when id is missing', function () {
        $validator = validateSecurityRequest(Save2FaRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('id'))->toBeTrue();
    });

    it('fails when is_2fa is not boolean', function () {
        $validator = validateSecurityRequest(Save2FaRequest::class, [
            'id' => $this->user->id,
            'is_2fa' => 'yes',
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// Save2FaWithCodeRequest
// ============================================================================

describe('Save2FaWithCodeRequest', function () {
    it('authorizes all requests', function () {
        $request = new Save2FaWithCodeRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateSecurityRequest(Save2FaWithCodeRequest::class, [
            'id' => $this->user->id,
            'is_2fa' => true,
            'token_2fa' => '123456',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when token_2fa is missing', function () {
        $validator = validateSecurityRequest(Save2FaWithCodeRequest::class, [
            'id' => $this->user->id,
            'is_2fa' => true,
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('token_2fa'))->toBeTrue();
    });

    it('fails when id is missing', function () {
        $validator = validateSecurityRequest(Save2FaWithCodeRequest::class, [
            'token_2fa' => '123456',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('id'))->toBeTrue();
    });
});

// ============================================================================
// SavePasswordRequest
// ============================================================================

describe('SavePasswordRequest', function () {
    it('authorizes all requests', function () {
        $request = new SavePasswordRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid password', function () {
        $validator = validateSecurityRequest(SavePasswordRequest::class, [
            'password' => 'securepassword123',
            'password_repeat' => 'securepassword123',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when password is missing', function () {
        $validator = validateSecurityRequest(SavePasswordRequest::class, [
            'password_repeat' => 'securepassword123',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('password'))->toBeTrue();
    });

    it('fails when password is too short', function () {
        $validator = validateSecurityRequest(SavePasswordRequest::class, [
            'password' => 'short',
            'password_repeat' => 'short',
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// SavePasswordWithCodeRequest
// ============================================================================

describe('SavePasswordWithCodeRequest', function () {
    it('authorizes all requests', function () {
        $request = new SavePasswordWithCodeRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateSecurityRequest(SavePasswordWithCodeRequest::class, [
            'password' => 'securepassword123',
            'password_repeat' => 'securepassword123',
            'token_2fa' => '123456',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when password is missing', function () {
        $validator = validateSecurityRequest(SavePasswordWithCodeRequest::class, [
            'password_repeat' => 'securepassword123',
            'token_2fa' => '123456',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('password'))->toBeTrue();
    });

    it('fails when code is missing', function () {
        $validator = validateSecurityRequest(SavePasswordWithCodeRequest::class, [
            'password' => 'securepassword123',
            'password_repeat' => 'securepassword123',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('token_2fa'))->toBeTrue();
    });
});

// ============================================================================
// EmailVerificationRequest
// ============================================================================

describe('EmailVerificationRequest', function () {
    it('authorizes all requests', function () {
        $request = new EmailVerificationRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid email and uuid', function () {
        $validator = validateSecurityRequest(EmailVerificationRequest::class, [
            'email' => $this->user->email,
            'uuid' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when uuid is missing', function () {
        $validator = validateSecurityRequest(EmailVerificationRequest::class, [
            'email' => $this->user->email,
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('uuid'))->toBeTrue();
    });

    it('fails when uuid is invalid', function () {
        $validator = validateSecurityRequest(EmailVerificationRequest::class, [
            'email' => $this->user->email,
            'uuid' => 'not-a-uuid',
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// SendVerificationMailRequest
// ============================================================================

describe('SendVerificationMailRequest', function () {
    it('authorizes all requests', function () {
        $request = new SendVerificationMailRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid ids array', function () {
        $validator = validateSecurityRequest(SendVerificationMailRequest::class, [
            'ids' => [$this->user->id],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when ids is missing', function () {
        $validator = validateSecurityRequest(SendVerificationMailRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('ids'))->toBeTrue();
    });

    it('fails when ids is not an array', function () {
        $validator = validateSecurityRequest(SendVerificationMailRequest::class, [
            'ids' => $this->user->id,
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// SendVerificationEmailInitializedFromUserRequest
// ============================================================================

describe('SendVerificationEmailInitializedFromUserRequest', function () {
    it('authorizes all requests', function () {
        $request = new SendVerificationEmailInitializedFromUserRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid email', function () {
        $validator = validateSecurityRequest(SendVerificationEmailInitializedFromUserRequest::class, [
            'email' => $this->user->email,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when email is missing', function () {
        $validator = validateSecurityRequest(SendVerificationEmailInitializedFromUserRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('email'))->toBeTrue();
    });

    it('fails when email is invalid', function () {
        $validator = validateSecurityRequest(SendVerificationEmailInitializedFromUserRequest::class, [
            'email' => 'not-an-email',
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when email does not exist', function () {
        $validator = validateSecurityRequest(SendVerificationEmailInitializedFromUserRequest::class, [
            'email' => 'missing@example.com',
        ]);

        expect($validator->fails())->toBeTrue();
    });
});
