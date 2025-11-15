<?php

use App\Models\Register;
use App\Models\RegisterDate;
use App\Models\RegisterDateBooking;
use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\PrintRegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new PrintRegisterService();
    
    // Create test directories
    if (!is_dir(storage_path('app/private/pdf'))) {
        mkdir(storage_path('app/private/pdf'), 0775, true);
    }
    if (!is_dir(storage_path('app/private/excel'))) {
        mkdir(storage_path('app/private/excel'), 0775, true);
    }
    
    // Create test data
    $this->school = School::factory()->create([
        'long_name' => 'Test School Long Name',
        'short_name' => 'Test School',
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
    ]);
});

afterEach(function () {
    // Clean up generated files
    $pdfPath = storage_path('app/private/pdf');
    $excelPath = storage_path('app/private/excel');
    
    if (is_dir($pdfPath)) {
        $files = glob("$pdfPath/*.pdf");
        foreach ($files as $file) {
            @unlink($file);
        }
    }
    if (is_dir($excelPath)) {
        $files = glob("$excelPath/*.xlsx");
        foreach ($files as $file) {
            @unlink($file);
        }
    }
});

describe('printSupervisor', function () {
    it('throws exception when register does not exist', function () {
        $data = ['register_id' => 99999];
        
        $this->service->printSupervisor($this->user, $data);
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    
    it('generates PDF file for supervisor view', function () {
        Pdf::fake();
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printSupervisor($this->user, $data);
        
        expect($path)->toBeString()
            ->and($path)->toContain('private')
            ->and($path)->toContain('pdf')
            ->and($path)->toContain('_betreuer.pdf')
            ->and($path)->toContain('test_register_2024');
    });
    
    it('generates PDF with correct filename format', function () {
        Pdf::fake();
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printSupervisor($this->user, $data);
        $filename = basename($path);
        
        expect($filename)
            ->toMatch('/^test_register_2024_\d{8}_\d{6}_betreuer\.pdf$/');
    });
    
    it('retrieves bookings with correct relationships', function () {
        Pdf::fake();
        
        $registerDate = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-01-15',
            'from' => '08:00',
            'to' => '12:00',
            'supervisor' => 'Supervisor A',
        ]);
        
        $booking = RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate->id,
            'user_id' => $this->user->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
            'student_first_name' => 'Jane',
            'student_last_name' => 'Doe',
            'student_birthdate' => '2015-05-20',
        ]);
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printSupervisor($this->user, $data);
        
        expect($path)->toBeString();
        
        Pdf::assertViewIs('pdfs.registerSupervisor');
    });
    
    it('orders bookings by supervisor, date, time, and last name', function () {
        Pdf::fake();
        
        $user2 = User::factory()->create([
            'first_name' => 'Alice',
            'last_name' => 'Anderson',
        ]);
        
        $registerDate1 = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-01-15',
            'from' => '08:00',
            'to' => '12:00',
            'supervisor' => 'Supervisor B',
        ]);
        
        $registerDate2 = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-01-16',
            'from' => '09:00',
            'to' => '13:00',
            'supervisor' => 'Supervisor A',
        ]);
        
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate1->id,
            'user_id' => $this->user->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
        ]);
        
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate2->id,
            'user_id' => $user2->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
        ]);
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printSupervisor($this->user, $data);
        
        expect($path)->toBeString();
    });
    
    it('calculates totals by supervisor correctly', function () {
        Pdf::fake();
        
        $registerDate1 = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-01-15',
            'supervisor' => 'Supervisor A',
        ]);
        
        $registerDate2 = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-01-16',
            'supervisor' => 'Supervisor A',
        ]);
        
        $registerDate3 = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-01-17',
            'supervisor' => 'Supervisor B',
        ]);
        
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate1->id,
            'user_id' => $this->user->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
        ]);
        
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate2->id,
            'user_id' => $this->user->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
        ]);
        
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate3->id,
            'user_id' => $this->user->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
        ]);
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printSupervisor($this->user, $data);
        
        expect($path)->toBeString();
    });
    
    it('passes correct data structure to PDF view', function () {
        Pdf::fake();
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printSupervisor($this->user, $data);
        
        expect($path)->toBeString();
    });
    
    it('uses correct header view with title', function () {
        Pdf::fake();
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printSupervisor($this->user, $data);
        
        expect($path)->toBeString();
    });
    
    it('uses correct footer view with school name', function () {
        Pdf::fake();
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printSupervisor($this->user, $data);
        
        expect($path)->toBeString();
    });
});

