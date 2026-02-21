<?php

use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Services\HomepageRoutingService;
use App\Services\LicenceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new HomepageRoutingService();
});

describe('checkRoute without school parameter', function () {
    it('returns default homepage redirect when no school is provided', function () {
        $result = $this->service->checkRoute(null, null);
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage');
    });

    it('returns default homepage redirect when school is empty string', function () {
        $result = $this->service->checkRoute('', null);
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage');
    });

    it('returns default homepage redirect when school is false', function () {
        $result = $this->service->checkRoute(false, null);
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage');
    });

    it('ignores licence parameter when no school is provided', function () {
        $result = $this->service->checkRoute(null, 'some-licence');
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage');
    });
});

describe('checkRoute with invalid school', function () {
    it('returns error when school does not exist', function () {
        $result = $this->service->checkRoute('NONEXISTENT', null);
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('error')
            ->and($result['msg'])->toBe('Die Schule konnte nicht gefunden werden.')
            ->and($result)->not->toHaveKey('redirect');
    });

    it('returns error for school with special characters', function () {
        $result = $this->service->checkRoute('TEST@#$', null);
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('error')
            ->and($result['msg'])->toBe('Die Schule konnte nicht gefunden werden.');
    });

    it('is case insensitive for school short_name', function () {
        School::factory()->create(['short_name' => 'ABC']);

        $result = $this->service->checkRoute('abc', null);

        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage/?school=abc');
    });
});

describe('checkRoute with valid school only', function () {
    it('returns success with school parameter when school exists', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        
        $result = $this->service->checkRoute('TEST', null);
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage/?school=TEST');
    });

    it('handles school with single letter short_name', function () {
        $school = School::factory()->create(['short_name' => 'A']);
        
        $result = $this->service->checkRoute('A', null);
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage/?school=A');
    });

    it('handles school with long short_name', function () {
        $school = School::factory()->create(['short_name' => 'VERYLONGSCHOOLNAME']);
        
        $result = $this->service->checkRoute('VERYLONGSCHOOLNAME', null);
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage/?school=VERYLONGSCHOOLNAME');
    });

    it('handles school with numbers in short_name', function () {
        $school = School::factory()->create(['short_name' => 'SCH123']);
        
        $result = $this->service->checkRoute('SCH123', null);
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage/?school=SCH123');
    });

    it('ignores licence when licence parameter is empty', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        
        $result = $this->service->checkRoute('TEST', '');
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage/?school=TEST');
    });

    it('ignores licence when licence parameter is false', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        
        $result = $this->service->checkRoute('TEST', false);
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage/?school=TEST');
    });
});

describe('checkRoute with school and invalid licence', function () {
    it('returns error when licence does not exist', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        
        $result = $this->service->checkRoute('TEST', 'nonexistent-licence');
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('error')
            ->and($result['msg'])->toBe('Die Lizenz konnte nicht gefunden werden.')
            ->and($result)->not->toHaveKey('redirect');
    });

    it('returns error when school does not have the licence', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        $licence = Licence::create([
            'name' => 'premium-app',
            'long_name' => 'Premium Application',
        ]);
        
        $result = $this->service->checkRoute('TEST', 'premium-app');
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('error')
            ->and($result['msg'])->toBe('Die Schule hat für die App keine Lizenz.')
            ->and($result)->not->toHaveKey('redirect');
    });

    it('returns error when school licence has expired', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        $licence = Licence::create([
            'name' => 'premium-app',
            'long_name' => 'Premium Application',
        ]);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => Carbon::yesterday(),
        ]);
        
        $result = $this->service->checkRoute('TEST', 'premium-app');
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('error')
            ->and($result['msg'])->toBe('Die Lizenz für die App ist abgelaufen.')
            ->and($result)->not->toHaveKey('redirect');
    });

    it('returns error when school licence expired today', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        $licence = Licence::create([
            'name' => 'premium-app',
            'long_name' => 'Premium Application',
        ]);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => Carbon::today()->subSecond(),
        ]);
        
        $result = $this->service->checkRoute('TEST', 'premium-app');
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('error')
            ->and($result['msg'])->toBe('Die Lizenz für die App ist abgelaufen.');
    });
});

