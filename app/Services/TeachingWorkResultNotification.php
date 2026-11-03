<?php

namespace App\Services;

use App\Models\SchoolTool;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Support\SchooltoolAssessmentJson;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class TeachingWorkResultNotification
{
    public const AI_NOTE = 'Diese Auswertung wurde mit Unterstützung von KI erstellt. Wenn Sie einen Fehler bemerken oder mit der Bewertung nicht einverstanden sind, wenden Sie sich bitte jederzeit an Ihre Lehrkraft, damit wir die Auswertung gemeinsam überprüfen können.';

    public const LINK = 'https://schooltool.at/';

    public function __construct(private TeachingWorkAiExport $export, private TeachingCourseWorkEntrySyncService $entries,
        private TeachingSimulation $simulator, private TeachingSimulationMailpit $mailpit) {}

    protected function snapshot(TeachingCourseWork $work, User $actor): array
    {
        $context = $this->export->build($work, $actor)['context'];
        $ids = array_values(array_unique(array_merge([], ...array_column($this->entries->groupsForWork($work), 'student_ids'))));
        $teacher = $work->teachingCourse->user ?: $actor;
        $activeYear = SchoolTool::where('school_id', $work->teachingCourse->school_id)->value('active_schoolyear_id') ?? $teacher->schoolyear_id;

        return ['people' => array_values(array_filter($context['course_roster']['participants'], fn (array $person): bool => $person['active']
                && in_array((int) $person['schooltool_person_id'], $ids, true))),
            'entry_ids' => (int) $activeYear === (int) $work->teachingCourse->schoolyear_id
                ? $work->teachingCourseStudentEntries()->pluck('user_id')->map(fn ($id): int => (int) $id)->all() : [],
            'simulation' => app()->isLocal() ? $this->simulator->collectionStatus($work, $actor->id, $ids) : null,
            'collection' => app(TeachingWorkCollectionStatus::class)->cached($work),
            'teacher' => ['first_name' => $teacher->first_name, 'last_name' => $teacher->last_name, 'email' => $teacher->email,
                'sex' => $teacher->sex]];
    }

    protected function currentWork(TeachingCourseWork $work): TeachingCourseWork
    {
        return $work->fresh();
    }

    public function preview(TeachingCourseWork $work, User $actor, array $input = []): array
    {
        abort_unless($work->automationEnabled(), 422, 'Automatik ist für diese Arbeit ausgeschaltet.');
        $source = $this->snapshot($work, $actor);
        $local = app()->isLocal();
        $previewOnly = (bool) ($input['teacher_preview'] ?? false);
        abort_if($previewOnly && ! $local, 403);
        $previewId = $previewOnly ? ($input['preview_id'] ?? (string) Str::uuid()) : null;
        abort_if($previewOnly && ! Str::isUuid($previewId), 422);
        $teacher = $source['teacher'];
        $addressForm = $input['address_form'] ?? 'sie';
        abort_unless(in_array($addressForm, ['du', 'sie'], true), 422, 'Bitte Du-Form oder Sie-Form auswählen.');
        $possessive = $addressForm === 'du' ? 'Deine' : 'Ihre';
        $subject = $input['subject'] ?? 'Ergebnisse zur Leistungsfeststellung: '.$work->title;
        $defaultBody = "{PersönlicheAnrede}\n\n"
            .($local ? $possessive.' Testauswertung zur Leistungsfeststellung „'.$work->title.'“ ist verfügbar.'
                : ($addressForm === 'du'
                    ? 'die Korrektur deiner Ausarbeitung zur Leistungsfeststellung „'.$work->title.'“ ist abgeschlossen. Deine Ergebnisse kannst du auf Schooltool einsehen.'
                    : 'die Korrektur Ihrer Ausarbeitung zur Leistungsfeststellung „'.$work->title.'“ ist abgeschlossen. Ihre Ergebnisse können Sie auf Schooltool einsehen.'))
            ."\n\n[Ergebnisse auf Schooltool ansehen](".self::LINK.")\n\n".self::AI_NOTE."\n\nMit besten Grüßen\n".$this->signature($teacher);
        $body = $input['body'] ?? $defaultBody;
        abort_unless(! preg_match('/[\r\n]/', $subject) && substr_count($body, '{PersönlicheAnrede}') === 1
            && substr_count($body, self::AI_NOTE) === 1 && str_contains($body, '[Ergebnisse auf Schooltool ansehen]('.self::LINK.')'), 422,
            'Bitte persönliche Anrede, Schooltool-Link und KI-Hinweis in der Nachricht erhalten.');
        $history = $this->status($work, $actor->id);
        $rows = $bindings = [];
        foreach ($source['people'] as $person) {
            $id = (int) $person['schooltool_person_id'];
            $receipt = collect($work->status['assessment_json_imports'] ?? [])->last(fn (array $item): bool => (int) $item['student_id'] === $id);
            $record = $receipt['record'] ?? [];
            $pdf = collect($work->status['evaluation_pdfs'] ?? [])->first(fn (array $item): bool => (int) ($item['student_id'] ?? 0) === $id
                && ($item['origin'] ?? null) === 'evaluation_import' && ($item['sha256'] ?? null) === ($record['pdf']['sha256'] ?? null));
            $simulated = collect($source['simulation']['evaluation']['rows'] ?? [])->firstWhere('student_id', $id);
            $reason = null;
            if (! filter_var($person['email'], FILTER_VALIDATE_EMAIL)) {
                $reason = 'Gültige E-Mail-Adresse fehlt';
            } elseif (($record['evaluation_state'] ?? null) !== 'complete' || ! $pdf || ! in_array($id, $source['entry_ids'], true)) {
                $reason = 'Bewertung noch offen oder Ergebnis noch nicht übernommen';
            } elseif (! $local && str_starts_with($receipt['exercise_id'] ?? '', 'simulation:')) {
                $reason = 'Testbewertung – kein echter Ergebnisversand';
            } elseif ($source['simulation'] && (($source['simulation']['evaluation']['run_complete'] ?? null) === false || ! $simulated || $simulated['state'] !== 'complete'
                || ($simulated['pdf_sha256'] ?? null) !== $pdf['sha256']
                || strtotime($simulated['evaluation_completed_at'] ?? '') !== strtotime($record['evaluation_completed_at'] ?? ''))) {
                $reason = 'Aktuelle Testbewertung noch nicht übernommen';
            } elseif (! Storage::disk($pdf['storage_disk'] ?? 'local')->exists($pdf['file_path'])
                || ! hash_equals($pdf['sha256'], hash('sha256', Storage::disk($pdf['storage_disk'] ?? 'local')->get($pdf['file_path'])))) {
                $reason = 'Persönliche Auswertung ist nicht verfügbar';
            }
            $already = collect($history['rows'] ?? [])->contains('student_id', $id);
            if (! $local && $receipt) {
                $already = $already || collect($work->status['dispatch_notifications'] ?? [])->contains(fn (array $item): bool => ($item['purpose'] ?? 'results') === 'results'
                    && (int) $item['student_id'] === $id && ($item['origin'] ?? null) === 'dispatch_import'
                    && strtotime($item['sent_at'] ?? '') >= strtotime($record['evaluation_completed_at'] ?? '')
                    && collect($work->status['dispatch_logs'] ?? [])->contains('sha256', $item['log_sha256'] ?? null));
            }
            if (! $reason) {
                $bindings[$id] = ['record_checksum' => $receipt['record_checksum'], 'pdf_sha256' => $pdf['sha256']];
            }
            $rows[] = ['student_id' => $id, 'first_name' => $person['first_name'], 'last_name' => $person['last_name'],
                'email' => $person['email'], 'greeting' => $this->greeting($person, $addressForm), 'eligible' => ! $reason && ! $already,
                'status' => $reason ?: ($already ? 'Bereits informiert' : 'Ergebnis verfügbar'), 'already_sent' => $already];
        }
        usort($rows, fn (array $a, array $b): int => strnatcasecmp($a['last_name'].' '.$a['first_name'], $b['last_name'].' '.$b['first_name']));
        $eligible = array_column(array_values(array_filter($rows, fn (array $row): bool => $row['eligible'])), 'student_id');
        $workflowBlocked = null;
        if ($source['simulation']) {
            if (! $source['simulation']['complete']) {
                $workflowBlocked = 'Zuerst die Testabgaben vollständig einsammeln.';
            } elseif (($source['simulation']['evaluation']['run_complete'] ?? false) !== true) {
                $workflowBlocked = 'Zuerst den aktuellen Abgabenbestand bewerten.';
            } elseif (collect($source['simulation']['evaluation']['rows'])->contains(fn (array $row): bool => $row['state'] === 'complete' && ! isset($bindings[$row['student_id']]))) {
                $workflowBlocked = 'Zuerst alle abgeschlossenen Ergebnisse des aktuellen Bewertungslaufs übernehmen.';
            }
        } elseif ($change = $source['collection']['last_changed_at'] ?? (($source['collection']['changes_detected'] ?? false) === true ? ($source['collection']['completed_at'] ?? null) : null)) {
            $latestImport = collect($work->status['assessment_json_packages'] ?? [])->filter(fn (array $package): bool => preg_match('/\A[a-f0-9]{64}\z/', $package['package_checksum'] ?? '')
                && collect($source['people'])->every(fn (array $person): bool => collect($work->status['assessment_json_imports'] ?? [])->contains(fn (array $receipt): bool => (int) $receipt['student_id'] === (int) $person['schooltool_person_id'] && $receipt['exercise_id'] === $package['exercise_id'])))
                ->max('imported_at');
            $latestGrade = $latestImport ?: collect($work->status['assessment_json_imports'] ?? [])->max('record.evaluation_completed_at');
            if (strtotime($change) !== false && (! $latestGrade || strtotime($latestGrade) < strtotime($change))) {
                $workflowBlocked = 'Zuerst die neuen oder geänderten Abgaben bewerten und die Ergebnisse übernehmen.';
            }
        }
        if ($workflowBlocked && ! $previewOnly) {
            $eligible = [];
            foreach ($rows as &$row) {
                if ($row['eligible']) {
                    $row['eligible'] = false;
                    $row['status'] = $workflowBlocked;
                }
            }
            unset($row);
        }
        $selected = $previewOnly ? [] : array_values(array_unique(array_map('intval', $input['student_ids'] ?? $eligible)));
        sort($selected);
        abort_unless(array_diff($selected, $eligible) === [], 409, 'Die Auswahl enthält offene oder bereits informierte Personen. Bitte die Vorschau neu laden.');
        $messages = [];
        foreach ($rows as $row) {
            if (in_array($row['student_id'], $selected, true)) {
                $messages[] = $this->message($row, $subject, $body, $local);
            }
        }
        if ($messages !== [] || $previewOnly) {
            abort_unless(filter_var($teacher['email'], FILTER_VALIDATE_EMAIL), 422, 'Die E-Mail-Adresse der Lehrperson fehlt.');
            $messages[] = $this->message($teacher + ['student_id' => null, 'greeting' => $this->greeting($teacher, $addressForm)], $subject, $body, $local);
        }

        return ['work_id' => $work->id, 'course_id' => $work->teaching_course_id, 'simulation' => $local,
            'run_id' => $source['simulation']['run_id'] ?? null, 'transport' => $local ? 'mailpit' : 'office_outlook',
            'teacher_preview' => $previewOnly, 'preview_id' => $previewId,
            'workflow_blocked' => $workflowBlocked,
            'last_completed_at' => $history['completed_at'] ?? null,
            'subject' => $subject, 'body' => $body, 'default_body' => $defaultBody, 'address_form' => $addressForm, 'rows' => $rows, 'student_ids' => $selected,
            'messages' => $messages, 'bindings' => $bindings, 'teacher' => $teacher,
            'hash' => SchooltoolAssessmentJson::digest([$work->id, $work->teaching_course_id, $actor->id,
                $source['simulation']['run_id'] ?? null, $previewOnly, $previewId, $rows, $selected, $bindings, $messages])];
    }

    private function signature(array $teacher): string
    {
        if (strcasecmp($teacher['email'], 'guenther.kron@cdgym.at') === 0) {
            return "**Prof. Dipl.-Ing. Günther Kron**\n\n*Informatik, Wirtschaftsinformatik, Medien, Digitale Grundbildung*\nChristian-Doppler-Gymnasium Salzburg\nT: 0662 431208 | W: cdgym.at\n\n*Informatik*\nAbendgymnasium Salzburg\nT: 0662 434575 | W: abendgymnasium.salzburg.at";
        }

        return $teacher['first_name'].' '.$teacher['last_name'];
    }

    private function greeting(array $person, string $addressForm): string
    {
        $sex = strcasecmp($person['email'], 'guenther.kron@cdgym.at') === 0 ? 'm' : strtolower((string) ($person['sex'] ?? ''));

        return match ($sex) {
            'm' => $addressForm === 'du' ? 'Lieber '.$person['first_name'].',' : 'Sehr geehrter Herr '.$person['last_name'].',',
            'w', 'f' => $addressForm === 'du' ? 'Liebe '.$person['first_name'].',' : 'Sehr geehrte Frau '.$person['last_name'].',',
            default => ($addressForm === 'du' ? 'Hallo '.$person['first_name'] : 'Guten Tag '.$person['first_name'].' '.$person['last_name']).',',
        };
    }

    private function signatureHtml(string $text): string
    {
        $escaped = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', e($text));

        return nl2br(preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/u', '<em>$1</em>', $escaped));
    }

    private function message(array $person, string $subject, string $body, bool $local): array
    {
        $text = str_replace('{PersönlicheAnrede}', $person['greeting'], $body);
        abort_if(preg_match('/\{[^{}]+\}/u', $text), 422, 'Die Nachricht enthält noch einen nicht ausgefüllten Platzhalter.');
        $paragraphs = [];
        $signature = [];
        $inSignature = false;
        foreach (preg_split('/\n\s*\n/', $text) as $index => $part) {
            if (preg_match('/\A(Mit (?:besten|freundlichen) Grüßen|Liebe Grüße)\n(.+)\z/us', $part, $match)) {
                $paragraphs[] = '<p style="margin:0 0 16px;line-height:1.6;text-align:left">'.e($match[1]).'</p>';
                $signature[] = '<p style="margin:0 0 16px;line-height:1.6;text-align:left;color:#465953">'.$this->signatureHtml($match[2]).'</p>';
                $inSignature = true;

                continue;
            }
            if ($inSignature) {
                $signature[] = '<p style="margin:0 0 16px;line-height:1.6;text-align:left;color:#465953">'.$this->signatureHtml($part).'</p>';

                continue;
            }
            if ($part === '[Ergebnisse auf Schooltool ansehen]('.self::LINK.')') {
                $paragraphs[] = '<table role="presentation" align="center" width="100%" style="width:100%;max-width:360px;margin:4px auto 26px"><tr><td align="center" bgcolor="#173e56" style="padding:16px 20px;border-radius:5px;text-align:center">'
                    .'<a href="'.self::LINK.'" style="display:block;color:#ffffff !important;font-family:Arial,Helvetica,sans-serif;text-decoration:none;font-weight:bold"><span style="color:#ffffff !important">Ergebnisse auf Schooltool ansehen</span></a></td></tr><tr><td height="28" style="height:28px;line-height:28px;font-size:1px">&nbsp;</td></tr></table>';

                continue;
            }
            $escaped = nl2br(e($part));
            $paragraphs[] = $part === self::AI_NOTE
                ? '<p style="margin:0 0 16px;line-height:1.6;text-align:left;font-size:0.9em;font-style:italic"><em>'.$escaped.'</em></p>'
                : '<p style="margin:0 0 16px;line-height:1.6;text-align:left">'.($index === 0 ? '<strong>'.$escaped.'</strong>' : $escaped).'</p>';
        }

        return ['student_id' => $person['student_id'], 'email' => $person['email'], 'name' => $person['first_name'].' '.$person['last_name'],
            'subject' => ($local ? '[Simulation] ' : '').$subject,
            'text' => preg_replace('/\*\*([^*]+)\*\*|\*([^*\n]+)\*/u', '$1$2', str_replace('[Ergebnisse auf Schooltool ansehen]('.self::LINK.')', 'Ergebnisse auf Schooltool ansehen: '.self::LINK, $text)),
            'html' => view('emails.teaching-work-result-notification', ['body' => implode('', $paragraphs), 'signature' => implode('', $signature)])->render()];
    }

    private function root(TeachingCourseWork $work, int $owner): string
    {
        return 'teaching-simulator/result-notifications/'.$owner.'/'.$work->id;
    }

    public function status(TeachingCourseWork $work, int $owner): ?array
    {
        if (! app()->isLocal()) {
            return null;
        }
        $disk = Storage::disk('local');
        $root = $this->root($work, $owner);
        if (! $disk->exists($root.'/journal.json')) {
            return null;
        }
        try {
            $journal = json_decode($disk->get($root.'/journal.json'), true, 64, JSON_THROW_ON_ERROR);
            if (($journal['work_id'] ?? null) !== $work->id || ($journal['course_id'] ?? null) !== $work->teaching_course_id || ($journal['owner_id'] ?? null) !== $owner) {
                return null;
            }
            $rows = [];
            $teacherCount = 0;
            $studentTimes = [];
            $teacherTimes = [];
            foreach ($journal['messages'] as $message) {
                if (($message['scope'] ?? null) === 'teacher_preview') {
                    continue;
                }
                if (($message['state'] ?? null) !== 'confirmed' || ! preg_match('/\A[a-f0-9]{64}\z/', $message['key'] ?? '')
                    || ! $disk->exists($root.'/'.$message['key'].'.eml')
                    || ! hash_equals($message['message_sha256'], hash('sha256', $disk->get($root.'/'.$message['key'].'.eml')))) {
                    continue;
                }
                if ($message['student_id'] === null) {
                    $teacherCount++;
                    if (strtotime($message['confirmed_at'] ?? $message['sent_at'] ?? '') !== false) {
                        $teacherTimes[] = $message['confirmed_at'] ?? $message['sent_at'];
                    }

                    continue;
                }
                $receipt = collect($work->status['assessment_json_imports'] ?? [])->last(fn (array $item): bool => (int) $item['student_id'] === $message['student_id']);
                if (($receipt['record_checksum'] ?? null) === $message['record_checksum'] && ($receipt['record']['pdf']['sha256'] ?? null) === $message['pdf_sha256']) {
                    $rows[$message['student_id']] = array_intersect_key($message, array_flip(['student_id', 'sent_at', 'record_checksum', 'pdf_sha256']));
                    if (strtotime($message['confirmed_at'] ?? $message['sent_at'] ?? '') !== false) {
                        $studentTimes[] = $message['confirmed_at'] ?? $message['sent_at'];
                    }
                }
            }
            rsort($studentTimes, SORT_STRING);
            rsort($teacherTimes, SORT_STRING);
            $completedAt = $studentTimes !== [] && $teacherTimes !== [] && strcmp($teacherTimes[0], $studentTimes[0]) >= 0 ? $teacherTimes[0] : null;

            return ['origin' => 'mailpit_result_notification', 'simulation' => true, 'work_id' => $work->id,
                'course_id' => $work->teaching_course_id, 'rows' => array_values($rows), 'teacher_count' => $teacherCount, 'completed_at' => $completedAt];
        } catch (Throwable) {
            return null;
        }
    }

    public function sendSimulation(TeachingCourseWork $work, User $actor, array $input): array
    {
        abort_unless(app()->isLocal(), 403, 'Dieser Versand ist ausschließlich lokal über Mailpit möglich.');

        return Cache::store('file')->lock('result-notification:'.$actor->id.':'.$work->id, 300)->block(5, function () use ($work, $actor, $input): array {
            $preview = $this->preview($work, $actor, $input);
            abort_unless(hash_equals($preview['hash'], $input['hash']) && $preview['messages'] !== [], 409, 'Bewertungen, Auswahl oder Text geändert. Bitte die Vorschau neu laden.');
            $this->mailpit->assertSafe();
            $disk = Storage::disk('local');
            $root = $this->root($work, $actor->id);
            $journal = $disk->exists($root.'/journal.json') ? json_decode($disk->get($root.'/journal.json'), true, 64, JSON_THROW_ON_ERROR)
                : ['work_id' => $work->id, 'course_id' => $work->teaching_course_id, 'owner_id' => $actor->id, 'messages' => []];
            abort_unless($journal['work_id'] === $work->id && $journal['course_id'] === $work->teaching_course_id && $journal['owner_id'] === $actor->id, 409);
            foreach ($preview['messages'] as $message) {
                $current = $this->preview($this->currentWork($work), $actor, ['student_ids' => [], 'subject' => $preview['subject'], 'body' => $preview['body'], 'address_form' => $preview['address_form']]);
                $expectedBindings = array_intersect_key($preview['bindings'], array_flip($preview['student_ids']));
                $currentBindings = array_intersect_key($current['bindings'], $expectedBindings);
                $person = $message['student_id'] === null ? $current['teacher'] : collect($current['rows'])->firstWhere('student_id', $message['student_id']);
                abort_unless($currentBindings === $expectedBindings && $current['teacher'] === $preview['teacher']
                    && ($preview['teacher_preview'] || ! $current['workflow_blocked'])
                    && ($person['email'] ?? null) === $message['email']
                    && ($person['first_name'] ?? '').' '.($person['last_name'] ?? '') === $message['name'],
                    409, 'Bewertung, Empfänger oder Verfügbarkeit während des Versands geändert. Bestätigte Nachrichten bleiben gespeichert.');
                $binding = $preview['teacher_preview'] ? ['preview_id' => $preview['preview_id']]
                    : ($message['student_id'] === null ? ['selection' => array_intersect_key($preview['bindings'], array_flip($preview['student_ids']))] : $preview['bindings'][$message['student_id']]);
                $key = SchooltoolAssessmentJson::digest([$actor->id, $work->id, $preview['run_id'], $message['student_id'], $binding]);
                $messageId = 'result.'.$key.'@schooltool.test';
                $payloadHash = SchooltoolAssessmentJson::digest($message);
                $previous = $journal['messages'][$key] ?? null;
                abort_if($previous && $previous['payload_sha256'] !== $payloadHash, 409, 'Für diesen Versand besteht bereits ein anderer Nachrichtenstand. Bitte den vorhandenen Lauf prüfen.');
                if ($message['student_id'] === null && ($previous['state'] ?? null) === 'confirmed') {
                    continue;
                }
                $journal['messages'][$key] = $previous ?? ['key' => $key, 'student_id' => $message['student_id'], 'state' => 'pending',
                    'scope' => $preview['teacher_preview'] ? 'teacher_preview' : 'notification',
                    'payload_sha256' => $payloadHash, 'record_checksum' => $binding['record_checksum'] ?? null, 'pdf_sha256' => $binding['pdf_sha256'] ?? null];
                abort_unless($disk->put($root.'/journal.json', json_encode($journal, JSON_THROW_ON_ERROR)), 500, 'Der Versandlauf konnte nicht gesichert werden.');
                $senderName = $preview['teacher']['first_name'].' '.$preview['teacher']['last_name'];
                if (strcasecmp($preview['teacher']['email'], 'guenther.kron@cdgym.at') === 0 && $senderName === 'Günther Kron') {
                    $senderName = 'DI. Günther Kron';
                }
                $id = $previous['mailpit_id'] ?? $this->mailpit->findMessage($messageId) ?? $this->mailpit->send(['From' => ['Email' => $preview['teacher']['email'], 'Name' => $senderName],
                    'To' => [['Email' => $message['email'], 'Name' => $message['name']]], 'Cc' => [], 'Bcc' => [], 'Attachments' => [],
                    'Subject' => $message['subject'], 'Text' => $message['text'], 'HTML' => $message['html'],
                    'Headers' => ['Message-ID' => '<'.$messageId.'>', 'X-Schooltool-Simulation' => 'results-'.$work->id], 'Tags' => ['Schooltool-Simulation', 'Ergebnisbenachrichtigung']]);
                $journal['messages'][$key]['mailpit_id'] = $id;
                abort_unless($disk->put($root.'/journal.json', json_encode($journal, JSON_THROW_ON_ERROR)), 500);
                $received = $this->mailpit->message($id);
                abort_unless(count($received['To'] ?? []) === 1 && strcasecmp($received['To'][0]['Address'] ?? '', $message['email']) === 0
                    && strcasecmp($received['From']['Address'] ?? '', $preview['teacher']['email']) === 0
                    && ($received['Subject'] ?? null) === $message['subject']
                    && trim(str_replace("\r\n", "\n", $received['Text'] ?? '')) === trim(str_replace("\r\n", "\n", $message['text']))
                    && trim(str_replace("\r\n", "\n", $received['HTML'] ?? '')) === trim(str_replace("\r\n", "\n", $message['html']))
                    && empty($received['Cc']) && empty($received['Bcc']) && empty($received['Attachments']), 502, 'Der Mailpit-Eingang passt nicht zur freigegebenen Nachricht.');
                $raw = $this->mailpit->raw($id);
                abort_unless(str_contains($raw, $messageId), 502, 'Die gespeicherte E-Mail hat keine passende Nachrichtenkennung.');
                abort_unless($disk->put($root.'/'.$key.'.eml', $raw), 500, 'Die bestätigte E-Mail konnte nicht gesichert werden.');
                $confirmedAt = $previous['confirmed_at'] ?? $previous['sent_at'] ?? now()->toISOString();
                $journal['messages'][$key] += ['mailpit_id' => $id, 'sent_at' => $confirmedAt, 'confirmed_at' => $confirmedAt, 'message_sha256' => hash('sha256', $raw)];
                $journal['messages'][$key]['state'] = 'confirmed';
                abort_unless($disk->put($root.'/journal.json', json_encode($journal, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)), 500);
            }

            return $this->status($work, $actor->id);
        });
    }

    public function officeOrder(TeachingCourseWork $work, User $actor, array $input): string
    {
        abort_if(app()->isLocal(), 403, 'Lokale Ergebnisnachrichten werden ausschließlich über Mailpit getestet.');
        $preview = $this->preview($work, $actor, $input);
        abort_unless(hash_equals($preview['hash'], $input['hash']) && $preview['messages'] !== [], 409, 'Bitte die Vorschau neu laden.');
        $dropbox = app(DropboxWorkImport::class);
        $folder = $dropbox->resultsFolder($dropbox->connection($actor), $work, $input['date'] ?? null);
        $context = ['purpose' => 'announce-exercise-results', 'work_id' => $work->id, 'course_id' => $work->teaching_course_id,
            'exercise_folder' => $folder, 'selected_student_ids' => $preview['student_ids'], 'recipients' => $preview['messages'],
            'email_layout' => 'school-standard-v1', 'preserve_prepared_html' => true,
            'subject' => $preview['subject'], 'body' => $preview['body'], 'teacher_copy' => true,
            'operation' => ['prepare' => true, 'send' => false, 'change_grades' => false]];

        return "# Auftrag: Ergebnisbenachrichtigungen vorbereiten\n\nVerwenden Sie den Skill announce-exercise-results für genau diese bestehende Arbeit und die ausdrücklich gewählte Teilmenge. Keine Bewertungen ändern. Lesen Sie den bestätigten Arbeitsordner ausschließlich über die Dropbox-API, erhalten Sie angepasste Nachrichtentexte, gleichen Sie bereits bestätigte Ergebnisnachrichten gegen Doppelversand ab und prüfen Sie die tatsächliche Verfügbarkeit im Schülerzugang. Die personalisierten HTML-Nachrichten in recipients verwenden die aktuelle Schul-Standardgestaltung aus dem Aufgabenversand. Übernehmen Sie dieses vorbereitete HTML unverändert in die Office-Payloads; verwenden Sie keine ältere Gestaltung aus einer anderen Vorlage. Bei Textänderungen zuerst die Vorschau in Schooltool aktualisieren. Zeigen Sie die vollständige personalisierte Nachricht als bearbeitbaren E-Mail-Schreibblock. Dieser Vorbereitungsauftrag sendet nichts. Versand erst nach konkreter Freigabe des aktuellen Textes und Laufs über das vorhandene CDGym-Office-/Outlook-Konto; eine separate Lehrernachricht im selben Modus. Nach bestätigtem Versand den Ergebnisbeleg im bestehenden Ordner sichern, damit Schooltool ihn mit Erneut prüfen übernehmen kann.\n\n## Gespeicherte Daten\n\n".json_encode($context, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
