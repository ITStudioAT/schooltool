<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

class TeachingWorkDispatchFixture
{
    /** @return list<array<string, mixed>> */
    public static function recipients(): array
    {
        $student = [
            'Rolle' => 'Schülerempfänger', 'Vorname' => 'Ada', 'Nachname' => 'Van Alpha', 'Klasse' => '1A', 'Kurs' => 'INF 1',
            'To' => 'ada@example.test', 'Betreff' => 'Ergebnisse zur Leistungsfeststellung: E-Mails',
            'Modus' => 'Live-Versand (Postmark)', 'Status' => 'Postmark-API-Annahme bestätigt', 'Providerstatus' => 'Sent',
            'Providerkennung' => '11111111-1111-4111-8111-111111111111', 'Providerzeit' => '2026-10-04T02:15:39+02:00',
            'Bestaetigungszeit' => '2026-10-04T02:15:38+02:00', 'Versuchzeit' => '2026-10-04T02:15:37+02:00',
            'HTTPStatus' => '200', 'PostmarkFehlercode' => '0', 'Fehler' => '',
        ];

        return [
            $student,
            array_replace($student, ['Vorname' => 'Bea', 'Nachname' => 'Beta', 'To' => 'bea@example.test',
                'Providerkennung' => '22222222-2222-4222-8222-222222222222', 'Status' => 'Fehlgeschlagen', 'HTTPStatus' => '422']),
            array_replace($student, ['Rolle' => 'Lehrperson', 'Vorname' => 'Teacher', 'Nachname' => 'Test', 'To' => 'teacher@example.test',
                'Providerkennung' => '33333333-3333-4333-8333-333333333333']),
        ];
    }

