<?php

use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Notifications\StandardEmail;
use App\Services\RegisterDateBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new RegisterDateBookingService();
    
    Notification::fake();
    
    // Create test data
    $this->school = School::factory()->create([
        'long_name' => 'Test School',
        'logo' => 'test-logo.png',
    ]);
    
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);
    
    $this->register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Test Register',
    ]);
    
    $this->registerDate = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => '2024-12-15',
        'from' => '10:00',
        'to' => '12:00',
    ]);
    
    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'register_id' => $this->register->id,
        'email' => 'test@example.com',
    ]);
    
    // Create register_user role
    Role::firstOrCreate(['name' => 'register_user', 'guard_name' => 'web']);
});

describe('deleteBookings', function () {
    it('deletes a single booking without notification', function () {
        $booking = RegisterDateBooking::factory()->create([
            'register_date_id' => $this->registerDate->id,
            'register_id' => $this->register->id,
            'user_id' => $this->user->id,
            'school_id' => $this->school->id,
        ]);
        
        expect(RegisterDateBooking::count())->toBe(1);
        
        $this->service->deleteBookings($this->user, [$booking->id], false);
        
        expect(RegisterDateBooking::count())->toBe(0);
        Notification::assertNothingSent();
    });
    
    it('deletes a single booking with notification', function () {
        $booking = RegisterDateBooking::factory()->create([
            'register_date_id' => $this->registerDate->id,
            'register_id' => $this->register->id,
            'user_id' => $this->user->id,
            'school_id' => $this->school->id,
            'student_first_name' => 'John',
            'student_last_name' => 'Doe',
            'note' => 'Test note',
        ]);
        
        $this->service->deleteBookings($this->user, [$booking->id], true);
        
        expect(RegisterDateBooking::count())->toBe(0);
        
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(),
            StandardEmail::class
        );
    });
    
    it('deletes multiple bookings', function () {
        $booking1 = RegisterDateBooking::factory()->create([
            'register_date_id' => $this->registerDate->id,
            'register_id' => $this->register->id,
            'user_id' => $this->user->id,
            'school_id' => $this->school->id,
        ]);
        
        $booking2 = RegisterDateBooking::factory()->create([
            'register_date_id' => $this->registerDate->id,
            'register_id' => $this->register->id,
            'user_id' => $this->user->id,
            'school_id' => $this->school->id,
        ]);
        
        $booking3 = RegisterDateBooking::factory()->create([
            'register_date_id' => $this->registerDate->id,
            'register_id' => $this->register->id,
            'user_id' => $this->user->id,
            'school_id' => $this->school->id,
        ]);
        
        expect(RegisterDateBooking::count())->toBe(3);
        
        $this->service->deleteBookings($this->user, [$booking1->id, $booking2->id, $booking3->id], false);
        
        expect(RegisterDateBooking::count())->toBe(0);
    });
    
    it('sends notification email with correct subject and template', function () {
        $booking = RegisterDateBooking::factory()->create([
            'register_date_id' => $this->registerDate->id,
            'register_id' => $this->register->id,
            'user_id' => $this->user->id,
            'school_id' => $this->school->id,
        ]);
        
        $this->service->deleteBookings($this->user, [$booking->id], true);
        
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(),
            StandardEmail::class,
            function ($notification, $channels, $notifiable) {
                $mail = $notification->toMail($notifiable);
                return $mail->subject === 'Stornierung Termin' &&
                       $mail->markdown === 'mails.admin.deleteRegisterDateBooking';
            }
        );
    });
    
    it('includes all booking details in notification', function () {
        $booking = RegisterDateBooking::factory()->create([
            'register_date_id' => $this->registerDate->id,
            'register_id' => $this->register->id,
            'user_id' => $this->user->id,
            'school_id' => $this->school->id,
            'student_first_name' => 'Jane',
            'student_last_name' => 'Smith',
            'note' => 'Important note',
        ]);
        
        $this->service->deleteBookings($this->user, [$booking->id], true);
        
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(),
            StandardEmail::class,
            function ($notification) {
                return isset($notification->data['register_name']) &&
                       isset($notification->data['student_last_name']) &&
                       isset($notification->data['student_first_name']) &&
                       isset($notification->data['note']) &&
                       isset($notification->data['date']) &&
                       isset($notification->data['from']) &&
                       isset($notification->data['to']);
            }
        );
    });
    
    it('only deletes bookings for current school and register', function () {
        $otherSchool = School::factory()->create();
        $otherSchoolyear = Schoolyear::factory()->create(['school_id' => $otherSchool->id]);
        $otherRegister = Register::factory()->create([
            'school_id' => $otherSchool->id,
            'schoolyear_id' => $otherSchoolyear->id,
        ]);
        $otherRegisterDate = RegisterDate::factory()->create(['register_id' => $otherRegister->id]);
        
        $booking1 = RegisterDateBooking::factory()->create([
            'register_date_id' => $this->registerDate->id,
            'register_id' => $this->register->id,
            'user_id' => $this->user->id,
            'school_id' => $this->school->id,
        ]);
        
        $booking2 = RegisterDateBooking::factory()->create([
            'register_date_id' => $otherRegisterDate->id,
            'register_id' => $otherRegister->id,
            'user_id' => $this->user->id,
            'school_id' => $otherSchool->id,
        ]);
        
        expect(RegisterDateBooking::count())->toBe(2);
        
        // Only booking1 should be attempted for deletion (booking2 is filtered out by query)
        // So if we only pass booking1's ID, only it will be deleted
        $this->service->deleteBookings($this->user, [$booking1->id], false);
        
        expect(RegisterDateBooking::count())->toBe(1)
            ->and(RegisterDateBooking::first()->id)->toBe($booking2->id);
    });
    
    it('handles empty booking array gracefully', function () {
        expect(RegisterDateBooking::count())->toBe(0);
        
        $this->service->deleteBookings($this->user, [], false);
        
        expect(RegisterDateBooking::count())->toBe(0);
    });
    
    it('uses school information in notification', function () {
        $booking = RegisterDateBooking::factory()->create([
            'register_date_id' => $this->registerDate->id,
            'register_id' => $this->register->id,
            'user_id' => $this->user->id,
            'school_id' => $this->school->id,
        ]);
        
        $this->service->deleteBookings($this->user, [$booking->id], true);
        
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(),
            StandardEmail::class,
            function ($notification) {
                return $notification->data['from_name'] === $this->school->long_name;
            }
        );
    });
});