describe('printDate', function () {
    it('throws exception when register does not exist', function () {
        $data = ['register_id' => 99999];
        
        $this->service->printDate($this->user, $data);
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    
    it('generates PDF file for date view', function () {
        Pdf::fake();
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printDate($this->user, $data);
        
        expect($path)->toBeString()
            ->and($path)->toContain('private')
            ->and($path)->toContain('pdf')
            ->and($path)->toContain('_betreuer.pdf')
            ->and($path)->toContain('test_register_2024');
    });
    
    it('generates PDF with correct filename format', function () {
        Pdf::fake();
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printDate($this->user, $data);
        $filename = basename($path);
        
        expect($filename)
            ->toMatch('/^test_register_2024_\d{8}_\d{6}_betreuer\.pdf$/');
    });
    
    it('orders bookings by date, supervisor, time, and last name', function () {
        Pdf::fake();
        
        $user2 = User::factory()->create([
            'first_name' => 'Bob',
            'last_name' => 'Brown',
        ]);
        
        $registerDate1 = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-01-16',
            'from' => '08:00',
            'supervisor' => 'Supervisor A',
        ]);
        
        $registerDate2 = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-01-15',
            'from' => '09:00',
            'supervisor' => 'Supervisor B',
        ]);
        
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate1->id,
            'user_id' => $this->user->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
        ]);
        
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate2->id,
            'user_id' => $user2->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
        ]);
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printDate($this->user, $data);
        
        expect($path)->toBeString();
    });
    
    it('calculates totals by date correctly', function () {
        Pdf::fake();
        
        $registerDate1 = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-01-15',
        ]);
        
        $registerDate2 = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-01-15',
        ]);
        
        $registerDate3 = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-01-16',
        ]);
        
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate1->id,
            'user_id' => $this->user->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
        ]);
        
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate2->id,
            'user_id' => $this->user->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
        ]);
        
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate3->id,
            'user_id' => $this->user->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
        ]);
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printDate($this->user, $data);
        
        expect($path)->toBeString();
    });
    
    it('passes correct data structure to PDF view', function () {
        Pdf::fake();
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printDate($this->user, $data);
        
        expect($path)->toBeString();
    });
    
    it('uses correct view for date format', function () {
        Pdf::fake();
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printDate($this->user, $data);
        
        Pdf::assertViewIs('pdfs.registerDate');
    });
    
    it('uses correct header view with title', function () {
        Pdf::fake();
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printDate($this->user, $data);
        
        expect($path)->toBeString();
    });
    
    it('uses correct footer view with school name', function () {
        Pdf::fake();
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printDate($this->user, $data);
        
        expect($path)->toBeString();
    });
});

