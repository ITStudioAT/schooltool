<?php

test('retired tutoring classes are no longer loadable', function (string $class): void {
    expect(class_exists($class))->toBeFalse();
})->with([
    'App\\Http\\Requests\\Admin\\SchoolToolSaveTutoringSettingsRequest',
    'App\\Http\\Requests\\Admin\\Tutoring\\OfferIndexRequest',
    'App\\Http\\Requests\\Admin\\Tutoring\\SubjectUpdateSubjectRequest',
    'App\\Http\\Requests\\Admin\\Tutoring\\UserConfirmUsersRequest',
    'App\\Http\\Requests\\Admin\\Tutoring\\UserDeleteUsersRequest',
    'App\\Http\\Requests\\Admin\\Tutoring\\UserIndexRequest',
    'App\\Http\\Requests\\Admin\\Tutoring\\UserStoreRequest',
    'App\\Http\\Requests\\Admin\\Tutoring\\UserUpdateRequest',
    'App\\Models\\TutoringSubject',
]);
