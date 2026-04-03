<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\User;
use App\Services\SchoolyearService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SchoolyearService;

    // Create test school
    $this->school = School::factory()->create([
        'short_name' => 'TestSchool',
        'long_name' => 'Test School Name',
    ]);
});

describe('setToUser', function () {
    it('sets schoolyear to user successfully', function () {
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'name' => '2024/2025',
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => null,
        ]);

        expect($user->schoolyear_id)->toBeNull();

        $result = $this->service->setToUser($user, $schoolyear->id);

        expect($result)->toBeInstanceOf(Schoolyear::class)
            ->and($result->id)->toBe($schoolyear->id)
            ->and($result->name)->toBe('2024/2025');

        $user->refresh();
        expect($user->schoolyear_id)->toBe($schoolyear->id);
    });

    it('updates existing schoolyear_id on user', function () {
        $oldSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'name' => '2023/2024',
        ]);

        $newSchoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'name' => '2024/2025',
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $oldSchoolyear->id,
        ]);

        expect($user->schoolyear_id)->toBe($oldSchoolyear->id);

        $result = $this->service->setToUser($user, $newSchoolyear->id);

        expect($result->id)->toBe($newSchoolyear->id);

        $user->refresh();
        expect($user->schoolyear_id)->toBe($newSchoolyear->id)
            ->and($user->schoolyear_id)->not->toBe($oldSchoolyear->id);
    });

    it('returns the schoolyear that was set', function () {
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'name' => '2024/2025',
            'from' => '2024-09-01',
            'until' => '2025-06-30',
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $result = $this->service->setToUser($user, $schoolyear->id);

        expect($result)->toBeInstanceOf(Schoolyear::class)
            ->and($result->id)->toBe($schoolyear->id)
            ->and($result->name)->toBe('2024/2025')
            ->and($result->from)->toBe('2024-09-01')
            ->and($result->until)->toBe('2025-06-30')
            ->and($result->school_id)->toBe($this->school->id);
    });

    it('throws exception when schoolyear does not exist', function () {
        $user = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $nonExistentId = 99999;

        $this->service->setToUser($user, $nonExistentId);
    })->throws(ModelNotFoundException::class);

    it('persists the change to database', function () {
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'name' => '2024/2025',
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => null,
        ]);

        $this->service->setToUser($user, $schoolyear->id);

        // Fetch fresh from database
        $freshUser = User::find($user->id);
        expect($freshUser->schoolyear_id)->toBe($schoolyear->id);
    });

    it('rejects schoolyear from different school', function () {
        $otherSchool = School::factory()->create([
            'short_name' => 'OtherSchool',
            'long_name' => 'Other School Name',
        ]);

        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $otherSchool->id,
            'name' => '2024/2025',
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $this->service->setToUser($user, $schoolyear->id);
    })->throws(HttpException::class, 'Sie haben keine Berechtigung');

    it('handles multiple users set to same schoolyear', function () {
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'name' => '2024/2025',
        ]);

        $user1 = User::factory()->create(['school_id' => $this->school->id]);
        $user2 = User::factory()->create(['school_id' => $this->school->id]);
        $user3 = User::factory()->create(['school_id' => $this->school->id]);

        $this->service->setToUser($user1, $schoolyear->id);
        $this->service->setToUser($user2, $schoolyear->id);
        $this->service->setToUser($user3, $schoolyear->id);

        $user1->refresh();
        $user2->refresh();
        $user3->refresh();

        expect($user1->schoolyear_id)->toBe($schoolyear->id)
            ->and($user2->schoolyear_id)->toBe($schoolyear->id)
            ->and($user3->schoolyear_id)->toBe($schoolyear->id);
    });

    it('works with schoolyear having all fields populated', function () {
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'name' => '2024/2025',
            'from' => '2024-09-01',
            'until' => '2025-06-30',
            'sem_2_start' => '2025-02-01',
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $result = $this->service->setToUser($user, $schoolyear->id);

        expect($result->name)->toBe('2024/2025')
            ->and($result->from)->toBe('2024-09-01')
            ->and($result->until)->toBe('2025-06-30')
            ->and($result->sem_2_start)->toBe('2025-02-01');
    });

    it('does not modify other user attributes', function () {
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567890',
        ]);

        $originalEmail = $user->email;
        $originalFirstName = $user->first_name;
        $originalLastName = $user->last_name;
        $originalPhone = $user->phone;
        $originalSchoolId = $user->school_id;

        $this->service->setToUser($user, $schoolyear->id);

        $user->refresh();

        expect($user->email)->toBe($originalEmail)
            ->and($user->first_name)->toBe($originalFirstName)
            ->and($user->last_name)->toBe($originalLastName)
            ->and($user->phone)->toBe($originalPhone)
            ->and($user->school_id)->toBe($originalSchoolId)
            ->and($user->schoolyear_id)->toBe($schoolyear->id);
    });

    it('works when user model is fresh from database', function () {
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        // Fetch user fresh from database
        $freshUser = User::find($user->id);

        $this->service->setToUser($freshUser, $schoolyear->id);

        $freshUser->refresh();
        expect($freshUser->schoolyear_id)->toBe($schoolyear->id);
    });

    it('works when user model has relationships loaded', function () {
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        // Load relationship
        $userWithRelations = User::with('selectedSchool')->find($user->id);

        $this->service->setToUser($userWithRelations, $schoolyear->id);

        $userWithRelations->refresh();
        expect($userWithRelations->schoolyear_id)->toBe($schoolyear->id);
    });

    it('can switch between multiple schoolyears sequentially', function () {
        $schoolyear1 = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'name' => '2022/2023',
        ]);

        $schoolyear2 = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'name' => '2023/2024',
        ]);

        $schoolyear3 = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'name' => '2024/2025',
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        // Switch to first
        $this->service->setToUser($user, $schoolyear1->id);
        $user->refresh();
        expect($user->schoolyear_id)->toBe($schoolyear1->id);

        // Switch to second
        $this->service->setToUser($user, $schoolyear2->id);
        $user->refresh();
        expect($user->schoolyear_id)->toBe($schoolyear2->id);

        // Switch to third
        $this->service->setToUser($user, $schoolyear3->id);
        $user->refresh();
        expect($user->schoolyear_id)->toBe($schoolyear3->id);
    });

    it('returns correct schoolyear even when user already has that schoolyear', function () {
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'name' => '2024/2025',
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
            'schoolyear_id' => $schoolyear->id,
        ]);

        // Set same schoolyear again
        $result = $this->service->setToUser($user, $schoolyear->id);

        expect($result->id)->toBe($schoolyear->id);

        $user->refresh();
        expect($user->schoolyear_id)->toBe($schoolyear->id);
    });

    it('works with schoolyear ID as string', function () {
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        // Pass ID as string (simulating request data)
        $result = $this->service->setToUser($user, (string) $schoolyear->id);

        expect($result->id)->toBe($schoolyear->id);

        $user->refresh();
        expect($user->schoolyear_id)->toBe($schoolyear->id);
    });

    it('saves user model correctly triggering model events', function () {
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $originalUpdatedAt = $user->updated_at;

        // Small delay to ensure updated_at changes
        sleep(1);

        $this->service->setToUser($user, $schoolyear->id);

        $user->refresh();

        expect($user->updated_at)->not->toBe($originalUpdatedAt)
            ->and($user->updated_at)->toBeGreaterThan($originalUpdatedAt);
    });

    it('handles schoolyear with minimal data', function () {
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
            'name' => null,
            'from' => null,
            'until' => null,
            'sem_2_start' => null,
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $result = $this->service->setToUser($user, $schoolyear->id);

        expect($result->id)->toBe($schoolyear->id);

        $user->refresh();
        expect($user->schoolyear_id)->toBe($schoolyear->id);
    });

    it('does not load unnecessary relationships on returned schoolyear', function () {
        $schoolyear = Schoolyear::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $user = User::factory()->create([
            'school_id' => $this->school->id,
        ]);

        $result = $this->service->setToUser($user, $schoolyear->id);

        // Verify it's a clean model fetch
        expect($result)->toBeInstanceOf(Schoolyear::class)
            ->and($result->exists)->toBeTrue()
            ->and($result->wasRecentlyCreated)->toBeFalse();
    });
});
