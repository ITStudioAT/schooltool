<?php

/**
 * Tutoring Form Request Tests
 *
 * Tests for tutoring portal requests (authenticated tutoring users).
 */

use App\Http\Requests\Tutoring\LoginWithPasswordRequest;
use App\Http\Requests\Tutoring\OfferConfirmRefuseRequest;
use App\Http\Requests\Tutoring\OfferIndexRequest;
use App\Http\Requests\Tutoring\OfferLoadOfferConfigRequest;
use App\Http\Requests\Tutoring\OfferLoadOffersRequest;
use App\Http\Requests\Tutoring\OfferRequestIndexRequest;
use App\Http\Requests\Tutoring\OfferRequestMailClickedRequest;
use App\Http\Requests\Tutoring\OfferRequestRequest;
use App\Http\Requests\Tutoring\OfferSendRequestRequest;
use App\Http\Requests\Tutoring\OfferSetUserSearchCriteriaRequest;
use App\Http\Requests\Tutoring\OfferStoreRequest;
use App\Http\Requests\Tutoring\OfferToggleOfferRequest;
use App\Http\Requests\Tutoring\OfferUpdateRequest;
use App\Http\Requests\Tutoring\SubjectCreateSubjectsRequest;
use App\Http\Requests\Tutoring\UserUpdatePasswordRequest;
use App\Http\Requests\Tutoring\UserUpdateRequest;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\TutoringOffer;
use App\Models\TutoringSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    // Create TutoringSubject directly (no factory exists)
    $this->subject = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'M',
        'long_name' => 'Mathematik',
        'is_active' => true,
    ]);
});

function validateTutoringRequest(string $requestClass, array $data): Illuminate\Validation\Validator
{
    $request = new $requestClass;

    return Validator::make($data, $request->rules());
}

// ============================================================================
// LoginWithPasswordRequest
// ============================================================================

