<?php

test('removed tutoring endpoints cannot be accessed', function (string $method, string $uri): void {
    $this->json($method, $uri, ['id' => 1, 'email' => 'removed@example.test'])->assertNotFound();
})->with([
    ['GET', '/api/admin/tutoring/subjects'],
    ['PUT', '/api/admin/tutoring/subjects/1'],
    ['DELETE', '/api/admin/tutoring/subjects/1'],
    ['POST', '/api/admin/tutoring/create_subjects'],
]);
