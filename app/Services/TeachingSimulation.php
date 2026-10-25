<?php

namespace App\Services;

use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;
use App\Models\User;
use App\Support\SchooltoolAssessmentJson;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use ZipArchive;

class TeachingSimulation
{
    public function __construct(private TeachingWorkAiExport $export, private DropboxModuleLinks $modules,
        private DropboxWorkImport $dropbox, private DropboxFolderContents $folders,
        private TeachingSimulationAnswers $answers, private TeachingSimulationMailpit $mailpit) {}

    public function create(TeachingCourseWork $work, User $actor, string $profile, ?string $date): array
    {
        $this->local();
        $this->mailpit->assertSafe();
        abort_unless(in_array($profile, ['good', 'mixed'], true), 422, 'Bitte ein Testszenario auswählen.');
        $context = $this->export->buildSubmissionCollection($work, $actor, false, $date)['context'];
        $participants = $context['course_roster']['participants'];
        abort_unless($context['course_roster']['complete'] && count($participants) > 0 && count($participants) <= 35, 422,
            'Bitte eine vollständige Prüfungsgruppe mit höchstens 35 Personen auswählen.');
        foreach ($participants as $person) {
            abort_unless(filter_var($person['email'] ?? '', FILTER_VALIDATE_EMAIL), 422,
                'Für alle ausgewählten Personen wird eine belegte E-Mail-Adresse benötigt.');
        }
        $connection = $this->dropbox->connection($actor);
        $folder = $context['exercise_folder'] ?? null;
        if (! $folder && ($link = $this->modules->current($work))) {
            $exercise = $this->modules->exercise($connection, $work, $link, $date, refresh: true);
            $folder = $exercise['status'] === 'found' ? $exercise['folder'] : null;
        }
        $folderId = $folder['id'] ?? $context['folder_binding']['dropbox_folder_id'] ?? null;
        abort_unless(is_string($folderId), 422, 'Zuerst den bestehenden Leistungsfeststellungsordner bestätigen.');
        $task = $this->assignment($connection, $folderId);
        $assignment = (new Parser)->parseContent($task['bytes'])->getText();
        $variants = $this->answers->generate($assignment);
        preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $assignment, $matches);
        $addresses = array_values(array_unique($matches[0]));
        $teacher = $addresses[0] ?? $actor->email;
        abort_unless(filter_var($teacher, FILTER_VALIDATE_EMAIL), 422, 'Lehreradresse nicht eindeutig bestätigt.');
        $id = (string) Str::uuid();
        $root = $this->root($actor->id, $id);
        $disk = Storage::disk('local');
        $disk->makeDirectory($root);
        $disk->put($root.'/Aufgabenstellung.pdf', $task['bytes']);
        $disk->put($root.'/Aufgabenstellung.txt', $assignment);
        $main = $context['work']['main_deadline'];
        $deadline = ($main['date'] ?? null) && ($main['time'] ?? null)
            ? CarbonImmutable::parse($main['date'].' '.$main['time'], 'Europe/Vienna') : now()->addHour()->toImmutable();
        $run = ['schema_version' => 1, 'kind' => 'schooltool.teaching-simulation', 'simulation' => true,
            'id' => $id, 'owner_id' => $actor->id, 'work_id' => $work->id, 'course_id' => $work->teaching_course_id,
            'title' => $work->title, 'course_title' => $context['course']['title'], 'profile' => $profile,
            'created_at' => now()->toISOString(), 'teacher_email' => $teacher, 'copy_email' => $addresses[1] ?? null,
            'source' => ['name' => $task['name'], 'sha256' => hash('sha256', $task['bytes']), 'folder_id' => $folderId],
            'simulated_deadline' => $deadline->toIso8601String(), 'variants' => $variants, 'rows' => [], 'history' => []];
        $scenarios = ['good', 'partial', 'weak', 'missing_attachment', 'no_submission', 'late', 'wrong_assignment'];
        foreach ($participants as $index => $person) {
            $scenario = $profile === 'good' ? 'good' : $scenarios[$index % count($scenarios)];
            $row = ['participant_id' => $person['participant_id'], 'person' => array_intersect_key($person,
                array_flip(['first_name', 'last_name', 'email', 'class_name', 'schooltool_person_id'])),
                'scenario' => $scenario, 'versions' => []];
            $row['versions'][] = $this->version($run, $row, 1, $scenario, $deadline);
            $run['rows'][] = $row;
        }
        $run['history'][] = ['event' => 'prepared', 'at' => now()->toISOString()];
        $this->save($actor->id, $run);

