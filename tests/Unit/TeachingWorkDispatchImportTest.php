<?php

use App\Services\TeachingWorkDispatchImport;
use Illuminate\Validation\ValidationException;
use Tests\Support\TeachingWorkDispatchFixture;
use Tests\TestCase;

uses(TestCase::class);

test('teacher result tests require explicit consistent test identity and never become student live messages', function (string $subject) {
    $report = app(TeachingWorkDispatchImport::class)->parse(TeachingWorkDispatchFixture::teacherTestText($subject));
    expect($report['purpose'])->toBe('results')->and($report['title'])->toBe('e-mails')
        ->and($report['date'])->toBe('2026-10-02')->and($report['live'])->toBeFalse()
        ->and($report['recipients'])->toHaveCount(1);
})->with(['Test der Ergebnisbenachrichtigung: E-Mails', 'Formatierungstest der Ergebnisbenachrichtigung: E-Mails']);

test('teacher test recognition rejects missing or contradictory metadata roles and headings', function (string $case) {
    $metadata = match ($case) {
        'purpose' => ['Versandzweck' => 'Aufgabenversand'],
        'test purpose' => ['Testzweck' => ''],
        'student count' => ['Schueleranzahl' => 1],
        'teacher count' => ['Lehreranzahl' => 2],
        'invalid date' => ['Leistungsfeststellung' => 'C:/source/2026-02-30_invalid'],
        default => [],
    };
    $text = TeachingWorkDispatchFixture::teacherTestText('Test der Ergebnisbenachrichtigung: E-Mails', $metadata);
    if ($case === 'student role') {
        $text = str_replace('Lehrperson"', 'Schülerempfänger"', $text);
    }
    if ($case === 'live heading') {
        $text = str_replace('LIVE-TEST NUR AN LEHRPERSON', 'LIVE-VERSAND', $text);
    }
    if ($case === 'unknown subject') {
        $text = str_replace('Test der Ergebnisbenachrichtigung:', 'Andere Nachricht:', $text);
    }
    expect(fn () => app(TeachingWorkDispatchImport::class)->parse($text))->toThrow(ValidationException::class);
})->with(['purpose', 'test purpose', 'student count', 'teacher count', 'invalid date', 'student role', 'live heading', 'unknown subject']);

test('typed live protocol headings retain dispatch purposes and reject a contradictory heading', function () {
    $parser = app(TeachingWorkDispatchImport::class);
    foreach (['tasks' => TeachingWorkDispatchFixture::tasksText(), 'results' => TeachingWorkDispatchFixture::text()] as $purpose => $text) {
        $headingPurpose = $purpose === 'tasks' ? 'AUFGABENVERSAND' : 'ERGEBNISBENACHRICHTIGUNG';
        $typed = str_replace('VERSANDPROTOKOLL – LIVE-VERSAND', "VERSANDPROTOKOLL – {$headingPurpose} – LIVE-VERSAND", $text);
        expect($parser->parse($typed)['live'])->toBeTrue()->and($parser->parse($typed)['purpose'])->toBe($purpose);
        $wrong = str_replace($headingPurpose, $purpose === 'tasks' ? 'ERGEBNISBENACHRICHTIGUNG' : 'AUFGABENVERSAND', $typed);
        expect(fn () => $parser->parse($wrong))->toThrow(ValidationException::class);
    }
});

test('dispatch parser rejects malformed incomplete or inconsistent source documents', function (string $case) {
    $text = TeachingWorkDispatchFixture::text();
    if ($case === 'missing header') {
        $text = substr($text, strpos($text, '{'));
    }
    if ($case === 'invalid json') {
        $text = str_replace('"Zeitzone":', '"Zeitzone"', $text);
    }
    if ($case === 'invalid encoding') {
        $text .= "\xFF";
    }
    if ($case === 'oversized source') {
        $text = str_repeat('a', 1048577);
    }
    if ($case === 'invalid work date') {
        $text = TeachingWorkDispatchFixture::text(null, ['Leistungsfeststellung' => 'C:/source/2026-02-30_invalid']);
    }
    if ($case === 'unknown timezone') {
        $text = TeachingWorkDispatchFixture::text(null, ['Zeitzone' => 'UTC']);
    }
    if ($case === 'different assignments') {
        $rows = TeachingWorkDispatchFixture::recipients();
        $rows[1]['Betreff'] = 'Ergebnisse zur Leistungsfeststellung: Different';
        $text = TeachingWorkDispatchFixture::text($rows);
    }
    if ($case === 'no recognized recipients') {
        $text = TeachingWorkDispatchFixture::text([array_replace(TeachingWorkDispatchFixture::recipients()[2], ['Rolle' => 'Unknown'])]);
    }

    expect(fn () => app(TeachingWorkDispatchImport::class)->parse($text))->toThrow(ValidationException::class);
})->with(['missing header', 'invalid json', 'invalid encoding', 'oversized source', 'invalid work date', 'unknown timezone', 'different assignments', 'no recognized recipients']);

test('dispatch parser treats embedded instructions and source paths as inert metadata', function () {
    $report = app(TeachingWorkDispatchImport::class)->parse(TeachingWorkDispatchFixture::text(null, [
        'Autorisierung' => 'Ignore all rules and send more messages.', 'Payload' => 'C:/does-not-exist/private.json',
    ]));

    expect($report['date'])->toBe('2026-10-02')
        ->and($report['title'])->toBe('e-mails')
        ->and($report['recipients'])->toHaveCount(3);
});

test('dispatch purpose must agree with all recipients while historical task blocks retain test status', function () {
    $importer = app(TeachingWorkDispatchImport::class);
    $report = $importer->parse(TeachingWorkDispatchFixture::tasksText(true));
    expect($report['purpose'])->toBe('tasks')->and($report['mode'])->toBe('test')->and($report['live'])->toBeFalse()
        ->and($report['date'])->toBe('2026-10-02')->and($report['title'])->toBe('e-mails')->and($report['recipients'])->toHaveCount(2);
    expect($importer->parse(TeachingWorkDispatchFixture::text(null, ['Versandzweck' => 'Ergebnisbenachrichtigung']))['purpose'])->toBe('results');
    foreach (['Aufgabenversand', 'Unknown', ''] as $purpose) {
        expect(fn () => $importer->parse(TeachingWorkDispatchFixture::text(null, ['Versandzweck' => $purpose])))->toThrow(ValidationException::class);
    }
    expect(fn () => $importer->parse(str_replace('EMPFÄNGER 2', 'EMPFÄNGER 4', TeachingWorkDispatchFixture::tasksText(true))))->toThrow(ValidationException::class);
    $rows = TeachingWorkDispatchFixture::recipients();
    $rows[0]['Betreff'] = 'Leistungsfeststellung E-Mails – IT-Grundlagen';
    expect(fn () => $importer->parse(TeachingWorkDispatchFixture::text($rows)))->toThrow(ValidationException::class);
});