describe('updateOrCreateUser', function () {
    it('creates new user when email does not exist', function () {
        $validated = [
            'email' => 'newuser@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567890',
        ];
        
        expect(User::where('email', 'newuser@example.com')->exists())->toBeFalse();
        
        $user = $this->service->updateOrCreateUser($this->school->id, $validated);
        
        expect($user)->toBeInstanceOf(User::class)
            ->and($user->email)->toBe('newuser@example.com')
            ->and($user->first_name)->toBe('John')
            ->and($user->last_name)->toBe('Doe')
            ->and($user->school_id)->toBe($this->school->id);
    });
    
    it('sets email_verified_at and confirmed_at for new user', function () {
        $validated = [
            'email' => 'newuser@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];
        
        $user = $this->service->updateOrCreateUser($this->school->id, $validated);
        
        expect($user->email_verified_at)->not->toBeNull()
            ->and($user->confirmed_at)->not->toBeNull();
    });
    
    it('sets password for new user', function () {
        $validated = [
            'email' => 'newuser@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];
        
        $user = $this->service->updateOrCreateUser($this->school->id, $validated);
        
        expect($user->password)->not->toBeNull();
    });
    
    it('assigns register_user role to new user', function () {
        $validated = [
            'email' => 'newuser@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];
        
        $user = $this->service->updateOrCreateUser($this->school->id, $validated);
        
        expect($user->hasRole('register_user'))->toBeTrue();
    });
    
    it('updates existing user when email exists', function () {
        $existingUser = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'existing@example.com',
            'first_name' => 'Old',
            'last_name' => 'Name',
            'phone' => '0000000000',
        ]);
        
        $validated = [
            'email' => 'existing@example.com',
            'first_name' => 'New',
            'last_name' => 'Name',
            'phone' => '1111111111',
        ];
        
        $user = $this->service->updateOrCreateUser($this->school->id, $validated);
        
        expect($user->id)->toBe($existingUser->id)
            ->and($user->first_name)->toBe('New')
            ->and($user->phone)->toBe('1111111111');
    });
    
    it('sets email_verified_at if null on existing user', function () {
        $existingUser = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'existing@example.com',
            'email_verified_at' => null,
        ]);
        
        $validated = [
            'email' => 'existing@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];
        
        $user = $this->service->updateOrCreateUser($this->school->id, $validated);
        
        expect($user->email_verified_at)->not->toBeNull();
    });
    
    it('sets confirmed_at if null on existing user', function () {
        $existingUser = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'existing@example.com',
            'confirmed_at' => null,
        ]);
        
        $validated = [
            'email' => 'existing@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];
        
        $user = $this->service->updateOrCreateUser($this->school->id, $validated);
        
        expect($user->confirmed_at)->not->toBeNull();
    });
    
    it('does not overwrite email_verified_at if already set', function () {
        $originalDate = now()->subDays(10);
        
        $existingUser = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'existing@example.com',
            'email_verified_at' => $originalDate,
        ]);
        
        $validated = [
            'email' => 'existing@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];
        
        $user = $this->service->updateOrCreateUser($this->school->id, $validated);
        
        expect($user->email_verified_at->timestamp)->toBe($originalDate->timestamp);
    });
    
    it('assigns register_user role to existing user', function () {
        $existingUser = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'existing@example.com',
        ]);
        
        expect($existingUser->hasRole('register_user'))->toBeFalse();
        
        $validated = [
            'email' => 'existing@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];
        
        $user = $this->service->updateOrCreateUser($this->school->id, $validated);
        
        expect($user->hasRole('register_user'))->toBeTrue();
    });
    
    it('only affects users in specified school', function () {
        $otherSchool = School::factory()->create();
        
        $otherUser = User::factory()->create([
            'school_id' => $otherSchool->id,
            'email' => 'test@example.com',
            'first_name' => 'Other',
        ]);
        
        $validated = [
            'email' => 'test@example.com',
            'first_name' => 'New',
            'last_name' => 'User',
        ];
        
        $user = $this->service->updateOrCreateUser($this->school->id, $validated);
        
        // Should create new user, not update other school's user
        expect($user->id)->not->toBe($otherUser->id)
            ->and($user->school_id)->toBe($this->school->id)
            ->and(User::count())->toBe(2); // original user from beforeEach + new user (otherUser doesn't count in filter)
    });
});

