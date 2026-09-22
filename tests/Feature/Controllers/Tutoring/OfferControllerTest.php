<?php

test('removed tutoring endpoints cannot be accessed', function (string $method, string $uri): void {
    $this->json($method, $uri, ['id' => 1, 'email' => 'removed@example.test'])->assertNotFound();
})->with([
    ['GET', '/api/homepage/tutoring/offers'],
    ['GET', '/api/homepage/tutoring/load_offers'],
    ['POST', '/api/homepage/tutoring/offers'],
    ['PUT', '/api/homepage/tutoring/offers/1'],
    ['GET', '/api/homepage/tutoring/load_my_offers'],
    ['POST', '/api/homepage/tutoring/toggle_offer'],
    ['GET', '/api/homepage/tutoring/load_offer_config'],
    ['POST', '/api/homepage/tutoring/click_count'],
    ['POST', '/api/homepage/tutoring/set_user_search_criteria'],
    ['POST', '/api/homepage/tutoring/send_request'],
]);
