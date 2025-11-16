<?php

use App\Jobs\PrintRegisterDateJob;
use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Notifications\StandardEmail;
use App\Services\PrintRegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Create test directories
    if (!is_dir(storage_path('app/private/pdf'))) {
        mkdir(storage_path('app/private/pdf'), 0775, true);
    }
    
    // Create test data
    $this->school = School::factory()->create([
        'long_name' => 'Test School Long Name',
        'short_name' => 'Test School',
        'logo' => 'test-logo.png',
    ]);
    
    $this->schoolyear = Schoolyear::factory()->create([
        'school_id' => $this->school->id,
    ]);
    
    $this->register = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Test Register 2024',
    ]);
    
    $this->user = User::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
        'phone' => '1234567890',
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);
    
    $this->data = [
        'register_id' => $this->register->id,
    ];
    
    // Mock Pdf facade to avoid actual PDF generation
    Pdf::fake();
});

test('job can be instantiated', function () {
    $job = new PrintRegisterDateJob($this->user, $this->data);
    
    expect($job)->toBeInstanceOf(PrintRegisterDateJob::class)
        ->and($job->user)->toBe($this->user)
        ->and($job->data)->toBe($this->data);
});

test('job implements ShouldQueue interface', function () {
    $job = new PrintRegisterDateJob($this->user, $this->data);
    
    expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

test('job can be dispatched to queue', function () {
    Queue::fake();
    
    PrintRegisterDateJob::dispatch($this->user, $this->data);
    
    Queue::assertPushed(PrintRegisterDateJob::class, function ($job) {
        return $job->user->id === $this->user->id
            && $job->data['register_id'] === $this->register->id;
    });
});

test('job sends notification email when handled', function () {
    Notification::fake();
    
    $job = new PrintRegisterDateJob($this->user, $this->data);
    $job->handle();
    
    Notification::assertSentOnDemand(StandardEmail::class);
});

test('job notification contains correct email data', function () {
    Notification::fake();
    
    $job = new PrintRegisterDateJob($this->user, $this->data);
    $job->handle();
    
    Notification::assertSentOnDemand(
        StandardEmail::class,
        function ($notification, $channels, $notifiable) {
            // Check email configuration
            expect($notification->data['from_address'])->toBe(config('schooltool.noreply_email'))
                ->and($notification->data['from_name'])->toBe($this->school->long_name)
                ->and($notification->data['subject'])->toContain($this->register->name)
                ->and($notification->data['subject'])->toContain('Pdf-Datei (Tag)')
                ->and($notification->data['markdown'])->toBe('mails.admin.sendPrint')
                ->and($notifiable->routes['mail'])->toBe($this->user->email);
            
            return true;
        }
    );
});

test('job uses PrintRegisterService to generate PDF', function () {
    Notification::fake();
    
    $job = new PrintRegisterDateJob($this->user, $this->data);
    $job->handle();
    
    // Verify PDF generation was called
    Pdf::assertViewIs('pdfs.registerDate');
});

test('job retrieves correct register from data', function () {
    Notification::fake();
    
    $job = new PrintRegisterDateJob($this->user, $this->data);
    $job->handle();
    
    // Verify the register exists and was used
    expect($this->register->fresh())->not->toBeNull()
        ->and($this->register->name)->toBe('Test Register 2024');
});

test('job accesses user selected school', function () {
    Notification::fake();
    
    $job = new PrintRegisterDateJob($this->user, $this->data);
    $job->handle();
    
    expect($this->user->selectedSchool)->not->toBeNull()
        ->and($this->user->selectedSchool->id)->toBe($this->school->id)
        ->and($this->user->selectedSchool->long_name)->toBe('Test School Long Name');
});

test('job notification routes to correct email address', function () {
    Notification::fake();
    
    $job = new PrintRegisterDateJob($this->user, $this->data);
    $job->handle();
    
    Notification::assertSentOnDemand(
        StandardEmail::class,
        function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'john.doe@example.com';
        }
    );
});

test('job handles multiple register dates', function () {
    Notification::fake();
    
    // Create register dates
    $registerDate1 = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => now()->addDays(1),
    ]);
    
    $registerDate2 = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => now()->addDays(2),
    ]);
    
    // Create bookings
    RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDate1->id,
        'user_id' => $this->user->id,
    ]);
    
    RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDate2->id,
        'user_id' => $this->user->id,
    ]);
    
    $job = new PrintRegisterDateJob($this->user, $this->data);
    $job->handle();
    
    // Verify bookings were created and job completed
    expect($this->register->bookings()->count())->toBe(2);
});

test('job attaches PDF file to notification', function () {
    Notification::fake();
    
    $job = new PrintRegisterDateJob($this->user, $this->data);
    $job->handle();
    
    Notification::assertSentOnDemand(
        StandardEmail::class,
        function ($notification) {
            return is_array($notification->attachments) && count($notification->attachments) > 0;
        }
    );
});

test('job uses school logo in email', function () {
    Notification::fake();
    
    $job = new PrintRegisterDateJob($this->user, $this->data);
    $job->handle();
    
    Notification::assertSentOnDemand(
        StandardEmail::class,
        function ($notification) {
            $expectedLogoPath = asset('/storage/images/' . $this->school->logo);
            return $notification->data['logo'] === $expectedLogoPath;
        }
    );
});

test('job throws exception when register not found', function () {
    Notification::fake();
    
    $invalidData = ['register_id' => 99999];
    
    $job = new PrintRegisterDateJob($this->user, $invalidData);
    
    expect(fn() => $job->handle())
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

test('job can be serialized and unserialized', function () {
    $job = new PrintRegisterDateJob($this->user, $this->data);
    
    $serialized = serialize($job);
    $unserialized = unserialize($serialized);
    
    expect($unserialized)->toBeInstanceOf(PrintRegisterDateJob::class)
        ->and($unserialized->data)->toBe($this->data);
});

test('job subject line includes register name and date indicator', function () {
    Notification::fake();
    
    $job = new PrintRegisterDateJob($this->user, $this->data);
    $job->handle();
    
    Notification::assertSentOnDemand(
        StandardEmail::class,
        function ($notification) {
            $subject = $notification->data['subject'];
            return str_contains($subject, 'Test Register 2024')
                && str_contains($subject, 'Pdf-Datei (Tag)');
        }
    );
});
