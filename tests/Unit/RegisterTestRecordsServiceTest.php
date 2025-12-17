<?php

use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\RegisterTestRecordsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Create required role for user factory
    Role::create(['name' => 'register_user', 'guard_name' => 'web']);
    
    $this->service = new RegisterTestRecordsService();
});

describe('checkRequirement', function () {
    it('returns false when school with id 1 does not exist', function () {
        $result = $this->service->checkRequirement();
        
        expect($result)->toBeFalse();
    });
    
    it('returns false when schoolyear with id 1 does not exist', function () {
        School::factory()->create(['id' => 1]);
        
        $result = $this->service->checkRequirement();
        
        expect($result)->toBeFalse();
    });
    
    it('returns true when both school and schoolyear exist', function () {
        School::factory()->create(['id' => 1]);
        Schoolyear::factory()->create(['id' => 1, 'school_id' => 1]);
        
        $result = $this->service->checkRequirement();
        
        expect($result)->toBeTrue();
    });
    
    it('returns false when schoolyear exists but wrong school_id', function () {
        School::factory()->create(['id' => 1]);
        Schoolyear::factory()->create(['id' => 1, 'school_id' => 2]);
        
        $result = $this->service->checkRequirement();
        
        expect($result)->toBeFalse();
    });
    
    it('validates specific IDs are required', function () {
        School::factory()->create(['id' => 2]);
        Schoolyear::factory()->create(['id' => 2, 'school_id' => 2]);
        
        $result = $this->service->checkRequirement();
        
        expect($result)->toBeFalse();
    });
});

describe('checkOrCreateUsers', function () {
    it('creates 500 users when none exist', function () {
        $result = $this->service->checkOrCreateUsers();
        
        expect($result)->toBeTrue()
            ->and(User::count())->toBe(500);
    });
    
    it('returns false when 500 or more users exist', function () {
        User::factory()->count(500)->create();
        
        $result = $this->service->checkOrCreateUsers();
        
        expect($result)->toBeFalse()
            ->and(User::count())->toBe(500);
    });
    
    it('creates users when less than 500 exist', function () {
        User::factory()->count(100)->create();
        
        $result = $this->service->checkOrCreateUsers();
        
        expect($result)->toBeTrue()
            ->and(User::count())->toBe(600);
    });
    
    it('returns false when exactly 500 users exist', function () {
        User::factory()->count(500)->create();
        
        $result = $this->service->checkOrCreateUsers();
        
        expect($result)->toBeFalse();
    });
    
    it('returns false when more than 500 users exist', function () {
        User::factory()->count(600)->create();
        
        $result = $this->service->checkOrCreateUsers();
        
        expect($result)->toBeFalse()
            ->and(User::count())->toBe(600);
    });
    
    it('creates users when 499 users exist', function () {
        User::factory()->count(499)->create();
        
        $result = $this->service->checkOrCreateUsers();
        
        expect($result)->toBeTrue()
            ->and(User::count())->toBe(999);
    });
});