    /** @param list<array<string, mixed>>|null $recipients
     * @param  array<string, mixed>  $metadata
     */
    public static function text(?array $recipients = null, array $metadata = []): string
    {
        $metadata = array_replace([
            'Leistungsfeststellung' => 'C:\\untrusted-source\\2026-10-02_IT-Grundlagen_INF1', 'Zeitzone' => 'Europe/Vienna',
            'Modus' => 'Live-Versand (Postmark)', 'AktuelleServerpruefung' => ['DeliveryType' => 'Live'],
            'Erstellt' => '2026-10-04T02:12:33+02:00',
        ], $metadata);

        return "VERSANDPROTOKOLL – LIVE-VERSAND (POSTMARK)\n\n".json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
            ."\n\nEMPFÄNGERSTATUS\n\n".json_encode($recipients ?? self::recipients(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
    }

    /** @param list<array<string, mixed>>|null $recipients
     * @param  array<string, mixed>  $metadata
     */
    public static function upload(?array $recipients = null, array $metadata = []): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('Versandprotokoll.txt', self::text($recipients, $metadata));
    }

    public static function officeResultsText(): string
    {
        $tasks = self::combinedTasksText();
        $metadata = json_decode(substr($tasks, strpos($tasks, '{')), true, flags: JSON_THROW_ON_ERROR);
        $metadata['Versandzweck'] = 'Ergebnisbenachrichtigung';
        $metadata['Abgeschlossen'] = $metadata['Erstellt'];
        unset($metadata['Erstellt']);
        $recipient = &$metadata['Empfaenger'][0];
        $recipient['Datensatz'] = $recipient['Datensatzposition'];
        $recipient['Rolle'] = 'Schüler/in';
        $recipient['Betreff'] = 'Ergebnisse zur Leistungsfeststellung: E-Mails – INF 1';
        $recipient['Office_Zustand']['Subject'] = $recipient['Betreff'];
        $recipient['Anhaenge'] = [];
        $recipient['Office_Zustand']['Attachments'] = [];
        $recipient['Office_Zustand']['AttachmentsVerified'] = 0;
        unset($recipient['Datensatzposition'], $recipient['TatsaechlicheAnhaenge'], $recipient);

        return "Ergebnisbenachrichtigung – produktiver Live-Versand (Office/Outlook)\nVersandzweck: Ergebnisbenachrichtigung\n".json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
    }

    public static function officeResultsWithTeacherText(int $studentCount = 14): string
    {
        $text = self::officeResultsText();
        $metadata = json_decode(substr($text, strpos($text, '{')), true, flags: JSON_THROW_ON_ERROR);
        $template = $metadata['Empfaenger'][0];
        $metadata['Aktualisiert_am'] = $metadata['Abgeschlossen'];
        unset($metadata['Abgeschlossen']);
        $metadata['Bestaetigte_Schuelernachrichten'] = $studentCount;
        $metadata['Bestaetigte_Lehrernachrichten'] = 1;
        $metadata['Empfaenger'] = [];
        for ($index = 0; $index <= $studentCount; $index++) {
            $teacher = $index === $studentCount;
            $identity = self::recipients()[$teacher ? 2 : min($index, 1)];
            if (! $teacher && $index > 1) {
                $identity = array_replace($identity, ['Vorname' => 'Student', 'Nachname' => (string) $index, 'To' => "student{$index}@example.test"]);
            }
            $row = array_replace($template, array_intersect_key($identity, array_flip(['Vorname', 'Nachname', 'To'])));
            unset($row['Datensatz'], $row['Anhaenge']);
            $row['Rolle'] = $teacher ? 'Lehrperson' : 'Schüler';
            if (! $teacher) {
                $row['Datensatzposition'] = $index + 1;
            }
            $row['Allgemeine_Anhaenge'] = [];
            $row['Persoenliche_Anhaenge'] = [];
            $row['InternetMessageID'] = $row['Providerkennung'] = "<message{$index}@example.test>";
            $row['SentEntryID'] = "SENT{$index}";
            $row['Office_Zustand'] = array_replace($row['Office_Zustand'], [
                'To' => $row['To'], 'InternetMessageID' => $row['InternetMessageID'], 'SentEntryID' => $row['SentEntryID'],
            ]);
            $metadata['Empfaenger'][] = $row;
        }

        return substr($text, 0, strpos($text, '{')).json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
    }

    public static function nativeOfficeTasksText(int $studentCount = 11): string
    {
        $text = self::combinedTasksText();
        $metadata = json_decode(substr($text, strpos($text, '{')), true, flags: JSON_THROW_ON_ERROR);
        $metadata['Leistungsfeststellungsordner'] = $metadata['Leistungsfeststellung'];
        $metadata['VorbereitungAm'] = $metadata['Erstellt'];
        unset($metadata['Leistungsfeststellung'], $metadata['Erstellt']);
        $row = $metadata['Empfaenger'][0];
        $row['From'] = $row['Account'];
        $row['Betreff'] = 'Leistungsfeststellung E-Mails – INF 1 – Abgabe heute, 16:00 Uhr';
        $row['Office_Zustand']['Subject'] = $row['Betreff'];
        $row['Office_Zustand']['Attachments'][] = ['Name' => 'Personal_MC.pdf', 'SHA256' => str_repeat('b', 64)];
        $row['Office_Zustand']['AttachmentsVerified'] = 2;
        $row['Tatsaechliche_Anhaenge'] = $row['Office_Zustand']['Attachments'];
        $row['Geplante_Anhaenge'] = $row['Tatsaechliche_Anhaenge'];
        unset($row['Account'], $row['SentEntryID'], $row['StoreID'], $row['TatsaechlicheAnhaenge']);
        $metadata['Empfaenger'] = [];
        for ($index = 0; $index < $studentCount; $index++) {
            $recipient = $row;
            $recipient['Datensatzposition'] = $index + 1;
            if ($index > 0) {
                $recipient['Vorname'] = 'Student';
                $recipient['Nachname'] = (string) $index;
                $recipient['To'] = $recipient['Office_Zustand']['To'] = "student{$index}@example.test";
            }
            $recipient['InternetMessageID'] = $recipient['Providerkennung'] = $recipient['Office_Zustand']['InternetMessageID'] = "<task{$index}@example.test>";
            $recipient['Office_Zustand']['SentEntryID'] = "SENT{$index}";
            $metadata['Empfaenger'][] = $recipient;
        }

        return substr($text, 0, strpos($text, '{')).json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
    }

    public static function tasksText(bool $legacy = false): string
    {
        $rows = array_map(fn (array $row): array => array_replace($row, [
            'Betreff' => 'Leistungsfeststellung E-Mails – IT-Grundlagen',
            'Providerkennung' => str_replace('11111111', 'aaaaaaaa', $row['Providerkennung']),
            'Providerzeit' => '2026-10-01T17:02:40+02:00',
        ]), self::recipients());
        if (! $legacy) {
            return self::text($rows, ['Versandzweck' => 'Aufgabenversand']);
        }
        $metadata = ['Laufkennung' => '2026-10-02_IT-Grundlagen_1A',
            'AllgemeinerAnhangQuelle' => 'C:/untrusted/Leistungsfeststellung_E-Mails.pdf', 'Abschlusszeit' => '2026-10-02T17:02:42+02:00'];
        $text = "VERSANDPROTOKOLL – LOKALER MAILPIT-TEST\n\n".json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        foreach (array_slice($rows, 0, 2) as $index => $row) {
            unset($row['Rolle'], $row['HTTPStatus'], $row['PostmarkFehlercode'], $row['Providerstatus']);
            $row = array_replace($row, ['Datensatzposition' => $index + 1, 'Modus' => 'lokaler Mailpit-Test',
                'Status' => 'lokaler Eingang in Mailpit bestätigt', 'Providerkennung' => 'MailpitId'.$index,
                'Providerzeit' => '2026-10-02T17:02:40+02:00']);
            $text .= "\n\nEMPFÄNGER ".($index + 1)."\n".json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        }

        return $text."\n\nBENENNUNGSMIGRATION 03.10.2026: Historische Prüfsummen erhalten.\n\nLokale Terminologieanpassung am 03.10.2026: Historische Belege unverändert.\n";
    }

    public static function combinedTasksText(string $provider = 'Office/Outlook', bool $stopped = false): string
    {
        $row = self::recipients()[0];
        unset($row['Rolle']);
        $row = array_replace($row, ['Datensatzposition' => 1, 'Betreff' => 'Leistungsfeststellung E-Mails – INF 1',
            'Modus' => "Live-Versand ({$provider})", 'Providerzeit' => '2026-10-04T16:59:41.1310000+02:00']);
        if ($provider === 'Office/Outlook') {
            $row = array_replace($row, ['Provider' => 'Office/Outlook/Exchange', 'Account' => 'teacher@example.test',
                'Status' => 'Office-Versand in Gesendete Elemente bestätigt', 'Providerkennung' => '<message@example.test>',
                'InternetMessageID' => '<message@example.test>', 'SentEntryID' => 'SENT123', 'StoreID' => 'STORE123']);
            $row['Office_Zustand'] = ['Account' => 'teacher@example.test', 'To' => $row['To'], 'Subject' => $row['Betreff'],
                'Status' => $row['Status'], 'SentConfirmed' => true, 'AttachmentsVerified' => 1,
                'Attachments' => [['Name' => 'Tasks.pdf', 'SHA256' => str_repeat('a', 64)]],
                'SentEntryID' => 'SENT123', 'SentStoreID' => 'STORE123', 'InternetMessageID' => '<message@example.test>', 'SentOn' => $row['Providerzeit']];
            $row['TatsaechlicheAnhaenge'] = [['Dateiname' => 'Tasks.pdf', 'SHA256' => str_repeat('a', 64)]];
        }
        if ($stopped) {
            $row['Status'] = 'gestoppt – nicht gesendet';
            $row['Providerkennung'] = null;
            $row['Providerzeit'] = null;
        }
        $metadata = ['Versandzweck' => 'Aufgabenversand', 'Modus' => "Live-Versand ({$provider})",
            'Leistungsfeststellung' => 'C:/source/2026-10-02_IT-Grundlagen_Test', 'Erstellt' => '2026-10-04T16:56:41.095637+02:00',
            'Provider' => 'Office/Outlook/Exchange', 'Account' => 'teacher@example.test',
            'AktuelleServerpruefung' => ['DeliveryType' => 'Live'], 'Empfaenger' => [$row]];

        return "Aufgabenversand – produktiver Live-Versand ({$provider})".($stopped ? ' – GESTOPPT' : '')
            ."\nVersandzweck: Aufgabenversand\n".json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
    }

    public static function mailpitTasksText(): string
    {
        $text = self::combinedTasksText('Postmark');

        return str_replace([
            'Aufgabenversand – produktiver Live-Versand (Postmark)',
            'Live-Versand (Postmark)',
            'Postmark-API-Annahme bestätigt',
        ], [
            'Aufgabenversand – Mailpit-Test',
            'Mailpit-Test',
            'lokaler Eingang in Mailpit bestätigt',
        ], $text);
    }

    /** @param array<string, mixed> $metadata */
    public static function teacherTaskTestText(string $provider = 'Postmark', array $metadata = []): string
    {
        $row = array_replace(self::recipients()[2], [
            'Rolle' => 'Lehrperson; einzelner echter Nachrichtentest', 'Datensatzposition' => 1,
            'Betreff' => 'Leistungsfeststellung E-Mails – INF 1',
        ]);
        $values = [
            'Versandzweck' => 'Aufgabenversand', 'Schueleranzahl' => 0, 'Lehreranzahl' => 1,
            'Leistungsfeststellung' => 'C:/source/2026-10-04_E-Mail_INF1',
        ];
        if ($provider === 'Office') {
            return json_encode(array_replace($values, [
                'Testart' => 'Einzeltest über vorhandenes Office-/Exchange-Konto', 'Modus' => 'Office/Microsoft 365',
                'From' => 'teacher@example.test', 'To' => 'teacher@example.test', 'Betreff' => $row['Betreff'],
                'Status' => 'Office-Versand in Gesendete Elemente bestätigt',
                'Gesendetzeit' => '2026-10-04T16:47:22.2670000+02:00', 'InternetMessageID' => '<test@example.test>',
            ], $metadata), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        }

        return "Aufgabenversand – Einzelnachrichtentest – Live-Versand (Postmark)\nVersandzweck: Aufgabenversand\n"
            .json_encode(array_replace($values, [
                'Testart' => 'Einzelnachrichtentest an Lehrperson über Postmark', 'Modus' => 'Live-Versand (Postmark)',
                'Erstellt' => '2026-10-04T16:35:23.117595+02:00', 'Empfaenger' => [$row],
            ], $metadata), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $metadata */
    public static function teacherTestText(string $subject = 'Test der Ergebnisbenachrichtigung: E-Mails', array $metadata = []): string
    {
        $text = self::text([array_replace(self::recipients()[2], ['Betreff' => $subject])], array_replace([
            'Versandzweck' => 'Ergebnisbenachrichtigung', 'Testzweck' => 'Lehrerprüfung der Formatierung',
            'Schueleranzahl' => 0, 'Lehreranzahl' => 1,
        ], $metadata));

        return str_replace('VERSANDPROTOKOLL – LIVE-VERSAND (POSTMARK)', 'VERSANDPROTOKOLL – ERGEBNISBENACHRICHTIGUNG – LIVE-TEST NUR AN LEHRPERSON (POSTMARK)', $text);
    }
}
