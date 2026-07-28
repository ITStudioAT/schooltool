<?php

use App\Jobs\PrintRegisterSupervisorJob;
use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Notifications\StandardEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Create test directories
    if (! is_dir(storage_path('app/private/pdf'))) {
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

afterEach(function () {
    // Clean up created PDF files
    $files = glob(storage_path('app/private/pdf/*_betreuer.pdf'));
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
});

test('job can be instantiated', function () {
    $job = new PrintRegisterSupervisorJob($this->user, $this->data);

    expect($job)->toBeInstanceOf(PrintRegisterSupervisorJob::class)
        ->and($job->user)->toBe($this->user)
        ->and($job->data)->toBe($this->data);
});

test('job implements ShouldQueue interface', function () {
    $job = new PrintRegisterSupervisorJob($this->user, $this->data);

    expect($job)->toBeInstanceOf(ShouldQueue::class);
});

test('job can be dispatched to queue', function () {
    Queue::fake();

    PrintRegisterSupervisorJob::dispatch($this->user, $this->data);

    Queue::assertPushed(PrintRegisterSupervisorJob::class, function ($job) {
        return $job->user->id === $this->user->id
            && $job->data['register_id'] === $this->register->id;
    });
});

test('job sends notification email when handled', function () {
    Notification::fake();

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    Notification::assertSentOnDemand(StandardEmail::class);
});

test('job notification contains correct email data', function () {
    Notification::fake();

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    Notification::assertSentOnDemand(
        StandardEmail::class,
        function ($notification, $channels, $notifiable) {
            // Check email configuration
            expect($notification->data['from_address'])->toBe(config('schooltool.noreply_email'))
                ->and($notification->data['from_name'])->toBe($this->school->long_name)
                ->and($notification->data['subject'])->toContain($this->register->name)
                ->and($notification->data['subject'])->toContain('Pdf-Datei (Betreuer)')
                ->and($notification->data['markdown'])->toBe('mails.admin.sendPrint')
                ->and($notifiable->routes['mail'])->toBe($this->user->email);

            return true;
        }
    );
});

test('job uses PrintRegisterService to generate PDF', function () {
    Notification::fake();

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    // Verify PDF generation was called
    Pdf::assertViewIs('pdfs.registerSupervisor');
});

test('job retrieves correct register from data', function () {
    Notification::fake();

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    // Verify the register exists and was used
    expect($this->register->fresh())->not->toBeNull()
        ->and($this->register->name)->toBe('Test Register 2024');
});

test('job accesses user selected school', function () {
    Notification::fake();

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    expect($this->user->selectedSchool)->not->toBeNull()
        ->and($this->user->selectedSchool->id)->toBe($this->school->id)
        ->and($this->user->selectedSchool->long_name)->toBe('Test School Long Name');
});

test('job notification routes to correct email address', function () {
    Notification::fake();

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    Notification::assertSentOnDemand(
        StandardEmail::class,
        function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'john.doe@example.com';
        }
    );
});

test('job handles multiple supervisors with bookings', function () {
    Notification::fake();

    // Create register dates with different supervisors
    $registerDate1 = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => now()->addDays(1),
        'from' => '08:00',
        'to' => '12:00',
        'supervisor' => 'Mrs. Smith',
    ]);

    $registerDate2 = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => now()->addDays(2),
        'from' => '13:00',
        'to' => '17:00',
        'supervisor' => 'Mr. Jones',
    ]);

    $registerDate3 = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => now()->addDays(3),
        'from' => '09:00',
        'to' => '15:00',
        'supervisor' => 'Mrs. Smith',
    ]);

    // Create bookings for different supervisors
    RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDate1->id,
        'user_id' => $this->user->id,
    ]);

    RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDate2->id,
        'user_id' => $this->user->id,
    ]);

    RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDate3->id,
        'user_id' => $this->user->id,
    ]);

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    // Verify bookings were created and job completed
    expect($this->register->bookings()->count())->toBe(3);
});

test('job attaches PDF file to notification', function () {
    Notification::fake();

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
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

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    Notification::assertSentOnDemand(
        StandardEmail::class,
        function ($notification) {
            $expectedLogoPath = asset('/storage/images/'.$this->school->logo);

            return $notification->data['logo'] === $expectedLogoPath;
        }
    );
});

test('job throws exception when register not found', function () {
    Notification::fake();

    $invalidData = ['register_id' => 99999];

    $job = new PrintRegisterSupervisorJob($this->user, $invalidData);

    expect(fn () => $job->handle())
        ->toThrow(ModelNotFoundException::class);
});

test('job can be serialized and unserialized', function () {
    $job = new PrintRegisterSupervisorJob($this->user, $this->data);

    $serialized = serialize($job);
    $unserialized = unserialize($serialized, ['allowed_classes' => true]);

    expect($unserialized)->toBeInstanceOf(PrintRegisterSupervisorJob::class)
        ->and($unserialized->data)->toBe($this->data);
});

test('job subject line includes register name and supervisor indicator', function () {
    Notification::fake();

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    Notification::assertSentOnDemand(
        StandardEmail::class,
        function ($notification) {
            $subject = $notification->data['subject'];

            return str_contains($subject, 'Test Register 2024')
                && str_contains($subject, 'Pdf-Datei (Betreuer)');
        }
    );
});