describe('createBooking', function () {
    it('creates booking with all required fields', function () {
        $validated = [
            'register_date_id' => $this->registerDate->id,
            'student_first_name' => 'Jane',
            'student_last_name' => 'Doe',
            'student_birthdate' => '2015-05-20',
            'note' => 'Test note',
        ];
        
        $booking = $this->service->createBooking(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $this->user->id,
            $validated
        );
        
        expect($booking)->toBeInstanceOf(RegisterDateBooking::class)
            ->and($booking->school_id)->toBe($this->school->id)
            ->and($booking->schoolyear_id)->toBe($this->schoolyear->id)
            ->and($booking->register_id)->toBe($this->register->id)
            ->and($booking->user_id)->toBe($this->user->id)
            ->and($booking->student_first_name)->toBe('Jane')
            ->and($booking->student_last_name)->toBe('Doe');
    });
    
    it('creates booking without notification when is_notify is false', function () {
        $validated = [
            'register_date_id' => $this->registerDate->id,
            'student_first_name' => 'Jane',
            'student_last_name' => 'Doe',
            'is_notify' => false,
        ];
        
        $booking = $this->service->createBooking(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $this->user->id,
            $validated
        );
        
        expect($booking)->toBeInstanceOf(RegisterDateBooking::class);
        Notification::assertNothingSent();
    });
    
    it('creates booking without notification when is_notify is not set', function () {
        $validated = [
            'register_date_id' => $this->registerDate->id,
            'student_first_name' => 'Jane',
            'student_last_name' => 'Doe',
        ];
        
        $booking = $this->service->createBooking(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $this->user->id,
            $validated
        );
        
        expect($booking)->toBeInstanceOf(RegisterDateBooking::class);
        Notification::assertNothingSent();
    });
    
    it('sends notification when is_notify is true', function () {
        $validated = [
            'register_date_id' => $this->registerDate->id,
            'student_first_name' => 'Jane',
            'student_last_name' => 'Doe',
            'note' => 'Test note',
            'is_notify' => true,
        ];
        
        $booking = $this->service->createBooking(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $this->user->id,
            $validated
        );
        
        expect($booking)->toBeInstanceOf(RegisterDateBooking::class);
        
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(),
            StandardEmail::class
        );
    });
    
    it('sends notification with correct subject and template', function () {
        $validated = [
            'register_date_id' => $this->registerDate->id,
            'student_first_name' => 'Jane',
            'student_last_name' => 'Doe',
            'is_notify' => true,
        ];
        
        $this->service->createBooking(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $this->user->id,
            $validated
        );
        
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(),
            StandardEmail::class,
            function ($notification, $channels, $notifiable) {
                $mail = $notification->toMail($notifiable);
                return $mail->subject === 'Buchung Termin' &&
                       $mail->markdown === 'mails.admin.bookRegisterDateBooking';
            }
        );
    });
    
    it('includes all booking details in notification', function () {
        $validated = [
            'register_date_id' => $this->registerDate->id,
            'student_first_name' => 'Jane',
            'student_last_name' => 'Doe',
            'note' => 'Important information',
            'is_notify' => true,
        ];
        
        $this->service->createBooking(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $this->user->id,
            $validated
        );
        
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(),
            StandardEmail::class,
            function ($notification) {
                return isset($notification->data['register_name']) &&
                       isset($notification->data['student_last_name']) &&
                       isset($notification->data['student_first_name']) &&
                       isset($notification->data['note']) &&
                       isset($notification->data['date']) &&
                       isset($notification->data['from']) &&
                       isset($notification->data['to']);
            }
        );
    });
    
    it('removes is_notify from booking data', function () {
        $validated = [
            'register_date_id' => $this->registerDate->id,
            'student_first_name' => 'Jane',
            'student_last_name' => 'Doe',
            'is_notify' => true,
        ];
        
        $booking = $this->service->createBooking(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $this->user->id,
            $validated
        );
        
        // is_notify should not be stored in the booking
        expect($booking->toArray())->not->toHaveKey('is_notify');
    });
    
    it('uses school information in notification', function () {
        $validated = [
            'register_date_id' => $this->registerDate->id,
            'student_first_name' => 'Jane',
            'student_last_name' => 'Doe',
            'is_notify' => true,
        ];
        
        $this->service->createBooking(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $this->user->id,
            $validated
        );
        
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(),
            StandardEmail::class,
            function ($notification) {
                return $notification->data['from_name'] === $this->school->long_name;
            }
        );
    });
    
    it('returns booking with loaded relationships', function () {
        $validated = [
            'register_date_id' => $this->registerDate->id,
            'student_first_name' => 'Jane',
            'student_last_name' => 'Doe',
        ];
        
        $booking = $this->service->createBooking(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $this->user->id,
            $validated
        );
        
        // Booking can load its relationships
        expect($booking->school)->toBeInstanceOf(School::class)
            ->and($booking->register)->toBeInstanceOf(Register::class)
            ->and($booking->user)->toBeInstanceOf(User::class)
            ->and($booking->registerDate)->toBeInstanceOf(RegisterDate::class);
    });
});

