<?php

use App\Models\School;
use App\Models\SchoolTool;
use App\Models\Schoolyear;
use App\Models\TutoringOffer;
use App\Models\TutoringSubject;
use App\Models\User;
use App\Services\TutoringOfferService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new TutoringOfferService;

    $this->school = School::factory()->create();
    $this->schoolyear = Schoolyear::factory()->create(['school_id' => $this->school->id]);
    $this->schoolTool = SchoolTool::create([
        'school_id' => $this->school->id,
        'tutoring_student_must_be_confirmed' => false,
        'tutoring_confirmer_email' => null,
        'tutoring_max_offers_per_student' => 0,
        'may_visible_for_other_schools' => true,
    ]);

    $this->user = User::factory()->create([
        'school_id' => $this->school->id,
        'schoolyear_id' => $this->schoolyear->id,
    ]);

    $this->subject = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'M',
        'long_name' => 'Mathematik',
        'must_be_accepted' => false,
        'email_mentors' => [],
    ]);

    $this->subjectWithAcceptance = TutoringSubject::create([
        'school_id' => $this->school->id,
        'short_name' => 'E',
        'long_name' => 'Englisch',
        'must_be_accepted' => true,
        'email_mentors' => ['mentor@example.com'],
    ]);
});

describe('isCreatingPossible', function () {
    it('returns status true with code 200', function () {
        $data = [
            'subject_id' => $this->subject->id,
            'description' => 'Test description',
        ];

        $result = $this->service->isCreatingPossible($this->school->id, $this->user->id, $data);

        expect($result)->toBeArray()
            ->and($result['status'])->toBeTrue()
            ->and($result['code'])->toBe(200)
            ->and($result['message'])->toBe('Speicherung möglich');
    });

    it('returns the original data in the response', function () {
        $data = [
            'subject_id' => $this->subject->id,
            'description' => 'Test description',
            'price_per_hour' => 15.50,
        ];

        $result = $this->service->isCreatingPossible($this->school->id, $this->user->id, $data);

        expect($result['data'])->toBe($data)
            ->and($result['data']['subject_id'])->toBe($this->subject->id)
            ->and($result['data']['description'])->toBe('Test description')
            ->and($result['data']['price_per_hour'])->toBe(15.50);
    });

    it('works with empty data', function () {
        $data = [];

        $result = $this->service->isCreatingPossible($this->school->id, $this->user->id, $data);

        expect($result['status'])->toBeTrue()
            ->and($result['data'])->toBe([]);
    });
});