describe('createRegisterEntries', function () {
    beforeEach(function () {
        School::factory()->create(['id' => 1]);
        Schoolyear::factory()->create(['id' => 1, 'school_id' => 1]);
        User::factory()->count(500)->create();
    });
    
    it('creates a register with correct data', function () {
        $this->service->createRegisterEntries();
        
        expect(Register::count())->toBe(1);
        
        $register = Register::first();
        expect($register->name)->toBe('Gruppengespräche')
            ->and($register->school_id)->toBe(1)
            ->and($register->schoolyear_id)->toBe(1);
    });
    
    it('creates register with all required fields', function () {
        $this->service->createRegisterEntries();
        
        $register = Register::first();
        expect($register->show_phone)->toBeTruthy()
            ->and($register->must_phone)->toBeTruthy()
            ->and($register->show_student_last_name)->toBeTruthy()
            ->and($register->must_student_last_name)->toBeTruthy()
            ->and($register->show_student_first_name)->toBeTruthy()
            ->and($register->must_student_first_name)->toBeTruthy()
            ->and($register->show_student_birthdate)->toBeTruthy()
            ->and($register->must_student_birthdate)->toBeTruthy();
    });
    
    it('creates register dates for three days', function () {
        $this->service->createRegisterEntries();
        
        $dates = RegisterDate::distinct('date')->pluck('date');
        
        expect($dates->count())->toBe(3);
    });
    
    it('creates register dates for Monday, Tuesday, Wednesday', function () {
        $this->service->createRegisterEntries();
        
        $dates = RegisterDate::orderBy('date')->pluck('date')->toArray();
        
        $nextMonday = Carbon::now()->next(Carbon::MONDAY)->toDateString();
        $nextTuesday = Carbon::now()->next(Carbon::TUESDAY)->toDateString();
        $nextWednesday = Carbon::now()->next(Carbon::WEDNESDAY)->toDateString();
        
        expect(in_array($nextMonday, $dates))->toBeTrue()
            ->and(in_array($nextTuesday, $dates))->toBeTrue()
            ->and(in_array($nextWednesday, $dates))->toBeTrue();
    });
    
    it('creates 24 register dates total', function () {
        $this->service->createRegisterEntries();
        
        expect(RegisterDate::count())->toBe(24);
    });
    
    it('creates 8 register dates per day', function () {
        $this->service->createRegisterEntries();
        
        $nextMonday = Carbon::now()->next(Carbon::MONDAY)->toDateString();
        $mondayDates = RegisterDate::where('date', $nextMonday)->count();
        
        expect($mondayDates)->toBe(8);
    });
    
    it('creates register dates with two groups', function () {
        $this->service->createRegisterEntries();
        
        $supervisors = RegisterDate::distinct('supervisor')->pluck('supervisor')->toArray();
        
        expect($supervisors)->toContain('Gruppe 1')
            ->and($supervisors)->toContain('Gruppe 2')
            ->and(count($supervisors))->toBe(2);
    });
    
    it('creates register dates with time slots', function () {
        $this->service->createRegisterEntries();
        
        $times = RegisterDate::distinct('from')->pluck('from')->toArray();

        expect($times)->toContain('08:00:00')
            ->and($times)->toContain('09:00:00')
            ->and($times)->toContain('10:00:00')
            ->and($times)->toContain('11:00:00');
    });
    
    it('sets max_registrations to 20 for each date', function () {
        $this->service->createRegisterEntries();
        
        $dates = RegisterDate::all();
        
        foreach ($dates as $date) {
            expect($date->max_registrations)->toBe(20);
        }
    });
    
    it('creates bookings for all 500 users', function () {
        $this->service->createRegisterEntries();
        
        expect(RegisterDateBooking::count())->toBe(480); // 24 dates * 20 bookings each
    });
    
    it('creates 20 bookings per register date', function () {
        $this->service->createRegisterEntries();
        
        $dates = RegisterDate::withCount('bookings')->get();
        
        foreach ($dates as $date) {
            expect($date->bookings_count)->toBe(20);
        }
    });
    
    it('assigns unique users to bookings', function () {
        $this->service->createRegisterEntries();
        
        $userIds = RegisterDateBooking::pluck('user_id')->toArray();
        $uniqueUserIds = array_unique($userIds);
        
        expect(count($uniqueUserIds))->toBe(480);
    });
    
    it('creates bookings with student data', function () {
        $this->service->createRegisterEntries();
        
        $booking = RegisterDateBooking::first();
        
        expect($booking->student_last_name)->not->toBeNull()
            ->and($booking->student_first_name)->not->toBeNull()
            ->and($booking->student_birthdate)->not->toBeNull();
    });
    
    it('creates bookings with school and schoolyear', function () {
        $this->service->createRegisterEntries();
        
        $bookings = RegisterDateBooking::all();
        
        foreach ($bookings as $booking) {
            expect($booking->school_id)->toBe(1)
                ->and($booking->schoolyear_id)->toBe(1);
        }
    });
    
    it('returns true after successful creation', function () {
        $result = $this->service->createRegisterEntries();
        
        expect($result)->toBeTrue();
    });
    
    it('creates register with description', function () {
        $this->service->createRegisterEntries();
        
        $register = Register::first();
        
        expect($register->description_on_website)->toContain('Liebe Eltern')
            ->and($register->description_on_website)->toContain('Gruppengespräche');
    });
});

