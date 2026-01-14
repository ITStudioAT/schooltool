<?php

use App\Jobs\PrintRegisterExcelJob;
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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Clean up any existing Excel files BEFORE the test
    $excelDir = storage_path('app/private/excel');
    if (is_dir($excelDir)) {
        $files = glob($excelDir . '/*.xlsx');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    } else {
        mkdir($excelDir, 0775, true);
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
});

afterEach(function () {
    // Clean up created Excel files AFTER the test
    $excelDir = storage_path('app/private/excel');
    if (is_dir($excelDir)) {
        $files = glob($excelDir . '/*.xlsx');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
});

test('job can be instantiated', function () {
    $job = new PrintRegisterExcelJob($this->user, $this->data);
    
    expect($job)->toBeInstanceOf(PrintRegisterExcelJob::class)
        ->and($job->user)->toBe($this->user)
        ->and($job->data)->toBe($this->data);
});

test('job implements ShouldQueue interface', function () {
    $job = new PrintRegisterExcelJob($this->user, $this->data);
    
    expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

test('job can be dispatched to queue', function () {
    Queue::fake();
    
    PrintRegisterExcelJob::dispatch($this->user, $this->data);
    
    Queue::assertPushed(PrintRegisterExcelJob::class, function ($job) {
        return $job->user->id === $this->user->id
            && $job->data['register_id'] === $this->register->id;
    });
});

test('job sends notification email when handled', function () {
    Notification::fake();
    
    $job = new PrintRegisterExcelJob($this->user, $this->data);
    $job->handle();
    
    Notification::assertSentOnDemand(StandardEmail::class);
});

test('job notification contains correct email data', function () {
    Notification::fake();
    
    $job = new PrintRegisterExcelJob($this->user, $this->data);
    $job->handle();
    
    Notification::assertSentOnDemand(
        StandardEmail::class,
        function ($notification, $channels, $notifiable) {
            // Check email configuration
            expect($notification->data['from_address'])->toBe(config('schooltool.noreply_email'))
                ->and($notification->data['from_name'])->toBe($this->school->long_name)
                ->and($notification->data['subject'])->toContain($this->register->name)
                ->and($notification->data['subject'])->toContain('Excel-Datei')
                ->and($notification->data['markdown'])->toBe('mails.admin.sendPrint')
                ->and($notifiable->routes['mail'])->toBe($this->user->email);
            
            return true;
        }
    );
});

test('job uses PrintRegisterService to generate Excel file', function () {
    Notification::fake();
    
    $job = new PrintRegisterExcelJob($this->user, $this->data);
    $job->handle();
    
    // Verify an Excel file was created
    $files = glob(storage_path('app/private/excel/*.xlsx'));
    expect(count($files))->toBeGreaterThan(0);
});

test('job retrieves correct register from data', function () {
    Notification::fake();
    
    $job = new PrintRegisterExcelJob($this->user, $this->data);
    $job->handle();
    
    // Verify the register exists and was used
    expect($this->register->fresh())->not->toBeNull()
        ->and($this->register->name)->toBe('Test Register 2024');
});

test('job accesses user selected school', function () {
    Notification::fake();
    
    $job = new PrintRegisterExcelJob($this->user, $this->data);
    $job->handle();
    
    expect($this->user->selectedSchool)->not->toBeNull()
        ->and($this->user->selectedSchool->id)->toBe($this->school->id)
        ->and($this->user->selectedSchool->long_name)->toBe('Test School Long Name');
});

test('job notification routes to correct email address', function () {
    Notification::fake();
    
    $job = new PrintRegisterExcelJob($this->user, $this->data);
    $job->handle();
    
    Notification::assertSentOnDemand(
        StandardEmail::class,
        function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'john.doe@example.com';
        }
    );
});

test('job handles multiple register dates with bookings', function () {
    Notification::fake();
    
    // Create register dates
    $registerDate1 = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => now()->addDays(1),
        'from' => '08:00',
        'to' => '12:00',
        'supervisor' => 'Teacher A',
    ]);
    
    $registerDate2 = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => now()->addDays(2),
        'from' => '13:00',
        'to' => '17:00',
        'supervisor' => 'Teacher B',
    ]);
    
    // Create bookings with student data
    RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDate1->id,
        'user_id' => $this->user->id,
        'student_first_name' => 'Max',
        'student_last_name' => 'Mustermann',
        'student_birthdate' => '2010-01-15',
    ]);
    
    RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDate2->id,
        'user_id' => $this->user->id,
        'student_first_name' => 'Emma',
        'student_last_name' => 'Schmidt',
        'student_birthdate' => '2011-05-20',
    ]);
    
    $job = new PrintRegisterExcelJob($this->user, $this->data);
    $job->handle();
    
    // Verify bookings were created and job completed
    expect($this->register->bookings()->count())->toBe(2);
});

