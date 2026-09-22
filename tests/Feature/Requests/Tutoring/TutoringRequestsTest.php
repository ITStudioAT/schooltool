<?php

test('retired tutoring classes are no longer loadable', function (string $class): void {
    expect(class_exists($class))->toBeFalse();
})->with([
    'App\\Http\\Requests\\Tutoring\\LoginWithPasswordRequest',
    'App\\Http\\Requests\\Tutoring\\OfferConfirmRefuseRequest',
    'App\\Http\\Requests\\Tutoring\\OfferIndexRequest',
    'App\\Http\\Requests\\Tutoring\\OfferLoadOfferConfigRequest',
    'App\\Http\\Requests\\Tutoring\\OfferLoadOffersRequest',
    'App\\Http\\Requests\\Tutoring\\OfferRequestIndexRequest',
    'App\\Http\\Requests\\Tutoring\\OfferRequestMailClickedRequest',
    'App\\Http\\Requests\\Tutoring\\OfferRequestRequest',
    'App\\Http\\Requests\\Tutoring\\OfferSendRequestRequest',
    'App\\Http\\Requests\\Tutoring\\OfferSetUserSearchCriteriaRequest',
    'App\\Http\\Requests\\Tutoring\\OfferStoreRequest',
    'App\\Http\\Requests\\Tutoring\\OfferToggleOfferRequest',
    'App\\Http\\Requests\\Tutoring\\OfferUpdateRequest',
    'App\\Http\\Requests\\Tutoring\\SubjectCreateSubjectsRequest',
    'App\\Http\\Requests\\Tutoring\\UserUpdatePasswordRequest',
    'App\\Http\\Requests\\Tutoring\\UserUpdateRequest',
    'App\\Models\\TutoringOffer',
    'App\\Models\\TutoringSubject',
]);
