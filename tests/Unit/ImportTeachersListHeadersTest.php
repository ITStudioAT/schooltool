<?php

use App\Jobs\ImportTeachersListJob;

it('imports Kurzzeichen into the teacher short code', function (string $shortHeader) {
    $job = new ImportTeachersListJob((object) ['id' => 1, 'school_id' => 1], 'teachers.xlsx');
    $headers = ['Nachname', 'Vorname', $shortHeader, 'E-Mail'];
    $mapping = (new ReflectionMethod($job, 'validateAndMapHeaders'))->invoke($job, $headers);

    expect($mapping)->toHaveKey($shortHeader, 'Kurz');

    $teachers = (new ReflectionMethod($job, 'mapImportedTeachers'))->invoke($job, [
        [
            'Nachname' => 'Example',
            'Vorname' => 'Teacher',
            $shortHeader => ' abc ',
            'E-Mail' => 'TEACHER@EXAMPLE.TEST',
        ],
    ], $mapping);

    expect($teachers['teacher@example.test']['short'])->toBe('ABC');
})->with(['Kurzzeichen', ' KURZZEICHEN ']);
