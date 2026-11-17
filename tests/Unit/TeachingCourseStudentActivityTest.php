<?php

use App\Models\Import116;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseStudent;
use App\Models\User;
use App\Services\TeachingCourseStudentActivity;

test('course activity requires an id link and leaves ambiguous references unresolved', function (?int $forward, ?int $reverse, bool $expected): void {
    $course = new TeachingCourse(['school_id' => 1, 'schoolyear_id' => 2]);
    $user = (new User)->forceFill(['id' => 10, 'school_id' => 1, 'is_active' => false,
        'import116_id' => $reverse, 'first_name' => 'Gleich', 'last_name' => 'Name', 'email' => 'same@example.invalid']);
    $import = (new Import116)->forceFill(['id' => 20, 'school_id' => 1, 'schoolyear_id' => 2,
        'user_id' => $forward, 'first_name' => 'Gleich', 'last_name' => 'Name', 'email' => 'same@example.invalid']);
    $membership = (new TeachingCourseStudent)->setRelation('user', $user)->setRelation('import116', $import);

    expect(app(TeachingCourseStudentActivity::class)->active($membership, $course))->toBe($expected);
})->with([
    'matching names and email without id' => [null, null, true],
    'contradictory forward id' => [11, 20, true],
    'contradictory reverse id' => [10, 21, true],
    'matching forward id' => [10, null, false],
    'matching reverse id' => [null, 20, false],
]);
