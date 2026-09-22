<?php

test('removed tutoring endpoints cannot be accessed', function (string $method, string $uri): void {
    $this->json($method, $uri, ['id' => 1, 'email' => 'removed@example.test'])->assertNotFound();
})->with([
    ['GET', '/api/homepage/tutoring/offer_requests'],
    ['GET', '/api/homepage/tutoring/received_offer_requests'],
    ['DELETE', '/api/homepage/tutoring/offer_requests/1'],
    ['POST', '/api/homepage/tutoring/request_mail_clicked'],
    ['POST', '/api/homepage/tutoring/to_archive'],
    ['POST', '/api/homepage/tutoring/to_active'],
]);