describe('create', function () {
    it('forces visible_for_other_schools to false when school disallows it', function () {
        $this->schoolTool->update(['may_visible_for_other_schools' => false]);

        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'Mathe Nachhilfe',
            'description' => 'Mathe Nachhilfe fr alle Klassen',
            'price_per_hour' => 20.00,
            'visible_for_other_schools' => true,
        ];

        $offer = $this->service->create($this->school->id, $this->user->id, $data);

        expect($offer->visible_for_other_schools)->toBeFalse();
    });
    it('creates an offer with subject that does not require acceptance', function () {
        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'Mathe Nachhilfe',
            'description' => 'Mathe Nachhilfe für alle Klassen',
            'price_per_hour' => 20.00,
        ];

        $offer = $this->service->create($this->school->id, $this->user->id, $data);

        expect($offer)->toBeInstanceOf(TutoringOffer::class)
            ->and($offer->school_id)->toBe($this->school->id)
            ->and($offer->user_id)->toBe($this->user->id)
            ->and($offer->subject_id)->toBe($this->subject->id)
            ->and($offer->title)->toBe('Mathe Nachhilfe')
            ->and($offer->description)->toBe('Mathe Nachhilfe für alle Klassen')
            ->and($offer->price_per_hour)->toBe('20.00')
            ->and($offer->must_be_accepted)->toBeFalse()
            ->and($offer->is_active)->toBeTrue()
            ->and($offer->accepted_at)->not->toBeNull();
    });

    it('creates an offer with subject that requires acceptance', function () {
        $data = [
            'subject_id' => $this->subjectWithAcceptance->id,
            'title' => 'Englisch Nachhilfe',
            'description' => 'Englisch Nachhilfe',
            'price_per_hour' => 25.00,
        ];

        $offer = $this->service->create($this->school->id, $this->user->id, $data);

        expect($offer)->toBeInstanceOf(TutoringOffer::class)
            ->and($offer->school_id)->toBe($this->school->id)
            ->and($offer->user_id)->toBe($this->user->id)
            ->and($offer->subject_id)->toBe($this->subjectWithAcceptance->id)
            ->and($offer->must_be_accepted)->toBeTrue()
            ->and($offer->is_active)->toBeFalse()
            ->and($offer->accepted_at)->toBeNull();
    });

    it('sets school_id and user_id from parameters', function () {
        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'Test offer',
            'description' => 'Test offer',
            'price_per_hour' => 15.00,
        ];

        $offer = $this->service->create($this->school->id, $this->user->id, $data);

        expect($offer->school_id)->toBe($this->school->id)
            ->and($offer->user_id)->toBe($this->user->id);
    });

    it('inherits must_be_accepted from subject', function () {
        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'Test offer',
            'description' => 'Test offer',
            'price_per_hour' => 15.00,
        ];

        $offer = $this->service->create($this->school->id, $this->user->id, $data);

        expect($offer->must_be_accepted)->toBe($this->subject->must_be_accepted);
    });

    it('sets is_active to true when must_be_accepted is false', function () {
        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'Test offer',
            'description' => 'Test offer',
            'price_per_hour' => 15.00,
        ];

        $offer = $this->service->create($this->school->id, $this->user->id, $data);

        expect($offer->must_be_accepted)->toBeFalse()
            ->and($offer->is_active)->toBeTrue();
    });

    it('sets is_active to false when must_be_accepted is true', function () {
        $data = [
            'subject_id' => $this->subjectWithAcceptance->id,
            'title' => 'Test offer',
            'description' => 'Test offer',
            'price_per_hour' => 15.00,
        ];

        $offer = $this->service->create($this->school->id, $this->user->id, $data);

        expect($offer->must_be_accepted)->toBeTrue()
            ->and($offer->is_active)->toBeFalse();
    });

    it('sets accepted_at to now when must_be_accepted is false', function () {
        $beforeCreation = Carbon::now();

        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'Test offer',
            'description' => 'Test offer',
            'price_per_hour' => 15.00,
        ];

        $offer = $this->service->create($this->school->id, $this->user->id, $data);

        $afterCreation = Carbon::now();

        expect($offer->accepted_at)->not->toBeNull()
            ->and(Carbon::parse($offer->accepted_at)->isAfter($beforeCreation->subSecond()))->toBeTrue()
            ->and(Carbon::parse($offer->accepted_at)->isBefore($afterCreation->addSecond()))->toBeTrue();
    });

    it('does not set accepted_at when must_be_accepted is true', function () {
        $data = [
            'subject_id' => $this->subjectWithAcceptance->id,
            'title' => 'Test offer',
            'description' => 'Test offer',
            'price_per_hour' => 15.00,
        ];

        $offer = $this->service->create($this->school->id, $this->user->id, $data);

        expect($offer->accepted_at)->toBeNull();
    });

    it('persists offer to database', function () {
        $initialCount = TutoringOffer::count();

        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'Test offer',
            'description' => 'Test offer',
            'price_per_hour' => 15.00,
        ];

        $this->service->create($this->school->id, $this->user->id, $data);

        expect(TutoringOffer::count())->toBe($initialCount + 1);
    });

    it('preserves additional data fields', function () {
        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'Comprehensive tutoring',
            'description' => 'Comprehensive tutoring',
            'price_per_hour' => 30.50,
            'classes' => ['5a', '5b', '6a'],
            'time_table' => ['monday' => '14:00-16:00', 'wednesday' => '15:00-17:00'],
        ];

        $offer = $this->service->create($this->school->id, $this->user->id, $data);

        expect($offer->title)->toBe('Comprehensive tutoring')
            ->and($offer->description)->toBe('Comprehensive tutoring')
            ->and($offer->price_per_hour)->toBe('30.50')
            ->and($offer->classes)->toBeInstanceOf(ArrayObject::class)
            ->and($offer->time_table)->toBeArray();
    });

    it('throws exception when subject does not exist', function () {
        $data = [
            'subject_id' => 99999,
            'title' => 'Test offer',
            'description' => 'Test offer',
            'price_per_hour' => 15.00,
        ];

        $this->service->create($this->school->id, $this->user->id, $data);
    })->throws(ModelNotFoundException::class);

    it('creates multiple offers for same user and school', function () {
        $data1 = [
            'subject_id' => $this->subject->id,
            'title' => 'First offer',
            'description' => 'First offer',
            'price_per_hour' => 15.00,
        ];

        $data2 = [
            'subject_id' => $this->subjectWithAcceptance->id,
            'title' => 'Second offer',
            'description' => 'Second offer',
            'price_per_hour' => 20.00,
        ];

        $offer1 = $this->service->create($this->school->id, $this->user->id, $data1);
        $offer2 = $this->service->create($this->school->id, $this->user->id, $data2);

        $count = TutoringOffer::where('user_id', $this->user->id)
            ->where('school_id', $this->school->id)
            ->count();

        expect($count)->toBe(2)
            ->and($offer1->id)->not->toBe($offer2->id);
    });

    it('creates offers for different users in same school', function () {
        $user2 = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $this->schoolyear->id,
        ]);

        $data = [
            'subject_id' => $this->subject->id,
            'title' => 'Test offer',
            'description' => 'Test offer',
            'price_per_hour' => 15.00,
        ];

        $offer1 = $this->service->create($this->school->id, $this->user->id, $data);
        $offer2 = $this->service->create($this->school->id, $user2->id, $data);

        expect($offer1->user_id)->toBe($this->user->id)
            ->and($offer2->user_id)->toBe($user2->id)
            ->and($offer1->school_id)->toBe($this->school->id)
            ->and($offer2->school_id)->toBe($this->school->id);
    });
});

describe('update', function () {
    it('forces visible_for_other_schools to false when school disallows it', function () {
        $this->schoolTool->update(['may_visible_for_other_schools' => false]);

        $offer = TutoringOffer::create([
            'school_id' => $this->school->id,
            'user_id' => $this->user->id,
            'subject_id' => $this->subject->id,
            'title' => 'Mathe Nachhilfe',
            'description' => 'Mathe Nachhilfe fr alle Klassen',
            'price_per_hour' => 20.00,
            'visible_for_other_schools' => true,
            'must_be_accepted' => false,
            'is_active' => true,
            'click_count' => 0,
        ]);

        $offer = $this->service->update($offer, [
            'visible_for_other_schools' => true,
        ]);

        expect($offer->visible_for_other_schools)->toBeFalse();
    });
});