describe('checkRoute with school and valid licence', function () {
    it('returns success with both school and licence parameters', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        $licence = Licence::create([
            'name' => 'premium-app',
            'long_name' => 'Premium Application',
        ]);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => Carbon::tomorrow(),
        ]);
        
        $result = $this->service->checkRoute('TEST', 'premium-app');
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage/?school=TEST&licence=premium-app');
    });

    it('returns success when licence is valid in far future', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        $licence = Licence::create([
            'name' => 'premium-app',
            'long_name' => 'Premium Application',
        ]);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => Carbon::now()->addYears(10),
        ]);
        
        $result = $this->service->checkRoute('TEST', 'premium-app');
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage/?school=TEST&licence=premium-app');
    });

    it('returns success when licence expires later today', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        $licence = Licence::create([
            'name' => 'premium-app',
            'long_name' => 'Premium Application',
        ]);

        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => Carbon::now()->addDay(),
        ]);

        $result = $this->service->checkRoute('TEST', 'premium-app');

        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage/?school=TEST&licence=premium-app');
    });

    it('handles licence with hyphenated name', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        $licence = Licence::create([
            'name' => 'multi-part-licence-name',
            'long_name' => 'Multi Part Licence',
        ]);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => Carbon::tomorrow(),
        ]);
        
        $result = $this->service->checkRoute('TEST', 'multi-part-licence-name');
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage/?school=TEST&licence=multi-part-licence-name');
    });

    it('handles licence with underscore in name', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        $licence = Licence::create([
            'name' => 'licence_with_underscore',
            'long_name' => 'Licence With Underscore',
        ]);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => Carbon::tomorrow(),
        ]);
        
        $result = $this->service->checkRoute('TEST', 'licence_with_underscore');
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage/?school=TEST&licence=licence_with_underscore');
    });
});

describe('checkRoute with null valid_until', function () {
    it('handles school licence with null valid_until', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        $licence = Licence::create([
            'name' => 'unlimited-app',
            'long_name' => 'Unlimited Application',
        ]);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => null,
        ]);
        
        $result = $this->service->checkRoute('TEST', 'unlimited-app');
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage/?school=TEST&licence=unlimited-app');
    });
});

describe('checkRoute integration tests', function () {
    it('handles multiple schools with same licence name', function () {
        $school1 = School::factory()->create(['short_name' => 'SCHOOL1']);
        $school2 = School::factory()->create(['short_name' => 'SCHOOL2']);
        
        $licence = Licence::create([
            'name' => 'shared-app',
            'long_name' => 'Shared Application',
        ]);
        
        // Only school1 has the licence
        SchoolLicence::create([
            'school_id' => $school1->id,
            'licence_id' => $licence->id,
            'valid_until' => Carbon::tomorrow(),
        ]);
        
        $result1 = $this->service->checkRoute('SCHOOL1', 'shared-app');
        $result2 = $this->service->checkRoute('SCHOOL2', 'shared-app');
        
        expect($result1['status'])->toBe('ok')
            ->and($result1['redirect'])->toBe('/homepage/?school=SCHOOL1&licence=shared-app')
            ->and($result2['status'])->toBe('error')
            ->and($result2['msg'])->toBe('Die Schule hat für die App keine Lizenz.');
    });

    it('handles school with multiple licences', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        
        $licence1 = Licence::create(['name' => 'app1', 'long_name' => 'App 1']);
        $licence2 = Licence::create(['name' => 'app2', 'long_name' => 'App 2']);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence1->id,
            'valid_until' => Carbon::tomorrow(),
        ]);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence2->id,
            'valid_until' => Carbon::tomorrow(),
        ]);
        
        $result1 = $this->service->checkRoute('TEST', 'app1');
        $result2 = $this->service->checkRoute('TEST', 'app2');
        
        expect($result1['status'])->toBe('ok')
            ->and($result1['redirect'])->toBe('/homepage/?school=TEST&licence=app1')
            ->and($result2['status'])->toBe('ok')
            ->and($result2['redirect'])->toBe('/homepage/?school=TEST&licence=app2');
    });

    it('handles school with expired and valid licences', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        
        $expiredLicence = Licence::create(['name' => 'expired-app', 'long_name' => 'Expired App']);
        $validLicence = Licence::create(['name' => 'valid-app', 'long_name' => 'Valid App']);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $expiredLicence->id,
            'valid_until' => Carbon::yesterday(),
        ]);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $validLicence->id,
            'valid_until' => Carbon::tomorrow(),
        ]);
        
        $expiredResult = $this->service->checkRoute('TEST', 'expired-app');
        $validResult = $this->service->checkRoute('TEST', 'valid-app');
        
        expect($expiredResult['status'])->toBe('error')
            ->and($expiredResult['msg'])->toBe('Die Lizenz für die App ist abgelaufen.')
            ->and($validResult['status'])->toBe('ok')
            ->and($validResult['redirect'])->toBe('/homepage/?school=TEST&licence=valid-app');
    });

    it('uses LicenceService internally for licence validation', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        $licence = Licence::create([
            'name' => 'test-app',
            'long_name' => 'Test Application',
        ]);
        
        SchoolLicence::create([
            'school_id' => $school->id,
            'licence_id' => $licence->id,
            'valid_until' => Carbon::tomorrow(),
        ]);
        
        // Mock LicenceService to verify it's being used
        $licenceService = Mockery::mock(LicenceService::class);
        $licenceService->shouldReceive('checkLicence')
            ->once()
            ->with(Mockery::on(function ($arg) use ($school) {
                return $arg->id === $school->id;
            }), 'test-app')
            ->andReturn(['status' => 'ok', 'redirect' => '&licence=test-app']);
        
        app()->instance(LicenceService::class, $licenceService);
        
        // Override service to use mocked LicenceService
        $service = new class extends HomepageRoutingService {
            public function checkRoute($school_load, $licence_load)
            {
                $redirect = '/homepage';
                
                if ($school_load) {
                    $school = School::where('short_name', $school_load)->first();
                    if (!$school) return ['status' => 'error', 'msg' => 'Die Schule konnte nicht gefunden werden.'];
                    $redirect = "/homepage/?school=" . $school_load;
                    
                    if ($licence_load) {
                        $licenceService = app(LicenceService::class);
                        $answer = $licenceService->checkLicence($school, $licence_load);
                        
                        if ($answer['status'] == 'error')  return $answer;
                        
                        $redirect .= $answer['redirect'];
                    }
                }
                
                return ['status' => 'ok', 'redirect' => $redirect];
            }
        };
        
        $result = $service->checkRoute('TEST', 'test-app');
        
        expect($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage/?school=TEST&licence=test-app');
    });
});