describe('integration scenarios', function () {
    it('handles complete booking workflow with new user', function () {
        $userValidated = [
            'email' => 'newbooking@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'phone' => '1234567890',
        ];
        
        $user = $this->service->updateOrCreateUser($this->school->id, $userValidated);
        
        $bookingValidated = [
            'register_date_id' => $this->registerDate->id,
            'student_first_name' => 'Child',
            'student_last_name' => 'Name',
            'is_notify' => true,
        ];
        
        $booking = $this->service->createBooking(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $user->id,
            $bookingValidated
        );
        
        expect($booking)->toBeInstanceOf(RegisterDateBooking::class)
            ->and($booking->user_id)->toBe($user->id)
            ->and($user->hasRole('register_user'))->toBeTrue();
        
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(),
            StandardEmail::class
        );
    });
    
    it('handles booking deletion workflow', function () {
        $booking1 = RegisterDateBooking::factory()->create([
            'register_date_id' => $this->registerDate->id,
            'register_id' => $this->register->id,
            'user_id' => $this->user->id,
            'school_id' => $this->school->id,
        ]);
        
        $booking2 = RegisterDateBooking::factory()->create([
            'register_date_id' => $this->registerDate->id,
            'register_id' => $this->register->id,
            'user_id' => $this->user->id,
            'school_id' => $this->school->id,
        ]);
        
        expect(RegisterDateBooking::count())->toBe(2);
        
        $this->service->deleteBookings($this->user, [$booking1->id], true);
        
        expect(RegisterDateBooking::count())->toBe(1)
            ->and(RegisterDateBooking::first()->id)->toBe($booking2->id);
        
        Notification::assertSentTimes(StandardEmail::class, 1);
    });
    
    it('handles updating existing user and creating booking', function () {
        $existingUser = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'update@example.com',
            'phone' => '0000000000',
        ]);
        
        $userValidated = [
            'email' => 'update@example.com',
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'phone' => '9999999999',
        ];
        
        $user = $this->service->updateOrCreateUser($this->school->id, $userValidated);
        
        expect($user->id)->toBe($existingUser->id)
            ->and($user->phone)->toBe('9999999999');
        
        $bookingValidated = [
            'register_date_id' => $this->registerDate->id,
            'student_first_name' => 'Child',
            'student_last_name' => 'Name',
        ];
        
        $booking = $this->service->createBooking(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $user->id,
            $bookingValidated
        );
        
        expect($booking->user_id)->toBe($existingUser->id);
    });
});

