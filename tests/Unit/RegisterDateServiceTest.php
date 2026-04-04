<?php

use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\RegisterDateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new RegisterDateService;

    // Create test data
    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);
    $this->register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Test Register',
    ]);
});

describe('loadDays', function () {
    it('returns empty array when no dates exist', function () {
        $result = $this->service->loadDays($this->register->id);

        expect($result)->toBeArray()
            ->and($result)->toBeEmpty();
    });

    it('returns dates for register', function () {
        RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-12-15',
            'from' => '10:00',
            'to' => '12:00',
        ]);

        $result = $this->service->loadDays($this->register->id);

        expect($result)->toBeArray()
            ->and(count($result))->toBe(1)
            ->and($result[0]->date)->toBe('2024-12-15');
    });

    it('groups multiple times on same date', function () {
        RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-12-15',
            'from' => '10:00',
            'to' => '11:00',
        ]);

        RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-12-15',
            'from' => '14:00',
            'to' => '15:00',
        ]);

        $result = $this->service->loadDays($this->register->id);

        expect($result)->toBeArray()
            ->and(count($result))->toBe(1);
    });

    it('includes bookings count for each date', function () {
        $registerDate = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-12-15',
        ]);

        $user = User::factory()->create(['school_id' => $this->school->id]);

        RegisterDateBooking::factory()->count(3)->create([
            'register_date_id' => $registerDate->id,
            'register_id' => $this->register->id,
            'user_id' => $user->id,
            'school_id' => $this->school->id,
        ]);

        $result = $this->service->loadDays($this->register->id);

        expect($result[0]->bookings_count)->toBe(3);
    });

    it('sums bookings count for same date', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);

        $registerDate1 = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-12-15',
            'from' => '10:00',
            'to' => '11:00',
        ]);

        $registerDate2 = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-12-15',
            'from' => '14:00',
            'to' => '15:00',
        ]);

        RegisterDateBooking::factory()->count(2)->create([
            'register_date_id' => $registerDate1->id,
            'register_id' => $this->register->id,
            'user_id' => $user->id,
            'school_id' => $this->school->id,
        ]);

        RegisterDateBooking::factory()->count(3)->create([
            'register_date_id' => $registerDate2->id,
            'register_id' => $this->register->id,
            'user_id' => $user->id,
            'school_id' => $this->school->id,
        ]);

        $result = $this->service->loadDays($this->register->id);

        expect($result[0]->bookings_count)->toBe(5);
    });

    it('orders dates chronologically', function () {
        RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-12-20',
        ]);

        RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-12-15',
        ]);

        RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-12-18',
        ]);

        $result = $this->service->loadDays($this->register->id);

        expect($result[0]->date)->toBe('2024-12-15')
            ->and($result[1]->date)->toBe('2024-12-18')
            ->and($result[2]->date)->toBe('2024-12-20');
    });

    it('adds day name to each date', function () {
        RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-12-16', // Monday
        ]);

        $result = $this->service->loadDays($this->register->id);

        expect($result[0]->day)->toBeString();
    });

    it('filters out dates with null date value', function () {
        RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-12-15',
        ]);

        RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => null,
        ]);

        $result = $this->service->loadDays($this->register->id);

        expect(count($result))->toBe(1);
    });

    it('only loads dates for specified register', function () {
        $otherRegister = Register::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-12-15',
        ]);

        RegisterDate::factory()->create([
            'register_id' => $otherRegister->id,
            'date' => '2024-12-16',
        ]);

        $result = $this->service->loadDays($this->register->id);

        expect(count($result))->toBe(1)
            ->and($result[0]->date)->toBe('2024-12-15');
    });
});

