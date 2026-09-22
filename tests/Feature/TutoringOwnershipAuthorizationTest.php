<?php

use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('retired tutoring mutations preserve shared accounts roles and bookings across schools', function (string $method, string $uri): void {
    Notification::fake();
    Role::firstOrCreate(['name' => 'register_user', 'guard_name' => 'web']);
    $school = School::factory()->create();
    $owner = User::factory()->create(['school_id' => $school->id]);
    $peer = User::factory()->create(['school_id' => $school->id]);
    $otherSchool = School::factory()->create();
    $other = User::factory()->create(['school_id' => $otherSchool->id]);
    $owner->assignRole('register_user');
    $register = Register::factory()->create(['school_id' => $school->id]);
    $date = RegisterDate::factory()->create(['register_id' => $register->id]);
    $booking = RegisterDateBooking::factory()->create([
        'register_date_id' => $date->id,
        'user_id' => $owner->id,
    ]);
    $usersBefore = User::query()->orderBy('id')->get()->toArray();
    $rolesBefore = $owner->roles->modelKeys();

    $this->actingAs($owner)->json($method, str_replace('{user}', (string) $peer->id, $uri), [
        'id' => $peer->id,
        'ids' => [$owner->id, $peer->id, $other->id],
        'data' => ['ids' => [$owner->id, $peer->id, $other->id]],
        'email' => 'changed@example.test',
        'first_name' => 'Changed',
        'password' => 'replacement-password',
    ])->assertNotFound();

    expect(User::query()->orderBy('id')->get()->toArray())->toBe($usersBefore)
        ->and($owner->fresh()->roles->modelKeys())->toBe($rolesBefore)
        ->and(RegisterDateBooking::find($booking->id))->not->toBeNull();
    Notification::assertNothingSent();
})->with([
    ['PUT', '/api/homepage/tutoring/users/{user}'],
    ['POST', '/api/homepage/tutoring/update_password'],
    ['POST', '/api/homepage/tutoring/create_user'],
    ['POST', '/api/homepage/tutoring/send_request'],
    ['POST', '/api/homepage/tutoring/request_mail_clicked'],
    ['POST', '/api/admin/tutoring/delete_users'],
    ['POST', '/api/admin/tutoring/confirm_users'],
    ['POST', '/api/admin/tutoring/clean_users'],
]);
