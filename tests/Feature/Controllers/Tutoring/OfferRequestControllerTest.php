<?php

namespace Tests\Feature\Controllers\Tutoring;

use App\Http\Controllers\Tutoring\OfferRequestController;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TutoringOffer;
use App\Models\TutoringOfferRequest;
use App\Models\TutoringSubject;
use App\Models\User;
use App\Services\TutoringOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Mockery;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    Role::firstOrCreate(['name' => 'tutoring_user', 'guard_name' => 'web']);

    // Create schools
    $this->school1 = School::factory()->create([
        'short_name' => 'SCHUL1',
        'long_name' => 'Testschule 1'
    ]);

    $this->school2 = School::factory()->create([
        'short_name' => 'SCHUL2',
        'long_name' => 'Testschule 2'
    ]);

    // Create licence
    $this->licence = Licence::create(['name' => 'Nachhilfetool']);
    $this->school1->licences()->attach($this->licence->id, [
        'valid_until' => now()->addYear(),
    ]);
    $this->school2->licences()->attach($this->licence->id, [
        'valid_until' => now()->addYear(),
    ]);

    // Create school tools
    SchoolTool::create([
        'school_id' => $this->school1->id,
        'tutoring_student_must_be_confirmed' => false,
        'tutoring_max_offers_per_student' => 3,
    ]);

    SchoolTool::create([
        'school_id' => $this->school2->id,
        'tutoring_student_must_be_confirmed' => false,
        'tutoring_max_offers_per_student' => 3,
    ]);

    // Create schoolyears
    $this->schoolyear1 = Schoolyear::factory()->create([
        'school_id' => $this->school1->id,
    ]);

    $this->schoolyear2 = Schoolyear::factory()->create([
        'school_id' => $this->school2->id,
    ]);

    // Create users
    $this->mentor = User::factory()->create([
        'first_name' => 'Mentor',
        'last_name' => 'User',
        'email' => 'mentor@test.com',
        'school_id' => $this->school1->id,
        'schoolyear_id' => $this->schoolyear1->id,
    ]);
    $this->mentor->assignRole('tutoring_user');

    $this->student = User::factory()->create([
        'first_name' => 'Student',
        'last_name' => 'User',
        'email' => 'student@test.com',
        'school_id' => $this->school1->id,
        'schoolyear_id' => $this->schoolyear1->id,
    ]);
    $this->student->assignRole('tutoring_user');

    $this->otherUser = User::factory()->create([
        'first_name' => 'Other',
        'last_name' => 'User',
        'email' => 'other@test.com',
        'school_id' => $this->school2->id,
        'schoolyear_id' => $this->schoolyear2->id,
    ]);
    $this->otherUser->assignRole('tutoring_user');

    $this->regularUser = User::factory()->create([
        'first_name' => 'Regular',
        'last_name' => 'User',
        'email' => 'regular@test.com',
        'school_id' => $this->school1->id,
        'schoolyear_id' => $this->schoolyear1->id,
    ]);

    // Create subjects
    $this->subject = TutoringSubject::create([
        'school_id' => $this->school1->id,
        'short_name' => 'M',
        'long_name' => 'Mathematik',
    ]);

    // Create offer
    $this->offer = TutoringOffer::create([
        'user_id' => $this->mentor->id,
        'school_id' => $this->school1->id,
        'schoolyear_id' => $this->schoolyear1->id,
        'subject_id' => $this->subject->id,
        'title' => 'Mathe Nachhilfe',
        'description' => 'Beschreibung',
        'classes' => json_encode([5 => true, 6 => true]),
        'is_active' => true,
        'is_group' => false,
        'max_group_members' => 3,
        'price_per_hour' => 15,
        'visible_for_other_schools' => false,
    ]);
    $this->offer->accepted_at = now();
    $this->offer->save();
});

// ============================
// index() tests - User's sent requests
// ============================

