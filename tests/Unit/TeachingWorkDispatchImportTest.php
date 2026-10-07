<?php

use App\Services\TeachingWorkDispatchImport;
use Illuminate\Validation\ValidationException;
use Tests\Support\TeachingWorkDispatchFixture;
use Tests\TestCase;

uses(TestCase::class);

test('embedded Mailpit task protocols retain recipients and remain local tests', function () {
    $report = app(TeachingWorkDispatchImport::class)->parse(TeachingWorkDispatchFixture::mailpitTasksText());

    expect($report['purpose'])->toBe('tasks')->and($report['title'])->toBe('e-mails')
        ->and($report['metadata']['Zeitzone'])->toBe('Europe/Vienna')
        ->and($report['recipients'][0]['To'])->toBe('ada@example.test')
        ->and($report['recipients'][0]['Rolle'])->toBe('Schülerempfänger')
        ->and($report['mode'])->toBe('test')->and($report['live'])->toBeFalse();
});

test('embedded Mailpit task protocols reject contradictory or missing evidence', function (string $case) {
    $text = TeachingWorkDispatchFixture::mailpitTasksText();
    $text = match ($case) {
        'purpose' => str_replace('"Versandzweck": "Aufgabenversand"', '"Versandzweck": "Ergebnisbenachrichtigung"', $text),
        'mode' => str_replace('"Modus": "Mailpit-Test"', '"Modus": "Live-Versand (Postmark)"', $text),
        'recipient order' => str_replace('"Datensatzposition": 1', '"Datensatzposition": 2', $text),
        'recipients' => str_replace('"Empfaenger":', '"Andere":', $text),
        'timezone' => str_replace('16:56:41.095637+02:00', '16:56:41.095637+09:00', $text),
        'subject' => str_replace('Leistungsfeststellung E-Mails – INF 1', 'Ergebnisse zur Leistungsfeststellung: E-Mails', $text),
    };

    expect(fn () => app(TeachingWorkDispatchImport::class)->parse($text))->toThrow(ValidationException::class);
})->with(['purpose', 'mode', 'recipient order', 'recipients', 'timezone', 'subject']);

test('Office result protocols recognize numbered student aliases and completion timezone', function () {
    $report = app(TeachingWorkDispatchImport::class)->parse(TeachingWorkDispatchFixture::officeResultsText());
    expect($report['purpose'])->toBe('results')->and($report['live'])->toBeTrue()
        ->and($report['metadata']['Zeitzone'])->toBe('Europe/Vienna')
        ->and($report['recipients'][0]['Rolle'])->toBe('Schülerempfänger');
});

test('Office result protocols reject conflicting numbering and completion offsets', function (string $case) {
    $text = TeachingWorkDispatchFixture::officeResultsText();
    $text = $case === 'numbering' ? str_replace('"Datensatz": 1', '"Datensatz": 2', $text)
        : str_replace('16:56:41.095637+02:00', '16:56:41.095637+09:00', $text);
    expect(fn () => app(TeachingWorkDispatchImport::class)->parse($text))->toThrow(ValidationException::class);
})->with(['numbering', 'offset']);

test('single teacher task tests recognize Postmark and raw Office protocols without student live status', function (string $provider) {
    $report = app(TeachingWorkDispatchImport::class)->parse(TeachingWorkDispatchFixture::teacherTaskTestText($provider));

    expect($report['purpose'])->toBe('tasks');
    expect($report['title'])->toBe('e-mails');
    expect($report['date'])->toBe('2026-10-04');
    expect($report['metadata']['Zeitzone'])->toBe('Europe/Vienna');
    expect($report['mode'])->toBe('teacher_test');
    expect($report['live'])->toBeFalse();
    expect($report['recipients'])->toHaveCount(1);
    expect($report['recipients'][0]['Rolle'])->toBe('Lehrperson');
})->with(['Postmark', 'Office']);

test('single teacher task tests reject contradictory purpose counts test kind mode timezone and subject', function (string $provider, string $case) {
    $metadata = match ($case) {
        'purpose' => ['Versandzweck' => 'Ergebnisbenachrichtigung'],
        'student count' => ['Schueleranzahl' => 1],
        'teacher count' => ['Lehreranzahl' => 2],
        'test kind' => ['Testart' => ''],
        'mode' => ['Modus' => 'unknown'],
        'timezone' => ['Zeitzone' => 'UTC'],
        default => [],
    };
    $text = TeachingWorkDispatchFixture::teacherTaskTestText($provider, $metadata);
    $text = match ($case) {
        'subject' => str_replace('Leistungsfeststellung E-Mails – INF 1', 'Andere Nachricht: E-Mails', $text),
        default => $text,
    };

    expect(fn () => app(TeachingWorkDispatchImport::class)->parse($text))->toThrow(ValidationException::class);
})->with(['Postmark', 'Office'])->with(['purpose', 'student count', 'teacher count', 'test kind', 'mode', 'timezone', 'subject']);

test('single Postmark teacher task tests reject student recipients', function () {
    $text = str_replace('Lehrperson; einzelner echter Nachrichtentest', 'Schülerempfänger', TeachingWorkDispatchFixture::teacherTaskTestText());

    expect(fn () => app(TeachingWorkDispatchImport::class)->parse($text))->toThrow(ValidationException::class);
});

test('combined task protocols recognize Outlook and stopped Postmark runs with embedded recipients', function (string $provider, bool $stopped) {
    $report = app(TeachingWorkDispatchImport::class)->parse(TeachingWorkDispatchFixture::combinedTasksText($provider, $stopped));

    expect($report['purpose'])->toBe('tasks')->and($report['title'])->toBe('e-mails')
        ->and($report['metadata']['Zeitzone'])->toBe('Europe/Vienna')
        ->and($report['recipients'][0]['Rolle'])->toBe('Schülerempfänger')
        ->and($report['live'])->toBe(! $stopped);
})->with(['Outlook' => ['Office/Outlook', false], 'stopped Postmark' => ['Postmark', true]]);

test('combined protocols reject contradictory purpose mode recipient order and timezone', function (string $case) {
    $text = TeachingWorkDispatchFixture::combinedTasksText();
    $text = match ($case) {
        'purpose' => str_replace('Versandzweck: Aufgabenversand', 'Versandzweck: Ergebnisbenachrichtigung', $text),
        'mode' => str_replace('"Modus": "Live-Versand (Office\/Outlook)"', '"Modus": "Live-Versand (Postmark)"', $text),
        'recipient order' => str_replace('"Datensatzposition": 1', '"Datensatzposition": 2', $text),
        'timezone' => str_replace('16:56:41.095637+02:00', '16:56:41.095637+09:00', $text),
    };

    expect(fn () => app(TeachingWorkDispatchImport::class)->parse($text))->toThrow(ValidationException::class);
})->with(['purpose', 'mode', 'recipient order', 'timezone']);

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