        return $this->public($run);
    }

    public function listing(int $owner, ?int $workId = null, ?int $courseId = null): array
    {
        $this->local();
        $runs = [];
        foreach (Storage::disk('local')->directories('teaching-simulator/runs/'.$owner) as $directory) {
            if (Storage::disk('local')->exists($directory.'/run.json')) {
                $run = json_decode(Storage::disk('local')->get($directory.'/run.json'), true, 64, JSON_THROW_ON_ERROR);
                if (($run['owner_id'] ?? null) === $owner && ($run['simulation'] ?? null) === true
                    && ($workId === null || ($run['work_id'] ?? null) === $workId)
                    && ($courseId === null || ($run['course_id'] ?? null) === $courseId)) {
                    $runs[] = $this->public($run);
                }
            }
        }
        usort($runs, fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return array_slice($runs, 0, 30);
    }

    public function collectionStatus(TeachingCourseWork $work, int $owner, array $studentIds): ?array
    {
        if (! app()->isLocal()) {
            return null;
        }
        $run = $this->listing($owner, (int) $work->id, (int) $work->teaching_course_id)[0] ?? null;
        if (! $run || ! Str::isUuid($run['id'] ?? '')) {
            return null;
        }
        $expected = array_values(array_unique(array_map('intval', $studentIds)));
        $actual = array_map(fn (array $row): int => (int) ($row['person']['schooltool_person_id'] ?? 0), $run['rows']);
        sort($expected);
        sort($actual);
        if ($expected === [] || $expected !== $actual || in_array(0, $actual, true)) {
            return null;
        }
        $collected = 0;
        $missing = 0;
        $times = [];
        $collectedBefore = collect($run['history'])->contains(fn (array $event): bool => ($event['event'] ?? null) === 'simulation_collected');
        $disk = Storage::disk('local');
        $prefix = $this->root($owner, $run['id']).'/Abgaben/';
        foreach ($run['rows'] as $row) {
            $version = $row['versions'][array_key_last($row['versions'])];
            if ($version['scenario'] === 'no_submission') {
                $missing += $collectedBefore ? 1 : 0;

                continue;
            }
            if (! $version['mailpit_id'] || ! $version['collected_at'] || strtotime($version['collected_at']) === false
                || strtotime($version['collected_at']) > time() + 5) {
                continue;
            }
            $files = [['path' => $version['saved_message'] ?? '', 'sha256' => $version['message_sha256'] ?? ''], ...($version['saved_attachments'] ?? [])];
            $verified = count($version['saved_attachments'] ?? []) === count($version['attachments']);
            foreach ($files as $file) {
                $path = $file['path'] ?? '';
                if (! str_starts_with($path, $prefix) || str_contains($path, '..') || str_contains($path, '\\')
                    || ! preg_match('/\A[a-f0-9]{64}\z/', $file['sha256'] ?? '') || ! $disk->exists($path)
                    || ! hash_equals($file['sha256'], hash('sha256', $disk->get($path)))) {
                    $verified = false;

                    break;
                }
            }
            if ($verified) {
                $collected++;
                $times[] = $version['collected_at'];
            }
        }
        usort($times, fn (string $a, string $b): int => strtotime($b) <=> strtotime($a));
        $complete = $collected > 0 && $collected + $missing === count($actual);
        $checks = array_column(array_values(array_filter($run['history'], fn (array $event): bool => ($event['event'] ?? null) === 'simulation_collected')), 'at');
        usort($checks, fn (string $a, string $b): int => strcmp($b, $a));

        return ['origin' => 'mailpit_simulation', 'simulation' => true, 'run_id' => $run['id'],
            'work_id' => $work->id, 'course_id' => $work->teaching_course_id, 'student_ids' => $actual,
            'collected_count' => $collected, 'non_submission_count' => $missing, 'processed_count' => $collected + $missing,
            'complete' => $complete, 'completed_at' => $complete ? $times[0] : null, 'last_collected_at' => $times[0] ?? null,
            'last_check_at' => $checks[0] ?? null,
            'evaluation' => $this->evaluationStatus($owner, $run)];
    }

    private function evaluationStatus(int $owner, array $run): ?array
    {
        $disk = Storage::disk('local');
        $root = $this->root($owner, $run['id']);
        $directory = $root.'/Auswertungen';
        if (! $disk->exists($directory.'/Laufabschluss_Simulation.json')) {
            return null;
        }
        try {
            $receipt = json_decode($disk->get($directory.'/Laufabschluss_Simulation.json'), true, 64, JSON_THROW_ON_ERROR);
            if (($receipt['kind'] ?? null) !== 'schooltool.simulation-grading-completion' || ($receipt['simulation'] ?? null) !== true
                || ($receipt['run_id'] ?? null) !== $run['id'] || ($receipt['work_id'] ?? null) !== $run['work_id']
                || ($receipt['course_id'] ?? null) !== $run['course_id']) {
                return null;
            }
            foreach (['Bewertungsdaten_Simulation.json' => 'data_sha256', 'PDF-Freigabe_Simulation.json' => 'pdf_release_sha256'] as $file => $hashKey) {
                if (! $disk->exists($directory.'/'.$file) || ! preg_match('/\A[a-f0-9]{64}\z/', $receipt[$hashKey] ?? '')
                    || ! hash_equals($receipt[$hashKey], hash('sha256', $disk->get($directory.'/'.$file)))) {
                    return null;
                }
            }
            $data = json_decode($disk->get($directory.'/Bewertungsdaten_Simulation.json'), true, 64, JSON_THROW_ON_ERROR);
            $release = json_decode($disk->get($directory.'/PDF-Freigabe_Simulation.json'), true, 64, JSON_THROW_ON_ERROR);
            if (($data['exercise_id'] ?? null) !== 'simulation:'.$run['id'].':work:'.$run['work_id']
                || ($release['all_pages_rendered'] ?? null) !== true || ($release['technical_flags'] ?? null) !== []) {
                return null;
            }
            $records = collect($data['records'] ?? [])->keyBy('id');
            if ($records->count() !== count($run['rows']) || $records->count() !== count($data['records'] ?? [])) {
                return null;
            }
            $rows = [];
            $completed = 0;
            $partial = 0;
            $allCurrent = true;
            foreach ($run['rows'] as $row) {
                $record = $records->get($row['participant_id']);
                if (! $record) {
                    return null;
                }
                $version = $row['versions'][array_key_last($row['versions'])];
                $expected = $version['collected_at']
                    ? [$version['message_sha256'], ...array_column($version['saved_attachments'] ?? [], 'sha256')] : [];
                $graded = array_map(fn (string $path): string => $data['source_hashes'][$path] ?? '', $record['evaluated_submission_files'] ?? []);
                $expected = array_values(array_unique($expected));
                $graded = array_values(array_unique($graded));
                sort($expected);
                sort($graded);
                $currentVerified = true;
                $currentFiles = $version['collected_at']
                    ? [['path' => $version['saved_message'], 'sha256' => $version['message_sha256']], ...($version['saved_attachments'] ?? [])] : [];
                foreach ($currentFiles as $file) {
                    if (! $disk->exists($file['path']) || ! hash_equals($file['sha256'], hash('sha256', $disk->get($file['path'])))) {
                        $currentVerified = false;
                        break;
                    }
                }
                $state = $expected === $graded && $currentVerified ? ($record['evaluation_state'] ?? 'open') : 'open';
                $allCurrent = $allCurrent && $expected === $graded && $currentVerified;
                if ($state === 'complete') {
                    $pdf = $record['filename'] ?? '';
                    $pdf = str_ends_with($pdf, '.md') && basename($pdf) === $pdf ? substr($pdf, 0, -3).'.pdf' : '';
                    $path = $directory.'/'.$pdf;
                    $proof = collect($release['pdfs'] ?? [])->first(fn (array $item): bool => strcasecmp(str_replace('\\', '/', $item['path'] ?? ''), str_replace('\\', '/', $disk->path($path))) === 0);
                    if (! $pdf || ! $proof || ! $disk->exists($path)
                        || ! hash_equals($proof['sha256'] ?? '', hash('sha256', $disk->get($path)))
                        || ! is_string($record['evaluation_completed_at'] ?? null) || ! isset($record['total'])) {
                        $state = 'open';
                        $allCurrent = false;
                    }
                }
                $completed += $state === 'complete' ? 1 : 0;
                $partial += $state === 'partial' ? 1 : 0;
                $knownMinor = 0;
                $knownCriteria = 0;
                foreach ($record['criteria'] ?? [] as $criterion) {
                    $earned = $this->pointMinor($criterion['earned'] ?? null);
                    $maximum = $this->pointMinor($criterion['maximum'] ?? null);
                    if ($earned !== null && $maximum !== null && $earned <= $maximum) {
                        $knownMinor += $earned;
                        $knownCriteria++;
                    }
                }
                $maximumMinor = $this->pointMinor($data['maximum'] ?? null);
                $provisional = $state === 'partial' && $knownCriteria > 0 && $maximumMinor !== null && $knownMinor <= $maximumMinor
                    ? rtrim(rtrim(number_format($knownMinor / 100, 2, ',', ''), '0'), ',') : null;
                $pending = array_column(array_values(array_filter($record['criteria'] ?? [],
                    fn (array $criterion): bool => $this->pointMinor($criterion['earned'] ?? null) === null)), 'criterion');
                $rows[] = ['student_id' => (int) $row['person']['schooltool_person_id'],
                    'first_name' => $row['person']['first_name'], 'last_name' => $row['person']['last_name'],
                    'evaluation_completed_at' => $state === 'complete' ? $record['evaluation_completed_at'] : null,
                    'pdf_sha256' => $state === 'complete' ? $proof['sha256'] : null,
                    'person' => $row['person']['first_name'].' '.$row['person']['last_name'],
                    'state' => in_array($state, ['complete', 'partial'], true) ? $state : 'open',
                    'result' => $state === 'complete' ? $record['total'].' von '.$data['maximum'].' Punkten'
                        : ($state === 'partial' ? ($provisional !== null ? $provisional.' von '.$data['maximum'].' Punkten (vorläufig)' : 'Teilbewertung')
                            .($pending ? ' · Offen: '.implode(', ', $pending) : '') : 'Noch offen')];
            }

            $collections = array_values(array_filter(array_map(fn (array $row): ?string => $row['versions'][array_key_last($row['versions'])]['collected_at'] ?? null, $run['rows'])));
            $runComplete = false;
            if ($allCurrent && is_string($receipt['completed_at'] ?? null) && strtotime($receipt['completed_at']) !== false && $collections !== []) {
                $completedAt = CarbonImmutable::parse($receipt['completed_at']);
                $runComplete = $completedAt->lessThanOrEqualTo(now()->addSeconds(5))
                    && collect($collections)->every(fn (string $collection): bool => strtotime($collection) !== false && CarbonImmutable::parse($collection)->lessThanOrEqualTo($completedAt));
            }

            return ['simulation' => true, 'run_id' => $run['id'],
                'run_complete' => $runComplete,
                'run_completed_at' => $receipt['completed_at'] ?? null,
                'completed_count' => $completed, 'partial_count' => $partial,
                'open_count' => count($rows) - $completed - $partial, 'rows' => $rows];
        } catch (\JsonException) {
            return null;
        }
    }

    private function pointMinor(mixed $value): ?int
    {
        if (! is_string($value) || ! preg_match('/\A\d{1,7}(?:[,.]\d{1,2})?\z/', $value)) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', str_replace(',', '.', $value), 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public function withResultFiles(TeachingCourseWork $work, int $owner, array $studentIds, Closure $consume): mixed
    {
        $this->local();
        $status = $this->collectionStatus($work, $owner, $studentIds);
        abort_unless($status && $status['complete'] && $status['evaluation'] && ($status['evaluation']['run_complete'] ?? null) !== false && $status['evaluation']['completed_count'] > 0,
            422, 'Für diesen Testdurchlauf liegen noch keine bestätigten abgeschlossenen Bewertungen vor.');
        $disk = Storage::disk('local');
        $directory = $this->root($owner, $status['run_id']).'/Auswertungen';
        abort_unless($disk->exists($directory.'/Simulator-Bewertungen.json'), 422, 'Das Testbewertungspaket fehlt.');
        $package = json_decode($disk->get($directory.'/Simulator-Bewertungen.json'), true, 64, JSON_THROW_ON_ERROR);
        abort_unless(($package['kind'] ?? null) === 'schooltool.simulation-assessment' && ($package['simulation'] ?? null) === true
            && ($package['run_id'] ?? null) === $status['run_id'] && ($package['work_id'] ?? null) === $work->id
            && ($package['course_id'] ?? null) === $work->teaching_course_id
            && ($package['exercise_id'] ?? null) === 'simulation:'.$status['run_id'].':work:'.$work->id,
            422, 'Das Testbewertungspaket gehört nicht zu dieser Arbeit.');
        $data = json_decode($disk->get($directory.'/Bewertungsdaten_Simulation.json'), true, 64, JSON_THROW_ON_ERROR);
        $release = json_decode($disk->get($directory.'/PDF-Freigabe_Simulation.json'), true, 64, JSON_THROW_ON_ERROR);
        $rows = collect($status['evaluation']['rows'])->keyBy('student_id');
        $records = collect($data['records'])->keyBy('id');
        abort_unless(count($package['records'] ?? []) === $rows->count(), 422, 'Die Testbewertungen sind unvollständig.');
        foreach ($package['records'] as &$record) {
            $row = $rows->get((int) ($record['identity']['schooltool_person_id'] ?? 0));
            $graded = $records->get($record['participant_id'] ?? '');
            abort_unless($row && $graded && $row['state'] === $record['evaluation_state']
                && $this->pointMinor($graded['total'] ?? null) === ($record['total_minor'] ?? null),
                422, 'Die Abgaben oder Testbewertungen haben sich geändert. Bitte erneut bewerten.');
            $record['comment'] = 'Simulation · lokale Testbewertung'."\n".$record['comment'];
            unset($record['record_checksum']);
            $record['record_checksum'] = SchooltoolAssessmentJson::digest($record);
        }
        unset($record);
        // Only this authenticated local action can adapt a simulator package; the general importer still rejects it.
        $package['kind'] = 'schooltool.assessment';
        unset($package['simulation'], $package['work_id'], $package['course_id'], $package['run_id'], $package['live_import_allowed'], $package['package_checksum']);
        $package['package_checksum'] = SchooltoolAssessmentJson::digest($package);
        $uploads = [];
        foreach (array_filter([$package['overview_pdf'], ...array_column($package['records'], 'pdf')]) as $pdf) {
            $name = $pdf['filename'] ?? '';
            abort_unless($name !== '' && basename($name) === $name && ! str_contains($name, '\\') && str_ends_with($name, '.pdf'), 422);
            $path = $directory.'/'.$name;
            $proof = collect($release['pdfs'])->first(fn (array $item): bool => strcasecmp(str_replace('\\', '/', $item['path']), str_replace('\\', '/', $disk->path($path))) === 0);
            abort_unless($proof && $disk->exists($path) && hash_equals($proof['sha256'], $pdf['sha256'])
                && hash_equals($pdf['sha256'], hash('sha256', $disk->get($path))), 422, 'Ein freigegebenes Testbewertungs-PDF fehlt oder wurde geändert.');
            $uploads[] = new UploadedFile($disk->path($path), $name, 'application/pdf', null, true);
        }
        $temporary = tempnam(sys_get_temp_dir(), 'schooltool-test-results-');
        abort_unless($temporary !== false, 500);
        try {
            file_put_contents($temporary, json_encode($package, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            return $consume([], ['package' => new UploadedFile($temporary, 'Schooltool-Bewertungen.json', 'application/json', null, true), 'pdfs' => $uploads]);
        } finally {
            unlink($temporary);
        }
    }

    public function read(int $owner, string $id): array
    {
        $this->local();
        $path = $this->root($owner, $id).'/run.json';
        abort_unless(Storage::disk('local')->exists($path), 404);
        $run = json_decode(Storage::disk('local')->get($path), true, 64, JSON_THROW_ON_ERROR);
        abort_unless(($run['owner_id'] ?? null) === $owner && ($run['id'] ?? null) === $id && ($run['simulation'] ?? null) === true, 403);

        return $run;
    }

    public function gradingSource(TeachingCourseWork $work, int $owner, array $studentIds): ?array
    {
        $status = $this->collectionStatus($work, $owner, $studentIds);
        if (! $status) {
            return null;
        }
        abort_unless($status['complete'], 422, 'Zuerst die vorgesehenen Simulator-Rückgaben senden und vollständig einsammeln.');
        $run = $this->read($owner, $status['run_id']);
        $disk = Storage::disk('local');
        $root = $this->root($owner, $run['id']);
        $assignment = $disk->get($root.'/Aufgabenstellung.pdf');
        abort_unless(hash_equals($run['source']['sha256'], hash('sha256', $assignment)), 409, 'Das Aufgabenblatt des Simulators wurde verändert.');
        $inventory = [];
        foreach ($run['rows'] as $row) {
            $version = $row['versions'][array_key_last($row['versions'])];
            $inventory[] = ['participant_id' => $row['participant_id'], 'schooltool_person_id' => $row['person']['schooltool_person_id'],
                'message_file' => isset($version['saved_message']) ? $disk->path($version['saved_message']) : null,
                'collected_at' => $version['collected_at'], 'simulated_received_at' => $version['simulated_received_at'],
                'files' => array_map(fn (array $file): array => ['path' => $disk->path($file['path']), 'sha256' => $file['sha256']], $version['saved_attachments'] ?? [])];
        }

        return ['simulation' => true, 'run_id' => $run['id'], 'work_id' => $work->id, 'course_id' => $work->teaching_course_id,
            'assignment_pdf' => $disk->path($root.'/Aufgabenstellung.pdf'), 'assignment_sha256' => $run['source']['sha256'],
            'collection_completed_at' => $status['completed_at'], 'simulated_deadline' => $run['simulated_deadline'],
            'submissions' => $inventory, 'output_directory' => $disk->path($root.'/Auswertungen')];
    }

    public function public(array $run): array
    {
        unset($run['variants'], $run['owner_id']);

        return $run;
    }

    public function send(int $owner, string $id): array
    {
        return $this->locked($owner, $id, function (array $run) use ($owner): array {
            $this->mailpit->assertSafe();
            foreach ($run['rows'] as &$row) {
                $last = array_key_last($row['versions']);
                $version = &$row['versions'][$last];
                if ($version['scenario'] === 'no_submission' || $version['mailpit_id']) {
                    continue;
                }
                $existing = $this->mailpit->find($run['id'], $row['participant_id'], $version['number']);
                if ($existing) {
                    $version['mailpit_id'] = $existing;
                    $version['sent_at'] = now()->toISOString();
                    unset($version);

                    continue;
                }
                $headers = ['X-Schooltool-Simulation' => $run['id'], 'X-Schooltool-Work' => (string) $run['work_id'],
                    'X-Schooltool-Participant' => $row['participant_id'], 'X-Schooltool-Simulated-Received' => $version['simulated_received_at'],
                    'Message-ID' => '<sim.'.$run['id'].'.'.str_replace(':', '-', $row['participant_id']).'.v'.$version['number'].'@schooltool.test>'];
                $payload = ['From' => ['Email' => $row['person']['email'], 'Name' => $row['person']['first_name'].' '.$row['person']['last_name']],
                    'To' => [['Email' => $run['teacher_email']]], 'Cc' => $run['copy_email'] ? [['Email' => $run['copy_email']]] : [],
                    'Subject' => '[Simulation] '.$version['subject'], 'Text' => $version['message'], 'Headers' => $headers,
                    'Tags' => ['Schooltool-Simulation', 'Arbeit-'.$run['work_id'], $run['id']], 'Attachments' => []];
                foreach ($version['attachments'] as $file) {
                    $bytes = Storage::disk('local')->get($file['path']);
                    abort_unless(hash_equals($file['sha256'], hash('sha256', $bytes)), 409, 'Eine erzeugte Testdatei wurde nachträglich verändert.');
                    $payload['Attachments'][] = ['Filename' => $file['name'], 'Content' => base64_encode($bytes), 'ContentType' => $file['mime']];
                }
                $version['mailpit_id'] = $this->mailpit->send($payload);
                $version['sent_at'] = now()->toISOString();
                unset($version);
                $this->save($owner, $run);
            }
            unset($row);
            $run['history'][] = ['event' => 'mailpit_returns_sent', 'at' => now()->toISOString()];

            return $run;
        });
    }

    public function collect(int $owner, string $id): array
    {
        return $this->locked($owner, $id, function (array $run) use ($owner): array {
            $this->mailpit->assertSafe();
            foreach ($run['rows'] as &$row) {
                foreach ($row['versions'] as &$version) {
                    if (! $version['mailpit_id'] || $version['collected_at']) {
                        continue;
                    }
                    $message = $this->mailpit->message($version['mailpit_id']);
                    $raw = $this->mailpit->raw($version['mailpit_id']);
                    abort_unless(strcasecmp($message['From']['Address'] ?? $message['From']['Email'] ?? '', $row['person']['email']) === 0
                        && str_contains($raw, 'X-Schooltool-Simulation: '.$run['id'])
                        && count($message['Attachments'] ?? []) === count($version['attachments']),
                        409, 'Die Mailpit-Nachricht passt nicht zur erzeugten Testabgabe.');
                    $target = $this->root($owner, $run['id']).'/Abgaben/'.str_replace(':', '-', $row['participant_id']).'/v'.$version['number'];
                    Storage::disk('local')->makeDirectory($target);
                    $proofs = [];
                    foreach ($version['attachments'] as $file) {
                        $matches = array_values(array_filter($message['Attachments'], fn (array $attachment): bool => $attachment['FileName'] === $file['name']));
                        abort_unless(count($matches) === 1, 409, 'Ein tatsächlicher Mailpit-Anhang fehlt oder ist mehrdeutig.');
                        $bytes = $this->mailpit->part($version['mailpit_id'], (string) $matches[0]['PartID']);
                        abort_unless(hash_equals($file['sha256'], hash('sha256', $bytes)), 409, 'Mailpit-Anhang und erzeugte Testdatei weichen ab.');
                        Storage::disk('local')->put($target.'/'.$file['name'], $bytes);
                        $proofs[] = ['path' => $target.'/'.$file['name'], 'sha256' => $file['sha256']];
                    }
                    Storage::disk('local')->put($target.'/Nachricht.eml', $raw);
                    $version['collected_at'] = now()->toISOString();
                    $version['saved_message'] = $target.'/Nachricht.eml';
                    $version['message_sha256'] = hash('sha256', $raw);
                    $version['saved_attachments'] = $proofs;
                    Storage::disk('local')->put($target.'/Herkunft.json', json_encode(['simulation' => true, 'run_id' => $run['id'],
                        'participant_id' => $row['participant_id'], 'mailpit_id' => $version['mailpit_id'],
                        'actual_collected_at' => $version['collected_at'], 'simulated_received_at' => $version['simulated_received_at'],
                        'message_sha256' => $version['message_sha256'], 'files' => $proofs], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                }
                unset($version);
            }
            unset($row);
            $run['history'][] = ['event' => 'simulation_collected', 'at' => now()->toISOString()];

            return $run;
        });
    }

    public function revise(int $owner, string $id, string $participant): array
    {
        return $this->locked($owner, $id, function (array $run) use ($participant): array {
            $matches = array_keys(array_filter($run['rows'], fn (array $row): bool => $row['participant_id'] === $participant));
            abort_unless(count($matches) === 1, 422, 'Person gehört nicht zu diesem Simulationslauf.');
            $row = &$run['rows'][$matches[0]];
            abort_if(count($row['versions']) >= 5, 422, 'Pro Person sind höchstens fünf Testfassungen vorgesehen.');
            $row['versions'][] = $this->version($run, $row, count($row['versions']) + 1, 'good',
                CarbonImmutable::parse($run['simulated_deadline']));
            $run['history'][] = ['event' => 'revision_prepared', 'participant_id' => $participant, 'at' => now()->toISOString()];

            return $run;
        });
    }

    public function artifact(int $owner, string $id, string $path): string
    {
        $this->read($owner, $id);
        $root = $this->root($owner, $id).'/';
        abort_unless(str_starts_with($path, $root) && ! str_contains($path, '..') && ! str_contains($path, '\\')
            && Storage::disk('local')->exists($path) && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['pdf', 'zip', 'eml', 'json', 'txt'], true), 404);

        return Storage::disk('local')->path($path);
    }

    public function archive(int $owner, string $id): string
    {
        $this->read($owner, $id);
        $disk = Storage::disk('local');
        $root = $this->root($owner, $id);
        $target = $root.'/Simulation.zip';
        $zip = new ZipArchive;
        abort_unless($zip->open($disk->path($target), ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500);
        try {
            foreach ($disk->allFiles($root) as $file) {
                if ($file !== $target) {
                    $zip->addFile($disk->path($file), substr($file, strlen($root) + 1));
                }
            }
        } finally {
            $zip->close();
        }

        return $disk->path($target);
    }

    private function assignment(DropboxConnection $connection, string $folderId): array
    {
        $root = $this->folders->read($connection, $folderId);
        abort_if($root['cursor'], 422, 'Der Aufgabenordner ist für eine begrenzte Prüfung zu groß.');
        $locations = [$root];
        foreach ($root['entries'] as $entry) {
            if ($entry['type'] === 'folder' && in_array(mb_strtolower($entry['name']), ['angaben', 'quellunterlagen'], true)) {
                $locations[] = $this->folders->read($connection, $folderId, $entry['id']);
            }
        }
        $tasks = [];
        foreach ($locations as $location) {
            abort_if($location['cursor'], 422, 'Die Aufgabenquelle ist nicht vollständig aufgelistet.');
            foreach ($location['entries'] as $entry) {
                if ($entry['type'] === 'file' && preg_match('/\A(?:Aufgabenstellung|Leistungsfeststellung).*\.pdf\z/ui', $entry['name'])) {
                    $tasks[] = ['entry' => $entry, 'folder' => $location['current']['id']];
                }
            }
        }
        abort_unless(count($tasks) === 1, 422, 'Bitte genau ein eindeutiges Aufgabenblatt im bestehenden Leistungsfeststellungsordner bereitstellen.');
        abort_if(($tasks[0]['entry']['size'] ?? PHP_INT_MAX) > 5 * 1024 * 1024, 422, 'Das Aufgabenblatt überschreitet 5 MB.');

        return $this->folders->pdf($connection, $tasks[0]['folder'], $tasks[0]['entry']['id']);
    }

    private function version(array $run, array $row, int $number, string $scenario, CarbonImmutable $deadline): array
    {
        $quality = in_array($scenario, ['partial', 'weak'], true) ? $scenario : 'good';
        $variant = $run['variants'][$quality];
        if ($scenario === 'wrong_assignment') {
            $variant['answer'] = 'Die römische Republik und ihre politischen Ämter. Diese Testausarbeitung gehört zu einer anderen Aufgabe.';
            $variant['subject'] = 'Abgabe Geschichte';
            $variant['files'] = [];
        }
        $directory = $this->root($run['owner_id'], $run['id']).'/Erzeugt/'.str_replace(':', '-', $row['participant_id']).'/v'.$number;
        $files = $scenario === 'no_submission' ? [] : $this->answers->files($directory, $row['person'], $run['title'], $variant, $number);
        $identity = $row['person']['first_name'].' '.$row['person']['last_name']."\n".$row['person']['class_name'];

        return ['number' => $number, 'scenario' => $scenario, 'subject' => $variant['subject'],
            'message' => "Simulation – lokal erzeugte Testabgabe.\n\n".$variant['message']."\n\n".$identity,
            'artifacts' => $files, 'attachments' => $scenario === 'missing_attachment' ? [] : $files,
            'simulated_received_at' => ($scenario === 'late' ? $deadline->addMinutes(30) : $deadline->subMinutes(30))->toIso8601String(),
            'mailpit_id' => null, 'sent_at' => null, 'collected_at' => null];
    }

    private function locked(int $owner, string $id, callable $action): array
    {
        $this->local();

        return Cache::store('file')->lock('teaching-simulation:'.$owner.':'.$id, 180)->block(5, function () use ($owner, $id, $action): array {
            $run = $action($this->read($owner, $id));
            $this->save($owner, $run);

            return $this->public($run);
        });
    }

    private function save(int $owner, array $run): void
    {
        Storage::disk('local')->put($this->root($owner, $run['id']).'/run.json',
            json_encode($run, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function root(int $owner, string $id): string
    {
        abort_unless($owner > 0 && Str::isUuid($id), 404);

        return 'teaching-simulator/runs/'.$owner.'/'.$id;
    }

    private function local(): void
    {
        abort_unless(app()->isLocal(), 403, 'Der Leistungsfeststellungs-Simulator ist ausschließlich lokal verfügbar.');
    }
}