test('index requires authentication', function () {
    $response = $this->getJson('/api/homepage/tutoring/offer_requests');
    $response->assertStatus(401);
});

test('index requires tutoring_user role', function () {
    $response = $this->actingAs($this->regularUser)
        ->getJson('/api/homepage/tutoring/offer_requests');
    $response->assertStatus(403);
});

test('index returns user sent requests', function () {
    // Create request from student to mentor
    $request = TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Ich brauche Hilfe',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($this->student)
        ->getJson('/api/homepage/tutoring/offer_requests');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'message',
                ]
            ],
            'meta'
        ])
        ->assertJsonCount(1, 'data');
});

test('index does not return other users requests', function () {
    // Create request from other user
    TutoringOfferRequest::create([
        'from_user_id' => $this->otherUser->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Test',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($this->student)
        ->getJson('/api/homepage/tutoring/offer_requests');

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

test('index filters archived requests when show_archived is false', function () {
    // Create active request
    TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Active request',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
        'archived_at' => null,
    ]);

    // Create archived request
    TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Archived request',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
        'archived_at' => now(),
    ]);

    $response = $this->actingAs($this->student)
        ->getJson('/api/homepage/tutoring/offer_requests?show_archived=false');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('index shows archived requests when show_archived is true', function () {
    // Create active request
    TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Active request',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
        'archived_at' => null,
    ]);

    // Create archived request
    TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Archived request',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
        'archived_at' => now(),
    ]);

    $response = $this->actingAs($this->student)
        ->getJson('/api/homepage/tutoring/offer_requests?show_archived=true');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

// ============================
// receivedRequests() tests
// ============================

test('receivedRequests requires authentication', function () {
    $response = $this->getJson('/api/homepage/tutoring/received_offer_requests');
    $response->assertStatus(401);
});

test('receivedRequests requires tutoring_user role', function () {
    $response = $this->actingAs($this->regularUser)
        ->getJson('/api/homepage/tutoring/received_offer_requests');
    $response->assertStatus(403);
});

test('receivedRequests returns requests received by user', function () {
    // Create request from student to mentor
    $request = TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Ich brauche Hilfe',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($this->mentor)
        ->getJson('/api/homepage/tutoring/received_offer_requests');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'message',
                ]
            ],
            'meta'
        ])
        ->assertJsonCount(1, 'data');
});

test('receivedRequests marks unseen requests as seen', function () {
    // Create unseen request
    $request = TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Ich brauche Hilfe',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
        'seen_at' => null,
    ]);

    expect($request->seen_at)->toBeNull();

    $response = $this->actingAs($this->mentor)
        ->getJson('/api/homepage/tutoring/received_offer_requests');

    $response->assertStatus(200);

    $request->refresh();
    expect($request->seen_at)->not->toBeNull();
});

test('receivedRequests does not return requests sent by user', function () {
    // Create request sent by mentor
    TutoringOfferRequest::create([
        'from_user_id' => $this->mentor->id,
        'to_user_id' => $this->student->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Test',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($this->mentor)
        ->getJson('/api/homepage/tutoring/received_offer_requests');

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

// ============================
// destroy() tests
// ============================

test('destroy requires authentication', function () {
    $request = TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Test',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
    ]);

    $response = $this->deleteJson("/api/homepage/tutoring/offer_requests/{$request->id}");
    $response->assertStatus(401);
});

test('destroy requires tutoring_user role', function () {
    $request = TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Test',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($this->regularUser)
        ->deleteJson("/api/homepage/tutoring/offer_requests/{$request->id}");
    $response->assertStatus(403);
});

test('destroy deletes request created by user', function () {
    $request = TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Test',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($this->student)
        ->deleteJson("/api/homepage/tutoring/offer_requests/{$request->id}");

    $response->assertStatus(204);
    $this->assertDatabaseMissing('tutoring_offer_requests', ['id' => $request->id]);
});

test('destroy prevents user from deleting another users request', function () {
    $request = TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Test',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($this->otherUser)
        ->deleteJson("/api/homepage/tutoring/offer_requests/{$request->id}");

    $response->assertStatus(422);
    $this->assertDatabaseHas('tutoring_offer_requests', ['id' => $request->id]);
});

// ============================
// requestMailClicked() tests
// ============================

test('requestMailClicked requires authentication', function () {
    $request = TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Test',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
    ]);

    $response = $this->postJson('/api/homepage/tutoring/request_mail_clicked', [
        'request_id' => $request->id,
    ]);
    $response->assertStatus(401);
});

