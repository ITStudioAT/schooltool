<?php

use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TutoringOffer;
use App\Models\TutoringOfferRequest;
use App\Models\TutoringSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    Role::firstOrCreate(['name' => 'tutoring_user', 'guard_name' => 'web']);

    $this->school = School::factory()->create(['short_name' => 'OWN']);
    $this->otherSchool = School::factory()->create(['short_name' => 'OTHER']);

    grantSchoolToolLicenceForTests($this->school, 'Nachhilfetool');
    grantSchoolToolLicenceForTests($this->otherSchool, 'Nachhilfetool');

    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'tutoring_visible_user' => true,
        'may_visible_for_other_schools' => true,
    ]);
    SchoolTool::factory()->create([
        'school_id' => $this->otherSchool->id,
        'tutoring_visible_user' => true,
        'may_visible_for_other_schools' => true,
    ]);

    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->otherSchoolyear = Schoolyear::factory()->create(['school_id' => $this->otherSchool->id]);

    $this->owner = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'owner@example.test',
        'first_name' => 'Owner',
        'last_name' => 'Original',
        'schoolclass' => '10A',
        'sex' => 'm',
    ]);
    $this->sameSchoolUser = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'peer@example.test',
        'first_name' => 'Peer',
        'last_name' => 'Original',
        'schoolclass' => '10B',
        'sex' => 'f',
    ]);
    $this->otherSchoolUser = User::factory()->create([
        'school_id' => $this->otherSchool->id,
        'schoolyear_id' => $this->otherSchoolyear->id,
        'email' => 'other-school@example.test',
        'first_name' => 'Other',
        'last_name' => 'Original',
        'schoolclass' => '10C',
        'sex' => 'd',
    ]);

    $this->owner->assignRole('tutoring_user');
    $this->sameSchoolUser->assignRole('tutoring_user');
    $this->otherSchoolUser->assignRole('tutoring_user');

    $this->subject = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'MAT',
        'long_name' => 'Mathematik',
        'must_be_accepted' => false,
    ]);
    $this->otherSchoolSubject = TutoringSubject::create([
        'school_id' => $this->otherSchool->id,
        'short_name' => 'ENG',
        'long_name' => 'English',
        'must_be_accepted' => false,
    ]);

    $this->ownerOffer = TutoringOffer::create([
        'school_id' => $this->school->id,
        'user_id' => $this->owner->id,
        'subject_id' => $this->subject->id,
        'title' => 'Owner offer',
        'classes' => ['10' => true],
        'price_per_hour' => 10,
        'is_group' => false,
        'max_group_members' => 2,
    ]);
    $this->sameSchoolOffer = TutoringOffer::create([
        'school_id' => $this->school->id,
        'user_id' => $this->sameSchoolUser->id,
        'subject_id' => $this->subject->id,
        'title' => 'Peer offer',
        'classes' => ['10' => true],
        'price_per_hour' => 10,
        'is_group' => false,
        'max_group_members' => 2,
    ]);
    $this->otherSchoolOffer = TutoringOffer::create([
        'school_id' => $this->otherSchool->id,
        'user_id' => $this->otherSchoolUser->id,
        'subject_id' => $this->otherSchoolSubject->id,
        'title' => 'Other school offer',
        'classes' => ['10' => true],
        'price_per_hour' => 10,
        'is_group' => false,
        'max_group_members' => 2,
    ]);

    $this->receivedRequest = TutoringOfferRequest::create([
        'school_id' => $this->school->id,
        'offer_id' => $this->ownerOffer->id,
        'from_user_id' => $this->sameSchoolUser->id,
        'to_user_id' => $this->owner->id,
        'message' => 'I need help.',
        'sent_at' => now(),
    ]);
    $this->sameSchoolRequest = TutoringOfferRequest::create([
        'school_id' => $this->school->id,
        'offer_id' => $this->sameSchoolOffer->id,
        'from_user_id' => $this->owner->id,
        'to_user_id' => $this->sameSchoolUser->id,
        'message' => 'Peer request.',
        'sent_at' => now(),
    ]);
    $this->otherSchoolRequest = TutoringOfferRequest::create([
        'school_id' => $this->otherSchool->id,
        'offer_id' => $this->otherSchoolOffer->id,
        'from_user_id' => $this->owner->id,
        'to_user_id' => $this->otherSchoolUser->id,
        'message' => 'Other school request.',
        'sent_at' => now(),
    ]);

    $this->profilePayload = fn (User $user, array $overrides = []): array => [
        'data' => array_merge([
            'id' => $user->id,
            'email' => $user->email,
            'last_name' => $user->last_name,
            'first_name' => $user->first_name,
            'sex' => $user->sex,
            'schoolclass' => $user->schoolclass,
        ], $overrides),
    ];

    $this->offerPayload = fn (TutoringOffer $offer, array $overrides = []): array => array_merge([
        'id' => $offer->id,
        'subject_id' => $offer->subject_id,
        'title' => $offer->title,
        'description' => $offer->description,
        'classes' => $offer->classes->getArrayCopy(),
        'active_until' => $offer->active_until,
        'is_group' => $offer->is_group,
        'max_group_members' => $offer->max_group_members,
        'price_per_hour' => (int) $offer->price_per_hour,
        'email_mentor' => $offer->email_mentor,
        'visible_for_other_schools' => (bool) $offer->visible_for_other_schools,
    ], $overrides);
});