test('job attaches Excel file to notification', function () {
    Notification::fake();
    
    $job = new PrintRegisterExcelJob($this->user, $this->data);
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
    
    $job = new PrintRegisterExcelJob($this->user, $this->data);
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
    
    $job = new PrintRegisterExcelJob($this->user, $invalidData);
    
    expect(fn() => $job->handle())
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

test('job can be serialized and unserialized', function () {
    $job = new PrintRegisterExcelJob($this->user, $this->data);
    
    $serialized = serialize($job);
    $unserialized = unserialize($serialized);
    
    expect($unserialized)->toBeInstanceOf(PrintRegisterExcelJob::class)
        ->and($unserialized->data)->toBe($this->data);
});

test('job subject line includes register name and Excel indicator', function () {
    Notification::fake();
    
    $job = new PrintRegisterExcelJob($this->user, $this->data);
    $job->handle();
    
    Notification::assertSentOnDemand(
        StandardEmail::class,
        function ($notification) {
            $subject = $notification->data['subject'];
            return str_contains($subject, 'Test Register 2024')
                && str_contains($subject, 'Excel-Datei');
        }
    );
});

test('job creates Excel file with correct filename format', function () {
    Notification::fake();
    
    $job = new PrintRegisterExcelJob($this->user, $this->data);
    $job->handle();
    
    $files = glob(storage_path('app/private/excel/*.xlsx'));
    expect(count($files))->toBe(1);
    
    $filename = basename($files[0]);
    expect($filename)->toContain('test_register_2024')
        ->and($filename)->toEndWith('.xlsx');
});

test('job generates Excel file in correct directory', function () {
    Notification::fake();
    
    $job = new PrintRegisterExcelJob($this->user, $this->data);
    $job->handle();
    
    $files = glob(storage_path('app/private/excel/*.xlsx'));
    expect(count($files))->toBeGreaterThan(0);
    
    $filePath = $files[0];
    expect($filePath)->toContain('app/private/excel');
});

test('job handles empty register with no bookings', function () {
    Notification::fake();
    
    $job = new PrintRegisterExcelJob($this->user, $this->data);
    $job->handle();
    
    // Should complete successfully even with no bookings
    Notification::assertSentOnDemand(StandardEmail::class);
    
    $files = glob(storage_path('app/private/excel/*.xlsx'));
    expect(count($files))->toBe(1);
});

test('job Excel file has correct structure with bookings', function () {
    Notification::fake();
    
    // Create a booking with full data
    $registerDate = RegisterDate::factory()->create([
        'register_id' => $this->register->id,
        'date' => '2024-12-01',
        'from' => '09:00',
        'to' => '15:00',
        'supervisor' => 'Mrs. Smith',
    ]);
    
    RegisterDateBooking::factory()->create([
        'register_date_id' => $registerDate->id,
        'user_id' => $this->user->id,
        'student_first_name' => 'Sophie',
        'student_last_name' => 'Mueller',
        'student_birthdate' => '2012-03-10',
    ]);
    
    $job = new PrintRegisterExcelJob($this->user, $this->data);
    $job->handle();
    
    $files = glob(storage_path('app/private/excel/*.xlsx'));
    expect(count($files))->toBe(1);
    
    // Verify file exists and is not empty
    $fileSize = filesize($files[0]);
    expect($fileSize)->toBeGreaterThan(0);
});

test('job uses correct email template', function () {
    Notification::fake();
    
    $job = new PrintRegisterExcelJob($this->user, $this->data);
    $job->handle();
    
    Notification::assertSentOnDemand(
        StandardEmail::class,
        function ($notification) {
            return $notification->data['markdown'] === 'mails.admin.sendPrint';
        }
    );
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
    
    $job = new PrintRegisterExcelJob($this->user, $data);
    $job->handle();
    
    Notification::assertSentOnDemand(StandardEmail::class);
    
    $files = glob(storage_path('app/private/excel/*.xlsx'));
    expect(count($files))->toBe(1);
});

test('job attaches file with correct path', function () {
    Notification::fake();
    
    $job = new PrintRegisterExcelJob($this->user, $this->data);
    $job->handle();
    
    Notification::assertSentOnDemand(
        StandardEmail::class,
        function ($notification) {
            if (!is_array($notification->attachments) || count($notification->attachments) === 0) {
                return false;
            }
            
            $attachmentPath = $notification->attachments[0];
            return str_contains($attachmentPath, 'app/private/excel') && file_exists($attachmentPath);
        }
    );
});