describe('integration scenarios', function () {
    it('completes full workflow from requirement check to creation', function () {
        // Setup
        School::factory()->create(['id' => 1]);
        Schoolyear::factory()->create(['id' => 1, 'school_id' => 1]);
        
        // Check requirements
        expect($this->service->checkRequirement())->toBeTrue();
        
        // Create users
        expect($this->service->checkOrCreateUsers())->toBeTrue();
        expect(User::count())->toBe(500);
        
        // Create register entries
        expect($this->service->createRegisterEntries())->toBeTrue();
        expect(Register::count())->toBe(1);
        expect(RegisterDate::count())->toBe(24);
        expect(RegisterDateBooking::count())->toBe(480);
    });
    
    it('handles re-running createRegisterEntries', function () {
        School::factory()->create(['id' => 1]);
        Schoolyear::factory()->create(['id' => 1, 'school_id' => 1]);
        User::factory()->count(500)->create();
        
        $this->service->createRegisterEntries();
        $firstRegisterCount = Register::count();
        
        $this->service->createRegisterEntries();
        $secondRegisterCount = Register::count();
        
        expect($secondRegisterCount)->toBe($firstRegisterCount + 1);
    });
    
    it('handles workflow when users already exist', function () {
        School::factory()->create(['id' => 1]);
        Schoolyear::factory()->create(['id' => 1, 'school_id' => 1]);
        User::factory()->count(600)->create();
        
        // Should not create more users
        expect($this->service->checkOrCreateUsers())->toBeFalse();
        expect(User::count())->toBe(600);
        
        // Should still create register entries
        expect($this->service->createRegisterEntries())->toBeTrue();
    });
});

describe('data validation', function () {
    beforeEach(function () {
        School::factory()->create(['id' => 1]);
        Schoolyear::factory()->create(['id' => 1, 'school_id' => 1]);
        User::factory()->count(500)->create();
    });
    
    it('creates register dates with correct time ranges', function () {
        $this->service->createRegisterEntries();
        
        $dates = RegisterDate::all();
        
        foreach ($dates as $date) {
            $from = Carbon::parse($date->from);
            $to = Carbon::parse($date->to);
            
            expect($to->greaterThan($from))->toBeTrue();
        }
    });
    
    it('creates bookings with valid birthdates', function () {
        $this->service->createRegisterEntries();
        
        $bookings = RegisterDateBooking::all();
        
        foreach ($bookings as $booking) {
            $birthdate = Carbon::parse($booking->student_birthdate);
            expect($birthdate->isPast())->toBeTrue();
        }
    });
    
    it('assigns correct register_id to all dates', function () {
        $this->service->createRegisterEntries();
        
        $register = Register::first();
        $dates = RegisterDate::all();
        
        foreach ($dates as $date) {
            expect($date->register_id)->toBe($register->id);
        }
    });
    
    it('assigns correct register_date_id to bookings', function () {
        $this->service->createRegisterEntries();
        
        $dates = RegisterDate::all();
        
        foreach ($dates as $date) {
            $bookings = RegisterDateBooking::where('register_date_id', $date->id)->get();
            
            foreach ($bookings as $booking) {
                expect($booking->register_date_id)->toBe($date->id);
            }
        }
    });
});

