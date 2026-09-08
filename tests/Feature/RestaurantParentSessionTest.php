<?php

use App\Http\Middleware\RestrictRestaurantParentSession;
use App\Models\Import116;
use App\Models\RestaurantSepaMandate;
use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\User;
use App\Notifications\StandardEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    config(['sanctum.stateful' => ['localhost']]);
    $this->withHeaders(['Origin' => 'http://localhost']);

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    SchoolTool::factory()->create([
        'school_id' => $this->school->id,
        'active_schoolyear_id' => $this->schoolyear->id,
        'restaurant_visible_user' => true,
        'restaurant_sepa_online_enabled' => false,
    ]);
    grantSchoolToolLicenceForTests($this->school, 'Restaurant');

    foreach (['lunch_user', 'student'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->student = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'adult.student@example.test',
        'password' => Hash::make('student-password'),
        'token_2fa' => null,
        'token_2fa_expires_at' => null,
        'is_active' => true,
    ]);
    $this->student->assignRole(['lunch_user', 'student']);
    $this->importStudent = Import116::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'user_id' => $this->student->id,
        'email' => $this->student->email,
        'mother_email' => 'parent@example.test',
        'father_email' => 'other.parent@example.test',
        'birth_date' => today()->subYears(20)->toDateString(),
        'exists_date' => now(),
    ]);

    $this->parentLoginData = [
        'school_id' => $this->school->id,
        'email' => 'parent@example.test',
        'user_id' => $this->student->id,
    ];
});

function restaurantParentLoginCode(): string
{
    $token = null;
    Notification::assertSentOnDemand(StandardEmail::class, function (StandardEmail $notification, array $channels, object $notifiable) use (&$token): bool {
        if (($notifiable->routes['mail'] ?? null) !== 'parent@example.test') {
            return false;
        }

        $token = (string) ($notification->data['token_2fa'] ?? '');

        return $token !== '';
    });

    return (string) $token;
}

it('keeps parent codes separate from the pupil code and confines adult child login to restaurant', function () {
    $this->postJson('/api/homepage/restaurant/send_login_code', ['data' => $this->parentLoginData])
        ->assertOk();
    $token = restaurantParentLoginCode();
    expect($this->student->fresh()->token_2fa)->toBeNull();

    $this->postJson('/api/homepage/restaurant/login_with_code', ['data' => [
        ...$this->parentLoginData,
        'email' => $this->student->email,
        'token_2fa' => $token,
    ]])->assertOk()->assertJsonPath('status', 'RETRY_LOGIN_WITH_CODE');
    $this->assertGuest();

    $response = $this->postJson('/api/homepage/restaurant/login_with_code', ['data' => [
        ...$this->parentLoginData,
        'token_2fa' => $token,
    ]])->assertOk()->assertJsonPath('status', 'LOGGED_IN');

    $this->assertAuthenticatedAs($this->student);
    $response->assertSessionHas(RestrictRestaurantParentSession::SESSION_KEY, $this->student->id)
        ->assertCookieExpired(Auth::guard('web')->getRecallerName());

    $this->getJson('/api/homepage/restaurant/bookings')->assertOk();
    $this->getJson('/api/homepage/restaurant/child-options')->assertOk();
    foreach ([
        '/api/homepage/student/user',
        '/api/homepage/student/courses',
        '/api/homepage/students-timetables/overview',
        '/api/admin/teaching/load_settings',
    ] as $path) {
        $this->getJson($path)->assertForbidden()
            ->assertJsonPath('message', 'Dieser Elternzugang ist auf das Restaurant beschränkt.');
    }
    $this->withHeaders(['Origin' => '']);
    $this->getJson('/api/homepage/student/user')->assertForbidden()
        ->assertJsonPath('message', 'Dieser Elternzugang ist auf das Restaurant beschränkt.');
    $this->withHeaders(['Origin' => 'http://localhost']);
    $this->get('/student/overview')->assertRedirect('/homepage/restaurant');

    $this->postJson('/api/homepage/restaurant/login_with_code', ['data' => [
        ...$this->parentLoginData,
        'token_2fa' => $token,
    ]])->assertOk()->assertJsonPath('status', 'RETRY_LOGIN_WITH_CODE');
});