test('requestMailClicked requires tutoring_user role', function () {
    $request = TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Test',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($this->regularUser)
        ->postJson('/api/homepage/tutoring/request_mail_clicked', [
            'request_id' => $request->id,
        ]);
    $response->assertStatus(403);
});

test('requestMailClicked updates mail_at timestamp', function () {
    $request = TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Test',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
        'mail_at' => null,
    ]);

    expect($request->mail_at)->toBeNull();

    $response = $this->actingAs($this->mentor)
        ->postJson('/api/homepage/tutoring/request_mail_clicked', [
            'request_id' => $request->id,
        ]);

    $response->assertStatus(200);

    $request->refresh();
    expect($request->mail_at)->not->toBeNull();
});

test('requestMailClicked requires valid request_id', function () {
    $response = $this->actingAs($this->mentor)
        ->postJson('/api/homepage/tutoring/request_mail_clicked', [
            'request_id' => 99999,
        ]);

    $response->assertStatus(422);
});

test('requestMailClicked requires request_id to be integer', function () {
    $response = $this->actingAs($this->mentor)
        ->postJson('/api/homepage/tutoring/request_mail_clicked', [
            'request_id' => 'invalid',
        ]);

    $response->assertStatus(422);
});

// ============================
// toArchive() tests
// ============================

test('toArchive requires authentication', function () {
    $request = TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Test',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
    ]);

    $response = $this->postJson('/api/homepage/tutoring/to_archive', [
        'request_id' => $request->id,
    ]);
    $response->assertStatus(401);
});

test('toArchive requires tutoring_user role', function () {
    $request = TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Test',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($this->regularUser)
        ->postJson('/api/homepage/tutoring/to_archive', [
            'request_id' => $request->id,
        ]);
    $response->assertStatus(403);
});

test('toArchive archives a request', function () {
    $request = TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Test',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
        'archived_at' => null,
    ]);

    expect($request->archived_at)->toBeNull();

    $response = $this->actingAs($this->student)
        ->postJson('/api/homepage/tutoring/to_archive', [
            'request_id' => $request->id,
        ]);

    $response->assertStatus(200);

    $request->refresh();
    expect($request->archived_at)->not->toBeNull();
});

test('toArchive requires valid request_id', function () {
    $response = $this->actingAs($this->student)
        ->postJson('/api/homepage/tutoring/to_archive', [
            'request_id' => 99999,
        ]);

    $response->assertStatus(422);
});

test('toArchive requires request_id parameter', function () {
    $response = $this->actingAs($this->student)
        ->postJson('/api/homepage/tutoring/to_archive', []);

    $response->assertStatus(422);
});

// ============================
// toActive() tests
// ============================

test('toActive requires authentication', function () {
    $request = TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Test',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
        'archived_at' => now(),
    ]);

    $response = $this->postJson('/api/homepage/tutoring/to_active', [
        'request_id' => $request->id,
    ]);
    $response->assertStatus(401);
});

test('toActive requires tutoring_user role', function () {
    $request = TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Test',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
        'archived_at' => now(),
    ]);

    $response = $this->actingAs($this->regularUser)
        ->postJson('/api/homepage/tutoring/to_active', [
            'request_id' => $request->id,
        ]);
    $response->assertStatus(403);
});

