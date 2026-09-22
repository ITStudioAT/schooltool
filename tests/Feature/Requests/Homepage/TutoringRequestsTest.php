<?php

test('retired tutoring classes are no longer loadable', function (string $class): void {
    expect(class_exists($class))->toBeFalse();
})->with([
    'App\\Http\\Requests\\Homepage\\TutoringCheckEmailRequest',
    'App\\Http\\Requests\\Homepage\\TutoringConfirmEmailRequest',
    'App\\Http\\Requests\\Homepage\\TutoringConfirmUserRequest',
    'App\\Http\\Requests\\Homepage\\TutoringCreateUserRequest',
    'App\\Http\\Requests\\Homepage\\TutoringLoginWithTokenRequest',
    'App\\Http\\Requests\\Homepage\\TutoringUnknownPasswordRequest',
]);
