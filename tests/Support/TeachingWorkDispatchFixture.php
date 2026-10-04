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
