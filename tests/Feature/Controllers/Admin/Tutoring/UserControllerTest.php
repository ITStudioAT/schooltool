<?php

test('removed tutoring endpoints cannot be accessed', function (string $method, string $uri): void {
    $this->json($method, $uri, ['id' => 1, 'email' => 'removed@example.test'])->assertNotFound();
})->with([
    ['GET', '/api/admin/tutoring/users'],
    ['POST', '/api/admin/tutoring/users'],
    ['PUT', '/api/admin/tutoring/users/1'],
    ['POST', '/api/admin/tutoring/delete_users'],
    ['POST', '/api/admin/tutoring/confirm_users'],
    ['POST', '/api/admin/tutoring/clean_users'],
]);