it('binds parent login codes to the challenged email and session', function () {
    $this->postJson('/api/homepage/restaurant/send_login_code', ['data' => $this->parentLoginData])->assertOk();
    $token = restaurantParentLoginCode();

    $this->postJson('/api/homepage/restaurant/login_with_code', ['data' => [
        ...$this->parentLoginData,
        'email' => 'other.parent@example.test',
        'token_2fa' => $token,
    ]])->assertOk()->assertJsonPath('status', 'RETRY_LOGIN_WITH_CODE');
    $this->assertGuest();

    $this->postJson('/api/homepage/restaurant/login_with_code', ['data' => [
        ...$this->parentLoginData,
        'token_2fa' => $token,
    ]])->assertOk()->assertJsonPath('status', 'RETRY_LOGIN_WITH_CODE');
});

it('expires restaurant parent codes and limits incorrect attempts', function (bool $expired) {
    $this->postJson('/api/homepage/restaurant/send_login_code', ['data' => $this->parentLoginData])->assertOk();
    $token = restaurantParentLoginCode();

    if ($expired) {
        $this->travel((int) config('schooltool.token_expire_time') + 1)->minutes();
    } else {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/homepage/restaurant/login_with_code', ['data' => [
                ...$this->parentLoginData,
                'token_2fa' => '000000',
            ]])->assertOk()->assertJsonPath('status', 'RETRY_LOGIN_WITH_CODE');
        }
    }

    $this->postJson('/api/homepage/restaurant/login_with_code', ['data' => [
        ...$this->parentLoginData,
        'token_2fa' => $token,
    ]])->assertOk()->assertJsonPath('status', 'RETRY_LOGIN_WITH_CODE');
    $this->assertGuest();
})->with([true, false]);

it('restricts parent password sessions without changing the child password or direct account access', function () {
    $this->postJson('/api/homepage/restaurant/login_with_password', ['data' => [
        ...$this->parentLoginData,
        'password' => 'student-password',
    ]])->assertOk()->assertSessionHas(RestrictRestaurantParentSession::SESSION_KEY, $this->student->id);

    $this->postJson('/api/homepage/restaurant/change_password', ['data' => [
        'school_id' => $this->school->id,
        'new_password' => 'changed-password',
        'confirm_password' => 'changed-password',
    ]])->assertForbidden();
    expect(Hash::check('student-password', $this->student->fresh()->password))->toBeTrue();

    $this->postJson('/api/homepage/restaurant/login_with_password', ['data' => [
        ...$this->parentLoginData,
        'email' => $this->student->email,
        'password' => 'student-password',
    ]])->assertOk()->assertSessionMissing(RestrictRestaurantParentSession::SESSION_KEY);

    $this->get('/student/overview')->assertOk();
});

it('preserves parent confinement when the restaurant SEPA flow logs the child in again', function () {
    $this->postJson('/api/homepage/restaurant/login_with_password', ['data' => [
        ...$this->parentLoginData,
        'password' => 'student-password',
    ]])->assertOk();

    $mandate = RestaurantSepaMandate::query()->create([
        'user_id' => $this->student->id,
        'school_id' => $this->school->id,
        'flow_uuid' => (string) Str::uuid(),
        'status' => 'confirmed',
        'confirmed_at' => now(),
    ]);

    $this->postJson('/api/homepage/restaurant/sepa/complete', ['data' => ['flow_uuid' => $mandate->flow_uuid]])
        ->assertOk()
        ->assertJsonPath('status', 'COMPLETED')
        ->assertSessionHas(RestrictRestaurantParentSession::SESSION_KEY, $this->student->id)
        ->assertCookieExpired(Auth::guard('web')->getRecallerName());

    $this->getJson('/api/homepage/student/user')->assertForbidden();
    $this->getJson('/api/homepage/restaurant/bookings')->assertOk();
    $this->postJson('/api/homepage/logout')->assertOk();
    $this->assertGuest('web');
    expect(session()->has(RestrictRestaurantParentSession::SESSION_KEY))->toBeFalse();
});

it('keeps a parents own restaurant account independent from child session restrictions', function () {
    $parent = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'email' => 'parent@example.test',
        'password' => Hash::make('parent-password'),
    ]);
    $parent->assignRole('lunch_user');

    $this->postJson('/api/homepage/restaurant/login_with_password', ['data' => [
        'school_id' => $this->school->id,
        'email' => $parent->email,
        'user_id' => $parent->id,
        'password' => 'parent-password',
    ]])->assertOk()->assertSessionMissing(RestrictRestaurantParentSession::SESSION_KEY);

    $this->assertAuthenticatedAs($parent, 'web');
    $this->getJson('/api/homepage/restaurant/child-options')->assertOk()
        ->assertJsonPath('options.0.id', $this->importStudent->id);
});