describe('createDates', function () {
    it('creates dates for single day', function () {
        $data = [
            'days' => ['monday'],
            'supervisor' => 'Test Supervisor',
            'date_from' => '2024-12-16', // Monday
            'date_until' => '2024-12-16',
            'time_from' => '10:00',
            'time_until' => '12:00',
            'min_per_date' => 60,
            'pause' => 0,
            'max_registrations' => 5,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($result)->toBeTrue()
            ->and(RegisterDate::count())->toBeGreaterThan(0);
    });

    it('creates dates for multiple days', function () {
        $data = [
            'days' => ['monday', 'wednesday', 'friday'],
            'supervisor' => 'Test Supervisor',
            'date_from' => '2024-12-16', // Monday
            'date_until' => '2024-12-20', // Friday
            'time_from' => '10:00',
            'time_until' => '11:00',
            'min_per_date' => 60,
            'max_registrations' => 5,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($result)->toBeTrue()
            ->and(RegisterDate::count())->toBe(3); // Mon, Wed, Fri
    });

    it('creates multiple time slots per day', function () {
        $data = [
            'days' => ['monday'],
            'supervisor' => 'Test Supervisor',
            'date_from' => '2024-12-16',
            'date_until' => '2024-12-16',
            'time_from' => '10:00',
            'time_until' => '14:00',
            'min_per_date' => 60,
            'pause' => 0,
            'max_registrations' => 5,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($result)->toBeTrue()
            ->and(RegisterDate::count())->toBe(4); // 10-11, 11-12, 12-13, 13-14
    });

    it('respects pause between slots', function () {
        $data = [
            'days' => ['monday'],
            'supervisor' => 'Test Supervisor',
            'date_from' => '2024-12-16',
            'date_until' => '2024-12-16',
            'time_from' => '10:00',
            'time_until' => '14:00',
            'min_per_date' => 60,
            'pause' => 30,
            'max_registrations' => 5,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($result)->toBeTrue();

        $dates = RegisterDate::orderBy('from')->get();
        // With 60min slots + 30min pause = 90min intervals
        // 10:00-11:00, 11:30-12:30, 13:00-14:00 = 3 slots fit
        expect($dates->count())->toBe(3);
    });

    it('creates dates for multiple supervisors', function () {
        $data = [
            'days' => ['monday'],
            'supervisors' => ['Supervisor A', 'Supervisor B'],
            'date_from' => '2024-12-16',
            'date_until' => '2024-12-16',
            'time_from' => '10:00',
            'time_until' => '11:00',
            'min_per_date' => 60,
            'max_registrations' => 5,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($result)->toBeTrue()
            ->and(RegisterDate::count())->toBe(2)
            ->and(RegisterDate::where('supervisor', 'Supervisor A')->exists())->toBeTrue()
            ->and(RegisterDate::where('supervisor', 'Supervisor B')->exists())->toBeTrue();
    });

    it('accepts checkbox format for days', function () {
        $data = [
            'monday' => '1',
            'wednesday' => '1',
            'supervisor' => 'Test Supervisor',
            'date_from' => '2024-12-16',
            'date_until' => '2024-12-18',
            'time_from' => '10:00',
            'time_until' => '11:00',
            'min_per_date' => 60,
            'max_registrations' => 5,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($result)->toBeTrue()
            ->and(RegisterDate::count())->toBe(2);
    });

    it('accepts legacy supervisor_1 to supervisor_5 format', function () {
        $data = [
            'days' => ['monday'],
            'supervisor_1' => 'Supervisor 1',
            'supervisor_2' => 'Supervisor 2',
            'date_from' => '2024-12-16',
            'date_until' => '2024-12-16',
            'time_from' => '10:00',
            'time_until' => '11:00',
            'min_per_date' => 60,
            'max_registrations' => 5,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($result)->toBeTrue()
            ->and(RegisterDate::count())->toBe(2);
    });

    it('returns false when min_per_date is zero', function () {
        $data = [
            'days' => ['monday'],
            'supervisor' => 'Test Supervisor',
            'date_from' => '2024-12-16',
            'time_from' => '10:00',
            'time_until' => '12:00',
            'min_per_date' => 0,
            'max_registrations' => 5,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($result)->toBeFalse();
    });

    it('returns false when no days specified', function () {
        $data = [
            'days' => [],
            'supervisor' => 'Test Supervisor',
            'date_from' => '2024-12-16',
            'time_from' => '10:00',
            'time_until' => '12:00',
            'min_per_date' => 60,
            'max_registrations' => 5,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($result)->toBeFalse();
    });

    it('returns false when no supervisors specified', function () {
        $data = [
            'days' => ['monday'],
            'date_from' => '2024-12-16',
            'time_from' => '10:00',
            'time_until' => '12:00',
            'min_per_date' => 60,
            'max_registrations' => 5,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($result)->toBeFalse();
    });

    it('auto-swaps reversed date range', function () {
        $data = [
            'days' => ['monday', 'tuesday', 'wednesday'],
            'supervisor' => 'Test Supervisor',
            'date_from' => '2024-12-18', // Wednesday
            'date_until' => '2024-12-16', // Monday (reversed)
            'time_from' => '10:00',
            'time_until' => '11:00',
            'min_per_date' => 60,
            'max_registrations' => 5,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        // After swap, Mon 16th, Tue 17th, Wed 18th = 2 dates (16th is Monday, 17th is Tuesday)
        expect($result)->toBeTrue()
            ->and(RegisterDate::count())->toBeGreaterThan(0);
    });

    it('uses upsert to avoid duplicates', function () {
        $data = [
            'days' => ['monday'],
            'supervisor' => 'Test Supervisor',
            'date_from' => '2024-12-16',
            'date_until' => '2024-12-16',
            'time_from' => '10:00',
            'time_until' => '11:00',
            'min_per_date' => 60,
            'max_registrations' => 5,
        ];

        $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        $firstCount = RegisterDate::count();

        $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect(RegisterDate::count())->toBe($firstCount);
    });

    it('sets max_registrations correctly', function () {
        $data = [
            'days' => ['monday'],
            'supervisor' => 'Test Supervisor',
            'date_from' => '2024-12-16',
            'date_until' => '2024-12-16',
            'time_from' => '10:00',
            'time_until' => '11:00',
            'min_per_date' => 60,
            'max_registrations' => 10,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($result)->toBeTrue();

        $date = RegisterDate::first();
        expect($date->max_registrations)->toBe(10);
    });

    it('defaults date_until to date_from', function () {
        $data = [
            'days' => ['monday'],
            'supervisor' => 'Test Supervisor',
            'date_from' => '2024-12-16',
            // date_until omitted
            'time_from' => '10:00',
            'time_until' => '11:00',
            'min_per_date' => 60,
            'max_registrations' => 5,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($result)->toBeTrue()
            ->and(RegisterDate::count())->toBe(1);
    });

    it('handles overnight time windows', function () {
        $data = [
            'days' => ['friday'],
            'supervisor' => 'Test Supervisor',
            'date_from' => '2024-12-20',
            'date_until' => '2024-12-20',
            'time_from' => '22:00',
            'time_until' => '02:00', // Next day
            'min_per_date' => 60,
            'max_registrations' => 5,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($result)->toBeTrue()
            ->and(RegisterDate::count())->toBeGreaterThan(0);
    });

    it('filters days case-insensitively', function () {
        $data = [
            'days' => ['MONDAY', 'Monday', 'monday'],
            'supervisor' => 'Test Supervisor',
            'date_from' => '2024-12-16',
            'date_until' => '2024-12-16',
            'time_from' => '10:00',
            'time_until' => '11:00',
            'min_per_date' => 60,
            'max_registrations' => 5,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($result)->toBeTrue()
            ->and(RegisterDate::count())->toBe(1); // Duplicates removed
    });

    it('removes empty supervisor values', function () {
        $data = [
            'days' => ['monday'],
            'supervisors' => ['Supervisor A', '', null, 'Supervisor B'],
            'date_from' => '2024-12-16',
            'date_until' => '2024-12-16',
            'time_from' => '10:00',
            'time_until' => '11:00',
            'min_per_date' => 60,
            'max_registrations' => 5,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($result)->toBeTrue()
            ->and(RegisterDate::count())->toBe(2); // Only 2 valid supervisors
    });
});

describe('deleteRegisterDates', function () {
    it('deletes dates without bookings', function () {
        $date1 = RegisterDate::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => $this->register->id,
        ]);

        $date2 = RegisterDate::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => $this->register->id,
        ]);

        expect(RegisterDate::count())->toBe(2);

        $result = $this->service->deleteRegisterDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            [$date1->id, $date2->id]
        );

        expect($result)->toBeTrue()
            ->and(RegisterDate::count())->toBe(0);
    });

    it('does not delete dates with bookings', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);

        $dateWithBooking = RegisterDate::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => $this->register->id,
        ]);

        RegisterDateBooking::factory()->create([
            'register_date_id' => $dateWithBooking->id,
            'register_id' => $this->register->id,
            'user_id' => $user->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        expect(RegisterDate::count())->toBeGreaterThan(0);

        $result = $this->service->deleteRegisterDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            [$dateWithBooking->id]
        );

        expect($result)->toBeTrue()
            ->and(RegisterDate::find($dateWithBooking->id))->not->toBeNull();
    });

    it('selectively deletes dates based on bookings', function () {
        $user = User::factory()->create(['school_id' => $this->school->id]);

        $dateWithBooking = RegisterDate::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => $this->register->id,
        ]);

        $dateWithoutBooking = RegisterDate::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => $this->register->id,
        ]);

        RegisterDateBooking::factory()->create([
            'register_date_id' => $dateWithBooking->id,
            'register_id' => $this->register->id,
            'user_id' => $user->id,
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $initialCount = RegisterDate::count();

        $result = $this->service->deleteRegisterDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            [$dateWithBooking->id, $dateWithoutBooking->id]
        );

        expect($result)->toBeTrue()
            ->and(RegisterDate::count())->toBeLessThan($initialCount)
            ->and(RegisterDate::find($dateWithBooking->id))->not->toBeNull()
            ->and(RegisterDate::find($dateWithoutBooking->id))->toBeNull();
    });

    it('only deletes dates for specified school', function () {
        $otherSchool = School::factory()->create();

        $date1 = RegisterDate::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => $this->register->id,
        ]);

        $date2 = RegisterDate::factory()->create([
            'school_id' => $otherSchool->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => $this->register->id,
        ]);

        expect(RegisterDate::count())->toBe(2);

        $result = $this->service->deleteRegisterDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            [$date1->id, $date2->id]
        );

        expect(RegisterDate::count())->toBe(1)
            ->and(RegisterDate::first()->school_id)->toBe($otherSchool->id);
    });

    it('only deletes dates for specified register', function () {
        $otherRegister = Register::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $date1 = RegisterDate::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => $this->register->id,
        ]);

        $date2 = RegisterDate::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => $otherRegister->id,
        ]);

        expect(RegisterDate::count())->toBe(2);

        $result = $this->service->deleteRegisterDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            [$date1->id, $date2->id]
        );

        expect(RegisterDate::count())->toBe(1)
            ->and(RegisterDate::first()->register_id)->toBe($otherRegister->id);
    });

    it('handles empty date array gracefully', function () {
        RegisterDate::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'register_id' => $this->register->id,
        ]);

        expect(RegisterDate::count())->toBe(1);

        $result = $this->service->deleteRegisterDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            []
        );

        expect($result)->toBeTrue()
            ->and(RegisterDate::count())->toBe(1);
    });

    it('returns true even when no dates match', function () {
        $result = $this->service->deleteRegisterDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            [99999]
        );

        expect($result)->toBeTrue();
    });
});