describe('LoginWithPasswordRequest', function () {
    it('authorizes all requests', function () {
        $request = new LoginWithPasswordRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateTutoringRequest(LoginWithPasswordRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'test@example.com',
                'user_id' => $this->user->id,
                'password' => 'securepassword123',
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when data.school_id is missing', function () {
        $validator = validateTutoringRequest(LoginWithPasswordRequest::class, [
            'data' => [
                'email' => 'test@example.com',
                'user_id' => $this->user->id,
                'password' => 'securepassword123',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.school_id'))->toBeTrue();
    });

    it('fails when data.email is missing', function () {
        $validator = validateTutoringRequest(LoginWithPasswordRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'user_id' => $this->user->id,
                'password' => 'securepassword123',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.email'))->toBeTrue();
    });

    it('fails when data.user_id is missing', function () {
        $validator = validateTutoringRequest(LoginWithPasswordRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'test@example.com',
                'password' => 'securepassword123',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.user_id'))->toBeTrue();
    });

    it('fails when data.password is missing', function () {
        $validator = validateTutoringRequest(LoginWithPasswordRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'test@example.com',
                'user_id' => $this->user->id,
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.password'))->toBeTrue();
    });

    it('fails when password is too short', function () {
        $validator = validateTutoringRequest(LoginWithPasswordRequest::class, [
            'data' => [
                'school_id' => $this->school->id,
                'email' => 'test@example.com',
                'user_id' => $this->user->id,
                'password' => 'short',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// OfferIndexRequest
// ============================================================================

describe('OfferIndexRequest', function () {
    it('requires authentication', function () {
        $request = new OfferIndexRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new OfferIndexRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid search parameters', function () {
        $validator = validateTutoringRequest(OfferIndexRequest::class, [
            'search_string' => 'mathematik',
            'page' => 1,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('passes with empty data', function () {
        $validator = validateTutoringRequest(OfferIndexRequest::class, []);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when search_string exceeds max length', function () {
        $validator = validateTutoringRequest(OfferIndexRequest::class, [
            'search_string' => str_repeat('a', 256),
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// OfferLoadOfferConfigRequest
// ============================================================================

describe('OfferLoadOfferConfigRequest', function () {
    it('authorizes all requests', function () {
        $request = new OfferLoadOfferConfigRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateTutoringRequest(OfferLoadOfferConfigRequest::class, [
            'school_name' => 'Test School',
        ]);

        expect($validator->passes())->toBeTrue();
    });
});

// ============================================================================
// OfferLoadOffersRequest
// ============================================================================

describe('OfferLoadOffersRequest', function () {
    it('authorizes all requests', function () {
        $request = new OfferLoadOffersRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateTutoringRequest(OfferLoadOffersRequest::class, [
            'school_name' => 'Test School',
            'search_string' => 'mathematik',
            'page' => 1,
        ]);

        expect($validator->passes())->toBeTrue();
    });
});

// ============================================================================
// OfferToggleOfferRequest
// ============================================================================

describe('OfferToggleOfferRequest', function () {
    it('requires authentication', function () {
        $request = new OfferToggleOfferRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Role::firstOrCreate(['name' => 'tutoring_user', 'guard_name' => 'web']);
        $this->user->assignRole('tutoring_user');
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Math Tutoring',
            'is_active' => true,
            'price_per_hour' => 15,
        ]);
        $request = OfferToggleOfferRequest::create('/', 'POST', ['id' => $offer->id]);
        $request->setUserResolver(fn () => $this->user);

        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid offer id', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Math Tutoring',
            'is_active' => true,
            'price_per_hour' => 15,
        ]);

        $validator = validateTutoringRequest(OfferToggleOfferRequest::class, [
            'id' => $offer->id,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when id is missing', function () {
        $validator = validateTutoringRequest(OfferToggleOfferRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('id'))->toBeTrue();
    });

    it('fails when id does not exist', function () {
        $validator = validateTutoringRequest(OfferToggleOfferRequest::class, [
            'id' => 99999,
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// OfferStoreRequest
// ============================================================================

describe('OfferStoreRequest', function () {
    it('requires authentication', function () {
        $request = new OfferStoreRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new OfferStoreRequest;
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// OfferUpdateRequest
// ============================================================================

describe('OfferUpdateRequest', function () {
    it('requires authentication', function () {
        $request = new OfferUpdateRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('requires an authorized route-bound offer', function () {
        $request = new OfferUpdateRequest;
        expect($request->authorize())->toBeFalse();
    });
});

// ============================================================================
// OfferSendRequestRequest
// ============================================================================

describe('OfferSendRequestRequest', function () {
    it('requires authentication', function () {
        $request = new OfferSendRequestRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Role::firstOrCreate(['name' => 'tutoring_user', 'guard_name' => 'web']);
        $this->user->assignRole('tutoring_user');
        $owner = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $owner->id,
            'subject_id' => $this->subject->id,
            'title' => 'Math Tutoring',
            'is_active' => true,
            'price_per_hour' => 15,
        ]);
        $offer->forceFill(['accepted_at' => now()])->save();
        $request = OfferSendRequestRequest::create('/', 'POST', ['offer_id' => $offer->id]);
        $request->setUserResolver(fn () => $this->user);

        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Math Tutoring',
            'is_active' => true,
            'price_per_hour' => 15,
        ]);

        $validator = validateTutoringRequest(OfferSendRequestRequest::class, [
            'offer_id' => $offer->id,
            'request_message' => 'Ich brauche Hilfe in Mathematik',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when offer_id is missing', function () {
        $validator = validateTutoringRequest(OfferSendRequestRequest::class, [
            'request_message' => 'Test message',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('offer_id'))->toBeTrue();
    });

    it('fails when offer_id does not exist', function () {
        $validator = validateTutoringRequest(OfferSendRequestRequest::class, [
            'offer_id' => 99999,
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('allows nullable request_message', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Math Tutoring',
            'is_active' => true,
            'price_per_hour' => 15,
        ]);

        $validator = validateTutoringRequest(OfferSendRequestRequest::class, [
            'offer_id' => $offer->id,
            'request_message' => null,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when request_message exceeds max length', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Math Tutoring',
            'is_active' => true,
            'price_per_hour' => 15,
        ]);

        $validator = validateTutoringRequest(OfferSendRequestRequest::class, [
            'offer_id' => $offer->id,
            'request_message' => str_repeat('a', 1025),
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// OfferConfirmRefuseRequest
// ============================================================================

describe('OfferConfirmRefuseRequest', function () {
    it('authorizes all requests', function () {
        $request = new OfferConfirmRefuseRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Math Tutoring',
            'is_active' => true,
            'price_per_hour' => 15,
        ]);

        $validator = validateTutoringRequest(OfferConfirmRefuseRequest::class, [
            'action' => 'confirm',
            'offer_id' => $offer->id,
            'token' => 'some-token',
            'email_mentor' => 'mentor@example.com',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when action is invalid', function () {
        $validator = validateTutoringRequest(OfferConfirmRefuseRequest::class, [
            'action' => 'invalid',
            'offer_id' => 1,
            'token' => 'some-token',
            'email_mentor' => 'mentor@example.com',
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// OfferRequestIndexRequest
// ============================================================================

describe('OfferRequestIndexRequest', function () {
    it('requires authentication', function () {
        $request = new OfferRequestIndexRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new OfferRequestIndexRequest;
        expect($request->authorize())->toBeTrue();
    });
});

// ============================================================================
// OfferRequestMailClickedRequest
// ============================================================================

describe('OfferRequestMailClickedRequest', function () {
    it('requires authentication', function () {
        $request = new OfferRequestMailClickedRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('requires an authorized request recipient', function () {
        $request = new OfferRequestMailClickedRequest;
        expect($request->authorize())->toBeFalse();
    });
});

// ============================================================================
// OfferRequestRequest
// ============================================================================

describe('OfferRequestRequest', function () {
    it('authorizes all requests', function () {
        $request = new OfferRequestRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateTutoringRequest(OfferRequestRequest::class, [
            'email' => 'test@example.com',
            'id' => 123,
            'token' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when email is missing', function () {
        $validator = validateTutoringRequest(OfferRequestRequest::class, [
            'id' => 123,
            'token' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('email'))->toBeTrue();
    });

    it('fails when id is missing', function () {
        $validator = validateTutoringRequest(OfferRequestRequest::class, [
            'email' => 'test@example.com',
            'token' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('id'))->toBeTrue();
    });

    it('fails when token is missing', function () {
        $validator = validateTutoringRequest(OfferRequestRequest::class, [
            'email' => 'test@example.com',
            'id' => 123,
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('token'))->toBeTrue();
    });

    it('fails when token is not a valid uuid', function () {
        $validator = validateTutoringRequest(OfferRequestRequest::class, [
            'email' => 'test@example.com',
            'id' => 123,
            'token' => 'not-a-uuid',
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// OfferSetUserSearchCriteriaRequest
// ============================================================================

describe('OfferSetUserSearchCriteriaRequest', function () {
    it('requires authentication', function () {
        $request = new OfferSetUserSearchCriteriaRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new OfferSetUserSearchCriteriaRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with empty data', function () {
        $validator = validateTutoringRequest(OfferSetUserSearchCriteriaRequest::class, []);

        expect($validator->passes())->toBeTrue();
    });

    it('passes with valid search criteria', function () {
        $validator = validateTutoringRequest(OfferSetUserSearchCriteriaRequest::class, [
            'only_in_my_school' => true,
            'only_girls' => false,
            'only_boys' => false,
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('passes with valid schools array', function () {
        $validator = validateTutoringRequest(OfferSetUserSearchCriteriaRequest::class, [
            'schools' => [
                ['id' => $this->school->id],
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });
});

// ============================================================================
// SubjectCreateSubjectsRequest
// ============================================================================

describe('SubjectCreateSubjectsRequest', function () {
    it('requires authentication', function () {
        $request = new SubjectCreateSubjectsRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new SubjectCreateSubjectsRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data array', function () {
        $validator = validateTutoringRequest(SubjectCreateSubjectsRequest::class, [
            'data' => [
                ['short_name' => 'M', 'long_name' => 'Mathematik', 'must_be_accepted' => false],
                ['short_name' => 'D', 'long_name' => 'Deutsch', 'must_be_accepted' => true],
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when data is missing', function () {
        $validator = validateTutoringRequest(SubjectCreateSubjectsRequest::class, []);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data'))->toBeTrue();
    });

    it('fails when data is not an array', function () {
        $validator = validateTutoringRequest(SubjectCreateSubjectsRequest::class, [
            'data' => 'not-an-array',
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('passes with email_mentors array', function () {
        $validator = validateTutoringRequest(SubjectCreateSubjectsRequest::class, [
            'data' => [
                [
                    'short_name' => 'M',
                    'long_name' => 'Mathematik',
                    'email_mentors' => ['mentor1@example.com', 'mentor2@example.com'],
                ],
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when email_mentors contains invalid email', function () {
        $validator = validateTutoringRequest(SubjectCreateSubjectsRequest::class, [
            'data' => [
                [
                    'short_name' => 'M',
                    'long_name' => 'Mathematik',
                    'email_mentors' => ['not-an-email'],
                ],
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// UserUpdatePasswordRequest
// ============================================================================

describe('UserUpdatePasswordRequest', function () {
    it('requires authentication', function () {
        $request = new UserUpdatePasswordRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('authorizes authenticated users', function () {
        Auth::shouldReceive('check')->andReturn(true);
        $request = new UserUpdatePasswordRequest;
        expect($request->authorize())->toBeTrue();
    });

    it('passes with valid data', function () {
        $validator = validateTutoringRequest(UserUpdatePasswordRequest::class, [
            'data' => [
                'id' => $this->user->id,
                'password' => 'newsecurepassword',
                'password_confirm' => 'newsecurepassword',
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when data.id is missing', function () {
        $validator = validateTutoringRequest(UserUpdatePasswordRequest::class, [
            'data' => [
                'password' => 'newsecurepassword',
                'password_confirm' => 'newsecurepassword',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.id'))->toBeTrue();
    });

    it('fails when data.password is missing', function () {
        $validator = validateTutoringRequest(UserUpdatePasswordRequest::class, [
            'data' => [
                'id' => $this->user->id,
                'password_confirm' => 'newsecurepassword',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.password'))->toBeTrue();
    });

    it('fails when password_confirm does not match', function () {
        $validator = validateTutoringRequest(UserUpdatePasswordRequest::class, [
            'data' => [
                'id' => $this->user->id,
                'password' => 'newsecurepassword',
                'password_confirm' => 'differentpassword',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when password is too short', function () {
        $validator = validateTutoringRequest(UserUpdatePasswordRequest::class, [
            'data' => [
                'id' => $this->user->id,
                'password' => 'short',
                'password_confirm' => 'short',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('validates token_2fa size when provided', function () {
        $validator = validateTutoringRequest(UserUpdatePasswordRequest::class, [
            'data' => [
                'id' => $this->user->id,
                'password' => 'newsecurepassword',
                'password_confirm' => 'newsecurepassword',
                'token_2fa' => '12345', // Should be 6 characters
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});

// ============================================================================
// UserUpdateRequest
// ============================================================================

describe('UserUpdateRequest', function () {
    it('requires authentication', function () {
        $request = new UserUpdateRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('requires an authorized route-bound user', function () {
        $request = new UserUpdateRequest;
        expect($request->authorize())->toBeFalse();
    });

    it('passes with valid data', function () {
        $validator = validateTutoringRequest(UserUpdateRequest::class, [
            'data' => [
                'id' => $this->user->id,
                'email' => 'updated@example.com',
                'last_name' => 'Müller',
                'first_name' => 'Hans',
                'sex' => 'm',
                'schoolclass' => '11B',
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('fails when id is missing', function () {
        $validator = validateTutoringRequest(UserUpdateRequest::class, [
            'data' => [
                'email' => 'updated@example.com',
                'last_name' => 'Müller',
                'sex' => 'm',
                'schoolclass' => '11B',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.id'))->toBeTrue();
    });

    it('fails when email is missing', function () {
        $validator = validateTutoringRequest(UserUpdateRequest::class, [
            'data' => [
                'id' => $this->user->id,
                'last_name' => 'Müller',
                'sex' => 'm',
                'schoolclass' => '11B',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.email'))->toBeTrue();
    });

    it('fails when last_name is missing', function () {
        $validator = validateTutoringRequest(UserUpdateRequest::class, [
            'data' => [
                'id' => $this->user->id,
                'email' => 'updated@example.com',
                'sex' => 'm',
                'schoolclass' => '11B',
            ],
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('data.last_name'))->toBeTrue();
    });

    it('fails when sex has invalid value', function () {
        $validator = validateTutoringRequest(UserUpdateRequest::class, [
            'data' => [
                'id' => $this->user->id,
                'email' => 'updated@example.com',
                'last_name' => 'Müller',
                'sex' => 'x',
                'schoolclass' => '11B',
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('fails when schoolclass exceeds max length', function () {
        $validator = validateTutoringRequest(UserUpdateRequest::class, [
            'data' => [
                'id' => $this->user->id,
                'email' => 'updated@example.com',
                'last_name' => 'Müller',
                'sex' => 'm',
                'schoolclass' => str_repeat('A', 11),
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });

    it('allows nullable first_name', function () {
        $validator = validateTutoringRequest(UserUpdateRequest::class, [
            'data' => [
                'id' => $this->user->id,
                'email' => 'updated@example.com',
                'last_name' => 'Müller',
                'first_name' => null,
                'sex' => 'm',
                'schoolclass' => '11B',
            ],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('validates token_2fa size when provided', function () {
        $validator = validateTutoringRequest(UserUpdateRequest::class, [
            'data' => [
                'id' => $this->user->id,
                'email' => 'updated@example.com',
                'last_name' => 'Müller',
                'sex' => 'm',
                'schoolclass' => '11B',
                'token_2fa' => '12345', // Should be 6 characters
            ],
        ]);

        expect($validator->fails())->toBeTrue();
    });
});
