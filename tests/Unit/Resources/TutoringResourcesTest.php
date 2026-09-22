<?php

use Tests\TestCase;

uses(TestCase::class);

test('retired tutoring classes are no longer loadable', function (string $class): void {
    expect(class_exists($class))->toBeFalse();
})->with([
    'App\\Http\\Resources\\Tutoring\\OfferNotLoggedInResource',
    'App\\Http\\Resources\\Tutoring\\OfferRequestResource',
    'App\\Http\\Resources\\Tutoring\\OfferResource',
    'App\\Http\\Resources\\Tutoring\\ReceivedOfferRequestResource',
    'App\\Http\\Resources\\Tutoring\\SchoolToolResource',
    'App\\Models\\TutoringOffer',
    'App\\Models\\TutoringOfferRequest',
    'App\\Models\\TutoringSubject',
]);
