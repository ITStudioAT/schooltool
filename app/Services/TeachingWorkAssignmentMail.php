<?php

namespace App\Services;

use App\Jobs\CreateTeachingAssessment;
use App\Jobs\SendTeachingWorkAssignments;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Support\SchooltoolAssessmentJson;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Mime\Address;
use Throwable;

class TeachingWorkAssignmentMail
{
    public function __construct(private TeachingWorkAssignmentMessage $messages, private TeachingSimulationMailpit $mailpit) {}

    public function slot(TeachingCourseWork $work, ?string $date): string
    {
        $dates = collect(app(TeachingCourseWorkEntrySyncService::class)->groupsForWork($work))->pluck('date')->filter()->unique();

        return $date ?: ($work->date_for_all_groups?->format('Y-m-d') ?? ($dates->count() === 1 ? $dates->first() : 'all'));
    }

    public function preview(TeachingCourseWork $work, string $slot): ?array
    {
        $batch = $work->status['assignment_mail']['batches'][$slot] ?? null;
        if (! $batch) {
            return null;
        }
        try {
            $draft = $this->readDraft($work, $batch);
        } catch (DecryptException|HttpExceptionInterface $exception) {
            return ['batch' => $batch, 'from' => null, 'reply_to' => null, 'messages' => [], 'attachments' => [],
                'unavailable' => 'Die gespeicherten Nachrichten lassen sich auf diesem System nicht öffnen. Versandbelege bleiben sichtbar. Bitte eine neue Fassung vorbereiten.'];
        }

        return ['batch' => $batch, 'from' => $draft['from'], 'reply_to' => $draft['reply_to'],
            'messages' => $draft['messages'], 'attachments' => array_column($draft['files'], 'name')];
    }