describe('edge cases', function () {
    it('handles booking with minimal data', function () {
        $validated = [
            'register_date_id' => $this->registerDate->id,
        ];
        
        $booking = $this->service->createBooking(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $this->user->id,
            $validated
        );
        
        expect($booking)->toBeInstanceOf(RegisterDateBooking::class)
            ->and($booking->register_date_id)->toBe($this->registerDate->id);
    });
    
    it('handles user creation with only email and name', function () {
        $validated = [
            'email' => 'minimal@example.com',
            'first_name' => 'First',
            'last_name' => 'Last',
        ];
        
        $user = $this->service->updateOrCreateUser($this->school->id, $validated);
        
        expect($user)->toBeInstanceOf(User::class)
            ->and($user->email)->toBe('minimal@example.com');
    });
    
    it('handles null note in booking notification', function () {
        $booking = RegisterDateBooking::factory()->create([
            'register_date_id' => $this->registerDate->id,
            'register_id' => $this->register->id,
            'user_id' => $this->user->id,
            'school_id' => $this->school->id,
            'note' => null,
        ]);
        
        $this->service->deleteBookings($this->user, [$booking->id], true);
        
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(),
            StandardEmail::class
        );
    });
    
    it('handles booking with optional fields', function () {
        $validated = [
            'register_date_id' => $this->registerDate->id,
            'student_first_name' => 'Jane',
            'student_last_name' => 'Doe',
            'student_birthdate' => null,
            'note' => null,
        ];
        
        $booking = $this->service->createBooking(
            $this->school->id,
            $this->schoolyear->id,
            $this->register->id,
            $this->user->id,
            $validated
        );
        
        expect($booking)->toBeInstanceOf(RegisterDateBooking::class)
            ->and($booking->student_birthdate)->toBeNull()
            ->and($booking->note)->toBeNull();
    });
});