describe('integration scenarios', function () {
    it('creates and loads dates workflow', function () {
        $data = [
            'days' => ['monday', 'wednesday'],
            'supervisor' => 'Integration Test',
            'date_from' => '2024-12-16',
            'date_until' => '2024-12-18',
            'time_from' => '10:00',
            'time_until' => '12:00',
            'min_per_date' => 60,
            'max_registrations' => 5,
        ];

        $created = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($created)->toBeTrue();

        $loaded = $this->service->loadDays($this->register->id);

        expect($loaded)->toBeArray()
            ->and(count($loaded))->toBe(2);
    });

    it('creates, loads, and deletes dates workflow', function () {
        $data = [
            'days' => ['monday'],
            'supervisor' => 'Workflow Test',
            'date_from' => '2024-12-16',
            'date_until' => '2024-12-16',
            'time_from' => '10:00',
            'time_until' => '11:00',
            'min_per_date' => 60,
            'max_registrations' => 5,
        ];

        $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        $loaded = $this->service->loadDays($this->register->id);
        expect(count($loaded))->toBe(1);

        $dateIds = RegisterDate::pluck('id')->toArray();

        $this->service->deleteRegisterDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $dateIds
        );

        $afterDelete = $this->service->loadDays($this->register->id);
        expect(count($afterDelete))->toBe(0);
    });

    it('handles complex multi-supervisor multi-day scenario', function () {
        $data = [
            'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'supervisors' => ['Supervisor A', 'Supervisor B', 'Supervisor C'],
            'date_from' => '2024-12-16',
            'date_until' => '2024-12-20',
            'time_from' => '09:00',
            'time_until' => '12:00',
            'min_per_date' => 60,
            'pause' => 15,
            'max_registrations' => 10,
        ];

        $created = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($created)->toBeTrue();

        // 5 days * 3 supervisors * 2 slots (9-10, 10:15-11:15) = 30 dates
        expect(RegisterDate::count())->toBe(30);

        $loaded = $this->service->loadDays($this->register->id);
        expect(count($loaded))->toBe(5); // 5 unique dates
    });
});

