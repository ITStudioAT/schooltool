<?php

test('removed tutoring endpoints cannot be accessed', function (string $method, string $uri): void {
    $this->json($method, $uri, ['id' => 1, 'email' => 'removed@example.test'])->assertNotFound();
})->with([
    ['PUT', '/api/homepage/tutoring/users/1'],
    ['POST', '/api/homepage/tutoring/update_password'],
    ['POST', '/api/homepage/tutoring/logout'],
]);
