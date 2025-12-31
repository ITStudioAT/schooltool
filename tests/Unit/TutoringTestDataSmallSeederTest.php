<?php

namespace Tests\Unit;

use App\Models\School;
use App\Models\TutoringOffer;
use App\Models\TutoringSubject;
use App\Models\User;
use Database\Seeders\TutoringTestDataSmallSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('TutoringTestDataSmallSeeder', function () {
    it('creates test school with all required data', function () {
        $this->artisan('db:seed', ['--class' => TutoringTestDataSmallSeeder::class]);

        $school = School::where('short_name', 'TEST-SCHOOL')->first();

        expect($school)->not->toBeNull()
            ->and($school->long_name)->toBe('Test Gymnasium')
            ->and($school->email)->toBe('office@test-school.at')
            ->and($school->is_selectable)->toBe(1);
    });

    it('adds Nachhilfetool license to test school', function () {
        // Erstelle die Nachhilfetool-Lizenz wenn sie noch nicht existiert
        \Illuminate\Support\Facades\DB::table('licences')->updateOrInsert(
            ['id' => 2],
            [
                'name' => 'Nachhilfetool',
                'long_name' => 'Nachhilfetool',
                'price_per_year' => 0,
                'is_selectable' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->artisan('db:seed', ['--class' => TutoringTestDataSmallSeeder::class]);

        $school = School::where('short_name', 'TEST-SCHOOL')->first();

        expect($school->licences)->toHaveCount(1)
            ->and($school->licences->first()->id)->toBe(2)
            ->and($school->licences->first()->name)->toBe('Nachhilfetool')
            ->and($school->licences->first()->pivot->valid_until)->toBe('2026-07-10');
    });

    it('creates super admin user kron@naturwelt.at', function () {
        $this->artisan('db:seed', ['--class' => TutoringTestDataSmallSeeder::class]);

        $school = School::where('short_name', 'TEST-SCHOOL')->first();
        $superAdmin = User::where('email', 'kron@naturwelt.at')
            ->where('school_id', $school->id)
            ->first();

        expect($superAdmin)->not->toBeNull()
            ->and($superAdmin->first_name)->toBe('Super')
            ->and($superAdmin->last_name)->toBe('Admin')
            ->and($superAdmin->is_active)->toBe(1)
            ->and($superAdmin->confirmed_at)->not->toBeNull()
            ->and($superAdmin->hasRole('super_admin'))->toBeTrue();
    });

    it('creates required roles', function () {
        $this->artisan('db:seed', ['--class' => TutoringTestDataSmallSeeder::class]);

        expect(Role::where('name', 'tutoring_user')->exists())->toBeTrue()
            ->and(Role::where('name', 'teacher')->exists())->toBeTrue()
            ->and(Role::where('name', 'super_admin')->exists())->toBeTrue();
    });

    it('creates 3 tutoring subjects', function () {
        $this->artisan('db:seed', ['--class' => TutoringTestDataSmallSeeder::class]);

        $school = School::where('short_name', 'TEST-SCHOOL')->first();
        $subjects = TutoringSubject::where('school_id', $school->id)->get();

        expect($subjects)->toHaveCount(3);

        $subjectNames = $subjects->pluck('long_name')->toArray();
        expect($subjectNames)->toContain('Mathematik')
            ->and($subjectNames)->toContain('Deutsch')
            ->and($subjectNames)->toContain('Englisch');
    });

    it('creates 2 teachers with teacher role', function () {
        $this->artisan('db:seed', ['--class' => TutoringTestDataSmallSeeder::class]);

        $school = School::where('short_name', 'TEST-SCHOOL')->first();
        $teachers = User::where('school_id', $school->id)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'teacher');
            })
            ->get();

        expect($teachers)->toHaveCount(2);

        foreach ($teachers as $teacher) {
            expect($teacher->email)->toContain('@test-school.at')
                ->and($teacher->is_active)->toBe(1)
                ->and($teacher->confirmed_at)->not->toBeNull()
                ->and($teacher->hasRole('teacher'))->toBeTrue();
        }
    });

    it('creates 10 students with tutoring_user role', function () {
        $this->artisan('db:seed', ['--class' => TutoringTestDataSmallSeeder::class]);

        $school = School::where('short_name', 'TEST-SCHOOL')->first();
        $students = User::where('school_id', $school->id)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'tutoring_user');
            })
            ->get();

        expect($students)->toHaveCount(10);

        foreach ($students as $student) {
            expect($student->email)->toContain('@test-school.at')
                ->and($student->is_active)->toBe(1)
                ->and($student->confirmed_at)->not->toBeNull()
                ->and($student->hasRole('tutoring_user'))->toBeTrue();
        }
    });

    it('creates 2 tutoring offers', function () {
        $this->artisan('db:seed', ['--class' => TutoringTestDataSmallSeeder::class]);

        $school = School::where('short_name', 'TEST-SCHOOL')->first();
        $offers = TutoringOffer::where('school_id', $school->id)->get();

        expect($offers)->toHaveCount(2);

        foreach ($offers as $offer) {
            $isArrayOrArrayObject = is_array($offer->time_table) || $offer->time_table instanceof \Illuminate\Database\Eloquent\Casts\ArrayObject;

            expect($offer->title)->toContain('Nachhilfe')
                ->and($offer->description)->not->toBeNull()
                ->and($offer->classes)->toBeInstanceOf(\Illuminate\Database\Eloquent\Casts\ArrayObject::class)
                ->and($isArrayOrArrayObject)->toBeTrue()
                ->and($offer->is_active)->toBeTrue()
                ->and($offer->accepted_at)->not->toBeNull()
                ->and((float) $offer->price_per_hour)->toBe(15.0);
        }
    });

    it('creates offers with valid time table structure', function () {
        $this->artisan('db:seed', ['--class' => TutoringTestDataSmallSeeder::class]);

        $school = School::where('short_name', 'TEST-SCHOOL')->first();
        $offer = TutoringOffer::where('school_id', $school->id)->first();

        expect($offer->time_table)->toBeArray()
            ->and($offer->time_table)->toHaveCount(1);

        $timeSlot = $offer->time_table[0];
        expect($timeSlot)->toHaveKey('day')
            ->and($timeSlot)->toHaveKey('from')
            ->and($timeSlot)->toHaveKey('to')
            ->and($timeSlot['day'])->toBe('Montag')
            ->and($timeSlot['from'])->toBe('15:00')
            ->and($timeSlot['to'])->toBe('17:00');
    });

    it('creates total of 13 users (10 students + 2 teachers + 1 super admin)', function () {
        $this->artisan('db:seed', ['--class' => TutoringTestDataSmallSeeder::class]);

        $school = School::where('short_name', 'TEST-SCHOOL')->first();
        $totalUsers = User::where('school_id', $school->id)->count();

        expect($totalUsers)->toBe(13);
    });
});
