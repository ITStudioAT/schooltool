<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Services\LicenceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new LicenceService();
});

describe('selectableSchoolLicences', function () {
    it('returns all licences for a given school', function () {
        $school = School::factory()->create();
        $licence1 = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        $licence2 = Licence::create(['name' => 'app2', 'long_name' => 'Application 2']);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence1->id,
            'valid_until' => now()->addYear(),
        ]);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence2->id,
            'valid_until' => now()->addMonths(6),
        ]);

        $result = $this->service->selectableSchoolLicences($school);

        expect($result)->toHaveCount(2);
    });

    it('returns empty collection when school has no licences', function () {
        $school = School::factory()->create();

        $result = $this->service->selectableSchoolLicences($school);

        expect($result)->toBeEmpty();
    });

    it('only returns licences for the specified school', function () {
        $school1 = School::factory()->create();
        $school2 = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        
        SchoolLicence::create([
            'school_id' => $school1->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ]);
        
        SchoolLicence::create([
            'school_id' => $school2->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ]);

        $result = $this->service->selectableSchoolLicences($school1);

        expect($result)->toHaveCount(1)
            ->and($result->first()->school_id)->toBe($school1->id);
    });
});

describe('isLicenceValid', function () {
    it('returns true when licence is valid and has no expiration date', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => null,
        ]);

        $result = $this->service->isLicenceValid($school, 'app1');

        expect($result)->toBeTrue();
    });

    it('returns true when licence is valid and expires in the future', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ]);

        $result = $this->service->isLicenceValid($school, 'app1');

        expect($result)->toBeTrue();
    });

    it('returns false when school does not exist', function () {
        $result = $this->service->isLicenceValid(null, 'app1');

        expect($result)->toBeFalse();
    });

    it('returns false when licence does not exist', function () {
        $school = School::factory()->create();

        $result = $this->service->isLicenceValid($school, 'nonexistent_app');

        expect($result)->toBeFalse();
    });

    it('returns false when school does not have the licence', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        $result = $this->service->isLicenceValid($school, 'app1');

        expect($result)->toBeFalse();
    });

    it('returns false when licence has expired', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        
        // Create a licence that expired 2 days ago to avoid any edge cases
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => Carbon::now()->subDays(2),
        ]);

        $result = $this->service->isLicenceValid($school, 'app1');

        expect($result)->toBeFalse();
    });

    it('returns true when licence expires today but in the future', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        
        // Create a licence that expires at end of today (still in future)
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => Carbon::today()->endOfDay(),
        ]);

        $result = $this->service->isLicenceValid($school, 'app1');

        expect($result)->toBeTrue();
    });

    it('returns true when licence expires tomorrow', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addDay(),
        ]);

        $result = $this->service->isLicenceValid($school, 'app1');

        expect($result)->toBeTrue();
    });
});

describe('schoolAddLicence', function () {
    it('creates a new school licence when it does not exist', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        
        $data = [
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ];

        $result = $this->service->schoolAddLicence($school, $data);

        expect($result)->toBeInstanceOf(SchoolLicence::class)
            ->and($result->school_id)->toBe($school->id)
            ->and($result->licence_id)->toBe($licence->id)
            ->and($result->valid_until)->not->toBeNull();
    });

    it('updates existing school licence when it already exists', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        
        $schoolLicence = SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addMonth(),
        ]);

        $data = [
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ];

        $result = $this->service->schoolAddLicence($school, $data);

        expect($result->id)->toBe($schoolLicence->id)
            ->and(SchoolLicence::count())->toBe(1)
            ->and($result->valid_until->format('Y-m-d'))->toBe(now()->addYear()->format('Y-m-d'));
    });

    it('handles null valid_until date', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        
        $data = [
            'licence_id' => $licence->id,
            'valid_until' => null,
        ];

        $result = $this->service->schoolAddLicence($school, $data);

        expect($result->valid_until)->toBeNull();
    });

    it('accepts school array with id', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        
        $data = [
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ];

        $result = $this->service->schoolAddLicence(['id' => $school->id], $data);

        expect($result)->toBeInstanceOf(SchoolLicence::class)
            ->and($result->school_id)->toBe($school->id);
    });
});