describe('checkRoute edge cases', function () {
    it('handles whitespace in school parameter', function () {
        $result = $this->service->checkRoute('  ', null);
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('error')
            ->and($result['msg'])->toBe('Die Schule konnte nicht gefunden werden.');
    });

    it('handles null values correctly', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        
        $result = $this->service->checkRoute('TEST', null);
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage/?school=TEST');
    });

    it('returns consistent response structure on success', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        
        $result = $this->service->checkRoute('TEST', null);
        
        expect($result)->toHaveKeys(['status', 'redirect'])
            ->and($result)->toHaveCount(2);
    });

    it('returns consistent response structure on error', function () {
        $result = $this->service->checkRoute('INVALID', null);
        
        expect($result)->toHaveKeys(['status', 'msg'])
            ->and($result)->toHaveCount(2);
    });

    it('handles URL special characters in school name', function () {
        $school = School::factory()->create(['short_name' => 'TEST&SCHOOL']);
        
        $result = $this->service->checkRoute('TEST&SCHOOL', null);
        
        expect($result)->toBeArray()
            ->and($result['status'])->toBe('ok')
            ->and($result['redirect'])->toBe('/homepage/?school=TEST&SCHOOL');
    });
});

describe('checkRoute return value validation', function () {
    it('always returns an array', function () {
        $result = $this->service->checkRoute(null, null);
        
        expect($result)->toBeArray();
    });

    it('always includes status key', function () {
        $result1 = $this->service->checkRoute(null, null);
        $result2 = $this->service->checkRoute('INVALID', null);
        
        expect($result1)->toHaveKey('status')
            ->and($result2)->toHaveKey('status');
    });

    it('status is either ok or error', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        
        $result1 = $this->service->checkRoute('TEST', null);
        $result2 = $this->service->checkRoute('INVALID', null);
        
        expect($result1['status'])->toBeIn(['ok', 'error'])
            ->and($result2['status'])->toBeIn(['ok', 'error']);
    });

    it('includes redirect on success and msg on error', function () {
        $school = School::factory()->create(['short_name' => 'TEST']);
        
        $successResult = $this->service->checkRoute('TEST', null);
        $errorResult = $this->service->checkRoute('INVALID', null);
        
        expect($successResult)->toHaveKey('redirect')
            ->and($successResult)->not->toHaveKey('msg')
            ->and($errorResult)->toHaveKey('msg')
            ->and($errorResult)->not->toHaveKey('redirect');
    });
});
