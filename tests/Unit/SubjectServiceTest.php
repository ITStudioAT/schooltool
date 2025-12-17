<?php

use App\Models\School;
use App\Models\TutoringSubject;
use App\Services\SubjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SubjectService();
    $this->school = School::factory()->create();
});

describe('create', function () {
    it('creates a new subject with all fields', function () {
        $subjects = [
            [
                'short_name' => 'M',
                'long_name' => 'Mathematik',
                'must_be_accepted' => true,
                'email_mentors' => ['mentor1@example.com', 'mentor2@example.com'],
            ],
        ];

        $this->service->create($this->school->id, $subjects);

        $subject = TutoringSubject::where('school_id', $this->school->id)
            ->where('short_name', 'M')
            ->first();

        expect($subject)->not->toBeNull()
            ->and($subject->long_name)->toBe('Mathematik')
            ->and($subject->must_be_accepted)->toBeTrue()
            ->and($subject->email_mentors)->toBeArray()
            ->and($subject->email_mentors)->toHaveCount(2)
            ->and($subject->email_mentors[0])->toBe('mentor1@example.com')
            ->and($subject->email_mentors[1])->toBe('mentor2@example.com');
    });

    it('creates a subject with must_be_accepted defaulting to false', function () {
        $subjects = [
            [
                'short_name' => 'E',
                'long_name' => 'Englisch',
            ],
        ];

        $this->service->create($this->school->id, $subjects);

        $subject = TutoringSubject::where('school_id', $this->school->id)
            ->where('short_name', 'E')
            ->first();

        expect($subject)->not->toBeNull()
            ->and($subject->must_be_accepted)->toBeFalse();
    });

    it('creates multiple subjects at once', function () {
        $subjects = [
            [
                'short_name' => 'M',
                'long_name' => 'Mathematik',
                'must_be_accepted' => true,
            ],
            [
                'short_name' => 'E',
                'long_name' => 'Englisch',
                'must_be_accepted' => false,
            ],
            [
                'short_name' => 'D',
                'long_name' => 'Deutsch',
            ],
        ];

        $this->service->create($this->school->id, $subjects);

        $count = TutoringSubject::where('school_id', $this->school->id)->count();

        expect($count)->toBe(3);
    });

    it('does not create subject when short_name is missing', function () {
        $subjects = [
            [
                'short_name' => '',
                'long_name' => 'Mathematik',
                'must_be_accepted' => true,
            ],
        ];

        $this->service->create($this->school->id, $subjects);

        $count = TutoringSubject::where('school_id', $this->school->id)->count();

        expect($count)->toBe(0);
    });

    it('does not create subject when long_name is missing', function () {
        $subjects = [
            [
                'short_name' => 'M',
                'long_name' => '',
                'must_be_accepted' => true,
            ],
        ];

        $this->service->create($this->school->id, $subjects);

        $count = TutoringSubject::where('school_id', $this->school->id)->count();

        expect($count)->toBe(0);
    });

    it('does not create subject when both short_name and long_name are missing', function () {
        $subjects = [
            [
                'short_name' => '',
                'long_name' => '',
                'must_be_accepted' => true,
            ],
        ];

        $this->service->create($this->school->id, $subjects);

        $count = TutoringSubject::where('school_id', $this->school->id)->count();

        expect($count)->toBe(0);
    });

    it('filters out empty emails from email_mentors array', function () {
        $subjects = [
            [
                'short_name' => 'M',
                'long_name' => 'Mathematik',
                'email_mentors' => ['mentor1@example.com', '', '   ', 'mentor2@example.com'],
            ],
        ];

        $this->service->create($this->school->id, $subjects);

        $subject = TutoringSubject::where('school_id', $this->school->id)
            ->where('short_name', 'M')
            ->first();

        expect($subject->email_mentors)->toBeArray()
            ->and($subject->email_mentors)->toHaveCount(2)
            ->and($subject->email_mentors[0])->toBe('mentor1@example.com')
            ->and($subject->email_mentors[1])->toBe('mentor2@example.com');
    });

    it('handles email_mentors as empty array when all emails are empty', function () {
        $subjects = [
            [
                'short_name' => 'M',
                'long_name' => 'Mathematik',
                'email_mentors' => ['', '   ', ''],
            ],
        ];

        $this->service->create($this->school->id, $subjects);

        $subject = TutoringSubject::where('school_id', $this->school->id)
            ->where('short_name', 'M')
            ->first();

        expect($subject->email_mentors)->toBeArray()
            ->and($subject->email_mentors)->toHaveCount(0);
    });

    it('creates subject with empty email_mentors array when not provided', function () {
        $subjects = [
            [
                'short_name' => 'M',
                'long_name' => 'Mathematik',
            ],
        ];

        $this->service->create($this->school->id, $subjects);

        $subject = TutoringSubject::where('school_id', $this->school->id)
            ->where('short_name', 'M')
            ->first();

        expect($subject->email_mentors)->toBeArray()
            ->and($subject->email_mentors)->toHaveCount(0);
    });

    it('uses firstOrCreate to avoid duplicates', function () {
        $subjects = [
            [
                'short_name' => 'M',
                'long_name' => 'Mathematik',
                'must_be_accepted' => true,
            ],
        ];

        // Create first time
        $this->service->create($this->school->id, $subjects);

        $firstCount = TutoringSubject::where('school_id', $this->school->id)
            ->where('short_name', 'M')
            ->count();

        // Create second time with same short_name and school_id
        $this->service->create($this->school->id, $subjects);

        $secondCount = TutoringSubject::where('school_id', $this->school->id)
            ->where('short_name', 'M')
            ->count();

        expect($firstCount)->toBe(1)
            ->and($secondCount)->toBe(1);
    });

    it('creates subjects for different schools with same short_name', function () {
        $school2 = School::factory()->create();

        $subjects = [
            [
                'short_name' => 'M',
                'long_name' => 'Mathematik',
            ],
        ];

        $this->service->create($this->school->id, $subjects);
        $this->service->create($school2->id, $subjects);

        $school1Subjects = TutoringSubject::where('school_id', $this->school->id)->count();
        $school2Subjects = TutoringSubject::where('school_id', $school2->id)->count();

        expect($school1Subjects)->toBe(1)
            ->and($school2Subjects)->toBe(1);
    });

    it('re-indexes email_mentors array after filtering', function () {
        $subjects = [
            [
                'short_name' => 'M',
                'long_name' => 'Mathematik',
                'email_mentors' => ['', 'mentor1@example.com', '', 'mentor2@example.com'],
            ],
        ];

        $this->service->create($this->school->id, $subjects);

        $subject = TutoringSubject::where('school_id', $this->school->id)
            ->where('short_name', 'M')
            ->first();

        // Check that array is properly indexed (0, 1) instead of (1, 3)
        expect($subject->email_mentors)->toBeArray()
            ->and($subject->email_mentors)->toHaveCount(2)
            ->and(array_keys($subject->email_mentors))->toBe([0, 1]);
    });

    it('handles mixed valid and invalid subjects', function () {
        $subjects = [
            [
                'short_name' => 'M',
                'long_name' => 'Mathematik',
            ],
            [
                'short_name' => '',
                'long_name' => 'Should not be created',
            ],
            [
                'short_name' => 'E',
                'long_name' => 'Englisch',
            ],
            [
                'short_name' => 'D',
                'long_name' => '', // empty long_name
            ],
        ];

        $this->service->create($this->school->id, $subjects);

        $count = TutoringSubject::where('school_id', $this->school->id)->count();

        expect($count)->toBe(2);

        $mathSubject = TutoringSubject::where('short_name', 'M')->first();
        $englishSubject = TutoringSubject::where('short_name', 'E')->first();

        expect($mathSubject)->not->toBeNull()
            ->and($englishSubject)->not->toBeNull();
    });
});
