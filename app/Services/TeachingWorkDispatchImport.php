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
        if (! preg_match('/\AVERSANDPROTOKOLL[^\n]*\n+(.+?)\n(EMPFÄNGERSTATUS|EMPFÄNGER 1)\s*\n(.*)\z/su', $text, $sections)) {
            $this->reject('Versandprotokoll mit Metadaten und Empfängereinträgen erwartet.');
        }
        try {
            $metadata = json_decode($sections[1], true, 32, JSON_THROW_ON_ERROR);
            $legacy = $sections[2] === 'EMPFÄNGER 1';
            $recipients = $legacy ? $this->legacyRecipients($sections[3]) : json_decode($sections[3], true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->reject('Das Versandprotokoll enthält ungültiges JSON.');
        }
        if (! is_array($metadata) || ! is_array($recipients) || ! array_is_list($recipients) || count($recipients) > 100 || $recipients === []) {
            $this->reject('Das Versandprotokoll muss 1 bis 100 Empfängereinträge enthalten.');
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
        $teacherTest = $heading === 'VERSANDPROTOKOLL – ERGEBNISBENACHRICHTIGUNG – LIVE-TEST NUR AN LEHRPERSON (POSTMARK)'
            && $this->value($metadata, 'Versandzweck') === 'Ergebnisbenachrichtigung'
            && $this->value($metadata, 'Testzweck') !== ''
            && $this->value($metadata, 'Schueleranzahl') === '0'
            && $this->value($metadata, 'Lehreranzahl') === (string) count($recipients)
            && collect($recipients)->every(fn (mixed $row): bool => is_array($row) && $this->value($row, 'Rolle') === 'Lehrperson');
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
            'live' => (bool) preg_match('/\AVERSANDPROTOKOLL – (?:(?:AUFGABENVERSAND|ERGEBNISBENACHRICHTIGUNG) – )?LIVE-VERSAND \(POSTMARK\)\z/u', $heading)];
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
    public function preview(TeachingCourseWork $work, array $report, string $sha256): array
    {
        $course = $work->teachingCourse;
        if ($work->date_for_all_groups?->format('Y-m-d') !== $report['date'] || $this->assignment((string) $work->title) !== $report['title']) {
            $this->reject('Arbeitsdatum oder Titel des Versandprotokolls passt nicht zur gespeicherten Arbeit.');
        }
        $candidates = $course->teachingCourseWorks()->whereDate('date_for_all_groups', $report['date'])->get(['id', 'title']);
        if ($candidates->filter(fn (TeachingCourseWork $candidate): bool => $this->assignment((string) $candidate->title) === $report['title'])->count() !== 1) {
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
            $accepted = $report['live'] && $this->value($report['metadata'], 'Modus') === 'Live-Versand (Postmark)'
                && ($report['metadata']['AktuelleServerpruefung']['DeliveryType'] ?? null) === 'Live'
                && $this->value($recipient, 'Modus') === 'Live-Versand (Postmark)'
                && $this->value($recipient, 'Status') === 'Postmark-API-Annahme bestätigt'
                && $this->value($recipient, 'Providerstatus') === 'Sent'
                && $this->value($recipient, 'HTTPStatus') === '200'
                && $this->value($recipient, 'PostmarkFehlercode') === '0'
                && is_string($recipient['Fehler'] ?? null) && trim($recipient['Fehler']) === ''
                && preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/i', $row['provider_id'])
                && $sentAt !== null && ($report['purpose'] === 'tasks' || (new DateTimeImmutable($sentAt))->setTimezone(new DateTimeZone('Europe/Vienna'))->format('Y-m-d') >= $report['date']);
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
        if (! preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})\z/', $value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', str_replace('Z', '+00:00', $value));
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
            return null;
        }

        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['protocol' => $message]);
    }
}
