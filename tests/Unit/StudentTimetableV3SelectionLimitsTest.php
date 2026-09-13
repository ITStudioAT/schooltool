<?php

use App\Http\Requests\Admin\UpdateStudentTimetableV3TimetableRequest;
use App\Http\Requests\Homepage\UpdateStudentTimetableV3TimetableRequest as HomepageTimetableRequest;
use App\Services\StudentsTimetables\RobotTimetableBackendSetupService;
use App\Services\StudentsTimetables\StudentTimetableV3TimetableService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

it('accepts more than ten modules and thirty hours for automatic selection', function () {
    $service = app(StudentTimetableV3TimetableService::class);
    $modules = array_map(fn (int $number): array => [
        'selection_key' => "additional:M{$number}",
        'hours' => 4,
        'courses' => [['key' => "course-{$number}"]],
    ], range(1, 12));
    $moduleKeys = array_column($modules, 'selection_key');
    $normalized = (new ReflectionMethod($service, 'normalizedModuleInput'))->invoke($service, $moduleKeys);
    $selected = (new ReflectionMethod($service, 'selectedModules'))->invoke($service, [
        'module_selection_groups' => [['modules' => $modules]],
    ], $normalized['module_keys']);
    $resolved = (new ReflectionMethod($service, 'resolvedCourseSelection'))->invoke(
        $service, $selected, array_map(fn (int $number): string => "course-{$number}", range(1, 12)),
    );

    expect($selected)->toHaveCount(12)
        ->and(array_sum(array_column($selected, 'hours')))->toBe(48)
        ->and($resolved['keys'])->toHaveCount(12);

    foreach ([new UpdateStudentTimetableV3TimetableRequest, new HomepageTimetableRequest] as $request) {
        $rules = $request->rules();
        expect(Validator::make(['modules' => $moduleKeys], [
            'modules' => $rules['modules'],
            'modules.*' => $rules['modules.*'],
        ])->passes())->toBeTrue();
    }
});

it('enforces the inclusive automatic candidate limit without enumerating timetables', function (array $optionCounts, bool $allowed) {
    $modules = [];
    $keys = [];

    foreach ($optionCounts as $moduleIndex => $optionCount) {
        $courses = [];
        foreach (range(1, $optionCount) as $option) {
            $courseKeys = ["m{$moduleIndex}-{$option}-a", "m{$moduleIndex}-{$option}-b"];
            $courses[] = ['keys' => $courseKeys];
            $keys = [...$keys, ...$courseKeys];
        }
        $modules[] = ['selection_key' => "module-{$moduleIndex}", 'courses' => $courses];
    }

    $service = app(StudentTimetableV3TimetableService::class);
    $resolve = fn (): mixed => (new ReflectionMethod($service, 'resolvedCourseSelection'))->invoke($service, $modules, $keys);

    if ($allowed) {
        expect($resolve()['keys'])->toHaveCount(count($keys));

        return;
    }

    expect($resolve)->toThrow(ValidationException::class, '100.000');
})->with([
    'exactly one hundred thousand grouped choices' => [[10, 10, 10, 10, 10], true],
    'above one hundred thousand choices' => [[10, 10, 10, 10, 11], false],
    'product beyond integer capacity stops early' => [array_fill(0, 25, 10), false],
]);

it('keeps the legacy robot limit and checks the separate v3 boundary safely', function () {
    $service = app(RobotTimetableBackendSetupService::class);
    $method = new ReflectionMethod($service, 'timetableVariationLimitExceeded');
    $options = array_fill(0, 5, array_fill(0, 10, []));

    expect($method->invoke($service, [[]], $options, false, 100000))->toBeFalse();
    $options[4][] = [];
    expect($method->invoke($service, [[]], $options, false, 100000))->toBeTrue()
        ->and($method->invoke($service, [[]], $options, false))->toBeFalse()
        ->and($method->invoke($service, [[]], array_fill(0, 25, array_fill(0, 10, [])), false, 100000))->toBeTrue();
});
