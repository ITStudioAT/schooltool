<?php

test('removed tutoring endpoints cannot be accessed', function (string $method, string $uri): void {
    $this->json($method, $uri, ['id' => 1, 'email' => 'removed@example.test'])->assertNotFound();
})->with([
    ['GET', '/api/admin/tutoring/offers'],
    ['DELETE', '/api/admin/tutoring/offers/1'],
    ['POST', '/api/admin/tutoring/toggle_accepted_offer'],
    ['POST', '/api/admin/tutoring/toggle_active_offer'],
    ['GET', '/api/admin/tutoring/get_stats'],
]);
