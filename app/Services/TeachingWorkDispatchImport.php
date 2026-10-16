<?php

namespace App\Services;

use App\Models\Import116;
use App\Models\TeachingCourse;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Support\PrivateImportSourceFile;
use App\Support\SchooltoolAssessmentJson;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeachingWorkDispatchImport
{
    public function __construct(private TeachingWorkMarkdownImport $evaluations) {}

    /** @return array{metadata: array<string, mixed>, recipients: list<array<string, mixed>>, date: string, title: string, personal_results: bool, live: bool, purpose: string, mode: string} */
    public function parse(string $text): array
    {
        if (strlen($text) > 1048576 || ! preg_match('//u', $text) || str_contains($text, "\0")) {
            $this->reject('Ein UTF-8-Versandprotokoll mit höchstens 1 MB auswählen.');
        }
        $text = str_replace(["\r\n", "\r"], "\n", preg_replace('/\A\xEF\xBB\xBF/', '', $text));
        if (str_starts_with(ltrim($text), '{')) {
            try {
                $preparation = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $this->reject('Ungültiges JSON im Versandprotokoll.');
            }
            if (($preparation['kind'] ?? null) === 'schooltool.preparation') {
                if (($preparation['schema_version'] ?? null) === 2) {
                    return $this->documentPreparation($preparation);
                }
                if (($preparation['schema_version'] ?? null) !== 1 || ($preparation['Versandzweck'] ?? null) !== 'Aufgabenversand'
                    || ! is_array($preparation['Empfaenger'] ?? null)) {
                    $this->reject('Ungültiges Schooltool-Vorbereitungspaket.');
                }
                $this->validateDispatchReadiness($preparation);
                $createdValue = $this->value($preparation, 'Erstellt');
                $created = $this->providerTime($createdValue);
                if ($created === null || (! str_ends_with($createdValue, 'Z')
                    && (new DateTimeImmutable($created))->setTimezone(new DateTimeZone('Europe/Vienna'))->format('P') !== substr($createdValue, -6))) {
                    $this->reject('Vorbereitungszeit benötigt ein gültiges Datum mit passendem Europe/Vienna-Offset.');
                }
                foreach ($preparation['Empfaenger'] as $recipient) {
                    if (! is_array($recipient) || ($recipient['Status'] ?? null) !== 'vorbereitet'
                        || isset($recipient['Office_Zustand']) || isset($recipient['Native_Terminierung'])
                        || isset($recipient['Providerzeit']) || isset($recipient['Providerkennung'])) {
                        $this->reject('Vorbereitung enthält unzulässige Versandbelege.');
                    }
                }
                $report = $this->parse("Aufgabenversand – produktiver Live-Versand (Office/Outlook)\nVersandzweck: Aufgabenversand\n".$text);
                $report['live'] = false;
                $report['mode'] = 'prepared';

                return $report;
            }
        }
        $postmarkTeacherTest = preg_match('/\AAufgabenversand – Einzelnachrichtentest – Live-Versand \(Postmark\)\nVersandzweck: Aufgabenversand\n(\{.*\})\s*\z/su', $text, $teacherSections);
        $officeTeacherTest = str_starts_with(ltrim($text), '{');
        $teacherTaskTest = $postmarkTeacherTest || $officeTeacherTest;
        $combined = preg_match('/\A(Aufgabenversand|Ergebnisbenachrichtigung) – produktiver Live-Versand \((Postmark|Office\/Outlook)\)( – GESTOPPT)?\nVersandzweck: (Aufgabenversand|Ergebnisbenachrichtigung)\n(\{.*\})\s*\z/su', $text, $combinedSections);
        $mailpitTest = preg_match('/\AAufgabenversand – Mailpit-Test\nVersandzweck: Aufgabenversand\n(\{.*\})\s*\z/su', $text, $mailpitSections);
        $embeddedRecipients = $combined || $mailpitTest;
        if (! $embeddedRecipients && ! $teacherTaskTest && ! preg_match('/\AVERSANDPROTOKOLL[^\n]*\n+(.+?)\n(EMPFÄNGERSTATUS|EMPFÄNGER 1)\s*\n(.*)\z/su', $text, $sections)) {
            $this->reject('Versandprotokoll mit Metadaten und Empfängereinträgen erwartet.');
        }
        try {
            $sourceJson = $teacherTaskTest ? ($officeTeacherTest ? $text : $teacherSections[1]) : ($mailpitTest ? $mailpitSections[1] : ($combined ? $combinedSections[5] : $sections[1]));
            $metadata = json_decode($sourceJson, true, 32, JSON_THROW_ON_ERROR);
            $legacy = ! $embeddedRecipients && ! $teacherTaskTest && $sections[2] === 'EMPFÄNGER 1';
            $recipients = $officeTeacherTest && is_array($metadata) ? [array_replace($metadata, [
                'Rolle' => 'Lehrperson', 'Providerzeit' => $metadata['Gesendetzeit'] ?? null,
                'Providerkennung' => $metadata['InternetMessageID'] ?? null,
            ])] : ($embeddedRecipients || $postmarkTeacherTest ? ($metadata['Empfaenger'] ?? null)
                : ($legacy ? $this->legacyRecipients($sections[3]) : json_decode($sections[3], true, 32, JSON_THROW_ON_ERROR)));
        } catch (JsonException) {
            $this->reject('Das Versandprotokoll enthält ungültiges JSON.');
        }
        if (! is_array($metadata) || ! is_array($recipients) || ! array_is_list($recipients) || count($recipients) > 100 || $recipients === []) {
            $this->reject('Das Versandprotokoll muss 1 bis 100 Empfängereinträge enthalten.');
        }
        if ($teacherTaskTest) {
            if ($this->value($metadata, 'Versandzweck') !== 'Aufgabenversand'
                || $this->value($metadata, 'Schueleranzahl') !== '0' || $this->value($metadata, 'Lehreranzahl') !== '1'
                || count($recipients) !== 1
                || $this->value($metadata, 'Testart') !== ($officeTeacherTest ? 'Einzeltest über vorhandenes Office-/Exchange-Konto' : 'Einzelnachrichtentest an Lehrperson über Postmark')
                || $this->value($metadata, 'Modus') !== ($officeTeacherTest ? 'Office/Microsoft 365' : 'Live-Versand (Postmark)')
                || ($officeTeacherTest && (isset($metadata['Empfaenger']) || (isset($metadata['Rolle']) && $this->value($metadata, 'Rolle') !== 'Lehrperson')))
                || ! is_array($recipients[0])
                || ! in_array($this->value($recipients[0], 'Rolle'), ['Lehrperson', 'Lehrperson; einzelner echter Nachrichtentest'], true)
                || ! filter_var($this->value($recipients[0], 'To'), FILTER_VALIDATE_EMAIL)
                || (! $officeTeacherTest && ($recipients[0]['Datensatzposition'] ?? null) !== 1)) {
                $this->reject('Einzeltest benötigt eindeutige Lehrerempfänger, Testart und Aufgabenversand-Metadaten.');
            }
            $recipients[0]['Rolle'] = 'Lehrperson';
            unset($metadata['Empfaenger']);
        }
        if ($embeddedRecipients) {
            if ($mailpitTest ? ($this->value($metadata, 'Versandzweck') !== 'Aufgabenversand' || $this->value($metadata, 'Modus') !== 'Mailpit-Test')
                : ($combinedSections[1] !== $combinedSections[4] || $this->value($metadata, 'Versandzweck') !== $combinedSections[1]
                    || $this->value($metadata, 'Modus') !== "Live-Versand ({$combinedSections[2]})")) {
                $this->reject('Protokollüberschrift, Versandzweck und Modus widersprechen einander.');
            }
            $officeProtocol = $combined && $combinedSections[2] === 'Office/Outlook';
            if ($officeProtocol && isset($metadata['Leistungsfeststellungsordner'])) {
                if (isset($metadata['Leistungsfeststellung']) && $metadata['Leistungsfeststellung'] !== $metadata['Leistungsfeststellungsordner']) {
                    $this->reject('Widersprüchliche Leistungsfeststellungsordner im Office-Protokoll.');
                }
                $metadata['Leistungsfeststellung'] ??= $metadata['Leistungsfeststellungsordner'];
            }
            foreach ($recipients as $index => &$recipient) {
                $officeResults = $combined && $combinedSections[1] === 'Ergebnisbenachrichtigung' && $combinedSections[2] === 'Office/Outlook';
                $position = is_array($recipient) ? ($recipient['Datensatzposition'] ?? ($officeResults ? ($recipient['Datensatz'] ?? null) : null)) : null;
                $separateTeacher = $officeResults && is_array($recipient) && ($recipient['Rolle'] ?? null) === 'Lehrperson' && $position === null
                    && $index === count($recipients) - 1 && count(array_filter($recipients, fn (mixed $row): bool => is_array($row) && ($row['Rolle'] ?? null) === 'Lehrperson')) === 1;
                if (! is_array($recipient) || (! $separateTeacher && $position !== $index + 1)
                    || (isset($recipient['Datensatz'], $recipient['Datensatzposition']) && $recipient['Datensatz'] !== $recipient['Datensatzposition'])) {
                    $this->reject('Empfängerfolge im Versandprotokoll nicht erkannt.');
                }
                if ($separateTeacher && ! $this->confirmedOfficeSend($recipient, $metadata)) {
                    $this->reject('Separate Lehrermail benötigt vollständige Office-Versandbelege.');
                }
                if ($officeResults && in_array($recipient['Rolle'] ?? null, ['Schüler/in', 'Schüler'], true)) {
                    $recipient['Rolle'] = 'Schülerempfänger';
                }
                $recipient['Rolle'] ??= 'Schülerempfänger';
                if ($officeResults && ! in_array($recipient['Rolle'], ['Schülerempfänger', 'Lehrperson'], true)) {
                    $this->reject('Empfängerrolle im Office-Ergebnisprotokoll nicht erkannt.');
                }
            }
            unset($recipient, $metadata['Empfaenger']);
            if ($officeProtocol) {
                foreach (['Bestaetigte_Schuelernachrichten' => 'Schülerempfänger', 'Bestaetigte_Lehrernachrichten' => 'Lehrperson', 'BestaetigteNachrichten' => null] as $field => $role) {
                    if (isset($metadata[$field]) && $metadata[$field] !== count(array_filter($recipients, fn (array $row): bool => ($role === null || $row['Rolle'] === $role) && $this->confirmedOfficeSend($row, $metadata)))) {
                        $this->reject('Office-Versandprotokoll: bestätigte Empfängeranzahl widerspricht den Versandbelegen.');
                    }
                }
            }
        }
        if ($embeddedRecipients || $teacherTaskTest) {
            $createdValue = $this->value($metadata, $officeTeacherTest ? 'Gesendetzeit' : 'Erstellt');
            if ($combined && $combinedSections[1] === 'Ergebnisbenachrichtigung' && $combinedSections[2] === 'Office/Outlook' && $createdValue === '') {
                $createdValue = $this->value($metadata, 'Abgeschlossen') ?: $this->value($metadata, 'Aktualisiert_am');
            }
            if ($combined && $combinedSections[2] === 'Office/Outlook' && $createdValue === '') {
                $createdValue = $this->value($metadata, 'VorbereitungAm') ?: $this->value($metadata, 'AbgeschlossenAm');
            }
            $created = $this->providerTime($createdValue);
            if (! isset($metadata['Zeitzone']) && $created !== null
                && (new DateTimeImmutable($created))->setTimezone(new DateTimeZone('Europe/Vienna'))->format('P') === substr($createdValue, -6)) {
                $metadata['Zeitzone'] = 'Europe/Vienna';
            }
        }
        $source = $this->value($metadata, 'Leistungsfeststellung') ?: ($legacy ? $this->value($metadata, 'Laufkennung') : '');
        $folder = basename(str_replace('\\', '/', rtrim($source, '/\\')));
        if (! preg_match('/\A(\d{4}-\d{2}-\d{2})_/', $folder, $date)
            || ! $this->validDate($date[1]) || (! ($legacy && ! isset($metadata['Zeitzone'])) && $this->value($metadata, 'Zeitzone') !== 'Europe/Vienna')) {
            $this->reject('Arbeitsdatum im Leistungsfeststellungsordner und Zeitzone Europe/Vienna fehlen.');
        }
        $title = null;
        $purpose = null;
        $personalResults = false;
        $heading = trim(strtok($text, "\n"));
        $teacherTest = $teacherTaskTest || ($heading === 'VERSANDPROTOKOLL – ERGEBNISBENACHRICHTIGUNG – LIVE-TEST NUR AN LEHRPERSON (POSTMARK)'
            && $this->value($metadata, 'Versandzweck') === 'Ergebnisbenachrichtigung'
            && $this->value($metadata, 'Testzweck') !== ''
            && $this->value($metadata, 'Schueleranzahl') === '0'
            && $this->value($metadata, 'Lehreranzahl') === (string) count($recipients)
            && collect($recipients)->every(fn (mixed $row): bool => is_array($row) && $this->value($row, 'Rolle') === 'Lehrperson'));
        foreach ($recipients as $recipient) {
            if (! is_array($recipient) || array_is_list($recipient)) {
                $this->reject('Ungültiger Empfängereintrag.');
            }
            if (! in_array($this->value($recipient, 'Rolle'), ['Schülerempfänger', 'Lehrperson'], true)) {
                continue;
            }
            $this->plannedTime($recipient, $metadata);
            $subject = $this->value($recipient, 'Betreff');
            if (preg_match('/\AErgebnisse zur Leistungsfeststellung:\s*(.+)\z/u', $subject, $matches)) {
                $rowPurpose = 'results';
            } elseif ($combined && $combinedSections[1] === 'Ergebnisbenachrichtigung' && $combinedSections[2] === 'Office/Outlook'
                && preg_match('/\ABeurteilung zur Leistungsfeststellung (?:„([^„“\r\n]+)“|"([^"\r\n]+)"|([^„“"\r\n]+)) auf Schooltool\z/u', $subject, $resultSubject)) {
                $matches = [null, ($resultSubject[1] ?? '') ?: (($resultSubject[2] ?? '') ?: ($resultSubject[3] ?? ''))];
                $rowPurpose = 'results';
            } elseif ($combined && $combinedSections[1] === 'Ergebnisbenachrichtigung' && $combinedSections[2] === 'Office/Outlook'
                && $this->value($recipient, 'Rolle') === 'Schülerempfänger'
                && preg_match('/\ADeine korrigierte Leistungsfeststellung ([^\r\n]+?) – ([\p{L}\p{N}]+), Gruppe ([1-9]\d*)\z/u', $subject, $matches)) {
                $subjectClass = $this->normalize($matches[2]);
                $subjectGroup = $matches[3];
                if ($subjectClass !== $this->normalize($this->value($recipient, 'Klasse'))
                    || ! preg_match('/\A.+? [-–] '.preg_quote($subjectClass, '/').' [-–] '.preg_quote($subjectGroup, '/').'\z/u', $this->normalize($this->value($recipient, 'Kurs')))) {
                    $this->reject('Klasse oder Gruppe im persönlichen Ergebnisbetreff widerspricht dem Empfänger-Kurs.');
                }
                $personalResults = true;
                $rowPurpose = 'results';
            } elseif ($teacherTest && preg_match('/\A(?:Test|Formatierungstest) der Ergebnisbenachrichtigung:\s*(.+)\z/u', $subject, $matches)) {
                $rowPurpose = 'results';
            } elseif (preg_match('/\A(?:Aufgaben zur Leistungsfeststellung:\s*(.+)|Leistungsfeststellung:?\s+(.+?)\s+–\s+.+)\z/u', $subject, $matches)) {
                $rowPurpose = 'tasks';
            } else {
                $this->reject('Die Nachricht „'.mb_substr($subject, 0, 255).'“ lässt sich keiner unterstützten Aufgaben- oder Ergebnisnachricht zuordnen. Ein Lehrer-Test benötigt den eindeutigen Testzweck und die passenden Empfängerangaben.');
            }
            $assignment = $this->assignment(($matches[1] ?? '') ?: ($matches[2] ?? ''));
            if ($assignment === '' || ($title !== null && $title !== $assignment) || ($purpose !== null && $purpose !== $rowPurpose)) {
                $this->reject('Die Schülernachrichten gehören zu unterschiedlichen Arbeiten.');
            }
            $title = $assignment;
            $purpose = $rowPurpose;
        }
        if ($title === null) {
            $this->reject('Keine zuordenbare Aufgaben- oder Ergebnisnachricht im Versandprotokoll.');
        }
        if ($teacherTaskTest && $purpose !== 'tasks') {
            $this->reject('Lehrer-Einzeltest und Aufgabenbetreff widersprechen einander.');
        }
        $declaredPurpose = $this->value($metadata, 'Versandzweck');
        if (array_key_exists('Versandzweck', $metadata) && $declaredPurpose !== ($purpose === 'tasks' ? 'Aufgabenversand' : 'Ergebnisbenachrichtigung')) {
            $this->reject('Versandzweck und Schülerbetreff widersprechen einander.');
        }
        if (preg_match('/\AVERSANDPROTOKOLL – (AUFGABENVERSAND|ERGEBNISBENACHRICHTIGUNG) – /u', $heading, $headingPurpose)
            && ($headingPurpose[1] === 'AUFGABENVERSAND' ? 'tasks' : 'results') !== $purpose) {
            $this->reject('Protokollüberschrift und Nachrichten gehören zu unterschiedlichen Versandarten.');
        }
        if ($legacy && ($purpose !== 'tasks' || trim(strtok($text, "\n")) !== 'VERSANDPROTOKOLL – LOKALER MAILPIT-TEST')) {
            $this->reject('Dieses historische Format wird als lokaler Aufgabenversand-Test unterstützt.');
        }

        return ['metadata' => $metadata, 'recipients' => $recipients, 'date' => $date[1], 'title' => $title, 'personal_results' => $personalResults,
            'purpose' => $purpose, 'mode' => $teacherTest ? 'teacher_test' : ($legacy || str_contains(mb_strtolower(strtok($text, "\n").' '.$this->value($metadata, 'Modus')), 'mailpit') ? 'test' : 'unconfirmed'),
            'live' => $combined ? ($combinedSections[3] ?? '') === ''
                : (bool) preg_match('/\AVERSANDPROTOKOLL – (?:(?:AUFGABENVERSAND|ERGEBNISBENACHRICHTIGUNG) – )?LIVE-VERSAND \(POSTMARK\)\z/u', $heading)];
    }

    /** @param array<string, mixed> $package
     * @return array<string, mixed>
     */
    private function documentPreparation(array $package): array
    {
        $keys = ['schema_version', 'kind', 'stage', 'work_id', 'course_id', 'prepared_at', 'timezone', 'exercise', 'participants', 'assignment', 'preparation_checksum'];
        if (array_diff(array_keys($package), $keys) !== [] || array_diff($keys, array_keys($package)) !== []
            || $package['stage'] !== 'assignment_created' || $package['timezone'] !== 'Europe/Vienna'
            || ! is_int($package['work_id']) || $package['work_id'] < 1 || ! is_int($package['course_id']) || $package['course_id'] < 1
            || ! is_string($package['prepared_at']) || ! is_string($package['preparation_checksum'])) {
            $this->reject('Ungültige Aufgaben-Vorbereitung; keine Versand- oder Bereitstellungsbelege erlaubt.');
        }
        $unsigned = $package;
        unset($unsigned['preparation_checksum']);
        if (! hash_equals(SchooltoolAssessmentJson::digest($unsigned), $package['preparation_checksum'])) {
            $this->reject('Prüfsumme der Aufgaben-Vorbereitung stimmt nicht.');
        }
        $created = $this->providerTime($package['prepared_at']);
        if ($created === null || (new DateTimeImmutable($created)) > new DateTimeImmutable(now()->toISOString())
            || (! str_ends_with($package['prepared_at'], 'Z')
                && (new DateTimeImmutable($created))->setTimezone(new DateTimeZone('Europe/Vienna'))->format('P') !== substr($package['prepared_at'], -6))) {
            $this->reject('Aufgaben-Vorbereitung benötigt einen tatsächlichen Erstellungszeitpunkt mit gültiger Zeitzone.');
        }
        $exercise = $package['exercise'];
        if (! is_array($exercise) || array_keys($exercise) === []
            || array_diff(array_keys($exercise), ['title', 'course_title', 'scheduled_date']) !== []
            || count($exercise) !== 3 || ! is_string($exercise['title'] ?? null) || trim($exercise['title']) === ''
            || ! is_string($exercise['course_title'] ?? null) || trim($exercise['course_title']) === ''
            || ! is_string($exercise['scheduled_date'] ?? null) || ! $this->validDate($exercise['scheduled_date'])) {
            $this->reject('Titel, Kurs und Durchführungstermin der Aufgaben-Vorbereitung fehlen.');
        }
        $assignment = $package['assignment'];
        if (! is_array($assignment) || array_diff(array_keys($assignment), ['filename', 'sha256']) !== [] || count($assignment) !== 2
            || ! is_string($assignment['filename'] ?? null) || ! preg_match('/\AAufgabenstellung_[^\/\\\\\x00-\x1f:]+\.pdf\z/u', $assignment['filename'])
            || ! is_string($assignment['sha256'] ?? null) || ! preg_match('/\A[0-9a-f]{64}\z/', $assignment['sha256'])) {
            $this->reject('Aufgaben-Vorbereitung benötigt eine eindeutige Aufgaben-PDF mit Prüfsumme.');
        }
        if (! is_array($package['participants']) || ! array_is_list($package['participants'])
            || $package['participants'] === [] || count($package['participants']) > 100) {
            $this->reject('Aufgaben-Vorbereitung benötigt 1 bis 100 Teilnehmer.');
        }
        $recipients = [];
        $ids = [];
        foreach ($package['participants'] as $person) {
            $personKeys = ['participant_id', 'schooltool_person_id', 'first_name', 'last_name', 'class_name'];
            if (! is_array($person) || array_diff(array_keys($person), $personKeys) !== [] || count($person) !== count($personKeys)
                || ! is_string($person['participant_id'] ?? null) || trim($person['participant_id']) === ''
                || in_array($person['participant_id'], $ids, true)
                || (($person['schooltool_person_id'] ?? null) !== null && (! is_string($person['schooltool_person_id']) || ! preg_match('/\A[1-9][0-9]*\z/', $person['schooltool_person_id'])))
                || ! is_string($person['first_name'] ?? null) || trim($person['first_name']) === ''
                || ! is_string($person['last_name'] ?? null) || trim($person['last_name']) === ''
                || ! is_string($person['class_name'] ?? null) || trim($person['class_name']) === '') {
                $this->reject('Unvollständige oder doppelte Teilnehmerkennung in der Aufgaben-Vorbereitung.');
            }
            $ids[] = $person['participant_id'];
            $recipients[] = ['Vorname' => $person['first_name'], 'Nachname' => $person['last_name'], 'Klasse' => $person['class_name'],
                'Kurs' => $exercise['course_title'], 'SchooltoolPersonId' => $person['schooltool_person_id'], 'Rolle' => 'Schülerempfänger'];
        }

        return ['metadata' => $package + ['Erstellt' => $package['prepared_at']], 'recipients' => $recipients,
            'date' => $exercise['scheduled_date'], 'title' => $this->assignment($exercise['title']),
            'personal_results' => false, 'purpose' => 'tasks', 'mode' => 'prepared', 'live' => false];
    }

    /** @return list<array<string, mixed>> */
    private function legacyRecipients(string $text): array
    {
        $blocks = preg_split('/\nEMPFÄNGER (\d+)\s*\n/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $recipients = [];
        for ($offset = 0; $offset < count($blocks); $offset += 2) {
            $index = intdiv($offset, 2);
            $block = $blocks[$offset];
            if (! preg_match('/\A\s*(\{.*?\n\})\s*(.*)\z/su', $block, $parts)) {
                $this->reject('Ungültiger historischer Empfängerblock.');
            }
            $row = json_decode($parts[1], true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($row) || ($row['Datensatzposition'] ?? null) !== $index + 1
                || ($offset > 0 && (int) $blocks[$offset - 1] !== $index + 1)
                || ($offset < count($blocks) - 1 && trim($parts[2]) !== '')
                || (trim($parts[2]) !== '' && ! preg_match('/\ABENENNUNGSMIGRATION[^\n]*\n+Lokale Terminologieanpassung[^\n]*\z/u', trim($parts[2])))) {
                $this->reject('Historische Empfängerfolge oder Abschlussvermerk nicht erkannt.');
            }
            $row['Rolle'] ??= 'Schülerempfänger';
            $recipients[] = $row;
        }

        return $recipients;
    }

    /** @param array<string, mixed> $preparation */
    private function validateDispatchReadiness(array $preparation): void
    {
        if (! array_key_exists('Versandbereit', $preparation)) {
            return;
        }
        if (! is_bool($preparation['Versandbereit'])) {
            $this->reject('Versandbereit muss ein eindeutiger Wahrheitswert sein.');
        }
        if (! $preparation['Versandbereit']) {
            return;
        }
        if (! preg_match('/\A[0-9a-f]{64}\z/i', $this->value($preparation, 'Textquelle_SHA256')) || $preparation['Empfaenger'] === []) {
            $this->reject('Versandbereit benötigt eine geprüfte Textquelle und vorbereitete Nachrichten.');
        }
        foreach ($preparation['Empfaenger'] as $recipient) {
            if (! is_array($recipient) || ($recipient['Rolle'] ?? null) !== 'Schülerempfänger'
                || ! preg_match('/\A[0-9a-f]{64}\z/i', $this->value($recipient, 'Nachricht_SHA256'))
                || ($recipient['AnhaengeGeprueft'] ?? null) !== true
                || ! is_array($recipient['VorbereiteteAnhaenge'] ?? null) || ! array_is_list($recipient['VorbereiteteAnhaenge'])) {
                $this->reject('Versandbereit benötigt für jede Person eine geprüfte Nachricht und Anhangliste.');
            }
            $names = [];
            foreach ($recipient['VorbereiteteAnhaenge'] as $attachment) {
                if (! is_array($attachment) || ! preg_match('/\A[^\/\\\\\x00-\x1f:]+\z/u', $this->value($attachment, 'Dateiname'))
                    || ! preg_match('/\A[0-9a-f]{64}\z/i', $this->value($attachment, 'SHA256'))
                    || in_array($attachment['Dateiname'], $names, true)) {
                    $this->reject('Versandbereit enthält eine ungültige oder doppelte Anhangreferenz.');
                }
                $names[] = $attachment['Dateiname'];
            }
        }
    }

    /** @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    /** @param array<string, array<string, mixed>>|null $participantAdditions */
    public function preview(TeachingCourseWork $work, array $report, string $sha256, bool $requireMatchingTitle = true, ?array $participantAdditions = null, ?array $identityBinding = null): array
    {
        $course = $work->teachingCourse;
        $documentPreparation = ($report['metadata']['kind'] ?? null) === 'schooltool.preparation' && ($report['metadata']['schema_version'] ?? null) === 2;
        if ($documentPreparation && ($report['metadata']['work_id'] !== (int) $work->id || $report['metadata']['course_id'] !== (int) $course->id
            || $report['date'] !== $work->date_for_all_groups?->format('Y-m-d'))) {
            $this->reject('Aufgaben-Vorbereitung gehört zu einer anderen Arbeit, einem anderen Kurs oder Durchführungstermin.');
        }
        if (($report['personal_results'] ?? false)
            && $this->personalResultAssignment((string) $work->title) !== $this->personalResultAssignment($report['title'])) {
            $this->reject('Persönliches Ergebnisanschreiben passt nicht zur gespeicherten Arbeit.');
        }
        if ($report['personal_results'] ?? false) {
            $report['title'] = $this->assignment((string) $work->title);
        }
        if ($requireMatchingTitle && $this->assignment((string) $work->title) !== $report['title']) {
            $this->reject('Titel des Versandprotokolls passt nicht zur gespeicherten Arbeit.');
        }
        $candidates = $course->teachingCourseWorks()->where('date_for_all_groups', $work->date_for_all_groups?->format('Y-m-d'))->get(['id', 'title']);
        if ($requireMatchingTitle && $candidates->filter(fn (TeachingCourseWork $candidate): bool => $this->assignment((string) $candidate->title) === $report['title'])->count() !== 1) {
            $this->reject('Mehrere Arbeiten mit diesem Titel und Datum – Zuordnung nicht eindeutig.');
        }
        $identities = $this->dispatchIdentities($course);
        $participantAdditions ??= $this->participantAdditions($work, [$report]);
        $groups = app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work);
        $members = array_map('intval', collect($groups)->flatMap(fn (array $group): array => is_array($group['student_ids'] ?? null) ? $group['student_ids'] : [])->all());
        $existing = $work->status['dispatch_notifications'] ?? [];
        $logs = $work->status['dispatch_logs'] ?? [];
        $alreadyImported = collect($logs)->contains('sha256', $sha256);
        $rows = [];
        $blocked = false;
        $providers = [];
        $providerRecipients = [];
        $preparedTargets = [];
        foreach ($report['recipients'] as $recipient) {
            $role = $this->value($recipient, 'Rolle');
            $row = [
                'person' => trim($this->value($recipient, 'Vorname').' '.$this->value($recipient, 'Nachname')),
                'class' => $this->value($recipient, 'Klasse'), 'student_id' => null, 'sent_at' => null,
                'provider_id' => strtolower($this->value($recipient, 'Providerkennung')),
                'status' => 'Kein bestätigter Live-Versand', 'accepted' => false,
                'mode' => $report['mode'], 'observed_at' => $this->providerTime($this->value($recipient, 'Providerzeit')),
            ];
            if ($row['provider_id'] !== '') {
                $recipientIdentity = [$role, TeachingWorkMarkdownImport::identity($row['person'], $row['class']), mb_strtolower($this->value($recipient, 'To'))];
                if (isset($providerRecipients[$row['provider_id']]) && $providerRecipients[$row['provider_id']] !== $recipientIdentity) {
                    $row['status'] = 'Providerkennung für unterschiedliche Empfänger – Import gesperrt';
                    $blocked = true;
                    $rows[] = $row;

                    continue;
                }
                $providerRecipients[$row['provider_id']] = $recipientIdentity;
            }
            if ($role === 'Lehrperson') {
                $row['status'] = 'Lehrperson – keine Schülerkennzeichnung';
                $rows[] = $row;

                continue;
            }
            $key = TeachingWorkMarkdownImport::identity($row['person'], $row['class']);
            $matches = collect($identities[$key] ?? [])->unique('student_id')->values()->all();
            $email = mb_strtolower($this->value($recipient, 'To'));
            $boundId = $recipient['SchooltoolPersonId'] ?? null;
            if ($identityBinding !== null) {
                $boundId = app(TeachingWorkJsonImport::class)->boundStudentId([
                    'schooltool_person_id' => $boundId, 'first_name' => $this->value($recipient, 'Vorname'),
                    'last_name' => $this->value($recipient, 'Nachname'), 'class_name' => $row['class'],
                ], $identityBinding, $email !== '' ? $email : null);
            }
            $addition = $participantAdditions[$key] ?? null;
            $pending = $addition !== null && $email === $addition['email'];
            if ($role !== 'Schülerempfänger' || $this->value($recipient, 'Vorname') === '' || $this->value($recipient, 'Nachname') === '' || $row['class'] === ''
                || $this->normalize($this->value($recipient, 'Kurs')) !== $this->normalize((string) $course->title)
                || (! $pending && (count($matches) !== 1 || ! in_array($matches[0]['student_id'] ?? null, $members, true)
                    || ($identityBinding !== null && (string) $matches[0]['student_id'] !== $boundId)
                    || ($documentPreparation ? ($boundId !== null && (string) $matches[0]['student_id'] !== $boundId)
                        : ! in_array($email, $matches[0]['emails'] ?? [], true))))
                || (! $documentPreparation && ! filter_var($email, FILTER_VALIDATE_EMAIL))) {
                $row['status'] = 'Kurs, Person, Klasse, Arbeitszuordnung oder E-Mail nicht eindeutig – Import gesperrt';
                $blocked = true;
                $rows[] = $row;

                continue;
            }
            $row['student_id'] = $pending ? $addition['user_id'] : $matches[0]['student_id'];
            if ($documentPreparation && in_array($row['student_id'], $preparedTargets, true)) {
                $row['status'] = 'Doppelte Person in der Aufgaben-Vorbereitung – Import gesperrt';
                $blocked = true;
                $rows[] = $row;

                continue;
            }
            $preparedTargets[] = $row['student_id'];
            $row['participant_addition'] = $pending ? $addition : null;
            $plannedAt = $this->plannedTime($recipient, $report['metadata']);
            $row['planned_at'] = $plannedAt;
            $row['prepared_at'] = $this->providerTime($this->value($report['metadata'], 'Erstellt'))
                ?? $this->providerTime($this->value($report['metadata'], 'VorbereitungAm'));
            if ($report['mode'] !== 'test' && ($plannedAt !== null || $report['mode'] === 'prepared' || $this->value($recipient, 'Status') === 'vorbereitet')) {
                $row['mode'] = $this->confirmedSchedule($recipient, $report['metadata'], $plannedAt) ? 'scheduled' : 'prepared';
                $row['observed_at'] = null;
                $row['status'] = $row['mode'] === 'scheduled' ? 'Serverseitig geplant – tatsächlicher Versand nicht bestätigt' : 'Vorbereitet – tatsächlicher Versand nicht bestätigt';
            }
            $sentAt = $this->providerTime($this->value($recipient, 'Providerzeit'));
            $postmarkAccepted = $this->value($report['metadata'], 'Modus') === 'Live-Versand (Postmark)'
                && ($report['metadata']['AktuelleServerpruefung']['DeliveryType'] ?? null) === 'Live'
                && $this->value($recipient, 'Modus') === 'Live-Versand (Postmark)'
                && $this->value($recipient, 'Status') === 'Postmark-API-Annahme bestätigt'
                && $this->value($recipient, 'Providerstatus') === 'Sent'
                && $this->value($recipient, 'HTTPStatus') === '200'
                && $this->value($recipient, 'PostmarkFehlercode') === '0'
                && is_string($recipient['Fehler'] ?? null) && trim($recipient['Fehler']) === ''
                && preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/i', $row['provider_id'])
                && $sentAt !== null;
            $accepted = $report['live'] && ($postmarkAccepted || $this->confirmedOfficeSend($recipient, $report['metadata']))
                && $sentAt !== null && ($report['purpose'] === 'tasks' || ! $work->date_for_all_groups
                    || (new DateTimeImmutable($sentAt))->setTimezone(new DateTimeZone('Europe/Vienna'))->format('Y-m-d') >= $work->date_for_all_groups->format('Y-m-d'));
            if ($row['mode'] === 'test' || str_contains(mb_strtolower($this->value($recipient, 'Modus')), 'mailpit')) {
                $row['mode'] = 'test';
                $row['status'] = 'Lokaler Mailpit-Test – kein Live-Versand';
            }
            if ($accepted) {
                $notification = ['student_id' => $row['student_id'], 'sent_at' => $sentAt, 'provider_id' => $row['provider_id']];
                $previous = collect($existing)->firstWhere('provider_id', $row['provider_id']);
                if ((isset($providers[$row['provider_id']]) && $providers[$row['provider_id']] !== $notification)
                    || ($previous && ((int) $previous['student_id'] !== $notification['student_id'] || ! $this->sameImportedProviderTime($previous, $notification['sent_at'], $sha256, $alreadyImported)
                        || ($previous['purpose'] ?? 'results') !== $report['purpose']))) {
                    $row['status'] = 'Widersprüchliche Providerkennung – Import gesperrt';
                    $blocked = true;
                } else {
                    $providers[$row['provider_id']] = $notification;
                    $row['accepted'] = true;
                    $row['sent_at'] = $sentAt;
                    $row['status'] = $previous ? 'Bereits protokolliert – unverändert' : 'Live-Versand übernehmen';
                    $row['mode'] = 'live';
                }
            }
            if ($pending && ! str_contains($row['status'], 'gesperrt')) {
                $row['status'] .= ' · Nachzügler '.($addition['create_enrollment'] ? 'zu Kurs und Arbeit' : 'zur Arbeit').' hinzufügen'
                    .($addition['create_account'] ? ' (Schülerkonto wird angelegt)' : '');
            }
            $rows[] = $row;
        }
        if (! $alreadyImported && collect($logs)->where('origin', 'dispatch_import')->count() >= 30) {
            $this->reject('Für diese Arbeit sind bereits 30 Versandprotokolle gespeichert.');
        }

        return [
            'purpose' => $report['purpose'], 'mode' => $report['mode'],
            'source_sha256' => $sha256,
            'log_info' => [
                'dispatch_ready' => ! $blocked && $report['purpose'] === 'tasks' && ($report['metadata']['Versandbereit'] ?? false) === true
                    && collect($rows)->every(fn (array $row): bool => $row['student_id'] !== null && $row['mode'] === 'prepared')
                    && collect($rows)->pluck('student_id')->unique()->sort()->values()->all() === collect($members)->unique()->sort()->values()->all(),
                'dispatch_at' => collect($rows)->where('accepted', true)->pluck('sent_at')->filter()->sortBy($this->providerTimeSortKey(...))->last(),
                'prepared_at' => $this->providerTime($this->value($report['metadata'], 'Erstellt'))
                    ?? $this->providerTime($this->value($report['metadata'], 'VorbereitungAm')),
                'planned_at' => collect($rows)->pluck('planned_at')->filter()->sortBy($this->providerTimeSortKey(...))->last(),
                'mode' => collect($rows)->contains('accepted', true) ? 'live'
                    : (collect($rows)->contains('mode', 'scheduled') ? 'scheduled' : (collect($rows)->contains('mode', 'prepared') ? 'prepared' : $report['mode'])),
                'recipient_scope' => collect($report['recipients'])->every(fn (array $recipient): bool => $this->value($recipient, 'Rolle') === 'Lehrperson') ? 'teacher' : 'students',
            ],
            'title' => $work->title, 'date' => $report['date'], 'rows' => $rows, 'already_imported' => $alreadyImported,
            'can_import' => ! $blocked,
            'participant_additions' => $participantAdditions,
            'identity_binding' => $identityBinding,
            'hash' => hash('sha256', json_encode([$work->getAttributes(), $course->getAttributes(), $candidates->toArray(), $identities, $groups, $participantAdditions, $sha256], JSON_THROW_ON_ERROR)),
        ];
    }

    /** @return array<string, list<array{student_id: int, email: string, emails: list<string>}>> */
    private function dispatchIdentities(TeachingCourse $course): array
    {
        $identities = $this->evaluations->courseIdentities($course);
        $users = $course->teachingCourseStudents()->whereNull('canceled_at')->with('user')->get()
            ->pluck('user')->filter()->keyBy('id');
        foreach ($identities as $key => &$people) {
            foreach ($people as &$person) {
                $person['emails'] = [$person['email']];
                $user = $users[$person['student_id']] ?? null;
                if ($user && (int) $user->school_id === (int) $course->school_id
                    && TeachingWorkMarkdownImport::identity($user->first_name.' '.$user->last_name, explode(' / ', $key)[1] ?? '') === $key) {
                    $email = mb_strtolower(trim((string) $user->email));
                    $otherOwner = collect($identities)->flatten(1)->contains(fn (array $other): bool => $other['student_id'] !== $person['student_id'] && $other['email'] === $email);
                    if (filter_var($email, FILTER_VALIDATE_EMAIL) && ! $otherOwner) {
                        $person['emails'] = array_values(array_unique([...$person['emails'], $email]));
                    }
                }
            }
            unset($person);
        }
        unset($people);

        return $identities;
    }

    /** Explicit additions are resolved read-only; only a later authorized apply enrolls them.
     * @param  list<array<string, mixed>>  $reports
     * @return array<string, array<string, mixed>>
     */
    public function participantAdditions(TeachingCourseWork $work, array $reports): array
    {
        $course = $work->teachingCourse;
        $identities = $this->dispatchIdentities($course);
        $members = collect(app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work))->flatMap(fn (array $group): array => $group['student_ids'] ?? [])->all();
        $additions = [];
        foreach ($reports as $report) {
            foreach ($report['recipients'] as $recipient) {
                if (! isset($recipient['Teilnehmerzugang'])) {
                    continue;
                }
                $person = trim($this->value($recipient, 'Vorname').' '.$this->value($recipient, 'Nachname'));
                $key = TeachingWorkMarkdownImport::identity($person, $this->value($recipient, 'Klasse'));
                $email = mb_strtolower($this->value($recipient, 'To'));
                if ($recipient['Teilnehmerzugang'] !== 'nachträglich' || $report['purpose'] !== 'tasks'
                    || $this->value($recipient, 'Rolle') !== 'Schülerempfänger'
                    || $this->normalize($this->value($recipient, 'Kurs')) !== $this->normalize((string) $course->title)
                    || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->reject($person.': Nachträglicher Teilnehmerzugang ist nicht gültig belegt.');
                }
                $matches = $identities[$key] ?? [];
                if (count($matches) > 1 || (count($matches) === 1 && ! in_array($email, $matches[0]['emails'], true))) {
                    $this->reject($person.': Bestehende Kursidentität oder E-Mail widerspricht dem Teilnehmerzugang.');
                }
                if (count($matches) === 1 && in_array($matches[0]['student_id'], $members, true)
                    && in_array($email, $matches[0]['emails'], true)) {
                    continue;
                }
                if ($work->is_group_work) {
                    $this->reject($person.': Nachzügler vor dem Import einer Arbeitsgruppe zuordnen.');
                }
                $imports = Import116::query()->where('school_id', $course->school_id)->where('schoolyear_id', $course->schoolyear_id)
                    ->where('first_name', $this->value($recipient, 'Vorname'))->where('last_name', $this->value($recipient, 'Nachname'))
                    ->where('class', $this->value($recipient, 'Klasse'))->lockForUpdate()->get();
                $import = $imports->first();
                if ($imports->count() !== 1 || ($matches === [] && mb_strtolower(trim((string) $import?->email)) !== $email)) {
                    $this->reject($person.': Nachzügler benötigt genau einen aktuellen Schülerimport mit passender Klasse und E-Mail.');
                }
                $expectedUserId = $matches[0]['student_id'] ?? $import->user_id;
                $users = User::query()->where('school_id', $course->school_id)
                    ->where(fn ($query) => $query->where('email', $email)->when($expectedUserId, fn ($query) => $query->orWhere('id', $expectedUserId)))
                    ->lockForUpdate()->get();
                $user = $users->first();
                if (($import->user_id && (! $user || (int) $import->user_id !== (int) $user->id))
                    || ($expectedUserId && (! $user || (int) $expectedUserId !== (int) $user->id))
                    || $users->count() > 1 || ($user && ((int) $user->schoolyear_id !== (int) $course->schoolyear_id
                    && (int) $user->import116_id !== (int) $import->id && (int) $import->user_id !== (int) $user->id
                    || $this->normalize($user->first_name.' '.$user->last_name) !== $this->normalize($person)
                    || ! $user->hasRole('student') || ($user->import116_id && (int) $user->import116_id !== (int) $import->id)))) {
                    $this->reject($person.': Schülerkonto widerspricht dem nachträglichen Teilnehmerzugang.');
                }
                $enrollments = $course->teachingCourseStudents()->withTrashed()
                    ->where(fn ($query) => $query->where('import116_id', $import->id)->when($user, fn ($query) => $query->orWhere('user_id', $user->id)))
                    ->lockForUpdate()->get();
                if ($enrollments->count() > 1 || $enrollments->contains(fn ($enrollment): bool => $enrollment->trashed() || $enrollment->canceled_at !== null
                    || ($enrollment->user_id && (int) $enrollment->user_id !== (int) $user?->id))) {
                    $this->reject($person.': Ausgetretene, entfernte oder mehrdeutige Kursteilnahme vor dem Import klären.');
                }
                if (isset($additions[$key]) && $additions[$key]['email'] !== $email) {
                    $this->reject($person.': Widersprüchliche Nachzüglerzuordnung.');
                }
                $additions[$key] = ['import116_id' => (int) $import->id, 'user_id' => $user?->id, 'email' => $email,
                    'create_account' => $user === null, 'create_enrollment' => $enrollments->isEmpty(), 'enrollment_id' => $enrollments->first()?->id,
                    'snapshot' => hash('sha256', json_encode([$import->getAttributes(), $user?->getAttributes(), $enrollments->toArray()], JSON_THROW_ON_ERROR))];
            }
        }

        return $additions;
    }

    /** @param array<string, array<string, mixed>> $additions */
    public function applyParticipantAdditions(TeachingCourseWork $work, array $additions): void
    {
        foreach ($additions as $addition) {
            $import = Import116::query()->lockForUpdate()->findOrFail($addition['import116_id']);
            $studentId = app(TeachingCourseService::class)->findOrCreateUserIdFromImport($import, (int) $work->teachingCourse->school_id);
            if (! $studentId) {
                $this->reject('Nachzügler konnte nicht mit dem geprüften Schülerimport verbunden werden.');
            }
            $enrollment = $addition['enrollment_id'] !== null
                ? $work->teachingCourse->teachingCourseStudents()->findOrFail($addition['enrollment_id'])
                : $work->teachingCourse->teachingCourseStudents()->make(['import116_id' => $import->id]);
            $enrollment->import116_id = $import->id;
            $enrollment->user_id = $studentId;
            $enrollment->save();
            $memberships = app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work);
            if (! collect($memberships)->contains(fn (array $group): bool => in_array($studentId, $group['student_ids'] ?? [], true))) {
                $groups = is_array($work->groups) ? $work->groups : [];
                $groups[] = ['student_ids' => [$studentId], 'date' => $work->date_for_all_groups?->format('Y-m-d'),
                    'grade' => null, 'comment' => null, 'grades' => [], 'points' => [], 'comments' => []];
                $work->groups = $groups;
                $work->save();
                app(TeachingCourseWorkEntrySyncService::class)->syncWork($work, useStoredGroups: true);
                $work->unsetRelation('teachingCourseWorkGroupStudents');
            }
        }
    }

    /** @param array<string, mixed> $preview */
    public function apply(TeachingCourseWork $work, array $preview, string $sha256, string $name, string $path): void
    {
        $status = $work->status ?? [];
        $logs = $status['dispatch_logs'] ?? [];
        if (! collect($logs)->contains('sha256', $sha256)) {
            $logs[] = ['name' => $name, 'sha256' => $sha256, 'file_path' => $path, 'storage_disk' => 'local', 'origin' => 'dispatch_import', 'purpose' => $preview['purpose'],
                ...(isset($preview['identity_binding']) ? ['identity_binding' => $preview['identity_binding']] : [])];
        }
        foreach ($logs as &$log) {
            if ($log['sha256'] === $sha256) {
                $log = array_replace($log, $preview['log_info']);
            }
        }
        unset($log);
        $notifications = $status['dispatch_notifications'] ?? [];
        $attempts = $status['dispatch_attempts'] ?? [];
        foreach ($preview['rows'] as $row) {
            if (! $row['accepted'] && $row['student_id']) {
                $parsedAttempt = ['student_id' => $row['student_id'], 'sent_at' => $row['observed_at'], 'mode' => $row['mode'],
                    'planned_at' => $row['planned_at'] ?? null, 'prepared_at' => $row['prepared_at'] ?? null,
                    'purpose' => $preview['purpose'], 'log_sha256' => $sha256, 'origin' => 'dispatch_import'];
                $index = collect($attempts)->search(fn (array $attempt): bool => $attempt['log_sha256'] === $sha256 && (int) $attempt['student_id'] === $row['student_id']);
                if ($index === false) {
                    $attempts[] = $parsedAttempt;
                } else {
                    $attempts[$index] = array_replace($attempts[$index], $parsedAttempt);
                }
            }
            if (! $row['accepted']) {
                continue;
            }
            $notificationIndex = collect($notifications)->search(fn (array $notification): bool => $notification['provider_id'] === $row['provider_id']);
            if ($notificationIndex !== false) {
                if (($notifications[$notificationIndex]['log_sha256'] ?? null) === $sha256) {
                    $notifications[$notificationIndex]['sent_at'] = $row['sent_at'];
                }

                continue;
            }
            $notifications[] = [
                'student_id' => $row['student_id'], 'sent_at' => $row['sent_at'], 'provider_id' => $row['provider_id'],
                'log_sha256' => $sha256, 'origin' => 'dispatch_import',
                'purpose' => $preview['purpose'],
            ];
        }
        $status['dispatch_logs'] = $logs;
        $status['dispatch_notifications'] = $notifications;
        $status['dispatch_attempts'] = $attempts;
        $work->status = $status;
        $work->save();
    }

    public function streamLog(TeachingCourseWork $work, string $sha256): StreamedResponse
    {
        $attachment = collect($work->status['dispatch_logs'] ?? [])->first(fn (array $log): bool => in_array($log['origin'] ?? null, ['dispatch_import', 'provision_import'], true) && ($log['sha256'] ?? null) === $sha256);
        abort_unless($attachment, 404);
        $path = $attachment['file_path'] ?? '';
        $schoolId = $work->teachingCourse->school_id;
        TeachingSynchronisationFiles::assertPath($path);
        abort_unless(($attachment['storage_disk'] ?? null) === 'local'
            && (str_starts_with($path, "teaching/work_dispatches/{$schoolId}/")
                || str_starts_with($path, "teaching/synchronisation/{$schoolId}/") || str_starts_with($path, 'teaching/personal_restores/')), 404);
        $provision = $attachment['origin'] === 'provision_import';
        $name = PrivateImportSourceFile::downloadName($attachment['name'], $provision ? 'bereitstellung' : 'versandprotokoll', $provision ? 'json' : 'txt');

        return Storage::disk('local')->download($path, $name, [
            'Content-Type' => ($provision ? 'application/json' : 'text/plain').'; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $name),
        ]);
    }

    /** @param array<string, mixed> $values */
    private function value(array $values, string $key): string
    {
        $value = $values[$key] ?? '';

        return is_string($value) || is_int($value) ? trim((string) $value) : '';
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)));
    }

    private function assignment(string $value): string
    {
        return $this->normalize(preg_replace('/\A(?:Übung|Leistungsfeststellung|Arbeit):\s*/iu', '', trim($value)));
    }

    private function personalResultAssignment(string $value): string
    {
        $title = $this->assignment($value);

        return in_array($title, ['e-mail', 'e-mails'], true) ? 'e-mail' : $title;
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value;
    }

    private function providerTime(string $value): ?string
    {
        if (! preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.(\d{1,7}))?(?:Z|[+-]\d{2}:\d{2})\z/', $value, $matches)) {
            return null;
        }
        $fraction = rtrim($matches[1] ?? '', '0');
        $value = preg_replace('/\.\d+(?=Z|[+-])/', '', $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', str_replace('Z', '+00:00', $value));
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
            return null;
        }

        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s').($fraction === '' ? '' : '.'.$fraction).'Z';
    }

    /** @param array<string, mixed> $previous */
    private function sameImportedProviderTime(array $previous, string $sentAt, string $sha256, bool $alreadyImported): bool
    {
        if ($previous['sent_at'] === $sentAt) {
            return true;
        }

        return $alreadyImported && ($previous['origin'] ?? null) === 'dispatch_import'
            && ($previous['log_sha256'] ?? null) === $sha256
            && preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/', $previous['sent_at']) === 1
            && str_starts_with($sentAt, substr($previous['sent_at'], 0, -1).'.');
    }

    private function providerTimeSortKey(string $time): string
    {
        $parts = explode('.', substr($time, 0, -1), 2);

        return $parts[0].'.'.str_pad($parts[1] ?? '', 7, '0');
    }

    private function plannedTime(array $recipient, array $metadata): ?string
    {
        $times = [];
        foreach ([$this->value($recipient, 'Termin'), $this->value($metadata, 'Termin'),
            $this->value(is_array($recipient['Native_Terminierung'] ?? null) ? $recipient['Native_Terminierung'] : [], 'ScheduledAt')] as $value) {
            if ($value === '') {
                continue;
            }
            $time = $this->providerTime($value);
            if ($time === null || (! str_ends_with($value, 'Z')
                && (new DateTimeImmutable($time))->setTimezone(new DateTimeZone('Europe/Vienna'))->format('P') !== substr($value, -6))) {
                $this->reject('Geplanter Versandtermin benötigt ein gültiges Datum mit passendem Europe/Vienna-Offset.');
            }
            $times[] = $time;
        }
        if (count(array_unique($times)) > 1) {
            $this->reject('Widersprüchliche geplante Versandtermine.');
        }

        return $times[0] ?? null;
    }

    private function confirmedSchedule(array $recipient, array $metadata, ?string $plannedAt): bool
    {
        $state = $recipient['Native_Terminierung'] ?? null;
        $account = mb_strtolower($this->value($metadata, 'Account'));

        return $plannedAt !== null && is_array($state)
            && ($state['Scheduled'] ?? null) === true && ($state['Sent'] ?? null) === false
            && $this->value($state, 'Status') === 'serverseitig geplant'
            && $this->value($recipient, 'Status') === 'serverseitig geplant'
            && $this->providerTime($this->value($state, 'ScheduledAt')) === $plannedAt
            && mb_strtolower($this->value($state, 'To')) === mb_strtolower($this->value($recipient, 'To'))
            && $this->value($state, 'DraftEntryID') !== '' && $this->value($state, 'DraftStoreID') !== ''
            && (bool) preg_match('/\A[0-9a-f]{64}\z/i', $this->value($state, 'Webnachweis_SHA256'))
            && $this->value($state, 'Webnachweis') !== ''
            && $this->value($metadata, 'Provider') === 'Office/Outlook/Exchange'
            && $this->value($recipient, 'Provider') === 'Office/Outlook/Exchange'
            && $this->value($recipient, 'Modus') === 'Live-Versand (Office/Outlook)'
            && filter_var($account, FILTER_VALIDATE_EMAIL) !== false
            && $account === mb_strtolower($this->value($recipient, 'From'))
            && $account === mb_strtolower($this->value($recipient, 'Account'));
    }

    private function confirmedOfficeSend(array $recipient, array $metadata): bool
    {
        $state = $recipient['Office_Zustand'] ?? null;
        if (! is_array($state) || $this->value($metadata, 'Modus') !== 'Live-Versand (Office/Outlook)'
            || $this->value($metadata, 'Provider') !== 'Office/Outlook/Exchange'
            || $this->value($recipient, 'Modus') !== 'Live-Versand (Office/Outlook)'
            || $this->value($recipient, 'Provider') !== 'Office/Outlook/Exchange'
            || $this->value($recipient, 'Status') !== 'Office-Versand in Gesendete Elemente bestätigt'
            || $this->value($state, 'Status') !== $this->value($recipient, 'Status')
            || ($state['SentConfirmed'] ?? null) !== true) {
            return false;
        }
        $results = $this->value($metadata, 'Versandzweck') === 'Ergebnisbenachrichtigung';
        $officeAttachments = $recipient['Tatsaechliche_Anhaenge'] ?? null;
        $attachments = $recipient['TatsaechlicheAnhaenge'] ?? $officeAttachments ?? ($results ? ($recipient['Anhaenge'] ?? null) : null);
        if (isset($recipient['TatsaechlicheAnhaenge'], $recipient['Tatsaechliche_Anhaenge'])) {
            return false;
        }
        if ($attachments === null && $results && isset($recipient['Allgemeine_Anhaenge'], $recipient['Persoenliche_Anhaenge'])) {
            $general = $recipient['Allgemeine_Anhaenge'];
            $personal = $recipient['Persoenliche_Anhaenge'];
            if (! is_array($general) || ! array_is_list($general) || ! is_array($personal) || ! array_is_list($personal)) {
                return false;
            }
            $attachments = array_merge($general, $personal);
        }
        $sentAttachments = $state['Attachments'] ?? null;
        if (! is_array($attachments) || ! array_is_list($attachments)
            || ! is_array($sentAttachments) || ! array_is_list($sentAttachments)
            || ($state['AttachmentsVerified'] ?? null) !== count($attachments) || count($sentAttachments) !== count($attachments)) {
            return false;
        }
        foreach ($attachments as $index => $attachment) {
            $sentAttachment = $sentAttachments[$index];
            $nameKey = $officeAttachments !== null ? 'Name' : 'Dateiname';
            if (! is_array($attachment) || ! is_array($sentAttachment)
                || $this->value($attachment, $nameKey) === '' || $this->value($attachment, $nameKey) !== $this->value($sentAttachment, 'Name')
                || ! preg_match('/\A[0-9a-f]{64}\z/i', $this->value($attachment, 'SHA256'))
                || mb_strtolower($this->value($attachment, 'SHA256')) !== mb_strtolower($this->value($sentAttachment, 'SHA256'))) {
                return false;
            }
        }
        $account = mb_strtolower($this->value($metadata, 'Account'));
        $messageId = $this->value($recipient, 'InternetMessageID');
        $sentAt = $this->providerTime($this->value($recipient, 'Providerzeit'));
        $nativeEvidence = isset($metadata['Leistungsfeststellungsordner']) && $officeAttachments !== null;
        $recipientAccount = $this->value($recipient, 'Account') ?: ($nativeEvidence ? $this->value($recipient, 'From') : '');
        $sentEntry = $this->value($recipient, 'SentEntryID') ?: ($nativeEvidence ? $this->value($state, 'SentEntryID') : '');
        $sentStore = $this->value($recipient, 'StoreID') ?: ($nativeEvidence ? $this->value($state, 'SentStoreID') : '');

        return filter_var($account, FILTER_VALIDATE_EMAIL) !== false
            && $account === mb_strtolower($recipientAccount) && $account === mb_strtolower($this->value($state, 'Account'))
            && (! isset($recipient['From']) || $account === mb_strtolower($this->value($recipient, 'From')))
            && mb_strtolower($this->value($state, 'To')) === mb_strtolower($this->value($recipient, 'To'))
            && $this->value($state, 'Subject') === $this->value($recipient, 'Betreff')
            && (bool) preg_match('/\A<[^\s<>@]+@[^\s<>@]+>\z/', $messageId)
            && $messageId === $this->value($recipient, 'Providerkennung') && $messageId === $this->value($state, 'InternetMessageID')
            && $sentEntry !== '' && $sentEntry === $this->value($state, 'SentEntryID')
            && $sentStore !== '' && $sentStore === $this->value($state, 'SentStoreID')
            && $sentAt !== null && $sentAt === $this->providerTime($this->value($state, 'SentOn'));
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['protocol' => $message]);
    }
}