describe('deleteLicences', function () {
    it('deletes licences when they are not assigned to any school', function () {
        $licence1 = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        $licence2 = Licence::create(['name' => 'app2', 'long_name' => 'Application 2']);

        $this->service->deleteLicences([$licence1->id, $licence2->id]);

        expect(Licence::count())->toBe(0);
    });

    it('deletes single licence by id', function () {
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        $this->service->deleteLicences([$licence->id]);

        expect(Licence::find($licence->id))->toBeNull();
    });

    it('aborts when trying to delete licence assigned to a school', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ]);

        $this->service->deleteLicences([$licence->id]);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Mindest eine Lizenz ist noch einer Schule zugeordnet');

    it('aborts when at least one licence in the array is assigned to a school', function () {
        $school = School::factory()->create();
        $licence1 = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        $licence2 = Licence::create(['name' => 'app2', 'long_name' => 'Application 2']);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence1->id,
            'valid_until' => now()->addYear(),
        ]);

        $this->service->deleteLicences([$licence1->id, $licence2->id]);
    })->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    it('does not delete any licences when one is assigned', function () {
        $school = School::factory()->create();
        $licence1 = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        $licence2 = Licence::create(['name' => 'app2', 'long_name' => 'Application 2']);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence1->id,
            'valid_until' => now()->addYear(),
        ]);

        try {
            $this->service->deleteLicences([$licence1->id, $licence2->id]);
        } catch (\Exception $e) {
            // Expected to throw
        }

        expect(Licence::count())->toBe(2);
    });
});

describe('checkLicence', function () {
    it('returns success status when licence is valid', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ]);

        $result = $this->service->checkLicence($school, 'app1');

        expect($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('&licence=app1');
    });

    it('returns error when licence has no expiration date set', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        
        // Note: checkLicence uses Carbon::parse() which treats null as "now" in the past
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => null,
        ]);

        $result = $this->service->checkLicence($school, 'app1');

        expect($result['status'])->toBe('error')
            ->and($result['msg'])->toBe('Die Lizenz für die App ist abgelaufen.');
    });

    it('returns error when licence does not exist', function () {
        $school = School::factory()->create();

        $result = $this->service->checkLicence($school, 'nonexistent');

        expect($result['status'])->toBe('error')
            ->and($result['msg'])->toBe('Die Lizenz konnte nicht gefunden werden.');
    });

    it('returns error when school does not have the licence', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);

        $result = $this->service->checkLicence($school, 'app1');

        expect($result['status'])->toBe('error')
            ->and($result['msg'])->toBe('Die Schule hat für die App keine Lizenz.');
    });

    it('returns error when licence has expired', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->subDay(),
        ]);

        $result = $this->service->checkLicence($school, 'app1');

        expect($result['status'])->toBe('error')
            ->and($result['msg'])->toBe('Die Lizenz für die App ist abgelaufen.');
    });

    it('returns error when licence expires today', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->startOfDay(),
        ]);

        $result = $this->service->checkLicence($school, 'app1');

        expect($result['status'])->toBe('error')
            ->and($result['msg'])->toBe('Die Lizenz für die App ist abgelaufen.');
    });

    it('includes licence name in redirect parameter', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'custom_app', 'long_name' => 'Custom Application']);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addYear(),
        ]);

        $result = $this->service->checkLicence($school, 'custom_app');

        expect($result['redirect'])->toBe('&licence=custom_app');
    });

    it('handles edge case where valid_until is exactly one second in future', function () {
        $school = School::factory()->create();
        $licence = Licence::create(['name' => 'app1', 'long_name' => 'Application 1']);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => now()->addSecond(),
        ]);

        $result = $this->service->checkLicence($school, 'app1');

        expect($result['status'])->toBe('ok');
    });
});
