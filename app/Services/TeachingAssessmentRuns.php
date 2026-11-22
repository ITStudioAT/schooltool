<?php

namespace App\Services;

use App\Jobs\CreateTeachingAssessment;
use App\Models\TeachingCourseWork;
use App\Models\User;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\Repository;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class TeachingAssessmentRuns
{
    public function __construct(private TeachingDirectAssessment $direct) {}

    public function show(TeachingCourseWork $work, User $actor, ?string $date): ?array
    {
        $run = $this->latest($work->id);
        if (! $run || $run['actor_id'] !== $actor->id || ($date !== null && $date !== $run['date'])) {
            return null;
        }

        return $this->visible($run);
    }

    public function progressForWork(TeachingCourseWork $work, User $actor, ?string $date): array
    {
        $run = $this->latest($work->id);
        $visible = $run && $run['actor_id'] === $actor->id
            && ($date === null || $date === $run['date'] || $this->isActive($run));

        return ['activity' => $this->summary($run),
            'run' => $visible ? Arr::only($run, ['id', 'date', 'state', 'queued_at', 'progress', 'message', 'finished_at']) : null];
    }

    /** @param list<int> $workIds */
    public function summaries(array $workIds): array
    {
        if ($workIds === []) {
            return [];
        }

        $keys = array_map($this->key(...), $workIds);
        $pointers = $this->state()->many($keys);
        $runKeys = [];
        foreach ($workIds as $index => $id) {
            $key = $keys[$index];
            if (is_string($pointers[$key] ?? null)) {
                $runKeys[$id] = $key.':'.$pointers[$key];
            }
        }
        $runs = $this->state()->many(array_values($runKeys));

        return array_map(fn (string $key): ?array => $this->summary($runs[$key] ?? null), $runKeys);
    }

    public function assertNoActiveCreation(TeachingCourseWork $work): void
    {
        abort_if($this->isActive($this->latest($work->id)), 409, 'Für diese Arbeit läuft noch ein Erstellungsauftrag.');
    }

    private function summary(?array $run): ?array
    {
        if ($run === null) {
            return null;
        }
        $summary = Arr::only($run, ['id', 'date', 'state', 'queued_at']);
        $result = $run['result'] ?? [];
        $points = $result['points'] ?? null;
        if ($run['state'] === 'completed' && ($result['status'] ?? null) === 'created'
            && ($result['scheduled_date'] ?? null) === $run['date'] && is_string($result['folder']['id'] ?? null)
            && (is_int($points) || is_float($points)) && is_finite((float) $points) && $points >= 0) {
            $summary['assignment_points'] = $points;
        }

        return $summary;
    }

    private function isActive(?array $run): bool
    {
        return in_array($run['state'] ?? null, ['queued', 'running'], true);
    }

    public function deleteExercise(TeachingCourseWork $work, User $actor, ?string $date, string $folderId, ?\Closure $progress = null): void
    {
        $cache = $this->state()->getStore();
        abort_unless($cache instanceof DatabaseStore && $cache->getConnection() === DB::connection(), 503,
            'Der Löschstand kann nicht zuverlässig gespeichert werden.');
        DB::transaction(function () use ($work, $actor, $date, $folderId): void {
            $work = TeachingCourseWork::query()->lockForUpdate()->findOrFail($work->id);
            $this->assertAccess($work, $actor);
            $run = $this->latest($work->id);
            abort_if(in_array($run['state'] ?? null, ['queued', 'running'], true), 409,
                'Für diese Arbeit läuft noch ein Erstellungsauftrag.');
            $pending = $this->pendingExerciseDeletion($work);
            abort_if($pending && $pending['folder']['id'] !== $folderId, 409, 'Zuerst die bisherige LF-Löschung abschließen.');
            $folder = $this->direct->exerciseDeletionCandidate($work, $actor, $date, $folderId);
            $record = ['folder' => $folder, 'date' => substr($folder['name'], 0, 10)];
            abort_unless(! $pending || $pending === $record, 409, 'Der Prüfungsordner der offenen LF-Löschung hat sich geändert.');
            if (! $pending) {
                abort_unless($this->state()->forever($this->key($work->id).':deletion', $record), 503, 'Der Löschstand konnte nicht gesichert werden.');
            }
        });

        DB::transaction(function () use ($work, $actor, $date, $folderId, $progress): void {
            $work = TeachingCourseWork::query()->lockForUpdate()->findOrFail($work->id);
            $this->assertAccess($work, $actor);
            $run = $this->latest($work->id);
            abort_if(in_array($run['state'] ?? null, ['queued', 'running'], true), 409,
                'Für diese Arbeit läuft noch ein Erstellungsauftrag.');
            abort_unless(($this->pendingExerciseDeletion($work)['folder']['id'] ?? null) === $folderId, 409,
                'Der Löschstand hat sich geändert. Bitte erneut prüfen.');
            $this->direct->deleteExercise($work, $actor, $date, $folderId, $progress);
            if (($run['result']['folder']['id'] ?? null) === $folderId) {
                $this->state()->forget($this->key($work->id));
            }
            $this->state()->forget($this->key($work->id).':deletion');
        });
    }

    public function pendingExerciseDeletion(TeachingCourseWork $work): ?array
    {
        return $this->state()->get($this->key($work->id).':deletion');
    }

    public function unlinkBlock(TeachingCourseWork $work): ?string
    {
        if ($this->pendingExerciseDeletion($work)) {
            return 'Die LF-Löschung ist noch nicht bestätigt. Bitte „Leistungsfeststellung löschen“ erneut ausführen.';
        }

        return in_array($this->latest($work->id)['state'] ?? null, ['queued', 'running'], true)
            ? 'Der Erstellungsstand ist noch nicht bestätigt. Bitte den Erstellungsauftrag prüfen.' : null;
    }

    /** @param array{date: ?string, points: float, mc_id: ?int}|array{generation_id: string, date: ?string} $input */
    public function start(TeachingCourseWork $work, User $actor, string $requestId, array $input): array
    {
        abort_unless($work->automationEnabled(), 422, 'Die automatische Beurteilung ist für diese Arbeit ausgeschaltet.');
        $queue = Queue::connection('database');
        $cache = $this->state()->getStore();
        abort_unless($queue instanceof DatabaseQueue && $queue->getDatabase() === DB::connection()
            && $cache instanceof DatabaseStore && $cache->getConnection() === DB::connection()
            && config('queue.connections.database.retry_after') > CreateTeachingAssessment::TIMEOUT, 503,
            'Die Hintergrundverarbeitung für Leistungsfeststellungen ist nicht bereit.');

        return DB::transaction(function () use ($work, $actor, $requestId, $input, $queue): array {
            $work = TeachingCourseWork::query()->lockForUpdate()->findOrFail($work->id);
            $this->assertAccess($work, $actor);
            abort_if($this->pendingExerciseDeletion($work), 409, 'Zuerst die offene LF-Löschung abschließen.');
            abort_if(isset($work->status['deletion_receipt']), 409, 'Zuerst die offene Löschung der Arbeit abschließen.');
            $alias = $this->state()->get($this->key($work->id).':request:'.$requestId);
            $existing = $this->get($work->id, is_string($alias) ? $alias : $requestId);
            if ($existing) {
                abort_unless($existing['actor_id'] === $actor->id && $existing['input'] === $input, 409, 'Der Auftrag ist bereits mit anderen Angaben gespeichert.');

                return $this->visible($existing);
            }
            $active = $this->latest($work->id);
            $date = $input['date'] ?? $work->date_for_all_groups?->format('Y-m-d');
            if ($active && in_array($active['state'], ['queued', 'running'], true)) {
                abort_unless($active['actor_id'] === $actor->id && $active['input'] === $input, 409, 'Für diese Arbeit läuft bereits eine andere Anlage.');
                $this->state()->put($this->key($work->id).':request:'.$requestId, $active['id'], now()->addDays(7));

                return $this->visible($active);
            }

            $run = ['id' => $requestId, 'request_id' => $requestId, 'actor_id' => $actor->id, 'date' => $date,
                'input' => $input, 'snapshot' => $this->snapshot($work), 'state' => 'queued', 'queued_at' => now()->toISOString(),
                'progress' => null, 'generation' => null, 'result' => null, 'message' => null];
            $this->save($work->id, $run);
            $this->state()->put($this->key($work->id), $run['id'], now()->addDays(7));
            $queue->push((new CreateTeachingAssessment($work->id, $run['id']))->beforeCommit(), queue: CreateTeachingAssessment::QUEUE);

            return $this->visible($run);
        });
    }

    public function execute(int $workId, string $runId): void
    {
        $run = DB::transaction(function () use ($workId, $runId): ?array {
            if (! TeachingCourseWork::query()->lockForUpdate()->find($workId)) {
                return null;
            }
            $queued = $this->get($workId, $runId);
            if (! $queued || $queued['state'] !== 'queued') {
                return null;
            }
            $queued['state'] = 'running';
            $this->save($workId, $queued);

            return $queued;
        });
        if (! $run) {
            return;
        }

        $report = function (string $event, ?string $step = null, mixed $details = null) use ($workId, &$run): void {
            $progress = &$run['progress'];
            switch ($event) {
                case 'start':
                    $progress['active'] = $step;
                    $progress['detail'] = is_string($details) ? $details : null;
                    break;
                case 'completed':
                    $progress['completed'][] = $step;
                    $progress['active'] = null;
                    $progress['detail'] = null;
                    break;
                case 'detail':
                    $progress['detail'] = $details;
                    break;
                case 'configure_mc':
                    if ($details === true && ! in_array('assessment_mc', $progress['steps'], true)) {
                        array_splice($progress['steps'], array_search('assessment_folder', $progress['steps'], true), 0, ['assessment_mc']);
                    }
                    break;
                case 'candidates':
                    $progress['labels'] = $details;
                    array_splice($progress['steps'], array_search('assessment_verify', $progress['steps'], true), 0, array_keys($details));
                    break;
                case 'replayed':
                    $progress['skipped'] = array_values(array_diff($progress['steps'], $progress['completed']));
                    $progress['active'] = null;
                    $progress['detail'] = null;
                    break;
            }
            $this->save($workId, $run);
        };

        try {
            $work = TeachingCourseWork::query()->findOrFail($workId);
            $actor = User::query()->findOrFail($run['actor_id']);
            $this->assertAccess($work, $actor);
            $this->assertSnapshot($work, $run);
            $input = $run['input'];
            $run['progress'] = ['state' => 'running', 'active' => null, 'completed' => [], 'skipped' => [], 'steps' => [], 'labels' => []];
            if (isset($input['generation_id'])) {
                $generationId = $input['generation_id'];
            } else {
                $run['progress']['steps'] = ['assessment_candidates', 'assessment_assignment', 'assessment_criteria', 'assessment_target'];
                $run['progress']['active'] = 'assessment_candidates';
                $this->save($workId, $run);
                $run['generation'] = $this->direct->preview($work, $actor, $input['date'], $input['points'], $input['mc_id'], true, $report);
                $generationId = $run['generation']['id'];
            }
            $work->refresh();
            $this->assertAccess($work, $actor->fresh());
            $this->assertSnapshot($work, $run);
            array_push($run['progress']['steps'], 'assessment_check', 'assessment_folder', 'assessment_documents', 'assessment_verify');
            $run['progress']['active'] = 'assessment_check';
            $this->save($workId, $run);
            $run['result'] = $this->direct->store($work, $actor, $generationId, $report);
            $run['state'] = 'completed';
            $run['progress']['state'] = 'done';
            $run['progress']['active'] = null;
        } catch (Throwable $failure) {
            $run['state'] = 'failed';
            if ($run['progress']) {
                $run['progress']['state'] = 'failed';
            }
            $run['message'] = $this->failureMessage($failure);
        }
        $run['finished_at'] = now()->toIso8601String();
        $this->save($workId, $run);
    }

    public function interrupt(int $workId, string $runId): void
    {
        $run = $this->get($workId, $runId);
        if ($run && in_array($run['state'], ['queued', 'running'], true)) {
            $run['state'] = 'interrupted';
            if ($run['progress']) {
                $run['progress']['state'] = 'interrupted';
            }
            $run['message'] = 'Der Hintergrundauftrag endete ohne bestätigtes Ergebnis. Erneut starten, um den vorhandenen Prüfungsordner zu prüfen und fortzusetzen.';
            $run['finished_at'] = now()->toIso8601String();
            $this->save($workId, $run);
        }
    }

    private function assertAccess(TeachingCourseWork $work, ?User $actor): void
    {
        $course = $work->teachingCourse;
        abort_unless($actor && $course && $actor->hasAnyRole(['admin', 'teaching_admin', 'teacher'])
            && (int) $actor->school_id === (int) $course->school_id && (int) $actor->schoolyear_id === (int) $course->schoolyear_id
            && ($actor->hasAnyRole(['admin', 'teaching_admin']) || (int) $actor->id === (int) $course->user_id), 403);
    }

    private function assertSnapshot(TeachingCourseWork $work, array $run): void
    {
        abort_unless($work->automationEnabled() && hash_equals($run['snapshot'], $this->snapshot($work)), 409,
            'Die Arbeit wurde seit der Bestätigung geändert. Bitte die Angaben prüfen und einen neuen Auftrag starten.');
    }

    private function snapshot(TeachingCourseWork $work): string
    {
        return hash('sha256', json_encode($work->getRawOriginal(), JSON_THROW_ON_ERROR));
    }

    private function failureMessage(Throwable $failure): string
    {
        if ($failure instanceof ValidationException) {
            return collect($failure->errors())->flatten()->first() ?: 'Die Angaben konnten nicht bestätigt werden.';
        }
        if ($failure instanceof HttpExceptionInterface && in_array($failure->getStatusCode(), [409, 422], true) && $failure->getMessage() !== '') {
            return $failure->getMessage();
        }
        logger()->warning('Teaching assessment background operation failed', ['exception_class' => $failure::class]);

        return 'Die Leistungsfeststellung konnte im Hintergrund nicht abgeschlossen werden.';
    }

    private function visible(array $run): array
    {
        return Arr::only($run, ['id', 'request_id', 'date', 'state', 'queued_at', 'progress', 'generation', 'result', 'message', 'finished_at']);
    }

    private function latest(int $workId): ?array
    {
        $id = $this->state()->get($this->key($workId));

        return is_string($id) ? $this->get($workId, $id) : null;
    }

    private function get(int $workId, string $runId): ?array
    {
        return $this->state()->get($this->key($workId).':'.$runId);
    }

    private function save(int $workId, array $run): void
    {
        $this->state()->put($this->key($workId).':'.$run['id'], $run, now()->addDays(7));
    }

    private function state(): Repository
    {
        return Cache::store('database');
    }

    private function key(int $workId): string
    {
        return 'teaching-assessment-run:'.$workId;
    }
}
