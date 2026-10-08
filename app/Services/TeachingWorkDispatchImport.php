<?php

namespace App\Services;

use App\Models\TeachingCourseWork;
use App\Support\PrivateImportSourceFile;
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

    /** @return array{metadata: array<string, mixed>, recipients: list<array<string, mixed>>, date: string, title: string, live: bool, purpose: string, mode: string} */
    public function parse(string $text): array
    {
        if (strlen($text) > 1048576 || ! preg_match('//u', $text) || str_contains($text, "\0")) {
            $this->reject('Ein UTF-8-Versandprotokoll mit höchstens 1 MB auswählen.');
        }
        $text = str_replace(["\r\n", "\r"], "\n", preg_replace('/\A\xEF\xBB\xBF/', '', $text));
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
            $subject = $this->value($recipient, 'Betreff');
            if (preg_match('/\AErgebnisse zur Leistungsfeststellung:\s*(.+)\z/u', $subject, $matches)) {
                $rowPurpose = 'results';
            } elseif ($teacherTest && preg_match('/\A(?:Test|Formatierungstest) der Ergebnisbenachrichtigung:\s*(.+)\z/u', $subject, $matches)) {
                $rowPurpose = 'results';
            } elseif (preg_match('/\A(?:Aufgaben zur Leistungsfeststellung:\s*(.+)|Leistungsfeststellung\s+(.+?)\s+–\s+.+)\z/u', $subject, $matches)) {
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

        return ['metadata' => $metadata, 'recipients' => $recipients, 'date' => $date[1], 'title' => $title,
            'purpose' => $purpose, 'mode' => $teacherTest ? 'teacher_test' : ($legacy || str_contains(mb_strtolower(strtok($text, "\n").' '.$this->value($metadata, 'Modus')), 'mailpit') ? 'test' : 'unconfirmed'),
            'live' => $combined ? ($combinedSections[3] ?? '') === ''
                : (bool) preg_match('/\AVERSANDPROTOKOLL – (?:(?:AUFGABENVERSAND|ERGEBNISBENACHRICHTIGUNG) – )?LIVE-VERSAND \(POSTMARK\)\z/u', $heading)];
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

    /** @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    public function preview(TeachingCourseWork $work, array $report, string $sha256, bool $requireMatchingTitle = true): array
    {
        $course = $work->teachingCourse;
        if ($requireMatchingTitle && $this->assignment((string) $work->title) !== $report['title']) {
            $this->reject('Titel des Versandprotokolls passt nicht zur gespeicherten Arbeit.');
        }
        $candidates = $course->teachingCourseWorks()->where('date_for_all_groups', $work->date_for_all_groups?->format('Y-m-d'))->get(['id', 'title']);
        if ($requireMatchingTitle && $candidates->filter(fn (TeachingCourseWork $candidate): bool => $this->assignment((string) $candidate->title) === $report['title'])->count() !== 1) {
            $this->reject('Mehrere Arbeiten mit diesem Titel und Datum – Zuordnung nicht eindeutig.');
        }
        $identities = $this->evaluations->courseIdentities($course);
        $groups = app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work);
        $members = array_map('intval', collect($groups)->flatMap(fn (array $group): array => is_array($group['student_ids'] ?? null) ? $group['student_ids'] : [])->all());
        $existing = $work->status['dispatch_notifications'] ?? [];
        $rows = [];
        $blocked = false;
        $providers = [];
        $providerRecipients = [];
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
            if ($role !== 'Schülerempfänger' || $this->value($recipient, 'Vorname') === '' || $this->value($recipient, 'Nachname') === '' || $row['class'] === ''
                || $this->normalize($this->value($recipient, 'Kurs')) !== $this->normalize((string) $course->title)
                || count($matches) !== 1 || ! in_array($matches[0]['student_id'] ?? null, $members, true)
                || ! filter_var($email, FILTER_VALIDATE_EMAIL) || $email !== ($matches[0]['email'] ?? null)) {
                $row['status'] = 'Kurs, Person, Klasse, Arbeitszuordnung oder E-Mail nicht eindeutig – Import gesperrt';
                $blocked = true;
                $rows[] = $row;

                continue;
            }
            $row['student_id'] = $matches[0]['student_id'];
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
                    || ($previous && ((int) $previous['student_id'] !== $notification['student_id'] || $previous['sent_at'] !== $notification['sent_at']
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
            $rows[] = $row;
        }
        $logs = $work->status['dispatch_logs'] ?? [];
        $alreadyImported = collect($logs)->contains('sha256', $sha256);
        if (! $alreadyImported && count($logs) >= 30) {
            $this->reject('Für diese Arbeit sind bereits 30 Versandprotokolle gespeichert.');
        }

        return [
            'purpose' => $report['purpose'], 'mode' => $report['mode'],
            'log_info' => [
                'dispatch_at' => collect($rows)->pluck('observed_at')->filter()->sort()->last()
                    ?? $this->providerTime($this->value($report['metadata'], 'Erstellt'))
                    ?? $this->providerTime($this->value($report['metadata'], 'Abschlusszeit')),
                'mode' => $report['live'] ? 'live' : $report['mode'],
                'recipient_scope' => collect($report['recipients'])->every(fn (array $recipient): bool => $this->value($recipient, 'Rolle') === 'Lehrperson') ? 'teacher' : 'students',
            ],
            'title' => $work->title, 'date' => $report['date'], 'rows' => $rows, 'already_imported' => $alreadyImported,
            'can_import' => ! $blocked,
            'hash' => hash('sha256', json_encode([$work->getAttributes(), $course->getAttributes(), $candidates->toArray(), $identities, $groups, $sha256], JSON_THROW_ON_ERROR)),
        ];
    }

    /** @param array<string, mixed> $preview */
    public function apply(TeachingCourseWork $work, array $preview, string $sha256, string $name, string $path): void
    {
        $status = $work->status ?? [];
        $logs = $status['dispatch_logs'] ?? [];
        if (! collect($logs)->contains('sha256', $sha256)) {
            $logs[] = ['name' => $name, 'sha256' => $sha256, 'file_path' => $path, 'storage_disk' => 'local', 'origin' => 'dispatch_import', 'purpose' => $preview['purpose']];
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
            if (! $row['accepted'] && $row['student_id'] && ! collect($attempts)->contains(fn (array $attempt): bool => $attempt['log_sha256'] === $sha256 && (int) $attempt['student_id'] === $row['student_id'])) {
                $attempts[] = ['student_id' => $row['student_id'], 'sent_at' => $row['observed_at'], 'mode' => $row['mode'],
                    'purpose' => $preview['purpose'], 'log_sha256' => $sha256, 'origin' => 'dispatch_import'];
            }
            if (! $row['accepted'] || collect($notifications)->contains('provider_id', $row['provider_id'])) {
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
        $attachment = collect($work->status['dispatch_logs'] ?? [])->first(fn (array $log): bool => ($log['origin'] ?? null) === 'dispatch_import' && ($log['sha256'] ?? null) === $sha256);
        abort_unless($attachment, 404);
        $path = $attachment['file_path'] ?? '';
        $schoolId = $work->teachingCourse->school_id;
        TeachingSynchronisationFiles::assertPath($path);
        abort_unless(($attachment['storage_disk'] ?? null) === 'local'
            && (str_starts_with($path, "teaching/work_dispatches/{$schoolId}/")
                || str_starts_with($path, "teaching/synchronisation/{$schoolId}/") || str_starts_with($path, 'teaching/personal_restores/')), 404);
        $name = PrivateImportSourceFile::downloadName($attachment['name'], 'versandprotokoll', 'txt');

        return Storage::disk('local')->download($path, $name, [
            'Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
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

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value;
    }

    private function providerTime(string $value): ?string
    {
        if (! preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,7})?(?:Z|[+-]\d{2}:\d{2})\z/', $value)) {
            return null;
        }
        $value = preg_replace('/\.\d+(?=Z|[+-])/', '', $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', str_replace('Z', '+00:00', $value));
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
            return null;
        }

        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
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