describe('edge cases', function () {
    it('handles checkRequirement with missing school gracefully', function () {
        Schoolyear::factory()->create(['id' => 1, 'school_id' => 1]);
        
        $result = $this->service->checkRequirement();
        
        expect($result)->toBeFalse();
    });
    
    it('handles checkOrCreateUsers when exactly at threshold', function () {
        User::factory()->count(500)->create();
        
        $result = $this->service->checkOrCreateUsers();
        
        expect($result)->toBeFalse()
            ->and(User::count())->toBe(500);
    });
    
    it('creates users even when 1 user exists', function () {
        User::factory()->create();
        
        $result = $this->service->checkOrCreateUsers();
        
        expect($result)->toBeTrue()
            ->and(User::count())->toBe(501);
    });
    
    it('handles users with high IDs correctly', function () {
        School::factory()->create(['id' => 1]);
        Schoolyear::factory()->create(['id' => 1, 'school_id' => 1]);
        
        // Create users with specific IDs
        $users = User::factory()->count(500)->create();
        
        $this->service->createRegisterEntries();
        
        $bookings = RegisterDateBooking::all();
        
        // All bookings should have valid user_ids
        foreach ($bookings as $booking) {
            expect($booking->user_id)->toBeGreaterThan(0);
        }
    });
});

describe('date calculations', function () {
    it('calculates next Monday correctly', function () {
        School::factory()->create(['id' => 1]);
        Schoolyear::factory()->create(['id' => 1, 'school_id' => 1]);
        User::factory()->count(500)->create();
        
        $expectedMonday = Carbon::now()->next(Carbon::MONDAY)->toDateString();
        
        $this->service->createRegisterEntries();
        
        $mondayDates = RegisterDate::where('date', $expectedMonday)->get();
        
        expect($mondayDates->count())->toBe(8);
    });
    
    it('creates dates in chronological order', function () {
        School::factory()->create(['id' => 1]);
        Schoolyear::factory()->create(['id' => 1, 'school_id' => 1]);
        User::factory()->count(500)->create();
        
        $this->service->createRegisterEntries();
        
        $dates = RegisterDate::orderBy('date')->pluck('date')->unique()->values()->toArray();
        
        expect(count($dates))->toBe(3)
            ->and($dates[0])->toBeLessThan($dates[1])
            ->and($dates[1])->toBeLessThan($dates[2]);
    });
});

describe('supervisor distribution', function () {
    beforeEach(function () {
        School::factory()->create(['id' => 1]);
        Schoolyear::factory()->create(['id' => 1, 'school_id' => 1]);
        User::factory()->count(500)->create();
    });
    
    it('distributes dates evenly between groups', function () {
        $this->service->createRegisterEntries();
        
        $gruppe1Count = RegisterDate::where('supervisor', 'Gruppe 1')->count();
        $gruppe2Count = RegisterDate::where('supervisor', 'Gruppe 2')->count();
        
        expect($gruppe1Count)->toBe(12)
            ->and($gruppe2Count)->toBe(12);
    });
    
    it('creates alternating groups per time slot', function () {
        $this->service->createRegisterEntries();
        
        $nextMonday = Carbon::now()->next(Carbon::MONDAY)->toDateString();
        $mondayDates = RegisterDate::where('date', $nextMonday)
            ->where('from', '08:00')
            ->pluck('supervisor')
            ->toArray();
        
        expect($mondayDates)->toContain('Gruppe 1')
            ->and($mondayDates)->toContain('Gruppe 2');
    });
});

describe('booking distribution', function () {
    beforeEach(function () {
        School::factory()->create(['id' => 1]);
        Schoolyear::factory()->create(['id' => 1, 'school_id' => 1]);
        User::factory()->count(500)->create();
    });
    
    it('uses different users for each booking', function () {
        $this->service->createRegisterEntries();
        
        $bookings = RegisterDateBooking::all();
        $userIds = $bookings->pluck('user_id')->toArray();
        
        // Check no user appears twice (since we have 480 bookings and 500 users)
        expect(count($userIds))->toBe(count(array_unique($userIds)));
    });
    
    it('assigns users from highest ID downward', function () {
        $this->service->createRegisterEntries();
        
        $highestUserId = User::max('id');
        $bookings = RegisterDateBooking::pluck('user_id')->toArray();
        
        // First booking should use highest ID
        expect(in_array($highestUserId, $bookings))->toBeTrue();
    });
    
    it('copies user last_name to student_last_name', function () {
        $this->service->createRegisterEntries();
        
        $booking = RegisterDateBooking::first();
        $user = User::find($booking->user_id);
        
        expect($booking->student_last_name)->toBe($user->last_name);
    });
});