test('a tutoring user can update their own profile', function () {
    $response = $this->actingAs($this->owner)->putJson(
        "/api/homepage/tutoring/users/{$this->owner->id}",
        ($this->profilePayload)($this->owner, ['last_name' => 'Updated']),
    );

    $response->assertSuccessful()->assertJsonPath('status', 'OK');

    expect($this->owner->refresh()->last_name)->toBe('Updated');
});

test('a tutoring user cannot update another profile in the same school', function () {
    $response = $this->actingAs($this->owner)->putJson(
        "/api/homepage/tutoring/users/{$this->sameSchoolUser->id}",
        ($this->profilePayload)($this->sameSchoolUser, ['last_name' => 'Compromised']),
    );

    $response->assertForbidden();

    expect($this->sameSchoolUser->refresh()->last_name)->toBe('Original');
});

test('a tutoring user cannot update a profile in another school', function () {
    $response = $this->actingAs($this->owner)->putJson(
        "/api/homepage/tutoring/users/{$this->otherSchoolUser->id}",
        ($this->profilePayload)($this->otherSchoolUser, ['last_name' => 'Compromised']),
    );

    $response->assertForbidden();

    expect($this->otherSchoolUser->refresh()->last_name)->toBe('Original');
});

test('a tutoring profile body id must match the route user', function () {
    $payload = ($this->profilePayload)($this->owner, [
        'id' => $this->sameSchoolUser->id,
        'last_name' => 'Compromised',
    ]);

    $response = $this->actingAs($this->owner)->putJson(
        "/api/homepage/tutoring/users/{$this->owner->id}",
        $payload,
    );

    $response->assertForbidden();

    expect($this->owner->refresh()->last_name)->toBe('Original')
        ->and($this->sameSchoolUser->refresh()->last_name)->toBe('Original');
});

test('a tutoring user can update their own offer', function () {
    $response = $this->actingAs($this->owner)->putJson(
        "/api/homepage/tutoring/offers/{$this->ownerOffer->id}",
        ($this->offerPayload)($this->ownerOffer, ['title' => 'Updated offer']),
    );

    $response->assertSuccessful();

    expect($this->ownerOffer->refresh()->title)->toBe('Updated offer');
});

test('a tutoring user cannot update another offer in the same school', function () {
    $response = $this->actingAs($this->owner)->putJson(
        "/api/homepage/tutoring/offers/{$this->sameSchoolOffer->id}",
        ($this->offerPayload)($this->sameSchoolOffer, ['title' => 'Compromised']),
    );

    $response->assertForbidden();

    expect($this->sameSchoolOffer->refresh()->title)->toBe('Peer offer');
});

test('a tutoring user cannot update an offer in another school', function () {
    $response = $this->actingAs($this->owner)->putJson(
        "/api/homepage/tutoring/offers/{$this->otherSchoolOffer->id}",
        ($this->offerPayload)($this->otherSchoolOffer, ['title' => 'Compromised']),
    );

    $response->assertForbidden();

    expect($this->otherSchoolOffer->refresh()->title)->toBe('Other school offer');
});

test('a tutoring offer body id must match the route offer', function () {
    $response = $this->actingAs($this->owner)->putJson(
        "/api/homepage/tutoring/offers/{$this->ownerOffer->id}",
        ($this->offerPayload)($this->ownerOffer, [
            'id' => $this->sameSchoolOffer->id,
            'title' => 'Compromised',
        ]),
    );

    $response->assertForbidden();

    expect($this->ownerOffer->refresh()->title)->toBe('Owner offer')
        ->and($this->sameSchoolOffer->refresh()->title)->toBe('Peer offer');
});

test('the recipient can mark a received tutoring request mail as clicked', function () {
    $response = $this->actingAs($this->owner)->postJson(
        '/api/homepage/tutoring/request_mail_clicked',
        ['request_id' => $this->receivedRequest->id],
    );

    $response->assertSuccessful();

    expect($this->receivedRequest->refresh()->mail_at)->not->toBeNull();
});

test('a tutoring user cannot mark another recipient request in the same school', function () {
    $response = $this->actingAs($this->owner)->postJson(
        '/api/homepage/tutoring/request_mail_clicked',
        ['request_id' => $this->sameSchoolRequest->id],
    );

    $response->assertForbidden();

    expect($this->sameSchoolRequest->refresh()->mail_at)->toBeNull();
});

test('a tutoring user cannot mark a request in another school', function () {
    $response = $this->actingAs($this->owner)->postJson(
        '/api/homepage/tutoring/request_mail_clicked',
        ['request_id' => $this->otherSchoolRequest->id],
    );

    $response->assertForbidden();

    expect($this->otherSchoolRequest->refresh()->mail_at)->toBeNull();
});
