<?php

use App\Http\Resources\Tutoring\OfferNotLoggedInResource;
use App\Http\Resources\Tutoring\OfferRequestResource;
use App\Http\Resources\Tutoring\OfferResource as TutoringOfferResource;
use App\Http\Resources\Tutoring\ReceivedOfferRequestResource;
use App\Http\Resources\Tutoring\SchoolToolResource;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TutoringOffer;
use App\Models\TutoringOfferRequest;
use App\Models\TutoringSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create([
        'short_name' => 'SCH',
        'long_name' => 'School',
    ]);
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);
    $this->subject = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'MAT',
        'long_name' => 'Mathematics',
    ]);
});

test('tutoring school tool resource maps tutoring settings', function () {
    $schoolTool = SchoolTool::create([
        'school_id' => $this->school->id,
        'tutoring_student_must_be_confirmed' => 1,
        'tutoring_confirmer_email' => 'mentor@example.test',
        'tutoring_max_offers_per_student' => 3,
        'may_visible_for_other_schools' => 0,
    ]);

    $data = (new SchoolToolResource($schoolTool))->toArray(request());

    expect($data['tutoring_student_must_be_confirmed'])->toBeTrue()
        ->and($data['tutoring_confirmer_email'])->toBe('mentor@example.test')
        ->and($data['may_visible_for_other_schools'])->toBeFalse();
});

test('offer not logged in resource includes subject and school when loaded', function () {
    $offer = TutoringOffer::create([
        'school_id' => $this->school->id,
        'user_id' => User::factory()->create(['school_id' => $this->school->id])->id,
        'subject_id' => $this->subject->id,
        'title' => 'Help',
        'description' => 'Desc',
        'classes' => ['5' => true],
        'active_until' => '2025-01-01',
        'is_active' => true,
        'price_per_hour' => 10,
        'is_group' => 0,
        'max_group_members' => 2,
        'must_be_accepted' => 0,
        'click_count' => 1,
        'visible_for_other_schools' => false,
    ]);

    $offer->load(['subject', 'school']);

    $data = (new OfferNotLoggedInResource($offer))->toArray(request());

    expect($data['subject'])->toMatchArray([
        'short_name' => 'MAT',
        'long_name' => 'Mathematics',
    ])->and($data['school'])->toMatchArray([
        'short_name' => 'SCH',
        'long_name' => 'School',
    ]);
});

test('offer request resource formats dates and includes offer subject', function () {
    $user = User::factory()->create(['school_id' => $this->school->id]);
    $offer = TutoringOffer::create([
        'school_id' => $this->school->id,
        'user_id' => $user->id,
        'subject_id' => $this->subject->id,
        'title' => 'Help',
        'description' => 'Desc',
        'classes' => ['5' => true],
        'active_until' => '2025-01-01',
        'is_active' => true,
        'price_per_hour' => 10,
        'is_group' => 0,
        'max_group_members' => 2,
        'must_be_accepted' => 0,
        'click_count' => 1,
    ]);
    $request = TutoringOfferRequest::create([
        'school_id' => $this->school->id,
        'offer_id' => $offer->id,
        'from_user_id' => $user->id,
        'to_user_id' => $user->id,
        'message' => 'Hello',
        'sent_at' => now(),
    ]);

    $request->load(['school', 'offer.subject']);

    $data = (new OfferRequestResource($request))->toArray(request());

    expect($data['message'])->toBe('Hello')
        ->and($data['offer']['subject']['short_name'])->toBe('MAT');
});

test('tutoring offer resource includes ownership and request data', function () {
    $owner = User::factory()->create(['school_id' => $this->school->id]);
    $offer = TutoringOffer::create([
        'school_id' => $this->school->id,
        'user_id' => $owner->id,
        'subject_id' => $this->subject->id,
        'title' => 'Help',
        'description' => 'Desc',
        'classes' => ['5' => true],
        'active_until' => '2025-01-01',
        'is_active' => true,
        'price_per_hour' => 10,
        'is_group' => 0,
        'max_group_members' => 2,
        'must_be_accepted' => 0,
        'click_count' => 1,
        'visible_for_other_schools' => false,
    ]);
    $request = TutoringOfferRequest::create([
        'school_id' => $this->school->id,
        'offer_id' => $offer->id,
        'from_user_id' => $owner->id,
        'to_user_id' => $owner->id,
        'message' => 'Hello',
        'sent_at' => now(),
    ]);

    $offer->load(['subject', 'school', 'requests']);

    Auth::login($owner);

    $data = (new TutoringOfferResource($offer))->toArray(request());

    expect($data['is_own_offer'])->toBeTrue()
        ->and($data['my_request']['message'])->toBe('Hello');
});

test('received offer request resource includes from_user and offer', function () {
    $fromUser = User::factory()->create(['school_id' => $this->school->id]);
    $offer = TutoringOffer::create([
        'school_id' => $this->school->id,
        'user_id' => $fromUser->id,
        'subject_id' => $this->subject->id,
        'title' => 'Help',
        'description' => 'Desc',
        'classes' => ['5' => true],
        'active_until' => '2025-01-01',
        'is_active' => true,
        'price_per_hour' => 10,
        'is_group' => 0,
        'max_group_members' => 2,
        'must_be_accepted' => 0,
        'click_count' => 1,
    ]);
    $request = TutoringOfferRequest::create([
        'school_id' => $this->school->id,
        'offer_id' => $offer->id,
        'from_user_id' => $fromUser->id,
        'to_user_id' => $fromUser->id,
        'message' => 'Hello',
        'sent_at' => now(),
        'seen_at' => now(),
        'sent_count' => 1,
        'seen_count' => 1,
    ]);

    $request->load(['school', 'offer.subject', 'from_user']);

    $data = (new ReceivedOfferRequestResource($request))->toArray(request());

    expect($data['from_user']['email'])->toBe($fromUser->email)
        ->and($data['offer']['title'])->toBe('Help')
        ->and($data['sent_count'])->toBe(1);
});
