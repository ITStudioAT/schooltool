<?php

/**
 * Admin Authentication Form Request Tests
 *
 * Tests for login, password recovery, and registration step requests.
 */

use App\Http\Requests\Admin\LoginStepEmailRequest;
use App\Http\Requests\Admin\LoginStep2Request;
use App\Http\Requests\Admin\LoginStep3Request;
use App\Http\Requests\Admin\PasswordUnknownStep1Request;
use App\Http\Requests\Admin\PasswordUnknownStep2Request;
use App\Http\Requests\Admin\PasswordUnknownStep3Request;
use App\Http\Requests\Admin\PasswordUnknownStep4Request;
use App\Http\Requests\Admin\PasswordUnknownStepEmailRequest;
use App\Http\Requests\Admin\AdminPasswordUnknownStepPasswordRequest;
use App\Http\Requests\Admin\AdminPasswordUnknownStepSchoolRequest;
use App\Http\Requests\Admin\AdminPasswordUnknownStepTokenRequest;
use App\Http\Requests\Admin\AdminPasswordUnknownStepToken2Request;
use App\Http\Requests\Admin\AdminPasswordUnkownStepPasswordRequest;
use App\Http\Requests\Admin\AdminPasswordUnkownStepTokenRequest;
use App\Http\Requests\Admin\RegisterStep1Request;
use App\Http\Requests\Admin\RegisterStep2Request;
use App\Http\Requests\Admin\RegisterStep3Request;
use App\Http\Requests\Admin\AdminNewTeacherStepCodeRequest;
use App\Http\Requests\Admin\AdminNewTeacherStepEmailRequest;
use App\Http\Requests\Admin\AdminNewTeacherStepSchoolRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

// Helper function to validate request rules
function validateRequest(string $requestClass, array $data): \Illuminate\Validation\Validator
{
    $request = new $requestClass();
    return Validator::make($data, $request->rules());
}

// ============================================================================
// LoginStepEmailRequest
// ============================================================================

describe('LoginStepEmailRequest', function () {
    it('authorizes all requests', function () {
        $request = new LoginStepEmailRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateRequest(LoginStepEmailRequest::class, [
            'data' => [
                'step' => 'LOGIN_ENTER_EMAIL',
                'email' => 'test@example.com',
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when step is missing', function () {
        $validator = validateRequest(LoginStepEmailRequest::class, [
            'data' => [
                'email' => 'test@example.com',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.step'))->toBeTrue();
    });

    it('fails when step has invalid value', function () {
        $validator = validateRequest(LoginStepEmailRequest::class, [
            'data' => [
                'step' => 'INVALID_STEP',
                'email' => 'test@example.com',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when email is missing', function () {
        $validator = validateRequest(LoginStepEmailRequest::class, [
            'data' => [
                'step' => 'LOGIN_ENTER_EMAIL',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.email'))->toBeTrue();
    });

    it('fails when email is invalid format', function () {
        $validator = validateRequest(LoginStepEmailRequest::class, [
            'data' => [
                'step' => 'LOGIN_ENTER_EMAIL',
                'email' => 'not-an-email',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when email exceeds max length', function () {
        $validator = validateRequest(LoginStepEmailRequest::class, [
            'data' => [
                'step' => 'LOGIN_ENTER_EMAIL',
                'email' => str_repeat('a', 250) . '@test.com',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// LoginStep2Request
// ============================================================================

describe('LoginStep2Request', function () {
    it('authorizes all requests', function () {
        $request = new LoginStep2Request();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// LoginStep3Request
// ============================================================================

describe('LoginStep3Request', function () {
    it('authorizes all requests', function () {
        $request = new LoginStep3Request();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// PasswordUnknownStep1Request
// ============================================================================

describe('PasswordUnknownStep1Request', function () {
    it('authorizes all requests', function () {
        $request = new PasswordUnknownStep1Request();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// PasswordUnknownStep2Request
// ============================================================================

describe('PasswordUnknownStep2Request', function () {
    it('authorizes all requests', function () {
        $request = new PasswordUnknownStep2Request();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// PasswordUnknownStep3Request
// ============================================================================

describe('PasswordUnknownStep3Request', function () {
    it('authorizes all requests', function () {
        $request = new PasswordUnknownStep3Request();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// PasswordUnknownStep4Request
// ============================================================================

describe('PasswordUnknownStep4Request', function () {
    it('authorizes all requests', function () {
        $request = new PasswordUnknownStep4Request();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// RegisterStep1Request
// ============================================================================

describe('RegisterStep1Request', function () {
    it('authorizes all requests', function () {
        $request = new RegisterStep1Request();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// RegisterStep2Request
// ============================================================================

describe('RegisterStep2Request', function () {
    it('authorizes all requests', function () {
        $request = new RegisterStep2Request();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// RegisterStep3Request
// ============================================================================

describe('RegisterStep3Request', function () {
    it('authorizes all requests', function () {
        $request = new RegisterStep3Request();
        expect($request->authorize())->toBeTrue();
    });
});