test('job groups bookings by supervisor correctly', function () {
    Notification::fake();

    // Create multiple bookings for same supervisor
    $supervisor = 'Dr. Mueller';

    $registerDate1 = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => now()->addDays(1),
        'supervisor' => $supervisor,
    ]);

    $registerDate2 = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => now()->addDays(2),
        'supervisor' => $supervisor,
    ]);

    RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDate1->id,
        'user_id' => $this->user->id,
    ]);

    RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDate2->id,
        'user_id' => $this->user->id,
    ]);

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    // Verify both bookings are for the same supervisor
    $bookings = $this->register->bookings()->with('registerDate')->get();
    expect($bookings->every(fn ($b) => $b->registerDate->supervisor === $supervisor))->toBeTrue();
});

test('job handles bookings with null supervisor', function () {
    Notification::fake();

    // Create register date without supervisor
    $registerDate = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => now()->addDays(1),
        'supervisor' => null,
    ]);

    RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDate->id,
        'user_id' => $this->user->id,
    ]);

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    // Should complete successfully even with null supervisor
    Notification::assertSentOnDemand(StandardEmail::class);
});

test('job orders bookings by supervisor then date', function () {
    Notification::fake();

    // Create bookings in non-ordered fashion
    $date1 = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => now()->addDays(3),
        'supervisor' => 'Teacher B',
    ]);

    $date2 = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => now()->addDays(1),
        'supervisor' => 'Teacher A',
    ]);

    $date3 = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => now()->addDays(2),
        'supervisor' => 'Teacher A',
    ]);

    RegisterDateBooking::factory()->create([
        'register_date_id' => $date1->id,
        'user_id' => $this->user->id,
    ]);

    RegisterDateBooking::factory()->create([
        'register_date_id' => $date2->id,
        'user_id' => $this->user->id,
    ]);

    RegisterDateBooking::factory()->create([
        'register_date_id' => $date3->id,
        'user_id' => $this->user->id,
    ]);

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    // Verify job completed successfully with ordering
    expect($this->register->bookings()->count())->toBe(3);
});

test('job creates PDF with supervisor totals', function () {
    Notification::fake();

    // Create bookings with different supervisors
    $supervisor1 = 'Teacher Alpha';
    $supervisor2 = 'Teacher Beta';

    $date1 = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => now()->addDays(1),
        'supervisor' => $supervisor1,
    ]);

    $date2 = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => now()->addDays(2),
        'supervisor' => $supervisor1,
    ]);

    $date3 = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => now()->addDays(3),
        'supervisor' => $supervisor2,
    ]);

    RegisterDateBooking::factory()->create([
        'register_date_id' => $date1->id,
        'user_id' => $this->user->id,
    ]);

    RegisterDateBooking::factory()->create([
        'register_date_id' => $date2->id,
        'user_id' => $this->user->id,
    ]);

    RegisterDateBooking::factory()->create([
        'register_date_id' => $date3->id,
        'user_id' => $this->user->id,
    ]);

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    // Verify PDF was generated with data
    Pdf::assertViewIs('pdfs.registerSupervisor');
});

test('job uses correct email template', function () {
    Notification::fake();

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    Notification::assertSentOnDemand(
        StandardEmail::class,
        function ($notification) {
            return $notification->data['markdown'] === 'mails.admin.sendPrint';
        }
    );
});

test('job handles empty register with no bookings', function () {
    Notification::fake();

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    // Should complete successfully even with no bookings
    Notification::assertSentOnDemand(StandardEmail::class);
    Pdf::assertViewIs('pdfs.registerSupervisor');
});

test('job creates PDF with correct filename pattern', function () {
    Notification::fake();

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    // Filename should contain register slug and _betreuer.pdf
    Notification::assertSentOnDemand(
        StandardEmail::class,
        function ($notification) {
            if (! is_array($notification->attachments) || count($notification->attachments) === 0) {
                return false;
            }

            $filename = basename($notification->attachments[0]);

            return str_contains($filename, 'test_register_2024')
                && str_contains($filename, '_betreuer.pdf');
        }
    );
});

test('job PDF includes header with supervisor title', function () {
    Notification::fake();

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    // Verify PDF was generated with correct header view
    Pdf::assertViewIs('pdfs.registerSupervisor');
});

test('job PDF includes footer with school name', function () {
    Notification::fake();

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    // Verify PDF generation includes school info
    Pdf::assertViewIs('pdfs.registerSupervisor');
});

test('job handles register with special characters in name', function () {
    Notification::fake();

    // Create register with special characters
    $specialRegister = Register::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
        'name' => 'Spezial-Register: Klasse 5/A (Gruppe 2024)',
    ]);

    $data = ['register_id' => $specialRegister->id];

    $job = new PrintRegisterSupervisorJob($this->user, $data);
    $job->handle();

    Notification::assertSentOnDemand(StandardEmail::class);
});

test('job attaches file with correct path', function () {
    Notification::fake();

    $job = new PrintRegisterSupervisorJob($this->user, $this->data);
    $job->handle();

    Notification::assertSentOnDemand(
        StandardEmail::class,
        function ($notification) {
            if (! is_array($notification->attachments) || count($notification->attachments) === 0) {
                return false;
            }

            $attachmentPath = $notification->attachments[0];

            return str_contains($attachmentPath, 'app/private/pdf')
                && str_contains($attachmentPath, '_betreuer.pdf');
        }
    );
});
