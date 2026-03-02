<?php

namespace Tests\Feature\Console;

use App\Models\School;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseDate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('changes configured hours only for records that belong to school_id 5', function () {
    School::factory()->count(4)->create();

    $targetSchool = School::query()->forceCreate([
        'id' => 5,
        'long_name' => 'School 5',
        'short_name' => 'S5',
        'email' => 'school5@example.test',
        'is_selectable' => true,
    ]);

    $otherSchool = School::factory()->create();

    $targetCourse = TeachingCourse::factory()->forSchool($targetSchool)->create();
    $otherCourse = TeachingCourse::factory()->forSchool($otherSchool)->create();

    $targetMappedSingle = TeachingCourseDate::query()->create([
        'teaching_course_id' => $targetCourse->id,
        'date' => '2026-03-02',
        'hours' => [4],
        'content' => 'target single',
    ]);

    $targetMappedMultiple = TeachingCourseDate::query()->create([
        'teaching_course_id' => $targetCourse->id,
        'date' => '2026-03-03',
        'hours' => [3, 5, 8],
        'content' => 'target multiple',
    ]);

    $targetUnchanged = TeachingCourseDate::query()->create([
        'teaching_course_id' => $targetCourse->id,
        'date' => '2026-03-04',
        'hours' => [1, 2],
        'content' => 'target unchanged',
    ]);

    $otherSchoolRecord = TeachingCourseDate::query()->create([
        'teaching_course_id' => $otherCourse->id,
        'date' => '2026-03-05',
        'hours' => [6],
        'content' => 'other school',
    ]);

    $this->artisan('hours:change')->assertExitCode(0);

    expect($targetMappedSingle->fresh()->hours)->toEqual([13]);
    expect($targetMappedMultiple->fresh()->hours)->toEqual([12, 14, 8]);
    expect($targetUnchanged->fresh()->hours)->toEqual([1, 2]);
    expect($otherSchoolRecord->fresh()->hours)->toEqual([6]);
});
