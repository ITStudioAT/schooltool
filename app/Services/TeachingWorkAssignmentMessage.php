<?php

namespace App\Services;

use App\Models\DropboxConnection;
use App\Models\McTest;
use App\Models\TeachingCourseWork;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class TeachingWorkAssignmentMessage
{
    public const SCHOOL_REPLY_TO = 'guenther.kron@cdgym.at';

    public const SCHOOL_SIGNATURE = ['Freundliche Grüße', 'Dipl.-Ing. Günther Kron', 'Professor für Informatik',
        'Christian-Doppler-Gymnasium Salzburg', 'Abendgymnasium Salzburg'];

    public function __construct(private TeachingWorkAiExport $export, private DropboxModuleLinks $modules) {}

    /** @return array<string, mixed> */
    public function source(TeachingCourseWork $work, User $actor, ?string $date, string $transport): array
    {
        abort_unless($work->automationEnabled() && (int) $work->teachingCourse->user_id === (int) $actor->id, 403,
            'Nur die zuständige Lehrkraft kann E-Mails für diese Arbeit vorbereiten.');
        abort_if($transport === 'postmark' && isset($work->status['local_test_copy']['source_work_id']), 422,
            'Für eine Testkopie ist ausschließlich der lokale Testversand möglich.');
        abort_if($transport === 'mailpit' && ! app()->environment('local', 'testing'), 422);
        $days = collect(app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work))
            ->map(fn ($group) => $group['date'] ?? $work->date_for_all_groups?->format('Y-m-d'))->filter()->unique();
        abort_if(! $date && $days->count() > 1, 422, 'Bitte den Durchführungstermin dieser Prüfungsgruppe wählen.');
        $context = $this->export->buildExercise($work, $actor, false, $date, resolveLegacyPath: false)['context'];
        $people = $context['course_roster']['participants'];
        $addresses = [];
        foreach ($people as $person) {
            $email = strtolower(trim((string) $person['email']));
            abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL) && ! isset($addresses[$email]), 422,
                'In der Prüfungsgruppe fehlt eine eindeutige E-Mail-Adresse. Bitte die Kurszuordnung prüfen.');
            $addresses[$email] = true;
        }
        $method = TeachingWorkSubmissions::method($work) ?? [];
        abort_unless(in_array($method['channel'] ?? null, ['portal', 'email'], true), 422, 'Bitte zuerst den Abgabeweg speichern.');
        abort_if($method['channel'] === 'portal' && collect($people)->contains(fn ($person) => empty($person['schooltool_person_id'])),
            422, 'Für die Online-Abgabe braucht jede Person der Prüfungsgruppe ein zugeordnetes Schülerkonto.');
        abort_if($method['channel'] === 'email' && ! filter_var($method['email'] ?? null, FILTER_VALIDATE_EMAIL),
            422, 'Für die Abgabe per E-Mail fehlt die Zieladresse.');
        $reply = trim((string) $actor->email);
        $school = strcasecmp($reply, self::SCHOOL_REPLY_TO) === 0;
        abort_unless(filter_var($reply, FILTER_VALIDATE_EMAIL) && $actor->email_verified_at, 422, 'Die E-Mail-Adresse der Lehrkraft muss bestätigt sein.');
        $sender = $school ? config('services.postmark.work_test_sender') : config('mail.from.address');
        $senderName = $school ? config('services.postmark.work_test_sender_name') : config('mail.from.name');
        abort_unless(filter_var($sender, FILTER_VALIDATE_EMAIL) && filled($senderName), 422, 'Bitte einen gültigen Versandabsender einrichten.');
        $signature = $school ? self::SCHOOL_SIGNATURE : ['Mit freundlichen Grüßen', trim($actor->first_name.' '.$actor->last_name), $context['school']['name']];
        $release = $work->status['student_distribution'] ?? [];
        $instructions = trim((string) ($release['instructions'] ?? ''));
        $stored = collect($work->status['student_task_files'] ?? [])->whereInStrict('id', $release['file_ids'] ?? [])->values()->all();
        abort_unless(count($stored) === count(array_unique($release['file_ids'] ?? [])), 409, 'Mindestens eine gespeicherte Aufgaben-PDF fehlt.');
        $module = $this->modules->current($work);
        $exercise = $context['folder_binding'] ?? null;
        $assessment = null;
        if ($module && $stored === []) {
            $connection = DropboxConnection::query()->whereKey($module->dropbox_connection_id)->where('user_id', $actor->id)->whereNull('revoked_at')->first();
            if ($connection) {
                $current = $this->modules->exercise($connection, $work, $module, $date, initial: false);
                $assessment = ['status' => $current['status'] ?? null, 'folder_id' => $current['folder']['id'] ?? null,
                    'preparation' => $current['preparation']['status'] ?? null, 'files' => $current['preparation']['pdf_files']['Angaben'] ?? null];
            }
        }
        $logs = collect($work->status['dispatch_logs'] ?? [])->where('origin', 'dispatch_import')->where('purpose', 'tasks')->pluck('sha256');
        $prior = collect($work->status['dispatch_notifications'] ?? [])->where('origin', 'dispatch_import')->where('purpose', 'tasks')
            ->whereInStrict('log_sha256', $logs)->filter(fn ($notice) => filled($notice['sent_at'] ?? null) && filled($notice['provider_id'] ?? null))
            ->sortBy('sent_at')->pluck('sent_at', 'student_id')->all();
        $bindings = $work->status['multiple_choice_tests'] ?? [];
        $testIds = collect($bindings)->filter(fn ($binding, $slot) => is_array($binding) && ! empty($binding['test_id'])
            && (int) ($binding['work_id'] ?? $work->id) === (int) $work->id
            && ($slot === 'all' || ! $date || $slot === $date || str_starts_with((string) $slot, $date.'|') || ($binding['date'] ?? null) === $date))
            ->pluck('test_id')->unique()->values();
        $baseUrl = rtrim((string) config('app.url'), '/');
        $mc = [];
        if ($testIds->isNotEmpty()) {
            $tests = McTest::query()->whereIn('id', $testIds)->where('school_id', $work->teachingCourse->school_id)
                ->where('schoolyear_id', $work->teachingCourse->schoolyear_id)->where('owner_id', $actor->id)->orderBy('id')
                ->with(['attempts' => fn ($query) => $query->select('id', 'test_id', 'student_id', 'email')])->get();
            abort_unless($tests->count() === $testIds->count(), 409, 'Die gespeicherte Testzuordnung passt nicht mehr zu dieser Arbeit.');
            foreach ($tests as $test) {
                $attempts = $test->attempts->keyBy('student_id');
                foreach ($people as $person) {
                    $attempt = $attempts->get((int) $person['schooltool_person_id']);
                    abort_unless($attempt && strcasecmp($attempt->email, $person['email']) === 0, 409,
                        'Für mindestens eine Person fehlt der persönliche MC-Zugang. Bitte die Testzuordnung prüfen.');
                    $mc[$person['participant_id']][] = ['test_id' => $test->id, 'attempt_id' => $attempt->id, 'title' => $test->title,
                        'available' => $test->status === 'published' && $test->access_links_disabled_at === null];
                }
            }
        }
        $source = ['work_id' => $work->id, 'course_id' => $work->teaching_course_id, 'title' => $work->title,
            'date' => $date, 'deadline' => $work->finalDeadlineLabel(), 'people' => array_map(fn ($p) => array_intersect_key($p, array_flip(
                ['participant_id', 'schooltool_person_id', 'first_name', 'last_name', 'email', 'sex'])), $people),
            'method' => $method, 'instructions' => $instructions, 'files' => $stored, 'mc' => $mc, 'student_url' => $baseUrl,
            'module' => $module?->only(['id', 'folder_id', 'module_id']), 'folder' => $exercise['dropbox_folder_id'] ?? null,
            'assessment' => $assessment, 'prior' => $prior,
            'sender' => $sender, 'sender_name' => $senderName, 'reply_to' => $reply, 'signature' => $signature,
            'stream' => (string) (config('mail.mailers.postmark.message_stream_id') ?: 'outbound'), 'transport' => $transport];

        return $source;
    }

    /** @return list<array{name: string, bytes: string}> */
    public function documents(TeachingCourseWork $work, User $actor, array $source): array
    {
        $files = [];
        foreach ($source['files'] as $file) {
            $prefix = 'teaching/work_tasks/'.$work->teachingCourse->school_id.'/'.$work->id.'/';
            $path = $file['file_path'] ?? '';
            $schoolId = $work->teachingCourse->school_id;
            abort_unless(($file['storage_disk'] ?? null) === 'local' && ! str_contains($path, '..') && ! str_contains($path, '\\')
                && ((str_starts_with($path, $prefix) && basename($path) === substr($path, strlen($prefix)))
                    || preg_match('~\Ateaching/(?:work_tasks/'.$schoolId.'/restored/[^/]+|personal_restores/[a-f0-9-]{36})\z~', $path)),
                409, 'Eine Aufgaben-PDF ist nicht dieser Arbeit zugeordnet.');
            $bytes = Storage::disk('local')->get($path);
            abort_unless(is_string($bytes) && str_starts_with($bytes, '%PDF-') && hash_equals((string) $file['sha256'], hash('sha256', $bytes)),
                409, 'Die gespeicherte Aufgaben-PDF konnte nicht bestätigt werden.');
            $files[] = ['name' => $file['name'], 'bytes' => $bytes];
        }
        if ($files === [] && $source['module']) {
            $dropbox = app(DropboxWorkImport::class);
            $link = $this->modules->current($work);
            $connection = $dropbox->connection($actor);
            abort_unless($link && $link->dropbox_connection_id === $connection->id && $link->account_id === $connection->account_id, 409);
            $exercise = $this->modules->exercise($connection, $work, $link, $source['date'], initial: false);
            abort_unless(($exercise['status'] ?? null) === 'found' && ! empty($exercise['folder']['id']),
                409, 'Bitte die vorbereitete Leistungsfeststellung mit „Status erneut prüfen“ bestätigen oder die Aufgaben-PDF in der Vorbereitung speichern.');
            $reader = app(DropboxFolderContents::class);
            $files[] = ($exercise['preparation']['status'] ?? null) === 'ready'
                ? $reader->preparedPdf($this->modules, $connection, $work, $exercise, 'Angaben', $source['date'])
                : $reader->assignment($connection, $exercise['folder']['id']);
        } elseif ($files === [] && $source['folder']) {
            $dropbox = app(DropboxWorkImport::class);
            $connection = $dropbox->connection($actor);
            $binding = $dropbox->legacyFolderContext($connection, $source['folder']);
            abort_unless(! empty($binding['exercise']['id']), 409, 'Bitte den konkreten Prüfungsordner dieser Gruppe verknüpfen.');
            $files[] = app(DropboxFolderContents::class)->assignment($connection, $binding['exercise']['id']);
        }
        abort_if($files === [] && ($source['instructions'] === '' || preg_match('/(?:siehe|lese|lesen).*pdf/iu', $source['instructions'])),
            422, 'Es fehlen die Angaben. Bitte die Leistungsfeststellung verknüpfen oder zuerst Aufgabentext bzw. Aufgaben-PDF in der Vorbereitung speichern.');
        abort_if(array_sum(array_map(fn ($f) => 4 * (int) ceil(strlen($f['bytes']) / 3), $files)) > 9_500_000,
            422, 'Die Aufgaben-PDFs überschreiten zusammen die zulässige Nachrichtengröße. Bitte kleinere PDFs vorbereiten.');

        return $files;
    }

    /** @return array{subject:string,html:string,text:string,to:string} */
    public function render(array $source, array $person, array $attachments): array
    {
        $subject = 'Aufgabe · '.$source['title'];
        $salutation = match (strtolower((string) $person['sex'])) {
            'm' => 'Sehr geehrter Herr '.$person['last_name'].',',
            'w', 'f' => 'Sehr geehrte Frau '.$person['last_name'].',',
            default => 'Guten Tag '.$person['first_name'].' '.$person['last_name'].',',
        };
        $paragraphs = [$source['instructions'] ?: (count($attachments) === 1
            ? 'Bitte lesen und bearbeiten Sie das angehängte Aufgabenblatt.' : 'Bitte bearbeiten Sie die angehängten Aufgabenblätter.')];
        if ($source['deadline']) {
            $paragraphs[] = 'Abgabefrist: '.$source['deadline'];
        }
        $method = $source['method'];
        $portalUrl = null;
        $appUrl = $source['student_url'];
        $shareable = $source['transport'] === 'mailpit' || (str_starts_with($appUrl, 'https://')
            && ! in_array(parse_url($appUrl, PHP_URL_HOST), ['localhost', '127.0.0.1', '::1', '[::1]'], true));
        if ($method['channel'] === 'portal') {
            $paragraphs[] = 'Laden Sie Ihre Abgabe in Schooltool im Kurs unter „Meine Aufgaben“ hoch.';
            if ($shareable) {
                $portalUrl = $appUrl.'/student/overview?panel=tasks&course='.$source['course_id'];
            }
        } else {
            $paragraphs[] = 'Senden Sie Ihre Abgabe per E-Mail an '.$method['email'].'. Bitte geben Sie „'.$source['title'].'“ im Betreff an.';
        }
        $testLinks = [];
        foreach ($source['mc'][$person['participant_id']] ?? [] as $test) {
            $paragraphs[] = 'Öffnen Sie in Ihrem Schülerkonto unter „Multiple Choice“ den Test „'.$test['title'].'“.';
            if ($shareable) {
                $testLinks[] = ['title' => $test['title'], 'url' => $appUrl.'/student/multiple-choice/'.$test['attempt_id'].'?course='.$source['course_id']];
            }
        }
        $signature = $source['signature'];
        $ending = (count($attachments) === 1 ? 'Anhang: ' : 'Anhänge: ').($attachments === [] ? 'keine' : implode(', ', $attachments));
        $text = implode("\n\n", array_filter([$salutation, ...$paragraphs, $portalUrl, ...array_column($testLinks, 'url'), implode("\n", $signature), $ending]));
        $html = view('emails.schooltool-postmark-test', compact('subject', 'salutation', 'paragraphs', 'portalUrl', 'testLinks', 'signature', 'attachments'))->render();

        return ['to' => $person['email'], 'subject' => $subject, 'html' => $html, 'text' => $text];
    }
}