    public function prepare(TeachingCourseWork $work, User $actor, string $slot, string $transport, ?string $previous): void
    {
        $work->assertStudentReleaseOpen();
        $source = $this->messages->source($work, $actor, $slot === 'all' ? null : $slot, $transport);
        $files = $this->messages->documents($work, $actor, $source);
        $id = (string) Str::uuid();
        $directory = $this->directory($work->id, $id);
        $disk = Storage::disk('local');
        try {
            $parts = $artifacts = [];
            foreach ($files as $index => $file) {
                $hash = hash('sha256', $file['bytes']);
                abort_unless($disk->put($directory.'/'.$index.'.pdf', $file['bytes']), 500, 'Die Anhangsvorschau konnte nicht gespeichert werden.');
                $parts[] = ['name' => $file['name'], 'sha256' => $hash];
                $artifacts[$index] = ['file_path' => $directory.'/'.$index.'.pdf', 'storage_disk' => 'local'];
            }
            $messages = $rows = [];
            foreach ($source['people'] as $person) {
                $key = $person['participant_id'];
                $messages[$key] = $this->messages->render($source, $person, array_column($parts, 'name'));
                $rows[] = ['key' => $key, 'student_id' => $person['schooltool_person_id'] ? (int) $person['schooltool_person_id'] : null,
                    'previously_sent_at' => $source['prior'][$person['schooltool_person_id']] ?? null,
                    'content_hash' => SchooltoolAssessmentJson::digest([$source['sender'], $source['reply_to'], $messages[$key], $parts]),
                    'state' => 'draft', 'accepted_at' => null];
            }
            $draft = ['from' => (new Address($source['sender'], $source['sender_name']))->toString(),
                'sender' => $source['sender'], 'sender_name' => $source['sender_name'], 'reply_to' => $source['reply_to'],
                'stream' => $source['stream'], 'messages' => $messages, 'files' => $parts];
            $encoded = Crypt::encryptString(json_encode($draft, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            abort_unless($disk->put($directory.'/draft.json', $encoded), 500, 'Die E-Mail-Vorschau konnte nicht gespeichert werden.');
            $artifacts['draft'] = ['file_path' => $directory.'/draft.json', 'storage_disk' => 'local'];
            $state = ['id' => $id, 'state' => 'draft', 'source_hash' => SchooltoolAssessmentJson::digest($source),
                'artifacts' => $artifacts,
                'test_ids' => collect($source['mc'])->flatten(1)->pluck('test_id')->unique()->values()->all(),
                'draft_hash' => hash('sha256', $encoded), 'transport' => $transport, 'actor_id' => (int) $actor->id,
                'prepared_at' => now()->toIso8601String(), 'withdrawn_at' => null, 'recipients' => $rows];
            DB::transaction(function () use ($work, $slot, $state, $previous): void {
                $locked = TeachingCourseWork::query()->lockForUpdate()->findOrFail($work->id);
                $locked->assertStudentReleaseOpen();
                $status = $locked->status ?? [];
                $prior = $status['assignment_mail']['batches'][$slot] ?? null;
                abort_if(($prior['id'] ?? null) !== $previous || ($prior && (! $prior['withdrawn_at']
                    || collect($prior['recipients'])->contains(fn ($row) => in_array($row['state'], ['sending', 'uncertain', 'queued'], true)))),
                    409, 'Es gibt eine neuere oder noch offene Vorbereitung. Bitte die Vorschau öffnen.');
                if ($prior) {
                    $status['assignment_mail']['history'][$prior['id']] = $prior;
                }
                $history = collect($status['assignment_mail']['history'] ?? [])->where('transport', $state['transport'])
                    ->flatMap(fn ($batch) => $batch['recipients'])->where('state', 'accepted')->groupBy('key');
                foreach ($state['recipients'] as &$row) {
                    $receipt = $history->get($row['key'])?->firstWhere('content_hash', $row['content_hash']);
                    if ($receipt) {
                        $row = [...$row, 'state' => 'already_sent', 'provider_id' => $receipt['provider_id'], 'accepted_at' => $receipt['accepted_at']];
                    }
                }
                unset($row);
                $status['assignment_mail']['batches'][$slot] = $state;
                $locked->update(['status' => $status]);
            });
        } catch (Throwable $exception) {
            $disk->deleteDirectory($directory);
            throw $exception;
        }
    }

    /** @return array{name:string,bytes:string} */
    public function attachment(TeachingCourseWork $work, string $slot, string $id, int $index): array
    {
        $batch = $this->batch($work, $slot, $id);
        $draft = $this->readDraft($work, $batch);
        abort_unless(isset($draft['files'][$index]), 404);

        return ['name' => $draft['files'][$index]['name'], 'bytes' => $this->pdfBytes($work, $batch, $index, $draft['files'][$index]['sha256'])];
    }

    private function pdfBytes(TeachingCourseWork $work, array $batch, int $index, string $digest): string
    {
        $bytes = Storage::disk('local')->get($this->artifact($work, $batch, $index.'.pdf'));
        abort_unless(is_string($bytes) && hash_equals($digest, hash('sha256', $bytes)), 409, 'Die gespeicherte Aufgaben-PDF ist nicht mehr vollständig.');

        return $bytes;
    }

    public function queue(TeachingCourseWork $work, User $actor, string $slot, string $id, bool $includePreviouslySent = false): void
    {
        $batch = $this->batch($work, $slot, $id);
        $this->assertSource($work, $batch, $slot, $actor);
        $this->readDraft($work, $batch);
        if ($batch['transport'] === 'postmark') {
            abort_unless(filled(config('services.postmark.token')), 422, 'Der Postmark-Versandzugang ist nicht konfiguriert.');
        } else {
            $this->mailpit->assertSafe();
        }
        // DatabaseQueue inserts each job on the same database transaction as the recipient states.
        abort_unless(config('queue.connections.database.driver') === 'database'
            && (config('queue.connections.database.connection') ?: DB::getDefaultConnection()) === $work->getConnection()->getName(),
            503, 'Die Versandwarteschlange muss dieselbe Datenbank wie die Arbeit verwenden.');
        $this->change($work->id, $slot, $id, function (array &$batch, TeachingCourseWork $locked) use ($work, $slot, $id, $includePreviouslySent): void {
            $locked->assertStudentReleaseOpen();
            abort_if($batch['withdrawn_at'], 409, 'Der Versand ist gestoppt.');
            foreach ($batch['recipients'] as &$row) {
                if ($row['state'] === 'already_sent') {
                    $row['state'] = 'accepted';

                    continue;
                }
                if (! in_array($row['state'], ['draft', 'rejected'], true)) {
                    continue;
                }
                if ($batch['transport'] === 'postmark' && ! empty($row['previously_sent_at']) && ! $includePreviouslySent) {
                    continue;
                }
                $row['state'] = 'queued';
                unset($row['error']);
                SendTeachingWorkAssignments::dispatch($work->id, $slot, $id, $row['key'])
                    ->onConnection('database')->onQueue(CreateTeachingAssessment::QUEUE)->beforeCommit();
            }
            unset($row);
        });
    }

    public function sendOne(int $workId, string $slot, string $id, string $key): void
    {
        $work = TeachingCourseWork::query()->find($workId);
        $batch = $work?->status['assignment_mail']['batches'][$slot] ?? [];
        if (($batch['id'] ?? null) !== $id || $batch['withdrawn_at']
            || (collect($batch['recipients'])->firstWhere('key', $key)['state'] ?? null) !== 'queued') {
            return;
        }
        try {
            $actor = User::query()->findOrFail($batch['actor_id']);
            $draft = $this->readDraft($work, $batch);
            $message = $draft['messages'][$key];
            $files = [];
            foreach ($draft['files'] as $i => $file) {
                $bytes = $this->pdfBytes($work, $batch, $i, $file['sha256']);
                $files[] = ['Name' => $file['name'], 'ContentType' => 'application/pdf', 'Content' => base64_encode($bytes)];
            }
            if (! $this->mark($workId, $slot, $id, $key, ['queued'], ['state' => 'sending'],
                confirm: fn ($locked, $current) => $this->assertSource($locked, $current, $slot, $actor))) {
                return;
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->mark($workId, $slot, $id, $key, ['queued'], ['state' => 'rejected',
                'error' => 'Die Daten oder Unterlagen der Vorschau sind nicht mehr aktuell. Bitte die Vorbereitung erneuern.']);

            return;
        }
        $tag = $this->tag($id, $key);
        try {
            if ($batch['transport'] === 'mailpit') {
                $provider = $this->mailpit->send(['From' => ['Email' => $draft['sender'], 'Name' => $draft['sender_name']],
                    'ReplyTo' => [['Email' => $draft['reply_to']]], 'To' => [['Email' => $message['to']]],
                    'Subject' => $message['subject'], 'Text' => $message['text'], 'HTML' => $message['html'],
                    'Headers' => ['Message-ID' => '<'.$tag.'@schooltool.test>'], 'Tags' => ['Schooltool-Aufgaben'],
                    'Attachments' => array_map(fn ($f) => ['Filename' => $f['Name'],
                        'Content' => $f['Content'], 'ContentType' => $f['ContentType']], $files)]);
                $saved = $this->mailpit->message($provider);
                abort_unless(($saved['Subject'] ?? null) === $message['subject'] && count($saved['To'] ?? []) === 1
                    && strcasecmp($saved['To'][0]['Address'] ?? '', $message['to']) === 0, 502);
                $result = ['state' => 'accepted', 'provider_id' => $provider, 'accepted_at' => now()->toIso8601String()];
            } else {
                $response = $this->postmark()->post('/email', ['From' => $draft['from'], 'ReplyTo' => $draft['reply_to'],
                    'To' => $message['to'], 'Subject' => $message['subject'], 'TextBody' => $message['text'], 'HtmlBody' => $message['html'],
                    'MessageStream' => $draft['stream'], 'Tag' => $tag, 'TrackOpens' => false, 'TrackLinks' => 'None', 'Attachments' => $files]);
                $data = $response->json() ?: [];
                if ($response->successful() && ($data['ErrorCode'] ?? null) === 0 && Str::isUuid($data['MessageID'] ?? '')
                    && strcasecmp((string) ($data['To'] ?? ''), $message['to']) === 0 && filled($data['SubmittedAt'] ?? null)) {
                    $result = ['state' => 'accepted', 'provider_id' => $data['MessageID'], 'accepted_at' => CarbonImmutable::parse($data['SubmittedAt'])->toIso8601String()];
                } elseif ($response->clientError() && is_int($data['ErrorCode'] ?? null) && $data['ErrorCode'] > 0) {
                    $result = ['state' => 'rejected', 'error' => 'Postmark hat die Nachricht abgelehnt ('.$data['ErrorCode'].').'];
                } else {
                    $result = $this->uncertain();
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            $result = $this->uncertain();
        }
        $this->mark($workId, $slot, $id, $key, ['sending', 'uncertain'], $result);
    }

    public function reconcile(TeachingCourseWork $work, string $slot, string $id): void
    {
        $batch = $this->batch($work, $slot, $id);
        $draft = $this->readDraft($work, $batch);
        foreach ($batch['recipients'] as $row) {
            if ($row['state'] !== 'uncertain') {
                continue;
            }
            $tag = $this->tag($id, $row['key']);
            $to = $draft['messages'][$row['key']]['to'];
            if ($batch['transport'] === 'mailpit') {
                $this->mailpit->assertSafe();
                $provider = $this->mailpit->findMessage($tag.'@schooltool.test');
                $saved = $provider ? $this->mailpit->message($provider) : null;
                if (! $saved || count($saved['To'] ?? []) !== 1 || strcasecmp($saved['To'][0]['Address'] ?? '', $to) !== 0
                    || ($saved['Subject'] ?? null) !== $draft['messages'][$row['key']]['subject']) {
                    continue;
                }
                $time = $saved['Date'] ?? null;
            } else {
                $rows = $this->postmark()->get('/messages/outbound', ['tag' => $tag, 'recipient' => $to, 'messagestream' => $draft['stream'],
                    'count' => 2, 'offset' => 0])->throw()->json('Messages');
                $match = is_array($rows) && count($rows) === 1 ? $rows[0] : [];
                $provider = $match['MessageID'] ?? null;
                if (! Str::isUuid($provider ?? '') || ($match['Tag'] ?? null) !== $tag || count($match['To'] ?? []) !== 1
                    || strcasecmp($match['To'][0]['Email'] ?? '', $to) !== 0 || ($match['Subject'] ?? null) !== $draft['messages'][$row['key']]['subject']) {
                    continue;
                }
                $time = $match['ReceivedAt'] ?? null;
            }
            if (filled($time)) {
                $this->mark($work->id, $slot, $id, $row['key'], ['uncertain'],
                    ['state' => 'accepted', 'provider_id' => $provider, 'accepted_at' => CarbonImmutable::parse($time)->toIso8601String()]);
            }
        }
    }

    public function failed(int $workId, string $slot, string $id, string $key): void
    {
        $this->mark($workId, $slot, $id, $key, ['sending'], $this->uncertain());
        $this->mark($workId, $slot, $id, $key, ['queued'], ['state' => 'rejected', 'error' => 'Der Auftrag wurde vor der Einlieferung unterbrochen.']);
    }

    public function undo(TeachingCourseWork $work, string $slot, string $id, string $action): void
    {
        $discarded = $this->change($work->id, $slot, $id, function (?array &$batch, TeachingCourseWork $locked) use ($action): bool {
            $locked->assertStudentReleaseOpen();
            if ($action === 'discard') {
                abort_if(collect($batch['recipients'])->contains(fn ($row) => ! in_array($row['state'], ['draft', 'already_sent', 'rejected'], true)),
                    409, 'Versandbelege bleiben erhalten. Bitte den Versand stoppen.');
                $batch = null;

                return true;
            }
            $batch['withdrawn_at'] = $action === 'withdraw' ? now()->toIso8601String() : null;
            if ($action === 'withdraw') {
                foreach ($batch['recipients'] as &$row) {
                    if ($row['state'] === 'queued') {
                        $row['state'] = 'draft';
                    }
                }
                unset($row);
            }

            return false;
        });
        if ($discarded) {
            Storage::disk('local')->deleteDirectory($this->directory($work->id, $id));
        }
    }

    private function assertSource(TeachingCourseWork $work, array $batch, string $slot, User $actor): void
    {
        $work->assertStudentReleaseOpen();
        $source = $this->messages->source($work, $actor, $slot === 'all' ? null : $slot, $batch['transport']);
        abort_if(collect($source['mc'])->flatten(1)->contains(fn ($test) => ! $test['available']), 409,
            'Bitte den zugeordneten Multiple-Choice-Test und seine Zugänge vor dem E-Mail-Versand freigeben.');
        abort_unless((int) $batch['actor_id'] === (int) $actor->id && hash_equals($batch['source_hash'], SchooltoolAssessmentJson::digest($source)),
            409, 'Empfänger, Angaben oder Abgabeweg wurden geändert. Bitte eine neue E-Mail-Vorbereitung erstellen.');
    }

    private function mark(int $workId, string $slot, string $id, string $key, array $allowed, array $next, ?\Closure $confirm = null): bool
    {
        return (bool) $this->change($workId, $slot, $id, function (array &$batch, TeachingCourseWork $locked) use ($key, $allowed, $next, $confirm): bool {
            foreach ($batch['recipients'] as &$row) {
                if ($row['key'] === $key && in_array($row['state'], $allowed, true) && (! $confirm || ! $batch['withdrawn_at'])) {
                    if ($confirm) {
                        $confirm($locked, $batch);
                    }
                    unset($row['error']);
                    $row = [...$row, ...$next];

                    return true;
                }
            }

            return false;
        }, ignoreMissing: true);
    }

    private function change(int $workId, string $slot, string $id, \Closure $operation, bool $ignoreMissing = false): mixed
    {
        return DB::transaction(function () use ($workId, $slot, $id, $operation, $ignoreMissing): mixed {
            $work = TeachingCourseWork::query()->lockForUpdate()->find($workId);
            $status = $work?->status ?? [];
            $batch = $status['assignment_mail']['batches'][$slot] ?? null;
            if (! $batch || $batch['id'] !== $id) {
                abort_unless($ignoreMissing, 409, 'Die Vorbereitung hat sich geändert. Bitte neu öffnen.');

                return null;
            }
            $result = $operation($batch, $work);
            if ($batch === null) {
                unset($status['assignment_mail']['batches'][$slot]);
            } else {
                $batch['state'] = self::batchState($batch);
                $status['assignment_mail']['batches'][$slot] = $batch;
            }
            $work->update(['status' => $status]);

            return $result;
        });
    }

    private function batch(TeachingCourseWork $work, string $slot, string $id): array
    {
        $batch = $work->status['assignment_mail']['batches'][$slot] ?? null;
        abort_unless(($batch['id'] ?? null) === $id, 409, 'Bitte die aktuelle E-Mail-Vorschau öffnen.');

        return $batch;
    }

    private function readDraft(TeachingCourseWork $work, array $batch): array
    {
        $content = Storage::disk('local')->get($this->artifact($work, $batch, 'draft.json'));
        abort_unless(is_string($content) && hash_equals($batch['draft_hash'], hash('sha256', $content)),
            409, 'Die vorbereiteten Nachrichten können nicht bestätigt werden.');

        return json_decode(Crypt::decryptString($content), true, 32, JSON_THROW_ON_ERROR);
    }

    private function artifact(TeachingCourseWork $work, array $batch, string $name): string
    {
        $file = $batch['artifacts'][pathinfo($name, PATHINFO_FILENAME)] ?? [];
        $path = $file['file_path'] ?? '';
        $schoolId = $work->teachingCourse->school_id;
        $expected = $this->directory($work->id, $batch['id']).'/'.$name;
        abort_unless(($file['storage_disk'] ?? null) === 'local' && ($path === $expected
            || preg_match('~\Ateaching/(?:assignment-mail/'.$schoolId.'/restored/[a-f0-9-]{36}[-a-zA-Z0-9.]+|personal_restores/[a-f0-9-]{36})\z~', $path)),
            409, 'Die gespeicherte E-Mail-Vorschau ist dieser Arbeit nicht mehr zugeordnet.');

        return $path;
    }

    private static function batchState(array $batch): string
    {
        $states = array_count_values(array_column($batch['recipients'], 'state'));

        return match (true) {
            isset($states['sending']) || isset($states['queued']) => 'sending',
            isset($states['uncertain']) => 'uncertain',
            count($states) === 1 && isset($states['accepted']) => 'sent',
            isset($states['accepted']) || isset($states['rejected']) => 'partial',
            default => 'draft',
        };
    }

    /** Private files keyed by their location inside the work status for both backup formats. */
    public static function artifactReferences(array $status): array
    {
        $files = [];
        foreach (['batches', 'history'] as $group) {
            foreach ($status['assignment_mail'][$group] ?? [] as $key => $batch) {
                abort_unless($group === 'history' ? Str::isUuid($key) : preg_match('/\A(?:all|\d{4}-\d{2}-\d{2})\z/', $key), 422);
                foreach ($batch['artifacts'] ?? [] as $index => $file) {
                    abort_unless($index === 'draft' || ctype_digit((string) $index), 422);
                    $files["assignment_mail.{$group}.{$key}.artifacts.{$index}"] = $file;
                }
            }
        }

        return $files;
    }

    public static function recoverReceipts(array &$status, array $studentMap, bool $sameSchoolAccounts = false): void
    {
        foreach (['history', 'batches'] as $group) {
            foreach ($status['assignment_mail'][$group] ?? [] as $index => $batch) {
                foreach ($batch['recipients'] as &$row) {
                    if ($studentMap !== [] && isset($row['student_id'])) {
                        $row['student_id'] = $studentMap[$row['student_id']] ?? ($sameSchoolAccounts ? $row['student_id'] : null);
                    }
                    if ($row['state'] === 'queued' || $row['state'] === 'sending') {
                        $row['state'] = $row['state'] === 'sending' ? 'uncertain' : 'rejected';
                        $row['error'] = 'Der frühere Versandlauf wurde bei der Wiederherstellung unterbrochen.';
                    }
                }
                unset($row);
                $batch['state'] = self::batchState($batch);
                $status['assignment_mail'][$group][$index] = $batch;
            }
        }
    }

    private function directory(int $workId, string $id): string
    {
        abort_unless(Str::isUuid($id), 409);

        return 'teaching/assignment-mail/'.$workId.'/'.$id;
    }

    private function tag(string $id, string $key): string
    {
        return 'work-'.hash('sha256', $id.':'.$key);
    }

    private function uncertain(): array
    {
        return ['state' => 'uncertain', 'error' => 'Die Einlieferung ist ungeklärt. Den Versandbeleg prüfen, bevor erneut versandt wird.'];
    }

    private function postmark(): PendingRequest
    {
        return Http::baseUrl('https://api.postmarkapp.com')->withHeaders(['X-Postmark-Server-Token' => (string) config('services.postmark.token')])
            ->acceptJson()->withoutRedirecting()->connectTimeout(4)->timeout(12);
    }
}
