<?php

/**
 * HealthJob Tests
 *
 * Tests the health check job that updates the health_at timestamp
 * for the SchoolTool configuration record.
 */

use App\Jobs\HealthJob;
use App\Models\School;
use App\Models\SchoolTool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Create a school manually to avoid faker issues
    $this->school = School::create([
        'long_name' => 'Test School',
        'short_name' => 'TEST',
        'email' => 'test@school.com',
        'logo' => null,
        'is_selectable' => true,
    ]);

    // Create SchoolTool record with ID 1 (required by the job)
    // Use DB insert to force ID 1
    DB::table('school_tools')->insert([
        'id' => 1,
        'school_id' => $this->school->id,
        'tutoring_student_must_be_confirmed' => 0,
        'tutoring_confirmer_email' => null,
        'tutoring_max_offers_per_student' => 5,
        'health_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->schoolTool = SchoolTool::find(1);
});

describe('handle', function () {
    it('updates health_at timestamp for SchoolTool with id 1', function () {
        expect($this->schoolTool->health_at)->toBeNull();

        $job = new HealthJob;
        $job->handle();

        $this->schoolTool->refresh();
        expect($this->schoolTool->health_at)->not->toBeNull();
    });

    it('sets health_at to current timestamp', function () {
        $before = now()->subSecond()->toDateTimeString();

        $job = new HealthJob;
        $job->handle();

        $after = now()->addSecond()->toDateTimeString();

        $this->schoolTool->refresh();
        expect($this->schoolTool->health_at)->toBeGreaterThanOrEqual($before)
            ->and($this->schoolTool->health_at)->toBeLessThanOrEqual($after);
    });

    it('updates existing health_at timestamp', function () {
        $oldTimestamp = now()->subHours(2);
        $this->schoolTool->health_at = $oldTimestamp;
        $this->schoolTool->save();

        expect($this->schoolTool->health_at->toDateTimeString())->toBe($oldTimestamp->toDateTimeString());

        $job = new HealthJob;
        $job->handle();

        $this->schoolTool->refresh();
        expect($this->schoolTool->health_at)->not->toBe($oldTimestamp)
            ->and($this->schoolTool->health_at)->toBeGreaterThan($oldTimestamp);
    });

    it('only updates health_at and preserves other fields', function () {
        $originalSchoolId = $this->schoolTool->school_id;
        $originalMustBeConfirmed = $this->schoolTool->tutoring_student_must_be_confirmed;
        $originalMaxOffers = $this->schoolTool->tutoring_max_offers_per_student;

        $job = new HealthJob;
        $job->handle();

        $this->schoolTool->refresh();
        expect($this->schoolTool->school_id)->toBe($originalSchoolId)
            ->and($this->schoolTool->tutoring_student_must_be_confirmed)->toBe($originalMustBeConfirmed)
            ->and($this->schoolTool->tutoring_max_offers_per_student)->toBe($originalMaxOffers)
            ->and($this->schoolTool->health_at)->not->toBeNull();
    });

    it('throws exception when SchoolTool with id 1 does not exist', function () {
        // Delete the SchoolTool record
        SchoolTool::where('id', 1)->delete();

        $job = new HealthJob;
        $job->handle();
    })->throws(ModelNotFoundException::class);
});

describe('job configuration', function () {
    it('implements ShouldQueue interface', function () {
        $job = new HealthJob;
        expect($job)->toBeInstanceOf(ShouldQueue::class);
    });

    it('uses Queueable trait', function () {
        $job = new HealthJob;
        expect(class_uses($job))->toContain(Queueable::class);
    });

    it('can be dispatched to queue', function () {
        Queue::fake();

        HealthJob::dispatch();

        Queue::assertPushed(HealthJob::class);
    });

    it('can be instantiated without parameters', function () {
        $job = new HealthJob;
        expect($job)->toBeInstanceOf(HealthJob::class);
    });
});

describe('job execution', function () {
    it('executes successfully when dispatched', function () {
        $this->schoolTool->health_at = null;
        $this->schoolTool->save();

        $job = new HealthJob;
        dispatch($job);

        // Since we're testing synchronously, the job should execute immediately
        $this->schoolTool->refresh();
        expect($this->schoolTool->health_at)->not->toBeNull();
    });

    it('updates timestamp accurately on repeated execution', function () {
        $job1 = new HealthJob;
        $job1->handle();

        $this->schoolTool->refresh();
        $firstTimestamp = $this->schoolTool->health_at;

        // Wait a moment to ensure different timestamp
        sleep(1);

        $job2 = new HealthJob;
        $job2->handle();

        $this->schoolTool->refresh();
        $secondTimestamp = $this->schoolTool->health_at;

        expect($secondTimestamp)->toBeGreaterThan($firstTimestamp);
    });
});

describe('database interactions', function () {
    it('persists health_at to database', function () {
        $job = new HealthJob;
        $job->handle();

        $this->assertDatabaseHas('school_tools', [
            'id' => 1,
            'school_id' => $this->school->id,
        ]);

        // Verify health_at is not null in database
        $record = DB::table('school_tools')->where('id', 1)->first();
        expect($record->health_at)->not->toBeNull();
    });

    it('uses findOrFail to retrieve SchoolTool', function () {
        // This test verifies that the job uses findOrFail by checking it throws
        // an exception when the record doesn't exist
        SchoolTool::where('id', 1)->delete();

        expect(fn () => (new HealthJob)->handle())
            ->toThrow(ModelNotFoundException::class);
    });
});