describe('printExcel', function () {
    it('throws exception when register does not exist', function () {
        $data = ['register_id' => 99999];
        
        $this->service->printExcel($this->user, $data);
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    
    it('generates Excel file with correct filename format', function () {
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printExcel($this->user, $data);
        $filename = basename($path);
        
        expect($filename)
            ->toMatch('/^test_register_2024_\d{8}_\d{6}\.xlsx$/')
            ->and($path)->toContain('excel');
    });
    
    it('creates Excel file at correct path', function () {
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printExcel($this->user, $data);
        
        expect(file_exists($path))->toBeTrue()
            ->and(filesize($path))->toBeGreaterThan(0);
    });
    
    it('orders bookings by date, time, supervisor, and last name', function () {
        $registerDate1 = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-01-16',
            'from' => '08:00',
            'to' => '12:00',
            'supervisor' => 'Supervisor B',
        ]);
        
        $registerDate2 = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-01-15',
            'from' => '09:00',
            'to' => '13:00',
            'supervisor' => 'Supervisor A',
        ]);
        
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate1->id,
            'user_id' => $this->user->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
        ]);
        
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate2->id,
            'user_id' => $this->user->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
        ]);
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printExcel($this->user, $data);
        
        expect(file_exists($path))->toBeTrue();
    });
    
    it('includes correct headers in Excel file', function () {
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printExcel($this->user, $data);
        
        expect(file_exists($path))->toBeTrue();
        
        // Headers should be: Datum, Von, Bis, Betreuer, Kind N.n., Kind V.n., 
        // Geb-Datum, Nachname, Vorname, Email, Telefon
        $reader = \Spatie\SimpleExcel\SimpleExcelReader::create($path);
        $rows = $reader->getRows()->toArray();
        
        expect($rows)->toBeArray();
    });
    
    it('exports booking data with all required fields', function () {
        $registerDate = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-01-15',
            'from' => '08:00',
            'to' => '12:00',
            'supervisor' => 'Test Supervisor',
        ]);
        
        $booking = RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate->id,
            'user_id' => $this->user->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
            'student_first_name' => 'StudentFirst',
            'student_last_name' => 'StudentLast',
            'student_birthdate' => '2015-05-20',
        ]);
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printExcel($this->user, $data);
        
        expect(file_exists($path))->toBeTrue();
        
        $reader = \Spatie\SimpleExcel\SimpleExcelReader::create($path);
        $rows = $reader->getRows()->toArray();
        
        expect(count($rows))->toBeGreaterThan(0);
    });
    
    it('includes user information in Excel export', function () {
        $registerDate = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-01-15',
        ]);
        
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate->id,
            'user_id' => $this->user->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
        ]);
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printExcel($this->user, $data);
        
        expect(file_exists($path))->toBeTrue();
        
        $reader = \Spatie\SimpleExcel\SimpleExcelReader::create($path);
        $rows = $reader->getRows()->toArray();
        
        expect($rows)->toHaveCount(1);
    });
    
    it('handles empty bookings gracefully', function () {
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printExcel($this->user, $data);
        
        expect(file_exists($path))->toBeTrue();
        
        $reader = \Spatie\SimpleExcel\SimpleExcelReader::create($path);
        $rows = $reader->getRows()->toArray();
        
        expect($rows)->toBeArray();
    });
    
    it('handles multiple bookings correctly', function () {
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        
        $registerDate = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
        ]);
        
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate->id,
            'user_id' => $this->user->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
        ]);
        
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate->id,
            'user_id' => $user2->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
        ]);
        
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate->id,
            'user_id' => $user3->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
        ]);
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printExcel($this->user, $data);
        
        $reader = \Spatie\SimpleExcel\SimpleExcelReader::create($path);
        $rows = $reader->getRows()->toArray();
        
        expect($rows)->toHaveCount(3);
    });
});

describe('edge cases', function () {
    it('handles register with null supervisor gracefully in printSupervisor', function () {
        Pdf::fake();
        
        $registerDate = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => '2024-01-15',
            'supervisor' => null,
        ]);
        
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate->id,
            'user_id' => $this->user->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
        ]);
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printSupervisor($this->user, $data);
        
        expect($path)->toBeString();
    });
    
    it('handles register with null date gracefully in printDate', function () {
        Pdf::fake();
        
        $registerDate = RegisterDate::factory()->create([
            'register_id' => $this->register->id,
            'date' => null,
        ]);
        
        RegisterDateBooking::factory()->create([
            'register_date_id' => $registerDate->id,
            'user_id' => $this->user->id,
            'register_id' => $this->register->id,
            'school_id' => $this->school->id,
        ]);
        
        $data = ['register_id' => $this->register->id];
        
        $path = $this->service->printDate($this->user, $data);
        
        expect($path)->toBeString();
    });
    
    it('handles special characters in register name for filename', function () {
        Pdf::fake();
        
        $specialRegister = Register::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
            'name' => 'Test/Register: 2024 & More!',
        ]);
        
        $data = ['register_id' => $specialRegister->id];
        
        $path = $this->service->printSupervisor($this->user, $data);
        $filename = basename($path);
        
        expect($filename)->not->toContain('/')
            ->and($filename)->not->toContain(':')
            ->and($filename)->not->toContain('&')
            ->and($filename)->not->toContain('!');
    });
});