describe('edge cases', function () {
    it('handles single minute time slot', function () {
        $data = [
            'days' => ['monday'],
            'supervisor' => 'Test',
            'date_from' => '2024-12-16',
            'date_until' => '2024-12-16',
            'time_from' => '10:00',
            'time_until' => '10:05',
            'min_per_date' => 5,
            'max_registrations' => 1,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($result)->toBeTrue()
            ->and(RegisterDate::count())->toBe(1);
    });

    it('handles year-end to new-year date range', function () {
        $data = [
            'days' => ['monday', 'tuesday', 'wednesday'],
            'supervisor' => 'Test',
            'date_from' => '2024-12-30',
            'date_until' => '2025-01-03',
            'time_from' => '10:00',
            'time_until' => '11:00',
            'min_per_date' => 60,
            'max_registrations' => 5,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($result)->toBeTrue()
            ->and(RegisterDate::count())->toBeGreaterThan(0);
    });

    it('handles dates with no matching weekdays', function () {
        $data = [
            'days' => ['saturday', 'sunday'],
            'supervisor' => 'Test',
            'date_from' => '2024-12-16', // Monday
            'date_until' => '2024-12-20', // Friday
            'time_from' => '10:00',
            'time_until' => '11:00',
            'min_per_date' => 60,
            'max_registrations' => 5,
        ];

        $result = $this->service->createDates(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $data
        );

        expect($result)->toBeTrue()
            ->and(RegisterDate::count())->toBe(0);
    });

    it('handles loading dates for non-existent register', function () {
        $result = $this->service->loadDays(99999);

        expect($result)->toBeArray()
            ->and($result)->toBeEmpty();
    });
});
