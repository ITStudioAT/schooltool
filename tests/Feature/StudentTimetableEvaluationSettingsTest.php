<?php

use App\Models\School;
use App\Models\Schoolyear;
use App\Models\StudentTimetableEvaluationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores timetable evaluation settings for a schoolyear', function () {
    $school = School::factory()->create();
    $schoolyear = Schoolyear::factory()->create([
        'school_id' => $school->id,
    ]);

    $evaluationSetting = StudentTimetableEvaluationSetting::create([
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
        'settings' => [
            'result_weights' => [
                'full_green' => 100,
                'green' => 60,
            ],
        ],
    ]);

    expect($evaluationSetting->settings)
        ->toBe([
            'result_weights' => [
                'full_green' => 100,
                'green' => 60,
            ],
        ])
        ->and($evaluationSetting->school->is($school))->toBeTrue()
        ->and($evaluationSetting->schoolyear->is($schoolyear))->toBeTrue();

    $this->assertDatabaseHas('student_timetable_evaluation_settings', [
        'school_id' => $school->id,
        'schoolyear_id' => $schoolyear->id,
    ]);
});
