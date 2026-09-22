<?php

use Tests\TestCase;

uses(TestCase::class);

test('retired tutoring classes are no longer loadable', function (string $class): void {
    expect(class_exists($class))->toBeFalse();
})->with([
    'App\\Services\\TutoringService',
]);
