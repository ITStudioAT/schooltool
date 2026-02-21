<?php

/**
 * RegisterPrintController Tests
 *
 * These tests cover the complete functionality of the RegisterPrintController including:
 * - Print Excel endpoint (dispatches PrintRegisterExcelJob)
 * - Print Supervisor endpoint (dispatches PrintRegisterSupervisorJob)
 * - Print Date endpoint (dispatches PrintRegisterDateJob)
 *
 * All endpoints require appropriate role permissions (admin or register_admin)
 * and dispatch jobs for asynchronous PDF generation
 */

use App\Jobs\PrintRegisterDateJob;
use App\Jobs\PrintRegisterExcelJob;
use App\Jobs\PrintRegisterSupervisorJob;
use App\Models\Licence;
use App\Models\Register;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Fake the queue
    Queue::fake();

    // Create test school and schoolyear
    $this->school = School::factory()->create([
        'long_name' => 'Test School',
        'short_name' => 'TS',
        'logo' => 'test-logo.png',
    ]);

    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
        'name' => '2023/2024',
    ]);

    $registerLicence = Licence::firstOrCreate(
        ['name' => 'Anmeldetool'],
        ['long_name' => 'Anmeldetool', 'is_selectable' => true]
    );
    $this->school->licences()->attach($registerLicence->id, [
        'valid_until' => now()->addYear()->toDateString(),
    ]);

    // Create roles
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'register_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'register_user', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    // Create register
    $this->register = Register::factory()->create([
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Test Register',
        'is_active' => true,
    ]);

    // Create admin user
    $this->adminUser = User::factory()->create([
        'school_id' => $this->school->id,
        'email' => 'admin@test.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);
    $this->adminUser->assignRole('admin');

    // Create register_admin user
    $this->registerAdminUser = User::factory()->create([
        'school_id' => $this->school->id,
        'email' => 'registeradmin@test.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);
    $this->registerAdminUser->assignRole('register_admin');

    // Create regular user without permissions
    $this->regularUser = User::factory()->create([
        'school_id' => $this->school->id,
        'email' => 'user@test.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);
    $this->regularUser->assignRole('user');
});

// ============================================================================
// Print Excel Tests
// ============================================================================

test('admin can print Excel and job is dispatched', function () {
    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/admin/registers/print_excel', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(204);

    Queue::assertPushed(PrintRegisterExcelJob::class, function ($job) {
        return $job->user->id === $this->adminUser->id &&
               $job->data['register_id'] === $this->register->id;
    });
});

test('register_admin can print Excel and job is dispatched', function () {
    $this->actingAs($this->registerAdminUser);

    $response = $this->postJson('/api/admin/registers/print_excel', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(204);

    Queue::assertPushed(PrintRegisterExcelJob::class, function ($job) {
        return $job->user->id === $this->registerAdminUser->id &&
               $job->data['register_id'] === $this->register->id;
    });
});

test('regular user cannot print Excel', function () {
    $this->actingAs($this->regularUser);

    $response = $this->postJson('/api/admin/registers/print_excel', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(403);
    $response->assertJson(['message' => 'Unzulässig']);

    Queue::assertNotPushed(PrintRegisterExcelJob::class);
});

test('unauthenticated user cannot print Excel', function () {
    $response = $this->postJson('/api/admin/registers/print_excel', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(401);

    Queue::assertNotPushed(PrintRegisterExcelJob::class);
});

test('print Excel requires register_id', function () {
    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/admin/registers/print_excel', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['register_id']);

    Queue::assertNotPushed(PrintRegisterExcelJob::class);
});

test('print Excel requires valid register_id', function () {
    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/admin/registers/print_excel', [
        'register_id' => 99999,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['register_id']);

    Queue::assertNotPushed(PrintRegisterExcelJob::class);
});

test('print Excel requires integer register_id', function () {
    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/admin/registers/print_excel', [
        'register_id' => 'invalid',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['register_id']);

    Queue::assertNotPushed(PrintRegisterExcelJob::class);
});

// ============================================================================
// Print Supervisor Tests
// ============================================================================

test('admin can print Supervisor and job is dispatched', function () {
    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/admin/registers/print_supervisor', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(204);

    Queue::assertPushed(PrintRegisterSupervisorJob::class, function ($job) {
        return $job->user->id === $this->adminUser->id &&
               $job->data['register_id'] === $this->register->id;
    });
});

test('register_admin can print Supervisor and job is dispatched', function () {
    $this->actingAs($this->registerAdminUser);

    $response = $this->postJson('/api/admin/registers/print_supervisor', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(204);

    Queue::assertPushed(PrintRegisterSupervisorJob::class, function ($job) {
        return $job->user->id === $this->registerAdminUser->id &&
               $job->data['register_id'] === $this->register->id;
    });
});

test('regular user cannot print Supervisor', function () {
    $this->actingAs($this->regularUser);

    $response = $this->postJson('/api/admin/registers/print_supervisor', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(403);
    $response->assertJson(['message' => 'Unzulässig']);

    Queue::assertNotPushed(PrintRegisterSupervisorJob::class);
});

test('unauthenticated user cannot print Supervisor', function () {
    $response = $this->postJson('/api/admin/registers/print_supervisor', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(401);

    Queue::assertNotPushed(PrintRegisterSupervisorJob::class);
});

test('print Supervisor requires register_id', function () {
    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/admin/registers/print_supervisor', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['register_id']);

    Queue::assertNotPushed(PrintRegisterSupervisorJob::class);
});

test('print Supervisor requires valid register_id', function () {
    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/admin/registers/print_supervisor', [
        'register_id' => 99999,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['register_id']);

    Queue::assertNotPushed(PrintRegisterSupervisorJob::class);
});

test('print Supervisor requires integer register_id', function () {
    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/admin/registers/print_supervisor', [
        'register_id' => 'invalid',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['register_id']);

    Queue::assertNotPushed(PrintRegisterSupervisorJob::class);
});

// ============================================================================
// Print Date Tests
// ============================================================================

test('admin can print Date and job is dispatched', function () {
    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/admin/registers/print_date', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(204);

    Queue::assertPushed(PrintRegisterDateJob::class, function ($job) {
        return $job->user->id === $this->adminUser->id &&
               $job->data['register_id'] === $this->register->id;
    });
});

test('register_admin can print Date and job is dispatched', function () {
    $this->actingAs($this->registerAdminUser);

    $response = $this->postJson('/api/admin/registers/print_date', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(204);

    Queue::assertPushed(PrintRegisterDateJob::class, function ($job) {
        return $job->user->id === $this->registerAdminUser->id &&
               $job->data['register_id'] === $this->register->id;
    });
});

test('regular user cannot print Date', function () {
    $this->actingAs($this->regularUser);

    $response = $this->postJson('/api/admin/registers/print_date', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(403);
    $response->assertJson(['message' => 'Unzulässig']);

    Queue::assertNotPushed(PrintRegisterDateJob::class);
});

test('unauthenticated user cannot print Date', function () {
    $response = $this->postJson('/api/admin/registers/print_date', [
        'register_id' => $this->register->id,
    ]);

    $response->assertStatus(401);

    Queue::assertNotPushed(PrintRegisterDateJob::class);
});

test('print Date requires register_id', function () {
    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/admin/registers/print_date', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['register_id']);

    Queue::assertNotPushed(PrintRegisterDateJob::class);
});

test('print Date requires valid register_id', function () {
    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/admin/registers/print_date', [
        'register_id' => 99999,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['register_id']);

    Queue::assertNotPushed(PrintRegisterDateJob::class);
});

test('print Date requires integer register_id', function () {
    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/admin/registers/print_date', [
        'register_id' => 'invalid',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['register_id']);

    Queue::assertNotPushed(PrintRegisterDateJob::class);
});

// ============================================================================
// General Edge Cases
// ============================================================================

test('print endpoints return no content on success', function () {
    $this->actingAs($this->adminUser);

    $excelResponse = $this->postJson('/api/admin/registers/print_excel', [
        'register_id' => $this->register->id,
    ]);

    $supervisorResponse = $this->postJson('/api/admin/registers/print_supervisor', [
        'register_id' => $this->register->id,
    ]);

    $dateResponse = $this->postJson('/api/admin/registers/print_date', [
        'register_id' => $this->register->id,
    ]);

    $excelResponse->assertStatus(204);
    $excelResponse->assertNoContent();

    $supervisorResponse->assertStatus(204);
    $supervisorResponse->assertNoContent();

    $dateResponse->assertStatus(204);
    $dateResponse->assertNoContent();
});

test('all print endpoints dispatch correct jobs with correct parameters', function () {
    $this->actingAs($this->adminUser);

    // Excel
    $this->postJson('/api/admin/registers/print_excel', [
        'register_id' => $this->register->id,
    ]);

    // Supervisor
    $this->postJson('/api/admin/registers/print_supervisor', [
        'register_id' => $this->register->id,
    ]);

    // Date
    $this->postJson('/api/admin/registers/print_date', [
        'register_id' => $this->register->id,
    ]);

    Queue::assertPushed(PrintRegisterExcelJob::class, 1);
    Queue::assertPushed(PrintRegisterSupervisorJob::class, 1);
    Queue::assertPushed(PrintRegisterDateJob::class, 1);
});

test('multiple users can print simultaneously', function () {
    $anotherAdmin = User::factory()->create([
        'school_id' => $this->school->id,
        'email' => 'admin2@test.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);
    $anotherAdmin->assignRole('admin');

    // First user prints
    $this->actingAs($this->adminUser);
    $response1 = $this->postJson('/api/admin/registers/print_excel', [
        'register_id' => $this->register->id,
    ]);

    // Second user prints
    $this->actingAs($anotherAdmin);
    $response2 = $this->postJson('/api/admin/registers/print_excel', [
        'register_id' => $this->register->id,
    ]);

    $response1->assertStatus(204);
    $response2->assertStatus(204);

    Queue::assertPushed(PrintRegisterExcelJob::class, 2);
});

test('print endpoints work with inactive register', function () {
    $inactiveRegister = Register::factory()->create([
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Inactive Register',
        'is_active' => false,
    ]);

    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/admin/registers/print_excel', [
        'register_id' => $inactiveRegister->id,
    ]);

    $response->assertStatus(204);

    Queue::assertPushed(PrintRegisterExcelJob::class, function ($job) use ($inactiveRegister) {
        return $job->data['register_id'] === $inactiveRegister->id;
    });
});

