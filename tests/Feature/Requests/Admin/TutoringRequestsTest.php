<?php

/**
 * Admin Tutoring Form Request Tests
 *
 * Tests for tutoring-related admin requests.
 */

use App\Http\Requests\Admin\SchoolToolSaveTutoringSettingsRequest;
use App\Http\Requests\Admin\Tutoring\OfferIndexRequest;
use App\Http\Requests\Admin\Tutoring\SubjectUpdateSubjectRequest;
use App\Http\Requests\Admin\Tutoring\UserConfirmUsersRequest;
use App\Http\Requests\Admin\Tutoring\UserDeleteUsersRequest;
use App\Http\Requests\Admin\Tutoring\UserIndexRequest;
use App\Http\Requests\Admin\Tutoring\UserStoreRequest;
use App\Http\Requests\Admin\Tutoring\UserUpdateRequest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\SchoolTool;
use App\Models\TutoringSubject;
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
});

function validateAdminTutoringRequest(string $requestClass, array $data): \Illuminate\Validation\Validator
{
    $request = new $requestClass();
    return Validator::make($data, $request->rules());
}

// ============================================================================
// SchoolToolSaveTutoringSettingsRequest
// ============================================================================

describe('SchoolToolSaveTutoringSettingsRequest', function () {
    it('requires authentication', function () {
        $request = new SchoolToolSaveTutoringSettingsRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new SchoolToolSaveTutoringSettingsRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid settings', function () {
        $schoolTool = SchoolTool::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $validator = validateAdminTutoringRequest(SchoolToolSaveTutoringSettingsRequest::class, [
            'data' => [
                'id' => $schoolTool->id,
                'tutoring_student_must_be_confirmed' => true,
                'tutoring_confirmer_email' => 'admin@example.com',
                'tutoring_max_offers_per_student' => 2,
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when data is missing', function () {
        $validator = validateAdminTutoringRequest(SchoolToolSaveTutoringSettingsRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.id'))->toBeTrue();
    });
});

// ============================================================================
// Admin\Tutoring\OfferIndexRequest
// ============================================================================

describe('Admin\Tutoring\OfferIndexRequest', function () {
    it('requires authentication', function () {
        $request = new OfferIndexRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new OfferIndexRequest();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// Admin\Tutoring\SubjectUpdateSubjectRequest
// ============================================================================

describe('Admin\Tutoring\SubjectUpdateSubjectRequest', function () {
    it('requires authentication', function () {
        $request = new SubjectUpdateSubjectRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new SubjectUpdateSubjectRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $subject = TutoringSubject::create([
            'school_id' => $this->school->id,
            'short_name' => 'M',
            'long_name' => 'Mathematik',
            'is_active' => true,
        ]);

        $validator = validateAdminTutoringRequest(SubjectUpdateSubjectRequest::class, [
            'data' => [
                'id' => $subject->id,
                'short_name' => 'M',
                'long_name' => 'Mathematik',
                'must_be_accepted' => true,
                'email_mentors' => ['mentor@example.com'],
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when id is missing', function () {
        $validator = validateAdminTutoringRequest(SubjectUpdateSubjectRequest::class, [
            'data' => [
                'short_name' => 'M',
                'long_name' => 'Mathematik',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.id'))->toBeTrue();
    });

    it('fails when long_name is missing', function () {
        $subject = TutoringSubject::create([
            'school_id' => $this->school->id,
            'short_name' => 'M',
            'long_name' => 'Mathematik',
            'is_active' => true,
        ]);

        $validator = validateAdminTutoringRequest(SubjectUpdateSubjectRequest::class, [
            'data' => [
                'id' => $subject->id,
                'short_name' => 'M',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.long_name'))->toBeTrue();
    });

    it('fails when id does not exist', function () {
        $validator = validateAdminTutoringRequest(SubjectUpdateSubjectRequest::class, [
            'data' => [
                'id' => 99999,
                'short_name' => 'M',
                'long_name' => 'Mathematik',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// Admin\Tutoring\UserConfirmUsersRequest
// ============================================================================

describe('Admin\Tutoring\UserConfirmUsersRequest', function () {
    it('requires authentication', function () {
        $request = new UserConfirmUsersRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new UserConfirmUsersRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid user ids', function () {
        $validator = validateAdminTutoringRequest(UserConfirmUsersRequest::class, [
            'data' => [$this->user->id],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when data is missing', function () {
        $validator = validateAdminTutoringRequest(UserConfirmUsersRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data'))->toBeTrue();
    });

    it('fails when data is not an array', function () {
        $validator = validateAdminTutoringRequest(UserConfirmUsersRequest::class, [
            'data' => 1,
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when data contains non-existent user', function () {
        $validator = validateAdminTutoringRequest(UserConfirmUsersRequest::class, [
            'data' => [99999],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// Admin\Tutoring\UserDeleteUsersRequest
// ============================================================================

describe('Admin\Tutoring\UserDeleteUsersRequest', function () {
    it('requires authentication', function () {
        $request = new UserDeleteUsersRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new UserDeleteUsersRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid user ids', function () {
        $validator = validateAdminTutoringRequest(UserDeleteUsersRequest::class, [
            'data' => [$this->user->id],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when data is missing', function () {
        $validator = validateAdminTutoringRequest(UserDeleteUsersRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data'))->toBeTrue();
    });

    it('fails when data is not an array', function () {
        $validator = validateAdminTutoringRequest(UserDeleteUsersRequest::class, [
            'data' => 1,
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when data contains non-existent user', function () {
        $validator = validateAdminTutoringRequest(UserDeleteUsersRequest::class, [
            'data' => [99999],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// Admin\Tutoring\UserIndexRequest
// ============================================================================

describe('Admin\Tutoring\UserIndexRequest', function () {
    it('requires authentication', function () {
        $request = new UserIndexRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new UserIndexRequest();
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// Admin\Tutoring\UserStoreRequest
// ============================================================================

describe('Admin\Tutoring\UserStoreRequest', function () {
    it('requires authentication', function () {
        $request = new UserStoreRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new UserStoreRequest();
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateAdminTutoringRequest(UserStoreRequest::class, [
            'email' => 'student@example.com',
            'last_name' => 'Müller',
            'first_name' => 'Anna',
            'schoolclass' => '10A',
            'sex' => 'f',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when email is missing', function () {
        $validator = validateAdminTutoringRequest(UserStoreRequest::class, [
            'last_name' => 'Müller',
            'first_name' => 'Anna',
            'schoolclass' => '10A',
            'sex' => 'f',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('email'))->toBeTrue();
    });

    it('fails when email is invalid', function () {
        $validator = validateAdminTutoringRequest(UserStoreRequest::class, [
            'email' => 'not-an-email',
            'last_name' => 'Müller',
            'schoolclass' => '10A',
            'sex' => 'f',
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when last_name is missing', function () {
        $validator = validateAdminTutoringRequest(UserStoreRequest::class, [
            'email' => 'student@example.com',
            'schoolclass' => '10A',
            'sex' => 'f',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('last_name'))->toBeTrue();
    });

    it('fails when sex has invalid value', function () {
        $validator = validateAdminTutoringRequest(UserStoreRequest::class, [
            'email' => 'student@example.com',
            'last_name' => 'Müller',
            'schoolclass' => '10A',
            'sex' => 'x',
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('passes with valid sex values m f d', function () {
        foreach (['m', 'f', 'd'] as $sex) {
            $validator = validateAdminTutoringRequest(UserStoreRequest::class, [
                'email' => "student{$sex}@example.com",
                'last_name' => 'Müller',
                'schoolclass' => '10A',
                'sex' => $sex,
            ]);

            expect($validator->passes())->toBeTrue();
        }
    });
});

// ============================================================================
// Admin\Tutoring\UserUpdateRequest
// ============================================================================

describe('Admin\Tutoring\UserUpdateRequest', function () {
    it('requires authentication', function () {
        $request = new UserUpdateRequest();
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new UserUpdateRequest();
        expect($request->authorize())->toBeTrue();
    });
});
