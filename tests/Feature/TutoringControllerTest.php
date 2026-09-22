<?php

test('removed tutoring endpoints cannot be accessed', function (string $method, string $uri): void {
    $this->json($method, $uri, ['id' => 1, 'email' => 'removed@example.test'])->assertNotFound();
})->with([
    ['GET', '/api/homepage/tutoring/config'],
    ['GET', '/api/homepage/tutoring/load_auth'],
    ['POST', '/api/homepage/tutoring/check_email'],
    ['POST', '/api/homepage/tutoring/create_user'],
    ['POST', '/api/homepage/tutoring/confirm_email'],
    ['POST', '/api/homepage/tutoring/login_with_password'],
    ['POST', '/api/homepage/tutoring/unknown_password'],
    ['POST', '/api/homepage/tutoring/login_with_token'],
]);