test('toActive unarchives a request', function () {
    $request = TutoringOfferRequest::create([
        'from_user_id' => $this->student->id,
        'to_user_id' => $this->mentor->id,
        'offer_id' => $this->offer->id,
        'school_id' => $this->school1->id,
        'message' => 'Test',
        'sent_at' => now(),
        'token' => Str::uuid(),
        'token_expires_at' => now()->addDays(7),
        'archived_at' => now(),
    ]);

    expect($request->archived_at)->not->toBeNull();

    $response = $this->actingAs($this->student)
        ->postJson('/api/homepage/tutoring/to_active', [
            'request_id' => $request->id,
        ]);

    $response->assertStatus(200);

    $request->refresh();
    expect($request->archived_at)->toBeNull();
});

test('toActive requires valid request_id', function () {
    $response = $this->actingAs($this->student)
        ->postJson('/api/homepage/tutoring/to_active', [
            'request_id' => 99999,
        ]);

    $response->assertStatus(422);
});

test('toActive requires request_id parameter', function () {
    $response = $this->actingAs($this->student)
        ->postJson('/api/homepage/tutoring/to_active', []);

    $response->assertStatus(422);
});

// ============================
// offerRequest() tests - Web route for email links
// ============================

test('offerRequest requires valid email', function () {
    $token = Str::uuid();

    $response = $this->get('/homepage/tutoring/offer_request?' . http_build_query([
        'email' => 'invalid-email',
        'id' => 1,
        'token' => $token,
    ]));

    $response->assertStatus(302);
});

test('offerRequest requires valid token format', function () {
    $response = $this->get('/homepage/tutoring/offer_request?' . http_build_query([
        'email' => 'test@example.com',
        'id' => 1,
        'token' => 'invalid-token',
    ]));

    $response->assertStatus(302);
});

test('offerRequest requires id parameter', function () {
    $token = Str::uuid();

    $response = $this->get('/homepage/tutoring/offer_request?' . http_build_query([
        'email' => 'test@example.com',
        'token' => $token,
    ]));

    $response->assertStatus(302);
});

test('offerRequest logs in the resolved user and redirects to tutoring overview', function () {
    $targetUser = User::factory()->create([
        'email' => 'target@example.com',
        'school_id' => $this->school1->id,
        'schoolyear_id' => $this->schoolyear1->id,
    ]);

    $service = Mockery::mock(TutoringOfferService::class);
    $service->shouldReceive('getUserFromOfferRequest')
        ->once()
        ->with($targetUser->email, 123, Mockery::type('string'))
        ->andReturn($targetUser);

    app()->instance(TutoringOfferService::class, $service);

    $this->actingAs($this->student)
        ->get('/homepage/tutoring/offer_request?' . http_build_query([
            'email' => $targetUser->email,
            'id' => 123,
            'token' => Str::uuid()->toString(),
        ]))
        ->assertRedirect('/homepage/tutoring_overview?school=ABG-SB&received_requests=true');

    expect(Auth::id())->toBe($targetUser->id);
});

test('offerRequest redirects to error page when user lookup fails', function () {
    $service = Mockery::mock(TutoringOfferService::class);
    $service->shouldReceive('getUserFromOfferRequest')
        ->once()
        ->andReturn(null);

    app()->instance(TutoringOfferService::class, $service);

    $response = $this->get('/homepage/tutoring/offer_request?' . http_build_query([
        'email' => 'test@example.com',
        'id' => 1,
        'token' => Str::uuid()->toString(),
    ]));

    $response->assertStatus(302);

    $location = $response->headers->get('Location');
    $parsed = parse_url($location);
    parse_str($parsed['query'] ?? '', $query);

    expect($parsed['path'] ?? '')->toBe('/homepage/tutoring_response')
        ->and($query['title'] ?? null)->toBe('Fehler')
        ->and($query['subtitle'] ?? null)->toBe('Fehler beim Anmelden')
        ->and($query['status'] ?? null)->toBe('422');
});
