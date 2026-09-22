<?php

test('removed tutoring endpoints cannot be accessed', function (string $method, string $uri): void {
    $this->json($method, $uri, ['id' => 1, 'email' => 'removed@example.test'])->assertNotFound();
})->with([
    ['GET', '/api/homepage/tutoring/subjects'],
]);
